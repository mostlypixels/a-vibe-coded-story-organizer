<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreNoteRequest;
use App\Mcp\Tools\Concerns\ResolvesNoteFields;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Note;
use App\Services\RevisionRecorder;
use App\Support\AutosavableFields;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update-note')]
#[Description('Change a note: title, body, category or links. Only the arguments you send change. Sent links replace all links of the note; send an empty list to remove them. Send null for category to clear it. The old body stays in the note history.')]
#[IsIdempotent]
class UpdateNoteTool extends Tool
{
    use ResolvesNoteFields;
    use ReturnsToolErrors;

    private const ARGUMENTS = ['title', 'body', 'category', 'links'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'note' => $schema->integer()->description('Note ID.')->required(),
            'title' => $schema->string()->description('New title.'),
            'body' => $schema->string()->description('New text, as HTML. It replaces the whole body.')->nullable(),
            'category' => $schema->string()->description('ID or name of an existing note category. No category is created.')->nullable(),
            'links' => $schema->array()->items($schema->string())->description('The full set of links, each as "<type>:<id>". Types: book, act, chapter, scene, event, plotline, codex.'),
        ];
    }

    public function handle(Request $request, RevisionRecorder $recorder): Response
    {
        return $this->guard(function () use ($request, $recorder): Response {
            $note = Note::query()->with('project.user')->findOrFail($request->validate(['note' => ['required', 'integer']])['note']);
            $project = $note->project;

            $data = $request->validate([
                'title' => ['sometimes', ...StoreNoteRequest::fieldRules()['title']],
                'body' => ['sometimes', ...AutosavableFields::validationRule('note', 'body')],
                'category' => ['sometimes', 'nullable'],
                ...array_map(fn (array $rules) => ['sometimes', ...$rules], $this->noteLinkRules($project)),
            ]);

            $sent = array_keys(array_intersect_key($data, array_flip(self::ARGUMENTS)));

            if ($sent === []) {
                return Response::error('Send at least one field to change: '.implode(', ', self::ARGUMENTS).'.');
            }

            $columns = array_intersect_key($data, array_flip(['title', 'body']));

            if (array_key_exists('category', $data)) {
                $columns['note_category_id'] = $this->noteCategoryId($project, $data['category']);
            }

            DB::transaction(function () use ($note, $columns, $data, $recorder) {
                $heldSince = $note->updated_at;
                $before = AutosavableFields::snapshotFieldsBeforeUpdate($note, $columns);

                $note->update($columns);
                $recorder->recordManualChanges($note, $before, $note->project->user, __('Claude'), $heldSince);

                if (array_key_exists('links', $data)) {
                    $this->replaceNoteLinks($note, $data['links'] ?? []);
                }
            });

            return Response::text("Updated note #{$note->id} \"{$note->title}\": ".implode(', ', $sent).'.');
        });
    }
}
