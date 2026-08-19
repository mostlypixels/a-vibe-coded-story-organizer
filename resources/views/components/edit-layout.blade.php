<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-9 space-y-6">
        {{ $slot }}
    </div>

    <div class="lg:col-span-3 space-y-6">
        @isset($sidebar)
            {{ $sidebar }}
        @endisset
    </div>
</div>
