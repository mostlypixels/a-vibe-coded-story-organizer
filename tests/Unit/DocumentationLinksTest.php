<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the cross-references inside `documentation/`.
 *
 * The docs are deliberately split: `architecture.md` is a short map, and each big
 * feature keeps its detail in its own page (`revisions.md`, `codex.md`,
 * `epub-export.md`, …). That split is what keeps the map readable, but it also means a
 * heading can move to another file while links to it stay behind — pointing at an
 * anchor that silently no longer exists. Markdown has no compiler to catch that, and a
 * dead `#anchor` renders as a working link that lands at the top of the page, so a
 * human reviewer will not notice either.
 *
 * Three things are checked:
 *  - every relative `*.md` link target exists;
 *  - every `#anchor` matches a real heading in the file it points at;
 *  - every page under `documentation/` is listed in `architecture.md`'s index table,
 *    so a new deep-dive cannot be written and then be undiscoverable.
 *
 * Plain filesystem assertions, no database — hence a Unit test that runs under
 * `composer test` (and therefore CI) with no extra wiring.
 */
class DocumentationLinksTest extends TestCase
{
    /** The map page whose index table must list every other page. */
    private const INDEX_PAGE = 'architecture.md';

    /**
     * Matches a Markdown inline link whose target is a local `.md` file, an `#anchor`
     * on the current page, or both. External links (`http…`) and image embeds are
     * skipped by the pattern itself.
     */
    private const LINK_PATTERN = '/\]\(([A-Za-z0-9._-]*\.md)?(#[^)\s]+)?\)/';

    private function documentationRoot(): string
    {
        // tests/Unit → repo root is two levels up.
        return dirname(__DIR__, 2).'/documentation';
    }

    /** @return list<string> every page's basename, e.g. ['architecture.md', …] */
    private function pages(): array
    {
        return array_map('basename', glob($this->documentationRoot().'/*.md'));
    }

    public function test_documentation_folder_is_not_empty(): void
    {
        // A silent glob failure would make every other test here vacuously pass.
        $this->assertNotEmpty($this->pages(), 'Expected at least one page under documentation/.');
    }

    public function test_every_relative_markdown_link_points_at_a_file_that_exists(): void
    {
        $pages = $this->pages();
        $checked = 0;

        foreach ($pages as $page) {
            foreach ($this->linksIn($page) as [$target, $anchor, $line]) {
                if ($target === null) {
                    continue; // A bare #anchor on the current page; covered by the anchor test.
                }

                $checked++;

                $this->assertContains(
                    $target,
                    $pages,
                    "documentation/$page:$line links to '$target', which does not exist. "
                    .'Either the file was renamed or the link has a typo — a dead relative link '
                    .'renders as a working one on GitHub, so nothing else catches this.'
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected the docs to cross-link at least once.');
    }

    public function test_every_anchor_matches_a_real_heading(): void
    {
        $headings = [];

        foreach ($this->pages() as $page) {
            $headings[$page] = $this->headingSlugsIn($page);
        }

        $checked = 0;

        foreach ($this->pages() as $page) {
            foreach ($this->linksIn($page) as [$target, $anchor, $line]) {
                if ($anchor === null) {
                    continue;
                }

                // A link with no file part anchors within the page it sits on.
                $destination = $target ?? $page;

                if (! isset($headings[$destination])) {
                    continue; // The file itself is missing; the previous test reports that.
                }

                $checked++;
                $slug = ltrim($anchor, '#');

                $this->assertContains(
                    $slug,
                    $headings[$destination],
                    "documentation/$page:$line links to '$destination#$slug', but that page has "
                    .'no heading with that anchor. Headings move between pages when a section is '
                    .'split out — update the link to wherever the section landed.'
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected the docs to use at least one anchor link.');
    }

    public function test_every_page_is_listed_in_the_architecture_index(): void
    {
        $index = file_get_contents($this->documentationRoot().'/'.self::INDEX_PAGE);
        $this->assertNotFalse($index, self::INDEX_PAGE.' is unreadable.');

        foreach ($this->pages() as $page) {
            if ($page === self::INDEX_PAGE) {
                continue; // The map does not list itself.
            }

            $this->assertStringContainsString(
                "]($page)",
                $index,
                "documentation/$page is not linked from ".self::INDEX_PAGE.'. Every page belongs '
                .'in its index table ("The rest of the documentation") — an unlisted page is one '
                .'nobody finds, which is how a deep-dive rots.'
            );
        }
    }

    /**
     * Every local Markdown link in a page, as [target file|null, #anchor|null, line number].
     *
     * @return list<array{0: string|null, 1: string|null, 2: int}>
     */
    private function linksIn(string $page): array
    {
        $contents = file_get_contents($this->documentationRoot().'/'.$page);

        if ($contents === false) {
            return [];
        }

        $links = [];

        foreach (preg_split('/\r?\n/', $contents) as $index => $line) {
            if (! preg_match_all(self::LINK_PATTERN, $line, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $target = ($match[1] ?? '') !== '' ? $match[1] : null;
                $anchor = ($match[2] ?? '') !== '' ? $match[2] : null;

                if ($target === null && $anchor === null) {
                    continue;
                }

                $links[] = [$target, $anchor, $index + 1];
            }
        }

        return $links;
    }

    /**
     * Every heading in a page, as the anchor slug GitHub generates for it.
     *
     * @return list<string>
     */
    private function headingSlugsIn(string $page): array
    {
        $contents = file_get_contents($this->documentationRoot().'/'.$page);

        if ($contents === false) {
            return [];
        }

        $slugs = [];
        $inFence = false;

        foreach (preg_split('/\r?\n/', $contents) as $line) {
            // A `#` inside a fenced code block is a comment, not a heading.
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = ! $inFence;

                continue;
            }

            if (! $inFence && preg_match('/^#{1,6}\s+(.*)$/', $line, $match)) {
                $slugs[] = $this->slug($match[1]);
            }
        }

        return $slugs;
    }

    /**
     * GitHub's heading-anchor algorithm: lowercase, drop everything that is not a
     * word character, space or hyphen (so backticks, punctuation and em dashes vanish
     * while their surrounding spaces remain), then turn spaces into hyphens. Two words
     * separated by " — " therefore yield a double hyphen, which is why this has to be
     * reproduced exactly rather than approximated.
     */
    private function slug(string $heading): string
    {
        $slug = mb_strtolower(trim($heading));
        $slug = preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $slug);

        return preg_replace('/\s/u', '-', trim($slug));
    }
}
