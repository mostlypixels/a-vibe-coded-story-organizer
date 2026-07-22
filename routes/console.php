<?php

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
