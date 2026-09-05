<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>


    @if (session('status') === 'revision-settings-updated')
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 4000)"
            role="status"
            class="rounded-md border border-info bg-info-surface px-4 py-3 text-sm text-info-surface-content mb-6"
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
            class="rounded-md border border-info bg-info-surface px-4 py-3 text-sm text-info-surface-content mb-6"
        >
            {{ __(':count revision(s) removed.', ['count' => session('purgedCount')]) }}
        </div>
    @endif

    <x-card class="max-w-xl mb-8">
        <x-slot name="header">
            <x-heading level="3">{{ __('Retention') }}</x-heading>
            <p class="mt-1 text-sm text-content-muted">
                {{ __('The nightly cleanup keeps unlabeled, autosaved revisions for this many days before removing them. Manual saves, labeled revisions, and reverts are never removed by this — see the storage panel below to clear those explicitly.') }}
            </p>
        </x-slot>

        <form method="POST" action="{{ route('admin.revisions.update') }}" class="space-y-4">
            @csrf
            @method('patch')

            <x-field name="retention_days" :label="__('Retention window (days)')">
                <x-text-input
                    id="retention_days"
                    type="number"
                    name="retention_days"
                    min="7"
                    max="3650"
                    :value="old('retention_days', $retentionDays)"
                    class="mt-1 block w-32"
                />
            </x-field>

            <x-button variant="primary">{{ __('Save') }}</x-button>
        </form>
    </x-card>

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Revision storage') }}</x-heading>
            <p class="mt-1 text-sm text-content-muted">
                {{ __('Bulk-delete revisions by category or age. Unlike the nightly cleanup above, this can remove labeled, manual, and reverted revisions — use it deliberately.') }}
            </p>
        </x-slot>

        @php
            $categoryLabels = [
                \App\Services\RevisionPurger::CATEGORY_AUTOMATIC => __('Automatic (autosaved)'),
                \App\Services\RevisionPurger::CATEGORY_MANUAL => __('Manual'),
                \App\Services\RevisionPurger::CATEGORY_LABELED => __('Labeled'),
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
                    <x-table-cell muted>{{ $categoryLabels[$category] }}</x-table-cell>
                    <x-table-cell muted>{{ number_format($result->count) }}</x-table-cell>
                    <x-table-cell muted>{{ number_format($result->sizeBytes / 1024, 1) }} KB</x-table-cell>
                    <x-table-cell align="right">
                        <x-button
                            type="button"
                            variant="danger"
                            size="sm"
                            x-data=""
                            x-on:click.prevent="$dispatch('open-modal', 'purge-{{ $category }}')"
                            :disabled="$result->count === 0"
                        >{{ __('Delete all') }}</x-button>

                        <x-dialog name="purge-{{ $category }}" :title="__('Delete :label revisions?', ['label' => $categoryLabels[$category]])">
                            <p class="text-sm text-content-muted">
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
                    </x-table-cell>
                </x-table-row>
            @endforeach
        </x-table>

        <div class="mt-6 flex items-center justify-between rounded-md border border-border p-4">
            <div>
                <p class="text-sm font-medium text-content">{{ __('Automatic revisions older than 1 year') }}</p>
                <p class="text-sm text-content-muted">{{ __('Removes even the newest automatic revision of a field if it is over a year old.') }}</p>
            </div>

            <x-button
                type="button"
                variant="danger"
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'purge-old-automatic')"
            >{{ __('Delete') }}</x-button>

            <x-dialog name="purge-old-automatic" :title="__('Delete old automatic revisions?')">
                <p class="text-sm text-content-muted">
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
