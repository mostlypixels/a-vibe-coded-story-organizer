<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // A bare login lands on the active project rather than the dashboard.
        // `intended()` still wins for a user bounced off a deep link. Project
        // routes carry only `auth` (the dashboard also carries `verified`), so
        // this bypasses that check today — inert while `User` does not
        // implement `MustVerifyEmail`.
        $activeProject = $request->user()->activeProject;

        $fallback = $activeProject
            ? route('projects.show', $activeProject, absolute: false)
            : route('projects.index', absolute: false);

        return redirect()->intended($fallback);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
