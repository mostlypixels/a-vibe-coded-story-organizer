<?php

namespace App\Http\Controllers;

use App\Enums\EditorCardState;
use App\Enums\LinkTargetType;
use App\Http\Requests\UpdateEditorCardRequest;
use App\Models\EditorCard;
use App\Models\Scene;
use App\Services\EditorCards;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Reads and saves the cards of the Write page, for the acting user. */
class EditorCardController extends Controller
{
    /** The cards of one section as an HTML partial, the same markup as the page load. */
    public function index(Request $request, Scene $scene, string $type, EditorCards $cards): View
    {
        $project = $scene->project();

        $this->authorize('update', $project);

        $isCodex = $type === LinkTargetType::Codex->value;

        return view('editor.partials.cards', [
            'type' => $type,
            'scene' => $scene->load('event'),
            'project' => $project,
            'cards' => $isCodex
                ? $cards->codex($request->user(), $scene, $project)
                : $cards->notes($request->user(), $scene),
        ]);
    }

    public function update(UpdateEditorCardRequest $request, Scene $scene): Response
    {
        EditorCard::saveState(
            $request->user(),
            $scene,
            $request->target(),
            EditorCardState::from($request->validated('state')),
        );

        return response()->noContent();
    }
}
