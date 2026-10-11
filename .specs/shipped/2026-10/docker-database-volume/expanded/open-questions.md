# Open questions

1. **Separate storage tree, or only document the risks?** Recommend separate. `LARAVEL_STORAGE_PATH` makes it one variable, and it is the only fix for both risks (shared files, cross-runtime purge).
2. **Tree name and place: `storage-docker/` at the root, or `storage/docker/`?** Recommend the root. Inside `storage/`, native code that walks its own tree (logs, purges) could touch Docker's files.
3. **Today's data: start Docker fresh, or run `db-from-native` once?** Recommend `db-from-native`: it keeps Gardenia Girl and Les Misérables without a 2.5 min re-import.
4. **Direction of copies: only native → Docker, or also Docker → native?** Recommend native → Docker only, plus the `db-to-host` snapshot. Docker is the default runtime. Add the reverse when you need it.
5. **Does `db-from-native` copy `storage/logs` and backups?** Recommend `storage/app` only, without `backups/` and `exports/`. Logs belong to the runtime that wrote them.
6. **Does PHP-FPM see `LARAVEL_STORAGE_PATH`?** Check `clear_env` in the first task. Fallback: an `env[...]` line in the FPM pool config.
7. **`public/storage` is one link for both runtimes.** It points to the Docker tree after Docker starts. No public-on-purpose files exist yet. Recommend to leave it and keep the existing handoff note.
