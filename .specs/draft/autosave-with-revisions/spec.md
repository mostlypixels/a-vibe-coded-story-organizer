---
status: draft
---

# Autosave With Revisions

The app requires a revisions package and an automated save when the user edits textareas.

This is a critical feature with no margin for error.

# Scope

The feature is meant for fields with long text.

The fields that must be have a history are the fields that pertain to the project and its children:

* Project
* Acts
* Chapters
* Scenes
* Events
* Codex entries

Future fields must implement the same functionality.

## The scene "content" field

* Is the most important.
* Saves are more frequent than the other fields.

# Saving

* Saves can be either automated or manual.
* Revisions save the user id.

## Automated saving

* Must be done in ajax (possibly through axios)
* Debouncing: saving with a delay when the user stops typing
* Save when the user leaves the field
* Save on Ctrl-S
* Display a loader indicator in the app's lower right corner while saving

## Manual saving

* A revision is created when the user saves an entity.
* Manual saves are named and tagged.
* Manual saves are not pruned automatically.

# Revision history

* The user can see the history of the field in a dedicated page by field
* The user can revert to a previous version
* The user can see the date of the revision
* The user can name any revision (manual or automated) and:
  * search the list by the "tag"
  * tagged versions cannot be pruned automatically
* There must be a way to compare revisions

# When to make a revision

* There must be significant changes to the field's value

# Retention

* There is a pruning script that deletes revisions older than X days
* The pruning script is run every day
* The pruning script can be run manually

# Import / Export

* Optional in zip export
* Handled by import
* Not included in epub export
* Not included in pdf export


