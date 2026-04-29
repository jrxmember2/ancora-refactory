<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessDataJudService
{
    public function syncAll(): array
    {
        $summary = ['checked' => 0, 'updated' => 0, 'created' => 0, 'refreshed' => 0, 'skipped' => 0, 'errors' => []];

        ProcessCase::query()
            ->whereNotNull('process_number')
            ->whereNotNull('datajud_court')
            ->whereNull('closed_at')
            ->orderBy('id')
            ->chunkById(50, function ($cases) use (&$summary) {
                foreach ($cases as $case) {
                    $summary['checked']++;
                    $result = $this->syncCase($case);
                    $summary['created'] += $result['created'] ?? 0;
                    $summary['refreshed'] += $result['refreshed'] ?? 0;
                    $summary['updated'] += (($result['created'] ?? 0) + ($result['refreshed'] ?? 0)) > 0 ? 1 : 0;
                    if (($result['skipped'] ?? false) === true) {
                        $summary['skipped']++;
                    }
                    if (!empty($result['error'])) {
                        $summary['errors'][] = $case->id . ': ' . $result['error'];
                    }
                }
            });

        $this->recordScheduledSummary($summary);
        $this->dataJudLogger()->info('Sincronizacao diaria do DataJud concluida.', $summary);

        return $summary;
    }

    public function syncCase(ProcessCase $case): array
    {
        $processNumber = preg_replace('/\D+/', '', (string) $case->process_number);
        $courtAlias = trim((string) $case->datajud_court);
        $apiKey = trim((string) config('services.datajud.api_key', ''));

        if ($processNumber === '' || $courtAlias === '') {
            return ['skipped' => true, 'created' => 0, 'refreshed' => 0, 'error' => null];
        }

        if ($apiKey === '') {
            return ['skipped' => true, 'created' => 0, 'refreshed' => 0, 'error' => 'DATAJUD_API_KEY nao configurada'];
        }

        try {
            $endpoint = rtrim((string) config('services.datajud.base_url'), '/') . '/' . trim($courtAlias, '/') . '/_search';
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'Authorization' => 'APIKey ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'query' => [
                        'match' => [
                            'numeroProcesso' => $processNumber,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                $this->dataJudLogger()->warning('Falha HTTP ao sincronizar processo no DataJud.', [
                    'process_case_id' => $case->id,
                    'process_number' => $case->process_number,
                    'court' => $courtAlias,
                    'status_code' => $response->status(),
                ]);

                return ['skipped' => true, 'created' => 0, 'refreshed' => 0, 'error' => 'DataJud HTTP ' . $response->status()];
            }

            $payload = $response->json();
            $hits = data_get($payload, 'hits.hits', []);
            $created = 0;
            $refreshed = 0;
            $fingerprint = sha1(json_encode($hits, JSON_UNESCAPED_UNICODE));

            foreach ($hits as $hit) {
                $source = data_get($hit, '_source', []);
                foreach ((array) data_get($source, 'movimentos', []) as $index => $movement) {
                    $movementId = $this->movementId($hit, $movement, $index);
                    if ($movementId === '') {
                        continue;
                    }

                    $when = $this->movementDate($movement);
                    $payload = [
                        'phase_date' => $when?->toDateString(),
                        'phase_time' => $when?->format('H:i:s'),
                        'description' => $this->movementDescription($movement),
                        'is_private' => false,
                        'is_reviewed' => false,
                        'notes' => $this->movementNotes($source, $movement),
                        'source' => 'datajud',
                        'datajud_payload_json' => [
                            'processo' => $this->processSummary($source),
                            'movimento' => $movement,
                            'anexos' => $this->movementAttachments($movement),
                        ],
                    ];

                    $phase = ProcessCasePhase::query()
                        ->where('process_case_id', $case->id)
                        ->where('datajud_movement_id', $movementId)
                        ->first();

                    if ($phase) {
                        $phase->update($payload);
                        $refreshed++;
                    } else {
                        ProcessCasePhase::query()->create($payload + [
                            'process_case_id' => $case->id,
                            'datajud_movement_id' => $movementId,
                            'legal_opinion' => null,
                            'conference' => null,
                            'created_by' => null,
                        ]);
                        $created++;
                    }
                }
            }

            $case->update([
                'last_datajud_sync_at' => now(),
                'datajud_last_hash' => $fingerprint,
            ]);

            if ($created > 0 || $refreshed > 0) {
                $this->dataJudLogger()->info('Processo sincronizado com movimentos do DataJud.', [
                    'process_case_id' => $case->id,
                    'process_number' => $case->process_number,
                    'court' => $courtAlias,
                    'created' => $created,
                    'refreshed' => $refreshed,
                ]);
            }

            return ['skipped' => false, 'created' => $created, 'refreshed' => $refreshed, 'error' => null];
        } catch (\Throwable $e) {
            $this->dataJudLogger()->error('Excecao ao sincronizar processo no DataJud.', [
                'process_case_id' => $case->id,
                'process_number' => $case->process_number,
                'court' => $courtAlias,
                'message' => $e->getMessage(),
            ]);

            return ['skipped' => true, 'created' => 0, 'refreshed' => 0, 'error' => Str::limit($e->getMessage(), 180, '')];
        }
    }

    private function movementId(array $hit, array $movement, int $index): string
    {
        $parts = [
            (string) data_get($hit, '_id', ''),
            (string) data_get($movement, 'dataHora', ''),
            (string) data_get($movement, 'codigo', ''),
            (string) data_get($movement, 'nome', ''),
            (string) $index,
        ];

        return sha1(implode('|', $parts));
    }

    private function movementDate(array $movement): ?Carbon
    {
        $value = data_get($movement, 'dataHora');
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function movementNotes(array $source, array $movement): string
    {
        $parts = [];
        $this->appendLine($parts, 'Movimento', data_get($movement, 'nome'));
        $this->appendLine($parts, 'Codigo TPU do movimento', data_get($movement, 'codigo'));
        $this->appendLine($parts, 'Data/hora original DataJud', data_get($movement, 'dataHora'));

        $movementCourt = data_get($movement, 'orgaoJulgador.nome');
        $caseCourt = data_get($source, 'orgaoJulgador.nome');
        $this->appendLine($parts, 'Orgao julgador do movimento', $movementCourt);
        $this->appendLine($parts, 'Orgao julgador do processo', $caseCourt);

        $complements = $this->movementComplements($movement);
        if ($complements !== []) {
            $parts[] = '';
            $parts[] = 'Complementos do movimento:';
            foreach ($complements as $line) {
                $parts[] = '- ' . $line;
            }
        }

        $attachments = $this->movementAttachments($movement);
        if ($attachments !== []) {
            $parts[] = '';
            $parts[] = 'Anexos/localizadores do movimento:';
            foreach ($attachments as $attachment) {
                $label = trim((string) ($attachment['label'] ?? 'Documento'));
                $url = trim((string) ($attachment['url'] ?? ''));
                if ($url !== '') {
                    $parts[] = '- ' . $label . ': ' . $url;
                }
            }
        }

        $parts[] = '';
        $parts[] = 'Dados do processo no DataJud:';
        $this->appendLine($parts, 'Tribunal', data_get($source, 'tribunal'));
        $this->appendLine($parts, 'Numero CNJ', data_get($source, 'numeroProcesso'));
        $this->appendLine($parts, 'Grau', data_get($source, 'grau'));
        $this->appendLine($parts, 'Classe', $this->namedCode(data_get($source, 'classe')));
        $this->appendLine($parts, 'Sistema', $this->namedCode(data_get($source, 'sistema')));
        $this->appendLine($parts, 'Formato', $this->namedCode(data_get($source, 'formato')));
        $this->appendLine($parts, 'Data de ajuizamento', data_get($source, 'dataAjuizamento'));
        $this->appendLine($parts, 'Ultima atualizacao DataJud', data_get($source, 'dataHoraUltimaAtualizacao') ?: data_get($source, '@timestamp'));

        $subjects = $this->subjects($source);
        if ($subjects !== []) {
            $parts[] = 'Assuntos: ' . implode('; ', $subjects);
        }

        return implode("\n", $parts);
    }

    private function movementDescription(array $movement): string
    {
        $name = trim((string) (data_get($movement, 'nome') ?: 'Movimento DataJud'));
        $complements = $this->movementComplements($movement, false);

        if ($complements !== []) {
            $name .= ' - ' . implode(' | ', array_slice($complements, 0, 2));
        }

        return Str::limit($name, 255, '');
    }

    private function movementComplements(array $movement, bool $includeMeta = true): array
    {
        $items = [];

        foreach ((array) data_get($movement, 'complementosTabelados', []) as $complement) {
            if (!is_array($complement)) {
                continue;
            }

            $description = trim((string) data_get($complement, 'descricao', ''));
            $name = trim((string) data_get($complement, 'nome', ''));
            $value = data_get($complement, 'valor');
            $code = data_get($complement, 'codigo');

            $description = $description !== ''
                ? Str::ucfirst(Str::of($description)->replace('_', ' ')->lower()->toString())
                : '';

            $label = $description !== '' && $name !== ''
                ? "{$description}: {$name}"
                : ($name !== '' ? $name : $description);

            $meta = [];
            if ($includeMeta && $value !== null && $value !== '') {
                $meta[] = 'valor ' . $value;
            }
            if ($includeMeta && $code !== null && $code !== '') {
                $meta[] = 'codigo ' . $code;
            }

            if ($label !== '') {
                $items[] = $label . ($meta !== [] ? ' (' . implode(', ', $meta) . ')' : '');
            }
        }

        return $items;
    }

    private function processSummary(array $source): array
    {
        return [
            'tribunal' => data_get($source, 'tribunal'),
            'numeroProcesso' => data_get($source, 'numeroProcesso'),
            'grau' => data_get($source, 'grau'),
            'classe' => data_get($source, 'classe'),
            'sistema' => data_get($source, 'sistema'),
            'formato' => data_get($source, 'formato'),
            'orgaoJulgador' => data_get($source, 'orgaoJulgador'),
            'assuntos' => data_get($source, 'assuntos'),
            'dataAjuizamento' => data_get($source, 'dataAjuizamento'),
            'dataHoraUltimaAtualizacao' => data_get($source, 'dataHoraUltimaAtualizacao') ?: data_get($source, '@timestamp'),
        ];
    }

    private function subjects(array $source): array
    {
        $subjects = [];

        foreach ((array) data_get($source, 'assuntos', []) as $subject) {
            $label = $this->namedCode($subject);
            if ($label !== '') {
                $subjects[] = $label;
            }
        }

        return $subjects;
    }

    private function namedCode(mixed $value): string
    {
        if (!is_array($value)) {
            return $this->formatDisplayValue(trim((string) $value));
        }

        $name = trim((string) data_get($value, 'nome', ''));
        $code = data_get($value, 'codigo');

        if ($name !== '' && $code !== null && $code !== '') {
            return "{$name} ({$code})";
        }

        return $name !== '' ? $name : trim((string) $code);
    }

    private function appendLine(array &$parts, string $label, mixed $value): void
    {
        $value = is_array($value) ? $this->namedCode($value) : trim((string) $value);
        $value = $this->formatDisplayValue($value, $label);

        if ($value !== '') {
            $parts[] = "{$label}: {$value}";
        }
    }

    private function formatDisplayValue(string $value, ?string $label = null): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{14}$/', $value) === 1) {
            try {
                return Carbon::createFromFormat('YmdHis', $value, 'UTC')
                    ->timezone(config('app.timezone'))
                    ->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        if ($label && str_contains(mb_strtolower($label, 'UTF-8'), 'data')) {
            try {
                return Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function movementAttachments(array $movement): array
    {
        $items = [];
        $this->collectMovementAttachments($movement, $items);

        return collect($items)
            ->filter(fn (array $item) => trim((string) ($item['url'] ?? '')) !== '')
            ->unique(fn (array $item) => trim((string) ($item['url'] ?? '')))
            ->values()
            ->all();
    }

    private function collectMovementAttachments(array $payload, array &$items, string $path = ''): void
    {
        foreach ($payload as $key => $value) {
            $currentPath = trim($path . '.' . (string) $key, '.');

            if (is_array($value)) {
                if ($this->looksLikeAttachmentNode($currentPath, $value)) {
                    $items[] = $this->normalizeAttachmentNode($currentPath, $value);
                }

                $this->collectMovementAttachments($value, $items, $currentPath);
            }
        }
    }

    private function looksLikeAttachmentNode(string $path, array $value): bool
    {
        $path = mb_strtolower($path, 'UTF-8');

        if (!str_contains($path, 'anex') && !str_contains($path, 'document') && !str_contains($path, 'arquivo') && !str_contains($path, 'link')) {
            return false;
        }

        return trim((string) ($value['url'] ?? $value['href'] ?? $value['link'] ?? '')) !== '';
    }

    private function normalizeAttachmentNode(string $path, array $value): array
    {
        $url = trim((string) ($value['url'] ?? $value['href'] ?? $value['link'] ?? ''));
        $label = trim((string) ($value['nome'] ?? $value['descricao'] ?? $value['titulo'] ?? $value['tipo'] ?? ''));

        if ($label === '') {
            $label = Str::headline(Str::afterLast($path, '.') ?: 'Documento');
        }

        return [
            'label' => $label,
            'url' => $url,
        ];
    }

    private function recordScheduledSummary(array $summary): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => null,
                'user_email' => 'scheduler@ancora.local',
                'action' => 'processos.datajud.schedule',
                'entity_type' => 'process_cases',
                'entity_id' => null,
                'details' => sprintf(
                    'Sincronizacao diaria do DataJud: %d verificado(s), %d atualizado(s), %d movimento(s) criado(s), %d revisado(s), %d ignorado(s).%s',
                    (int) ($summary['checked'] ?? 0),
                    (int) ($summary['updated'] ?? 0),
                    (int) ($summary['created'] ?? 0),
                    (int) ($summary['refreshed'] ?? 0),
                    (int) ($summary['skipped'] ?? 0),
                    !empty($summary['errors']) ? ' Erros: ' . implode(' | ', array_slice((array) $summary['errors'], 0, 5)) : ''
                ),
                'ip_address' => 'scheduler',
                'user_agent' => 'artisan-processos:datajud-sync',
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Mantem a rotina principal viva mesmo sem conseguir registrar a auditoria.
        }
    }

    private function dataJudLogger()
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/datajud-sync.log'),
            'replace_placeholders' => true,
        ]);
    }
}
