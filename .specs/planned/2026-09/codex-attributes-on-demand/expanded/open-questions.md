# Open questions

1. **A pair with a blank Start and a filled later period — does the show page list it?**
   Recommend yes. `setOnly()` tests "any row filled", so the history stays reachable. This
   answers the spec's first open end.

2. **May the picker create a new project attribute inline?**
   Recommend no for this feature — pick from the list, with a link to the Attributes
   screen. Inline creation is the fastest path back to fifteen attributes.

3. **Is "attached but blank" worth supporting?**
   It already is, for free: an `''` row. Confirm that is wanted, because it means clearing
   a value to blank does *not* detach — only the explicit Remove does.

4. **Should detach be confirmed?**
   Recommend yes, with the values-are-deleted wording. Detach is not undoable and is one
   click from a picker click.

5. **Do we ship the `setOnly()` fix and the cleanup migration in this feature?**
   Recommend yes. Without them the read pages keep listing blanks for every form-made
   entry, and the edit form still shows fifteen for existing entries.

6. **Does the create form need the picker at all, or just name + description?**
   Recommend the picker. Without it a writer who *does* know the character's hair colour
   has to save, then reopen.

7. **Does the archive format need a version bump?**
   Import/export carries rows unchanged, so probably not — but a pre-change archive
   restores fifteen blank rows per entry. Confirm the cleanup migration is enough, or that
   the importer should drop all-blank pairs too.
