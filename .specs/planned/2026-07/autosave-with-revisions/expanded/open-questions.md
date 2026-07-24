---
title: Autosave With Revisions — Open Questions
---

# Open questions

Each is phrased as a sharp yes/no or pick-one with a recommended answer, so
`plan-tasks`' grilling pass has a concrete agenda. Most design decisions were already
resolved by the extensive `handoff.md` grilling session; what remains is either newly
surfaced by this expansion or explicitly left open there.

1. **`jfcherng/php-diff` — confirm Laravel 13 / PHP 8.5 compatibility on Packagist
   before adopting it.** `handoff.md` §6 flagged this as unverified since before the
   Laravel 13 release; this session confirmed it is still absent from `composer.json`.
   **Recommendation:** check Packagist/GitHub for a maintained release supporting PHP
   8.5 as the first implementation task in this area; if unmaintained, fall back to a
   hand-rolled word-level diff (the algorithm — LCS over token arrays — is not large,
   and `RichText::toPlainText()` already does the harder normalization work).

2. **`longText()->change()` requires `doctrine/dbal`.** Laravel's `$table->column()->
   change()` needs that package. **Recommendation:** confirm it's already a
   `composer.json` dependency (transitively or directly) before writing the widening
   migration; if absent, add it as a dev-time migration dependency only if Laravel 13
   still requires it (some recent Laravel versions dropped the DBAL requirement for
   simple type changes — verify against the installed Laravel version specifically,
   don't assume the old requirement still holds).

3. **The dirty-only rule — confirm before planning.** `handoff.md` §11.5.1: *never
   autosave a field the writer hasn't actually typed in*, so merely opening a scene
   never writes anything (no phantom baseline-triggering saves from a page view).
   Recommended and never formally confirmed. **Recommendation: adopt as stated** — it's
   what makes opening a record for reading safe, and it's cheap (a dirty flag set on
   the editor's first `input`/`update` event, checked before the first debounce fires).

4. **`Ctrl-S` vs. `.specs/draft/keyboard-shortcuts`.** Unresolved collision, carried
   from `handoff.md` §10's dependency table — that spec is still a bare draft with no
   handoff of its own. **Recommendation:** treat this as non-blocking for planning
   (`Ctrl-S` → save is an extremely standard binding unlikely to collide), but flag it
   explicitly in the plan's first task so whoever picks up `keyboard-shortcuts` later
   knows this binding is already spoken for.

5. **The 403-after-different-user-login gap (`handoff.md` §9.6's flagged warning).**
   Session expires, writer signs into the new tab as a *different* user, the queued
   replay then 403s against `ProjectPolicy`. Needs its own indicator state/copy, not a
   fold into generic `error`. **Recommendation:** add a distinct `forbidden-after-
   replay` state with copy like *"This save couldn't complete — you're signed in as a
   different account. Copy your text before switching back."* — surfacing the pending
   value inline (already mirrored to `localStorage`) rather than losing it, since a
   silent generic error is exactly the failure `handoff.md` calls out.

6. **Where does the "Revision storage" panel and retention setting live in Admin
   settings?** `handoff.md` §4.3/§9.11 specify what they contain, not where in the nav.
   `ImportSetting`'s equivalent lives on the "Export & import" admin page
   (`ImportSettingController`, confirmed above). **Recommendation:** a new "Revisions"
   admin page/section rather than folding into Export & import (which is about
   file transfer, not ongoing storage) or General settings (which is broad app config,
   not a growing-over-time concern) — but this is a placement call worth a quick grill
   rather than assuming.

7. **Does `data-loss-warnings` need to land before or after autosave ships?**
   `handoff.md` §2.3 calls it a "hard dependency" for the short-field gap but that spec
   is currently only a bare `spec.md` with no grilling handoff. **Recommendation:**
   sequence-independent for *this* spec's plan — autosave's long-text coverage is
   valuable and shippable on its own; document the known gap (short fields still lose
   work on crash) in the shipped feature's user-facing copy/changelog rather than
   blocking on a sibling spec that hasn't even been expanded yet.

8. **Import service integration point for revision history (§8).** This session did not
   read the existing importer's code (`.specs/shipped/2026-07/import`) closely enough to
   name the exact class/method that walks the manifest and would need to also import
   `revisions/*.json` files. **Recommendation:** `plan-tasks` should read
   `app/Services/ProjectImportJob`-and-friends before decomposing the import/export
   tasks, since `handoff.md` explicitly warns "not a toggle" about touching that area
   without sizing it first.
