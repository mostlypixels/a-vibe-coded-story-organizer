# Hidden from crawlers — UI

## Settings page: `resources/views/settings/crawlers/edit.blade.php`

Mirror the `profile/edit.blade.php` shell: `<x-app-layout>` with a header slot
and a white card (`p-4 sm:p-8 bg-white shadow sm:rounded-lg` → `max-w-xl`). One
`<section>` with header + form. Reuse existing form components — `x-input-label`,
`x-input-error`, `x-primary-button`, and the profile page's "Saved." Alpine
transition keyed on `session('status') === 'crawler-settings-updated'`.

Form (`method="post"` + `@method('patch')` + `@csrf`, action
`route('crawler-settings.update')`):

1. **Hidden mode toggle** — a checkbox named `enabled` (Tailwind styled; there is
   no `x-checkbox` component, write a semantic `<input type="checkbox">` with a
   `<label>`). Checked when `old('enabled', $setting->enabled)` is truthy. Helper
   text: "When on, the site is hidden from search engines and crawlers."

2. **Whitelist textarea** — `name="user_agent_whitelist"`, one term per line.
   Value must survive both a fresh load (array from DB) and a validation redirect
   (array from `old()`):
   ```blade
   @php
       $whitelistOld  = old('user_agent_whitelist');
       $whitelistText = is_array($whitelistOld)
           ? implode("\n", $whitelistOld)
           : ($whitelistOld ?? implode("\n", $setting->whitelistTerms()));
   @endphp
   ```
   Render `<x-input-error :messages="$errors->get('user_agent_whitelist.*')" />`
   (glob) so per-line errors surface. Helper text: "One user-agent term per line
   (e.g. Googlebot). These crawlers stay allowed while hidden mode is on."

3. **Actions** — `x-primary-button` "Save", plus a "Preview robots.txt" link
   (`route('robots.txt')`, `target="_blank" rel="noopener"`) so the operator can
   see the generated output.

Keyboard-accessible, semantic HTML, labels tied to inputs (`for`/`id`) — per the
Frontend guideline.

## Navigation entry

`resources/views/layouts/navigation.blade.php`:

- Desktop settings dropdown (`x-slot name="content"`, near the `profile.edit`
  link):
  ```blade
  <x-dropdown-link :href="route('crawler-settings.edit')">
      {{ __('Site settings') }}
  </x-dropdown-link>
  ```
- Responsive menu (near the responsive `profile.edit` link):
  ```blade
  <x-responsive-nav-link :href="route('crawler-settings.edit')">
      {{ __('Site settings') }}
  </x-responsive-nav-link>
  ```

The link is unconditional (any authenticated user; no role gate).

## Meta component

`resources/views/components/robots-meta.blade.php` — see `architecture.md`. Added
to `<head>` of `layouts/app`, `layouts/guest`, `welcome`, and (forced)
`layouts/public`. No visible UI; verified via page source in tests.

## No new table/list components

This feature reuses the form-component family only; it adds no `x-table` rows,
icons, sortable headers, or Alpine widgets beyond the existing "Saved." toast
pattern copied from the profile form.
