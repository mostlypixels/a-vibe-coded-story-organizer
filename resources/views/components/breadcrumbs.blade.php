@props(['items' => []])

@php
    // Data-driven breadcrumb trail. Pass an array of ['label' => …, 'url' => …];
    // items without a `url` (and the last item) render as plain text. Example:
    //   <x-breadcrumbs :items="[
    //       ['label' => __('Projects'), 'url' => route('dashboard')],
    //       ['label' => $project->name, 'url' => route('projects.show', $project)],
    //       ['label' => __('Acts')],
    //   ]" />
@endphp

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->merge(['class' => 'flex']) }}>
    <ol class="flex flex-wrap items-center gap-1 text-sm text-content-muted">
        @foreach ($items as $item)
            <li class="flex items-center gap-1">
                @if (! $loop->last && ! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-content">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif class="font-medium text-content">{{ $item['label'] }}</span>
                @endif

                @unless ($loop->last)
                    <x-tabler-chevron-right class="h-4 w-4 text-content-subtle" aria-hidden="true" />
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
