{{-- Opens on the window event `open-lightbox` with `{ url, alt }`. Include it once per page. --}}
<div
    x-data="{ image: null }"
    x-show="image"
    style="display: none"
    @open-lightbox.window="image = $event.detail"
    @keydown.escape.window="image = null"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6"
    role="dialog"
    aria-modal="true"
>
    <div class="fixed inset-0 bg-scrim opacity-75" @click="image = null"></div>

    <div class="relative mx-auto max-w-3xl">
        <x-icon-close-button @click="image = null" variant="light" class="absolute -top-10 right-0" />
        <img :src="image?.url" :alt="image?.alt" data-full-opacity class="w-full rounded-lg shadow-xl">
    </div>
</div>
