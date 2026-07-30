<?php

namespace App\Support;

/**
 * The WYSIWYG toolbar's button definitions — reference data for
 * resources/views/components/wysiwyg.blade.php, which used to build these
 * arrays in an 80-line inline @php block.
 *
 * Each entry is the shape x-wysiwyg.toolbar-button consumes:
 *
 *   command  Tiptap chain method passed to the Alpine cmd() helper
 *   args     optional argument for that command
 *   active   [nodeOrMarkName, ?args] passed to isOn() for the highlight
 *   label    button glyph (rendered as raw HTML — entities are intentional)
 *   title    tooltip and aria-label
 *
 * A few buttons (Link, Image, Callout) call bespoke no-arg helpers instead of
 * cmd(), so they stay hand-written in the template; they are not listed here.
 */
class WysiwygToolbar
{
    /**
     * Heading levels offered in the Headings dropdown. Also handed to Alpine
     * (see wysiwyg.js headingLabel()) so the trigger's label and the dropdown
     * cannot offer different levels.
     */
    public const HEADING_LEVELS = [1, 2, 3, 4];

    /**
     * @param  bool  $markdown  Whether the field stores CommonMark rather than
     *                          HTML. Gates the affordances that cannot survive a
     *                          Markdown round-trip.
     */
    public function __construct(private readonly bool $markdown) {}

    /**
     * Cluster 1 — H1..H4, collapsed into the Headings dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function headings(): array
    {
        return array_map(fn (int $level) => [
            'label' => "H{$level}",
            'command' => 'toggleHeading',
            'args' => ['level' => $level],
            'active' => ['heading', ['level' => $level]],
            'title' => __('Heading :level', ['level' => $level]),
        ], self::HEADING_LEVELS);
    }

    /**
     * Cluster 2 — Bold / Italic / Underline / Strike.
     *
     * Underline and Strike round-trip in both formats (Strike is standard GFM;
     * Underline serializes via the sanctioned `<u>` HTML-passthrough exception,
     * see resources/js/wysiwyg.js's MarkdownUnderline), so neither is gated.
     *
     * @return array<int, array<string, mixed>>
     */
    public function textFormat(): array
    {
        return [
            ['label' => 'B', 'command' => 'toggleBold', 'active' => ['bold'], 'title' => __('Bold')],
            ['label' => 'I', 'command' => 'toggleItalic', 'active' => ['italic'], 'title' => __('Italic')],
            ['label' => 'U', 'command' => 'toggleUnderline', 'active' => ['underline'], 'title' => __('Underline')],
            ['label' => 'S', 'command' => 'toggleStrike', 'active' => ['strike'], 'title' => __('Strikethrough')],
        ];
    }

    /**
     * Cluster 3 — bullet / ordered / task list, blockquote, inline code, code block.
     * Task lists round-trip in both formats, so they need no format gate either.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listsAndBlocks(): array
    {
        return [
            ['label' => '&bull;', 'command' => 'toggleBulletList', 'active' => ['bulletList'], 'title' => __('Bulleted list')],
            ['label' => '1.', 'command' => 'toggleOrderedList', 'active' => ['orderedList'], 'title' => __('Numbered list')],
            ['label' => '&#9744;', 'command' => 'toggleTaskList', 'active' => ['taskList'], 'title' => __('Task list')],
            ['label' => '&rdquo;', 'command' => 'toggleBlockquote', 'active' => ['blockquote'], 'title' => __('Blockquote')],
            ['label' => '&lt;/&gt;', 'command' => 'toggleCode', 'active' => ['code'], 'title' => __('Inline code')],
            ['label' => '{ }', 'command' => 'toggleCodeBlock', 'active' => ['codeBlock'], 'title' => __('Code block')],
        ];
    }

    /**
     * Cluster 5 — row/column ops, plus merge/split for HTML-mode fields only.
     *
     * Row/column ops keep the grid rectangular, so they are safe in both
     * formats. A merged cell (colspan) is lossless in HTML but loses its
     * structure in Markdown, so for a Markdown field the affordance is not
     * rendered at all — prevent, don't warn (architecture.md §2).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tableStructure(): array
    {
        $operations = [
            ['label' => '&#8213;+', 'command' => 'addRowAfter', 'title' => __('Add row below')],
            ['label' => '&#8213;&minus;', 'command' => 'deleteRow', 'title' => __('Delete row')],
            ['label' => '&#8214;+', 'command' => 'addColumnAfter', 'title' => __('Add column right')],
            ['label' => '&#8214;&minus;', 'command' => 'deleteColumn', 'title' => __('Delete column')],
        ];

        if (! $this->markdown) {
            $operations[] = ['label' => '&#8676;&#8677;', 'command' => 'mergeCells', 'title' => __('Merge cells')];
            $operations[] = ['label' => '&#8677;&#8676;', 'command' => 'splitCell', 'title' => __('Split cell')];
        }

        return $operations;
    }
}
