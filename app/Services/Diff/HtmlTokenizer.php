<?php

namespace App\Services\Diff;

use App\Support\HtmlBlock;
use App\Support\InlineToken;
use App\Support\RichTextFields;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts stored rich HTML into the {@see HtmlBlock} values used by the differ.
 *
 * This class is not a security boundary and emits no HTML. The renderer escapes
 * all output. Unknown tags are ignored.
 *
 * > [!WARNING]
 * > Use this tokenizer only for rich fields. Markdown must remain source text.
 */
class HtmlTokenizer
{
    /**
     * Elements that become one block each. `li` yields one block per item (with
     * its list type and depth), `tr` one per row (cells joined).
     *
     * @var list<string>
     */
    public const BLOCK_TAGS = ['p', 'h1', 'h2', 'h3', 'h4', 'li', 'blockquote', 'pre', 'tr', 'hr', 'img'];

    /**
     * Inline elements recorded as marks on the words they wrap. A link is
     * recorded as `a:<href>` so re-pointing it counts as a change, and a
     * coloured span as `color:<name>` for the same reason.
     *
     * @var list<string>
     */
    public const MARK_TAGS = ['strong', 'em', 'u', 's', 'sub', 'sup', 'code', 'a', 'span'];

    /** Prefix of the mark a coloured span contributes. */
    public const COLOR_MARK_PREFIX = 'color:';

    /** Prefix of the mark a link contributes. The href follows it. */
    public const LINK_MARK_PREFIX = 'a:';

    /** Key of the alignment in a block's attribute map. */
    public const ALIGN_ATTRIBUTE = 'align';

    /** Prefix of the pseudo-mark a block's alignment contributes to a diff. */
    public const ALIGN_MARK_PREFIX = 'align:';

    /**
     * The pseudo-mark a ticked task item contributes to a diff. Unticking is
     * the mark's removal, so an unticked item contributes nothing — the same
     * shape as an unaligned paragraph.
     */
    public const CHECKED_MARK = 'checked';

    /** @var list<string> */
    private const LIST_TAGS = ['ul', 'ol'];

    /** @var list<string> */
    private const TABLE_TAGS = ['table', 'thead', 'tbody'];

    /** @var list<string> */
    private const CELL_TAGS = ['td', 'th'];

    /**
     * Blocks that carry meaning without carrying text, and so survive the
     * empty-block filter.
     *
     * @var list<string>
     */
    private const VOID_BLOCK_TAGS = ['hr', 'img'];

    /**
     * Parse `$html` into blocks. A null, empty or text-free value yields `[]`,
     * which every caller reads as "nothing to diff on this side".
     *
     * @return list<HtmlBlock>
     */
    public function tokenize(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $body = $this->parseBody($html);

        if ($body === null) {
            return [];
        }

        $blocks = [];
        $this->walkContainer($body, $blocks, listType: null, depth: 0);

        return $blocks;
    }

    /** Parses a body and repairs malformed legacy HTML instead of failing history. */
    private function parseBody(string $html): ?DOMNode
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument;
        // loadHTML assumes ISO-8859-1 unless the processing instruction sets UTF-8.
        $document->loadHTML('<?xml encoding="UTF-8"?>'.$html, LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        return $document->getElementsByTagName('body')->item(0);
    }

