<?php

namespace App\Providers;

use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\RevisionRecorder;
use App\Support\AdminNavigation;
use App\Support\Breadcrumbs;
use App\Support\PageTitle;
use App\Support\ProjectNavigation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Building HTMLPurifier is non-trivial; share one instance across the
        // per-field sanitization calls and the SanitizeHtml rule.
        $this->app->singleton(HtmlSanitizer::class);

        // One recorder per request, so its save-point memo (RevisionRecorder::
        // currentSaveId()) survives across the several record() calls one
        // request makes. Without this binding every `app(RevisionRecorder::
        // class)` and every method injection resolves a *fresh* object, and
        // each field of one form submit would be stamped with its own save id
        // — i.e. the grouping the whole history feature is built on would
        // silently degrade back to one-save-point-per-field. Load-bearing;
        // covered by RevisionSaveGroupingTest.
        $this->app->scoped(RevisionRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin Configuration access. This deliberately continues the
        // CrawlerSetting "any authenticated user" posture (documentation/
        // architecture.md -> Hidden from crawlers): the /admin area is owned by
        // no Project, so it does NOT use ProjectPolicy's walk. Encoding it here
        // as a single Gate (applied as `can:access-admin` on the route group)
        // keeps the check in ONE place — tightening it later (e.g. an is_admin
        // role) is a one-line change instead of editing every admin controller.
        Gate::define('access-admin', fn (User $user) => true);

        // Staging/production sit behind a proxy that terminates TLS, so Laravel
        // sees the request as plain http unless told otherwise; force https so
        // generated URLs (route(), asset(), password reset links, etc.) and the
        // session cookie's Secure flag are correct instead of silently downgrading.
        if (! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        // The primary nav needs the current project and the active section on
        // every authenticated page. Computing it here — once per render, in one
        // place — is what lets layouts/navigation.blade.php stay markup-only and
        // keeps its desktop and responsive menus provably in step.
        //
        // layouts.app gets the same treatment for its <title>, off the same
        // object so project resolution stays in one place — but off
        // `routeProject`/`routeBook`, NOT `project`/`book`. The title answers
        // "what is on this page"; the nav answers "what am I working on".
        // Since active-project persistence those differ: `project` and `book`
        // fall back to the account's stored context, and building the title
        // from them would silently retitle the dashboard, /profile and every
        // Configuration page "<project> - <app>", making the dashboard tab
        // indistinguishable from the project's own.
        View::composer(['layouts.navigation', 'layouts.app'], function ($view) {
            $navigation = new ProjectNavigation(request());

            $view->with('navigation', $navigation)
                ->with('pageTitle', new PageTitle($navigation->routeProject, $navigation->routeBook))
                // Off `routeProject` too (Breadcrumbs enforces it): the trail is
                // empty off-project, so the layout band falls back to the page's
                // own `header` slot. See documentation/architecture.md → Breadcrumbs.
                ->with('breadcrumbs', new Breadcrumbs($navigation, request()));
        });

        // The error pages carry a reduced bar. It gets its own composer because
        // its nav must ignore the route — see ProjectNavigation::offRoute().
        View::composer('layouts.error-navigation', function ($view) {
            $view->with('navigation', ProjectNavigation::offRoute(request()->user()));
        });

        // Same deal for the Configuration area's two link lists.
        View::composer(['admin.partials.sidebar', 'admin.data.partials.subnav'], function ($view) {
            $view->with('adminNavigation', new AdminNavigation);
        });
    }
}
