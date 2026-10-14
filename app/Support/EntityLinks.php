<?php

namespace App\Support;

use App\Enums\LinkTargetType;
use Closure;

/**
 * Finds and rewrites `entity:<type>/<id>` links in rich HTML and in scene Markdown.
 *
 * One parser for both formats, because both store the link the same way: an `href`
 * in HTML, the link destination in Markdown. A link with an unknown type is not an
 * entity link: the sanitizer removes it, so this class ignores it.
 *
 * Pure: it never reads the database. Callers resolve the ids.
 */
class EntityLinks
{
    public const SCHEME = 'entity';

    // HTML branch, then Markdown branch. `(?<!!)` skips a Markdown image.
    private const PATTERN = '~
        <a\b[^>]*?\shref\s*=\s*(["\'])entity:(?<html_type>[a-z]+)/(?<html_id>\d+)\1[^>]*>(?<html_text>.*?)</a\s*>
        |
        (?<!!)\[(?<md_text>(?:[^\[\]\\\\]|\\\\.)*)\]\(entity:(?<md_type>[a-z]+)/(?<md_id>\d+)\)
    ~isx';

    /**
     * Parses the part after `entity:`, for example `scene/42`.
     *
     * @return array{type: LinkTargetType, id: int}|null
     */
    public static function parseTarget(string $target): ?array
    {
        if (preg_match('~\A([a-z]+)/(\d+)\z~', $target, $parts) !== 1) {
            return null;
        }

        $type = LinkTargetType::tryFrom($parts[1]);

        return $type === null ? null : ['type' => $type, 'id' => (int) $parts[2]];
    }

    public static function href(LinkTargetType $type, int $id): string
    {
        return self::SCHEME.':'.$type->value.'/'.$id;
    }

    /** @return list<array{type: LinkTargetType, id: int}> In text order, duplicates kept. */
    public static function find(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $links = [];

        foreach ($matches as $match) {
            $link = self::targetOf($match);

            if ($link !== null) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * $replace gets (type, id) and returns a new href, or null to unwrap the link to its text.
     *
     * @param  Closure(LinkTargetType, int): ?string  $replace
     */
    public static function rewrite(string $text, Closure $replace): string
    {
        return (string) preg_replace_callback(self::PATTERN, static function (array $match) use ($replace): string {
            $link = self::targetOf($match);

            if ($link === null) {
                return $match[0];
            }

            $href = $replace($link['type'], $link['id']);
            $isHtml = $match['html_type'] !== null;

            if ($href === null) {
                return $isHtml ? $match['html_text'] : $match['md_text'];
            }

            if ($isHtml) {
                $old = self::href($link['type'], $link['id']);
                $at = (int) strpos($match[0], $old);

                return substr_replace($match[0], htmlspecialchars($href, ENT_QUOTES), $at, strlen($old));
            }

            // Angle brackets let a Markdown destination hold a space or a parenthesis.
            $destination = preg_match('~[\s()]~', $href) === 1 ? "<{$href}>" : $href;

            return "[{$match['md_text']}]({$destination})";
        }, $text, -1, $count, PREG_UNMATCHED_AS_NULL);
    }

    /**
     * Replaces each HTML entity link, the whole `<a>` element. Use it on rendered HTML:
     * Markdown-shaped text there is literal text, for example in a code block.
     *
     * @param  Closure(LinkTargetType, int, string, string): string  $replace  gets (type, id, the anchor, its inner HTML)
     */
    public static function replaceAnchors(string $html, Closure $replace): string
    {
        return (string) preg_replace_callback(self::PATTERN, static function (array $match) use ($replace): string {
            $link = $match['html_type'] === null ? null : self::targetOf($match);

            return $link === null
                ? $match[0]
                : $replace($link['type'], $link['id'], $match[0], $match['html_text']);
        }, $html, -1, $count, PREG_UNMATCHED_AS_NULL);
    }

    /** Unwraps every entity link to its text. */
    public static function strip(string $text): string
    {
        return self::rewrite($text, static fn (): ?string => null);
    }

    /**
     * @param  array<int|string, string|null>  $match
     * @return array{type: LinkTargetType, id: int}|null
     */
    private static function targetOf(array $match): ?array
    {
        $type = $match['html_type'] ?? $match['md_type'];
        $id = $match['html_id'] ?? $match['md_id'];

        return self::parseTarget("{$type}/{$id}");
    }
}
