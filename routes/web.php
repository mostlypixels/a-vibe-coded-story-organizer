<?php

use App\Http\Controllers\ActController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\CodexAttributeController;
use App\Http\Controllers\CodexAttributeValueController;
use App\Http\Controllers\CodexEntryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PlotlineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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

    // Codex entries — nested index/create/store carry the {type} segment; edit/update/destroy
    // only need the entry, so they are flat (manual shallow nesting). Unknown {type} 404s
    // before the controller thanks to the whereIn constraint.
    Route::get('/projects/{project}/codex/{type}', [CodexEntryController::class, 'index'])
        ->whereIn('type', ['characters', 'locations', 'organizations'])
        ->name('projects.codex.index');
    Route::get('/projects/{project}/codex/{type}/create', [CodexEntryController::class, 'create'])
        ->whereIn('type', ['characters', 'locations', 'organizations'])
        ->name('projects.codex.create');
    Route::post('/projects/{project}/codex/{type}', [CodexEntryController::class, 'store'])
        ->whereIn('type', ['characters', 'locations', 'organizations'])
        ->name('projects.codex.store');
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