    /**
     * Walk one container element, appending the blocks it contains.
     *
     * Three kinds of child are possible: a block element (emit it), a list or
     * table wrapper (recurse — the wrapper itself is not a block, its items and
     * rows are), or anything else. That last group — bare text, an inline
     * element, an unknown tag — is buffered and flushed as an implicit
     * paragraph, so a value that predates the editor (or a fragment the
     * sanitizer left unwrapped) still yields its text instead of vanishing.
     *
     * @param  list<HtmlBlock>  $blocks
     * @param  string|null  $listType  The `ul`/`ol`/task-list wrapper we are inside, if any.
     * @param  int  $depth  List nesting depth; 0 outside any list.
     */
    private function walkContainer(DOMNode $node, array &$blocks, ?string $listType, int $depth): void
    {
        /** @var list<DOMNode> $pending */
        $pending = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::BLOCK_TAGS, true)) {
                    $this->flushPending($pending, $blocks);
                    $this->emitBlock($child, $tag, $blocks, $listType, $depth);

                    continue;
                }

                if (in_array($tag, self::LIST_TAGS, true)) {
                    $this->flushPending($pending, $blocks);
                    $this->walkContainer($child, $blocks, $this->listTypeOf($child), $depth + 1);

                    continue;
                }

                if (in_array($tag, self::TABLE_TAGS, true)) {
                    $this->flushPending($pending, $blocks);
                    $this->walkContainer($child, $blocks, $listType, $depth);

                    continue;
                }
            }

            $pending[] = $child;
        }

        $this->flushPending($pending, $blocks);
    }

    /**
     * Turn buffered loose nodes into one implicit paragraph, then clear the
     * buffer.
     *
     * @param  list<DOMNode>  $pending
     * @param  list<HtmlBlock>  $blocks
     */
    private function flushPending(array &$pending, array &$blocks): void
    {
        if ($pending === []) {
            return;
        }

        $tokens = [];

        foreach ($pending as $node) {
            $tokens = array_merge($tokens, $this->tokensFrom($node, marks: [], skipLists: false));
        }

        $pending = [];

        if ($tokens !== []) {
            $blocks[] = $this->buildBlock('p', [], $tokens, $this->textOf($tokens));
        }
    }

    /**
     * Emit the block for one block-level element — and, for a list item, walk
     * the lists nested inside it so that a sub-list becomes its own deeper
     * blocks rather than being folded into its parent's text.
     *
     * @param  list<HtmlBlock>  $blocks
     */
    private function emitBlock(DOMElement $element, string $tag, array &$blocks, ?string $listType, int $depth): void
    {
        $block = match ($tag) {
            'tr' => $this->rowBlock($element),
            'img' => $this->imageBlock($element),
            'hr' => $this->buildBlock('hr', [], [], ''),
            'li' => $this->listItemBlock($element, $listType, $depth),
            'blockquote' => $this->quoteBlock($element),
            default => $this->simpleBlock($element, $tag),
        };

        // An empty paragraph carries nothing a reader could see change, and
        // emitting it would make an invisible edit look like a real one. `hr`
        // and `img` are exempt: they *are* the content.
        if ($block->tokens !== [] || in_array($tag, self::VOID_BLOCK_TAGS, true)) {
            $blocks[] = $block;
        }

        if ($tag === 'li') {
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), self::LIST_TAGS, true)) {
                    $this->walkContainer($child, $blocks, $this->listTypeOf($child), $depth + 1);
                }
            }
        }

        if ($tag !== 'img') {
            $this->emitNestedImages($element, $blocks);
        }
    }

    /**
     * Emit a block for each image *inside* another block.
     *
     * The editor wraps an image in a paragraph, so `img` is almost never a
     * top-level element — yet an image is a block a reader sees, not a word.
     * It therefore surfaces as its own block, after the text of the block it
     * sat in.
     *
     * @param  list<HtmlBlock>  $blocks
     */
    private function emitNestedImages(DOMElement $element, array &$blocks): void
    {
        foreach ($element->getElementsByTagName('img') as $image) {
            $blocks[] = $this->imageBlock($image);
        }
    }

    /**
     * A paragraph or heading keeps its alignment: it is a visible change that
     * leaves every word alone, so it has to travel as an attribute rather than
     * be found in the text. It stays out of {@see HtmlBlock::matchKey()}, or a
     * re-aligned paragraph would read as a delete plus an insert.
     */
    private function simpleBlock(DOMElement $element, string $tag): HtmlBlock
    {
        $tokens = $this->tokensFrom($element, marks: [], skipLists: false);
        $attributes = [];
        $align = in_array($tag, RichTextFields::ALIGNABLE_TAGS, true)
            ? $this->decorativeValue($element, RichTextFields::ALIGN_CLASS_PREFIX, RichTextFields::ALIGNMENTS)
            : null;

        if ($align !== null) {
            $attributes[self::ALIGN_ATTRIBUTE] = $align;
        }

        return $this->buildBlock($tag, $attributes, $tokens, $this->textOf($tokens));
    }

    /**
     * A quote keeps its callout type (`> [!NOTE]` and friends present over
     * `blockquote` via this attribute), because turning a note into a warning
     * is a change a reader sees.
     */
    private function quoteBlock(DOMElement $element): HtmlBlock
    {
        $tokens = $this->tokensFrom($element, marks: [], skipLists: false);
        $attributes = [];

        if ($element->hasAttribute('data-callout-type')) {
            $attributes['data-callout-type'] = $element->getAttribute('data-callout-type');
        }

        return $this->buildBlock('blockquote', $attributes, $tokens, $this->textOf($tokens));
    }

    /**
     * A list item carries where it sits — which kind of list, how deep, and
     * (for a task item) whether it is ticked. All three are visible changes on
     * their own, so they belong in the block's identity, not just its text.
     */
    private function listItemBlock(DOMElement $element, ?string $listType, int $depth): HtmlBlock
    {
        // Nested lists are skipped here: emitBlock() walks them separately into
        // their own blocks, so folding their text in too would duplicate it.
        $tokens = $this->tokensFrom($element, marks: [], skipLists: true);

        $attributes = [
            'list-type' => $listType ?? 'ul',
            'depth' => max(1, $depth),
        ];

        if ($element->hasAttribute('data-checked')) {
            $attributes['data-checked'] = $element->getAttribute('data-checked');
        }

        return $this->buildBlock('li', $attributes, $tokens, $this->textOf($tokens));
    }

    /**
     * A table row becomes one block whose cells are separated by
     * {@see HtmlBlock::CELL_BOUNDARY} in the token stream (so the renderer can
     * rebuild the row) and by {@see HtmlBlock::CELL_SEPARATOR} in the text (so
     * the text stays readable).
     */
    private function rowBlock(DOMElement $row): HtmlBlock
    {
        $tokens = [];
        $cellTexts = [];
        $index = 0;

        foreach ($row->childNodes as $cell) {
            if (! $cell instanceof DOMElement || ! in_array(strtolower($cell->nodeName), self::CELL_TAGS, true)) {
                continue;
            }

            if ($index > 0) {
                $tokens[] = new InlineToken(HtmlBlock::CELL_BOUNDARY);
            }

            $cellTokens = $this->tokensFrom($cell, marks: [], skipLists: false);
            $tokens = array_merge($tokens, $cellTokens);
            $cellTexts[] = $this->textOf($cellTokens);
            $index++;
        }

        return $this->buildBlock('tr', [], $tokens, implode(HtmlBlock::CELL_SEPARATOR, $cellTexts));
    }

    /**
     * An image is matched by its source (see {@see HtmlBlock::matchKey()}), not
     * by its alt text — swapping the picture behind identical alt text is still
     * a change. It contributes no word tokens: there is nothing to diff inside
     * an image.
     */
    private function imageBlock(DOMElement $element): HtmlBlock
    {
        $attributes = ['src' => $element->getAttribute('src')];

        if ($element->hasAttribute('alt')) {
            $attributes['alt'] = $element->getAttribute('alt');
        }

        return $this->buildBlock('img', $attributes, [], $this->normalize($element->getAttribute('alt')));
    }

    /**
     * @param  array<string, string|int>  $attributes
     * @param  list<InlineToken>  $tokens
     */
    private function buildBlock(string $tag, array $attributes, array $tokens, string $text): HtmlBlock
    {
        return new HtmlBlock($tag, $attributes, $text, $tokens, $this->signatureOf($tokens, $attributes));
    }

    /**
     * Collect the word tokens under `$node`, carrying the mark stack down.
     *
     * Done in two passes, and the reason is the whole point of this method. A
     * mark inside a word — `mc<sup>2</sup>`, or a bolded stem — puts the word's
     * halves in two different text nodes. Tokenising each node on its own made
     * them two words, so `E = mc2` and `E = mc<sup>2</sup>` had different
     * {@see HtmlBlock::$text}, different {@see HtmlBlock::matchKey()}, and the
     * differ reported a delete plus an insert instead of a formatting change.
     *
     * So the first pass flattens the tree to marked *segments* of raw text, and
     * the second splits that stream on whitespace alone. A word that spans marks
     * carries the union of them: half-bold reads as bold, which is the right
     * altitude for a change summary.
     *
     * @param  list<string>  $marks
     * @return list<InlineToken>
     */
    private function tokensFrom(DOMNode $node, array $marks, bool $skipLists): array
    {
        return $this->assembleWords($this->segmentsFrom($node, $marks, $skipLists));
    }

    /**
     * Flatten `$node` to `{text, marks}` segments in document order, text kept
     * exactly as written so the second pass can tell a word boundary from a
     * mere tag boundary.
     *
     * `input` produces nothing — the task-list checkbox is rendered from the
     * item's `data-checked` attribute instead. `br` produces a space, because it
     * *is* a word boundary and dropping it would glue two words together.
     * Unknown elements are transparent: we keep their text and drop the tag.
     *
     * @param  list<string>  $marks
     * @return list<array{text: string, marks: list<string>}>
     */
    private function segmentsFrom(DOMNode $node, array $marks, bool $skipLists): array
    {
        if ($node instanceof DOMText) {
            return [['text' => $node->textContent, 'marks' => $marks]];
        }

        $segments = [];

        foreach ($node->childNodes ?? [] as $child) {
            if ($child instanceof DOMText) {
                $segments[] = ['text' => $child->textContent, 'marks' => $marks];

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if ($tag === 'input') {
                continue;
            }

            if ($tag === 'br') {
                $segments[] = ['text' => ' ', 'marks' => []];

                continue;
            }

            if ($skipLists && in_array($tag, self::LIST_TAGS, true)) {
                continue;
            }

            $childMarks = $marks;
            $mark = $this->markFor($child, $tag);

            if ($mark !== null) {
                $childMarks[] = $mark;
            }

            $segments = array_merge($segments, $this->segmentsFrom($child, $childMarks, $skipLists));
        }

        return $segments;
    }

    /**
     * Split a segment stream into words, breaking on whitespace and never on a
     * segment boundary.
     *
     * @param  list<array{text: string, marks: list<string>}>  $segments
     * @return list<InlineToken>
     */
    private function assembleWords(array $segments): array
    {
        $tokens = [];
        $word = '';
        $marks = [];

        $flush = function () use (&$tokens, &$word, &$marks): void {
            if ($word === '') {
                return;
            }

            $tokens[] = new InlineToken($word, array_values(array_unique($marks)));
            $word = '';
            $marks = [];
        };

        foreach ($segments as $segment) {
            // DELIM_CAPTURE keeps the separators, so a whitespace run inside a
            // segment still ends the word it follows.
            $pieces = preg_split('/([\s\x{00A0}]+)/u', $segment['text'], -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

            foreach ($pieces as $piece) {
                if ($piece === '') {
                    continue;
                }

                if (preg_match('/^[\s\x{00A0}]+$/u', $piece) === 1) {
                    $flush();

                    continue;
                }

                $word .= $piece;

                foreach ($segment['marks'] as $mark) {
                    $marks[] = $mark;
                }
            }
        }

        $flush();

        return $tokens;
    }

    /**
     * The mark an inline element contributes, or null when it contributes none.
     *
     * A link and a coloured span both carry a value, so the mark carries it too:
     * re-pointing a link and recolouring a word are changes a reader sees. An
     * uncoloured span is transparent, like any other unknown element.
     */
    private function markFor(DOMElement $element, string $tag): ?string
    {
        if (! in_array($tag, self::MARK_TAGS, true)) {
            return null;
        }

        if ($tag === 'a') {
            return self::LINK_MARK_PREFIX.trim($element->getAttribute('href'));
        }

        if ($tag === 'span') {
            $color = $this->decorativeValue($element, RichTextFields::COLOR_CLASS_PREFIX, RichTextFields::TEXT_COLORS);

            return $color === null ? null : self::COLOR_MARK_PREFIX.$color;
        }

        return $tag;
    }

    /**
     * The value of a decorative class on `$element` — `center` from
     * `rt-align-center` — or null when it carries none the registry knows.
     *
     * @param  list<string>  $allowed
     */
    private function decorativeValue(DOMElement $element, string $prefix, array $allowed): ?string
    {
        foreach ($this->words($element->getAttribute('class')) as $class) {
            $name = str_starts_with($class, $prefix) ? substr($class, strlen($prefix)) : null;

            if ($name !== null && in_array($name, $allowed, true)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * A fingerprint of how the block says what it says.
     *
     * The marks are recorded as transitions (position + new mark stack) rather
     * than one entry per word, so it stays short on long paragraphs while still
     * differing whenever the *same* words are formatted differently — which is
     * exactly the "formatting changed" signal the differ looks for.
     *
     * The alignment leads the string because it is the same kind of fact: a
     * centred paragraph says its words differently from a flush-left one, and
     * an alignment-only edit must reach the reader as a formatting change
     * rather than as no change at all. It stays out of
     * {@see HtmlBlock::matchKey()}, which is what keeps the re-aligned
     * paragraph paired with its old self.
     *
     * @param  list<InlineToken>  $tokens
     * @param  array<string, string|int>  $attributes
     */
    private function signatureOf(array $tokens, array $attributes = []): string
    {
        $align = $attributes[self::ALIGN_ATTRIBUTE] ?? null;
        $prefix = $align === null ? '' : self::ALIGN_MARK_PREFIX.$align.'|';

        // A ticked box is formatting the words don't carry, exactly like an
        // alignment: without it here the differ's signature comparison short-
        // circuits to "unchanged" and ticking an item reports nothing.
        if (($attributes['data-checked'] ?? null) === 'true') {
            $prefix .= self::CHECKED_MARK.'|';
        }
        $transitions = [];
        $previous = null;

        foreach ($tokens as $index => $token) {
            $marks = implode(',', $token->marks);

            if ($marks !== $previous) {
                $transitions[] = $index.':'.$marks;
                $previous = $marks;
            }
        }

        return $prefix.implode('|', $transitions);
    }

    /**
     * The readable text of a token run — cell boundaries dropped, words joined
     * by single spaces.
     *
     * @param  list<InlineToken>  $tokens
     */
    private function textOf(array $tokens): string
    {
        $words = [];

        foreach ($tokens as $token) {
            if (! $token->isCellBoundary()) {
                $words[] = $token->word;
            }
        }

        return implode(' ', $words);
    }

    /**
     * Split a text node into words.
     *
     * Non-breaking space counts as whitespace: the editor emits it freely, and
     * a value that differs from another only by `&nbsp;` versus a plain space
     * is not a change anyone can see.
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        return preg_split('/[\s\x{00A0}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Collapse whitespace runs to a single space, so HTML that was
     * re-serialised (different indentation, a line break moved) but says the
     * same thing tokenises identically.
     */
    private function normalize(string $text): string
    {
        return implode(' ', $this->words($text));
    }

    /**
     * `taskList` (TipTap's checkbox list) is a third list type beside `ul` and
     * `ol`, distinguished by the wrapper's `data-type` rather than its tag.
     */
    private function listTypeOf(DOMElement $list): string
    {
        if ($list->getAttribute('data-type') === 'taskList') {
            return 'task';
        }

        return strtolower($list->nodeName);
    }
}
