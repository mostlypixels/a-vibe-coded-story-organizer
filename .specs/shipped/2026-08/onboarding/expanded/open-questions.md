# Onboarding — open questions

1. **Attribute/tag/entry content per genre.** The four bundles need real lists. Deferred to
   an authoring pass; not a blocker for the mechanism. → *Recommend: ship the mechanism with
   1–2 attributes and tags per genre, fill in later.*

2. **Does stored `genre` do anything after seeding?** Re-suggest, filter the UI, or just a
   label? → *Recommend: label only in v1. Keep the column; add behavior later.*

3. **Bundle location: enum vs support classes.** → *Recommend: a `Genre` enum for the
   stored value + one support class per bundle in `app/Support`, keyed by `Genre`. Mirrors
   `PlotlineColors` / `ThemePreset`.*

4. **Demo install: separate command or a `db:seed` flag?** → *Recommend: a separate
   `app:install-demo` command. Maps 1:1 to the onboarding button and keeps `db:seed` clean.*

5. **What runs `app:install-demo` in dev?** `make seed` and the docker startup call
   `db:seed` today and expect demo data. → *Recommend: `make seed` runs `db:seed` then
   `app:install-demo`; document a demo-less path for a clean start.*

6. **`SecondUserSeeder`'s home.** It exists to test authorization by hand (a non-owner). Does
   it stay in `db:seed` or move under demo install? → *Recommend: move it under
   `app:install-demo` — it is demo data, not core.*

7. **Skip: reuse `store` with Blank, or its own action?** → *Recommend: reuse `store` with
   `genre = Blank` so there is one seed path.*

8. **Melusine is temporary filler.** The author will supply a real novel for the demo later.
   The install mechanism stays; only the seeder content changes. Not a blocker.

9. **Wizard vs single page.** v1 is one page with sections. → *Recommend: single page; revisit
   if the explainer content grows.*
