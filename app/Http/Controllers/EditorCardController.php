<?php

namespace App\Http\Controllers;

use App\Enums\EditorCardState;
use App\Http\Requests\UpdateEditorCardRequest;
use App\Models\EditorCard;
use App\Models\Scene;
use Illuminate\Http\Response;

/** Saves the state of one card on the Write page, for the acting user. */
class EditorCardController extends Controller
{
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
