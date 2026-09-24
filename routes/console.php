<?php

use App\Console\Commands\BackupDatabase;
use App\Models\Revision;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The autosave-with-revisions feature's safety-preserving daily sweep (never
// touches labeled or non-automatic rows — see Revision::prunable()). This is
// distinct from the explicit, user-requested `revisions:purge` command
// (App\Console\Commands\PurgeRevisions), which is allowed to remove rows this
// prune never will.
Schedule::command('model:prune', ['--model' => [Revision::class]])->daily();

// Temporary export files are deleted after the download streams. The ones whose
// download never streamed (an aborted request) have nothing else to remove them.
Schedule::command('exports:purge')->daily();

// An abandoned import keeps its ZIP and extracted folder until someone resumes
// or discards it. Account deletion leaves them with no import row at all.
Schedule::command('imports:purge')->daily();

// The whole app is one SQLite file. A snapshot every few hours limits what a bad
// migration or a corrupt write can take. A failed run exits non-zero, so it is visible.
Schedule::command('db:backup')->cron(BackupDatabase::cronEvery((int) config('backup.every_hours')));
