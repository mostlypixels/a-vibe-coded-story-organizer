<x-app-layout>
    <x-page-heading>
        {{ __('Progress') }} &mdash; {{ $project->name }}
    </x-page-heading>

    <x-card>
        <div class="space-y-4">
            @if ($project->daily_word_goal !== null)
                <x-progress-bar :label="__('Today')" :value="$writtenToday" :goal="$project->daily_word_goal" />
            @endif

            @if ($project->total_word_goal !== null)
                <x-progress-bar :label="__('Total')" :value="$totalWords" :goal="$project->total_word_goal" />
            @endif

            @if ($project->daily_word_goal === null && $project->total_word_goal === null)
                <p class="text-content-muted">
                    {{ __('Set a daily or total word goal on the project form to track progress here.') }}
                </p>
            @endif
        </div>
    </x-card>

    <x-card class="mt-6">
        <form
            method="GET"
            action="{{ route('projects.progress', $project) }}"
            x-data="{ from: @js($from->toDateString()), to: @js($to->toDateString()) }"
            class="flex flex-wrap items-end gap-4"
        >
            <div class="space-y-1">
                <x-input-label for="month" :value="__('Month')" />
                <x-select
                    id="month"
                    x-on:change="
                        if ($event.target.value) {
                            const [monthFrom, monthTo] = $event.target.value.split('|');
                            from = monthFrom;
                            to = monthTo;
                        }
                    "
                >
                    <option value="">{{ __('Choose a month…') }}</option>
                    @foreach ($months as $month)
                        <option
                            value="{{ $month['from'] }}|{{ $month['to'] }}"
                            @selected($from->toDateString() === $month['from'] && $to->toDateString() === $month['to'])
                        >{{ $month['label'] }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="space-y-1">
                <x-input-label for="from" :value="__('From')" />
                <x-text-input id="from" type="date" name="from" x-model="from" />
                <x-input-error :messages="$errors->get('from')" />
            </div>

            <div class="space-y-1">
                <x-input-label for="to" :value="__('To')" />
                <x-text-input id="to" type="date" name="to" x-model="to" />
                <x-input-error :messages="$errors->get('to')" />
            </div>

            <x-button type="submit" variant="primary">{{ __('Apply') }}</x-button>
        </form>

        <div class="mt-6">
            @if (! $hasSnapshots)
                <div class="rounded-lg bg-surface-raised px-6 py-10 text-center text-content-muted">
                    <p class="font-medium text-content-muted">{{ __('No writing recorded yet.') }}</p>
                    <p class="mt-1 text-sm">{{ __('Save a scene and today’s words appear here.') }}</p>
                </div>
            @else
                <x-word-count-chart :series="$series" :daily-goal="$project->daily_word_goal" variant="full" />
            @endif
        </div>
    </x-card>
</x-app-layout>
