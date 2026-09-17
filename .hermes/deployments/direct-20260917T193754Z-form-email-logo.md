# Feature deployment requirements

- feature: form-email-logo
- task_id: direct-20260917T193754Z
- base_commit: `f402f7126c76e713d3b60b63e1cb8d3ca888e15d`
- feature_commit: the commit containing this artifact; resolve with `git log -1 --format=%H -- .hermes/deployments/direct-20260917T193754Z-form-email-logo.md`
- target_branch: staging
- default_branch: origin/main at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`; implementation remains on the explicitly approved exact live base above
- staging_branch: staging
- production_changes_required: yes
- deployment_status: no push, deployment, migration, service restart, or external mail was performed

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
- queues: no configuration or behavior changes; the existing member confirmation remains queued as before
- cron: none
- workers: no changes

## Service and infrastructure changes

- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: deploy the tracked `public/images/incline-fitness-logo.png` with the same ownership and read permissions as existing public assets
- external_services: none; the existing mail transport embeds the tracked PNG as an inline CID part

## Build and release procedure

- build_commands: install existing locked dependencies through the normal release process; run `npm run build`; run `php artisan test --compact`
- deployment_sequence: no deployment was performed. For a separately authorized release: (1) create and verify a restorable backup of the tracked application release and current built assets, excluding environment files; (2) build the exact candidate from existing lockfiles; (3) activate the code, tracked PNG, and generated assets through the existing release process; (4) refresh existing Laravel caches; (5) perform the hosted checks below
- cache_actions: use the existing release process to clear and rebuild Laravel configuration, route, and view caches after activation
- restart_actions: none required; use the existing runtime reload procedure only if the normal release process requires it
- health_checks: confirm the application and tablet enrollment routes return their expected responses; authenticate through the tablet flow and verify the Incline Fitness logo is sharp, visible on its dark container, does not overflow, and leaves the Logout action usable at hosted desktop and mobile widths; create a clearly disposable approved enrollment in an authorized QA environment using a non-external mail capture and verify the member HTML email has one visible logo, a `cid:` source, one inline PNG part with checksum `7c01ad394253b9b68672ab29a3311b7e070efc37f25d3233700c7b232b2a6720`, unchanged member data, and all eight terms in order; verify the plain-text confirmation, admin notification, and PDF remain semantically and visually unchanged
- backup: before any separately authorized hosted activation, archive the current tracked release and built assets without environment files, record checksums, and verify the archive is readable
- rollback: deactivate this candidate and restore the prior tracked release and built assets from the verified backup; refresh existing Laravel caches; no database rollback is required; repeat application, tablet form, admin notification, member email, plain-text email, and PDF checks
- downtime_and_risk: no expected downtime; low presentation and email-client compatibility risk. Mitigate with the exact-byte asset checksum, responsive desktop/mobile QA, CID inspection in captured email source, and representative hosted email-client rendering before release signoff

## Verification evidence

- red: in the isolated no-environment-file copy, the two focused tests failed with 2 tests, 3 assertions, and 2 expected failures: the tracked PNG did not exist and the constructed member HTML message had no `Incline Fitness` image
- green_targeted: the focused logo tests passed with 2 tests and 25 assertions; the complete `EnrollmentMailContentTest` and `VisualIdentityTest` files passed with 13 tests and 175 assertions
- green_full: `php artisan test --compact` passed in the isolated no-environment-file copy with 130 tests and 1,068 assertions
- real_email_render: the focused member-email test constructed the real Symfony message through Laravel's array mail transport with clearly fake member data and verified one `cid:` logo reference, one inline PNG part, the exact PNG checksum, conservative dimensions and responsive style, all eight accepted terms, and no logo added to the admin notification
- formatter: `vendor/bin/pint --dirty --format agent` passed
- syntax: `php -l` passed for both changed PHPUnit files and all three changed Blade files
- asset_build: Vite 8.2.1 production build passed with empty `/tmp/gym-logo-empty-env` as `envDir` and isolated `/tmp/gym-logo-vite-output` output, producing one manifest and six assets; output was not installed or committed
- whitespace: `git diff --check` passed
- migrations_run: none
- isolation: no environment files were accessed; no migration, push, deployment, service restart, or external mail was performed

## Gates

- reviewer_signoff: pending
- qa_signoff: pending
- hosted_desktop_signoff: pending after a separately authorized deployment
- hosted_mobile_signoff: pending after a separately authorized deployment
- hosted_email_signoff: pending after a separately authorized deployment
