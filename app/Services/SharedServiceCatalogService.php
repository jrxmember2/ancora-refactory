<?php

namespace App\Services;

use App\Models\DemandCategory;
use App\Models\Servico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SharedServiceCatalogService
{
    private ?bool $supportsMapping = null;

    public function syncDemandCategoriesToProposalServices(): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        DemandCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(fn (DemandCategory $category) => $this->syncDemandCategory($category));
    }

    public function syncDemandCategory(DemandCategory $category): ?Servico
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $service = $this->findMappedOrNamedService($category);
        $payload = [
            'name' => trim((string) $category->name),
            'description' => 'Sincronizado automaticamente de Configuracoes > Demandas > Servicos.',
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) $category->sort_order,
        ];

        if ($this->supportsMapping()) {
            $payload['demand_category_id'] = (int) $category->id;
        }

        if ($service) {
            $service->fill($payload);

            if ($service->isDirty()) {
                $service->save();
            }

            return $service->fresh();
        }

        return Servico::query()->create($payload);
    }

    public function releaseDemandCategory(DemandCategory $category): void
    {
        if (!$this->tablesReady()) {
            return;
        }

        $service = $this->supportsMapping()
            ? Servico::query()->where('demand_category_id', $category->id)->first()
            : $this->findServiceByName((string) $category->name);

        if (!$service) {
            return;
        }

        $payload = [
            'is_active' => false,
            'description' => 'Servico legado preservado apos desvinculo de Configuracoes > Demandas > Servicos.',
        ];

        if ($this->supportsMapping()) {
            $payload['demand_category_id'] = null;
        }

        $service->fill($payload);

        if ($service->isDirty()) {
            $service->save();
        }
    }

    public function proposalServiceOptions(?int $selectedServiceId = null): Collection
    {
        $this->syncDemandCategoriesToProposalServices();

        $items = $this->mirroredServiceQuery()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($selectedServiceId && !$items->contains('id', $selectedServiceId)) {
            $current = Servico::query()->find($selectedServiceId);
            if ($current) {
                $items->push($current);
            }
        }

        return $items
            ->unique('id')
            ->sortBy(fn (Servico $item) => sprintf('%010d|%s', (int) ($item->sort_order ?? 0), Str::lower((string) $item->name)))
            ->values();
    }

    public function mirroredServices(): Collection
    {
        $this->syncDemandCategoriesToProposalServices();

        return $this->mirroredServiceQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function mirroredServiceQuery()
    {
        $query = Servico::query();

        if ($this->supportsMapping()) {
            return $query->whereNotNull('demand_category_id');
        }

        $categoryNames = DemandCategory::query()
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        if ($categoryNames === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('name', $categoryNames);
    }

    private function findMappedOrNamedService(DemandCategory $category): ?Servico
    {
        if ($this->supportsMapping()) {
            $mapped = Servico::query()
                ->where('demand_category_id', $category->id)
                ->first();

            if ($mapped) {
                return $mapped;
            }
        }

        return $this->findServiceByName((string) $category->name);
    }

    private function findServiceByName(string $name): ?Servico
    {
        $normalized = trim($name);
        if ($normalized === '') {
            return null;
        }

        return Servico::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($normalized)])
            ->first();
    }

    private function supportsMapping(): bool
    {
        if ($this->supportsMapping === null) {
            $this->supportsMapping = Schema::hasTable('servicos')
                && Schema::hasColumn('servicos', 'demand_category_id');
        }

        return $this->supportsMapping;
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('servicos') && Schema::hasTable('demand_categories');
    }
}
