<?php

namespace App\Support;

use Illuminate\Support\Js;

/** Defines the button data consumed by the WYSIWYG toolbar components. */
class WysiwygToolbar
{
    /** Heading levels in the Style menu. */
    public const HEADING_LEVELS = [1, 2, 3, 4];

    /** Keep this list in step with CALLOUT_TYPES in resources/js/wysiwyg.js. */
    public const CALLOUT_TYPES = ['note', 'tip', 'important', 'warning', 'caution'];

    /**
     * @param  bool  $markdown  Whether the field stores CommonMark rather than
     *                          HTML. Gates the affordances that cannot survive a
     *                          Markdown round-trip.
     */
    public function __construct(private readonly bool $markdown) {}

    /** @return array<int, array<string, mixed>> */
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

    /** @return array<int, array<string, mixed>> */
    public function textFormat(): array
    {
        return [
            ['icon' => 'bold', 'command' => 'toggleBold', 'active' => ['bold'], 'title' => __('Bold')],
            ['icon' => 'italic', 'command' => 'toggleItalic', 'active' => ['italic'], 'title' => __('Italic')],
            ['icon' => 'underline', 'command' => 'toggleUnderline', 'active' => ['underline'], 'title' => __('Underline')],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function typography(): array
    {
        return [
            ['icon' => 'strikethrough', 'label' => __('Strikethrough'), 'command' => 'toggleStrike', 'active' => ['strike'], 'title' => __('Strikethrough')],
            ['icon' => 'subscript', 'label' => __('Subscript'), 'command' => 'toggleSubscript', 'active' => ['subscript'], 'title' => __('Subscript')],
            ['icon' => 'superscript', 'label' => __('Superscript'), 'command' => 'toggleSuperscript', 'active' => ['superscript'], 'title' => __('Superscript')],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function lists(): array
    {
        return [
            ['icon' => 'list', 'label' => __('Bulleted list'), 'command' => 'toggleBulletList', 'active' => ['bulletList'], 'title' => __('Bulleted list')],
            ['icon' => 'list-numbers', 'label' => __('Numbered list'), 'command' => 'toggleOrderedList', 'active' => ['orderedList'], 'title' => __('Numbered list')],
            ['icon' => 'list-check', 'label' => __('Task list'), 'command' => 'toggleTaskList', 'active' => ['taskList'], 'title' => __('Task list')],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function code(): array
    {
        return [
            ['icon' => 'code', 'label' => __('Inline code'), 'command' => 'toggleCode', 'active' => ['code'], 'title' => __('Inline code')],
            ['icon' => 'codeblock', 'label' => __('Code block'), 'command' => 'toggleCodeBlock', 'active' => ['codeBlock'], 'title' => __('Code block')],
        ];
    }

    /**
     * Markdown fields omit cell merge and split actions because Markdown loses spans.
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
            $operations[] = ['icon' => 'arrow-merge', 'label' => __('Merge cells'), 'command' => 'mergeCells', 'title' => __('Merge cells')];
            $operations[] = ['icon' => 'arrows-split', 'label' => __('Split cell'), 'command' => 'splitCell', 'title' => __('Split cell')];
        }

        return $operations;
    }

    /** @return array<int, array<string, mixed>> */
    public function callouts(): array
    {
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

    /**
     * Block alignment. Markdown scene text stays structural, so this list is
     * empty for it; the empty array is what keeps `wysiwyg.blade.php` free of
     * a `@unless ($markdown)` conditional.
     *
     * `left` is a reset item, not a registry value: it clears the class rather
     * than writing one, so it stays out of `RichTextFields::ALIGNMENTS`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function alignment(): array
    {
        if ($this->markdown) {
            return [];
        }

        $icons = [
            'left' => 'align-left',
            'center' => 'align-center',
            'right' => 'align-right',
            'justify' => 'align-justified',
        ];

        $labels = [
            'left' => __('Align left'),
            'center' => __('Align center'),
            'right' => __('Align right'),
            'justify' => __('Justify'),
        ];

        return array_map(fn (string $align) => [
            'command' => 'setTextAlign',
            'args' => ['align' => $align],
            'active' => ['textAlign', ['align' => $align]],
            'icon' => $icons[$align],
            'label' => $labels[$align],
            'title' => $labels[$align],
        ], ['left', ...RichTextFields::ALIGNMENTS]);
    }

    /**
     * Named text colour. Empty for Markdown, like {@see alignment()}.
     *
     * @return array<int, array<string, mixed>>
     */
    public function textColor(): array
    {
        if ($this->markdown) {
            return [];
        }

        $labels = [
            'red' => __('Red'),
            'green' => __('Green'),
            'amber' => __('Amber'),
            'blue' => __('Blue'),
            'grey' => __('Grey'),
        ];

        $items = array_map(fn (string $color) => [
            'command' => 'setTextColor',
            'args' => ['color' => $color],
            'active' => ['textColor', ['color' => $color]],
            'icon' => 'circle-filled',
            // A swatch, not the token: the same class the mark writes into content.
            'iconClass' => RichTextFields::colorClass($color),
            'label' => $labels[$color],
            'title' => $labels[$color],
        ], RichTextFields::TEXT_COLORS);

        $items[] = [
            'command' => 'unsetTextColor',
            'icon' => 'palette-off',
            'label' => __('Remove colour'),
            'title' => __('Remove colour'),
        ];

        return $items;
    }

    /**
     * The Align dropdown trigger highlights for any non-default alignment.
     * Built here, not as an `isOn('a') || isOn('b')` chain in Blade, because
     * the chain would need one term per `RichTextFields::ALIGNMENTS` entry.
     */
    public function alignmentActiveExpression(): string
    {
        return implode(' || ', array_map(
            fn (string $align) => "isOn('textAlign', ".Js::from(['align' => $align]).')',
            RichTextFields::ALIGNMENTS,
        ));
    }

    /** The Colour dropdown trigger highlights whenever the mark is set, any name. */
    public function textColorActiveExpression(): string
    {
        return "isOn('textColor')";
    }
}
