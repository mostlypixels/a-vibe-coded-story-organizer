<div class="flex items-start gap-3">
    <input
        type="checkbox"
        id="is_approximate"
        name="is_approximate"
        value="1"
        @checked($checked)
        class="mt-1 rounded-sm border-border-strong text-link shadow-xs focus:ring-focus"
    >
    <div>
        <x-input-label for="is_approximate" :value="__('Approximate date')" />
        <p class="mt-1 text-sm text-content-muted">{{ __('The date is a guess. Pages show it as “c.” and the date, with no time.') }}</p>
    </div>
</div>
