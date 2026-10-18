{{-- `project` scopes the `entity:` links: a link never resolves into another project. --}}
@props(['html' => null, 'project'])

@if (filled($html))
    <div {{ $attributes->merge(['class' => 'prose prose-sm max-w-none text-content-muted']) }}>
        {!! app(\App\Services\EntityLinkResolver::class)->render($html, $project) !!}
    </div>
@endif
