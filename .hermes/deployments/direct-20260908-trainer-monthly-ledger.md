# Feature deployment requirements

- feature: trainer-monthly-ledger
- task_id: direct-20260908 (direct request; no project workflow)
- feature_commit: the commit containing this artifact; resolve with `git log -1 --format=%H -- .hermes/deployments/direct-20260908-trainer-monthly-ledger.md`
- target_branch: staging
- default_branch: origin/main; exact implementation base `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: staging
- production_changes_required: yes
- deployment_status: no deployment, push, shared database migration, or service restart was performed or authorized by this change

## Runtime and dependencies

- runtime_versions: PHP 8.4; locked Laravel 13.26.1 and Filament 5.7.6; Vite 8 frontend verified with Node 20.20.2 and npm 10.8.2
- os_packages: none added
- language_dependencies: none changed
- lockfile_changes: none
- environment_variable_names: none; no new environment variables

## Data and asynchronous processing

- database_migrations: run `database/migrations/2026_09_09_002809_expand_personal_training_members_for_trainer_ledger.php` after the two existing PT prerequisite migrations, then run `database/migrations/2026_09_09_011948_enforce_personal_training_member_money_invariants.php`
- database_backfill: the ledger migration renames the existing client-name and fee columns in place, adds ledger columns with safe defaults, and sets every existing trainer amount equal to its existing total client amount (zero gym commission); the invariant migration preflights every existing row against its shared nonnegative, two-decimal, `decimal(10,2)` range, gym-not-above-total, and exact trainer-split predicate before installing SQLite insert/update triggers with that same predicate
- database_rollback: roll back `2026_09_09_011948_enforce_personal_training_member_money_invariants.php` first to remove its triggers, then—after verifying the ledger migration is the latest remaining batch—roll back `2026_09_09_002809_expand_personal_training_members_for_trainer_ledger.php`; the latter restores the original column names and preserves client names and total fees, but drops payment mode, gym/trainer split, and remarks, so take and verify a restorable backup first
- database_locking_and_duration: column renames, column additions, index creation, the split backfill, trigger creation, and the invariant migration's full-table validation require schema/write locks whose duration scales with the PT table; inspect row count and rehearse both migrations on a production-sized staging copy before scheduling a maintenance window
- queues: none
- cron: none
- workers: none

## Service and infrastructure changes

- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: none

## Build and release procedure

- build_commands: `composer install --no-interaction --prefer-dist --no-scripts`; `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: no deployment in this task. For a separately authorized release: (1) take and verify a restorable database backup; (2) build the release from locked dependencies; (3) enable the normal maintenance or atomic-release protection because the old code and renamed columns are not mutually compatible; (4) run the ledger expansion migration; (5) run the money-invariant migration; if its legacy-data preflight fails, repair the invalid rows and retry—the failed attempt creates no triggers; (6) activate the feature release; (7) refresh application caches; (8) disable maintenance protection; (9) perform the health checks
- cache_actions: use the existing release procedure, such as `php artisan optimize:clear` followed by `php artisan optimize`
- restart_actions: no new services; use the existing PHP runtime reload only if the release procedure requires it
- health_checks: `php artisan migrate:status` shows both ledger migrations as applied; as admin or staff, create and edit a PT entry and confirm trainer amount always equals total client amount minus gym amount; open a trainer details page and confirm monthly rows are grouped by start month, cancelled entries are excluded from summary counts/totals, pending and settled rows have visible text states, and month details paginate after 12 entries with working navigation; guests must still redirect to `/admin/login`
- rollback: re-enable maintenance protection, stop PT writes, take a current backup/export, roll back the money-invariant migration before the ledger expansion migration, activate the prior application release, refresh caches, disable maintenance protection, and repeat the prior-release health checks; restore a backup only through the existing recovery procedure
- downtime_and_risk: a short maintenance window is expected because the ledger migration renames columns used by the previous release and the invariant migration scans all PT rows under a write lock. Primary risks are an invariant violation stopping migration, lock duration on a large PT table, and loss of newly captured ledger-only fields during schema rollback; mitigate with row-count and data-quality review, staging rehearsal, verified backups, and a controlled write freeze

## Gates

- reviewer_signoff: pending
- qa_signoff: local retry-safety verification passed: observed RED at the old post-trigger validation, then GREEN fail-repair-retry-down-up regression (1 test with 6 assertions), PT migration suite (20 tests with 92 assertions), focused ledger suite (14 tests with 70 assertions), 44 relevant tests with 435 assertions, 69 full-suite tests with 622 assertions, Pint, both SQLite triggers restored after retry and down/up, Vite production build, Composer audit with no advisories, and npm audit with zero vulnerabilities
