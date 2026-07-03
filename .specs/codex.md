# Codex

There will be a new entry in the menu: the codex.

It will contain several entities that are relevant to storybuilding / storytelling / novel writing:

* Characters
* Locations
* Organizations

While each of these entities will have different features and properties, they have many points in common:

* They have a name
* They have a description
* They have aliases (pseudonyms, nicknames, acronyms, etc.) that will be used to search for them
* They have tags / categories

The edit pages will have three columns: col-8 for the main content on left, col-2 for the columns on right.
The left column will contain the main content/form. the middle column will contain the tags and categories. the right column has file uploads: cover image, reference images, reference files.

The entities have attributes that function the same between them and could possibly be a shared base class.

Ie: characters would have the type of attributes that belong on a character sheet (such as race, eye color...). Locations would have the type of attributes that belong on a location sheet (such as terrain, type of building...). Organization would have the type of attributes that belong on an organization sheet (such as type of organization, size, political alignment...).

Those attributes are mutable and tied to events.

Example:

* The character has blonde hair starting from the fixed event "Start"
* The character dies their hair during event "halloween" and their hair is green from that point on.
* The character dies it to to black hair during the "back to class" event.
* The character has black hair from the back to class event to the End event (unless another change is applied in another event between those two).

Another example:

* A house is painted red from the beginning of the story.
* The house is painted green during the "back to class" event.
* The house is green until the End event.

There must be a way to view the value of an attribute at a given time (in a scene through its assigned event during which the scene happens, or the in the event itself). There cannot be "holes" in the values; it starts at the Start event, with other periods inserted, and ends at the End event.

There must be a way to create new attributes for entities and pick which entities they show up on.
