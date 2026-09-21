# Feature deployment requirements

- feature: combined manual PT status, sidebar visibility, dashboard tables, and enrolment-only reset
- task_id: direct-20260921T045855Z
- feature_commit: the commit containing this artifact; resolve the exact full SHA with `git log -1 --format=%H -- .hermes/deployments/direct-20260921T045855Z-combined-pt-navigation-dashboard-reset.md`
- expected_parent: reviewed correction candidate `e903a16d6ca01ffc518d20160e566fe046b6e226`; the release commit must be its exact direct child
- live_base: `729c0d424128e75df2fd87aaabe3ee5bde6b5b0c`
- target_branch: staging
- default_branch: origin/main (`36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b` when last fetched and verified for this feature line)
- staging_branch: staging
- production_changes_required: yes
- destructive_authorization: the user explicitly authorized a one-time production deletion of enrolment data only: “remove only the enrolment data. We will start from zero.” This authorization does not permit deletion from any other table.
- supersedes: for this release candidate, this record supersedes older deployment records only where they describe Dashboard widget identities, sidebar verification, the two 2026-09-21 PT migrations, or enrolment reset/rollback. All unrelated safety requirements remain in force.

## Runtime and dependencies

- runtime_versions: PHP 8.4; Laravel 13.26.1; Filament 5.7.6; PHPUnit 12.5.33; Vite 8.2.1; existing locked dependencies only
- os_packages: none
- language_dependencies: none changed
- lockfile_changes: none
- environment_variable_names: none added or changed

## Data and asynchronous processing

