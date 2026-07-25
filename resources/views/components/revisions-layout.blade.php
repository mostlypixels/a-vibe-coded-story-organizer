{{--
    The revisions-browser shell. Wraps <x-app-layout> so it inherits the top nav,
    the x-robots-meta head tag, and the shared max-w-7xl container, then lays out
    the revision sidebar (3 cols) beside the content pane (9 cols) — the same 9-3
    split the admin shell uses. An optional `header` slot is forwarded to
    x-app-layout's page-heading band.

    $tree / $project / $entity / $id / $field are the class-based component's
    public properties (see App\View\Components\RevisionsLayout); the active
    (entity, id, field) is nullable — the landing page renders with no selection.
--}}
<x-app-layout>
    @isset($header)
        <x-slot name="header">
            {{ $header }}
        </x-slot>
    @endisset

    {{-- Sidebar stacks above the content on mobile (semantic order already
         correct) and sits beside it on lg+. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-3">
            @include('revisions.partials.sidebar', [
                'tree' => $tree,
                'project' => $project,
                'activeEntity' => $entity,
                'activeId' => $id,
                'activeField' => $field,
            ])
        </div>

        <div class="lg:col-span-9 space-y-6">
            {{--
                Every revert redirects back to the page it was fired from, and
                both of those pages (history and compare) live in this shell —
                so the two outcomes are rendered once, here, rather than
                duplicated per page.

                `error` carries an App\Exceptions\RevisionConflictException's
                message: someone else moved the value while this page was open,
                which is a situation to act on, not a failure to apologise for.
            --}}
            @if (session('error'))
                <x-alert variant="danger" dismissible>{{ session('error') }}</x-alert>
            @endif

            @if (session('status') === 'reverted')
                <x-alert variant="success" dismissible>
                    {{ __('Reverted. That value is current again, and the revert was added to the history — nothing was removed.') }}
                </x-alert>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-app-layout>
