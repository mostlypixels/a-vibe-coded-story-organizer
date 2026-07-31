# Open questions — Tailwind 4

Each has a recommended answer. These are the agenda for the grill in `plan-tasks`.

## 1. The 88 `border` usages — shim or explicit? — **DECIDED: shim** (2026-07-31)

v4's width-only `border` defaults to `currentColor`; v3 defaulted to `gray-200`.

**Decided: the base-layer shim**, removed in spec 2.

```css
@layer base {
  *, ::after, ::before, ::backdrop, ::file-selector-button {
    border-color: var(--color-gray-200);
  }
}
```

Rewriting 88 usages inside a PR whose acceptance criterion is "nothing changed" is 88 chances
to change something. Spec 2 touches those elements anyway to introduce `--color-border`, and
can drop the shim in the same pass. The cost is a global rule that contradicts v4's default
for as long as spec 2 takes to land — worth naming in `standing-issues.md` so it is not
mistaken for an oversight.

*Counter-argument heard and accepted as a cost:* the shim is invisible magic in a codebase
written for junior developers, and "we will remove it in the next spec" is how debt becomes
permanent. Two obligations follow from taking it anyway:

* A comment at the rule itself saying what it restores, why, and that spec 2 removes it —
  the rule must explain itself where a reader finds it, not only in a spec folder.
* An entry in `standing-issues.md` (see §7), so it reads as a dated decision rather than
  something nobody noticed.

## 2. Does the browser floor need a decision, or is it just noted?

v4 requires Safari 16.4+ / Chrome 111+ / Firefox 128+.

**Recommend: note it, do not gate on it.** This is a self-hosted writing app for a small
number of known users, not a public site with analytics. If that is wrong — if there is a
target user on an older browser — the whole migration needs reconsidering, so it is worth one
explicit answer rather than an assumption.

## 3. Should the `border-nav-active` placeholder token exist at all in this PR?

The `flame` → `fuchsia` swap is the one intentional visual change.

**Recommend: yes, via a `@theme` token**, not a literal `fuchsia` class in Blade. It costs one
line, it proves the `@theme` → custom-property → utility path works end to end (which is the
whole point of the migration), and spec 2 repoints one line instead of editing four
components.

Sub-question: `nav-link` and `responsive-nav-link` also use `focus:border-flame-600`. One
token or two? **Recommend two** (`--color-nav-active`, `--color-nav-active-focus`) — a single
token loses the focus distinction, and spec 2 will want them separable anyway.

## 4. Delete `tailwind.config.js`, or keep it via `@config`?

v4 can still load a v3 JS config with `@config "../../tailwind.config.js"`.

**Recommend: delete it.** Keeping it defeats the migration — theme values would stay in JS,
not become runtime custom properties, and spec 2 would have nothing to build on. The `@config`
escape hatch is for incremental migrations of large codebases; 55 colour values and one font
stack is not that.

## 5. Does the Docker polling watcher still work?

`vite.config.js` sets `usePolling: true, interval: 60_000` under `VITE_USE_POLLING`, because
bind-mounted source on a Windows/macOS host delivers no filesystem events into a Linux
container — the exact failure it guards against is *"a newly used utility class silently
renders as nothing"*, which is the same symptom a broken `@source` produces.

v4 does its own file scanning rather than reading a `content` array, so it is not obvious the
polling setting still reaches it.

**Recommend: verify in Docker before merging**, and update the comment in `vite.config.js` if
the mechanism changed. Nobody should have to rediscover that failure mode a second time — the
comment is unusually good and deserves to stay accurate. If polling no longer applies, say so
there rather than deleting it.

## 6. `admin/appearance` already exists — does this PR touch it?

`AppearanceController` renders `admin.appearance.edit`, documented in its own docblock as
*"Appearance & accessibility section… This placeholder page is the final v1 form — no later
task enriches it."*

**Recommend: do not touch it.** But the docblock is now wrong — `display-configurator` (spec 3)
is exactly the later task that enriches it. Correcting that one comment is in scope; adding
anything to the page is not.

## 7. Where do the accepted differences get recorded?

If the browser pass finds drift that is not worth fixing (a 1px shadow difference nobody would
notice), it needs a home.

**Recommend: create `standing-issues.md` in this feature folder** — the convention from
`revision-history-rework`, for facts that stay true of `master`. `flame`'s 2.48:1 contrast
belongs there too, as a known defect deliberately deferred to spec 2 rather than an oversight.

## 8. Is `@tailwindcss/forms` still doing what we think?

Both plugins are 0.5.x and v4-compatible, but `forms` works by resetting form-element styling
— and v4 changed several of the defaults it interacts with (`outline`, `border-color`,
`ring`). `text-input.blade.php` sits on top of it.

**Recommend: no action, but make form controls an explicit line item in the browser pass**
rather than assuming the plugin absorbs the change. Inputs, selects, checkboxes, radios,
file inputs (`hover:file:bg-ocean-100` exists in the codebase), and textareas — all at rest,
focused, and in an error state.
