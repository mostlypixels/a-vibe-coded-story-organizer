@props(['chapter', 'numbering'])

<x-collapsible-card>
    <x-slot:header>
        <div class="flex items-center justify-between gap-4">
            <h3 id="chapter-{{ $chapter->id }}" class="text-xl font-semibold text-content scroll-mt-16">
                {{ __('Chapter :number', ['number' => $numbering->chapter($chapter)]) }} &mdash; {{ $chapter->name }}
            </h3>
            <x-word-count :count="$chapter->scenes->sum('word_count')" class="shrink-0" />
        </div>
    </x-slot:header>

    <div class="space-y-4">
        @forelse ($chapter->scenes as $scene)
            <section x-data="{ open: true }" @unless($scene->event) title="{{ __('This scene has no “happens during” event yet.') }}" @endunless class="space-y-2 pb-4 border-b border-border last:border-b-0 last:pb-0 {{ $scene->event ? '' : 'border-l-4 border-l-danger pl-4' }}">
                <div class="flex items-center justify-between">
                    <button type="button" @click="open = ! open" class="flex items-center gap-2 text-sm font-light text-content-muted">
                        <x-tabler-chevron-down class="h-4 w-4 text-content-muted transition-transform" x-bind:class="{ 'rotate-180': open }" />
                        <span data-scene-number class="text-content-subtle">{{ $numbering->scene($scene) }}.</span>
                        {{ $scene->name }}
                    </button>

                    <div class="flex items-center gap-2">
                        @if ($scene->event)
                            <span class="text-xs text-content-muted">{{ __('Set during') }} {{ $scene->event->title }}</span>
                        @else
                            <span title="{{ __('This scene has no “happens during” event yet.') }}" class="inline-flex items-center rounded-md border border-danger px-2 py-0.5 text-xs font-medium text-danger-surface-content">{{ __('Unassigned') }}</span>
                        @endif
                        <x-scene-status-badge :status="$scene->status" />
                        <x-icon-button
                            type="button"
                            variant="ghost"
                            icon="chevron-up"
                            :label="__('Move up')"
                            data-move="up"
                            onclick="moveScene(this, '{{ route('scenes.move-up', $scene) }}', 'up')"
                            :disabled="$loop->first"
                        />
                        <x-icon-button
                            type="button"
                            variant="ghost"
                            icon="chevron-down"
                            :label="__('Move down')"
                            data-move="down"
                            onclick="moveScene(this, '{{ route('scenes.move-down', $scene) }}', 'down')"
                            :disabled="$loop->last"
                        />
                        <x-icon-view-link :href="route('scenes.show', $scene)" />
                        <x-icon-edit-link :href="route('scenes.edit', $scene)" />
                    </div>
                </div>

                <x-scene-prose :scene="$scene" x-show="open" x-transition class="text-[0.8125rem]" />
            </section>
        @empty
            <p class="text-sm text-content-muted">{{ __('No scenes in this chapter yet.') }}</p>
        @endforelse
    </div>
</x-collapsible-card>
