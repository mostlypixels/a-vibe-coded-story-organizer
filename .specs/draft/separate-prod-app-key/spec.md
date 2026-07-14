---
status: draft
---

# Separate prod APP_KEY

Generate a distinct `APP_KEY` for `.env.production` (currently duplicated from dev's `.env` as a temporary shortcut) before release, so prod and dev can't decrypt each other's encrypted values/sessions.
