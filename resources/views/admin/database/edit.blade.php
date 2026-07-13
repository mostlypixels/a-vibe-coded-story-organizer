<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    {{-- Read-only view of the active database connection. The controller passes a
         whitelisted subset (driver / database / host) — never the password. --}}
    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Database configuration') }}</x-heading>
        </x-slot>

        <dl class="space-y-4 text-sm">
            <div>
                <dt class="font-medium text-gray-500">{{ __('Driver') }}</dt>
                <dd class="mt-1 text-gray-900">{{ $connection['driver'] ?? '—' }}</dd>
            </div>

            <div>
                <dt class="font-medium text-gray-500">{{ __('Database') }}</dt>
                <dd class="mt-1 break-all text-gray-900">{{ $connection['database'] ?? '—' }}</dd>
            </div>

            @if (! empty($connection['host']))
                <div>
                    <dt class="font-medium text-gray-500">{{ __('Host') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $connection['host'] }}</dd>
                </div>
            @endif
        </dl>
    </x-card>
</x-admin-layout>
