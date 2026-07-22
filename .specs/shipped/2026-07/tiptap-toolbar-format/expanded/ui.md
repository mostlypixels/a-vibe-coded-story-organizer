# UI / Blade plan

## Reuse decision: `<x-dropdown>`

`resources/views/components/dropdown.blade.php` already exists and is used elsewhere in the app
(Breeze/Jetstream-style: `x-data="{ open: false }"`, `@click.outside="open = false"`, `$trigger`
and `$content` slots, `align`/`width` props). It's the natural fit for the two collapsed groups —
**no new dropdown component needed.** Its default `align="right"`/`width="48"` (i.e. `w-48`) is
sized for a vertical menu-item list (see `dropdown-link.blade.php`); the toolbar groups need a
*horizontal row of icon buttons* instead, so pass `contentClasses="p-1 bg-white flex items-center gap-0.5"`
and `width="auto"` (the component's `$width` `match` falls through to whatever string is passed
when it isn't `'48'`) so the popover hugs its button row instead of stretching to `w-48`.

## New component: `x-wysiwyg.toolbar-button`

`resources/views/components/wysiwyg/toolbar-button.blade.php` (namespaced under `wysiwyg.*` like
Blade's own sub-component convention — first component to use that subfolder; nothing else needs
splitting out of `wysiwyg.blade.php` yet).

```blade
@props(['command', 'args' => null, 'active' => null, 'label', 'title'])

<button
    type="button"
    @click="cmd({{ Js::from($command) }}{{ $args ? ', '.Js::from($args) : '' }})"
    @if($active)
        :class="isOn({{ Js::from($active[0]) }}{{ isset($active[1]) ? ', '.Js::from($active[1]) : '' }}) ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
    @else
        class="text-gray-600 hover:bg-gray-200"
    @endif
    {{ $attributes->merge(['class' => 'inline-flex min-w-[2rem] items-center justify-center rounded px-2 py-1 text-sm font-medium']) }}
    title="{{ $title }}"
    aria-label="{{ $title }}"
>{!! $label !!}</button>
```

This only covers the **plain-toggle shape** (`cmd(command[, args])` + optional `isOn(active)`) —
the existing `$toggles` array shape, plus headings and the row/column ops, all fit it. **Link,
Image, and Callout do not** — they call `setLink()`, `setImage()`, `toggleCallout()` (no-arg,
app-specific helpers), so they stay as their own hand-written `<button>` markup in
`wysiwyg.blade.php`, exactly as today. Don't force them into the shared component just for
uniformity — that would need a third prop shape (`@click` as a raw string) that defeats the
component's purpose of keeping the array-driven buttons declarative.

## `wysiwyg.blade.php` restructure

Replace the single flat `$toggles` array and inline heading loop with clearly-named arrays, one
per cluster, defined in the existing `@php` block:

```php
$headings = collect(range(1, 4))->map(fn ($level) => [
    'label' => "H{$level}", 'command' => 'toggleHeading', 'args' => ['level' => $level],
    'active' => ['heading', ['level' => $level]], 'title' => __('Heading :level', ['level' => $level]),
]);

$textFormat = [...]; // Bold/Italic/Underline/Strike — same 4 entries as today's $toggles[0..3]
$listsAndBlocks = [...]; // bullet/ordered/task list, blockquote, code, code block — today's $toggles[4..9]
$tableStructure = [
    ['label' => '&#8213;+', 'command' => 'addRowAfter', ...],
    ['label' => '&#8213;&minus;', 'command' => 'deleteRow', ...],
    ['label' => '&#8214;+', 'command' => 'addColumnAfter', ...],
    ['label' => '&#8214;&minus;', 'command' => 'deleteColumn', ...],
    // merge/split appended conditionally, see below
];
if (! $markdown) {
    $tableStructure[] = ['label' => '&#8676;&#8677;', 'command' => 'mergeCells', ...];
    $tableStructure[] = ['label' => '&#8677;&#8676;', 'command' => 'splitCell', ...];
}
```

Toolbar markup becomes 5 clusters in order, each separated by the existing
`<span class="mx-1 h-5 w-px bg-gray-300"></span>` divider tick (unchanged divider style —
goal #1 asks for "tighter/more consistent", and reusing the existing tick *consistently between
every cluster* — including before/after each dropdown trigger — satisfies that without inventing a
new visual language):

1. **Headings dropdown** — trigger button showing the active level's label (`H1`/`H2`/../`H4`, or
   plain `H` when `isOn('heading')` is false for all levels — use
   `:class`/computed label the same way the trigger already reads `isOn()`), `title="Heading"`.
   Content: the 4 `x-wysiwyg.toolbar-button` from `$headings`, laid out in a row.
2. **Text format** — inline, unchanged from today (`$toggles[0..3]` → `$textFormat`).
3. **Lists & blocks** — inline, unchanged (`$toggles[4..9]` → `$listsAndBlocks`).
4. **Insert** — inline: Link, Horizontal rule, Table, Image, Callout (hand-written buttons,
   unchanged markup, just moved adjacent to each other — Table and Image move up next to Link/HR
   from their current position after the table-structure buttons).
5. **Table structure dropdown** — trigger button (a table glyph, e.g. `&#9638;`, distinct from the
   "Insert table" button in cluster 4 — give it `title="Table structure"` / `aria-label` to
   disambiguate from cluster 4's `title="Table"`), content: the `x-wysiwyg.toolbar-button` row
   built from `$tableStructure` (4 or 6 buttons depending on `$markdown`).

Net effect on the always-visible row: 5 heading buttons → 1 dropdown trigger, 6 table-structure
buttons → 1 dropdown trigger. Worst case (HTML mode) collapses from ~25 visible buttons to ~15,
which should fit one line in the toolbar's existing width without wrapping in the common case —
`flex flex-wrap` on the outer container stays as a safety net for narrow viewports, not the
primary layout mechanism.

## Alpine/JS

No changes to `resources/js/wysiwyg.js`. `<x-dropdown>`'s `open` state is local Alpine state scoped
to its own `x-data`, independent of the editor's `x-data="wysiwyg(...)"` — the two don't need to
communicate, dropdown open/close doesn't touch editor state, `cmd()`/`isOn()` are still called the
same way from inside the dropdown's `$content` slot (Alpine scope nests, so `cmd`/`isOn` from the
parent `wysiwyg()` component remain reachable inside the dropdown's markup).

## Testing note

No existing test asserts toolbar DOM structure (confirmed: `WysiwygFormTest.php` and
`wysiwyg.test.js` test editor mount/hydrate/sync behavior, not button layout). This refactor needs
no test *changes*, but plan-tasks should still add one small Feature-test assertion (e.g. render
the component and assert a couple of representative `aria-label`s are still present — Bold,
Heading, Table structure) as a regression guard, per CLAUDE.md's "every ... bug fix ships with a
test" spirit, even though this is a refactor rather than a bug fix.
