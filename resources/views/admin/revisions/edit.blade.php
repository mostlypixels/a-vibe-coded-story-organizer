<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    {{--
        Task 13: the dedicated admin "Revisions" page — the RevisionSetting
        retention form (confirm-gated on lowering, RevisionSettingController::
        update()) and the "Revision storage" panel (bulk-delete actions calling
        RevisionPurger, the second of its two call sites alongside the
        `revisions:purge` command).
    --}}

    @if (session('status') === 'revision-settings-updated')
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-navy-900 mb-6"
        >
            {{ __('Retention setting saved.') }}
        </div>
    @elseif (session('status') === 'revisions-purged')
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-navy-900 mb-6"
        >
            {{ __(':count revision(s) removed.', ['count' => session('purgedCount')]) }}
        </div>
    @endif

    <x-card class="max-w-xl mb-8">
        <x-slot name="header">
            <x-heading level="3">{{ __('Retention') }}</x-heading>
            <p class="mt-1 text-sm text-gray-600">
                {{ __('The nightly cleanup keeps unlabeled, autosaved revisions for this many days before removing them. Manual saves, labeled revisions, reverts, and imports are never removed by this — see the storage panel below to clear those explicitly.') }}
            </p>
        </x-slot>

        <form method="POST" action="{{ route('admin.revisions.update') }}" class="space-y-4">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="retention_days" :value="__('Retention window (days)')" />
                <input
                    id="retention_days"
                    type="number"
                    name="retention_days"
                    min="7"
                    max="3650"
                    value="{{ old('retention_days', $retentionDays) }}"
                    class="mt-1 block w-32 border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                >
                <x-input-error :messages="$errors->get('retention_days')" class="mt-2" />
            </div>

            <x-button variant="primary">{{ __('Save') }}</x-button>
        </form>
    </x-card>

    {{--
        "Revision storage" panel: counts + SUM(size_bytes) per category, never
        hydrating `value` (00-overview.md's read rule) — RevisionSettingController
        computes this via RevisionPurger's own dry-run query, so the figures can
        never drift from what a bulk-delete button actually removes.
    --}}
    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Revision storage') }}</x-heading>
            <p class="mt-1 text-sm text-gray-600">
                {{ __('Bulk-delete revisions by category or age. Unlike the nightly cleanup above, this can remove labeled, manual, reverted, and imported revisions — use it deliberately.') }}
            </p>
        </x-slot>

        @php
            $categoryLabels = [
                \App\Services\RevisionPurger::CATEGORY_AUTOMATIC => __('Automatic (autosaved)'),
                \App\Services\RevisionPurger::CATEGORY_MANUAL => __('Manual'),
                \App\Services\RevisionPurger::CATEGORY_LABELED => __('Labeled'),
                \App\Services\RevisionPurger::CATEGORY_IMPORTED => __('Imported'),
            ];
        @endphp

        <x-table>
            <x-slot:head>
                <x-table-heading>{{ __('Category') }}</x-table-heading>
                <x-table-heading>{{ __('Count') }}</x-table-heading>
                <x-table-heading>{{ __('Size') }}</x-table-heading>
                <x-table-heading />
            </x-slot:head>

            @foreach ($storage as $category => $result)
                <x-table-row :striped="$loop->even">
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $categoryLabels[$category] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($result->count) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ number_format($result->sizeBytes / 1024, 1) }} KB</td>
                    <td class="px-4 py-3 text-right">
                        <x-button
                            type="button"
                            variant="danger"
                            size="sm"
                            x-data=""
                            x-on:click.prevent="$dispatch('open-modal', 'purge-{{ $category }}')"
                            :disabled="$result->count === 0"
                        >{{ __('Delete all') }}</x-button>

                        <x-dialog name="purge-{{ $category }}" :title="__('Delete :label revisions?', ['label' => $categoryLabels[$category]])">
                            <p class="text-sm text-gray-600">
                                {{ __('This will permanently delete :count revision(s). This cannot be undone.', ['count' => number_format($result->count)]) }}
                            </p>

                            <x-slot name="footer">
                                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                                    {{ __('Cancel') }}
                                </x-button>

                                <form method="POST" action="{{ route('admin.revisions.purge-category', $category) }}">
                                    @csrf
                                    @method('delete')
                                    <x-button variant="danger" type="submit">{{ __('Delete') }}</x-button>
                                </form>
                            </x-slot>
                        </x-dialog>
                    </td>
                </x-table-row>
            @endforeach
        </x-table>

        {{--
            Age-based bulk-delete example named in handoff.md §4.3: automatic
            revisions older than a year, even the newest one for a field (purge
            is explicitly allowed to do what prune never does).
        --}}
        <div class="mt-6 flex items-center justify-between rounded-md border border-gray-200 p-4">
            <div>
                <p class="text-sm font-medium text-navy-900">{{ __('Automatic revisions older than 1 year') }}</p>
                <p class="text-sm text-gray-600">{{ __('Removes even the newest automatic revision of a field if it is over a year old.') }}</p>
            </div>

            <x-button
                type="button"
                variant="danger"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'purge-old-automatic')"
            >{{ __('Delete') }}</x-button>

            <x-dialog name="purge-old-automatic" :title="__('Delete old automatic revisions?')">
                <p class="text-sm text-gray-600">
                    {{ __('This will permanently delete every automatic revision older than 1 year, including the newest one for a field. This cannot be undone.') }}
                </p>

                <x-slot name="footer">
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-button>

                    <form method="POST" action="{{ route('admin.revisions.purge-old-automatic') }}">
                        @csrf
                        @method('delete')
                        <x-button variant="danger" type="submit">{{ __('Delete') }}</x-button>
                    </form>
                </x-slot>
            </x-dialog>
        </div>
    </x-card>
</x-admin-layout>
