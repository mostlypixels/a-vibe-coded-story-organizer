<?php

namespace App\Mcp\Tools;

use App\Enums\SceneStatus;
use App\Http\Requests\StoreSceneRequest;
use App\Mcp\Tools\Concerns\FindsOneScene;
use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Services\SceneResolver;
use App\Services\SceneSaver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

/**
 * Changes the details of a scene, never its text.
 *
 * > [!WARNING]
 * > Do not add a `contents` argument. Scene text belongs to the author.
 */
#[Name('update-scene')]
#[Description('Change the details of a scene: title, description, status, event or viewpoint. Only the arguments you send change. The scene text never changes. Send null for event or viewpoint to clear it.')]
#[IsIdempotent]
class UpdateSceneTool extends Tool
{
    use FindsOneScene;
    use ReturnsToolErrors;

    /** Tool argument => scene column. */
    private const COLUMNS = [
        'name' => 'name',
        'description' => 'description',
        'status' => 'status',
        'event' => 'event_id',
        'viewpoint' => 'viewpoint_character_id',
    ];

    public function schema(JsonSchema $schema): array
    {
        return [
            'scene' => $schema->string()->description('Scene ID or name.')->required(),
            'project' => $schema->integer()->description('Only look for the scene name in this project ID.'),
            'name' => $schema->string()->description('New scene title.'),
            'description' => $schema->string()->description('New description, as HTML.')->nullable(),
            'status' => $schema->string()->enum(array_column(SceneStatus::cases(), 'value'))->description('New status.'),
            'event' => $schema->integer()->description('ID of the event the scene happens during.')->nullable(),
            'viewpoint' => $schema->integer()->description('ID of the viewpoint character (a codex entry).')->nullable(),
        ];
    }

    public function handle(Request $request, SceneResolver $resolver, SceneSaver $saver): Response
    {
        return $this->guard(function () use ($request, $resolver, $saver): Response {
            $where = $request->validate([
                'scene' => ['required'],
                'project' => ['nullable', 'integer'],
            ]);

            $scene = $this->findOneScene($resolver, (string) $where['scene'], isset($where['project']) ? (int) $where['project'] : null);

            if ($scene instanceof Response) {
                return $scene;
            }

            $sceneRules = StoreSceneRequest::rulesFor($scene->book());
            $rules = [];

            foreach (self::COLUMNS as $argument => $column) {
                $rules[$argument] = ['sometimes', ...$sceneRules[$column]];
            }

            $data = $request->validate($rules, attributes: StoreSceneRequest::fieldAttributes());

            if ($data === []) {
                return Response::error('Send at least one field to change: '.implode(', ', array_keys(self::COLUMNS)).'.');
            }

            $details = [];

            foreach (Arr::only($data, array_keys(self::COLUMNS)) as $argument => $value) {
                $details[self::COLUMNS[$argument]] = $value;
            }

            $saver->updateDetails($scene, $details, $scene->project()->user, __('Claude'));

            return Response::text("Updated scene #{$scene->id} {$scene->name}: ".implode(', ', array_keys($data)).'.');
        });
    }
}
