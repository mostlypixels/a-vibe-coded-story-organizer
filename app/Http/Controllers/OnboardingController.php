<?php

namespace App\Http\Controllers;

use App\Enums\Genre;
use App\Http\Requests\StoreOnboardingRequest;
use App\Services\InstallsDemoProjects;
use App\Services\SeedsGenreBundle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The first-project prompt. `projects.index` redirects an account with no
 * projects here, so a new writer starts with one clear call to action.
 *
 * A returning writer with projects has nothing to onboard: `show` bounces
 * them back to the project list.
 *
 * The acting user is always `$request->user()` — this route never takes a
 * user id from input, since a signed-in writer only ever seeds for
 * themselves.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->projects()->exists()) {
            return redirect()->route('projects.index');
        }

        return view('onboarding');
    }

    public function store(StoreOnboardingRequest $request, SeedsGenreBundle $action): RedirectResponse
    {
        $project = $action->seed(
            $request->user(),
            Genre::from($request->string('genre')->toString()),
            $request->string('name')->toString(),
        );

        return redirect()->route('projects.show', $project)->with('status', 'onboarding-seeded');
    }

    public function installDemo(Request $request, InstallsDemoProjects $action): RedirectResponse
    {
        $action->install($request->user());

        return redirect()->route('projects.index');
    }
}
