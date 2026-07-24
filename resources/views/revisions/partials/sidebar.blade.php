{{--
    The revisions-browser sidebar: every entity in this project that has
    revisions, grouped by type, with its revised fields (and their counts)
    beneath it. Built by App\Services\ProjectRevisionsBrowser — only entities
    and fields that actually have history appear here.

    Expects: $tree, $project, and the active $activeEntity / $activeId /
    $activeField (any may be null on the landing page).

    Sizing (handoff D2): a heavily-revised project can list hundreds of
    entities, so the sidebar is bounded three ways —
      1. each group heading carries a count badge of the revised entities it
         holds, so its size reads at a glance while collapsed;
      2. groups start collapsed by default; only the group holding the entity
         currently being viewed ($activeEntity) starts open;
      3. a client-side (Alpine) filter box narrows the list by entity name as
         you type — matching groups auto-expand, non-matching ones hide.
    The single `filter` state lives on the root <nav>; each group keeps its own
    `open` state and its lower-cased entity names for the match test. All match
    logic is plain Alpine expressions (never a method), so a child element can
    read the ancestor `filter` without the `this`-binding pitfall.
--}}
@php
    $activeClasses = 'border-flame-500 bg-aqua-50 text-navy-900 font-semibold';
    $inactiveClasses = 'border-transparent text-gray-700 hover:bg-gray-100 hover:text-navy-900';
@endphp

<nav aria-label="{{ __('Revision history') }}" class="text-sm" x-data="{ filter: '' }">
    <a
        href="{{ route('projects.show', $project) }}"
        class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-700 no-underline hover:underline"
    >
        <span aria-hidden="true">&larr;</span>
        <span class="truncate">{{ $project->name }}</span>
    </a>

    @if ($tree->isNotEmpty())
        <div class="mt-4">
            <label for="revision-filter" class="sr-only">{{ __('Filter revised items') }}</label>
            <input
                id="revision-filter"
                type="search"
                x-model="filter"
                placeholder="{{ __('Filter…') }}"
                autocomplete="off"
                class="w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm text-sm"
            >
        </div>
    @endif

    @forelse ($tree as $group)
        @php
            // Lower-cased entity names for this group, handed to the client-side
            // filter so typing narrows the list without a round-trip.
            $entityNames = $group->entities->map(fn ($entity) => \Illuminate\Support\Str::lower($entity->name))->values()->all();
            // Only the group holding the entity currently being viewed starts
            // open; every other group starts collapsed (D2, point 2).
            $startOpen = $activeEntity === $group->type;
        @endphp
        <div
            x-data="{ open: {{ $startOpen ? 'true' : 'false' }}, names: {{ \Illuminate\Support\Js::from($entityNames) }} }"
            x-show="filter.trim() === '' || names.some(name => name.includes(filter.trim().toLowerCase()))"
            class="mt-4"
        >
            <button
                type="button"
                @click="open = ! open"
                :aria-expanded="(filter.trim() !== '' || open) ? 'true' : 'false'"
                class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold uppercase tracking-wider text-gray-500 hover:text-gray-700"
            >
                <span class="flex items-center gap-2">
                    <span>{{ __($group->label) }}</span>
                    {{-- D2, point 1: how many revised entities this group holds. --}}
                    <x-badge>{{ $group->entities->count() }}</x-badge>
                </span>
                <svg class="h-4 w-4 fill-current transition-transform" :class="{ '-rotate-90': ! (filter.trim() !== '' || open) }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            {{-- Force-open while filtering so matches are always visible. --}}
            <ul x-show="filter.trim() !== '' || open" class="mt-1 space-y-2">
                @foreach ($group->entities as $treeEntity)
                    <li x-show="filter.trim() === '' || {{ \Illuminate\Support\Js::from(\Illuminate\Support\Str::lower($treeEntity->name)) }}.includes(filter.trim().toLowerCase())">
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
