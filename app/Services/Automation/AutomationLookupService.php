<?php

namespace App\Services\Automation;

use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Support\Automation\AutomationText;
use Illuminate\Support\Collection;

class AutomationLookupService
{
    public function searchCondominiums(string $term, int $limit): Collection
    {
        $needle = AutomationText::normalize($term);
        $needleCompact = AutomationText::normalizeCompact($term);

        return ClientCondominium::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->map(function (ClientCondominium $condominium) use ($needle, $needleCompact) {
                $normalized = AutomationText::normalize($condominium->name);
                $compact = AutomationText::normalizeCompact($condominium->name);

                $score = match (true) {
                    $needleCompact !== '' && $compact === $needleCompact => 100,
                    $needleCompact !== '' && str_starts_with($compact, $needleCompact) => 90,
                    $needle !== '' && str_contains($normalized, $needle) => 75,
                    default => 0,
                };

                return ['score' => $score, 'item' => $condominium];
            })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('item')
            ->values();
    }

    public function searchBlocks(ClientCondominium $condominium, string $term, int $limit = 10): Collection
    {
        $needle = AutomationText::normalize($term);
        $needleCompact = AutomationText::normalizeCompact($term);

        return $condominium->blocks()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (ClientBlock $block) use ($needle, $needleCompact) {
                $normalized = AutomationText::normalize($block->name);
                $compact = AutomationText::normalizeCompact($block->name);

                $score = match (true) {
                    $needleCompact !== '' && $compact === $needleCompact => 100,
                    $needleCompact !== '' && str_starts_with($compact, $needleCompact) => 90,
                    $needle !== '' && str_contains($normalized, $needle) => 75,
                    default => 0,
                };

                return ['score' => $score, 'item' => $block];
            })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('item')
            ->values();
    }

    public function findUnit(ClientCondominium $condominium, ?ClientBlock $block, string $unitInput): ?ClientUnit
    {
        $normalizedInput = AutomationText::normalizeUnit($unitInput);

        return $this->unitsFor($condominium, $block)
            ->first(function (ClientUnit $unit) use ($normalizedInput) {
                return AutomationText::normalizeUnit($unit->unit_number) === $normalizedInput;
            });
    }

    public function unitsFor(ClientCondominium $condominium, ?ClientBlock $block): Collection
    {
        return ClientUnit::query()
            ->with(['condominium', 'block', 'owner', 'tenant'])
            ->where('condominium_id', $condominium->id)
            ->when($block, fn ($query) => $query->where('block_id', $block->id), fn ($query) => $query->whereNull('block_id'))
            ->orderBy('unit_number')
            ->get();
    }

    public function decoyEntitiesForUnit(ClientUnit $unit, int $excludeEntityId, int $limit): Collection
    {
        $targetName = $unit->owner?->display_name ?: $unit->tenant?->display_name ?: '';

        $sameCondo = $this->entitiesFromUnits(
            ClientUnit::query()
                ->with(['owner', 'tenant'])
                ->where('condominium_id', $unit->condominium_id)
                ->where('id', '<>', $unit->id)
                ->get()
        );

        $others = $this->entitiesFromUnits(
            ClientUnit::query()
                ->with(['owner', 'tenant'])
                ->where('condominium_id', '<>', $unit->condominium_id)
                ->limit(200)
                ->get()
        );

        return $sameCondo
            ->concat($others)
            ->filter(fn (ClientEntity $entity) => $entity->id !== $excludeEntityId)
            ->unique('id')
            ->filter(function (ClientEntity $entity) use ($targetName) {
                return AutomationText::similarity($entity->display_name, $targetName) < 85;
            })
            ->shuffle()
            ->take($limit)
            ->values();
    }

    public function cpfFinalCandidates(int $excludeEntityId, int $limit): Collection
    {
        return ClientEntity::query()
            ->whereNotNull('cpf_cnpj')
            ->where('id', '<>', $excludeEntityId)
            ->get()
            ->map(fn (ClientEntity $entity) => $this->cpfFinal($entity->cpf_cnpj))
            ->filter()
            ->unique()
            ->shuffle()
            ->take($limit)
            ->values();
    }

    private function entitiesFromUnits(Collection $units): Collection
    {
        return $units
            ->flatMap(function (ClientUnit $unit) {
                return collect([$unit->owner, $unit->tenant])->filter();
            })
            ->filter(fn (ClientEntity $entity) => trim((string) $entity->display_name) !== '');
    }

    private function cpfFinal(?string $document): ?string
    {
        $digits = AutomationText::digits($document);
        if (strlen($digits) !== 11) {
            return null;
        }

        return substr($digits, -5);
    }
}
