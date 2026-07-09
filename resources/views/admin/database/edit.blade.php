<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configuration') }}
        </h2>
    </x-slot>

    {{-- Read-only view of the active database connection. The controller passes a
         whitelisted subset (driver / database / host) — never the password. --}}
    <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Database configuration') }}
        </h2>

        <dl class="mt-4 space-y-4 text-sm">
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
    </div>
</x-admin-layout>
