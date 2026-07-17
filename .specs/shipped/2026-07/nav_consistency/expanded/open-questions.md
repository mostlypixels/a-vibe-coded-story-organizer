# Nav consistency — open questions

Each question states a recommended answer. These are the grilling agenda for `plan-tasks`.

## Q1 — Scope: Story only, or all three desktop dropdowns?

The spec asks only to highlight the **Story** dropdown, and frames it as "match the Codex nav
behavior." But the desktop **Codex** and **Timeline** dropdowns *also* don't highlight today —
the highlighting the spec attributes to Codex actually lives only in the **mobile** menu. So
"match Codex" can't be done by copying a desktop behavior that doesn't exist; we're building it
fresh.

**Recommendation:** Extend `x-dropdown-link` once, then wire **all three** desktop dropdowns
(Story, Codex, Timeline). It's a few extra `:active` attributes, it's the literal meaning of
"nav consistency," and shipping Story-only would leave the exact inconsistency this feature
names. If the user insists on minimal scope, do Story-only but still land the reusable
`active` prop so Codex/Timeline are a trivial follow-up.

## Q2 — Highlight the collapsed dropdown trigger too?

With a closed dropdown, an active *item* inside it is invisible until you open it. Highlighting
the trigger ("Story" / "Codex" / "Timeline") is what actually tells a user which section
they're in at a glance — arguably the more valuable half.

**Recommendation:** Yes, include it — swap the trigger to `nav-link`'s active look
(`text-white border-flame-500`) when any child route matches. It's the same matchers OR-ed
together. If the user wants to keep the change minimal, defer it, but note the item-only version
delivers little at-a-glance value.

## Q3 — Active style: fill only, or fill + left accent bar?

`x-responsive-nav-link` uses `bg-aqua-50` **and** a `border-l-4 border-flame-500`. Inside a
narrow white desktop dropdown the accent bar can look heavy.

**Recommendation:** Start with fill + `font-semibold` only (`bg-aqua-50`, `text-navy-900`); add a
thin `border-l-2 border-flame-500` only if reviewers find the fill too subtle. Pin the final
look in `ui.md` before implementation.

## Q4 — Deduplicate the shared `:active` expressions?

After wiring, the same `routeIs(...)` strings appear in both the desktop and responsive blocks
of `navigation.blade.php`.

**Recommendation:** Leave them duplicated for now (same file, adjacent, trivial to sync; the
file already duplicates the `$project` `@if` and codex loop). Only extract a tiny inline
closure if Q1's full scope makes the repetition noisy. Do **not** create a `Nav` support class
or view composer — that's new architecture for a styling tweak and would need explicit sign-off.

## Q5 — Does this warrant a `NavigationTest`, or fold into an existing test?

**Recommendation:** New `tests/Feature/NavigationTest.php` — the behavior is cross-cutting (it's
not "about" scenes or acts), so it doesn't belong in a resource test. Small, 3–5 assertions per
`testing.md`.

## Q6 — Backward move in the spec lifecycle

The folder was stamped `status: planned` but has **no `expanded/` or `plan/` folder** — it was
never actually run through the pipeline. This skill will re-stamp it to `expanded` and move it
to `.specs/expanded/nav_consistency/`.

**Recommendation:** Accept the re-stamp; the earlier `planned` label appears to have been set by
hand without artifacts. Confirm no in-flight plan references the old path.
