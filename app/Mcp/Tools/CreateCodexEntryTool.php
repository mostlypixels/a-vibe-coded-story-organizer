<?php

namespace App\Mcp\Tools;

use App\Enums\CodexEntryType;
use App\Http\Requests\StoreCodexEntryRequest;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Project;
use App\Services\CodexEntrySaver;
use App\Support\AutosavableFields;
use App\Support\CodexMediaUploads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-codex-entry')]
#[Description('Create a codex entry: a character, location or organization. Scenes whose text contains the name or an alias link to it automatically.')]
class CreateCodexEntryTool extends Tool
{
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->integer()->description('Project ID.')->required(),
            'type' => $schema->string()->enum(array_column(CodexEntryType::cases(), 'value'))->description('Entry type.')->required(),
            'name' => $schema->string()->description('Entry name.')->required(),
            'description' => $schema->string()->description('Description, as HTML.'),
            'aliases' => $schema->array()->items($schema->string())->description('Other names that the scene text uses for the entry.'),
        ];
    }

    public function handle(Request $request, CodexEntrySaver $saver): Response
    {
        return $this->guard(function () use ($request, $saver): Response {
            $project = Project::query()->findOrFail($request->validate(['project' => ['required', 'integer']])['project']);

            $data = $request->validate([
                'type' => ['required', Rule::enum(CodexEntryType::class)],
                ...StoreCodexEntryRequest::fieldRules(),
                'description' => AutosavableFields::validationRule('codex', 'description'),
            ]);

            $entry = $saver->create($project, CodexEntryType::from($data['type']), $data, new CodexMediaUploads);
            $scenes = $entry->referencingScenes()->count();

            return Response::text("Created {$entry->type->label()} #{$entry->id} \"{$entry->name}\" in project #{$project->id}. Scenes that name it: {$scenes}.");
        });
    }
}
