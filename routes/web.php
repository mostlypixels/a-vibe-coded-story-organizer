<?php

use App\Enums\CodexEntryType;
use App\Enums\SearchDomain;
use App\Http\Controllers\ActController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CodexAttributeController;
use App\Http\Controllers\CodexAttributeValueController;
use App\Http\Controllers\CodexController;
use App\Http\Controllers\CodexEntryController;
use App\Http\Controllers\DatabaseConfigurationController;
use App\Http\Controllers\DataTransferController;
use App\Http\Controllers\EpubExportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FieldAutosaveController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportSettingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlotlineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicationSettingController;
use App\Http\Controllers\RevisionBrowserController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\RevisionSettingController;
use App\Http\Controllers\RobotsTxtController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SharedSceneController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\ToolsController;
use App\Http\Middleware\TrackActiveProject;
use App\Services\RevisionPurger;
use App\Support\AutosavableFields;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The share token is the only gate. Keep this route outside `auth`.
Route::get('/shared/scenes/{token}', [SharedSceneController::class, 'show'])
    ->name('shared.scenes.show');

// Crawlers are anonymous. A static public/robots.txt would shadow this route.
Route::get('/robots.txt', RobotsTxtController::class)->name('robots.txt');

// The project list. An account with no projects is redirected to onboarding,
// so every path here (the logo, the auth redirects, a project delete) sends a
// new writer to the same first-project prompt.
Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('projects.index');

