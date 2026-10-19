<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FindsOneScene;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Services\SceneResolver;
use App\Services\SceneReviewNoteWriter;
use App\Support\AutosavableFields;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('save-scene-review')]
#[Description('Save the points to fix in a scene as its review note, in the "TODO" note category. A scene has one review note: a new review replaces the old one, and the old text stays in the note history.')]
#[IsIdempotent]
class SaveSceneReviewTool extends Tool
{
    use FindsOneScene;
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'scene' => $schema->string()->description('Scene ID or name.')->required(),
            'project' => $schema->integer()->description('Only look for the scene name in this project ID.'),
            'body' => $schema->string()->description('The review, as HTML.')->required(),
        ];
    }

    public function handle(Request $request, SceneResolver $resolver, SceneReviewNoteWriter $writer): Response
    {
        return $this->guard(function () use ($request, $resolver, $writer): Response {
            $data = $request->validate([
                'scene' => ['required'],
                'project' => ['nullable', 'integer'],
                'body' => ['required', ...AutosavableFields::validationRule('note', 'body')],
            ]);

            $scene = $this->findOneScene($resolver, (string) $data['scene'], isset($data['project']) ? (int) $data['project'] : null);

            if ($scene instanceof Response) {
                return $scene;
            }

            $note = $writer->write($scene, $data['body']);

            return Response::text("Saved review note #{$note->id} \"{$note->title}\" for scene #{$scene->id}.");
        });
    }
}
