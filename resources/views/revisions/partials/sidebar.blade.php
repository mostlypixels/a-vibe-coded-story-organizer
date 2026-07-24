{{--
    The revisions-browser sidebar: every entity in this project that has
    revisions, grouped by type, with its revised fields (and their counts)
    beneath it. Built by App\Services\ProjectRevisionsBrowser — only entities
    and fields that actually have history appear here.

    Expects: $tree, $project, and the active $activeEntity / $activeId /
    $activeField (any may be null on the landing page). Group headings are
    collapsible (Alpine, default open), matching the app's existing Alpine nav.
--}}
@php
    $activeClasses = 'border-flame-500 bg-aqua-50 text-navy-900 font-semibold';
    $inactiveClasses = 'border-transparent text-gray-700 hover:bg-gray-100 hover:text-navy-900';
@endphp

<nav aria-label="{{ __('Revision history') }}" class="text-sm">
    <a
        href="{{ route('projects.show', $project) }}"
        class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-700 no-underline hover:underline"
    >
        <span aria-hidden="true">&larr;</span>
        <span class="truncate">{{ $project->name }}</span>
    </a>

    @forelse ($tree as $group)
        <div x-data="{ open: true }" class="mt-4">
            <button
                type="button"
                @click="open = ! open"
                :aria-expanded="open ? 'true' : 'false'"
                class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold uppercase tracking-wider text-gray-500 hover:text-gray-700"
            >
                <span>{{ __($group->label) }}</span>
                <svg class="h-4 w-4 fill-current transition-transform" :class="{ '-rotate-90': ! open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            <ul x-show="open" class="mt-1 space-y-2">
                @foreach ($group->entities as $treeEntity)
                    <li>
                        <div class="px-2 py-1 font-medium text-gray-800 truncate" title="{{ $treeEntity->name }}">
                            {{ $treeEntity->name }}
                        </div>

                        <ul class="ms-2 border-s border-gray-200">
                            @foreach ($treeEntity->fields as $leaf)
                                @php
                                    $isActive = $activeEntity === $leaf->entity
                                        && $activeId === $treeEntity->id
                                        && $activeField === $leaf->field;
                                @endphp
                                <li>
                                    <a
                                        href="{{ $leaf->url }}"
                                        @if ($isActive) aria-current="page" @endif
                                        class="flex items-center justify-between gap-2 ps-3 pe-2 py-1 border-s-2 no-underline hover:no-underline {{ $isActive ? $activeClasses : $inactiveClasses }}"
                                    >
                                        <span class="truncate">{{ $leaf->label }}</span>
                                        <x-badge>{{ $leaf->count }}</x-badge>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="mt-4 text-gray-500">{{ __('No revisions recorded in this project yet.') }}</p>
    @endforelse
</nav>
