# Open questions

1. **Skip the project-wide rescan?**
   Recommend yes. Running `syncProject()` mid-paragraph is the cost the feature exists to
   avoid. The price: other scenes keep a stale pivot until they save or the resync command
   runs. Record it as a standing issue.

2. **Duplicate name — block, warn, or link?**
   Recommend block, with an *Open it* link. The full form warns because there is a form
   left to read the warning on. Here there is not.

3. **Ship before or after `codex-attributes-on-demand`?**
   Recommend after. Before it, every quick-created entry still gets fifteen blank attribute
   rows, which is the thing the writer was running from.

4. **Trigger set: sidebar button plus slash item, or also a selection popover?**
   Recommend the two. A popover is an interaction pattern this app has nowhere else, and
   the slash menu already covers "the name I just typed".

5. **Is an abandoned name-only entry a problem?**
   Recommend no. It is one row, and it shows in the codex list where it can be deleted. A
   nag or a cleanup job costs more than the row does.

6. **Build `ajax-inline-events` first and share a pattern?**
   Recommend not blocking on it. This one needs the autosave flush and the sidebar
   replacement, which that feature does not. Extract a shared JS helper afterwards, once
   there are two real callers.

7. **Should the dialog offer a description field after all?**
   Recommend no. One field and one choice is the entire point. If it needs a second field,
   it needs the real form.
