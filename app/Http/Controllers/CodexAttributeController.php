<?php

namespace App\Http\Controllers;

use App\Enums\CodexEntryType;
use App\Http\Requests\StoreCodexAttributeRequest;
use App\Http\Requests\UpdateCodexAttributeRequest;
use App\Models\CodexAttribute;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CodexAttributeController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('codex-attributes.index', [
            'project' => $project,
            // Attributes render on the sheet in their stored display order.
            'attributes' => $project->codexAttributes()->orderBy('position')->get(),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('codex-attributes.create', [
            'project' => $project,
            'types' => CodexEntryType::cases(),
        ]);
    }

    public function store(StoreCodexAttributeRequest $request, Project $project): RedirectResponse
    {
        // position is assigned by the CodexAttribute::creating() hook.
        $project->codexAttributes()->create($request->validated());

        return redirect()->route('projects.codex-attributes.index', $project);
    }

    public function edit(CodexAttribute $codexAttribute): View
    {
        $this->authorize('update', $codexAttribute->project);

        return view('codex-attributes.edit', [
            'project' => $codexAttribute->project,
            'attribute' => $codexAttribute,
            'types' => CodexEntryType::cases(),
        ]);
    }

    public function update(UpdateCodexAttributeRequest $request, CodexAttribute $codexAttribute): RedirectResponse
    {
        $codexAttribute->update($request->validated());

        return $request->boolean('stay')
            ? redirect()->route('codex-attributes.edit', $codexAttribute)->with('status', 'saved')
            : redirect()->route('projects.codex-attributes.index', $codexAttribute->project);
    }

    public function destroy(CodexAttribute $codexAttribute): RedirectResponse
    {
        $this->authorize('update', $codexAttribute->project);

        $project = $codexAttribute->project;

        // FK cascadeOnDelete removes every codex_attribute_values row for this
        // attribute — the UI confirm warns the writer that timeline data is lost.
        $codexAttribute->delete();

        return redirect()->route('projects.codex-attributes.index', $project);
    }
}
