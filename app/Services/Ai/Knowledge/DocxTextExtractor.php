<?php

namespace App\Services\Ai\Knowledge;

class DocxTextExtractor
{
    public function extract(string $absolutePath): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('A extensao ZIP do PHP nao esta disponivel para ler arquivos .docx.');
        }

        if (!is_file($absolutePath)) {
            throw new \RuntimeException('O arquivo DOCX informado nao foi encontrado no disco.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Nao foi possivel abrir o arquivo DOCX informado.');
        }

        try {
            $parts = array_filter([
                $this->readWordXml($zip, 'word/document.xml'),
                $this->readWordXml($zip, 'word/footnotes.xml'),
                $this->readWordXml($zip, 'word/endnotes.xml'),
            ]);
        } finally {
            $zip->close();
        }

        $text = trim(implode("\n\n", $parts));
        if ($text === '') {
            throw new \RuntimeException('Nao foi possivel extrair texto legivel do DOCX enviado.');
        }

        return $this->normalizeBlock($text);
    }

    private function readWordXml(\ZipArchive $zip, string $entry): string
    {
        $xml = $zip->getFromName($entry);
        if ($xml === false || trim($xml) === '') {
            return '';
        }

        $root = @simplexml_load_string($xml);
        if (!$root) {
            return '';
        }

        $root->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = [];

        foreach ($root->xpath('//w:p') ?: [] as $paragraph) {
            $paragraph->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $parts = [];

            foreach ($paragraph->xpath('.//w:t') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }

            $line = $this->normalizeLine(implode('', $parts));
            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return implode("\n", $paragraphs);
    }

    private function normalizeLine(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t", "\xc2\xa0"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function normalizeBlock(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        $lines = preg_split('/\n/u', $value) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $clean = $this->normalizeLine($line);
            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        return trim(implode("\n", $normalized));
    }
}
