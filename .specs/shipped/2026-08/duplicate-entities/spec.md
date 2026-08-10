---
status: shipped
shipped: 2026-08-10
planned: 2026-08-10
expanded: 2026-08-10
---

# Duplicate entities action

* The various "story" and "codex" entities should be able to be duplicated.
* The duplicate action should be available on the entity list and in the "Actions" card of edit page.
* There should be a step between the duplicate action and the save action so the user can chose a new name.
  * The new name is prefilled with the original name and a number between parentheses.
  * If the name with the number is already taken by another entity of the same type, we attempt to increment the number. (2) becomes (3) and so on until we find a unique name.
* For entities that have a position in a chapter, the "duplicate" action should create the entity in the next position.
* The attachements should be duplicated.
* The images/covers should be duplicated.
* Entity "children" should be duplicated, but references (ie: scene happens during event) should merely be replicated.