- database_migrations: after confirming the database is at the live-base migration state, run pending migrations in filename order: `2026_09_21_100139_add_training_status_to_personal_training_members_table.php`, then `2026_09_21_101122_add_number_of_sessions_to_personal_training_members_table.php`. Stop if any unexpected migration is pending.
- database_backfill: the status migration snapshots existing PT meaning on the deployment date: legacy inactive rows become `cancelled`; otherwise future-start rows become `pending`, past-end rows become `completed`, and remaining rows become `active`. It replaces the legacy `active` column/index with `training_status` and its index. The sessions migration adds nullable `number_of_sessions`; existing rows remain `NULL`. On SQLite it creates `BEFORE INSERT` and `BEFORE UPDATE` triggers that permit `NULL` or an integer of at least 1 and abort zero, negative, fractional, or non-integer storage with `Invalid personal training number of sessions`; it does not execute SQLite trigger SQL on other drivers.
- enrolment_reset_prerequisites: perform the reset only under maintenance after intake is closed, workers are stopped, in-flight requests are drained, the release SHA is verified, migrations and cache preparation succeed, a full online SQLite backup is verified, and all three counts are exactly zero: pending enrolments (`enrollments.approval_status = 'pending'`), `jobs`, and `failed_jobs`. Any nonzero count is a fail-closed stop, not permission to discard or process it.
- backup_and_integrity: using the deployment runbook's explicitly resolved absolute SQLite database path and a new root-owned mode-0600 backup path, run SQLite's online `.backup`; record the source path, backup path, UTC timestamp, byte size, and SHA-256 checksum. Run `PRAGMA integrity_check;` against both source and backup and require the sole result `ok`. Open the backup read-only and confirm its schema and row counts before proceeding. Never overwrite an earlier backup.
- before_counts: immediately before the reset transaction, record and sign off exact row counts for `enrollments`, `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `trainers`, `personal_training_members`, and `migrations`; also record the pending-enrolment count and the `sqlite_sequence` row for `enrollments`. Discover user tables from `sqlite_schema` and stop if any additional table is present until its count is added to the protected snapshot. Every table except `enrollments` is protected.
- one_time_reset: with SQLite foreign keys enabled and the resolved production database explicitly selected, execute one `BEGIN IMMEDIATE` transaction containing only `DELETE FROM enrollments;` and `DELETE FROM sqlite_sequence WHERE name = 'enrollments';`, then `COMMIT`. Do not use `TRUNCATE`, `DROP`, schema recreation, a broad wipe command, wildcard table selection, or any deletion against another table.
- reset_verification: before leaving maintenance, require `SELECT COUNT(*) FROM enrollments;` to return `0`, require no `sqlite_sequence` row for `enrollments` (so the next inserted enrolment starts from the clean sequence), and compare every protected-table count byte-for-byte with the signed pre-reset snapshot. Require `PRAGMA foreign_key_check;` to return no rows and `PRAGMA integrity_check;` to return only `ok`. A mismatch is an immediate rollback condition.
- database_rollback: exact rollback of the destructive reset or of migrations requires taking the application offline and restoring the verified pre-release full database backup. If schema rollback is separately authorized, roll back the sessions migration first, dropping `personal_training_members_sessions_insert` and `personal_training_members_sessions_update` before dropping `number_of_sessions`, then roll back the status migration. Migration `down()` alone is not an acceptable reset rollback because it cannot restore deleted enrolments and the legacy PT boolean cannot preserve all four manual statuses.
- database_locking_and_duration: SQLite migration/backfill and the reset transaction take write locks. Keep the application under maintenance, do not interrupt either operation, and abort before mutation if an exclusive/required lock cannot be obtained promptly.
- queues: no queue configuration change; `jobs = 0` and `failed_jobs = 0` are hard preconditions before the backup/reset window
- cron: none
- workers: stop the existing worker only after queues are confirmed drained; restart it through the normal release procedure after activation and verification

## Service and infrastructure changes

- systemd: no configuration change; verify PHP 8.4 FPM and Nginx are active after release
- supervisor: no configuration change; verify `gym-enrolment-worker` is RUNNING after the application leaves maintenance
- nginx: no configuration change; run `nginx -t` before resuming traffic and fail closed on any warning treated as an error or nonzero status
- storage_and_permissions: preserve existing application/database ownership and modes; the new backup directory remains root-only and each backup remains mode 0600
- external_services: no new service and no external mail during deployment verification

## Build and release procedure

- build_commands: install unchanged lockfiles with `composer install --no-scripts --no-interaction --prefer-dist` and `npm ci --ignore-scripts`; run the production Vite build; run the approved focused and full PHPUnit commands before activation
- exact_commit_gate: reviewer and QA must approve the same full commit SHA resolved from this document. Before any production action, require the release checkout's `git rev-parse HEAD` to equal that exact approved SHA, require its parent to be `e903a16d6ca01ffc518d20160e566fe046b6e226`, and require ancestry from live base `729c0d424128e75df2fd87aaabe3ee5bde6b5b0c`. Stop on a dirty checkout, SHA mismatch, ancestry mismatch, unsigned count record, or missing approval.
- deployment_sequence: 1. Obtain independent reviewer, QA, and explicit deployment approval for the exact commit. 2. Build and test an immutable release without reading production environment files. 3. Announce the maintenance window, close enrolment intake, enable maintenance mode, drain requests, confirm pending enrolments/jobs/failed jobs are all zero, then stop workers. 4. Create and verify the full online SQLite backup and checksum. 5. Apply only the two expected migrations in timestamp order and verify migration/backfill results. 6. Perform the standard cache clear/warm steps. 7. Record the complete pre-reset table-count snapshot. 8. Execute the single enrollment-only reset transaction and its immediate protected-count, foreign-key, integrity, and sequence checks. 9. Activate the exact reviewed release, validate Nginx/PHP/static assets/HTTPS, restart the worker, and leave maintenance. 10. Run authenticated hosted verification without creating enrolments or sending mail.
- cache_actions: use only the established Laravel optimize clear/warm procedure after migrations and before the protected count snapshot; do not run a database cache clear after that snapshot until reset verification is complete
- restart_actions: follow the existing release runbook to reload PHP 8.4 FPM and restart the Supervisor worker only after database verification; validate Nginx configuration before any reload
- static_and_https_checks: require `nginx -t` success, active Nginx and PHP-FPM, a successful HTTPS `/up` response without redirecting to HTTP, a valid certificate/hostname, and HTTP 200 for every hashed CSS/JS asset referenced by the production manifest. Confirm the built theme contains the scoped sidebar white foreground and `background-color:#ffffff1a` interaction rules.
- hosted_admin_checks: authenticate with an approved existing admin account. Verify Dashboard mounts `OverdueBalances` and `RenewalsDue` only, with no `EnrollmentStats` or `PersonalTrainingStats` mini cards; verify both admin and staff retain their existing table permissions. At desktop and mobile widths in light and dark mode, verify sidebar brand, group labels/icons, normal items, active items, and both `.fi-sidebar-group-collapse-btn` and `.fi-sidebar-group-dropdown-trigger-btn` in normal, hover, and keyboard-focus states while topbar/content contrast and mobile open/close controls remain unchanged. Verify PT status/session fields, including a session count above 10,000, and current-scope behavior without creating enrolments or sending mail.
- rollback: on any reset, migration, activation, protected-count, integrity, worker, Nginx, HTTPS, static-asset, or authenticated-check failure, re-enter/retain maintenance, stop workers and writes, restore the verified pre-release SQLite backup through the established recovery procedure, deploy the previous application release, rebuild/restore its assets, clear/warm its caches, run integrity/foreign-key/count checks, and only then resume traffic. Preserve the failed release database and logs for review; never attempt to reconstruct deleted enrolments manually.
- fail_closed_conditions: stop before destructive mutation for any unapproved/mismatched commit, dirty release, unexpected pending migration/table, missing or unverifiable backup, integrity result other than `ok`, nonzero pending enrolments/jobs/failed jobs, inability to enter maintenance or stop workers, incomplete count snapshot, database lock failure, or ambiguous database path. After mutation, remain in maintenance and restore the backup for any enrollment count/sequence failure, protected count change, foreign-key/integrity failure, application error, inaccessible HTTPS endpoint, missing static asset, unreadable required UI state, or worker/service failure.
- downtime_and_risk: maintenance is mandatory. The authorized enrolment deletion is irreversible without the verified backup; SQLite schema/backfill/reset operations lock writes; cache/session counts can change if traffic resumes prematurely. Mitigations are the exact-SHA gate, drained queues, stopped workers, online backup plus integrity/checksum verification, complete table-count snapshot, one narrow transaction, immediate protected-data comparison, and fail-closed restore.

