<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ProjectController extends Controller
{
    /**
     * The disk and directory the project cover image lives on. Mirrors
     * CodexMediaService::DISK ('public') so covers are reachable at /storage/...
     * once `php artisan storage:link` has run.
     */
    private const COVER_DISK = 'public';

    private const COVER_DIRECTORY = 'project-covers';

    public function create(): View
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()->route('projects.show', $project);
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->loadCount(['plotlines', 'events']);

        return view('projects.show', ['project' => $project]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', ['project' => $project]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        // The cover is a file, not a mass-assignable column value, so keep it (and its
        // remove checkbox) out of the plain attribute update and resolve it separately.
        $data = $request->safe()->except(['cover_image', 'remove_cover_image']);

        // The previous file is only unlinked *after* a successful save, so a failed
        // write never leaves the row pointing at a file we already deleted.
        $previousCover = $project->cover_image;
        $storedCover = null;

        if ($request->hasFile('cover_image')) {
            $storedCover = $this->storeCoverImage($request->file('cover_image'));
            $data['cover_image'] = $storedCover;
        } elseif ($request->boolean('remove_cover_image')) {
            $data['cover_image'] = null;
        }

        try {
            $project->update($data);
        } catch (Throwable $exception) {
            // The row write failed after the new file landed — unlink it before
            // rethrowing so the failure never leaves an orphan file behind
            // (mirrors CodexMediaService::store()'s store-then-unlink pattern).
            $this->deleteCoverImage($storedCover);

            throw $exception;
        }

        // A new upload replaces the old file; the remove checkbox clears it. Either way
        // the previous file is now safe to delete post-commit.
        if ($storedCover !== null || $request->boolean('remove_cover_image')) {
            $this->deleteCoverImage($previousCover);
        }

        return redirect()->route('projects.show', $project);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('dashboard');
    }

    /**
     * Store an already-validated cover upload on the public disk and return its path.
     *
     * A single path column on `projects` (no codex_media-style tracking row), so this
     * is intentionally thinner than CodexMediaService::store().
     */
    private function storeCoverImage(UploadedFile $file): string
    {
        return $file->store(self::COVER_DIRECTORY, self::COVER_DISK);
    }

    /**
     * Delete a cover file off the public disk when there is one, so a replaced or
     * removed cover never lingers as an orphan.
     */
    private function deleteCoverImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::COVER_DISK)->delete($path);
        }
    }
}
