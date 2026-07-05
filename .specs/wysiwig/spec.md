---
status: shipped
shipped: 2026-07-05
---

#  Wysiwig textareas

We want to implement a "notion like" wysuwig editor for textareas.

The solutions picked can be one of the following options (but Claude can suggest different options if they think it's better):

* https://github.com/fedorananin/redactix
* https://github.com/ueberdosis/tiptap
* https://github.com/Milkdown/milkdown

We want to avoid bloat of the codebase / libraries, to avoid security issues.

Redactix is the initial favorite for HTML fields as its list of out of the box features matches the needs of the project, but we must ensure its image upload feature does not allow non-logged in users to upload images. If Redactix is not suitable because of security issues, suggestions are welcome.

For html fields, we want all of Redactix's slash commands to be implemented (but image galleries are not a priority).

## HTML fields

Most textareas, including "description" fields precedently flagged as markdown, can become wywsiwy html fields.

## Markdown only fields

* The "content" field of the Scene entity MUST be markdown only.
