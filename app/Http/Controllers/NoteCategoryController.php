<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoteCategoryRequest;
use App\Http\Requests\UpdateNoteCategoryRequest;
use App\Models\NoteCategory;
use App\Models\Project;
use App\Services\NoteCategoryDeleter;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

class NoteCategoryController extends Controller
{
    public function store(StoreNoteCategoryRequest $request, Project $project): RedirectResponse
    {
        $project->noteCategories()->create($request->validated());

        return redirect()->route('projects.notes.index', $project)->with(Flash::SUCCESS, __('Category created.'));
    }

    public function update(UpdateNoteCategoryRequest $request, NoteCategory $noteCategory): RedirectResponse
    {
        $noteCategory->update($request->validated());

        return redirect()->route('projects.notes.index', $noteCategory->project)->with(Flash::SUCCESS, __('Category saved.'));
    }

    public function destroy(NoteCategory $noteCategory, NoteCategoryDeleter $deleter): RedirectResponse
    {
        $this->authorize('update', $noteCategory->project);

        $project = $noteCategory->project;
        $deleter->delete($noteCategory);

        return redirect()->route('projects.notes.index', $project)->with(Flash::SUCCESS, __('Category deleted.'));
    }
}
