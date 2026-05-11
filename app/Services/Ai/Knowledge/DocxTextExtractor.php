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
            $entries = $this->relevantEntries($zip);
            $parts = [];

            foreach ($entries as $entry) {
                $text = $this->readWordXml($zip, $entry);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }

            $text = $this->normalizeBlock(implode("\n\n", $parts));

            if ($text === '') {
                $text = $this->fallbackAcrossWordXmlEntries($zip);
            }
        } finally {
            $zip->close();
        }

        if ($text === '') {
            throw new \RuntimeException('Nao foi possivel extrair texto legivel do DOCX enviado.');
        }

        return $text;
    }

    /** @return list<string> */
    private function relevantEntries(\ZipArchive $zip): array
    {
        $preferred = [
            'word/document.xml',
            'word/footnotes.xml',
            'word/endnotes.xml',
            'word/comments.xml',
        ];

        $extra = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '') {
                continue;
            }

            if (preg_match('#^word/(header|footer)\d+\.xml$#i', $name)) {
                $extra[] = $name;
            }
        }

        return array_values(array_unique(array_merge($preferred, $extra)));
    }

    private function readWordXml(\ZipArchive $zip, string $entry): string
    {
        $xml = $zip->getFromName($entry);
        if ($xml === false || trim($xml) === '') {
            return '';
        }

        $text = $this->extractStructuredText($xml);
        if ($text !== '') {
            return $text;
        }

        return $this->fallbackTextFromXml($xml);
    }

    private function extractStructuredText(string $xml): string
    {
        libxml_use_internal_errors(true);
        $root = @simplexml_load_string($xml);
        libxml_clear_errors();

        if (!$root) {
            return '';
        }

        $root->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = [];

        foreach ($root->xpath('//w:p') ?: [] as $paragraph) {
            $paragraph->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $parts = [];

            foreach ($paragraph->xpath('.//w:t | .//w:instrText') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }

            $line = $this->normalizeLine(implode('', $parts));
            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return implode("\n", $paragraphs);
    }

    private function fallbackAcrossWordXmlEntries(\ZipArchive $zip): string
    {
        $parts = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if ($name === '' || !$this->shouldUseFallbackEntry($name)) {
                continue;
            }

            $xml = $zip->getFromName($name);
            if ($xml === false || trim($xml) === '') {
                continue;
            }

            $text = $this->fallbackTextFromXml($xml);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $this->normalizeBlock(implode("\n\n", array_unique($parts)));
    }

    private function shouldUseFallbackEntry(string $entry): bool
    {
        if (!preg_match('#^word/.+\.xml$#i', $entry)) {
            return false;
        }

        return !preg_match('#^word/(_rels/|styles(\w+)?\.xml$|fontTable\.xml$|numbering\.xml$|settings\.xml$|webSettings\.xml$|theme/|commentsExtended\.xml$|people\.xml$)#i', $entry);
    }

    private function fallbackTextFromXml(string $xml): string
    {
        $xml = preg_replace('/<w:(tab)[^>]*\/>/iu', ' ', $xml) ?? $xml;
        $xml = preg_replace('/<w:(br|cr)[^>]*\/>/iu', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:(p|tr|tbl|tc|body|hdr|ftr)>/iu', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<[^>]+>/', ' ', $xml) ?? $xml;
        $xml = html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->normalizeBlock($xml);
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
        $value = preg_replace("/[ \t]+\n/u", "\n", $value) ?? $value;
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
