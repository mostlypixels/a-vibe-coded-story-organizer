{{--
    The cards of one section of the right panel, as the page load and the refresh endpoint render them.
    Needs: $type ('codex' or 'note'), $cards, $scene and $project.
--}}
@foreach ($cards as $card)
    <x-editor-card
        :type="$card['type']"
        :id="$card['id']"
        :title="$card['title']"
        :state="$card['state']->value"
        :view-url="$card['viewUrl']"
    >
        @if ($type === 'codex')
            @include('editor.partials.codex-card', ['row' => $card['row'], 'undetermined' => $scene->event === null])
        @else
            @include('editor.partials.note-card', ['note' => $card['note']])
        @endif
    </x-editor-card>
@endforeach
