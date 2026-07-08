<?php

namespace App\Services;

use App\Models\CrawlerSetting;

/**
 * Turns a {@see CrawlerSetting} into robots.txt text.
 *
 * A robots.txt-compliant crawler obeys ONLY the most specific matching
 * "User-agent" group. We exploit that for the whitelist: a named bot matches its
 * own group (empty "Disallow:" = crawl everything); every other bot falls to the
 * catch-all "*" group ("Disallow: /" = crawl nothing). This is the standard
 * allow-list idiom.
 *
 * Whitelist terms are already line-safe (validated on the write path by
 * UpdateCrawlerSettingRequest), so the generator trusts them and does no escaping.
 */
class RobotsTxtGenerator
{
    public function generate(CrawlerSetting $setting): string
    {
        // Hidden off — allow everyone. A single group with an empty Disallow.
        if (! $setting->isHidden()) {
            return "User-agent: *\nDisallow:\n";
        }

        $lines = [];

        // One allow-group per whitelisted term: named bots may crawl everything.
        foreach ($setting->whitelistTerms() as $term) {
            $lines[] = "User-agent: {$term}";
            $lines[] = 'Disallow:';
            $lines[] = '';
        }

        // Catch-all block: everyone else is disallowed from the configured path.
        $lines[] = 'User-agent: *';
        $lines[] = 'Disallow: '.config('crawlers.disallow_path');

        // Trailing newline terminates the file.
        return implode("\n", $lines)."\n";
    }
}
