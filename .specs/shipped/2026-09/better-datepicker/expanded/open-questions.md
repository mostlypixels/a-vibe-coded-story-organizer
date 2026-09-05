# Open questions

- **Explicit `clock`/`order` map vs parsing the ICU pattern per locale?**
  Recommend the explicit map in `config/locales.php`. The supported list is tiny (3), the map is
  readable and deterministic, and it avoids `IntlDateFormatter` pattern-scraping. Month names
  still come from Carbon locale data, so nothing is hand-translated.

- **Which locales at launch?**
  Recommend `en`, `fr`, `it` — the three demo-content languages. Adding more is a config edit
  later; nothing in code hardcodes the set.

- **Confirm event dates never get the user's `timezone`.**
  Recommend: they don't. `event_datetime` is an in-universe instant; only real-world dates
  (word-count snapshots, challenge windows) use `timezone`. `DateFormat` must not `setTimezone`.
  Flagging because a naive "format for the user" reading would wrongly shift them.

- **Display rollout: all sites now, or events first?**
  Recommend all known `event_datetime->format(...)` sites in this feature — the grep set is
  small and a half-migrated app shows two date styles at once. New non-event date displays
  (real-world) are out of scope.

- **Year box as `type="text" inputmode="numeric"` vs `type="number"`?**
  Recommend text+inputmode: `type="number"` reintroduces spinners and, in some locales, digit
  grouping — the opposite of the goal. Validate digits in Alpine.

- **Where does the resolved `LocaleChoice` get shared** — middleware vs `AppServiceProvider`
  `View::share`? Recommend `AppServiceProvider` boot, guarded for a null user. Minor; decide at
  build time.
