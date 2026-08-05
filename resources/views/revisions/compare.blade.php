@php
    use Illuminate\Support\Str;
@endphp

<x-revisions-layout :project="$project" :entity="$entity" :id="$id" :field="$field">
    {{-- Breadcrumb-band exception (RevisionController class docblock): this
         route binds {entity}+{id}, not {project}, so the central builder
         yields an empty trail and the controller hands us a finished one. --}}
    <x-slot name="header">
        <div class="min-w-0">
            <x-breadcrumbs :items="$breadcrumbTrail" />
        </div>
    </x-slot>

    {{-- x-page-heading has no actions affordance (see resolution-log.md), so
         — same as projects/show.blade.php's dashboard heading row — this uses
         x-heading directly in a flex row rather than double up on margin. --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <x-heading level="1">{{ $heading }}</x-heading>
        <a href="{{ route('revisions.index', ['entity' => $entity, 'id' => $id, 'field' => $field]) }}" class="text-sm text-content-muted hover:text-content shrink-0">
            {{ __('Back to history') }}
        </a>
    </div>

    <div class="space-y-6">
        @if ($from === null || $to === null)
            {{-- Fewer than two save points: there is no pair to be had yet. --}}
            <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                <p class="font-medium text-content-muted">{{ __('Nothing to compare yet.') }}</p>
                <p class="mt-1 text-sm">{{ __('This entity needs at least two saves before they can be compared.') }}</p>
            </div>
        @else
            {{-- Left is always the older side, right the newer. The invalid
                 pairing is made *unreachable* — x-revision-picker disables every
                 option not strictly newer than the older selection, server-side,
                 so it holds with JS off too. There is no backwards diff and no
                 error state to design.

                 Each picker is a native <select> that the Alpine combobox
                 replaces once it mounts; the surrounding form is what makes the
                 no-JS baseline work. --}}
            <form method="GET" class="bg-surface-raised shadow-xs rounded-lg px-6 py-4">
                @if ($field !== null)
                    <input type="hidden" name="field" value="{{ $field }}">
                @endif

                @php
                    // The pair the page is currently showing. Each picker writes
                    // *both* halves when it navigates: a reader who arrived with
                    // no explicit pair is looking at the defaulted two most
                    // recent points, and writing only the chosen side would let
                    // the other default straight back — the selection would
                    // appear to do nothing.
                    $pair = ['from' => $from->saveId, 'to' => $to->saveId];
                @endphp

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-revision-picker side="from" :label="__('Older')" :points="$points" :selected="$from" :pair="$pair" />
                    <x-revision-picker side="to" :label="__('Newer')" :points="$points" :selected="$to" :pair="$pair" :disabled-before="$from" />
                </div>

                <div class="mt-4 flex items-center justify-between gap-4">
                    <p class="text-sm text-content-muted">
                        {{ trans_choice('{0}The same save|{1}1 save apart|[2,*]:count saves apart', $savesApart, ['count' => $savesApart]) }}
                        &middot;
                        {{ $from->savedAt->format('d F Y H:i') }} &rarr; {{ $to->savedAt->format('d F Y H:i') }}
                    </p>
                    <x-button variant="secondary" type="submit">{{ __('Compare') }}</x-button>
                </div>
            </form>

            @if ($field !== null)
                <p class="text-sm text-content-muted">
                    {{ __('Showing :field only.', ['field' => Str::headline($field)]) }}
                    <a
                        href="{{ route('revisions.compare', ['entity' => $entity, 'id' => $id, 'from' => $from->saveId, 'to' => $to->saveId]) }}"
                        class="text-link hover:text-link-hover hover:underline"
                    >{{ __('Show all fields') }}</a>
                </p>
            @endif

            @forelse ($comparisons as $comparison)
                {{-- One section per changed field, in registry order. An <article>
                     with its own heading, so the page reads as a list of things
                     that changed rather than as one undifferentiated diff. --}}
                <article aria-labelledby="diff-{{ $comparison->field }}">
                    <x-card>
                        <x-slot name="header">
                            {{-- Names the whole comparison, not just the field:
                                 a bare "Description" left the reader to work out
                                 what they were looking at, and which of the
                                 seven entity types it belonged to. --}}
                            <x-heading level="3" id="diff-{{ $comparison->field }}">
                                {{ __("Comparing changes to :entity field ':field'", [
                                    'entity' => Str::headline($entity),
                                    'field' => Str::headline($comparison->field),
                                ]) }}
                                @if ($comparison->isNewField())
                                    <x-badge variant="success">{{ __('New') }}</x-badge>
                                @endif
                            </x-heading>
                        </x-slot>

                        {{-- Three panes, one shell: what moved, what it was,
                             what it became. The diff is labelled like the two
                             columns are, so it reads as one of the three things
                             being shown rather than as loose text above them.

                             "Revert to this" lives under the version it restores
                             — in the card header it pointed at neither side,
                             which is what made it unreadable next to an inline
                             diff. --}}
                        <x-revision-panel :title="__('What changed')">
                            <x-diff :html="$comparison->result->html" :kind="$comparison->kind" />
                        </x-revision-panel>

                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <x-revision-version
                                :revision="$comparison->from"
                                :kind="$comparison->kind"
                                :label="__('Older')"
                                :base-hash="$baseHashes[$comparison->field]"
                                :is-current="$from->isCurrent"
                            />
                            <x-revision-version
                                :revision="$comparison->to"
                                :kind="$comparison->kind"
                                :label="__('Newer')"
                                :base-hash="$baseHashes[$comparison->field]"
                                :is-current="$to->isCurrent"
                            />
                        </div>
                    </x-card>
                </article>
            @empty
                <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                    <p class="font-medium text-content-muted">{{ __('These two saves left every field identical.') }}</p>
                </div>
            @endforelse

            @if ($unchangedFields !== [])
                <p class="text-sm text-content-muted">
                    {{ trans_choice(
                        '{1}1 other field unchanged (:fields)|[2,*]:count other fields unchanged (:fields)',
                        count($unchangedFields),
                        ['count' => count($unchangedFields), 'fields' => implode(', ', $unchangedFields)],
                    ) }}
                </p>
            @endif
        @endif
    </div>
</x-revisions-layout>
