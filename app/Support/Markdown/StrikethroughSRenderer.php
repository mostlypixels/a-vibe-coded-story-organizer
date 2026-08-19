<?php

namespace App\Support\Markdown;

use App\Support\RichTextFields;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

/**
 * Renders Markdown strikethrough (`~~text~~`) as `<s>` instead of CommonMark's
 * default `<del>`.
 *
 * `<del>` is reserved for generated revision diffs and is absent from
 * {@see RichTextFields::ALLOWED_TAGS} (see
 * RichTextFieldsDiffTagsTest). The sanitizer therefore strips it, which made
 * every `~~...~~` paragraph fail the import allow-list check. `<s>` is the tag
 * the WYSIWYG editor already writes for the same author intent, so this brings
 * the Markdown path in step with the editor rather than widening the allow-list.
 */
class StrikethroughSRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        Strikethrough::assertInstanceOf($node);

        return new HtmlElement('s', $node->data->get('attributes'), $childRenderer->renderNodes($node->children()));
    }
}
