{{--
    Shared 9-3 shell for edit/create form pages: primary form in the left 9 columns,
    secondary/reference content (cover images, related links, share panels, etc.) in
    the right 3. The right column always renders (even with no $sidebar slot passed)
    so the left column's width stays consistent whether or not a given resource has
    sidebar content.
--}}
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
