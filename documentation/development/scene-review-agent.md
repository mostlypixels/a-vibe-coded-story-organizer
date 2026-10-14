# Scene review agent

[Documentation](../README.md) › [Development](README.md) › Scene review agent

The `scene-reviewer` Claude agent (`.claude/agents/scene-reviewer.md`) checks one scene for spelling, grammar, vocabulary, name and plot problems. It saves the points to fix as a note. It never changes the scene.

## Use

Ask Claude Code: "review scene 42 with the scene-reviewer agent". A scene name also works.

## Artisan commands

The agent reads and writes only through these commands. They run in the Docker app.

| Command | Purpose |
|---|---|
| `scene:review-context` | Scene, book language, codex names and aliases, codex values at the scene moment, linked notes, earlier scenes |
| `scene:show` | Full text of other scenes |
| `scene:search` | The project search, as plain text |
| `scene:review-note` | Creates or replaces the review note. HTML body on stdin or `--file` |

- Earlier scenes show only their description, except the last three (`--full`). A long book then stays small enough to read. The agent asks for more with `scene:show`.
- Every codex name is listed, not only the matched ones: a misspelled name matches no entry.
- A TODO note linked to nothing is book-wide. The context prints it, and the agent does not report its problems again in each scene.

## The review note

- Category: the project's `TODO` note category, created when missing. The name match ignores case.
- Title: `Review: <scene name>`, linked to the scene.
- One review note per scene. A new review replaces the body and records a manual revision, so earlier findings stay in the history.
