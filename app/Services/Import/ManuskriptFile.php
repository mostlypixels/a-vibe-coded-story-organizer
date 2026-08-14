<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Reads one Manuskript source file (`folder.txt`, a scene `.md`, a character
 * `.txt`) into its header map and body.
 *
 * Every such file shares one shape: a run of `Key: value` lines, a
 * continuation line for a multi-line value padded with leading whitespace,
 * then the first blank line ends the header and everything after it is body,
 * verbatim. This class knows only that shape — nothing about which keys mean
 * chapters, scenes, or characters. That domain knowledge lives in the callers
 * that read `header()`.
 *
 * Line endings are normalized CRLF/CR -> LF here, on read, so no caller can
 * forget it: console commands never pass through the HTTP
 * NormalizeLineEndings middleware, and an un-normalized scene body would diff
 * dirty against its very first edit.
 */
class ManuskriptFile
{
    /** @var array<string, string> Header values, keyed by lowercased trimmed key. */
    private readonly array $header;

    private readonly string $body;

    public function __construct(string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("Manuskript source file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not read Manuskript source file: {$path}");
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $contents);

        [$this->header, $this->body] = self::parse($normalized);
    }

    /**
     * @return array<string, string>
     */
    public function header(): array
    {
        return $this->header;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Look up a header value case-insensitively. Returns null when the key is
     * absent, distinct from a key present with an empty value.
     */
    public function headerValue(string $key): ?string
    {
        return $this->header[mb_strtolower(trim($key))] ?? null;
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private static function parse(string $contents): array
    {
        $lines = explode("\n", $contents);

        $header = [];
        $currentKey = null;
        $bodyStartLine = null;

        foreach ($lines as $index => $line) {
            if ($line === '') {
                $bodyStartLine = $index + 1;
                break;
            }

            if (preg_match('/^([^:]+):[ \t]*(.*)$/', $line, $matches) === 1) {
                $currentKey = mb_strtolower(trim($matches[1]));
                $header[$currentKey] = $matches[2];
            } elseif ($currentKey !== null) {
                // A continuation line: no colon, so it extends the previous
                // key's value rather than starting a new one.
                $header[$currentKey] .= "\n".trim($line);
            }
        }

        $body = $bodyStartLine === null
            ? ''
            : implode("\n", array_slice($lines, $bodyStartLine));

        return [$header, $body];
    }
}
