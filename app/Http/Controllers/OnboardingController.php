<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The first-project prompt. `projects.index` redirects an account with no
 * projects here, so a new writer starts with one clear call to action.
 *
 * A returning writer with projects has nothing to onboard: this bounces them
 * back to the project list.
 */
class OnboardingController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->projects()->exists()) {
            return redirect()->route('projects.index');
        }

        return view('onboarding');
    }
}
