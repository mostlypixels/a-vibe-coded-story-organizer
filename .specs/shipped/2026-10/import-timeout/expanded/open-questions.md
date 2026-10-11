# Open questions

1. **Where does the run mode live?** Recommend: config only (`IMPORT_RUN_IN_BACKGROUND`). Drop the `import_settings.run_in_background` column and its checkbox. Whether a queue worker runs is a fact about the runtime, not a writer setting, and the env var cannot reach an existing row today.
2. **What does "recently" mean for the warning?** Recommend: while the earlier project still exists (`project_id` not null). There is no time window to tune.
3. **Do the duplicate checks apply to native inline imports?** Recommend: yes. The check runs in `start()`, before the run mode is chosen.
4. **Worker settings for all jobs, or only the import?** Recommend: only the import, through job properties (`tries`, `timeout`). `retry_after` is global, and raising it is harmless.
5. **Polling interval?** Recommend: 3 s. A phase takes tens of seconds.
6. **What does the page show on failure?** Recommend: reload to the stalled row with `failure_message`, Resume and Discard. This is the same as a stalled inline import.
7. **Is the upload request under 60 s without the unpack?** Validation reads every descriptor. Recommend: measure validate + hash with the Les Misérables archive before planning. If it is over 30 s, the validation also moves to the job.
8. **Hide Discard while an import runs?** Recommend: yes. Discard deletes the partial project under a running phase.
9. **Add `pcntl` to both Docker images?** Recommend: yes. Without it, the job timeout does nothing.
