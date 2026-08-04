{{--
    Read-only "as of" panel: every codex entry's attribute values resolved at one moment.
    Shared by scenes/edit and events/edit. All resolution is pre-computed by
    CodexAsOfResolver in the controller — this template only renders it.

    $title:  card heading (e.g. "Codex as of this scene" / "Values as of this event").
    $moment: the Event the values are resolved at, or null (an unassigned scene).
    $groups: collection of ['type' => CodexEntryType, 'entries' => [['entry' => CodexEntry,
             'attributes' => [['name' => string, 'value' => ?string]]]]].
--}}
<x-collapsible-card :title="$title">
    @if ($moment === null)
        {{-- Mirrors the red-border unassigned-scene affordance: no event, no values. --}}
        <p class="text-sm text-content-muted">&mdash; {{ __('Assign an event to this scene to see codex values.') }}</p>
    @elseif ($groups->isEmpty())
        <p class="text-sm text-content-muted">{{ __('No codex entries yet.') }}</p>
    @else
        <div x-data="{ activeTab: '{{ $groups->first()['type']->value }}' }">
            <div class="flex gap-4 border-b border-border" role="tablist">
                @foreach ($groups as $group)
                    <button
                        type="button"
                        role="tab"
                        x-on:click="activeTab = '{{ $group['type']->value }}'"
                        x-bind:aria-selected="activeTab === '{{ $group['type']->value }}'"
                        x-bind:class="activeTab === '{{ $group['type']->value }}' ? 'border-link text-link' : 'border-transparent text-content-muted hover:text-content'"
                        class="-mb-px border-b-2 px-1 pb-2 text-sm font-medium"
                    >
                        {{ $group['type']->pluralLabel() }}
                    </button>
                @endforeach
            </div>

            @foreach ($groups as $group)
                <div x-show="activeTab === '{{ $group['type']->value }}'" class="mt-4 space-y-3">
                    @foreach ($group['entries'] as $row)
                        <div>
                            <a href="{{ route('codex.edit', $row['entry']) }}" class="text-sm font-medium text-link hover:text-link-hover">{{ $row['entry']->name }}</a>
                            @if ($row['attributes']->isNotEmpty())
                                <dl class="mt-1 space-y-0.5">
                                    @foreach ($row['attributes'] as $attribute)
                                        <div class="flex gap-2 text-sm">
                                            <dt class="text-content-muted">{{ $attribute['name'] }}:</dt>
                                            <dd class="text-content">{{ filled($attribute['value']) ? $attribute['value'] : '—' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</x-collapsible-card>
