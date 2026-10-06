<?php

namespace Tests\Unit\Support;

use App\Support\ScaleLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScaleLabelTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function labels(): array
    {
        return [
            'px from a percentage' => ['100%', 'px', '16px'],
            'times from a percentage' => ['115%', 'times', '1.15×'],
            'times from a whole ratio' => ['1', 'times', '1×'],
            'times from a decimal ratio' => ['1.25', 'times', '1.25×'],
            'unknown format' => ['1.25', 'ratio', '1.25'],
        ];
    }

    #[DataProvider('labels')]
    public function test_it_formats_the_value(string $value, string $format, string $expected): void
    {
        $this->assertSame($expected, ScaleLabel::format($value, $format));
    }
}
