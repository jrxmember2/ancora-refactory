<?php

namespace App\Services\Ai\Knowledge;

use App\Services\Ai\DocumentTextExtractor;

class DocxTextExtractor
{
    public function __construct(
        private readonly DocumentTextExtractor $extractor,
    ) {
    }

    public function extract(string $absolutePath): string
    {
        return $this->extractor->extract($absolutePath);
    }
}
