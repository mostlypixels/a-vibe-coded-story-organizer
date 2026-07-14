<?php

namespace App\Providers;

use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Building HTMLPurifier is non-trivial; share one instance across the
        // per-field sanitization calls (wired in task 02) and the SanitizeHtml rule.
        $this->app->singleton(HtmlSanitizer::class);
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
    }
}
