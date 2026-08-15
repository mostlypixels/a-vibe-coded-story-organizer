@props(['project', 'mode', 'chapterId' => null])

{{--
    Owner-only control that PATCHes `projects.story.overview.mode`. A labelled
    <select> submitting on change, rather than a link per option, so the
    choice is one accessible form control instead of two buttons that must be
    kept in sync with the current value.

    The option list comes from StoryOverviewMode::cases()/label() — never
    hard-coded strings here — so a third mode needs no Blade change.
--}}
<form method="POST" action="{{ route('projects.story.overview.mode', array_filter(['project' => $project, 'chapter' => $chapterId])) }}">
    @csrf
    @method('PATCH')

    <label for="overview-render-mode" class="sr-only">{{ __('Story overview display') }}</label>
    <x-select
        id="overview-render-mode"
        name="overview_render_mode"
        onchange="this.form.requestSubmit()"
        class="text-sm"
    >
        @foreach (\App\Enums\StoryOverviewMode::cases() as $case)
            <option value="{{ $case->value }}" @selected($case === $mode)>{{ $case->label() }}</option>
        @endforeach
    </x-select>
</form>
