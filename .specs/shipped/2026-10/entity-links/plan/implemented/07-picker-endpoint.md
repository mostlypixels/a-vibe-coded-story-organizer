# 07 — Picker endpoint

## Scope

- `GET /projects/{project}/link-targets?type=&q=` → `[{type, id, label}]`, named `projects.link-targets`.
- Move the candidate query out of `NoteLinkController::candidates` into one place that both endpoints use.
- Every `LinkTargetType` is allowed here, `note` included.
- Deferred: the dialog that calls it → 08.

## Depends on

- 01.

## Key decisions already made

- Authorize `view` on the project, as `NoteLinkController::candidates` does.
- Same limit and search rule as the note-link candidates (`LikeSearch`, `CANDIDATE_LIMIT`).

## Docs

- `expanded/architecture.md` → Authorization.

## Tests

- Owner gets candidates for each type, filtered by `q`. Non-owner gets 403. Unknown type gets 422.
- Results contain only entities of that project.
