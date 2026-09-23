<?php

namespace Tests\Feature;

use App\Support\AccentFolder;
use App\Support\LikeSearch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LikeSearchTest extends TestCase
{
    /**
     * The SQL pre-filter must accept every value that the PHP match accepts, or
     * search loses results.
     */
    public function test_the_accent_insensitive_pattern_accepts_every_folded_match(): void
    {
        $values = ['Mélusine', 'MELUSINE', 'forêt', 'FORÊT', 'Ève', 'ça', 'Çà', 'año', 'naïve', 'Ÿvon', 'a_b', '50%', 'stop!now', 'cœur', 'Œuvre'];
        $terms = ['melusine', 'MÉLUSINE', 'foret', 'FORÊT', 'eve', 'ÈVE', 'ca', 'ano', 'naive', 'yvon', 'a_b', '50%', 'stop!', 'cœur', 'Œuvre', 'xyz', 'axb'];

        foreach ($values as $value) {
            foreach ($terms as $term) {
                $phpMatch = str_contains(AccentFolder::fold($value), AccentFolder::fold($term));
                $sqlMatch = (bool) DB::selectOne('select ? like ? escape ? as hit', [
                    $value, LikeSearch::accentInsensitivePattern($term), LikeSearch::ESCAPE,
                ])->hit;

                if ($phpMatch) {
                    $this->assertTrue($sqlMatch, "SQL must accept '{$value}' for '{$term}'");
                }
            }
        }
    }

    public function test_the_accent_insensitive_pattern_still_rejects_other_text(): void
    {
        $hit = fn (string $value, string $term) => (bool) DB::selectOne('select ? like ? escape ? as hit', [
            $value, LikeSearch::accentInsensitivePattern($term), LikeSearch::ESCAPE,
        ])->hit;

        $this->assertFalse($hit('the year 1950', '50%'));
        $this->assertFalse($hit('value axb', 'a_b'));
        $this->assertFalse($hit('a quiet chapter', 'lantern'));
    }
}
