@props(['title'])

<section class="space-y-4">
    <x-heading level="2">{{ $title }}</x-heading>

    <div class="space-y-4">
        {{ $slot }}
    </div>
</section>
