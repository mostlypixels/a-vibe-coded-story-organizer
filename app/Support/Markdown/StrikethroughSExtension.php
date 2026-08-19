<?php

namespace App\Support\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Strikethrough\Strikethrough;

/** Replaces the GFM strikethrough renderer with {@see StrikethroughSRenderer}. */
class StrikethroughSExtension implements ExtensionInterface
{
    /** Above the GFM default of 0, so this renderer wins the node. */
    public const PRIORITY = 10;

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addRenderer(Strikethrough::class, new StrikethroughSRenderer, self::PRIORITY);
    }
}
