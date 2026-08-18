@props(['book', 'mode', 'chapterId' => null])

<form method="POST" action="{{ route('books.story.overview.mode', array_filter(['book' => $book, 'chapter' => $chapterId])) }}">
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
