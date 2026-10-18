<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEditorLayoutRequest;
use App\Support\EditorLayout;
use Illuminate\Http\Response;

/**
 * The whole-user layout of the Write page.
 *
 * No policy and no `ProjectPolicy` walk: the preference has no owning
 * project, and the write always targets the acting user.
 */
class EditorLayoutController extends Controller
{
    public function update(UpdateEditorLayoutRequest $request): Response
    {
        $request->user()->update([
            'editor_layout' => EditorLayout::resolve($request->validated()),
        ]);

        return response()->noContent();
    }
}
