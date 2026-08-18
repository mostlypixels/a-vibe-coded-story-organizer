<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Database configuration') }}</x-heading>
        </x-slot>

        <dl class="space-y-4 text-sm">
            <div>
                <dt class="font-medium text-content-muted">{{ __('Driver') }}</dt>
                <dd class="mt-1 text-content">{{ $connection['driver'] ?? '—' }}</dd>
            </div>

            <div>
                <dt class="font-medium text-content-muted">{{ __('Database') }}</dt>
                <dd class="mt-1 break-all text-content">{{ $connection['database'] ?? '—' }}</dd>
            </div>

            @if (! empty($connection['host']))
                <div>
                    <dt class="font-medium text-content-muted">{{ __('Host') }}</dt>
                    <dd class="mt-1 text-content">{{ $connection['host'] }}</dd>
                </div>
            @endif
        </dl>
    </x-card>
</x-admin-layout>
