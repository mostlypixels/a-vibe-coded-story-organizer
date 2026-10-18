@if (session('status') === 'reverted-save')
    <x-alert variant="success" dismissible class="mb-6">
        {{ __('Save undone.') }}
        @if (session('restored_fields'))
            {{ __('Restored: :fields.', ['fields' => implode(', ', session('restored_fields'))]) }}
        @endif
    </x-alert>
@endif

@if (session(\App\Support\Flash::SUCCESS))
    <x-alert variant="success" dismissible class="mb-6">{{ session(\App\Support\Flash::SUCCESS) }}</x-alert>
@endif
