---
status: planned
planned: 2026-07-17
expanded: 2026-07-17
---

# Epub Configuration

## Before proceeding

We will split Export & import configuration page into 
 * Export, which will have tabs (no longer javascript tabs, but separate controller actions)
   * Export Project
   * Export ebook, which will contain the current export form and also the configuration defined in this spec
 * Import (just for import project)

## Specification

Config form to allow choices of what to include or not in the epub:

* Covers (and which: Project and chapter as cover pages) 
  * For scenes, that's a V2 with before/after scene images
* the scene titles
* Chapter title format: "Chapter 123: Title", or "123: Title', "Chapter 123", "123", "Title"
* descriptions (at act/chapter/scene level checkboxes)
* Acknowledgements (to be saved at the project level as markdown)
* Dedication (to be saved at the project level as markdown)
* Preface and Postface (to be saved at the project level as markdown)
* author,
* publisher
* Licence / rights (cf rights field on project)
* isbn
* Table of contents depth: to acts, to chapters, or to scenes
* Divider type: hr, decorative, maybe image
* Also for V2: a Review entity as a child of the project, with a title, link, 

* Appendix with Codex entries or not:
  * which types of entries
  * with cover images or not

## After proceeding

What was decided as "V2" will be handed over to a new draft spec: "epub-configuration-v2"
