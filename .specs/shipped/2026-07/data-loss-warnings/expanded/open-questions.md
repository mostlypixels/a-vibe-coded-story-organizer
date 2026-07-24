---
title: Data Loss Warnings — Open Questions
---

# Open Questions

All six original questions (plus the "move or delete" design pivot they led to) were
resolved via grilling before planning — see `../resolution-log.md`'s **Feedback &
decisions** section for the full record. Nothing left open that blocks decomposition
into tasks.

One item flagged during grilling as worth confirming at implementation time, not
architecturally blocking: the exact query shape for an Act's scene-count (a plain
`whereHas` count vs. a nested `withCount` dot-path) — `architecture.md` §4b states the
recommended approach; either is fine as long as it returns the right number.
