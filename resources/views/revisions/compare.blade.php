@php
    use Illuminate\Support\Str;
@endphp

<x-revisions-layout :project="$project" :entity="$entity" :id="$id" :field="$field">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <x-heading level="2">
                {{ __('Compare') }} &mdash; {{ Str::headline($entity) }} "{{ $entityName }}"
            </x-heading>
            <a href="{{ route('revisions.index', ['entity' => $entity, 'id' => $id, 'field' => $field]) }}" class="text-sm">
                {{ __('Back to history') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($from === null || $to === null)
            {{-- Fewer than two save points: there is no pair to be had yet. --}}
            <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-gray-500">
                <p class="font-medium text-gray-600">{{ __('Nothing to compare yet.') }}</p>
                <p class="mt-1 text-sm">{{ __('This entity needs at least two saves before they can be compared.') }}</p>
            </div>
        @else
            {{-- The pickers, as two plain <select>s in a GET form: the working
                 no-JS baseline task 15 progressively enhances into a combobox.
                 Left is always the older side and right the newer, and the
                 invalid pairing is made *unreachable* (disabled options) rather
                 than accepted and then rejected — there is no backwards diff and
                 no error state to design. --}}
            <form method="GET" class="bg-white shadow-sm rounded-lg px-6 py-4">
                @if ($field !== null)
                    <input type="hidden" name="field" value="{{ $field }}">
                @endif

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="compare-from" :value="__('Older')" />
                        <select
                            id="compare-from"
                            name="from"
                            class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm text-sm"
                        >
                            @foreach ($points as $point)
                                <option value="{{ $point->saveId }}" @selected($point->saveId === $from->saveId)>
                                    @include('revisions.partials.save-point-option', ['point' => $point, 'number' => $points->count() - $loop->index])
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="compare-to" :value="__('Newer')" />
                        <select
                            id="compare-to"
                            name="to"
                            class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm text-sm"
                        >
                            @foreach ($points as $point)
                                {{-- The list is newest-first, so anything at or
                                     after the older selection's position is not
                                     newer than it. --}}
                                <option
                                    value="{{ $point->saveId }}"
                                    @selected($point->saveId === $to->saveId)
                                    @disabled($loop->index >= $points->search(fn ($candidate) => $candidate->saveId === $from->saveId))
                                >
                                    @include('revisions.partials.save-point-option', ['point' => $point, 'number' => $points->count() - $loop->index])
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between gap-4">
                    <p class="text-sm text-gray-500">
                        {{ trans_choice('{0}The same save|{1}1 save apart|[2,*]:count saves apart', $savesApart, ['count' => $savesApart]) }}
                        &middot;
                        {{ $from->savedAt->format('d F Y H:i') }} &rarr; {{ $to->savedAt->format('d F Y H:i') }}
                    </p>
                    <x-button variant="secondary" type="submit">{{ __('Compare') }}</x-button>
                </div>
            </form>

            @if ($field !== null)
                <p class="text-sm text-gray-500">
                    {{ __('Showing :field only.', ['field' => Str::headline($field)]) }}
                    <a
                        href="{{ route('revisions.compare', ['entity' => $entity, 'id' => $id, 'from' => $from->saveId, 'to' => $to->saveId]) }}"
                        class="text-ocean-600 hover:text-ocean-800 hover:underline"
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
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <x-heading level="3" id="diff-{{ $comparison->field }}">
                                    {{ Str::headline($comparison->field) }}
                                    @if ($comparison->isNewField())
                                        <x-badge variant="success">{{ __('New') }}</x-badge>
                                    @endif
                                </x-heading>

                                {{-- Restore the older side. Hidden when that side
                                     is already the live value, where restoring it
                                     would be a no-op dressed up as an action. --}}
                                @if ($comparison->from !== null && ! $from->isCurrent)
                                    <x-revert-revision-button
                                        :revision="$comparison->from"
                                        :base-hash="$baseHashes[$comparison->field]"
                                    />
                                @endif
                            </div>
                        </x-slot>

                        <x-diff :html="$comparison->result->html" :kind="$comparison->kind" />
                    </x-card>
                </article>
            @empty
                <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-gray-500">
                    <p class="font-medium text-gray-600">{{ __('These two saves left every field identical.') }}</p>
                </div>
            @endforelse

            @if ($unchangedFields !== [])
                <p class="text-sm text-gray-500">
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
