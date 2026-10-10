{{-- A note's links, grouped by type. `$editable` adds an unlink button to each row. --}}

@if ($groups === [])
    <p class="text-sm text-content-muted">{{ __('This note has no links yet.') }}</p>
@else
    <dl class="space-y-4">
        @foreach ($groups as $group)
            <div>
                <dt class="text-sm font-medium text-content-muted">{{ __($group['type']->label()) }}</dt>
                <dd>
                    <ul class="mt-1 space-y-1">
                        @foreach ($group['items'] as $item)
                            <li class="flex items-center justify-between gap-2" data-note-link="{{ $group['type']->value }}:{{ $item->getKey() }}">
                                <a href="{{ route($group['type']->showRoute(), $item) }}" class="text-link hover:text-link-hover">{{ $group['type']->labelFor($item) }}</a>
                                @if ($editable ?? false)
                                    <x-icon-button
                                        type="button"
                                        icon="unlink"
                                        variant="ghost"
                                        :label="__('Unlink')"
                                        x-bind:disabled="busy"
                                        x-on:click="unlink({{ Js::from(route('notes.links.destroy', ['note' => $note, 'type' => $group['type']->value, 'id' => $item->getKey()])) }})"
                                    />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </dd>
            </div>
        @endforeach
    </dl>
@endif
