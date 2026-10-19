<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FindsOneScene;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Services\SceneResolver;
use App\Services\SceneReviewContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('scene-context')]
#[Description('Print a scene with its codex, notes and earlier scenes, for a review. Give a scene ID or name. Earlier scenes show their description, except the last few, which show their text.')]
#[IsReadOnly]
class SceneContextTool extends Tool
{
    use FindsOneScene;
    use ReturnsToolErrors;

    public function schema(JsonSchema $schema): array
    {
        return [
            'scene' => $schema->string()->description('Scene ID or name.')->required(),
            'project' => $schema->integer()->description('Only look for the scene name in this project ID.'),
            'full' => $schema->integer()->description('How many earlier scenes to print in full. Default: 3.'),
        ];
    }

    public function handle(Request $request, SceneResolver $resolver, SceneReviewContext $context): Response
    {
        return $this->guard(function () use ($request, $resolver, $context): Response {
            $data = $request->validate([
                'scene' => ['required'],
                'project' => ['nullable', 'integer'],
                'full' => ['nullable', 'integer', 'min:0'],
            ]);

            $scene = $this->findOneScene($resolver, (string) $data['scene'], isset($data['project']) ? (int) $data['project'] : null);

            if ($scene instanceof Response) {
                return $scene;
            }

            return Response::text($context->build($scene, (int) ($data['full'] ?? 3)));
        });
    }
}
