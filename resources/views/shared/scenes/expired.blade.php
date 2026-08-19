<x-public-layout>
    <div class="max-w-3xl mx-auto px-4 py-20 text-center space-y-4">
        <x-application-logo class="mx-auto w-16 h-16 fill-current text-content-subtle" />

        <x-heading level="1">
            {{ __('This share link has expired.') }}
        </x-heading>

        <p class="text-content-muted">
            {{ __('The link you followed is no longer active. Ask the person who shared it for a fresh link.') }}
        </p>

        @isset($expiredAt)
            <p class="text-sm text-content-subtle">
                {{ __('This link expired :time.', ['time' => $expiredAt->diffForHumans()]) }}
            </p>
        @endisset
    </div>
</x-public-layout>
