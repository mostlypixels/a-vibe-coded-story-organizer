---
status: shipped
shipped: 2026-07-14
---

# Alias references v1

Codex entities have aliases.

A relationship system must be implemented between the codex entries and scenes.

Whenever a scene is saved, look for mention of all the codex aliases in the text, and save the association the db. This is to avoid live lookups

When the mention is removed, the codex entry is deleted, or the alias is deleted, remove the relationship.

Be restrictive about mention: match full words. Ex:

* If Melusine has the alias "Mel", the word "melody" must not match.

In the codex edit page:

* Add a help text under the aliases to explain there can be conflicts in the references search if aliases overlap.
* in the right sidebar, show a list of references in scenes, in timeline order

In the scene edit page, show the codex relationships in the right sidebar. For V1, they do NOT update through AJAX. Instead, they are saved when the save button is pressed.

Optimizations for performance should be considered before implementation.
