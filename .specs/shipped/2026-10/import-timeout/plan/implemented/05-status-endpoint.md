---
title: "Task 05 — Status endpoint"
---

# Task 05 — Status endpoint

## Scope

- Route `GET /admin/data/imports/{import}/status` → `admin.data.imports.status`, `ImportController::status()`.
- `ImportPolicy::view()`: owner only.
- JSON response:

```json
{ "state": "queued|running|stalled|completed", "phase": "story", "label": "Story imported",
  "failure_message": null, "project_url": null }
```

- The state comes from the model (`isRunning()`, `isQueued()`, phase), not the controller.

## Depends on

02.

## Key decisions already made

- `project_url` only when completed.
- A JSON action on `ImportController`; no separate API controller.

Docs: `expanded/architecture.md` → *Status endpoint*.

## Tests

- Owner gets each of the four states.
- Non-owner → 403. Guest → login redirect.
