<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePublicationSettingRequest;
use App\Models\Book;
use App\Models\PublicationSetting;
use Illuminate\Http\RedirectResponse;

/**
 * Persists a book's PublicationSetting — the Export-ebook configuration
 * form (toggles, enums, appendix types) — and reorders its front/back-matter
 * `section_order` list.
 *
 * This controller owns the write path only. It mirrors ImportSettingController's
 * thin resolve -> authorize -> persist -> redirect shape.
 */
class PublicationSettingController extends Controller
{
    /**
     * Save every toggle/enum/appendix-type field from the config form.
     * `firstOrNew`s the singleton row per book, lazily, so the first save
     * creates it and every later save updates the same row.
     */
    public function update(UpdatePublicationSettingRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book->project);

        $setting = $book->publicationSettingOrDefault();
        $setting->fill($request->validated());
        $setting->save();

        return $this->backToConfigForm($book);
    }

    /**
     * Move a section one step earlier in `section_order`. A dedicated action
     * per section, mirroring ActController::moveUp, rather than folding
     * reordering into the main config form submission.
     */
    public function moveSectionUp(Book $book, string $section): RedirectResponse
    {
        return $this->moveSection($book, $section, moveUp: true);
    }

    /**
     * Move a section one step later in `section_order`.
     */
    public function moveSectionDown(Book $book, string $section): RedirectResponse
    {
        return $this->moveSection($book, $section, moveUp: false);
    }

    private function moveSection(Book $book, string $section, bool $moveUp): RedirectResponse
    {
        $this->authorize('update', $book->project);
        abort_unless(in_array($section, PublicationSetting::SECTION_KEYS, true), 404);

        $setting = $book->publicationSettingOrDefault();
        $moveUp ? $setting->moveSectionUp($section) : $setting->moveSectionDown($section);
        $setting->save();

        return $this->backToConfigForm($book);
    }

    private function backToConfigForm(Book $book): RedirectResponse
    {
        return redirect()
            ->route('admin.data.export-ebook', ['book' => $book->id])
            ->with('status', 'publication-settings-updated');
    }
}
