@props(['force' => false])

@if ($force || \App\Models\CrawlerSetting::current()->isHidden())
    <meta name="robots" content="noindex, nofollow">
@endif
