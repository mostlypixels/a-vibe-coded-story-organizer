<?php

namespace App\Console\Commands;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Models\Project;
use App\Services\ProjectSearch;
use Illuminate\Console\Command;

/**
 * The project search page as plain text. The scene-reviewer agent uses it to check a
 * name or a fact against the whole project. A match shows as [text].
 */
class SceneSearchCommand extends Command
{
    protected $signature = 'scene:search
        {project : Project ID}
        {query : Search terms}
        {--in=scenes : Where to search: scenes, characters, locations, organizations, events, notes, …}
        {--mode=all : all, any or exact}';

    protected $description = 'Search one project and print the matches as plain text';

    public function handle(ProjectSearch $search): int
    {
        $project = Project::query()->find($this->argument('project'));
        $domain = SearchDomain::tryFrom((string) $this->option('in'));
        $mode = SearchMode::tryFrom((string) $this->option('mode'));

        if ($project === null || $domain === null || $mode === null) {
            $this->error('Unknown project, --in or --mode value.');

            return self::FAILURE;
        }

        $rows = $search->searchDomain($project, $domain, $this->argument('query'), $mode);

        if ($rows->isEmpty()) {
            $this->line('No matches.');
        }

        foreach ($rows as $row) {
            $snippet = preg_replace('#<mark[^>]*>(.*?)</mark>#s', '[$1]', $row->snippet);
            $label = $row->entity->getAttribute('name') ?? $row->entity->getAttribute('title');

            $this->line("{$row->entity->getKey()}  {$label}  ({$row->matchedFields()})");
            $this->line('    '.html_entity_decode(strip_tags((string) $snippet), ENT_QUOTES | ENT_HTML5));
        }

        return self::SUCCESS;
    }
}
