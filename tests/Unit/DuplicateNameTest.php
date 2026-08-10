<?php

namespace Tests\Unit;

use App\Support\DuplicateName;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DuplicateNameTest extends TestCase
{
    #[DataProvider('suggestionProvider')]
    public function test_suggests_a_free_name(string $name, array $taken, string $expected): void
    {
        $this->assertSame($expected, DuplicateName::suggest($name, $taken));
    }

    public static function suggestionProvider(): array
    {
        return [
            'no collision' => ['Arrival', [], 'Arrival (2)'],
            'source name taken' => ['Arrival', ['Arrival'], 'Arrival (2)'],
            'first suffix also taken' => ['Arrival', ['Arrival', 'Arrival (2)'], 'Arrival (3)'],
            'already suffixed source, suffix taken' => ['Arrival (2)', ['Arrival (2)'], 'Arrival (3)'],
            'case-insensitive match' => ['arrival', ['ARRIVAL'], 'arrival (2)'],
            'already suffixed source, free suffix still re-suggested' => ['Arrival (2)', [], 'Arrival (2)'],
        ];
    }
}
