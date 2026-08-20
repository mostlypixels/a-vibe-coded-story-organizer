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
        <div class="flex items-center justify-between gap-4">
            <x-heading level="3">{{ __('Challenges') }}</x-heading>
            <x-button variant="primary" size="sm" :href="route('projects.challenges.create', $project)">{{ __('New challenge') }}</x-button>
        </div>

        @if ($runningChallenges->isEmpty() && $upcomingChallenges->isEmpty() && $pastChallenges->isEmpty())
            <p class="mt-4 text-content-muted">{{ __("You haven't started a challenge yet.") }}</p>
        @else
            <div class="mt-4 space-y-6">
                @if ($runningChallenges->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($runningChallenges as $pair)
                            <x-challenge-card :challenge="$pair['challenge']" :standing="$pair['standing']" />
                        @endforeach
                    </div>
                @endif

                @if ($upcomingChallenges->isNotEmpty())
                    <div>
                        <x-heading level="5">{{ __('Upcoming') }}</x-heading>
                        <ul class="mt-2 divide-y divide-border">
                            @foreach ($upcomingChallenges as $pair)
                                @php
                                    $challenge = $pair['challenge'];
                                    $standing = $pair['standing'];
                                    $parPerDay = (int) round($standing->target / $standing->totalDays);
                                @endphp
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                    <div>
                                        <span class="font-medium text-content">{{ $challenge->name }}</span>
                                        <x-challenge-window class="ml-2 text-content-muted" :window="$standing->window" :recurrence="$challenge->recurrence" />
                                    </div>
                                    <span class="text-content-muted">
                                        {{ __(':target words · :par a day', [
                                            'target' => number_format($standing->target),
                                            'par' => number_format($parPerDay),
                                        ]) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($pastChallenges->isNotEmpty())
                    <div>
                        <x-heading level="5">{{ __('Past') }}</x-heading>
                        <x-table class="mt-2">
                            <x-slot:head>
                                <x-table-heading>{{ __('Name') }}</x-table-heading>
                                <x-table-heading>{{ __('Window') }}</x-table-heading>
                                <x-table-heading>{{ __('Words') }}</x-table-heading>
                                <x-table-heading />
                            </x-slot:head>

                            @foreach ($pastChallenges as $pair)
                                @php
                                    $challenge = $pair['challenge'];
                                    $standing = $pair['standing'];
                                @endphp
                                <x-table-row :striped="$loop->even">
                                    <td class="px-4 py-3 font-medium text-content">{{ $challenge->name }}</td>
                                    <td class="px-4 py-3 text-sm text-content-muted">
                                        <x-challenge-window :window="$standing->window" :recurrence="$challenge->recurrence" />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-content-muted">
                                        <x-word-count :count="$standing->written" variant="inline" /> {{ __('of') }} <x-word-count :count="$standing->target" variant="inline" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :variant="$standing->met ? 'success' : 'danger'">
                                            {{ $standing->met ? __('Met') : __('Missed') }}
                                        </x-badge>
                                    </td>
                                </x-table-row>
                            @endforeach
                        </x-table>
                    </div>
                @endif
            </div>
        @endif
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
