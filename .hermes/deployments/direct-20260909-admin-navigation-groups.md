# Feature deployment requirements

- feature: admin-navigation-groups
- task_id: direct-20260909-admin-navigation-groups
- base_commit: 76b082472d54819fb2f752f1125b42281c1cedeb
- feature_commit: 52fa9057e24b852b1d89bd54f581232bf6666361
- target_branch: staging
- default_branch: origin/main
- staging_branch: staging
- production_changes_required: no

## Runtime and dependencies

- runtime_versions: PHP 8.4; Laravel 13.26.1; Filament 5.7.6
- os_packages: none
- language_dependencies: none
- lockfile_changes: none
- environment_variable_names: none

## Data and asynchronous processing

- database_migrations: none
- database_backfill: none
- database_rollback: none
- database_locking_and_duration: none
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

- build_commands: `npm run build`; `php artisan test --compact`
- deployment_sequence: after approval, back up the current hosted release and database; integrate candidate `52fa9057e24b852b1d89bd54f581232bf6666361` into `staging`; install from existing lockfiles; run `npm run build`; run the test suite; promote the verified release using the existing deployment process; perform the hosted checks below
- cache_actions: use the existing deployment process to clear and rebuild Laravel caches after release activation
- restart_actions: use the existing deployment process to reload PHP-FPM and restart the queue worker only if release activation requires it; no service configuration changes
- health_checks: confirm the application and `/admin` return successful responses; sign in through the existing admin authentication flow; verify Membership overview and Users remain ungrouped at the top; verify one `Enrolment` section contains Enrollments; verify one `PT` section contains Personal Training Members followed by Trainers; open each navigation item and confirm its existing URL and authorization behavior; verify the deployed admin content contrast remains readable
- backup: before hosted release activation, create a timestamped archive of the current tracked release and an online SQLite backup, excluding environment files; record checksums and confirm the database integrity check succeeds
- rollback: deactivate the candidate and restore the release at base `76b082472d54819fb2f752f1125b42281c1cedeb` from the verified archive; rebuild caches and assets; no database rollback is required; repeat the application, authentication, URL, authorization, and contrast health checks
- downtime_and_risk: no expected downtime; low presentation-only risk, mitigated by the focused navigation contract, full test suite, asset build, pre-release backup, and hosted sidebar verification

## Verification evidence

- red: `php artisan test --compact tests/Feature/AdminNavigationTest.php` failed with 1 test and 1 assertion because only the ungrouped navigation section existed
- green_targeted: `php artisan test --compact tests/Feature/AdminNavigationTest.php` passed with 1 test and 2 assertions
- green_full: `php artisan test --compact` passed with 116 tests and 1,057 assertions after the test environment supplied its application key and `npm run build` produced the required Vite manifest
- formatter: `vendor/bin/pint --dirty --format agent` passed
- asset_build: `npm run build` passed with Vite 8.2.1
- whitespace: `git diff --check` passed
- migrations_run: none

## Gates

- reviewer_signoff: pending
- qa_signoff: pending
