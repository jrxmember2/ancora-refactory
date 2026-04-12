<?php

namespace App\Services;

use App\Models\ClientEntity;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseInstallment;
use Illuminate\Support\Collection;

class CobrancaAgreementTermService
{
    private const LAW_OFFICE = [
        'name' => 'REBECA DA SILVA PAULA SOCIEDADE INDIVIDUAL DE ADVOCACIA',
        'cnpj' => '52.816.983/0001-32',
        'municipal_registration' => '1319712',
        'address' => 'Av. Doutor Hervan Modenese Wanderley, nº 55, sala 505, Ed. Enseada, Vitória/ES, CEP: 29.090-640',
        'email' => 'contato@rebecamedina.com.br',
        'phone' => '(27) 99603-4719',
        'attorney' => 'REBECA DA SILVA PAULA',
        'attorney_oab' => 'OAB/ES 25.057',
    ];

    private const WITNESSES = [
        ['name' => 'JUNIOR CORDEIRO DE AMORIM', 'document' => '090.969.157-63'],
        ['name' => 'ILCIANE MARY THOMPSON DE PAULA', 'document' => '072.706.397-95'],
    ];

    private const MONTHS = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'março',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro',
    ];

    public function build(CobrancaCase $case): array
    {
        $case->loadMissing([
            'condominium.syndic',
            'block',
            'unit.owner',
            'debtor',
            'contacts',
            'quotas',
            'installments',
        ]);

        $templateType = $this->templateType($case);
        $payload = $this->payload($case, $templateType);

        return [
            'template_type' => $templateType,
            'title' => 'Termo de Confissão de Dívida e Acordo Extrajudicial',
            'body_text' => $this->bodyText($payload),
            'payload' => $payload,
            'warnings' => $this->warnings($case, $payload),
        ];
    }

    private function payload(CobrancaCase $case, string $templateType): array
    {
        $agreementAmount = $this->agreementAmount($case);
        $email = $this->primaryContact($case, 'email');
        $phone = $this->primaryContact($case, 'phone');
        $entry = $this->entryData($case);
        $installments = $this->agreementInstallments($case, $entry);

        return [
            'template_type' => $templateType,
            'creditor' => $this->creditorText($case),
            'debtor' => $this->debtorText($case, $email, $phone),
            'debtor_name' => $case->debtor_name_snapshot ?: ($case->debtor?->display_name ?: 'DEVEDOR'),
            'debtor_label' => $this->debtorLabel($case),
            'debtor_confession_label' => $this->debtorConfessionLabel($case),
            'condominium_name' => $case->condominium?->name ?: 'CONDOMÍNIO CREDOR',
            'unit_label' => $this->unitLabel($case),
            'agreement_amount' => $agreementAmount,
            'agreement_amount_money' => $this->money($agreementAmount),
            'agreement_amount_words' => $this->moneyInWords($agreementAmount),
            'quota_period' => $this->quotaPeriod($case->quotas),
            'calc_base_date' => $this->date($case->calc_base_date ?: now()),
            'payment_plan' => $this->paymentPlanText($entry, $installments, $email, $phone),
            'has_entry' => $entry !== null,
            'judicial_case_number' => $case->judicial_case_number,
            'signature_date' => $this->longDate(now()),
            'signature_city' => 'Vitória/ES',
            'creditor_signature' => mb_strtoupper((string) ($case->condominium?->name ?: 'CONDOMÍNIO CREDOR'), 'UTF-8'),
            'debtor_signature' => mb_strtoupper((string) ($case->debtor_name_snapshot ?: ($case->debtor?->display_name ?: 'DEVEDOR')), 'UTF-8'),
            'law_office' => self::LAW_OFFICE,
            'witnesses' => self::WITNESSES,
        ];
    }

    private function bodyText(array $payload): string
    {
        $debtor = $payload['debtor_label'];
        $confession = $payload['debtor_confession_label'];
        $judicial = $payload['template_type'] === 'judicial';
        $foroClause = $judicial ? 'CLÁUSULA DÉCIMA' : 'CLÁUSULA NONA';

        $paragraphs = [
            'TERMO DE CONFISSÃO DE DÍVIDA E ACORDO EXTRAJUDICIAL',
            $payload['creditor'],
            $payload['debtor'],
            'As partes acima identificadas têm, entre si, justo e acertado o presente Termo de Confissão de Dívida, que se regerá pelas cláusulas seguintes e pelas condições descritas no presente instrumento.',
            'CLÁUSULA PRIMEIRA: ' . $payload['debtor_name'] . ' confessa ser ' . $confession . ' da dívida no importe de ' . $payload['agreement_amount_money'] . ' (' . $payload['agreement_amount_words'] . '), referente às cotas condominiais ' . $payload['quota_period'] . ', acrescidas de juros, custas, multas, atualização monetária até ' . $payload['calc_base_date'] . ', honorários, taxas bancárias por cancelamentos de boletos e multa por inadimplemento nos termos da convenção vigente da ' . $payload['unit_label'] . ', localizada no ' . $payload['condominium_name'] . '.',
            'CLÁUSULA SEGUNDA: O atraso no pagamento das parcelas do presente acordo, bem como das parcelas vincendas na vigência desse, gera o vencimento antecipado do acordo, possibilitando a sua exigência de imediato.',
            'CLÁUSULA TERCEIRA: Fica a dívida aqui assumida a ser cobrada como na execução do crédito nos termos admitidos em lei, inclusive pedido de penhora do bem, por ter a dívida característica propter rem, constituindo-se este termo em título executivo.',
            'CLÁUSULA QUARTA: Fica estipulada e autorizada a cobrança de multa de 20% (vinte por cento) sobre o valor ainda não quitado deste acordo em caso de inadimplência ou atraso no pagamento, bem como 20% (vinte por cento) de honorários sobre o saldo a pagar, devidos ao patrono subscrevente, independentemente de haver arbitramento de honorários sucumbenciais nas ações de cobrança ou execução.',
            'CLÁUSULA QUINTA: A dívida ora reconhecida e assumida pelo ' . $debtor . ' como líquida, certa e exigível, no valor acima mencionado, aplica-se o disposto no artigo 784, III do Código de Processo Civil, haja vista o caráter de título executivo extrajudicial do presente termo de confissão de dívida.',
            'CLÁUSULA SEXTA: A eventual tolerância à infringência de qualquer das cláusulas deste instrumento ou o não exercício de qualquer direito nele previsto constituirá mera liberalidade, não implicando em novação ou transação de qualquer espécie.',
            'CLÁUSULA SÉTIMA: O pagamento da importância de ' . $payload['agreement_amount_money'] . ' (' . $payload['agreement_amount_words'] . ') será realizado ' . $payload['payment_plan'] . '.',
            'CLÁUSULA OITAVA: Os pagamentos das taxas condominiais da unidade que não integram este acordo vencem nos termos aprovados em assembleia e convenção do condomínio e passarão a compor a dívida deste termo, caso não sejam pagos, podendo ser aplicadas as penalidades previstas na convenção, regimento interno, leis vigentes e nas cláusulas quarta e quinta deste termo.',
        ];

        if ($judicial) {
            $judicialTrigger = $payload['has_entry'] ? 'Após a quitação da entrada' : 'Após a assinatura do presente termo';
            $paragraphs[] = 'CLÁUSULA NONA: ' . $judicialTrigger . ', o CREDOR irá protocolar nos autos do processo de nº ' . ($payload['judicial_case_number'] ?: '[INFORMAR NÚMERO DO PROCESSO]') . ', o presente termo de acordo indicando o parcelamento supramencionado e requerendo o sobrestamento da ação até a quitação da última parcela.';
        }

        $paragraphs = array_merge($paragraphs, [
            $foroClause . ': Para dirimir quaisquer controvérsias oriundas do presente TERMO DE CONFISSÃO DE DÍVIDAS, as partes elegem o foro da comarca de Vitória/ES, com renúncia expressa de qualquer outro, por mais privilegiado que seja, com amparo nos arts. 78 do Código Civil e art. 63 do Código de Processo Civil.',
            'Assim, por estarem justos CREDOR e ' . $debtor . ', firmam de forma irrevogável e irretratável o presente instrumento, em duas vias de igual teor, na presença de 02 (duas) testemunhas que a tudo presenciaram, para que produza e surta os seus efeitos legais e jurídicos devidos.',
            $payload['signature_city'] . ', ' . $payload['signature_date'] . '.',
            "________________________________________\n" . $payload['creditor_signature'] . "\np.p/ Rebeca de Paula - OAB/ES 25.057\nCREDOR",
            "________________________________________\n" . $payload['debtor_signature'] . "\n" . $debtor,
            "TESTEMUNHAS:\n\n________________________________________\n" . self::WITNESSES[0]['name'] . "\nCPF: " . self::WITNESSES[0]['document'] . "\n\n________________________________________\n" . self::WITNESSES[1]['name'] . "\nCPF: " . self::WITNESSES[1]['document'],
        ]);

        return implode("\n\n", $paragraphs);
    }

    private function warnings(CobrancaCase $case, array $payload): array
    {
        $warnings = [];
        if (!$case->condominium) {
            $warnings[] = 'A OS não possui condomínio vinculado.';
        }
        if (!$case->unit) {
            $warnings[] = 'A OS não possui unidade vinculada.';
        }
        if (!$case->debtor_name_snapshot && !$case->debtor?->display_name) {
            $warnings[] = 'A OS não possui nome do devedor no snapshot.';
        }
        if ($payload['agreement_amount'] <= 0) {
            $warnings[] = 'Informe o valor do acordo ou quotas com valor atualizado.';
        }
        if ($case->quotas->isEmpty()) {
            $warnings[] = 'Cadastre as quotas que compõem a dívida.';
        }
        if ($case->installments->isEmpty() && !$case->entry_amount) {
            $warnings[] = 'Cadastre entrada e/ou parcelas para montar a cláusula de pagamento.';
        }
        if ($payload['template_type'] === 'judicial' && !$case->judicial_case_number) {
            $warnings[] = 'Cobrança judicial sem número do processo.';
        }
        if (!$this->primaryContact($case, 'email')) {
            $warnings[] = 'Nenhum e-mail principal localizado para envio dos boletos.';
        }

        return $warnings;
    }

    private function creditorText(CobrancaCase $case): string
    {
        $condo = $case->condominium;
        $name = mb_strtoupper((string) ($condo?->name ?: 'CONDOMÍNIO CREDOR'), 'UTF-8');
        $cnpj = $condo?->cnpj ? ', inscrito no CNPJ sob o nº ' . $condo->cnpj : '';
        $address = $this->address($condo?->address_json ?? []);
        $addressText = $address ? ', com endereço na ' . $address : '';
        $syndic = $this->syndicText($condo?->syndic);

        return $name . $cnpj . $addressText . $syndic . ', neste acordo representado por sua patrona ' . self::LAW_OFFICE['name'] . ', pessoa jurídica de direito privado, inscrita no CNPJ sob o nº ' . self::LAW_OFFICE['cnpj'] . ', inscrição municipal de nº ' . self::LAW_OFFICE['municipal_registration'] . ', com sede à ' . self::LAW_OFFICE['address'] . ', e-mail: ' . self::LAW_OFFICE['email'] . ' / telefone: ' . self::LAW_OFFICE['phone'] . ', neste ato representada por sua sócia administradora, ' . self::LAW_OFFICE['attorney'] . ', brasileira, solteira, devidamente inscrita na ' . self::LAW_OFFICE['attorney_oab'] . ', doravante denominado CREDOR.';
    }

    private function syndicText(?ClientEntity $syndic): string
    {
        if (!$syndic) {
            return '';
        }

        $document = $syndic->cpf_cnpj ? ', inscrito(a) no CPF/CNPJ sob o nº ' . $syndic->cpf_cnpj : '';

        return ', neste ato representado por seu/sua síndico(a), ' . $syndic->display_name . $document;
    }

    private function debtorText(CobrancaCase $case, ?string $email, ?string $phone): string
    {
        $entity = $case->debtor;
        $name = mb_strtoupper((string) ($case->debtor_name_snapshot ?: $entity?->display_name ?: 'DEVEDOR'), 'UTF-8');
        $label = $this->debtorLabel($case);
        $ownerText = $this->isFemaleDebtor($case) ? 'proprietária' : 'proprietário';
        $address = $this->address($entity?->primary_address_json ?? []) ?: $this->address($case->condominium?->address_json ?? []);
        $contact = collect([
            $email ? 'endereço eletrônico ' . $email : null,
            $phone ? 'telefone ' . $phone : null,
        ])->filter()->implode(', ');

        if (($entity?->entity_type ?? 'pf') === 'pj') {
            $document = $case->debtor_document_snapshot ?: $entity?->cpf_cnpj;
            return $name . ', pessoa jurídica de direito privado' . ($document ? ', inscrita no CNPJ sob o nº ' . $document : '') . ', ' . $ownerText . ' da ' . $this->unitLabel($case) . ($address ? ', com endereço na ' . $address : '') . ($contact ? ', ' . $contact : '') . ', doravante denominada ' . $label . '.';
        }

        $document = $case->debtor_document_snapshot ?: $entity?->cpf_cnpj;
        $rg = $entity?->rg_ie ? ', RG nº ' . $entity->rg_ie : '';
        $nationality = $entity?->nationality ?: ($this->isFemaleDebtor($case) ? 'brasileira' : 'brasileiro');
        $maritalStatus = $entity?->marital_status ? ', ' . $entity->marital_status : '';

        return $name . ', ' . $nationality . $maritalStatus . ($document ? ', inscrito(a) no CPF sob o nº ' . $document : '') . $rg . ', ' . $ownerText . ' da ' . $this->unitLabel($case) . ($address ? ', residente e domiciliado(a) à ' . $address : '') . ($contact ? ', ' . $contact : '') . ', doravante denominado(a) ' . $label . '.';
    }

    private function templateType(CobrancaCase $case): string
    {
        return $case->charge_type === 'judicial' || trim((string) $case->judicial_case_number) !== '' ? 'judicial' : 'extrajudicial';
    }

    private function unitLabel(CobrancaCase $case): string
    {
        $unit = $case->unit?->unit_number ?: 'unidade não informada';
        $block = $case->block?->name ? ' do bloco ' . $case->block->name : '';

        return 'unidade ' . $unit . $block;
    }

    private function debtorLabel(CobrancaCase $case): string
    {
        return $this->isFemaleDebtor($case) ? 'DEVEDORA' : 'DEVEDOR';
    }

    private function debtorConfessionLabel(CobrancaCase $case): string
    {
        return $this->isFemaleDebtor($case) ? 'devedora' : 'devedor';
    }

    private function isFemaleDebtor(CobrancaCase $case): bool
    {
        if (($case->debtor?->entity_type ?? null) === 'pj') {
            return true;
        }

        $gender = mb_strtolower((string) ($case->debtor?->gender ?? ''), 'UTF-8');
        if (str_contains($gender, 'fem') || in_array($gender, ['f', 'mulher'], true)) {
            return true;
        }

        $name = trim((string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name));
        return (bool) preg_match('/(A|Y)$/u', mb_strtoupper($name, 'UTF-8'));
    }

    private function agreementAmount(CobrancaCase $case): float
    {
        if ((float) $case->agreement_total > 0) {
            return (float) $case->agreement_total;
        }

        return round((float) $case->quotas->sum(fn ($quota) => (float) ($quota->updated_amount ?? $quota->original_amount)), 2);
    }

    private function quotaPeriod(Collection $quotas): string
    {
        $labels = $quotas
            ->sortBy('due_date')
            ->map(fn ($quota) => $quota->reference_label ?: ($quota->due_date ? $quota->due_date->format('m/Y') : null))
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return 'descritas na OS de cobrança';
        }

        if ($labels->count() === 1) {
            return 'do mês de ' . $this->referenceLong($labels->first());
        }

        return 'dos meses de ' . $this->referenceLong($labels->first()) . ' a ' . $this->referenceLong($labels->last());
    }

    private function entryData(CobrancaCase $case): ?array
    {
        if ((float) $case->entry_amount > 0) {
            return [
                'amount' => (float) $case->entry_amount,
                'due_date' => $case->entry_due_date,
                'source_id' => null,
            ];
        }

        $entry = $case->installments->first(fn ($item) => $item->installment_type === 'entrada');
        if (!$entry) {
            return null;
        }

        return [
            'amount' => (float) $entry->amount,
            'due_date' => $entry->due_date,
            'source_id' => $entry->id,
        ];
    }

    private function agreementInstallments(CobrancaCase $case, ?array $entry): Collection
    {
        $entrySourceId = $entry['source_id'] ?? null;

        return $case->installments
            ->filter(fn ($item) => $entrySourceId === null || $item->id !== $entrySourceId)
            ->sortBy('due_date')
            ->values();
    }

    private function paymentPlanText(?array $entry, Collection $installments, ?string $email, ?string $phone): string
    {
        $parts = [];
        if ($entry) {
            $parts[] = 'por meio de uma entrada no valor de ' . $this->money($entry['amount']) . ' (' . $this->moneyInWords((float) $entry['amount']) . '), cujo vencimento ocorrerá em ' . $this->date($entry['due_date']);
        }

        if ($installments->isNotEmpty()) {
            $count = $installments->count();
            $amounts = $installments->map(fn ($item) => (float) $item->amount)->unique();
            if ($amounts->count() === 1) {
                $dates = $installments->map(fn ($item) => $this->date($item->due_date))->implode(', ');
                $amount = (float) $amounts->first();
                $installmentLabel = $count === 1 ? 'parcela no valor de ' : 'parcelas, cada uma no valor de ';
                $dueLabel = $count === 1 ? 'cujo vencimento ocorrerá em ' : 'cujos vencimentos ocorrerão em ';
                $parts[] = ($entry ? 'mais ' : '') . $count . ' (' . $this->numberInWords($count) . ') ' . $installmentLabel . $this->money($amount) . ' (' . $this->moneyInWords($amount) . '), ' . $dueLabel . $dates;
            } else {
                $details = $installments->map(function (CobrancaCaseInstallment $item) {
                    return ($item->label ?: 'parcela ' . $item->installment_number) . ' no valor de ' . $this->money((float) $item->amount) . ', com vencimento em ' . $this->date($item->due_date);
                })->implode('; ');
                $parts[] = ($entry ? 'mais ' : '') . $details;
            }
        }

        if ($parts === []) {
            $parts[] = 'conforme cronograma de pagamento ajustado entre as partes e registrado na OS de cobrança';
        }

        $delivery = collect([
            $email ? 'e-mail ' . $email : null,
            $phone ? 'WhatsApp número ' . $phone : null,
        ])->filter()->implode(' e ');

        return implode(' e ', $parts) . ($delivery ? ', por meio de boleto a ser encaminhado para o ' . $delivery : '');
    }

    private function primaryContact(CobrancaCase $case, string $type): ?string
    {
        $contact = $case->contacts->first(fn ($item) => $item->contact_type === $type && trim((string) $item->value) !== '');
        if ($contact) {
            return (string) $contact->value;
        }

        if ($type === 'email') {
            return $case->debtor_email_snapshot ?: collect($case->debtor?->emails_json ?? [])->pluck('email')->filter()->first();
        }

        return $case->debtor_phone_snapshot ?: collect($case->debtor?->phones_json ?? [])->pluck('number')->filter()->first();
    }

    private function address(array $address): string
    {
        $street = trim((string) ($address['street'] ?? ''));
        $number = trim((string) ($address['number'] ?? ''));

        return collect([
            $street !== '' ? $street . ($number !== '' ? ', nº ' . $number : '') : null,
            $address['complement'] ?? null,
            $address['neighborhood'] ?? null,
            collect([$address['city'] ?? null, $address['state'] ?? null])->filter()->implode('/'),
            ($address['zip'] ?? null) ? 'CEP: ' . $address['zip'] : null,
        ])->filter(fn ($item) => trim((string) $item) !== '')->implode(', ');
    }

    private function referenceLong(string $label): string
    {
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', trim($label), $m)) {
            return (self::MONTHS[(int) $m[1]] ?? $m[1]) . ' de ' . $m[2];
        }

        return $label;
    }

    private function date(mixed $date): string
    {
        if (!$date) {
            return '[informar data]';
        }

        try {
            return $date instanceof \DateTimeInterface
                ? $date->format('d/m/Y')
                : (new \DateTimeImmutable((string) $date))->format('d/m/Y');
        } catch (\Throwable) {
            return '[informar data]';
        }
    }

    private function longDate(mixed $date): string
    {
        try {
            $dt = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable((string) $date);
            return (int) $dt->format('d') . ' de ' . self::MONTHS[(int) $dt->format('n')] . ' de ' . $dt->format('Y');
        } catch (\Throwable) {
            return '[informar data]';
        }
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function moneyInWords(float $value): string
    {
        $value = round($value, 2);
        $reais = (int) floor($value);
        $centavos = (int) round(($value - $reais) * 100);
        if ($centavos === 100) {
            $reais++;
            $centavos = 0;
        }

        $parts = [];
        if ($reais > 0) {
            $currencySeparator = $reais >= 1000000 && $reais % 1000000 === 0 ? ' de ' : ' ';
            $parts[] = $this->numberInWords($reais) . $currencySeparator . ($reais === 1 ? 'real' : 'reais');
        }
        if ($centavos > 0) {
            $parts[] = $this->numberInWords($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
        }

        return ucfirst($parts ? implode(' e ', $parts) : 'zero real');
    }

    private function numberInWords(int $number): string
    {
        $number = max(0, $number);
        $units = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
        $tens = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $hundreds = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        if ($number < 20) {
            return $units[$number] ?: 'zero';
        }
        if ($number < 100) {
            return $tens[intdiv($number, 10)] . ($number % 10 ? ' e ' . $units[$number % 10] : '');
        }
        if ($number === 100) {
            return 'cem';
        }
        if ($number < 1000) {
            return $hundreds[intdiv($number, 100)] . ($number % 100 ? ' e ' . $this->numberInWords($number % 100) : '');
        }
        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;
            return ($thousands === 1 ? 'mil' : $this->numberInWords($thousands) . ' mil') . ($remainder ? ($remainder < 100 ? ' e ' : ' ') . $this->numberInWords($remainder) : '');
        }

        $millions = intdiv($number, 1000000);
        $remainder = $number % 1000000;
        return $this->numberInWords($millions) . ' ' . ($millions === 1 ? 'milhão' : 'milhões') . ($remainder ? ' ' . $this->numberInWords($remainder) : '');
    }
}
