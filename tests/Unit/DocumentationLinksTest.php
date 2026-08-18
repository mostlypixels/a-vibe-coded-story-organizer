<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Guards local links and navigation in documentation/. */
class DocumentationLinksTest extends TestCase
{
    private const INDEX_PAGE = 'README.md';

    private const LINK_PATTERN = '/\]\((?!https?:\/\/|mailto:)([^)\s#]*\.md)?(#[^)\s]+)?\)/';

    private function documentationRoot(): string
    {
        return dirname(__DIR__, 2).'/documentation';
    }

    /** @return list<string> paths relative to documentation/ */
    private function pages(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->documentationRoot(), FilesystemIterator::SKIP_DOTS)
        );
        $pages = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $pages[] = str_replace('\\', '/', $iterator->getSubPathname());
            }
        }

        sort($pages);

        return $pages;
    }

    public function test_documentation_folder_is_not_empty(): void
    {
        $this->assertNotEmpty($this->pages());
    }

    public function test_every_relative_markdown_link_has_a_valid_destination(): void
    {
        $pages = $this->pages();
        $headings = array_fill_keys($pages, []);

        foreach ($pages as $page) {
            $headings[$page] = $this->headingSlugsIn($page);
        }

        $checkedFiles = 0;
        $checkedAnchors = 0;

        foreach ($pages as $page) {
            foreach ($this->linksIn($page) as [$target, $anchor, $line]) {
                $destination = $target === null ? $page : $this->resolve($page, $target);

                if ($target !== null) {
                    $checkedFiles++;
                    $this->assertContains(
                        $destination,
                        $pages,
                        "documentation/$page:$line links to missing page '$destination'."
                    );
                }

                if ($anchor !== null && isset($headings[$destination])) {
                    $checkedAnchors++;
                    $slug = ltrim($anchor, '#');
                    $this->assertContains(
                        $slug,
                        $headings[$destination],
                        "documentation/$page:$line links to missing anchor '$destination#$slug'."
                    );
                }
            }
        }

        $this->assertGreaterThan(0, $checkedFiles);
        $this->assertGreaterThan(0, $checkedAnchors);
    }

    public function test_every_page_is_reachable_from_the_documentation_index(): void
    {
        $pages = $this->pages();
        $this->assertContains(self::INDEX_PAGE, $pages);

        $visited = [];
        $queue = [self::INDEX_PAGE];

        while ($queue !== []) {
            $page = array_shift($queue);

            if (isset($visited[$page])) {
                continue;
            }

            $visited[$page] = true;

            foreach ($this->linksIn($page) as [$target]) {
                if ($target === null) {
                    continue;
                }

                $destination = $this->resolve($page, $target);

                if (in_array($destination, $pages, true) && ! isset($visited[$destination])) {
                    $queue[] = $destination;
                }
            }
        }

        $unreachable = array_values(array_diff($pages, array_keys($visited)));

        $this->assertSame(
            [],
            $unreachable,
            'Every page must be reachable from documentation/README.md. Unreachable: '.implode(', ', $unreachable)
        );
    }

    /** @return list<array{0: string|null, 1: string|null, 2: int}> */
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

                if ($target !== null || $anchor !== null) {
                    $links[] = [$target, $anchor, $index + 1];
                }
            }
        }

        return $links;
    }

    private function resolve(string $source, string $target): string
    {
        $parts = explode('/', dirname($source).'/'.$target);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        return implode('/', $resolved);
    }

    /** @return list<string> */
    private function headingSlugsIn(string $page): array
    {
        $contents = file_get_contents($this->documentationRoot().'/'.$page);

        if ($contents === false) {
            return [];
        }

        $slugs = [];
        $inFence = false;

        foreach (preg_split('/\r?\n/', $contents) as $line) {
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

    private function slug(string $heading): string
    {
        $slug = mb_strtolower(trim($heading));
        $slug = preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $slug);

        return preg_replace('/\s/u', '-', trim($slug));
    }
}
