<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the `.specs/` layout. `draft/` holds feature folders directly
 * (`.specs/draft/<name>/` — a draft has no lifecycle date yet); every other status
 * groups its features into month buckets named for the date the feature entered that
 * stage (`.specs/<status>/<YYYY-MM>/<name>/`), and the spec's frontmatter carries the
 * matching date stamp (`expanded:` / `planned:` / `shipped:`). The folder location and
 * the frontmatter encode the lifecycle stage redundantly, so they can drift — this test
 * is the reconciler that catches it (e.g. a feature implemented and moved to `shipped/`
 * but left stamped `planned`, or filed in a bucket that disagrees with its date stamp).
 * It also guards name uniqueness across the tree: locating a feature by name must
 * resolve to one folder, so no feature name may appear twice anywhere under `.specs/`.
 *
 * Plain filesystem assertions, no database — hence a Unit test that runs under
 * `composer test` (and therefore CI) with no extra wiring.
 */
class SpecsStatusConsistencyTest extends TestCase
{
    /** The four lifecycle stages, which are also the only allowed status subfolders. */
    private const KNOWN_STATUSES = ['draft', 'expanded', 'planned', 'shipped'];

    /** Statuses whose features live inside a YYYY-MM month bucket (all but draft). */
    private const BUCKETED_STATUSES = ['expanded', 'planned', 'shipped'];

    private const BUCKET_PATTERN = '/^\d{4}-(0[1-9]|1[0-2])$/';

    private function specsRoot(): string
    {
        // tests/Unit → repo root is two levels up.
        return dirname(__DIR__, 2).'/.specs';
    }

