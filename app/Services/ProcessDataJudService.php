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
        $summary = ['checked' => 0, 'updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];

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
                    $summary['updated'] += ($result['created'] ?? 0) > 0 ? 1 : 0;
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
            return ['skipped' => true, 'created' => 0, 'error' => null];
        }

        if ($apiKey === '') {
            return ['skipped' => true, 'created' => 0, 'error' => 'DATAJUD_API_KEY nao configurada'];
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
                return ['skipped' => true, 'created' => 0, 'error' => 'DataJud HTTP ' . $response->status()];
            }

            $payload = $response->json();
            $hits = data_get($payload, 'hits.hits', []);
            $created = 0;
            $fingerprint = sha1(json_encode($hits, JSON_UNESCAPED_UNICODE));

            foreach ($hits as $hit) {
                $source = data_get($hit, '_source', []);
                foreach ((array) data_get($source, 'movimentos', []) as $index => $movement) {
                    $movementId = $this->movementId($hit, $movement, $index);
                    if ($movementId === '') {
                        continue;
                    }

                    $when = $this->movementDate($movement);
                    $phase = ProcessCasePhase::query()->firstOrCreate(
                        [
                            'process_case_id' => $case->id,
                            'datajud_movement_id' => $movementId,
                        ],
                        [
                            'phase_date' => $when?->toDateString(),
                            'phase_time' => $when?->format('H:i:s'),
                            'description' => Str::limit((string) (data_get($movement, 'nome') ?: 'Movimento DataJud'), 255, ''),
                            'is_private' => false,
                            'is_reviewed' => false,
                            'notes' => $this->movementNotes($source, $movement),
                            'legal_opinion' => null,
                            'conference' => null,
                            'source' => 'datajud',
                            'datajud_payload_json' => $movement,
                            'created_by' => null,
                        ]
                    );

                    if ($phase->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }

            $case->update([
                'last_datajud_sync_at' => now(),
                'datajud_last_hash' => $fingerprint,
            ]);

            return ['skipped' => false, 'created' => $created, 'error' => null];
        } catch (\Throwable $e) {
            return ['skipped' => true, 'created' => 0, 'error' => Str::limit($e->getMessage(), 180, '')];
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
        $tribunal = data_get($source, 'tribunal');
        $orgao = data_get($source, 'orgaoJulgador.nome');
        $classe = data_get($source, 'classe.nome');
        $codigo = data_get($movement, 'codigo');

        if ($tribunal) {
            $parts[] = 'Tribunal: ' . $tribunal;
        }
        if ($orgao) {
            $parts[] = 'Orgao julgador: ' . $orgao;
        }
        if ($classe) {
            $parts[] = 'Classe: ' . $classe;
        }
        if ($codigo) {
            $parts[] = 'Codigo do movimento: ' . $codigo;
        }

        return implode("\n", $parts);
    }
}
