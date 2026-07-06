---
status: planned
---

# Harden dependencies

`league/commonmark` is only a transitive dependency today, yet the Story overview
depends on it directly via `Str::markdown()`.

* Add `league/commonmark` to `composer.json`'s own `require` so a dependency prune
  can't break Markdown rendering of `Scene.contents`.
