@props(['force' => false])

{{-- Emits <meta name="robots" content="noindex, nofollow"> when the site is
     hidden from crawlers, or when :force is set (link-only pages that must stay
     hidden regardless of the global toggle — see layouts/public). The content
     string lives only here, so all layouts stay in sync. --}}
@if ($force || \App\Models\CrawlerSetting::current()->isHidden())
    <meta name="robots" content="noindex, nofollow">
@endif
