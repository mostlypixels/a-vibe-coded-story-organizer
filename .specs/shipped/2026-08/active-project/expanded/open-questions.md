# Active project — open questions

> [!IMPORTANT]
> **Resolved in the grill (2026-08-03). This file is now history — `plan/00-overview.md` is
> binding.** Read it for the reasoning behind a decision, not to reopen one. Deltas from the
> recommendations below:
>
> - **Q4** — reversed. A bare login lands on the active project's `projects.show`, not the
>   dashboard; `intended()` still wins for a blocked deep link. The Q6 rationale (don't route the
>   user through a page they didn't want) applies harder to login than to Configuration.
> - **Q3** and **Q6** were settled before the grill, mid-expansion; both files already reflect it.
> - **Q1, Q2, Q5, Q7** stand as recommended.
> - Two claims below were wrong and are corrected in `data-model.md`: SQLite rollback needs no
>   special handling, and `scenes.event_id` is an existing precedent for the exact column shape.

## Q1 — Does the `<title>` follow the active project? **Recommend: no, route only.**

The nav answers "what am I working on", the title answers "what is on this screen" — one project
per tab is the reason the project name leads (`documentation/architecture.md` → *Page title*). If
the title follows the account, every tab of a multi-tab session reads `"Melusine - imagoldfish"`,
including Configuration and the dashboard, and the tab stops distinguishing anything. Cost of being
wrong: one argument in the view composer.

## Q2 — Is there a way to leave a project without deleting it? **Recommend: not in v1.**

Deleting is the only clear today. Options if that itches: a "Leave project" item at the bottom of
the picker panel (a `DELETE` route + one controller action — the first user-submitted write this
feature would have, and the first thing to authorize), or repurposing "All projects" to clear on
click (rejected: it is a navigation link, and users click it to *look* at the list, not to log out
of a project). Sticky-until-replaced is the whole point of the feature; ship it and see if anyone
asks.

## Q3 — Track on open, or on every project page? **Recommend: every page. Settled 2026-08-03.**

Two drafts, in order:

1. **`ProjectController::show` only.** Simplest thing that works, and authorization is trivially
   satisfied because the write sits after `authorize()`. Rejected: a **bookmark** into
   `/scenes/{scene}/edit` in project B, while A is active, never activates B. That page renders B
   (the route wins) and so does every link off it — but the moment the user opens the dashboard,
   `/profile` or `/admin/*`, the nav silently reverts to A. A nav that lies about what you are
   working on defeats the feature.
2. **Middleware, writing after the response, gated on 2xx.** The objection to middleware was that
   it runs before the controller's policy call — true only when writing on the way *in*. Writing on
   the way out makes the status code the authorization check: a 403 or 404 never reaches the write.

Result: stored value ≡ displayed value ≡ "last project page successfully loaded". One rule, no
drift, no ownership comparison. Cost: one middleware class plus extracting `RouteProject` so it
and the nav can't disagree about which project a URL belongs to.

## Q4 — Should login redirect to the active project? **Recommend: no.**

Out of the spec's scope, and it touches Breeze's `AuthenticatedSessionController` /
`RouteServiceProvider::HOME`. The dashboard already shows the nav with the project in it after
this change, which is most of the benefit for none of the surprise. Genuinely separable — decide
it after living with the nav for a week.

## Q5 — Where does the middleware sit? **Recommend: on the auth group in `routes/web.php`.**

`bootstrap/app.php`'s `web` group would run it for guests and for the public share/robots routes
that deliberately live outside `auth`. Route-level registration keeps the auth-only assumption
visible next to the routes it applies to. The counter-argument — someone adds an authenticated
route outside that group and silently loses tracking — is real but small; there is exactly one
such route today (`/dashboard`, which resolves no project).

## Q6 — Should the project menu really render over `/admin/*` and `/profile`? **Yes. Settled 2026-08-03.**

**This is a goal of the feature, not a side effect.** Configuration is a detour: the writer goes
there to change a setting and wants one click back to whatever they were working on, instead of
dashboard → project → section. The `/admin/*` and `/profile` pages are precisely where the nav has
nothing else to offer, so they are where the stored project earns its keep.

"The project navigation is shown whenever a project is active" with no exceptions is also one rule
and zero conditionals. Carving out the Configuration area would mean the nav's contents depend on
which *kind* of page you are on, which is the class of rule that rots. The Configuration sidebar is a
separate navigation and does not compete for the same space.

## Q7 — What about an active project the user no longer owns? **Recommend: guard the write, not the read.**

Only a 2xx project page can store an id (Q3), and no feature transfers or shares project ownership
— so a stored id is either the user's or already deleted, and the FK nulled it. Re-checking on
every read adds a policy call to every authenticated page render for a state that cannot currently
occur. If project sharing ever lands, this becomes a read-side check and this line is the reminder.
