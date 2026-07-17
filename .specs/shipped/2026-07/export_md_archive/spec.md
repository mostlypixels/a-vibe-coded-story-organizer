---
status: shipped
shipped: 2026-07-10
---

# Export to static files

The export admin page should have a section to export to static files.
Have a switch choice to include the images or not.

* Images are exported in their initial format, with no thumbnails.
* Html is exported as is.
* There must be a way to connect the images to the entity they belong to, and the field name (ie: cover, ...).
* Use an arborescence that matches the menu, except for the story itself.
* Have a directory by act, named after the act's number and a slug of its title/name. It contains an index.html 
* Inside the act directory, do the same for the chapters.
* Inside the chapter directories, have the scenes files named after the scene's number and a slug of its title/name.
* The scene files should contain the html of the scene (and of all the other fields). Have a second file for the MD content, with frontmatter.
* The compiled storyline, as html, should be in the story folder (that contains the acts).

The export will be downloaded as a zip file.
