<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreNoteRequest;
use App\Mcp\Tools\Concerns\ResolvesNoteFields;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Project;
use App\Support\AutosavableFields;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-note')]
#[Description('Create a note in a project: a titled page about the story, such as research or a to-do list. Optionally put it in an existing category and link it to books, acts, chapters, scenes, events, plotlines or codex entries.')]
class CreateNoteTool extends Tool
{
    use ResolvesNoteFields;
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->integer()->description('Project ID.')->required(),
            'title' => $schema->string()->description('Note title.')->required(),
            'body' => $schema->string()->description('Note text, as HTML.')->required(),
            'category' => $schema->string()->description('ID or name of an existing note category. No category is created.'),
            'links' => $schema->array()->items($schema->string())->description('Entities to link, each as "<type>:<id>". Types: book, act, chapter, scene, event, plotline, codex. Example: "scene:12".'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->guard(function () use ($request): Response {
            $project = Project::query()->findOrFail($request->validate(['project' => ['required', 'integer']])['project']);

            $data = $request->validate([
                ...StoreNoteRequest::fieldRules(),
                'body' => ['required', ...AutosavableFields::validationRule('note', 'body')],
                'category' => ['nullable'],
                ...$this->noteLinkRules($project),
            ]);

            $categoryId = $this->noteCategoryId($project, $data['category'] ?? null);
            $links = $data['links'] ?? [];

            $note = DB::transaction(function () use ($project, $data, $categoryId, $links) {
                $note = $project->notes()->create([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'note_category_id' => $categoryId,
                ]);

                $this->replaceNoteLinks($note, $links);

                return $note;
            });

            $category = $categoryId === null ? 'no category' : "category #{$categoryId}";

            return Response::text("Created note #{$note->id} \"{$note->title}\" in project #{$project->id}: {$category}, {$this->describeLinks($links)}.");
        });
    }
}
