---
status: shipped
shipped: 2026-08-07
planned: 2026-08-07
expanded: 2026-08-07
---

# No localstorage with autosave

The debounce delay of autosave fields is short enough that localstorage is not needed.
Furthermore, the restoration of localstorage can introduce bugs.

We remove persistence of the textarea's contents to localstorage.
