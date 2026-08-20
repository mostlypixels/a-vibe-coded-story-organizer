<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsAfterSave;
use App\Http\Requests\StoreChallengeRequest;
use App\Http\Requests\UpdateChallengeRequest;
use App\Models\Challenge;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Create, edit, and delete challenges. No `index`: the Progress page lists
 * them. Not `RecordsManualRevisions` — a challenge is a target, not authored
 * content, and edits are silent.
 */
class ChallengeController extends Controller
{
    use RedirectsAfterSave;

    public function create(Project $project): View
    {
        $this->authorize('update', $project);

        return view('challenges.create', ['project' => $project]);
    }

    public function store(StoreChallengeRequest $request, Project $project): RedirectResponse
    {
        $project->challenges()->create($request->validated());

        return redirect()->route('projects.progress', $project);
    }

    public function edit(Challenge $challenge): View
    {
        $this->authorize('update', $challenge->project);

        return view('challenges.edit', ['challenge' => $challenge, 'project' => $challenge->project]);
    }

    public function update(UpdateChallengeRequest $request, Challenge $challenge): RedirectResponse
    {
        $challenge->update($request->validated());

        return $this->redirectAfterSave(
            $request,
            ['challenges.edit', $challenge],
            ['projects.progress', $challenge->project],
        );
    }

    public function destroy(Challenge $challenge): RedirectResponse
    {
        $this->authorize('update', $challenge->project);

        $project = $challenge->project;
        $challenge->delete();

        return redirect()->route('projects.progress', $project);
    }
}
