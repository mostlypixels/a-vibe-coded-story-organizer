<?php

use App\Http\Controllers\ActController;
use App\Http\Controllers\ChapterController;
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
});

require __DIR__.'/auth.php';
