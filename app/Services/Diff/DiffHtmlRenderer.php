<?php

namespace App\Services\Diff;

use App\Enums\DiffChange;
use App\Support\DiffBlock;
use App\Support\DiffSpan;
use App\Support\HtmlBlock;
use App\Support\InlineToken;
use App\Support\RichTextFields;

/**
 * Turns {@see VisualHtmlDiffer}'s structure into the HTML a diff is displayed
 * as — and is the *only* thing in the app that produces `<ins>`/`<del>`.
 *
 * ## Why this class is the safety boundary
 *
 * The order is: already-purified content in → diff → wrap the changes → render.
 *
 * > [!WARNING]
 * > **Never** run the output through the sanitizer. The author allow-list
 * > ({@see RichTextFields::ALLOWED_TAGS}) has no `ins`/`del` in it — by design —
 * > so purifying afterwards would eat precisely the markers this class exists to
 * > add. Safety instead comes from *production*: this renderer builds every tag
 * > itself from {@see self::EMITTED_TAGS}, escapes every text node with `e()`,
 * > and re-emits attributes from parsed values rather than copying strings.
 * > Nothing from the stored value is ever concatenated raw, so a stored value
 * > containing a literal `<del>` renders as visible text, not as a marker.
 *
 * That is the same contract the `jfcherng/php-diff` output already has, for the
 * same reason: the producer escapes, so the result is safe to `{!! !!}`.
 *
 * ## `<s>` is not `<del>`
 *
 * The editor's strikethrough stays `<s>` ("no longer accurate"); `<del>` means
 * "removed between these two revisions" and belongs to this layer alone. They
 * are different statements, not synonyms — see RichTextFieldsDiffTagsTest,
 * which guards the allow-list that keeps them apart.
 */
