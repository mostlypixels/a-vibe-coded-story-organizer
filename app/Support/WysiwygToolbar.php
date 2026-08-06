<?php

namespace App\Support;

use Illuminate\Support\Js;

/**
 * The WYSIWYG toolbar's button definitions — reference data for
 * resources/views/components/wysiwyg.blade.php, which used to build these
 * arrays in an 80-line inline @php block.
 *
 * Each entry is the shape x-wysiwyg.toolbar-button consumes:
 *
 *   command  Tiptap chain method passed to the Alpine cmd() helper
 *   args     optional argument for that command
 *   action   ready-made JS call, for entries that need a bespoke wysiwyg.js
 *            helper (e.g. Callout's setCalloutType()) instead of a plain
 *            command — mutually exclusive with command/args
 *   active   [nodeOrMarkName, ?args] passed to isOn() for the highlight
 *   icon     Tabler component name without its prefix (e.g. `bold` for
 *             `<x-tabler-bold>`), for an entry with a real icon rather than a
 *             text glyph — the template renders it, this class never does
 *   label    button text: an HTML-entity glyph for a flat toolbar button
 *             with no `icon` (rendered raw via the `label` prop), or plain
 *             text alongside an `icon` for a dropdown item
 *   title    tooltip and aria-label
 *
 * Link and Image call bespoke no-arg helpers instead of cmd(), so they stay
 * hand-written in the template; they are not listed here.
 */
class WysiwygToolbar
{
    /**
     * Heading levels offered in the Style dropdown.
     */
    public const HEADING_LEVELS = [1, 2, 3, 4];

    /**
     * The five GitHub-flavoured alert/callout types, mirroring
     * resources/js/wysiwyg.js's own CALLOUT_TYPES (kept in sync by hand, the
     * same way HEADING_LEVELS crosses the PHP/JS boundary for headings).
     */
    public const CALLOUT_TYPES = ['note', 'tip', 'important', 'warning', 'caution'];

    /**
     * @param  bool  $markdown  Whether the field stores CommonMark rather than
     *                          HTML. Gates the affordances that cannot survive a
     *                          Markdown round-trip.
     */
    public function __construct(private readonly bool $markdown) {}

    /**
     * Block-level style: Paragraph, Blockquote, H1..H4, collapsed into the
     * Style dropdown. Blockquote sits with Paragraph/Headings rather than
     * with the other lists() entries because it's the same kind of choice —
     * what is this block, not a toggleable inline/list affordance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function styles(): array
    {
        $paragraph = [
            'icon' => 'pilcrow',
            'label' => __('Paragraph'),
            'command' => 'setParagraph',
            'active' => ['paragraph'],
            'title' => __('Paragraph'),
        ];

        $blockquote = [
            'icon' => 'blockquote',
            'label' => __('Blockquote'),
            'command' => 'toggleBlockquote',
            'active' => ['blockquote'],
            'title' => __('Blockquote'),
        ];

        $headings = array_map(fn (int $level) => [
            'icon' => "h-{$level}",
            'label' => __('Heading :level', ['level' => $level]),
            'command' => 'toggleHeading',
            'args' => ['level' => $level],
            'active' => ['heading', ['level' => $level]],
            'title' => __('Heading :level', ['level' => $level]),
        ], self::HEADING_LEVELS);

        return [$paragraph, $blockquote, ...$headings];
    }

    /**
     * Bold / Italic / Underline, the three flat text-format buttons. All
     * three are common enough to earn a permanent toolbar slot rather than
     * living in typography()'s dropdown. Underline has no clean CommonMark
     * equivalent, so it round-trips via the sanctioned `<u>` raw-HTML
     * passthrough (resources/js/wysiwyg.js's MarkdownUnderline) — unlike Bold
     * and Italic, which are plain CommonMark (`**`/`_`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function textFormat(): array
    {
        return [
            ['icon' => 'bold', 'command' => 'toggleBold', 'active' => ['bold'], 'title' => __('Bold')],
            ['icon' => 'italic', 'command' => 'toggleItalic', 'active' => ['italic'], 'title' => __('Italic')],
            ['icon' => 'underline', 'command' => 'toggleUnderline', 'active' => ['underline'], 'title' => __('Underline')],
        ];
    }

    /**
     * Strikethrough / Subscript / Superscript, the less-common text
     * decorations, collapsed into a dropdown under the typography icon.
     * Strikethrough is standard GFM (`~~text~~`) and round-trips
     * unconditionally; Subscript/Superscript have no CommonMark equivalent
     * and round-trip via the same sanctioned raw-HTML-passthrough treatment
     * as Underline (`<sub>`/`<sup>` — resources/js/wysiwyg.js's
     * MarkdownSubscript/MarkdownSuperscript). Grouped together because all
     * three are occasional-use text decorations, not because they share one
     * serialization story.
     *
     * @return array<int, array<string, mixed>>
     */
    public function typography(): array
    {
        return [
            ['icon' => 'strikethrough', 'label' => __('Strikethrough'), 'command' => 'toggleStrike', 'active' => ['strike'], 'title' => __('Strikethrough')],
            ['icon' => 'subscript', 'label' => __('Subscript'), 'command' => 'toggleSubscript', 'active' => ['subscript'], 'title' => __('Subscript')],
            ['icon' => 'superscript', 'label' => __('Superscript'), 'command' => 'toggleSuperscript', 'active' => ['superscript'], 'title' => __('Superscript')],
        ];
    }

