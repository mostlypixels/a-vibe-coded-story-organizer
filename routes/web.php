<?php

use App\Enums\CodexEntryType;
use App\Http\Controllers\ActController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CodexAttributeController;
use App\Http\Controllers\CodexAttributeValueController;
use App\Http\Controllers\CodexEntryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseConfigurationController;
use App\Http\Controllers\DataTransferController;
use App\Http\Controllers\EpubExportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportSettingController;
use App\Http\Controllers\PlotlineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RobotsTxtController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SharedSceneController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// PUBLIC scene share view — deliberately OUTSIDE the auth group. The opaque
// share_token is the only gate (no policy, no login); the controller resolves
// the token manually so an unknown token 404s and an expired one renders a
// friendly 410 page. This is the sole unauthenticated app route besides
// `welcome` and the Breeze auth screens — do not widen the auth group below.
Route::get('/shared/scenes/{token}', [SharedSceneController::class, 'show'])
    ->name('shared.scenes.show');

// PUBLIC dynamic robots.txt — rendered live from the crawler settings singleton.
// Deliberately OUTSIDE the auth group (crawlers are anonymous). The static
// public/robots.txt was removed so this route is reached — a physical file in
// public/ shadows the route under `artisan serve` and nginx `try_files`.
Route::get('/robots.txt', RobotsTxtController::class)->name('robots.txt');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin Configuration area. Every section sits behind `auth` (this group) AND
    // the single `access-admin` Gate — the deliberate continuation of the
    // CrawlerSetting any-authenticated-user posture, encoded once here so it can
    // be tightened later without touching controllers. These routes are owned by
    // no Project, so they do NOT use ProjectPolicy's walk.
    Route::middleware('can:access-admin')->prefix('admin')->name('admin.')->group(function () {
        // Landing -> first section.
        Route::get('/', fn () => redirect()->route('admin.settings.edit'))->name('index');

        // General settings = the relocated search-engine (crawler) form. The
        // global crawler policy is a singleton owned by no Project; any
        // authenticated user may edit it (the access-admin posture above),
        // validated by UpdateCrawlerSettingRequest.
        Route::get('/settings', [GeneralSettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [GeneralSettingsController::class, 'update'])->name('settings.update');

        Route::get('/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');

        // The Export & import area is three server-rendered pages (not JS tabs),
        // reached by ordinary <a> links in admin/data/partials/subnav.blade.php.
        // /data itself only redirects to the first sub-page; the name is kept so
        // existing route('admin.data.index') callers (the sidebar link, POST
        // controllers that redirect back to "the area" generically) keep working.
        Route::get('/data', fn () => redirect()->route('admin.data.export-project'))->name('data.index');
        Route::get('/data/export/project', [DataTransferController::class, 'exportProject'])->name('data.export-project');
        Route::get('/data/export/ebook', [DataTransferController::class, 'exportEbook'])->name('data.export-ebook');
        Route::get('/data/import', [DataTransferController::class, 'import'])->name('data.import.index');

        // Export a project to a downloadable .zip. POST (not GET): a non-idempotent,
        // potentially expensive action that produces a download and carries CSRF +
        // the include_images toggle. The admin gate is "any authenticated user"; the
        // controller ALSO authorizes('view', $project) so a foreign project_id 403s.
        Route::post('/data/export', [ExportController::class, 'store'])->name('data.export');
        // Export a project as a downloadable .epub. Same POST posture and ownership
        // guard as the .zip export above; the EpubExporter owns tree filtering,
        // rendering, packaging, and structural validation. A project with nothing to
        // export redirects back with an error (EpubExportException), never a 500.
        Route::post('/data/export/epub', [EpubExportController::class, 'store'])->name('data.export.epub');

        // Import a project from an uploaded export .zip. Each import creates a
        // BRAND-NEW project for the current user (never merges/updates), so store
        // uses the any-authenticated-user gate (no existing project to walk up to,
        // like CrawlerSetting). resume/destroy act on an existing Import row that
        // DOES have an owner, so they use the real ImportPolicy — the two postures
        // are intentional and must not be collapsed.
        Route::post('/data/import', [ImportController::class, 'store'])->name('data.import');
        Route::post('/data/imports/{import}/resume', [ImportController::class, 'resume'])->name('data.imports.resume');
        Route::delete('/data/imports/{import}', [ImportController::class, 'destroy'])->name('data.imports.destroy');
        // Global import settings (archive size cap + background mode) — a singleton
        // owned by no Project, edited the same any-authenticated-user way as the
        // crawler settings (see ImportSettingController / UpdateImportSettingRequest).
        Route::patch('/data/import-settings', [ImportSettingController::class, 'update'])->name('data.import-settings');

        Route::get('/database', [DatabaseConfigurationController::class, 'edit'])->name('database.edit');
    });

    Route::resource('projects', ProjectController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    // Manual rebuild of the derived scene_codex_entry pivot for one project — a footer
    // form on the edit page, separate from the main project-fields form (see
    // ProjectController::syncCodexReferences).
    Route::post('/projects/{project}/codex-references/sync', [ProjectController::class, 'syncCodexReferences'])
        ->name('projects.codex-references.sync');

    Route::resource('projects.plotlines', PlotlineController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();

    Route::resource('projects.events', EventController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();

    Route::get('/projects/{project}/story', [StoryController::class, 'index'])->name('projects.story.index');

    // Full-text-ish search across one project's six searchable entities. Single
    // GET action (no AJAX): q/mode round-trip via the query string. Authorizes
    // via ProjectPolicy::view (SearchController + SearchRequest).
    Route::get('/projects/{project}/search', [SearchController::class, 'index'])->name('projects.search.index');

    Route::resource('projects.acts', ActController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::patch('/acts/{act}/move-up', [ActController::class, 'moveUp'])->name('acts.move-up');
    Route::patch('/acts/{act}/move-down', [ActController::class, 'moveDown'])->name('acts.move-down');

    Route::resource('projects.chapters', ChapterController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::patch('/chapters/{chapter}/move-up', [ChapterController::class, 'moveUp'])->name('chapters.move-up');
    Route::patch('/chapters/{chapter}/move-down', [ChapterController::class, 'moveDown'])->name('chapters.move-down');

    Route::resource('projects.scenes', SceneController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();
    Route::patch('/scenes/{scene}/move-up', [SceneController::class, 'moveUp'])->name('scenes.move-up');
    Route::patch('/scenes/{scene}/move-down', [SceneController::class, 'moveDown'])->name('scenes.move-down');

    // Owner-facing scene share link management. Flat on {scene} (implicit binding),
    // matching the shallow scene routes. store generates/rotates the link; destroy
    // revokes it. The public view route (shared.scenes.show) lives outside this auth
    // group — see task 03.
    Route::post('/scenes/{scene}/share', [SceneShareController::class, 'store'])->name('scenes.share.store');
    Route::delete('/scenes/{scene}/share', [SceneShareController::class, 'destroy'])->name('scenes.share.destroy');

    // Codex entries — nested index/create/store carry the {type} segment; edit/update/destroy
    // only need the entry, so they are flat (manual shallow nesting). The grouped whereIn
    // constraint (derived from CodexEntryType) makes an unknown {type} 404 before the
    // controller runs; keeping it local avoids binding {type} app-wide via a global pattern.
    Route::whereIn('type', CodexEntryType::routeKeys())->group(function () {
        Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
            ->name('projects.codex.index');
        Route::get('/projects/{project}/codex/{type}/create', [CodexEntryController::class, 'create'])
            ->name('projects.codex.create');
        Route::post('/projects/{project}/codex/{type}', [CodexEntryController::class, 'store'])
            ->name('projects.codex.store');
    });
    Route::get('/codex/{codexEntry}/edit', [CodexEntryController::class, 'edit'])->name('codex.edit');
    Route::put('/codex/{codexEntry}', [CodexEntryController::class, 'update'])->name('codex.update');
    Route::delete('/codex/{codexEntry}', [CodexEntryController::class, 'destroy'])->name('codex.destroy');

    // Attribute definitions — project-scoped resource, shallow (edit/update/destroy
    // only need the attribute). The camelCase parameter matches the {codexEntry}
    // convention so implicit binding resolves CodexAttribute $codexAttribute.
    Route::resource('projects.codex-attributes', CodexAttributeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['codex-attributes' => 'codexAttribute'])
        ->shallow();

    // Attribute timeline periods. store is an upsert (posting an existing anchor updates
    // its value — this is also how the Start baseline is edited, hence no update route);
    // destroy removes a period. Both live in App\Services\AttributeTimeline.
    Route::post('/codex/{codexEntry}/attributes/{codexAttribute}/values', [CodexAttributeValueController::class, 'store'])
        ->name('codex.attribute-values.store');
    Route::delete('/codex-attribute-values/{codexAttributeValue}', [CodexAttributeValueController::class, 'destroy'])
        ->name('codex.attribute-values.destroy');
});

require __DIR__.'/auth.php';
