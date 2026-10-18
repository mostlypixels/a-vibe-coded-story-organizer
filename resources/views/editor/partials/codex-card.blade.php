@php($entry = $row['entry'])

<div class="space-y-3">
    <div class="flex items-center gap-3">
        @if ($entry->cover?->url())
            <img src="{{ $entry->cover->url() }}" alt="{{ $entry->name }}" class="h-10 w-10 shrink-0 rounded-sm border border-border object-cover">
        @endif
        <x-badge>{{ $entry->type->label() }}</x-badge>
    </div>

    <x-rich-text :html="$entry->description" :project="$project" />

    @if ($undetermined)
        <p class="text-sm text-content-muted">&mdash; {{ __('Assign an event to this scene to see codex values.') }}</p>
    @elseif (! $row['present'])
        <p class="text-sm text-content-muted">{{ __('Not present at this scene\'s moment') }}</p>
    @else
        @if ($row['age'] !== null)
            <p class="text-sm text-content-muted">{{ __('Age :years', ['years' => $row['age']->years]) }}</p>
        @endif
        @if ($row['attributes']->isNotEmpty())
            <dl class="space-y-0.5">
                @foreach ($row['attributes'] as $attribute)
                    <div class="flex gap-2 text-sm">
                        <dt class="text-content-muted">{{ $attribute['name'] }}:</dt>
                        <dd class="text-content">{{ $attribute['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    @endif
</div>
