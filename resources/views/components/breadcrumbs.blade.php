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
    <ol class="flex flex-wrap items-center gap-1 text-sm text-gray-500">
        @foreach ($items as $item)
            <li class="flex items-center gap-1">
                @if (! $loop->last && ! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-gray-700">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif class="font-medium text-gray-700">{{ $item['label'] }}</span>
                @endif

                @unless ($loop->last)
                    <svg class="h-4 w-4 text-gray-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
