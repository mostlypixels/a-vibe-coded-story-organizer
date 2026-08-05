<?php

namespace App\Support;

/**
 * One entry in a breadcrumb trail.
 *
 * `url = null` covers two different reasons a crumb isn't a link: a section
 * crumb (Story/Timeline/Codex/Tools) is a dropdown trigger with no page of
 * its own, and the current crumb never links to itself. Exactly one crumb in
 * a trail has `current = true` — see {@see Breadcrumbs}.
 */
final readonly class Crumb
{
    public function __construct(
        public string $label,
        public ?string $url = null,
        public bool $current = false,
    ) {}
}
