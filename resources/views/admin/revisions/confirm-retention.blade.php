<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    {{--
        The confirm step for lowering RevisionSetting::retention_days
        (handoff.md §9.11): a plain two-step POST/confirm form, works without
        JavaScript. $prunableCount is computed by RevisionSettingController from
        the REAL Revision::prunable() query object evaluated as if
        $newRetentionDays were already saved — never a hand-rolled estimate.
        Nothing is persisted unless "Confirm" is submitted; "Cancel" simply
        returns to the settings page without a POST.
    --}}
    <x-card class="max-w-xl">
        <x-slot name="header">
            <x-heading level="3">{{ __('Confirm lower retention window') }}</x-heading>
        </x-slot>

        <p class="text-sm text-gray-700">
            {{ __('Lowering the retention window from :current to :new days will permanently delete :count version(s) on the next nightly cleanup.', [
                'current' => $currentRetentionDays,
                'new' => $newRetentionDays,
                'count' => number_format($prunableCount),
            ]) }}
        </p>
        <p class="mt-2 text-sm text-gray-600">
            {{ __('Manual saves, labeled revisions, reverts, imports, and the newest revision of every field are never removed by this.') }}
        </p>

        <div class="mt-6 flex items-center gap-4">
            <a href="{{ route('admin.revisions.edit') }}" class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Cancel') }}
            </a>

            <form method="POST" action="{{ route('admin.revisions.update') }}">
                @csrf
                @method('patch')
                <input type="hidden" name="retention_days" value="{{ $newRetentionDays }}">
                <input type="hidden" name="confirmed" value="1">
                <x-button variant="danger" type="submit">{{ __('Confirm') }}</x-button>
            </form>
        </div>
    </x-card>
</x-admin-layout>
