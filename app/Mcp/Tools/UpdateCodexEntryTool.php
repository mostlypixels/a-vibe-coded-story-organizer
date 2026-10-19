<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreCodexEntryRequest;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\CodexEntry;
use App\Services\CodexEntrySaver;
use App\Support\AutosavableFields;
use App\Support\CodexMediaUploads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Changes the name, description or aliases of a codex entry. The type never changes.
 *
 * > [!WARNING]
 * > {@see CodexEntrySaver::update()} saves the full edit form. A value missing from its
 * > input clears that column or relation, so this tool fills in every field it does not send.
 */
#[Name('update-codex-entry')]
#[Description('Change a codex entry: name, description or aliases. Only the arguments you send change. Sent aliases replace all aliases; send an empty list to remove them. The type cannot change. The old description stays in the entry history.')]
#[IsIdempotent]
class UpdateCodexEntryTool extends Tool
{
    use ReturnsToolErrors;

    private const ARGUMENTS = ['name', 'description', 'aliases'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'entry' => $schema->integer()->description('Codex entry ID.')->required(),
            'name' => $schema->string()->description('New name.'),
            'description' => $schema->string()->description('New description, as HTML. It replaces the whole description.')->nullable(),
            'aliases' => $schema->array()->items($schema->string())->description('The full set of aliases.'),
        ];
    }

    public function handle(Request $request, CodexEntrySaver $saver): Response
    {
        return $this->guard(function () use ($request, $saver): Response {
            $entry = CodexEntry::query()
                ->with(['project.user', 'aliases', 'tags'])
                ->findOrFail($request->validate(['entry' => ['required', 'integer']])['entry']);

            $fieldRules = StoreCodexEntryRequest::fieldRules();

            $data = $request->validate([
                'name' => ['sometimes', ...$fieldRules['name']],
                'description' => ['sometimes', ...AutosavableFields::validationRule('codex', 'description')],
                'aliases' => ['sometimes', ...$fieldRules['aliases']],
                'aliases.*' => $fieldRules['aliases.*'],
            ]);

            $sent = array_values(array_intersect(self::ARGUMENTS, array_keys($data)));

            if ($sent === []) {
                return Response::error('Send at least one field to change: '.implode(', ', self::ARGUMENTS).'.');
            }

            $form = [
                'name' => $entry->name,
                'description' => $entry->description,
                'aliases' => $entry->aliases->pluck('alias')->all(),
                'tags' => $entry->tags->pluck('name')->all(),
                'inception_event_id' => $entry->inception_event_id,
                'termination_event_id' => $entry->termination_event_id,
                ...$data,
            ];

            // No base hashes: a tool write wins, and an open editor tab gets the conflict choice.
            $saver->update($entry, $form, new CodexMediaUploads, [], $entry->project->user, __('Claude'));

            return Response::text("Updated {$entry->type->label()} #{$entry->id} \"{$entry->name}\": ".implode(', ', $sent).'.');
        });
    }
}
