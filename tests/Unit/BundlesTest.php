<?php

namespace Tests\Unit;

use App\Enums\CodexEntryType;
use App\Enums\Genre;
use App\Support\Bundles\BlankBundle;
use App\Support\Bundles\Bundles;
use App\Support\Bundles\ContemporaryBundle;
use App\Support\Bundles\FantasyBundle;
use App\Support\Bundles\GenreBundle;
use App\Support\Bundles\HistoricalBundle;
use App\Support\Bundles\ScienceFictionBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Guard genre bundle resolution and bundle content invariants. */
class BundlesTest extends TestCase
{
    public function test_each_genre_resolves_to_its_own_bundle_class(): void
    {
        $this->assertInstanceOf(ContemporaryBundle::class, Bundles::for(Genre::Contemporary));
        $this->assertInstanceOf(HistoricalBundle::class, Bundles::for(Genre::Historical));
        $this->assertInstanceOf(FantasyBundle::class, Bundles::for(Genre::Fantasy));
        $this->assertInstanceOf(ScienceFictionBundle::class, Bundles::for(Genre::ScienceFiction));
        $this->assertInstanceOf(BlankBundle::class, Bundles::for(Genre::Blank));
    }

    public function test_blank_returns_empty_lists_everywhere(): void
    {
        $bundle = Bundles::for(Genre::Blank);

        $this->assertSame([], $bundle->attributes());
        $this->assertSame([], $bundle->tags());
        $this->assertSame([], $bundle->sampleEntries());
        $this->assertSame([], $bundle->skeleton());
    }

    /** @return array<int, array<int, GenreBundle>> */
    public static function genreBundles(): array
    {
        return array_map(
            fn (Genre $genre) => [Bundles::for($genre)],
            Genre::cases(),
        );
    }

    #[DataProvider('genreBundles')]
    public function test_declared_applies_to_values_are_valid_codex_entry_types(GenreBundle $bundle): void
    {
        $attributes = $bundle->attributes();

        // Assert on the collection itself, not only inside the loop, so a
        // bundle with no attributes (Blank) still makes an assertion.
        $this->assertIsArray($attributes);

        foreach ($attributes as $attribute) {
            foreach ($attribute->appliesTo as $type) {
                $this->assertInstanceOf(CodexEntryType::class, $type);
                $this->assertContains($type, CodexEntryType::cases());
            }
        }
    }

    #[DataProvider('genreBundles')]
    public function test_sample_entry_attribute_keys_match_attribute_names_the_bundle_declares(GenreBundle $bundle): void
    {
        $declaredNames = array_map(fn ($attribute) => $attribute->name, $bundle->attributes());
        $sampleEntries = $bundle->sampleEntries();

        // Assert on the collection itself, not only inside the loop, so a
        // bundle with no sample entries (Blank) still makes an assertion.
        $this->assertIsArray($sampleEntries);

        foreach ($sampleEntries as $entry) {
            foreach (array_keys($entry->attributeValues) as $name) {
                $this->assertContains(
                    $name,
                    $declaredNames,
                    "Sample entry [{$entry->name}] sets attribute [{$name}], which the bundle does not declare."
                );
            }
        }
    }
}
