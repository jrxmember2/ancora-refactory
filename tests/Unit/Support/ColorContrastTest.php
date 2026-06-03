<?php

namespace Tests\Unit\Support;

use App\Support\ColorContrast;
use PHPUnit\Framework\TestCase;

class ColorContrastTest extends TestCase
{
    public function test_normalize_hex(): void
    {
        $this->assertSame('#ffffff', ColorContrast::normalizeHex('#FFF'));
        $this->assertSame('#abc123', ColorContrast::normalizeHex('abc123'));
        $this->assertSame('#0a0a0a', ColorContrast::normalizeHex('#0A0A0A'));
        $this->assertNull(ColorContrast::normalizeHex('xyz'));
        $this->assertNull(ColorContrast::normalizeHex('#12345'));
        $this->assertNull(ColorContrast::normalizeHex(''));
        $this->assertNull(ColorContrast::normalizeHex(null));
    }

    public function test_ideal_text_color_contrasts_with_background(): void
    {
        // Fundos claros -> texto escuro
        $this->assertSame('#111827', ColorContrast::idealTextColor('#ffffff'));
        $this->assertSame('#111827', ColorContrast::idealTextColor('#ffeb3b')); // amarelo
        // Fundos escuros -> texto claro
        $this->assertSame('#ffffff', ColorContrast::idealTextColor('#000000'));
        $this->assertSame('#ffffff', ColorContrast::idealTextColor('#1e3a8a')); // azul escuro
        // Cor invalida -> texto escuro (padrao seguro)
        $this->assertSame('#111827', ColorContrast::idealTextColor('invalida'));
    }
}
