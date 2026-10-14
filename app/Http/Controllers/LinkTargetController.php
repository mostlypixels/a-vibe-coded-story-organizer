<?php

namespace App\Http\Controllers;

use App\Enums\LinkTargetType;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The "Link to…" picker in the editor searches entities of one project here. */
class LinkTargetController extends Controller
{
    /** Entities of one type that a text link can point to: `[{type, id, label}]`. */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'type' => ['required', 'string', Rule::enum(LinkTargetType::class)],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $type = LinkTargetType::from($request->string('type')->value());

        return response()->json($type->candidates($project, $request->string('q')->value()));
    }
}