class DiffHtmlRenderer
{
    /**
     * Every tag this class can emit. Nothing else can appear in its output:
     * blocks are rebuilt from the parsed structure, so a tag that somehow
     * reached the tokenizer from content is dropped rather than passed through.
     *
     * `ul`/`ol`/`table`/`tbody`/`td` are here as structural wrappers even though
     * the tokenizer has no block for them: a bare `<li>` or `<tr>` is invalid
     * HTML that browsers silently discard, taking the diff with it.
     *
     * @var list<string>
     */
    public const EMITTED_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'pre', 'hr', 'img',
        'ul', 'ol', 'li', 'table', 'tbody', 'tr', 'td',
        'strong', 'em', 'u', 's', 'code', 'a',
        'ins', 'del', 'span',
    ];

    /** The class hook the `x-diff` component styles insertions with. */
    private const INSERT_CLASS = 'diff-ins';

    /** …and removals. */
    private const REMOVE_CLASS = 'diff-del';

    /**
     * Render a diff.
     *
     * @param  list<DiffBlock>  $diffBlocks
     * @param  bool  $inline  Summary mode: one line of text plus change markers, with the
     *                        author's own formatting dropped. A history row is a scan
     *                        target, not a reading surface — bold and links there are
     *                        noise competing with the markers that matter.
     */
    public function render(array $diffBlocks, bool $inline = false): string
    {
        if ($inline) {
            return $this->renderInline($diffBlocks);
        }

        $html = '';
        $index = 0;
        $count = count($diffBlocks);

        while ($index < $count) {
            $tag = $diffBlocks[$index]->block->tag;

            // List items and table rows only mean anything inside their
            // wrapper, so consecutive ones are gathered into a single list or
            // table instead of being emitted one orphan at a time.
            if ($tag === 'li' || $tag === 'tr') {
                $run = [];

                while ($index < $count && $diffBlocks[$index]->block->tag === $tag) {
                    $run[] = $diffBlocks[$index];
                    $index++;
                }

                $html .= $tag === 'li' ? $this->renderList($run) : $this->renderTable($run);

                continue;
            }

            $html .= $this->renderBlock($diffBlocks[$index]);
            $index++;
        }

        return $html;
    }

    /**
     * Summary mode: every block's words in reading order, separated by spaces,
     * with only `<ins>`/`<del>` surviving.
     *
     * @param  list<DiffBlock>  $diffBlocks
     */
    private function renderInline(array $diffBlocks): string
    {
        $parts = [];

        foreach ($diffBlocks as $diffBlock) {
            $rendered = $this->renderContent($diffBlock, inline: true);

            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param  list<DiffBlock>  $run
     */
    private function renderList(array $run): string
    {
        // The whole run takes its wrapper from the first item: a run of list
        // items always comes from one list, since the tokenizer emits them in
        // document order.
        $listType = $run[0]->block->attributes['list-type'] ?? 'ul';
        $wrapper = $listType === 'ol' ? 'ol' : 'ul';

        $items = '';

        foreach ($run as $diffBlock) {
            $items .= $this->renderBlock($diffBlock);
        }

        return "<{$wrapper}>{$items}</{$wrapper}>";
    }

    /**
     * @param  list<DiffBlock>  $run
     */
    private function renderTable(array $run): string
    {
        $rows = '';

        foreach ($run as $diffBlock) {
            $rows .= $this->renderBlock($diffBlock);
        }

        return "<table><tbody>{$rows}</tbody></table>";
    }

    /**
     * One block element, with the attributes the change status calls for.
     */
    private function renderBlock(DiffBlock $diffBlock): string
    {
        $block = $diffBlock->block;

        if ($block->tag === 'hr') {
            return '<hr'.$this->attributes($this->blockAttributes($diffBlock)).'>';
        }

        if ($block->tag === 'img') {
            return $this->wrapWholeBlock($this->renderImage($block), $diffBlock->change);
        }

        $tag = in_array($block->tag, self::EMITTED_TAGS, true) ? $block->tag : 'p';
        $content = $this->renderContent($diffBlock, inline: false);

        return "<{$tag}".$this->attributes($this->blockAttributes($diffBlock)).">{$content}</{$tag}>";
    }

    /**
     * The inner HTML of a block: either its word runs (a replacement) or its
     * whole content, marked as inserted/removed when the block itself is.
     */
    private function renderContent(DiffBlock $diffBlock, bool $inline): string
    {
        if ($diffBlock->change === DiffChange::Replaced) {
            return $this->renderSpans($diffBlock->spans, $inline);
        }

        $tokens = $diffBlock->block->tokens;

        if ($diffBlock->block->tag === 'tr') {
            return $this->renderCells($tokens, $diffBlock->change, $inline);
        }

        return $this->wrapWholeBlock($this->renderTokens($tokens, $inline), $diffBlock->change);
    }

    /**
     * @param  list<DiffSpan>  $spans
     */
    private function renderSpans(array $spans, bool $inline): string
    {
        $parts = [];

        foreach ($spans as $span) {
            $rendered = $this->renderTokens($span->tokens, $inline);

            if ($rendered === '') {
                continue;
            }

            $parts[] = match ($span->change) {
                DiffChange::Inserted => $this->marker('ins', self::INSERT_CLASS, __('inserted'), $rendered),
                DiffChange::Removed => $this->marker('del', self::REMOVE_CLASS, __('removed'), $rendered),
                default => $rendered,
            };
        }

        return implode(' ', $parts);
    }

    /**
     * A table row's cells, split back apart on the boundary tokens the
     * tokenizer left in the stream.
     *
     * @param  list<InlineToken>  $tokens
     */
    private function renderCells(array $tokens, DiffChange $change, bool $inline): string
    {
        $cells = [[]];

        foreach ($tokens as $token) {
            if ($token->isCellBoundary()) {
                $cells[] = [];

                continue;
            }

            $cells[count($cells) - 1][] = $token;
        }

        $html = '';

        foreach ($cells as $cell) {
            $html .= '<td>'.$this->wrapWholeBlock($this->renderTokens($cell, $inline), $change).'</td>';
        }

        return $html;
    }

    /**
     * Wrap a whole block's content in a change marker, when the block as a
     * whole was inserted or removed.
     */
    private function wrapWholeBlock(string $content, DiffChange $change): string
    {
        if ($content === '') {
            return '';
        }

        return match ($change) {
            DiffChange::Inserted => $this->marker('ins', self::INSERT_CLASS, __('inserted'), $content),
            DiffChange::Removed => $this->marker('del', self::REMOVE_CLASS, __('removed'), $content),
            default => $content,
        };
    }

    /**
     * A change marker, carrying its own visually-hidden label so the change is
     * announced rather than conveyed by colour alone.
     */
    private function marker(string $tag, string $class, string $label, string $content): string
    {
        return "<{$tag} class=\"{$class}\"><span class=\"sr-only\">".e($label).' </span>'.$content."</{$tag}>";
    }

    /**
     * Words, grouped into runs that share a mark stack so each run is wrapped
     * once. In summary mode the marks are dropped and only the text survives.
     *
     * @param  list<InlineToken>  $tokens
     */
    private function renderTokens(array $tokens, bool $inline): string
    {
        $parts = [];
        $index = 0;
        $count = count($tokens);

        while ($index < $count) {
            $marks = $tokens[$index]->marks;
            $words = [];

            while ($index < $count && $tokens[$index]->marks === $marks) {
                if (! $tokens[$index]->isCellBoundary()) {
                    $words[] = $tokens[$index]->word;
                }

                $index++;
            }

            if ($words === []) {
                continue;
            }

            // The single most important line in this class: content text is
            // escaped here and nowhere else, so no stored value can contribute
            // markup to the output.
            $text = e(implode(' ', $words));

            $parts[] = $inline ? $text : $this->wrapInMarks($text, $marks);
        }

        return implode(' ', $parts);
    }

    /**
     * Re-apply the author's inline formatting around an already-escaped run.
     *
     * @param  list<string>  $marks  Outermost first, so they are closed in reverse.
     */
    private function wrapInMarks(string $text, array $marks): string
    {
        foreach (array_reverse($marks) as $mark) {
            if (str_starts_with($mark, 'a:')) {
                $text = $this->renderLink(substr($mark, 2), $text);

                continue;
            }

            if (in_array($mark, self::EMITTED_TAGS, true)) {
                $text = "<{$mark}>{$text}</{$mark}>";
            }
        }

        return $text;
    }

    /**
     * A link, with its target re-checked against the app's allowed schemes.
     *
     * The href is validated here rather than trusted from the stored value:
     * this renderer must hold on its own even for content that reached the
     * database by some other route than the sanitizer (an import, say).
     * A rejected target still renders its text — losing the words would be a
     * worse outcome than losing the link.
     */
    private function renderLink(string $href, string $text): string
    {
        $scheme = parse_url($href, PHP_URL_SCHEME);

        if ($scheme !== null && ! in_array(strtolower((string) $scheme), RichTextFields::ALLOWED_SCHEMES, true)) {
            return "<a>{$text}</a>";
        }

        return '<a href="'.e($href).'">'.$text.'</a>';
    }

    private function renderImage(HtmlBlock $block): string
    {
        $source = (string) ($block->attributes['src'] ?? '');
        $scheme = parse_url($source, PHP_URL_SCHEME);

        if ($scheme !== null && ! in_array(strtolower((string) $scheme), RichTextFields::ALLOWED_SCHEMES, true)) {
            $source = '';
        }

        return '<img'.$this->attributes([
            'src' => $source,
            'alt' => (string) ($block->attributes['alt'] ?? ''),
        ]).'>';
    }

    /**
     * The attributes a block element carries: the change class, whatever the
     * block itself declared, and (for a formatting-only change) which marks
     * moved, so the view can name them in its badge.
     *
     * @return array<string, string>
     */
    private function blockAttributes(DiffBlock $diffBlock): array
    {
        $attributes = [];
        $block = $diffBlock->block;

        if ($diffBlock->change->isChange()) {
            $attributes['class'] = 'diff-'.$diffBlock->change->value;
        }

        foreach (['data-callout-type', 'data-checked'] as $passthrough) {
            if (isset($block->attributes[$passthrough])) {
                $attributes[$passthrough] = (string) $block->attributes[$passthrough];
            }
        }

        if ($block->tag === 'li') {
            $attributes['data-depth'] = (string) ($block->attributes['depth'] ?? 1);
        }

        if ($diffBlock->change === DiffChange::FormattingChanged) {
            $attributes['data-marks-added'] = implode(',', $diffBlock->marksAdded);
            $attributes['data-marks-removed'] = implode(',', $diffBlock->marksRemoved);
        }

        return $attributes;
    }

    /**
     * Serialise an attribute map, escaping every value. Empty values are
     * dropped rather than emitted blank.
     *
     * @param  array<string, string>  $attributes
     */
    private function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === '') {
                continue;
            }

            $html .= ' '.$name.'="'.e($value).'"';
        }

        return $html;
    }
}
