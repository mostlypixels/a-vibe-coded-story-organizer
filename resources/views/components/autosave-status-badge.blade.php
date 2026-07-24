{{--
    Global lower-right autosave badge (task 9, `expanded/ui.md` "Global indicator"):
    one page-wide indicator reflecting the worst state across every mounted
    `x-autosave-field` instance, via `Alpine.store('autosave')`
    (`resources/js/autosave/field.js`'s `registerAutosaveField()`). Additive to the
    per-field inline indicator, never a replacement (00-overview.md/handoff.md
    §9.5 — "both indicators, always").

    Rendered once, in the authenticated layout (see resources/views/layouts/app.
    blade.php). z-40 — one below x-modal's z-50 (resources/views/components/modal.
    blade.php, the only other fixed-position component today) so an open modal's
    backdrop naturally covers this badge instead of the two competing for the same
    layer, and bottom-4/right-4 so it never overlaps a modal's typically centered
    footprint.
--}}
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
        {{-- Clicking scrolls to and focuses the offending field (ui.md); a no-op for
             session-expired/forbidden-after-replay, where the Sign in link (or simply
             copying the still-visible text) is the actual next step, not navigation. --}}
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
