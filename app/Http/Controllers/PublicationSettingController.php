<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePublicationSettingRequest;
use App\Models\Project;
use App\Models\PublicationSetting;
use Illuminate\Http\RedirectResponse;

/**
 * Persists a project's PublicationSetting — the Export-ebook configuration
 * form (toggles, enums, appendix types) — and reorders its front/back-matter
 * `section_order` list.
 *
 * The EPUB exporter does not consume PublicationSetting yet (that lands in
 * task 08+); this controller only owns the write path, mirroring
 * ImportSettingController's thin resolve -> authorize -> persist -> redirect
 * shape.
 */
class PublicationSettingController extends Controller
{
    /**
     * Save every toggle/enum/appendix-type field from the config form.
     * `firstOrNew`s the singleton row per project (lazy, per overview #2) so
     * the first save creates it and every later save updates the same row.
     */
    public function update(UpdatePublicationSettingRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $setting = $project->publicationSettingOrDefault();
        $setting->fill($request->validated());
        $setting->save();

        return $this->backToConfigForm($project);
    }

    /**
     * Move a section one step earlier in `section_order`. A dedicated action
     * per section, mirroring ActController::moveUp, rather than folding
     * reordering into the main config form submission.
     */
    public function moveSectionUp(Project $project, string $section): RedirectResponse
    {
        return $this->moveSection($project, $section, moveUp: true);
    }

    /**
     * Move a section one step later in `section_order`.
     */
    public function moveSectionDown(Project $project, string $section): RedirectResponse
    {
        return $this->moveSection($project, $section, moveUp: false);
    }

    private function moveSection(Project $project, string $section, bool $moveUp): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless(in_array($section, PublicationSetting::SECTION_KEYS, true), 404);

        $setting = $project->publicationSettingOrDefault();
        $moveUp ? $setting->moveSectionUp($section) : $setting->moveSectionDown($section);
        $setting->save();

        return $this->backToConfigForm($project);
    }

    private function backToConfigForm(Project $project): RedirectResponse
    {
        return redirect()
            ->route('admin.data.export-ebook', ['project' => $project->id])
            ->with('status', 'publication-settings-updated');
    }
}
