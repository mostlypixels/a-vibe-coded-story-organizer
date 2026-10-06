@aware(['disclosureId' => null])

{{-- Its own component, because only a nested component can read the dropdown's id through @aware. --}}
<div
    x-data="appearanceSwitcherLoader({{ Js::from([
        'url' => route('admin.appearance.switcher'),
        'id' => $disclosureId,
        'messages' => ['loading' => __('Loading…'), 'failed' => __('Could not load the settings. Try again.')],
    ]) }})"
>
    <p x-show="!loaded" x-text="status" aria-live="polite" class="p-4 text-sm text-content-muted">{{ __('Loading…') }}</p>
    <div x-ref="body"></div>
</div>
