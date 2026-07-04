{{--
    Read-only "as of" panel: every codex entry's attribute values resolved at one moment.
    Shared by scenes/edit and events/edit. All resolution is pre-computed by
    CodexAsOfResolver in the controller — this template only renders it.

    $title:  card heading (e.g. "Codex as of this scene" / "Values as of this event").
    $moment: the Event the values are resolved at, or null (an unassigned scene).
    $groups: collection of ['type' => CodexEntryType, 'entries' => [['entry' => CodexEntry,
             'attributes' => [['name' => string, 'value' => ?string]]]]].
--}}
<x-card>
    <details open>
        <summary class="cursor-pointer select-none font-semibold text-gray-800">
            {{ $title }}
        </summary>

        <div class="mt-4">
            @if ($moment === null)
                {{-- Mirrors the red-border unassigned-scene affordance: no event, no values. --}}
                <p class="text-sm text-gray-500">&mdash; {{ __('Assign an event to this scene to see codex values.') }}</p>
            @elseif ($groups->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No codex entries yet.') }}</p>
            @else
                <div class="space-y-6">
                    @foreach ($groups as $group)
                        <div>
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $group['type']->pluralLabel() }}</h4>
                            <div class="mt-2 space-y-3">
                                @foreach ($group['entries'] as $row)
                                    <div>
                                        <a href="{{ route('codex.edit', $row['entry']) }}" class="text-sm font-medium text-ocean-600 hover:text-ocean-800">{{ $row['entry']->name }}</a>
                                        @if ($row['attributes']->isNotEmpty())
                                            <dl class="mt-1 space-y-0.5">
                                                @foreach ($row['attributes'] as $attribute)
                                                    <div class="flex gap-2 text-sm">
                                                        <dt class="text-gray-500">{{ $attribute['name'] }}:</dt>
                                                        <dd class="text-gray-800">{{ filled($attribute['value']) ? $attribute['value'] : '—' }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </details>
</x-card>
