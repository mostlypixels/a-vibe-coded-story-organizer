<?php

namespace App\Support\Bundles;

/**
 * Thin preset content for one genre, applied by the seed action onto a
 * freshly created project. Content is placeholder in v1; a later pass fills
 * it in. Bundles declare no scenes, so the seed path never needs
 * SceneReferenceMatcher.
 */
interface GenreBundle
{
    /**
     * @return array<int, BundleAttribute>
     */
    public function attributes(): array;

    /**
     * @return array<int, string>
     */
    public function tags(): array;

    /**
     * @return array<int, BundleSampleEntry>
     */
    public function sampleEntries(): array;

    /**
     * @return array<int, BundleAct>
     */
    public function skeleton(): array;
}
