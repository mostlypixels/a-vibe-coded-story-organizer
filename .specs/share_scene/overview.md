# Share scene — Overview

## Problem statement

An author working in the app can only see their scenes while logged in. They want to
show a single scene to someone outside the project (a beta reader, an editor) **without
creating an account or granting project access**. The share must be link-based, carry a
non-guessable token, and stop working after a configurable period.

## Goals

- Generate a **public, unauthenticated URL** for one `Scene`, gated by an unguessable **token**.
- The link **expires** after a configurable amount of time; an expired/invalid token does not
  reveal the scene.
- A clean, read-only **public display page** showing:
  - A formatted title: `Chapter {n} — {chapter title}: {scene title}`, where `{n}` is the
    Arabic `chapter.position` (matching the rest of the app).
  - The scene **contents rendered as formatted HTML** (contents is stored as Markdown → render
    via `Str::markdown()`, the same path the Story overview uses).
  - The scene **description inside a collapsed card** (rich HTML via `x-rich-text`).
- Owner controls on the authenticated side to **create / view / revoke** the link.

## Non-goals

- No sharing of acts, chapters, whole projects, or the codex — scene only (one aggregate).
- No editing, comments, or reactions from the public visitor (read-only).
- No public listing/discovery — the token is the only entry point (add `noindex`).
- No per-recipient links, view analytics, or email delivery in this iteration.
- The scene **notes** field is internal and is **never** shown on the public page.

## User stories

- As a project owner, I can generate a share link for a scene from its edit page, copy it,
  and send it to someone.
- As a project owner, I can see whether a scene currently has an active link and when it
  expires, and I can revoke it at any time.
- As an external visitor with the link, I can read the scene's formatted contents and expand
  its description, with no login.
- As a visitor with an expired or wrong link, I get a clear "not available" response and no
  scene data.

## Acceptance criteria

- `GET /shared/scenes/{token}` with a valid, unexpired token returns 200 and renders the scene
  read-only; notes are absent from the HTML.
- The same route with an unknown token returns 404; with an expired token returns 410 (or a
  friendly expired page — see open questions).
- The public route is reachable **without** `auth` middleware; every other scene route stays
  behind `auth`.
- Generating a link requires an authenticated **owner** of the scene's project; a non-owner
  gets 403 (authorization walks up `scene.chapter.act.project` via `ProjectPolicy`).
- Revoking clears the token so the previously-issued URL then returns 404.
- The default validity duration comes from **config**, not a hard-coded literal.
- Feature tests cover: valid view, expired, unknown token, owner-only generation/revocation,
  and that notes never appear in the shared HTML.
