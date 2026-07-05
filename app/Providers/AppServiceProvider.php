<?php

namespace App\Providers;

use App\Services\HtmlSanitizer;
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
        //
    }
}
