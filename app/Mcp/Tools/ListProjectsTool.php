<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Project;
use App\Support\CountPhrase;
use App\Support\WordCountFormat;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-projects')]
#[Description('List every project with its ID, name, book count, scene count and word count.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use ReturnsToolErrors;

    public function handle(): Response
    {
        return $this->guard(function (): Response {
            $projects = Project::query()->withCount('books')->orderBy('id')->get();

            if ($projects->isEmpty()) {
                return Response::text('No projects.');
            }

            $lines = $projects->map(function (Project $project): string {
                $scenes = $project->sceneQuery();

                return sprintf(
                    '- #%d %s: %s, %s, %s',
                    $project->id,
                    $project->name,
                    CountPhrase::make($project->books_count, 'book', 'books'),
                    CountPhrase::make($scenes->count(), 'scene', 'scenes'),
                    WordCountFormat::text((int) $scenes->sum('word_count')),
                );
            });

            return Response::text("# Projects\n\n".$lines->implode("\n"));
        });
    }
}
