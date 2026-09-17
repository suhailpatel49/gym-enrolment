# Feature deployment requirements

- feature: freezing-charge-disclosure
- task_id: direct-20260917T185240Z
- base_commit: `fbe455d6c5192edf6bbb247df6b65a30231675f5`
- feature_commit: the commit containing this artifact; resolve with `git log -1 --format=%H -- .hermes/deployments/direct-20260917T185240Z-freezing-charge-disclosure.md`
- target_branch: staging
- default_branch: origin/main at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: staging
- production_changes_required: yes
- deployment_status: deployed to the hosted staging/live checkout under explicit user authorization at `2026-09-17T19:28:42Z`; hosted authenticated browser QA remains pending parent-agent verification

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
- deployment_sequence: completed under explicit authorization. Revalidated the exact clean reviewed candidate and live base, bounded the disclosure-only diff, verified zero pending enrollments/jobs/failed jobs and healthy infrastructure, created and verified the rollback set, atomically advanced the feature and staging refs without force, built isolated production assets, enabled maintenance mode, repeated the zero-count gates, fast-forwarded live code, installed byte-verified assets, refreshed existing Laravel caches, reopened the application, and completed CLI health checks. The first maintenance attempt could not resolve the not-yet-fetched candidate object in the live checkout; its failure handler restored the exact old code/assets and availability, all rollback gates were reverified, the approved staging ref was fetched while live, and the successful activation repeated the full maintenance gates.
- cache_actions: refresh existing Laravel caches through the normal release process after activation; no new cache configuration is required
- restart_actions: no service changes; use the existing runtime reload procedure only if the normal release process requires it
- health_checks: confirm the application and tablet enrollment route return their expected responses; authenticate through the tablet flow and verify the exact sentence `Membership freezing is charged separately.` remains visible beside the Membership Freezing Yes/No control before and after selecting Yes on desktop and mobile widths; in an authorized Filament create/edit surface using the shared enrollment schema, verify the same sentence appears as helper text for the Membership Freezing toggle; confirm enrollment review and submission behavior is unchanged
- backup: `/var/backups/gym-enrolment/direct-freezing-charge-disclosure-20260917T192124Z`; root-owned directory mode `0700`, six non-empty root-owned files mode `0600`, including tracked release, built assets, relevant non-secret runtime config, checksums, and rollback instructions. The online SQLite backup integrity result was exactly `ok` with SHA-256 `47f2d96e661097a26215b2fcc4608ab37ae5b1c77e5ec1e0599422e8a508b3b5`.
- rollback: deactivate this candidate and restore the exact pre-release tracked application archive; rebuild assets and Laravel caches through the existing release process; no database rollback is required; repeat the application, tablet authentication, enrollment form, and admin health checks
- downtime_and_risk: no expected downtime; low presentation-only risk. The disclosure is unconditional and uses existing responsive typography, but hosted desktop/mobile rendering still requires QA after a separately authorized deployment.

## Gates

- reviewer_signoff: PASS — independent Reviewer
- qa_signoff: PASS — independent runtime 34/34 assertions; targeted 7 tests/43 assertions; focused regressions 36 tests/302 assertions; full suite 128 tests/1,043 assertions; isolated Vite build passed with one manifest and six assets
- hosted_browser_signoff: pending parent-agent authenticated browser verification; no authenticated browser verification is claimed here

## Local verification

- red: focused disclosure tests failed before implementation with 2 tests, 3 assertions, and both failures caused by the missing disclosure
- green_targeted: `php artisan test --compact tests/Feature/EnrollmentFormTest.php` passed 7 tests with 43 assertions
- green_full: `php artisan test --compact` passed 128 tests with 1,043 assertions using the isolated build manifest
- formatter: `vendor/bin/pint --dirty --format agent` passed after formatting the changed test file
- syntax: `php -l` passed for both changed PHP application files, the changed Blade file, and the changed PHPUnit file
- asset_build: Vite 8.2.1 production build passed with an empty temporary `envDir` and isolated `/tmp` output, producing a manifest and six assets; the temporary output was not installed or committed
- whitespace: `git diff --check` passed
- migrations_run: none
- isolation: no environment files were accessed; no migration, worker restart, mail, dependency change, or default-branch mutation was performed

## Hosted release evidence

- reviewed_code_commit: `174a4e97a718bd30bced51b95139d9edfac236d3`
- pre_release_live_commit: `fbe455d6c5192edf6bbb247df6b65a30231675f5`
- refs_after_code_activation: `origin/direct/freezing-charge-disclosure` and `origin/staging` both `174a4e97a718bd30bced51b95139d9edfac236d3`; no force push and no main/default/production ref update
- release_build: exact candidate archived into isolated temporary source; unchanged installed Vite 8.2.1 and Composer dependency trees; empty temporary `envDir`; `/tmp` output; one manifest and six assets; installed files matched the generated SHA-256 inventory byte for byte
- runtime_gates: effective database driver SQLite; final pending enrollments, jobs, and failed jobs counts each `0`; Gym Supervisor worker remained `RUNNING` without restart; Nginx syntax valid
- health: local and public HTTPS `/up` and `/enrol` each returned `200` with successful TLS verification; live source contained the exact canonical sentence and both intended consumers
- cache_actions: `php artisan optimize:clear --no-interaction` and `php artisan optimize --no-interaction` completed successfully
- migration_actions: none
- deployment_timestamp: `2026-09-17T19:28:42Z`
- rollback_ready: put Laravel into maintenance mode, require pending enrollments/jobs/failed jobs to remain zero, stop only the Gym worker if rollback requires code replacement, restore tracked code and built assets to `fbe455d6c5192edf6bbb247df6b65a30231675f5` from the verified rollback set, clear/warm existing caches, start the Gym worker if stopped, reopen the application, and repeat local/public health checks. No database rollback is required; retain the online database backup unless a separately authorized restore is necessary after reconciling later writes.
