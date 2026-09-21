# Feature deployment requirements

- feature: manual-pt-status-sessions
- task_id: direct-20260921T042749Z
- feature_commit: the commit containing this artifact; resolve with `git log -1 --format=%H -- .hermes/deployments/direct-20260921T042749Z-manual-pt-status-sessions.md`
- target_branch: staging
- default_branch: origin/main (`36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b` when fetched on 2026-09-21)
- staging_branch: staging
- expected_base: exact live base `729c0d424128e75df2fd87aaabe3ee5bde6b5b0c` on `direct/manual-pt-status-sessions`
- production_changes_required: yes

## Runtime and dependencies
- runtime_versions: PHP 8.4; Laravel 13.26.1; Filament 5.7.6; Node.js version remains the release environment default
- os_packages: none
- language_dependencies: none
- lockfile_changes: none
- environment_variable_names: none

## Data and asynchronous processing
- database_migrations: run `2026_09_21_100139_add_training_status_to_personal_training_members_table.php`, then `2026_09_21_101122_add_number_of_sessions_to_personal_training_members_table.php`
- database_backfill: the first migration snapshots each existing row's displayed meaning at execution time: `active = 0` becomes `cancelled`; otherwise a start date after the migration date becomes `pending`; otherwise an end date before the migration date becomes `completed`; all remaining rows become `active`. It then drops the legacy `active` column and indexes persisted `training_status` with `end_date`. The second migration adds nullable `number_of_sessions`; historical rows remain unknown (`NULL`).
- database_rollback: before deployment, take and verify an online SQLite backup. Schema rollback runs the sessions migration down first, then the status migration down. Status rollback recreates `active`, mapping `cancelled` to false and every other manual status to true, then removes `training_status`. Because the legacy schema cannot represent pending/active/completed independently, restore the verified pre-deploy database backup if exact manual-status data must be recovered after users have edited statuses.
- database_locking_and_duration: SQLite table/index alteration and backfill require a write lock and are proportional to the personal-training row count. Deploy in the normal maintenance window after a verified online backup; do not interrupt migration execution.
- queues: none
- cron: none
- workers: no configuration change; restart only if the standard release process requires workers to load new application code

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: none

## Build and release procedure
- build_commands: `npm ci --ignore-scripts && npm run build`; `php artisan migrate --force`
- deployment_sequence: 1. Obtain independent reviewer and QA signoff plus explicit deploy approval. 2. Capture the approved commit SHA and verify it descends from expected base `729c0d424128e75df2fd87aaabe3ee5bde6b5b0c`. 3. Put the application into the normal maintenance window. 4. Create an online SQLite backup, run an integrity check, record its checksum, and confirm it is restorable. 5. Install locked dependencies and build assets. 6. Run the two migrations in timestamp order. 7. Clear/warm standard Laravel caches. 8. Release application code, resume workers if the normal process stopped them, exit maintenance, and run health checks.
- cache_actions: run the release process's standard Laravel optimize clear/warm commands after migration and before traffic resumes
- restart_actions: no new service requirement; follow the existing release process for PHP-FPM and queue worker reloads
- health_checks: request `GET /admin` and expect the existing authentication page or redirect; authenticate in approved QA and verify PT create/edit/view/list pages; confirm status options are Pending, Active, Completed, Cancelled; confirm a manually active past-dated row remains active; confirm Number of sessions and Remarks persist and render; confirm dashboard active count and trainer payable ledger exclude only the intended statuses
- rollback: stop writes, deploy the previous application commit, run `php artisan migrate:rollback --step=2 --force` only if legacy-compatible data is acceptable, rebuild assets, clear/warm caches, and run health checks. To preserve exact pre-release meanings or recover post-release manual distinctions, restore the verified pre-deploy SQLite backup instead of relying on the lossy legacy boolean mapping.
- downtime_and_risk: brief maintenance is recommended because SQLite schema alteration/backfill takes a write lock. Main risks are lock duration and the legacy rollback schema's inability to represent four statuses; mitigate with a verified backup, small migration scope, isolated SQLite up/down tests, and explicit rollback choice.

## Constraints and verification
- implementation_constraints: no dependency upgrades; no production migration during implementation; no live-data mutation; no push, merge, deploy, service restart, external notification, or account change
- test_evidence: strict focused RED/GREEN coverage for manual status/date independence, four-state backfill and rollback, canonical database values, Filament form/filter/table/infolist, dashboard/current scope, renew/cancel, ledger exclusion, number of sessions, and Remarks; focused suite 53 tests/566 assertions passed plus the final enum migration check at 1 test/14 assertions; final full suite 134 tests/1,169 assertions passed; isolated temporary-SQLite migrate/status/rollback/reapply passed; Vite 8.2.1 production build passed with an empty external `envDir` and output outside the repository

## Gates
- reviewer_signoff: pending independent review
- qa_signoff: pending independent QA
- deploy_approval: pending explicit approval
