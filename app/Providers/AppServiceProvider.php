<?php

namespace App\Providers;

use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\RevisionRecorder;
use App\Support\AdminNavigation;
use App\Support\Breadcrumbs;
use App\Support\LocaleChoice;
use App\Support\PageTitle;
use App\Support\ProjectNavigation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // HTMLPurifier is costly to build. Share it across all fields in one request.
        $this->app->singleton(HtmlSanitizer::class);

        // One request must use one save ID for all revised fields.
        $this->app->scoped(RevisionRecorder::class);
    }

    public function boot(): void
    {
        // Configuration has no owning project. Any authenticated user can access it.
        Gate::define('access-admin', fn (User $user) => true);

        // The production proxy terminates TLS. Force secure URLs behind the proxy.
        if (! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        // A composer, not a bare View::share value: auth isn't resolved yet when
        // providers boot, only once the request reaches view rendering.
        View::composer('*', function ($view) {
            $view->with('locale', LocaleChoice::resolve(Auth::user()?->locale));
        });

        // Route context sets the title and breadcrumbs. Stored context sets the main navigation.
        View::composer(['layouts.navigation', 'layouts.app'], function ($view) {
            $navigation = new ProjectNavigation(request());

            $view->with('navigation', $navigation)
                ->with('pageTitle', new PageTitle($navigation->routeProject, $navigation->routeBook))
                // Pages outside a project use their header instead of breadcrumbs.
                ->with('breadcrumbs', new Breadcrumbs($navigation, request()));
        });

        // Error pages must not infer navigation from a failed route.
        View::composer('layouts.error-navigation', function ($view) {
            $view->with('navigation', ProjectNavigation::offRoute(request()->user()));
        });

        View::composer(['admin.partials.sidebar', 'admin.data.partials.subnav'], function ($view) {
            $view->with('adminNavigation', new AdminNavigation);
        });
    }
}
