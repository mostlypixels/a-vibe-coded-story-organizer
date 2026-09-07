<?php

namespace App\Services;

use App\Support\SpecTree;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Moves a feature spec between `draft/` and `shelved/` (see .specs/README.md).
 *
 * Shelving is the one lifecycle move that goes backwards: a draft nobody will
 * work on for a while leaves `draft/` so the drafting queue reads as work that
 * is actually queued, and comes back unchanged when it is wanted again. Both
 * folders are flat, so the move is a rename plus a `status:` re-stamp — no
 * month bucket and no date stamp, because a shelf is a place, not a stage the
 * feature passed through.
 *
 * Only drafts shelve. An expanded or planned spec has design work hanging off
 * it, and parking that is a different problem than parking a paragraph.
 */
final class SpecShelf
{
    /** Marks the optional reason inside the spec body, so unshelving can find and remove it. */
    private const NOTE_MARKER = '**Shelved.**';

    /** Move a draft to the shelf. Returns the new spec path, relative to the tree root. */
    public static function shelve(string $name, ?string $reason = null): string
    {
        $spec = self::move($name, from: 'draft', to: 'shelved');

        $body = self::restamp(File::get($spec), 'shelved');

        if (filled($reason)) {
            $body = self::withNote($body, $reason);
        }

        File::put($spec, $body);

        return "shelved/$name/spec.md";
    }

    /** Move a shelved spec back to the drafting queue. Returns the new spec path. */
    public static function unshelve(string $name): string
    {
        $spec = self::move($name, from: 'shelved', to: 'draft');

        File::put($spec, self::withoutNote(self::restamp(File::get($spec), 'draft')));

        return "draft/$name/spec.md";
    }

    /**
     * Rename the feature folder, refusing anything that would leave the tree in a
     * state tests/Unit/SpecsStatusConsistencyTest fails on.
     *
     * @return string the moved spec.md path
     */
    private static function move(string $name, string $from, string $to): string
    {
        if (! SpecTree::isValidName($name)) {
            throw new RuntimeException("'$name' is not a valid spec name: use kebab-case (e.g. 'plotline-merge').");
        }

        $root = SpecTree::root();
        $source = "$root/$from/$name";

        if (! File::isDirectory($source)) {
            $found = SpecTree::locate($name);

            throw new RuntimeException($found === []
                ? "No feature named '$name' under .specs/$from/."
                : "Feature '$name' is at ".implode(', ', $found).", not under .specs/$from/. Only a $from spec can move here.");
        }

        if (! File::exists("$source/spec.md")) {
            throw new RuntimeException(".specs/$from/$name/ has no spec.md.");
        }

        $destination = "$root/$to/$name";

        if (File::exists($destination)) {
            throw new RuntimeException("A feature named '$name' already sits at .specs/$to/$name/.");
        }

        File::ensureDirectoryExists("$root/$to");

        if (! File::moveDirectory($source, $destination)) {
            throw new RuntimeException("Could not move .specs/$from/$name/ to .specs/$to/$name/.");
        }

        return "$destination/spec.md";
    }

    /**
     * Rewrite the `status:` line of the leading frontmatter block. A spec whose
     * frontmatter is missing or malformed gets a fresh block, because the folder
     * it now sits in is the truth and the test reads the stamp, not the prose.
     */
    private static function restamp(string $contents, string $status): string
    {
        if (! preg_match('/\A---\r?\n(.*?)\r?\n---/s', $contents, $block)) {
            return "---\nstatus: $status\n---\n\n".ltrim($contents);
        }

        $rewritten = preg_match('/^status:/m', $block[1])
            ? preg_replace('/^status:.*$/m', "status: $status", $block[1], 1)
            : "status: $status\n".$block[1];

        return substr_replace($contents, $rewritten, 4, strlen($block[1]));
    }

    /** Put the reason under the title, in the callout style the low-priority drafts already use. */
    private static function withNote(string $contents, string $reason): string
    {
        $note = "> [!NOTE]\n> ".self::NOTE_MARKER.' '.trim($reason)."\n";

        // After the title when there is one, so the note reads as being about the
        // feature rather than about the file.
        if (preg_match('/^# .*$/m', $contents, $title, PREG_OFFSET_CAPTURE)) {
            $end = $title[0][1] + strlen($title[0][0]);
            $rest = ltrim(substr($contents, $end), "\r\n");

            return substr($contents, 0, $end)."\n\n".$note."\n".$rest;
        }

        return $note."\n".$contents;
    }

    /** Remove a note this class wrote. A note the author edited by hand stays. */
    private static function withoutNote(string $contents): string
    {
        $marker = preg_quote(self::NOTE_MARKER, '/');

        return preg_replace('/^> \[!NOTE\]\r?\n> '.$marker.'[^\r\n]*\r?\n(> [^\r\n]*\r?\n)*\r?\n?/m', '', $contents, 1);
    }
}
