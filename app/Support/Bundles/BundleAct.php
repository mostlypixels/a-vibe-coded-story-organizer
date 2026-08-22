<?php

namespace App\Support\Bundles;

/**
 * One act of a bundle's starting outline, with its chapters in order.
 */
final readonly class BundleAct
{
    /**
     * @param  array<int, string>  $chapters  chapter names, in order
     */
    public function __construct(
        public string $name,
        public array $chapters,
    ) {}
}