Route::get('/onboarding', [OnboardingController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('onboarding');

Route::post('/onboarding', [OnboardingController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('onboarding.store');

Route::post('/onboarding/demo', [OnboardingController::class, 'installDemo'])
    ->middleware(['auth', 'verified'])
    ->name('onboarding.demo');

// Only authenticated project pages update users.active_project_id.
Route::middleware(['auth', TrackActiveProject::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Global settings use one gate because they do not belong to a project.
    Route::middleware('can:access-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.settings.edit'))->name('index');

        Route::get('/settings', [GeneralSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [GeneralSettingsController::class, 'update'])->name('settings.update');

        Route::get('/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
        Route::patch('/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

        Route::get('/data', fn () => redirect()->route('admin.data.export-project'))->name('data.index');
        Route::get('/data/export/project', [DataTransferController::class, 'exportProject'])->name('data.export-project');
        Route::get('/data/export/ebook', [DataTransferController::class, 'exportEbook'])->name('data.export-ebook');
        Route::get('/data/import', [DataTransferController::class, 'import'])->name('data.import.index');

        Route::post('/data/export', [ExportController::class, 'store'])->name('data.export');
        Route::post('/data/export/epub', [EpubExportController::class, 'store'])->name('data.export.epub');

        Route::patch('/data/export/ebook/{book}/settings', [PublicationSettingController::class, 'update'])
            ->name('data.publication-settings.update');
        Route::patch('/data/export/ebook/{book}/settings/section-order/{section}/move-up', [PublicationSettingController::class, 'moveSectionUp'])
            ->name('data.publication-settings.section-order.move-up');
        Route::patch('/data/export/ebook/{book}/settings/section-order/{section}/move-down', [PublicationSettingController::class, 'moveSectionDown'])
            ->name('data.publication-settings.section-order.move-down');

        // Store creates a project. Resume and destroy authorize the existing import.
        Route::post('/data/import', [ImportController::class, 'store'])->name('data.import');
        Route::post('/data/imports/{import}/resume', [ImportController::class, 'resume'])->name('data.imports.resume');
        Route::delete('/data/imports/{import}', [ImportController::class, 'destroy'])->name('data.imports.destroy');
        Route::patch('/data/import-settings', [ImportSettingController::class, 'update'])->name('data.import-settings');

        Route::get('/database', [DatabaseConfigurationController::class, 'edit'])->name('database.edit');

        Route::get('/revisions', [RevisionSettingController::class, 'edit'])->name('revisions.edit');
        Route::patch('/revisions', [RevisionSettingController::class, 'update'])->name('revisions.update');
        Route::delete('/revisions/purge/{category}', [RevisionSettingController::class, 'purgeCategory'])
            ->whereIn('category', RevisionPurger::CATEGORIES)
            ->name('revisions.purge-category');
        Route::delete('/revisions/purge-old-automatic', [RevisionSettingController::class, 'purgeOldAutomatic'])
            ->name('revisions.purge-old-automatic');
    });

    Route::resource('projects', ProjectController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{project}/codex-references/sync', [ProjectController::class, 'syncCodexReferences'])
        ->name('projects.codex-references.sync');

    Route::resource('projects.plotlines', PlotlineController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->shallow();

    // No index: the Progress page lists challenges.
    Route::resource('projects.challenges', ChallengeController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();

    Route::resource('projects.events', EventController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->shallow();

    Route::resource('projects.books', BookController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::get('/books/{book}/select', [BookController::class, 'select'])->name('books.select');
    Route::patch('/books/{book}/move-up', [BookController::class, 'moveUp'])->name('books.move-up');
    Route::patch('/books/{book}/move-down', [BookController::class, 'moveDown'])->name('books.move-down');

    Route::get('/books/{book}/story', [StoryController::class, 'home'])->name('books.story.home');
    Route::get('/projects/{project}/timeline', [TimelineController::class, 'home'])->name('projects.timeline.home');
    Route::get('/projects/{project}/codex', [CodexController::class, 'home'])->name('projects.codex.home');
    Route::get('/projects/{project}/tools', [ToolsController::class, 'home'])->name('projects.tools.home');

    Route::get('/books/{book}/story/overview', [StoryController::class, 'index'])->name('books.story.overview');

    Route::patch('/books/{book}/story/overview/mode', [StoryController::class, 'updateMode'])
        ->name('books.story.overview.mode');

    Route::get('/projects/{project}/search', [SearchController::class, 'index'])->name('projects.search.index');

    // Reject unknown search domains before the controller runs.
    Route::whereIn('domain', SearchDomain::routeKeys())->group(function () {
        Route::get('/projects/{project}/search/{domain}', [SearchController::class, 'domain'])
            ->name('projects.search.domain');
    });

    // Manuscript resources nest for creation and use shallow member routes.
    Route::resource('books.acts', ActController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::patch('/acts/{act}/move-up', [ActController::class, 'moveUp'])->name('acts.move-up');
    Route::patch('/acts/{act}/move-down', [ActController::class, 'moveDown'])->name('acts.move-down');
    Route::patch('/acts/{act}/move-to-book', [ActController::class, 'moveToBook'])->name('acts.move-to-book');

    Route::resource('books.chapters', ChapterController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::patch('/chapters/{chapter}/move-up', [ChapterController::class, 'moveUp'])->name('chapters.move-up');
    Route::patch('/chapters/{chapter}/move-down', [ChapterController::class, 'moveDown'])->name('chapters.move-down');

    Route::resource('books.scenes', SceneController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::patch('/scenes/{scene}/move-up', [SceneController::class, 'moveUp'])->name('scenes.move-up');
    Route::patch('/scenes/{scene}/move-down', [SceneController::class, 'moveDown'])->name('scenes.move-down');
    Route::post('/scenes/{scene}/duplicate', [SceneController::class, 'duplicate'])->name('scenes.duplicate');

    Route::post('/scenes/{scene}/share', [SceneShareController::class, 'store'])->name('scenes.share.store');
    Route::delete('/scenes/{scene}/share', [SceneShareController::class, 'destroy'])->name('scenes.share.destroy');

    // Constrain nested routes locally; member routes use only the entry binding.
    Route::whereIn('type', CodexEntryType::routeKeys())->group(function () {
        Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
            ->name('projects.codex.index');
        Route::get('/projects/{project}/codex/{type}/create', [CodexEntryController::class, 'create'])
            ->name('projects.codex.create');
        Route::post('/projects/{project}/codex/{type}', [CodexEntryController::class, 'store'])
            ->name('projects.codex.store');
    });
    Route::get('/codex/{codexEntry}/edit', [CodexEntryController::class, 'edit'])->name('codex.edit');
    Route::get('/codex/{codexEntry}', [CodexEntryController::class, 'show'])->name('codex.show');
    Route::put('/codex/{codexEntry}', [CodexEntryController::class, 'update'])->name('codex.update');
    Route::delete('/codex/{codexEntry}', [CodexEntryController::class, 'destroy'])->name('codex.destroy');
    Route::post('/codex/{codexEntry}/duplicate', [CodexEntryController::class, 'duplicate'])->name('codex.duplicate');

    Route::resource('projects.codex-attributes', CodexAttributeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['codex-attributes' => 'codexAttribute'])
        ->shallow();

    // Tags are created inline on the codex form; this screen renames and removes
    // them, so it needs no create or edit page of its own.
    Route::resource('projects.tags', TagController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->shallow();

    // Store is an upsert, including for the Start baseline.
    Route::post('/codex/{codexEntry}/attributes/{codexAttribute}/values', [CodexAttributeValueController::class, 'store'])
        ->name('codex.attribute-values.store');
    Route::delete('/codex-attribute-values/{codexAttributeValue}', [CodexAttributeValueController::class, 'destroy'])
        ->name('codex.attribute-values.destroy');

    // Gate autosave by its registry and allow concurrent two-second debounces.
    Route::whereIn('entity', AutosavableFields::slugs())->middleware('throttle:120,1')->group(function () {
        Route::patch('/autosave/{entity}/{id}/{field}', [FieldAutosaveController::class, 'update'])
            ->name('autosave.update');
    });

    Route::get('/projects/{project}/revisions', [RevisionBrowserController::class, 'index'])
        ->name('projects.revisions.index');

    Route::get('/projects/{project}/progress', [ProgressController::class, 'index'])
        ->name('projects.progress');

    // Revision pages use the same entity registry as autosave.
    Route::whereIn('entity', AutosavableFields::slugs())->group(function () {
        Route::get('/revisions/{entity}/{id}', [RevisionController::class, 'index'])
            ->name('revisions.index');

        // Keep this before `{field}` so `compare` is not bound as a field.
        Route::get('/revisions/{entity}/{id}/compare', [RevisionController::class, 'compare'])
            ->name('revisions.compare');

        Route::get('/revisions/{entity}/{id}/{field}/compare', [RevisionController::class, 'fieldCompare'])
            ->name('revisions.field-compare');

        Route::get('/revisions/{entity}/{id}/{field}', [RevisionController::class, 'field'])
            ->name('revisions.field');
    });

    Route::post('/revisions/{revision}/revert', [RevisionController::class, 'revert'])
        ->name('revisions.revert');

    // Reject malformed save ULIDs before the controller runs.
    Route::post('/revisions/saves/{save}/revert', [RevisionController::class, 'revertSave'])
        ->where('save', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('revisions.saves.revert');
});

require __DIR__.'/auth.php';

// Start the web session before a 404 so the error page knows the user. `any` also
// prevents unmatched non-GET requests from becoming 405 responses.
Route::any('{unmatched}', fn () => abort(404))
    ->where('unmatched', '.*')
    ->fallback();