## Gates

- reviewer_signoff: pending independent review of the exact commit
- qa_signoff: pending independent QA of the exact reviewed commit, including real Chromium desktop/mobile light/dark computed styles
- deploy_approval: required again for the exact reviewed commit and maintenance window before any production mutation

## Implementation constraints

- No production deployment, migration, live database read/write, record deletion, mail, push, merge, service restart, or environment-file access occurred during this implementation.

## Local verification

- Correction RED: the number-of-sessions form contract failed because 10,001 was rejected (1 test, 21 assertions before failure); the disposable-SQLite migration contract failed all 10 invalid insert/update cases because the schema accepted zero, negative, fractional, fractional-string, and text values, and its trigger-presence assertion found zero triggers (11 expected failures, 23 assertions before failure); the sidebar contract failed at the missing collapse-button icon selector (1 test, 4 assertions before failure).
- Correction focused GREEN: the number-of-sessions form contract passed 1 test with 37 assertions; the SQLite insert/update trigger matrix and rollback-order contract passed 11 tests with 35 assertions; the collapse/dropdown group-control foreground and hover/focus source contract passed 1 test with 18 assertions.
- Correction final verification: the complete PT/ledger suite passed 59 tests with 571 assertions and the full suite passed 146 tests with 1,230 assertions. Disposable SQLite fresh migrate, rollback of the last two migrations, and reapply passed; rollback reported 0 status/session columns and 0 sessions triggers, reapply reported 2 columns and 2 triggers, and integrity checks returned `ok`. Pint, PHP syntax checks for all changed PHP files, and `git diff --check` passed. Vite 8.2.1 built 4 modules using empty external envDir `/tmp/gym-direct-ui-empty-env.fRHP6e` and external output `/tmp/gym-direct-ui-build.eNIeWc`; emitted CSS contains both group-control icon selectors, active-group selectors with higher specificity than Filament's base rule, and sidebar-scoped collapse/dropdown hover/focus rules with `background-color:#ffffff1a`.

- RED navigation: the new focused source contract failed at the missing `.fi-sidebar .fi-logo` readable-foreground override (1 test, 1 expected failing assertion).
- RED dashboard: the real rendered/dashboard discovery contract failed for admin and staff because the old subheading and all four discovered widgets were still present; the underlying stats contract cases continued to pass (5 tests, 2 passed, 3 expected failures, 13 assertions before failure).
- GREEN navigation/dashboard: 3 focused theme tests passed with 31 assertions; 7 dashboard/PT tests passed with 39 assertions. The broader navigation/theme/dashboard/staff command passed 23 tests with 199 assertions.
- PT suite: 48 focused PT tests passed with 536 assertions, including retained direct coverage of `PersonalTrainingStats` current/manual-status calculations even though it is no longer mounted on Dashboard.
- migration checks: PT migration up/down/reapply coverage passed 4 tests with 28 assertions; approval/combined release migration coverage passed 5 tests with 30 assertions.
- full suite: 135 tests passed with 1,189 assertions and no failures.
- formatting_and_syntax: `vendor/bin/pint --dirty --format agent`, PHP syntax checks for every changed PHP file, and `git diff --check` passed.
- isolated_build: Vite 8.2.1 production build passed with 4 modules and output under `/tmp/gym-direct-ui-build`; the wrapper used empty `/tmp/gym-direct-ui-empty-env` as `envDir`. Generated CSS contains scoped sidebar header `background-color:var(--color-secondary)`, normal/active descendant `color:var(--color-white)`, and URL-item hover/focus `background-color:#ffffff1a` declarations. The resulting white-on-`#1d2229` contrast calculates to approximately 15.99:1, and white over the 10% interaction surface to approximately 11.8:1.
