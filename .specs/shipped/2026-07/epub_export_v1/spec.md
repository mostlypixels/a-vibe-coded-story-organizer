---
status: shipped
shipped: 2026-07-12
---

# Epub export (v1)

The export page must have a new section: "Epub export"

The export will be able to create an epub file from the compiled story

* It has a table of contents 
* The epub only contains semantic tags for the document structure
* Acts have a single page with "Act 1 newline Act title"
* Chapters start new pages but can have content under the "Chapter 1 : title"
* The markdown scenes are compiled into clean HTML, and separated by <hr>.
* the en and em dashes must be converted properly from -- and ---
* other special characters must be processed also, like ... to an ellipsis

We must respect proper epub conventions. If it needs to include a language or other data, add the necessary fields to the Project

* The epub must respect accessibility rules
* The epub must implement all features needed to be commercializable.
