<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Resolves which project a request's route belongs to.
 *
 * Shallow child routes (e.g. /scenes/{scene}/edit) carry no {project} route
 * parameter, so this walks whatever model the route did bind back up to its
 * project. Extracted out of ProjectNavigation so the nav and
 * TrackActiveProject middleware agree on the answer — two copies of this walk
 * would drift the first time a route parameter is added.
 */
class RouteProject
{
    /**
     * The project the current route belongs to, or null outside a project.
     */
    public static function resolve(Request $request): ?Project
    {
        return $request->route('project')
            ?? $request->route('plotline')?->project
            ?? $request->route('event')?->project
            ?? $request->route('act')?->project
            ?? $request->route('chapter')?->act?->project
            ?? $request->route('scene')?->chapter?->act?->project
            ?? $request->route('codexEntry')?->project
            ?? $request->route('codexAttribute')?->project;
    }
}
