<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Support\Contracts\ContractCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class ContractReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        $query = Contract::query()->with(['client', 'condominium', 'category', 'responsible']);
        $this->applyFilters($query, $filters);
        $items = $query->orderBy('title')->get();

        return view('pages.contratos.reports.index', [
            'title' => 'Relatórios de contratos',
            'filters' => $filters,
            'items' => $items,
            'summary' => [
                'total' => $items->count(),
                'contracted_revenue' => $items->sum(fn (Contract $item) => (float) ($item->monthly_value ?? $item->contract_value ?? $item->total_value ?? 0)),
                'active' => $items->where('status', 'ativo')->count(),
                'expired' => $items->filter(fn (Contract $item) => $item->end_date && $item->end_date->isPast() && !$item->indefinite_term)->count(),
                'rescinded' => $items->where('status', 'rescindido')->count(),
            ],
            'categories' => ContractCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'clients' => ClientEntity::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name']),
            'condominiums' => ClientCondominium::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statusLabels' => ContractCatalog::statuses(),
            'typeOptions' => ContractCatalog::types(),
            'grouped' => [
                'status' => $items->groupBy('status'),
                'type' => $items->groupBy('type'),
                'client' => $items->groupBy(fn (Contract $item) => $item->client?->display_name ?: 'Sem cliente'),
                'condominium' => $items->groupBy(fn (Contract $item) => $item->condominium?->name ?: 'Sem condomínio'),
            ],
        ]);
    }

    public function exportCsv(Request $request): BinaryFileResponse
    {
        $filters = $this->filtersFromRequest($request);
        $query = Contract::query()->with(['client', 'condominium', 'category', 'responsible']);
        $this->applyFilters($query, $filters);
        $items = $query->orderBy('title')->get();

        $dir = storage_path('app/generated/contracts-reports');
        File::ensureDirectoryExists($dir);
        $path = $dir . DIRECTORY_SEPARATOR . 'contracts-report-' . now()->format('YmdHis') . '.csv';

        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Código', 'Título', 'Cliente', 'Condomínio', 'Tipo', 'Categoria', 'Status', 'Início', 'Término', 'Valor', 'Responsável'], ';');
        foreach ($items as $item) {
            fputcsv($handle, [
                $item->code,
                $item->title,
                $item->client?->display_name,
                $item->condominium?->name,
                $item->type,
                $item->category?->name,
                ContractCatalog::statuses()[$item->status] ?? $item->status,
                optional($item->start_date)->format('d/m/Y'),
                $item->indefinite_term ? 'Prazo indeterminado' : optional($item->end_date)->format('d/m/Y'),
                number_format((float) ($item->contract_value ?? $item->monthly_value ?? $item->total_value ?? 0), 2, ',', '.'),
                $item->responsible?->name,
            ], ';');
        }
        fclose($handle);

        return response()->download($path, basename($path), ['Content-Type' => 'text/csv; charset=UTF-8'])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request): View|BinaryFileResponse
    {
        $filters = $this->filtersFromRequest($request);
        $query = Contract::query()->with(['client', 'condominium', 'category', 'responsible']);
        $this->applyFilters($query, $filters);
        $items = $query->orderBy('title')->get();

        $payload = [
            'items' => $items,
            'filters' => $filters,
            'summary' => [
                'total' => $items->count(),
                'contracted_revenue' => $items->sum(fn (Contract $item) => (float) ($item->monthly_value ?? $item->contract_value ?? $item->total_value ?? 0)),
            ],
            'statusLabels' => ContractCatalog::statuses(),
            'pdfMode' => true,
        ];

        $dir = storage_path('app/generated/contracts-reports');
        File::ensureDirectoryExists($dir);
        $htmlPath = $dir . DIRECTORY_SEPARATOR . 'contracts-report-' . now()->format('YmdHis') . '.html';
        $pdfPath = $dir . DIRECTORY_SEPARATOR . 'contracts-report-' . now()->format('YmdHis') . '.pdf';
        File::put($htmlPath, view('pages.contratos.reports.pdf', $payload)->render());

        $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath) || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);
        File::delete($htmlPath);

        if (!$generated || !is_file($pdfPath)) {
            File::delete($pdfPath);
            return view('pages.contratos.reports.pdf', array_merge($payload, ['pdfMode' => false]));
        }

        return response()->download($pdfPath, basename($pdfPath), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'client_id' => (int) $request->integer('client_id') ?: null,
            'condominium_id' => (int) $request->integer('condominium_id') ?: null,
            'type' => trim((string) $request->input('type', '')),
            'category_id' => (int) $request->integer('category_id') ?: null,
            'status' => trim((string) $request->input('status', '')),
            'start_from' => trim((string) $request->input('start_from', '')),
            'end_to' => trim((string) $request->input('end_to', '')),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['client_id']) {
            $query->where('client_id', $filters['client_id']);
        }
        if ($filters['condominium_id']) {
            $query->where('condominium_id', $filters['condominium_id']);
        }
        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }
        if ($filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['start_from'] !== '') {
            $query->whereDate('start_date', '>=', $filters['start_from']);
        }
        if ($filters['end_to'] !== '') {
            $query->whereDate('end_date', '<=', $filters['end_to']);
        }
    }

    private function renderPdfWithChromium(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable(['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable']);
        if (!$binary) {
            return false;
        }

        $profileDir = dirname($pdfPath) . DIRECTORY_SEPARATOR . pathinfo($pdfPath, PATHINFO_FILENAME) . '-chrome-profile';
        File::ensureDirectoryExists($profileDir);

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--no-first-run',
                '--no-default-browser-check',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf-no-header',
                '--user-data-dir=' . $profileDir,
                '--print-to-pdf=' . $pdfPath,
                'file://' . str_replace('\\', '/', $htmlPath),
            ], timeout: 120);
            $process->run();
            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        } finally {
            File::deleteDirectory($profileDir);
        }
    }

    private function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable(['wkhtmltopdf']);
        if (!$binary) {
            return false;
        }

        try {
            $process = new Process([
                $binary,
                '--enable-local-file-access',
                '--encoding',
                'UTF-8',
                '--page-size',
                'A4',
                '--margin-top',
                '10',
                '--margin-right',
                '10',
                '--margin-bottom',
                '10',
                '--margin-left',
                '10',
                $htmlPath,
                $pdfPath,
            ], timeout: 120);
            $process->run();
            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function availableExecutable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate, '--version'], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // segue
            }

            try {
                $where = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN' ? 'where' : 'which';
                $process = new Process([$where, $candidate], timeout: 10);
                $process->run();
                if ($process->isSuccessful()) {
                    $path = trim((string) $process->getOutput());
                    if ($path !== '') {
                        return preg_split('/\r\n|\r|\n/', $path)[0];
                    }
                }
            } catch (\Throwable) {
                // segue
            }
        }

        return null;
    }
}
