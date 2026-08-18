<div
    x-data="autosaveBadge()"
    x-show="visible"
    style="display: none;"
    x-transition.opacity
    class="fixed bottom-4 right-4 z-40 max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg"
    :class="badgeClasses"
    role="status"
    aria-live="polite"
    data-autosave-badge
>
    <div class="flex items-start justify-between gap-3">
        <button type="button" @click="focusField()" class="text-left" x-text="label"></button>

        <a
            x-show="showSignIn"
            style="display: none;"
            href="{{ route('login') }}"
            target="_blank"
            rel="noopener"
            class="shrink-0 font-medium underline"
        >
            {{ __('Sign in') }}
        </a>
    </div>
</div>
