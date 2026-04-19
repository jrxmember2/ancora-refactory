<?php

namespace App\Services;

use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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

            return ['skipped' => false, 'created' => $created, 'refreshed' => $refreshed, 'error' => null];
        } catch (\Throwable $e) {
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
        $complements = $this->movementComplements($movement);

        if ($complements !== []) {
            $name .= ' - ' . implode(' | ', array_slice($complements, 0, 2));
        }

        return Str::limit($name, 255, '');
    }

    private function movementComplements(array $movement): array
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

            $label = $description !== '' && $name !== ''
                ? "{$description}: {$name}"
                : ($name !== '' ? $name : $description);

            $meta = [];
            if ($value !== null && $value !== '') {
                $meta[] = 'valor ' . $value;
            }
            if ($code !== null && $code !== '') {
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
            return trim((string) $value);
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

        if ($value !== '') {
            $parts[] = "{$label}: {$value}";
        }
    }
}
