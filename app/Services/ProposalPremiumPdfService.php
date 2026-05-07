<?php

namespace App\Services;

use App\Models\Proposal;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProposalPremiumPdfService
{
    public function generate(Proposal $proposal): string
    {
        $render = ProposalRenderService::buildByPropostaId((int) $proposal->id);

        if (!$render) {
            throw new \RuntimeException('O documento premium da proposta ainda nao foi salvo.');
        }

        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('A biblioteca mPDF nao esta disponivel neste ambiente.');
        }

        $directory = storage_path('app/temp/proposals');
        File::ensureDirectoryExists($directory);

        $pdfPath = $directory . DIRECTORY_SEPARATOR . Str::uuid() . '.pdf';
        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        $html = view('pages.propostas.documentos.pdf', [
            'render' => $render,
            'proposta' => $proposal,
            'inlineCss' => $this->inlineCss(),
        ])->render();

        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => $tempDir,
                'format' => 'A4',
                'orientation' => 'P',
                'margin_top' => 0,
                'margin_right' => 0,
                'margin_bottom' => 0,
                'margin_left' => 0,
            ]);

            $mpdf->showImageErrors = false;
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            $mpdf->WriteHTML($html);
            $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);
        } catch (\Throwable $e) {
            File::delete($pdfPath);

            throw new \RuntimeException('Falha ao renderizar o PDF com mPDF.', previous: $e);
        }

        if (!is_file($pdfPath)) {
            throw new \RuntimeException('O arquivo PDF nao foi gerado corretamente.');
        }

        return $pdfPath;
    }

    public function downloadFilename(Proposal $proposal): string
    {
        $base = $proposal->proposal_code ?: $proposal->client_name ?: 'proposta-premium';

        return Str::slug((string) $base) . '.pdf';
    }

    private function inlineCss(): string
    {
        $path = public_path('assets/css/proposal-template-aquarela.css');

        if (!is_file($path)) {
            return '';
        }

        return (string) File::get($path);
    }
}