    public function test_specs_root_holds_only_status_subfolders(): void
    {
        foreach (glob($this->specsRoot().'/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);

            $this->assertContains(
                $name,
                self::KNOWN_STATUSES,
                ".specs/$name/ is not a valid status folder. The root may hold only "
                .implode(', ', self::KNOWN_STATUSES)
                .' (feature folders go deeper: .specs/draft/<name>/ or .specs/<status>/<YYYY-MM>/<name>/).'
            );
        }
    }

    public function test_no_feature_folder_sits_directly_under_specs_root(): void
    {
        // A spec.md exactly one level under .specs/ means a feature was not filed
        // under a status subfolder.
        foreach (glob($this->specsRoot().'/*/spec.md') as $misplaced) {
            $this->fail(
                'Feature folder '.dirname($misplaced).' sits directly under .specs/; '
                .'move it under a status subfolder (.specs/draft/<name>/ or .specs/<status>/<YYYY-MM>/<name>/).'
            );
        }

        $this->assertTrue(true, 'No misplaced feature folders.');
    }

    public function test_bucketed_statuses_hold_only_month_buckets(): void
    {
        // Under expanded/, planned/, and shipped/ the first level must be YYYY-MM
        // month buckets — a feature folder directly under the status (the pre-bucket
        // layout, or a stage move that skipped the bucket) is a violation. A folder
        // containing spec.md is a feature; anything else is just a badly named bucket.
        foreach (self::BUCKETED_STATUSES as $status) {
            foreach (glob($this->specsRoot()."/$status/*", GLOB_ONLYDIR) as $dir) {
                $bucket = basename($dir);

                $this->assertMatchesRegularExpression(
                    self::BUCKET_PATTERN,
                    $bucket,
                    ".specs/$status/$bucket/ is not a YYYY-MM month bucket. Features under "
                    ."'$status' live at .specs/$status/<YYYY-MM>/<name>/, bucketed by the month "
                    ."the feature entered the stage (its `$status:` frontmatter date)."
                );
            }
        }
    }

    public function test_draft_features_are_not_bucketed(): void
    {
        // Drafts have no lifecycle date yet, so draft/ holds feature folders directly.
        // A spec.md two levels under draft/ means someone bucketed a draft by hand.
        foreach (glob($this->specsRoot().'/draft/*/*/spec.md') as $misplaced) {
            $this->fail(
                'Draft feature '.dirname($misplaced).' is nested too deep; drafts are not '
                .'bucketed — file it at .specs/draft/<name>/ (a draft has no stage date to bucket by).'
            );
        }

        $this->assertTrue(true, 'No bucketed draft features.');
    }

    public function test_no_feature_name_is_reused_anywhere_in_the_tree(): void
    {
        // A feature is located by name with the globs `.specs/draft/<name>/` and
        // `.specs/*/*/<name>/`, so a name must resolve to exactly one folder. The
        // collision happens when new work reuses a name that already shipped (e.g. a
        // fresh `draft/foo` beside an existing `shipped/2026-07/foo`): the lookup then
        // matches two folders and every skill that locates by name silently picks the
        // wrong one — or clobbers the shipped spec on the next `git mv`. Catch it here.
        $foldersByName = [];

        foreach (glob($this->specsRoot().'/draft/*', GLOB_ONLYDIR) as $dir) {
            $foldersByName[basename($dir)][] = 'draft';
        }

        foreach (self::BUCKETED_STATUSES as $status) {
            foreach (glob($this->specsRoot()."/$status/*/*", GLOB_ONLYDIR) as $dir) {
                $foldersByName[basename($dir)][] = $status.'/'.basename(dirname($dir));
            }
        }

        foreach ($foldersByName as $name => $locations) {
            $this->assertCount(
                1,
                $locations,
                "Feature '$name' exists in multiple places (".implode(', ', $locations).'). '
                .'Feature names must be unique across the whole .specs/ tree so locating by name '
                .'resolves to one folder. The pipeline auto-suffixes a colliding name with the move '
                ."date ('$name-YYYY-MM-DD') when a stage moves the folder; if you created this "
                .'collision by hand, rename the newer folder the same way (see .specs/README.md → '
                .'Name-collision handling).'
            );
        }
    }

    public function test_every_spec_frontmatter_matches_its_folder(): void
    {
        $checked = 0;

        // Drafts: .specs/draft/<name>/spec.md — status must be 'draft', no date required.
        foreach (glob($this->specsRoot().'/draft/*/spec.md') as $spec) {
            $checked++;
            $relative = '.specs/draft/'.basename(dirname($spec)).'/spec.md';

            $this->assertSame(
                'draft',
                $this->frontmatterValue($spec, 'status'),
                "$relative lives under draft/ but does not declare `status: draft`."
            );
        }

        // Bucketed stages: .specs/<status>/<YYYY-MM>/<name>/spec.md — status must match,
        // and the stage's date stamp (`expanded:` / `planned:` / `shipped:`) must exist
        // and fall inside the bucket month.
        foreach (self::BUCKETED_STATUSES as $status) {
            foreach (glob($this->specsRoot()."/$status/*/*/spec.md") as $spec) {
                $checked++;
                $bucket = basename(dirname($spec, 2));
                $relative = ".specs/$status/$bucket/".basename(dirname($spec)).'/spec.md';

                $declared = $this->frontmatterValue($spec, 'status');
                $this->assertSame(
                    $status,
                    $declared,
                    "$relative declares status '".($declared ?? 'none')."' but lives in '$status/' — "
                    .'the frontmatter stamp and the folder disagree.'
                );

                $stageDate = $this->frontmatterValue($spec, $status);
                $this->assertNotNull(
                    $stageDate,
                    "$relative has no `$status: YYYY-MM-DD` date stamp — every stage past draft "
                    .'stamps the date the feature entered it, and that date names the month bucket.'
                );

                $this->assertSame(
                    $bucket,
                    substr($stageDate, 0, 7),
                    "$relative is filed in bucket '$bucket' but its `$status:` date is '$stageDate' — "
                    .'the bucket must be the YYYY-MM of the stage date stamp.'
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected at least one feature spec under .specs/.');
    }

    /**
     * Read a scalar `<key>: value` from a spec's leading YAML frontmatter block, or
     * null when the file has no frontmatter or no such line.
     */
    private function frontmatterValue(string $file, string $key): ?string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        // Frontmatter must be the very first thing in the file: --- ... ---.
        if (! preg_match('/\A---\r?\n(.*?)\r?\n---/s', $contents, $block)) {
            return null;
        }

        if (! preg_match('/^'.preg_quote($key, '/').':\s*(\S+)/m', $block[1], $match)) {
            return null;
        }

        return trim($match[1], "\"' ");
    }
}
