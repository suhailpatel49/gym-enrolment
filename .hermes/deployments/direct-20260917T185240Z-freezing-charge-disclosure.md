# Feature deployment requirements

- feature: freezing-charge-disclosure
- task_id: direct-20260917T185240Z
- base_commit: `fbe455d6c5192edf6bbb247df6b65a30231675f5`
- feature_commit: the commit containing this artifact; resolve with `git log -1 --format=%H -- .hermes/deployments/direct-20260917T185240Z-freezing-charge-disclosure.md`
- target_branch: staging
- default_branch: origin/main at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: staging
- production_changes_required: yes
- deployment_status: no deployment or push was performed or authorized by this change

## Runtime and dependencies

- runtime_versions: existing PHP 8.4, Laravel 13.26.1, Filament 5.7.6, Livewire 4.4.1, and Vite 8.2.1
- os_packages: none
- language_dependencies: none changed
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

- build_commands: install the existing locked dependencies through the normal release process; run `npm run build`; run `php artisan test --compact`
- deployment_sequence: no deployment was performed. For a separately authorized release, create and verify the backups below, build and test the exact candidate commit, activate it through the existing deployment process, refresh existing application caches, and complete the hosted QA checks.
- cache_actions: refresh existing Laravel caches through the normal release process after activation; no new cache configuration is required
- restart_actions: no service changes; use the existing runtime reload procedure only if the normal release process requires it
- health_checks: confirm the application and tablet enrollment route return their expected responses; authenticate through the tablet flow and verify the exact sentence `Membership freezing is charged separately.` remains visible beside the Membership Freezing Yes/No control before and after selecting Yes on desktop and mobile widths; in an authorized Filament create/edit surface using the shared enrollment schema, verify the same sentence appears as helper text for the Membership Freezing toggle; confirm enrollment review and submission behavior is unchanged
- backup: before any separately authorized hosted release, create a timestamped archive of the current tracked release and an online database backup using the existing production backup procedure, excluding environment files; record checksums and verify database integrity and restore readiness
- rollback: deactivate this candidate and restore the exact pre-release tracked application archive; rebuild assets and Laravel caches through the existing release process; no database rollback is required; repeat the application, tablet authentication, enrollment form, and admin health checks
- downtime_and_risk: no expected downtime; low presentation-only risk. The disclosure is unconditional and uses existing responsive typography, but hosted desktop/mobile rendering still requires QA after a separately authorized deployment.

## Gates

- reviewer_signoff: pending
- qa_signoff: local targeted and full automated verification passed; hosted QA required after a separately authorized deployment
- hosted_browser_signoff: pending; no hosted verification is claimed

## Local verification

- red: focused disclosure tests failed before implementation with 2 tests, 3 assertions, and both failures caused by the missing disclosure
- green_targeted: `php artisan test --compact tests/Feature/EnrollmentFormTest.php` passed 7 tests with 43 assertions
- green_full: `php artisan test --compact` passed 128 tests with 1,043 assertions using the isolated build manifest
- formatter: `vendor/bin/pint --dirty --format agent` passed after formatting the changed test file
- syntax: `php -l` passed for both changed PHP application files, the changed Blade file, and the changed PHPUnit file
- asset_build: Vite 8.2.1 production build passed with an empty temporary `envDir` and isolated `/tmp` output, producing a manifest and six assets; the temporary output was not installed or committed
- whitespace: `git diff --check` passed
- migrations_run: none
- isolation: no environment files were accessed; no push, deployment, shared migration, service restart, or mail was performed
