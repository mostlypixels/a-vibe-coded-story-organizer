@props(['book', 'mode', 'sceneDisplay', 'chapterId' => null])

<form method="POST" action="{{ route('books.story.overview.mode', array_filter(['book' => $book, 'chapter' => $chapterId])) }}" class="flex items-center gap-2">
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

    <label for="overview-scene-display" class="sr-only">{{ __('Scene text') }}</label>
    <x-select
        id="overview-scene-display"
        name="overview_scene_display"
        onchange="this.form.requestSubmit()"
        class="text-sm"
    >
        @foreach (\App\Enums\StoryOverviewSceneDisplay::cases() as $case)
            <option value="{{ $case->value }}" @selected($case === $sceneDisplay)>{{ $case->label() }}</option>
        @endforeach
    </x-select>
</form>
