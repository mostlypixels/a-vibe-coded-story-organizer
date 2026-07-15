# Open questions — Laravel 13 upgrade

1. **Which PHP build should WAMP switch to: 8.3, 8.4, or 8.5?**
   Recommendation: **8.3** — it's the minimum that satisfies Laravel 13's `^8.3` constraint, so
   it's the smallest behavioral delta from the current 8.2.18 and the safest first hop. Jumping
   straight to 8.4/8.5 stacks more PHP-version risk onto the same change window for no benefit,
   since nothing in the goals requires the newer versions. Confirm 8.3 is one of the builds
   already installed locally (the source spec says "a compatible build is already available").

2. **Is this upgrade done by one person locally, or does it need to be reproducible by other
   contributors / a future CI setup?**
   Recommendation: treat it as **local-only for now** — the repo has no `.github/workflows/`, so
   there's no CI matrix to update, and the source spec's non-goals exclude sail/breeze bumps.
   If a second developer's machine also runs this app under WAMP, note in
   `documentation/best-practices.md` (or a new setup note) which PHP build WAMP must serve, so
   the requirement doesn't only live in `composer.json`'s constraint.

3. **Should `laravel/sail`, `laravel/breeze`, `laravel/pail`, `laravel/pint` be bumped
   proactively, or only if Composer's dependency resolution forces it?**
   Recommendation: **only if forced** — this matches the source spec's explicit non-goal. Let
   `composer require laravel/framework:^13.0` surface any forced transitive bumps; don't
   pre-emptively touch dev tooling versions.

4. **Do any of the three non-Laravel packages (`ezyang/htmlpurifier`, `rampmaster/phpepub`,
   `secondnetwork/blade-tabler-icons`) actually block Laravel 13 or PHP 8.3?**
   Unknown until `composer why-not laravel/framework:13.20.0` is run against the current lock
   file — this is a pre-flight check in `architecture.md`, not something to guess at spec time.
   If one does block, the plan needs a task to resolve it (pin update, fork, or replacement)
   before the framework bump can proceed — flag this as a plan risk rather than deciding a
   mitigation now.

5. **Where should "PHP 8.3+ / Laravel 13" be documented once done?**
   Recommendation: `documentation/architecture.md:3` (`"This is a Laravel 12 app..."`) is the one
   confirmed spot; also grep `README.md` / `melusine.md` at plan time in case either mentions a
   version, and add a `CHANGELOG.md` `Changed` entry per the project's changelog convention.
