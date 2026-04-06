<?php

namespace App\Services;

use App\Models\Proposal;
use App\Models\StatusRetorno;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    public static function payloadFromRequest(Request $request): array
    {
        return [
            'proposal_date' => $request->string('proposal_date')->toString() ?: null,
            'client_name' => trim($request->string('client_name')->toString()),
            'administradora_id' => (int) $request->integer('administradora_id'),
            'service_id' => (int) $request->integer('service_id'),
            'proposal_total' => self::moneyToDb($request->input('proposal_total')),
            'closed_total' => self::moneyToDb($request->input('closed_total')),
            'requester_name' => trim($request->string('requester_name')->toString()),
            'requester_phone' => self::normalizePhone($request->string('requester_phone')->toString()),
            'contact_email' => trim($request->string('contact_email')->toString()),
            'has_referral' => $request->boolean('has_referral'),
            'referral_name' => $request->boolean('has_referral') ? trim($request->string('referral_name')->toString()) : null,
            'send_method_id' => (int) $request->integer('send_method_id'),
            'response_status_id' => (int) $request->integer('response_status_id'),
            'refusal_reason' => trim($request->string('refusal_reason')->toString()) ?: null,
            'followup_date' => $request->string('followup_date')->toString() ?: null,
            'validity_days' => max(0, (int) $request->integer('validity_days')),
            'notes' => trim($request->string('notes')->toString()) ?: null,
        ];
    }

    public static function validate(array $payload): array
    {
        $errors = [];

        if (!$payload['proposal_date']) $errors[] = 'Informe a data da proposta.';
        if ($payload['client_name'] === '') $errors[] = 'Informe o cliente.';
        if ($payload['administradora_id'] <= 0) $errors[] = 'Selecione a administradora.';
        if ($payload['service_id'] <= 0) $errors[] = 'Selecione o serviço.';
        if ($payload['proposal_total'] === null) $errors[] = 'Informe o valor total da proposta.';
        if ($payload['requester_name'] === '') $errors[] = 'Informe o solicitante.';
        if ($payload['requester_phone'] === '') $errors[] = 'Informe o telefone de contato.';
        if ($payload['send_method_id'] <= 0) $errors[] = 'Selecione a forma de envio.';
        if ($payload['response_status_id'] <= 0) $errors[] = 'Selecione o status de retorno.';
        if ($payload['validity_days'] < 1 || $payload['validity_days'] > 365) $errors[] = 'A validade da proposta deve estar entre 1 e 365 dias.';

        if (!empty($payload['requester_phone']) && !self::isValidPhone($payload['requester_phone'])) {
            $errors[] = 'Informe um telefone válido com DDD.';
        }

        if (!empty($payload['contact_email']) && !filter_var($payload['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if ($payload['has_referral'] && empty($payload['referral_name'])) {
            $errors[] = 'Informe o nome da indicação.';
        }

        $status = StatusRetorno::query()->find($payload['response_status_id']);
        if ($status?->requires_closed_value && $payload['closed_total'] === null) {
            $errors[] = 'O valor fechado é obrigatório para o status selecionado.';
        }
        if ($status?->requires_refusal_reason && empty($payload['refusal_reason'])) {
            $errors[] = 'O motivo da recusa é obrigatório para o status selecionado.';
        }

        return $errors;
    }

    public static function create(array $payload, int $userId): Proposal
    {
        return DB::transaction(function () use ($payload, $userId) {
            $year = (int) Carbon::parse($payload['proposal_date'])->format('Y');
            $seq = (int) Proposal::query()->where('proposal_year', $year)->max('proposal_seq') + 1;
            $payload['proposal_year'] = $year;
            $payload['proposal_seq'] = $seq;
            $payload['proposal_code'] = sprintf('%03d.%d', $seq, $year);
            $payload['created_by'] = $userId;
            $payload['updated_by'] = $userId;

            return Proposal::query()->create($payload);
        });
    }

    public static function update(Proposal $proposal, array $payload, int $userId): void
    {
        $payload['updated_by'] = $userId;
        $proposal->update($payload);
    }

    public static function attachmentValidation(mixed $file): array
    {
        $errors = [];
        if (!$file) {
            $errors[] = 'Selecione um arquivo PDF.';
            return $errors;
        }
        if (!$file->isValid()) {
            $errors[] = 'Falha no upload do arquivo.';
            return $errors;
        }
        if (($file->getSize() ?: 0) > 8 * 1024 * 1024) {
            $errors[] = 'O arquivo deve ter no máximo 8 MB.';
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            $errors[] = 'Apenas arquivos PDF são permitidos.';
        }
        return $errors;
    }


    public static function normalizePhone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) > 11) {
            $digits = substr($digits, 0, 11);
        }

        if (strlen($digits) > 10) {
            return preg_replace('/(\d{2})(\d{5})(\d{0,4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        if (strlen($digits) > 6) {
            return preg_replace('/(\d{2})(\d{4})(\d{0,4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        if (strlen($digits) > 2) {
            return preg_replace('/(\d{2})(\d{0,5})/', '($1) $2', $digits) ?: $digits;
        }

        return $digits;
    }

    public static function isValidPhone(?string $value): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
        return in_array(strlen($digits), [10, 11], true);
    }

    public static function moneyToDb(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = preg_replace('/[^\d,.-]/', '', (string) $value) ?: '';
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }
}
