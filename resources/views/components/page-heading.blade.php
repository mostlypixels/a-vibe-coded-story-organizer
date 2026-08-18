@props(['level' => 1])

<div class="mb-6">
    <x-heading :level="$level">{{ $slot }}</x-heading>
</div>
