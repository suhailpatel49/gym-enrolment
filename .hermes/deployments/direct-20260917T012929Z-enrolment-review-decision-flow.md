# Feature deployment requirements

- feature: enrollment-review-decision-flow
- task_id: direct-20260917T012929Z
- feature_commit: SELF (resolve to the commit containing this document)
- target_branch: staging
- default_branch: origin/main at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: origin/staging base `60ff7fb86ad3f7ac919458e6108fe10e55c3648f`
- production_changes_required: yes
- deployment_performed: no

## Runtime and dependencies
- runtime_versions: existing PHP 8.4, Laravel 13.26.1, Filament 5.7.6, Livewire 4.4.1, and Vite 8.2.1
- os_packages: none
- language_dependencies: none changed
- lockfile_changes: none
- environment_variable_names: none added or changed

## Data and asynchronous processing
- database_migrations: apply `database/migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php` after the existing staging migrations
- database_preflight: while the legacy approval UI is active, pause intake and run `php artisan tinker --execute 'echo \App\Models\Enrollment::query()->where("approval_status", "pending")->count().PHP_EOL;'`; the only acceptable result is `0`. If nonzero, resolve every pending enrollment through the legacy approval workflow and repeat the command. The migration independently checks the same condition before DDL and fails with the count and remediation if it is nonzero.
- database_backfill: none; approved rows remain approved. No pending mapping is invented: migration is fail-closed until the exact zero-pending gate passes.
- database_schema: widens `approval_status` to include `rejected` and adds nullable unique `decision_token_hash` for new direct decisions; legacy rows retain null token hashes
- database_rollback: pause intake, then run `php artisan migrate:rollback --path=database/migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php --force`; rejected records are deterministically converted to pending before the old constraint is restored, and `decision_token_hash` is removed
- database_locking_and_duration: changing the enrollment status enum/check constraint and adding the unique token index may rebuild or lock the enrollments table depending on the production database engine and table size; production-engine duration is unmeasured
- queues: Approve inserts the existing queued `EnrollmentConfirmation` into the `jobs` table inside the same database transaction as the enrollment and explicitly dispatches before commit; rollback removes both writes on queue insertion failure. Continue, Go Back, and Reject queue nothing.
- queue_atomicity_gate: the named `database` queue driver must resolve to the same database connection as `Enrollment`; the application fails closed before persistence when it does not. Verify with `php artisan config:show database.default` and `php artisan config:show queue.connections.database` before activation. No new environment variables are introduced.
- idempotency: review issues a 64-character random token, stores the normalized reviewed payload and reference in the tablet session, locks all server-controlled Livewire properties, and persists only the SHA-256 token hash behind a unique index. Response-loss retries and conflicting stale decisions resolve to the first committed decision and cannot enqueue another confirmation.
- cron: none
- workers: no new worker type; existing mail queue workers must be available for approved enrollments

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: existing mail transport and queue backend only

## Build and release procedure
- build_commands: `composer install --no-dev --prefer-dist --no-interaction`; `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: requires separate authorization; back up the database; verify the database queue uses the enrollment database; resolve all legacy pending enrollments under the old workflow; build the release; pause enrollment intake and drain in-flight requests; run the exact pending-count command above and require `0`; apply with `php artisan migrate --force` (the migration rechecks before DDL); activate the application and assets; perform the existing cache/worker refresh; run health checks; then reopen intake. No deployment was performed by this task.
- cache_actions: use the existing release cache clear/warm procedure after migration and activation
- restart_actions: use the existing queue-worker reload procedure after activation; no service restart was performed here
- health_checks: authenticate the tablet flow in an approved QA environment; verify Continue shows every entered value and exactly Go Back, Approve, and Reject; verify Go Back preserves values and sends no mail; approve once and verify one approved row and one member confirmation; repeat/refresh and verify no duplicate; reject once and verify one rejected row and no member confirmation. In admin, verify status visibility/filtering includes rejected, approval controls and pending dashboard affordances are absent, and approved-only PDF/resend/balance actions remain gated. Render member HTML/text confirmation and PDF and verify all eight canonical terms appear in order.
- rollback: pause intake and drain in-flight enrollment requests; back up and reconcile direct decisions; use the exact targeted rollback command above; roll back application code in the same authorized release. Rejected records become pending for the restored admin workflow. Rebuild assets, restore caches/workers, verify the former workflow, then reopen intake. Existing confirmation jobs remain compatible with the unchanged mailable; do not replay or manually duplicate them.
- downtime_and_risk: intake must remain paused across the final zero-pending check, migration, and application activation. Residual risks are schema/index locking, rejected-to-pending conversion on rollback, database queue availability, and standard at-least-once worker delivery after a mail transport succeeds but before job acknowledgement; mitigate with backup, measured staging migration timing, drained requests, queue health checks, and no manual replay.

## Gates
- reviewer_signoff: candidate `e3b2627adbe167a4efd2534f3b99c1e84f20da8d` failed independent senior review; fix self-review completed and independent reviewer rerun remains pending
- qa_signoff: independent QA passed the prior candidate at 106 tests/932 assertions plus a 3-test/117-assertion acceptance probe; independent QA rerun of this fix remains pending

## Local verification
- baseline: clean worktree confirmed at candidate `e3b2627adbe167a4efd2534f3b99c1e84f20da8d`; hosted `origin/staging` revalidated at exact base `60ff7fb86ad3f7ac919458e6108fe10e55c3648f`
- red_green: HTTP Livewire revocation RED returned 200 instead of redirect, then GREEN at 1 test/12 assertions; locked/active-review RED failed 7 tests, then GREEN at 7/12; durability RED failed 3 tests (10 assertions), then GREEN at 3/19; pending preflight RED failed 1 of 2 tests, then GREEN at 2/11; logout review invalidation RED failed 1/4 assertions, then GREEN at 1/4; visible queue retry RED errored before assertions, then GREEN at 1/8; synchronized approve/approve and approve/reject race RED produced no rows before the recorder existed, then GREEN at 2/12
- focused_tests: final eleven-file enrollment/tablet/migration/concurrency/UI/mail/PDF/terms run passed 58 tests with 436 assertions
- full_tests: `php artisan test --compact` passed 121 tests with 1,001 assertions without command-line environment overrides
- internal_review: read-only pre-handoff review found no critical runtime issue; its sequential-race and missing PHPUnit key findings were fixed with synchronized independent database connections and a non-secret test-only `APP_KEY`. Designated independent Reviewer and QA reruns remain pending.
- composer_test: could not start PHPUnit because the installed Composer passes the repository's `@no_additional_args` token literally to `artisan config:clear`; the project-standard full Artisan command above was used instead
- formatter: `vendor/bin/pint --dirty --format agent` passed
- syntax: `php -l` passed for all twelve changed PHP/Blade source and test files
- assets: `npm run build -- --config /tmp/direct-enrollment-vite.Q69LEh/vite.config.mjs` passed with Vite 8.2.1 and 4 modules transformed; the wrapper redirected `envDir` to an empty temporary directory and was removed after the build
- whitespace: `git diff --check` passed
- isolation: no deployment, push, merge, shared migration, service restart, external mail delivery, live-path modification, or environment-file access was performed
