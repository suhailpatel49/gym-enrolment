# Feature deployment requirements

- feature: form-email-logo
- task_id: direct-20260917T193754Z
- base_commit: `f402f7126c76e713d3b60b63e1cb8d3ca888e15d`
- reviewed_code_commit: `e695fdb96cbc8a8de7b547f80157b478e832f2af`
- feature_commit: `e695fdb96cbc8a8de7b547f80157b478e832f2af` plus the docs-only deployment-evidence follow-up containing this update
- target_branch: staging
- default_branch: origin/main at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`; implementation remains on the explicitly approved exact live base above
- staging_branch: staging
- production_changes_required: yes
- deployment_status: deployed to the hosted staging/live checkout under explicit user authorization at `2026-09-17T20:08:17Z`; authenticated hosted browser and representative external email-client rendering remain pending parent-agent verification

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
- deployment_sequence: completed under explicit authorization. Revalidated the exact clean reviewed candidate and expected live base, bounded the seven-file diff, verified the exact PNG, zero pending enrollments/jobs/failed jobs, and healthy infrastructure; created and verified the rollback set; atomically advanced the feature and staging refs without force; built and tested an environment-file-free isolated candidate; entered maintenance without a bypass secret; repeated zero-count gates; fetched and fast-forwarded live; installed byte-verified built assets and the tracked PNG; refreshed existing Laravel caches; reopened the application; and completed CLI health, static-asset, and isolated array-mail CID checks
- cache_actions: use the existing release process to clear and rebuild Laravel configuration, route, and view caches after activation
- restart_actions: none required; use the existing runtime reload procedure only if the normal release process requires it
- health_checks: local-origin and public HTTPS `/up` and `/enrol` returned `200` with successful TLS verification; the direct PNG returned `200 image/png`, 84,965 bytes, and the exact expected checksum; authenticate through the tablet flow and verify the logo is sharp, visible on its dark container, does not overflow, and leaves Logout usable at hosted desktop and mobile widths; representative authenticated hosted browser and external email-client rendering remain pending the parent agent
- backup: `/var/backups/gym-enrolment/direct-form-email-logo-20260917T200447Z`; root-owned directory mode `0700`, seven non-empty root-owned files mode `0600`, with tracked release and built assets, non-secret Nginx/Supervisor configuration, checksums, metadata, and rollback instructions. All `.env`/`.env.*` paths were excluded. The online Python stdlib SQLite backup returned exactly `ok` from an independent `PRAGMA integrity_check` and has SHA-256 `eaf9dcee2368424bed3e06d23f738252f83103cab2e7ad55d04b854dce10d9c4`.
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

- reviewer_signoff: PASS — independent Reviewer for reviewed code commit `e695fdb96cbc8a8de7b547f80157b478e832f2af`
- qa_signoff: PASS — independent QA for reviewed code commit `e695fdb96cbc8a8de7b547f80157b478e832f2af`; independent probe 81/81 checks, focused 23 tests/254 assertions, full 130 tests/1,068 assertions, and isolated Vite build passed; PNG SHA-256 `7c01ad394253b9b68672ab29a3311b7e070efc37f25d3233700c7b232b2a6720`, RGBA 1194×298
- hosted_desktop_signoff: pending parent-agent authenticated browser verification
- hosted_mobile_signoff: pending parent-agent authenticated browser verification
- hosted_email_signoff: pending parent-agent representative external email-client rendering; no external mail was sent during release

## Hosted release evidence

- reviewed_code_commit: `e695fdb96cbc8a8de7b547f80157b478e832f2af`
- pre_release_live_commit: `f402f7126c76e713d3b60b63e1cb8d3ca888e15d`
- refs_after_code_activation: `origin/direct/form-email-logo` and `origin/staging` both reached `e695fdb96cbc8a8de7b547f80157b478e832f2af` by one atomic non-force push; `origin/main` remained unchanged
- release_build: exact candidate archived without environment files; Vite 8.2.1 built with an empty temporary `envDir` to isolated `/tmp` output; six manifest-referenced assets and seven files were generated and installed with a byte-identical SHA-256 inventory
- release_tests: the environment-file-free isolated candidate passed `EnrollmentMailContentTest` and `VisualIdentityTest` with 13 tests and 175 assertions using in-memory SQLite and array mail. The first harness run exposed a symlinked Composer test-path isolation issue and was discarded; the corrected physical dependency tree passed.
- runtime_gates: effective database driver SQLite; final pending enrollments, jobs, and failed jobs counts each `0`; Gym Supervisor worker remained `RUNNING` without restart; Nginx syntax valid
- health: local and public HTTPS `/up` and `/enrol` each returned `200` with TLS verification; all six built assets were healthy; direct `/images/incline-fitness-logo.png` returned `200 image/png`, 84,965 bytes, with SHA-256 `7c01ad394253b9b68672ab29a3311b7e070efc37f25d3233700c7b232b2a6720`
- deployed_source: live was clean at the reviewed commit, with exactly one tracked logo asset and the intended enrollment-form and member-confirmation-email consumers
- local_mail_probe: an environment-file-free isolated archive of live code constructed the real `EnrollmentConfirmation` with a fake unsaved model and array mail only; it produced one `cid:` image, one inline PNG with the exact expected checksum, and all eight membership terms in order. No external delivery or live database mutation occurred.
- cache_actions: `php artisan optimize:clear --no-interaction` and `php artisan optimize --no-interaction` completed successfully
- migration_actions: none
- worker_actions: none; restart was not required
- deployment_timestamp: `2026-09-17T20:08:17Z`
- rollback_ready: put Laravel into maintenance mode without a bypass secret, require pending enrollments/jobs/failed jobs to remain zero, stop only the Gym worker if code replacement requires it, restore tracked code and built assets to `f402f7126c76e713d3b60b63e1cb8d3ca888e15d` from the verified rollback set, clear/warm existing caches, start the worker only if stopped, reopen the application, and repeat worker/count, Nginx, local/public HTTPS, and static-asset checks. No database rollback is required; retain the online database backup unless a separately authorized restore is necessary after reconciling later writes.
