# 08 — Docker dev watcher verification

**Depends on:** 04.

## Scope

`vite.config.js` sets `usePolling: true, interval: 60_000` under `VITE_USE_POLLING`, which
`docker-compose.dev.yml` sets. Its comment explains why: bind-mounted source delivers no
filesystem events into a Linux container from a Windows or macOS host, so Vite never learns a
Blade file changed and **"a newly used utility class silently renders as nothing"**.

v4 does its own content scanning instead of reading a `content` array, so it is not obvious the
polling setting still reaches it. That failure mode is identical to a broken `@source`, which
makes it exactly the kind of thing someone will spend an afternoon misdiagnosing.

Verify it empirically:

1. `make up` (or the `docker compose -f docker-compose.dev.yml` equivalent — the Makefile
   targets are one-line aliases).
2. Edit a Blade template to use a utility class **not currently used anywhere** in the project
   — pick something arbitrary like `tracking-widest` and confirm with grep that it is unused,
   so a cached stylesheet cannot produce a false pass.
3. Wait up to the 60s poll interval. Reload. Confirm the class actually renders.
4. Revert the edit.

## Outcome — either way, the comment gets corrected

- **Works unchanged:** update the comment to say the polling setting has been verified against
  Tailwind 4's own scanner, with the date. Do not leave it implying v3 mechanics.
- **No longer works:** fix it, and rewrite the comment to describe what actually happens now.
- **No longer needed** (v4 picks up changes in-container without polling): say so in the
  comment, but **do not delete the polling block in this task** — removing it also means
  touching `docker-compose.dev.yml`, and that is a larger blast radius than a port should
  carry. Record it in `resolution-log.md` as a follow-up.

## Why this blocks the merge

The failure is silent and its symptom points away from this PR. The next person to hit it will
not connect an unstyled element to a Tailwind migration that shipped weeks earlier — that is
precisely the confusion the existing comment was written to prevent, and it deserves to stay
accurate.

## Not in scope

- Changing `docker-compose.dev.yml`.
- Tuning the 60s interval.
- Production Docker (`docker-compose.yml`) — it builds assets, it does not watch.

## Blocked?

If Docker Desktop is not available in the implementing environment, **do not silently skip**.
Stop and report it, so the user can either run the check themselves or consciously downgrade
this to a `standing-issues.md` note.

## Tests

None automatable — this verifies a developer-experience behaviour that no test harness
observes.

## Consult

`../expanded/open-questions.md` §5; `documentation/docker.md`; the comment in `vite.config.js`.
