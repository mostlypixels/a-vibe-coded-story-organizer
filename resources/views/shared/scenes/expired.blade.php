<x-public-layout>
    {{-- Friendly branded 410 page for an expired or revoked share link.
         Deliberately shows NO scene data — an expired token must not leak the
         title, description, or contents it once granted. --}}
    <div class="max-w-3xl mx-auto px-4 py-20 text-center space-y-4">
        <x-application-logo class="mx-auto w-16 h-16 fill-current text-gray-400" />

        <x-heading level="1">
            {{ __('This share link has expired.') }}
        </x-heading>

        <p class="text-gray-600">
            {{ __('The link you followed is no longer active. Ask the person who shared it for a fresh link.') }}
        </p>

        {{-- Only a relative timestamp — never scene content. Omitted when the
             share was revoked outright (no expiry recorded). --}}
        @isset($expiredAt)
            <p class="text-sm text-gray-400">
                {{ __('This link expired :time.', ['time' => $expiredAt->diffForHumans()]) }}
            </p>
        @endisset
    </div>
</x-public-layout>
