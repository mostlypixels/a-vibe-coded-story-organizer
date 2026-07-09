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
use App\Http\Controllers\EventController;
use App\Http\Controllers\GeneralSettingsController;
use App\Http\Controllers\PlotlineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RobotsTxtController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneShareController;
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
        Route::get('/data', [DataTransferController::class, 'index'])->name('data.index');
        Route::get('/database', [DatabaseConfigurationController::class, 'edit'])->name('database.edit');
    });

    Route::resource('projects', ProjectController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::resource('projects.plotlines', PlotlineController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();

    Route::resource('projects.events', EventController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->shallow();

    Route::get('/projects/{project}/story', [StoryController::class, 'index'])->name('projects.story.index');

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
