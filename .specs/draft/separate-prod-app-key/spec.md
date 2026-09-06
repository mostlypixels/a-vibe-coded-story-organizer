---
status: draft
---

# Separate prod APP_KEY

Generate a distinct `APP_KEY` for `.env.production` (currently duplicated from dev's `.env` as a temporary shortcut) before release, so prod and dev can't decrypt each other's encrypted values/sessions.

## Not yet

Held until the release candidate. Nothing runs in production and the only data is the
Melusine seed, so the shared key costs nothing today. Do it as part of cutting the RC,
before any real data exists — after that, rotating the key makes every value encrypted
under the old one unreadable.