    /**
     * Bulleted / Numbered / Task list, collapsed into a dropdown. Task lists
     * round-trip in both formats, so no format gate is needed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lists(): array
    {
        return [
            ['icon' => 'list', 'label' => __('Bulleted list'), 'command' => 'toggleBulletList', 'active' => ['bulletList'], 'title' => __('Bulleted list')],
            ['icon' => 'list-numbers', 'label' => __('Numbered list'), 'command' => 'toggleOrderedList', 'active' => ['orderedList'], 'title' => __('Numbered list')],
            ['icon' => 'list-check', 'label' => __('Task list'), 'command' => 'toggleTaskList', 'active' => ['taskList'], 'title' => __('Task list')],
        ];
    }

    /**
     * Inline code, Code block, collapsed into a dropdown. Blockquote lives in
     * styles() instead, lists in lists() — see their docblocks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function code(): array
    {
        return [
            ['icon' => 'code', 'label' => __('Inline code'), 'command' => 'toggleCode', 'active' => ['code'], 'title' => __('Inline code')],
            ['icon' => 'codeblock', 'label' => __('Code block'), 'command' => 'toggleCodeBlock', 'active' => ['codeBlock'], 'title' => __('Code block')],
        ];
    }

    /**
     * Everything table-related, one dropdown: insert, then row/column ops,
     * then merge/split for HTML-mode fields only. Previously split across two
     * toolbar entries (an "insert" button plus a separate "structure"
     * dropdown some distance away); merged so the concern lives in one place.
     *
     * Row/column ops keep the grid rectangular, so they are safe in both
     * formats. A merged cell (colspan) is lossless in HTML but loses its
     * structure in Markdown, so for a Markdown field the affordance is not
     * rendered at all — prevent, don't warn.
     *
     * @return array<int, array<string, mixed>>
     */
    public function table(): array
    {
        $operations = [
            ['icon' => 'table-plus', 'label' => __('Insert table'), 'command' => 'insertTable', 'args' => ['rows' => 3, 'cols' => 3, 'withHeaderRow' => true], 'title' => __('Insert table')],
            ['icon' => 'row-insert-bottom', 'label' => __('Add row below'), 'command' => 'addRowAfter', 'title' => __('Add row below')],
            ['icon' => 'row-remove', 'label' => __('Delete row'), 'command' => 'deleteRow', 'title' => __('Delete row')],
            ['icon' => 'column-insert-right', 'label' => __('Add column right'), 'command' => 'addColumnAfter', 'title' => __('Add column right')],
            ['icon' => 'column-remove', 'label' => __('Delete column'), 'command' => 'deleteColumn', 'title' => __('Delete column')],
        ];

        if (! $this->markdown) {
            // No Tabler icon exists for "merge/split a table cell" specifically;
            // the generic merge/split-arrows icons are the closest available match.
            $operations[] = ['icon' => 'arrow-merge', 'label' => __('Merge cells'), 'command' => 'mergeCells', 'title' => __('Merge cells')];
            $operations[] = ['icon' => 'arrows-split', 'label' => __('Split cell'), 'command' => 'splitCell', 'title' => __('Split cell')];
        }

        return $operations;
    }

    /**
     * The Callout toolbar entry, collapsed into a dropdown of the five
     * labeled types instead of one glyph button that cycled through them on
     * repeated clicks. `action` is a ready-made JS call (built the same way
     * toolbar-button.blade.php itself builds `cmd()` calls) because
     * setCalloutType() is a bespoke wysiwyg.js helper, not a plain Tiptap
     * chain command — see its docblock for why (insert vs. update-in-place).
     *
     * @return array<int, array<string, mixed>>
     */
    public function callouts(): array
    {
        // Mirrors GitHub's own icon choice per alert type.
        $icons = [
            'note' => 'info-circle',
            'tip' => 'bulb',
            'important' => 'alert-circle',
            'warning' => 'alert-triangle',
            'caution' => 'alert-octagon',
        ];

        $labels = [
            'note' => __('Note'),
            'tip' => __('Tip'),
            'important' => __('Important'),
            'warning' => __('Warning'),
            'caution' => __('Caution'),
        ];

        return array_map(fn (string $type) => [
            'action' => 'setCalloutType('.Js::from($type).')',
            'active' => ['callout', ['calloutType' => $type]],
            'icon' => $icons[$type],
            'label' => $labels[$type],
            'title' => $labels[$type],
        ], self::CALLOUT_TYPES);
    }
}
