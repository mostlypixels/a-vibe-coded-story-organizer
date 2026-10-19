<?php

namespace App\Mcp\Tools;

use App\Enums\SearchDomain;
use App\Enums\SearchMode;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Project;
use App\Services\ProjectSearch;
use App\Support\SearchResultRow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search')]
#[Description('Search one project, like the search page. Prints matching rows with their ID and a snippet; a match shows as [text]. Check a name or a fact against the whole project.')]
#[IsReadOnly]
class SearchTool extends Tool
{
    use ReturnsToolErrors;

    /** Rows per call. A common word in a long book would flood the answer. */
    public const LIMIT = 50;

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->integer()->description('Project ID.')->required(),
            'query' => $schema->string()->description('Search terms.')->required(),
            'in' => $schema->string()->enum(array_column(SearchDomain::cases(), 'value'))->description('Where to search. Default: scenes.'),
            'mode' => $schema->string()->enum(array_column(SearchMode::cases(), 'value'))->description('all: every word. any: one word. exact: the whole phrase. Default: all.'),
        ];
    }

    public function handle(Request $request, ProjectSearch $search): Response
    {
        return $this->guard(function () use ($request, $search): Response {
            $data = $request->validate([
                'project' => ['required', 'integer'],
                'query' => ['required', 'string'],
                'in' => ['nullable', Rule::enum(SearchDomain::class)],
                'mode' => ['nullable', Rule::enum(SearchMode::class)],
            ]);

            $project = Project::query()->findOrFail($data['project']);
            $domain = SearchDomain::from($data['in'] ?? SearchDomain::Scenes->value);
            $mode = SearchMode::from($data['mode'] ?? SearchMode::AllTerms->value);

            $rows = $search->searchDomain($project, $domain, $data['query'], $mode);

            if ($rows->isEmpty()) {
                return Response::text('No matches.');
            }

            $lines = ["# {$domain->label()} matching \"{$data['query']}\" ({$rows->count()})", ''];

            foreach ($rows->take(self::LIMIT) as $row) {
                array_push($lines, ...$this->rowLines($row, $domain));
            }

            if ($rows->count() > self::LIMIT) {
                array_push($lines, '', 'Showing the first '.self::LIMIT.'. Narrow the query to see the others.');
            }

            return Response::text(implode("\n", $lines));
        });
    }

    /** @return list<string> */
    private function rowLines(SearchResultRow $row, SearchDomain $domain): array
    {
        $entity = $row->entity;
        $label = $entity->getAttribute($domain->nameField());
        $where = $row->book === null ? '' : " in {$row->book->displayName()}";
        $snippet = preg_replace('#<mark[^>]*>(.*?)</mark>#s', '[$1]', $row->snippet);

        return [
            "- #{$entity->getKey()} {$label}{$where} ({$row->matchedFields()})",
            '    '.html_entity_decode(strip_tags((string) $snippet), ENT_QUOTES | ENT_HTML5),
        ];
    }
}
