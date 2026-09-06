# Feature deployment requirements

- feature: simple-personal-training
- task_id: direct-pt (direct request; no project workflow)
- feature_commit: the commit containing this artifact; resolve with `git log -1 --format=%H -- .hermes/deployments/direct-pt-simple-personal-training.md`
- default_branch: origin/main; exact implementation base `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: staging (unchanged)
- production_changes_required: yes
- deployment_status: no deployment performed or authorized by this change; no push or shared database migration performed.

## Runtime and dependencies
- runtime_versions: PHP 8.4; locked Laravel 13.26.1 and Filament 5.7.6; existing Vite 8 frontend. Use Node 22+ for the locked development dependencies.
- os_packages: none added
- language_dependencies: none changed; install existing lockfiles only
- lockfile_changes: none
- environment_variable_names: none; no new env vars. No `.env` file is needed for this feature.

## Data and asynchronous processing
- database_migrations: required, in order: `database/migrations/2026_09_06_000001_create_trainers_table.php`, then `database/migrations/2026_09_06_000002_create_personal_training_members_table.php`. Creates two new tables; the member trainer FK restricts deletion. No existing enrollment or user schema changes.
- database_backfill: none; no seed data
- database_backup: before any separately authorized release, take and verify a restorable database backup using the existing production backup procedure. Record the snapshot identifier and retain it through validation and rollback.
- database_rollback: the `down()` methods drop PT members first, then trainers. This destroys all PT data, so export or back up new PT records before rollback. From the release containing these files, use `php artisan migrate:rollback --path=database/migrations/2026_09_06_000002_create_personal_training_members_table.php --force`, then `php artisan migrate:rollback --path=database/migrations/2026_09_06_000001_create_trainers_table.php --force`. Verify these are the latest migration batch before using these commands; otherwise stop and use an operator-reviewed targeted rollback. Never roll back unrelated migrations.
- database_locking_and_duration: creates empty tables plus indexes/FK; brief schema locks expected, duration depends on the database. No bulk updates or backfills.
- queues: none
- cron: none
- workers: none; no worker changes or restarts required
- notifications: none; no email, SMS, database notifications, or UI save notifications added

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: none

## Build and release
- build_commands: `composer install --no-interaction --prefer-dist --no-scripts`; `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: no deployment in this task. For a separately authorized future release: (1) verify backup and release rollback target; (2) build the release using existing dependencies; (3) run the two migrations above in order using `php artisan migrate --path=<migration-file> --force`, before activating code that discovers the new widget/resources; (4) activate the release through the existing deployment process; (5) refresh existing application caches; (6) perform health checks. Do not seed.
- cache_actions: during a future authorized release, refresh existing cached routes/config/views through the normal release process (for example, `php artisan optimize:clear` then `php artisan optimize`).
- restart_actions: no new services; use existing application runtime reload procedure only if required by the release process.
- health_checks: `php artisan migrate:status` lists both PT migrations as applied. As admin and staff, open `/admin/trainers` and `/admin/personal-training-members`; list/create/view/edit must work, delete actions must be absent. Confirm four PT statistics appear alongside existing dashboard content. Confirm a renewed test membership resets both payments and a duplicate open confirmation does not renew twice. Guests must redirect to `/admin/login`. Use disposable staging records for write checks, not production records.
- rollback: disable access and stop writes; back up any new PT data; revert application code to the prior release and refresh its caches. Retaining the two additive tables preserves PT data and is the preferred application rollback. If schema removal is explicitly required, run the member-then-trainer rollback above using the feature release before retiring it. Restore the verified backup only under the existing recovery procedure, accounting for unrelated writes since the snapshot.
- downtime_and_risk: minimal schema work; avoid exposing the new resources/widget before migration, since they query the new tables. Schema rollback loses PT records. Renewal changes the current record only: no history, billing engine, scheduling, or automated processing. Active means the active flag is true and end date is today or later; pending-payment counts use that same set, and expiry counts include today through seven days ahead. Inactive trainers remain selectable so existing relationships remain editable.

## Gates
- reviewer_signoff: independent read-only GPT-6 Astra review completed; no actionable correctness, security, or scope findings. No release signoff implied.
- qa_signoff: focused tests passed (25 tests, 318 assertions); full `php artisan test --compact` passed (50 tests, 505 assertions). Tests used in-memory SQLite and a process-only test APP_KEY, without an environment file. `vendor/bin/pint --dirty --format agent` passed. `npm run build` passed (Vite 8.2.1, 3 modules; optional fontaine warning only). Git whitespace checks required before commit; delivery reports final status. The initial full run needed the missing Vite manifest; building assets resolved it without changing existing tests.
