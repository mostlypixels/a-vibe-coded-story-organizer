<?php

namespace App\Services\Diff;

use App\Support\HtmlBlock;
use App\Support\InlineToken;
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
     * recorded as `a:<href>` so re-pointing it counts as a change.
     *
     * @var list<string>
     */
    public const MARK_TAGS = ['strong', 'em', 'u', 's', 'code', 'a'];

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

    private function simpleBlock(DOMElement $element, string $tag): HtmlBlock
    {
        $tokens = $this->tokensFrom($element, marks: [], skipLists: false);

        return $this->buildBlock($tag, [], $tokens, $this->textOf($tokens));
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
        return new HtmlBlock($tag, $attributes, $text, $tokens, $this->signatureOf($tokens));
    }

    /**
     * Collect the word tokens under `$node`, carrying the mark stack down.
     *
     * `br` and the task-list checkbox `input` produce nothing: the first is
     * already a word boundary, and the second is rendered from the item's
     * `data-checked` attribute instead. Unknown elements are transparent — we
     * keep their text and drop the tag, which is what "ignored, not emitted"
     * means for something outside the vocabulary.
     *
     * @param  list<string>  $marks
     * @return list<InlineToken>
     */
    private function tokensFrom(DOMNode $node, array $marks, bool $skipLists): array
    {
        if ($node instanceof DOMText) {
            return array_map(fn (string $word): InlineToken => new InlineToken($word, $marks), $this->words($node->textContent));
        }

        $tokens = [];

        foreach ($node->childNodes ?? [] as $child) {
            if ($child instanceof DOMText) {
                foreach ($this->words($child->textContent) as $word) {
                    $tokens[] = new InlineToken($word, $marks);
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if ($tag === 'br' || $tag === 'input') {
                continue;
            }

            if ($skipLists && in_array($tag, self::LIST_TAGS, true)) {
                continue;
            }

            $childMarks = $marks;

            if (in_array($tag, self::MARK_TAGS, true)) {
                $childMarks[] = $tag === 'a'
                    ? 'a:'.trim($child->getAttribute('href'))
                    : $tag;
            }

            $tokens = array_merge($tokens, $this->tokensFrom($child, $childMarks, $skipLists));
        }

        return $tokens;
    }

    /**
     * A fingerprint of where the marks change along the block.
     *
     * Recorded as transitions (position + new mark stack) rather than one entry
     * per word, so it stays short on long paragraphs while still differing
     * whenever the *same* words are formatted differently — which is exactly
     * the "formatting changed" signal the differ looks for.
     *
     * @param  list<InlineToken>  $tokens
     */
    private function signatureOf(array $tokens): string
    {
        $transitions = [];
        $previous = null;

        foreach ($tokens as $index => $token) {
            $marks = implode(',', $token->marks);

            if ($marks !== $previous) {
                $transitions[] = $index.':'.$marks;
                $previous = $marks;
            }
        }

        return implode('|', $transitions);
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
