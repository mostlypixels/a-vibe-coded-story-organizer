{{--
    The dashboard "Today" figure. It follows each autosave: the figure at load
    plus the change of the scene word count since the load.
--}}
@php
    $goal = $project->daily_word_goal;
    $percent = $goal > 0 ? max(0, min(100, (int) round($writtenToday / $goal * 100))) : 0;
@endphp

<div
    x-data="editorToday({
        today: {{ (int) $writtenToday }},
        sceneCount: {{ (int) $scene->word_count }},
        goal: @js($goal),
        templates: @js(\App\Support\WordCountFormat::jsTemplates()),
    })"
    data-editor-today="{{ $writtenToday }}"
>
    @if ($goal !== null)
        <div class="flex items-baseline justify-between gap-4 text-sm">
            <span class="font-medium text-content">{{ __('Today') }}</span>
            <span class="text-content-muted">
                <x-word-count :count="$writtenToday" variant="inline" x-text="writtenText()" /> {{ __('of') }} <x-word-count :count="$goal" variant="inline" />
            </span>
        </div>
        <div class="mt-1 h-2 rounded-full bg-border" role="progressbar" aria-label="{{ __('Today') }}" aria-valuenow="{{ $writtenToday }}" :aria-valuenow="written" aria-valuemin="0" aria-valuemax="{{ $goal }}" data-editor-today-goal>
            <div class="h-2 rounded-full bg-accent" style="width: {{ $percent }}%" :style="`width: ${percent()}%`"></div>
        </div>
    @else
        <p class="text-sm">
            <span class="font-medium text-content">{{ __('Today') }}</span>
            <x-word-count :count="$writtenToday" variant="inline" class="text-content-muted" x-text="writtenText()" />
        </p>
    @endif
</div>
