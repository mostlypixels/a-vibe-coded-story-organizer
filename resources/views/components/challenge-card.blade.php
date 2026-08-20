@props(['challenge', 'standing'])

{{-- One running challenge. Reads $standing only; every figure is already
     computed by App\Services\ChallengeProgress. --}}
<x-card>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="font-semibold text-content">{{ $challenge->name }}</h3>
            <x-challenge-window class="text-sm text-content-muted" :window="$standing->window" :recurrence="$challenge->recurrence" />
        </div>
        <div class="flex items-center gap-1">
            <x-icon-edit-link :href="route('challenges.edit', $challenge)" />
            <x-icon-delete-button :action="route('challenges.destroy', $challenge)" :confirm="__('Are you sure you want to delete this challenge?')" />
        </div>
    </div>

    <div class="mt-4 space-y-2">
        <x-progress-bar :value="$standing->written" :goal="$standing->target" :label="__('Written')" />

        <p class="text-lg font-semibold {{ $standing->delta >= 0 ? 'text-accent' : 'text-danger' }}">
            @if ($standing->delta >= 0)
                {{ __('Ahead by') }} <x-word-count :count="$standing->delta" variant="inline" />
            @else
                {{ __('Behind by') }} <x-word-count :count="abs($standing->delta)" variant="inline" />
            @endif
        </p>

        <p class="text-sm text-content-muted">
            {{ __('Par today :par', ['par' => number_format($standing->par)]) }}
            &middot;
            {{ trans_choice('{1} :count day left|[2,*] :count days left', $standing->daysLeft, ['count' => $standing->daysLeft]) }}
            &middot;
            @if ($standing->remaining > 0 && $standing->perDayNeeded !== null)
                {{ __(':count words a day to finish', ['count' => number_format($standing->perDayNeeded)]) }}
            @else
                {{ __('Target reached') }}
            @endif
        </p>
    </div>

    <x-challenge-chart class="mt-4" :standing="$standing" />
</x-card>
