<?php

namespace App\Mcp\Tools;

use App\Http\Requests\StoreAttributeValueRequest;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\CodexAttribute;
use App\Models\CodexEntry;
use App\Services\AttributeTimeline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Sets the value of a codex attribute from one event onward.
 *
 * {@see AttributeTimeline::upsertAt()} keeps the Start baseline in place. No tool creates
 * an attribute definition, so an unknown attribute is an error.
 */
#[Name('set-codex-attribute')]
#[Description('Set the value of an attribute of a codex entry, such as a goal or a belief, from one event onward. Without start_event, the value applies from the project Start. A value at an event that already has one replaces it. The attribute must already exist for the entry type.')]
#[IsIdempotent]
class SetCodexAttributeTool extends Tool
{
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'entry' => $schema->integer()->description('Codex entry ID.')->required(),
            'attribute' => $schema->string()->description('Attribute name or ID.')->required(),
            'value' => $schema->string()->description('The value. An empty string records a blank value.')->required(),
            'start_event' => $schema->integer()->description('ID of the event the value applies from. Default: the project Start event.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->guard(function () use ($request): Response {
            $entry = CodexEntry::query()->with('project')->findOrFail($request->validate(['entry' => ['required', 'integer']])['entry']);
            $project = $entry->project;
            $valueRules = StoreAttributeValueRequest::fieldRules($project);

            $data = $request->validate([
                'attribute' => ['required', 'string'],
                'value' => $valueRules['value'],
                'start_event' => ['nullable', ...$valueRules['start_event_id']],
            ]);

            $attribute = $this->attributeFor($entry, $data['attribute']);
            $startEvent = isset($data['start_event'])
                ? $project->events()->findOrFail($data['start_event'])
                : $project->startEvent();

            (new AttributeTimeline($entry, $attribute))->upsertAt($startEvent, (string) $data['value']);

            return Response::text("Set {$attribute->name} of #{$entry->id} {$entry->name} from event #{$startEvent->id} {$startEvent->title}: ".((string) $data['value'] === '' ? '(empty)' : $data['value']).'.');
        });
    }

    /** An attribute of the project that applies to the entry type, by ID or by name without regard to case. */
    private function attributeFor(CodexEntry $entry, string $reference): CodexAttribute
    {
        $reference = trim($reference);
        $attributes = $entry->project->codexAttributesFor($entry->type);

        $byId = ctype_digit($reference) ? $attributes->firstWhere('id', (int) $reference) : null;

        if ($byId !== null) {
            return $byId;
        }

        $matches = $attributes->filter(fn (CodexAttribute $attribute) => mb_strtolower($attribute->name) === mb_strtolower($reference));

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $problem = $matches->isEmpty()
            ? "No {$entry->type->label()} attribute matches \"{$reference}\". Use `show` on the entry, or ask the author to add the attribute."
            : "Several attributes are named \"{$reference}\". Use an ID: ".$matches->map(fn (CodexAttribute $attribute) => "#{$attribute->id}")->join(', ').'.';

        throw ValidationException::withMessages(['attribute' => $problem]);
    }
}
