# Feature deployment requirements

- feature: enrollment-review-decision-flow
- task_id: direct-20260917T012929Z
- reviewed_code_commit: `dcb8ab62e49c9e4f5b13b762c163600cec3df879`
- feature_commit: `dcb8ab62e49c9e4f5b13b762c163600cec3df879` plus the docs-only deployment-evidence follow-up containing this update
- target_branch: staging
- default_branch: origin/main at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: origin/staging base `60ff7fb86ad3f7ac919458e6108fe10e55c3648f`
- production_changes_required: yes
- deployment_performed: yes, completed at `2026-09-17T03:34:55Z`; hosted browser verification remains pending for the parent agent

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
- queues: Approve uses the default `database` queue and inserts the existing queued `EnrollmentConfirmation` into the `jobs` table inside the same database transaction as the enrollment; rollback removes both writes on queue insertion failure. Continue, Go Back, and Reject queue nothing.
- queue_atomicity_gate: `queue.default` must be `database`; that connection must use the database driver, resolve to the same database connection as `Enrollment`, and have queue name `default` to match the hosted worker. Approval fails closed before persistence otherwise. Verify with `php artisan config:show queue.default`, `php artisan config:show database.default`, and `php artisan config:show queue.connections.database` before activation. No new environment variables are introduced, and no configuration values are logged or exposed by the application error.
- idempotency: each tablet session has one server-authoritative active review token. Reissuing review invalidates the prior session entry before storing the replacement normalized payload/reference; stale signed Livewire snapshots therefore fail closed. Only the SHA-256 hash of a decided token is persisted behind a unique index. Response-loss retries and conflicting decisions for that decided token resolve to the first committed decision and cannot enqueue another confirmation.
- cron: none
- workers: before activation, inspect `/etc/supervisor/conf.d/gym-enrolment-worker.conf` and verify its command targets `queue:work database --queue=default`; verify the configured process reports running in Supervisor. In an approved non-production context using the non-external array mail transport, enqueue one harmless test enrollment confirmation, run `php artisan queue:work database --queue=default --once --tries=1 --no-interaction`, verify the job leaves `jobs` without entering `failed_jobs`, and remove the test enrollment afterward.

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: existing mail transport and queue backend only

## Build and release procedure
- build_commands: `composer install --no-dev --prefer-dist --no-interaction`; `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: completed under explicit authorization. Revalidated the clean reviewed worktree, expected remote staging base, clean live checkout, SQLite/database queue invariants, exact zero pending/jobs/failed counts, and Supervisor command. Created and verified the rollback set, pushed the feature branch, fast-forwarded staging without force, built isolated production assets, enabled maintenance mode, repeated zero-count gates, stopped the Gym worker, fast-forwarded live code, cleared caches, migrated, installed assets, warmed caches, started only the Gym worker, reopened intake, and completed CLI health checks. No external test mail or unknown job processing occurred.
- cache_actions: `php artisan optimize:clear` completed after code activation; `php artisan optimize --no-interaction` completed after migration and asset installation
- restart_actions: only `gym-enrolment-worker:gym-enrolment-worker_00` was stopped and started; its live process command was verified as `queue:work database --queue=default` and final Supervisor state was `RUNNING`
- health_checks: authenticate the tablet flow in an approved QA environment; verify Continue shows every entered value and exactly Go Back, Approve, and Reject; issue reviews A then B in one tablet session and verify A cannot decide while B can decide exactly once; verify Go Back preserves values and sends no mail; approve once and verify one approved row and one member confirmation; repeat/refresh and verify no duplicate; reject once and verify one rejected row and no member confirmation. Confirm the hosted Supervisor worker still reports running with command semantics `queue:work database --queue=default`, then verify `php artisan queue:work database --queue=default --once --tries=1 --no-interaction` drains one harmless confirmation under the non-external array mail transport without a failed job. In admin, verify status visibility/filtering includes rejected, approval controls and pending dashboard affordances are absent, and approved-only PDF/resend/balance actions remain gated. Render member HTML/text confirmation and PDF and verify all eight canonical terms appear in order.
- rollback: pause intake and drain in-flight enrollment requests; before changing code, confirm the hosted Supervisor worker reports running with command semantics `queue:work database --queue=default`, run `php artisan queue:work database --queue=default --once --tries=1 --no-interaction` for the harmless non-external confirmation proof, and confirm no enrollment confirmation remains queued or failed; back up and reconcile direct decisions; use the exact targeted rollback command above; roll back application code in the same authorized release. Rejected records become pending for the restored admin workflow. Rebuild assets, restore caches/workers, repeat the same exact harmless drain and Supervisor status checks against the restored release, verify the former workflow, then reopen intake. Existing confirmation jobs remain compatible with the unchanged mailable; do not replay or manually duplicate them.
- downtime_and_risk: intake must remain paused across the final zero-pending check, migration, and application activation. Residual risks are schema/index locking, rejected-to-pending conversion on rollback, database queue availability, and standard at-least-once worker delivery after a mail transport succeeds but before job acknowledgement; mitigate with backup, measured staging migration timing, drained requests, queue health checks, and no manual replay.

## Gates
- reviewer_signoff: PASS for reviewed code commit `dcb8ab62e49c9e4f5b13b762c163600cec3df879`
- qa_signoff: PASS for reviewed code commit `dcb8ab62e49c9e4f5b13b762c163600cec3df879`; focused QA passed 12 tests with 54 assertions, and the implementation full suite passed 126 tests with 1,038 assertions
- hosted_browser_signoff: pending parent-agent verification; no hosted browser result is claimed here

## Deployment evidence
- rollback_backup: `/var/backups/gym-enrolment/direct-enrolment-review-decision-flow-20260917T032835Z`; root-owned directory mode `0700`, eight non-empty root-owned files mode `0600`
- database_backup: Python 3 stdlib `sqlite3.Connection.backup()` online backup; independently opened backup returned exactly `ok` from `PRAGMA integrity_check`; SHA-256 `f0a611aec2e6986b434425091b82922d88bc304fc57052811696caf833e0398a`
- refs: feature branch and remote `staging` reached reviewed code commit `dcb8ab62e49c9e4f5b13b762c163600cec3df879` by non-force pushes; live checkout reached the same commit by fast-forward
- production_build: Vite 8.2.1 production build passed with an empty temporary env directory and isolated `/tmp` output; seven generated asset files were installed and byte-compared with the isolated output
- migration: `2026_09_17_070812_add_rejected_status_to_enrollments_table` ran in batch 7; the live schema supports `rejected`, contains `decision_token_hash`, and has its unique index
- runtime_gates: effective database driver remained SQLite; default queue and database queue driver remained `database`; the queue database connection remained equivalent to the enrollment database connection; final pending enrollments, jobs, and failed jobs counts were each `0`
- service_health: `nginx -t` passed; local-origin HTTPS `/up` returned `200`; public HTTPS `/up` returned `200`; `/` returned the expected `302`; `/enrol` returned `200` with the tablet login; unauthenticated `/enroll` returned the expected `302`; no checked response exposed framework error markers
- rollback_ready: rollback target is `60ff7fb86ad3f7ac919458e6108fe10e55c3648f`; with intake paused and the Gym worker stopped, run `php artisan migrate:rollback --force --path=database/migrations/2026_09_17_070812_add_rejected_status_to_enrollments_table.php`, restore the old tracked release/assets or database backup as required, clear/warm caches, revalidate zero-count/config/schema gates, start the worker, run health checks, then reopen intake

## Local verification
- baseline: clean worktree confirmed at candidate `e3b2627adbe167a4efd2534f3b99c1e84f20da8d`; hosted `origin/staging` revalidated at exact base `60ff7fb86ad3f7ac919458e6108fe10e55c3648f`
- red_green: HTTP Livewire revocation RED returned 200 instead of redirect, then GREEN at 1 test/12 assertions; locked/active-review RED failed 7 tests, then GREEN at 7/12; durability RED failed 3 tests (10 assertions), then GREEN at 3/19; pending preflight RED failed 1 of 2 tests, then GREEN at 2/11; logout review invalidation RED failed 1/4 assertions, then GREEN at 1/4; visible queue retry RED errored before assertions, then GREEN at 1/8; synchronized approve/approve and approve/reject race RED produced no rows before the recorder existed, then GREEN at 2/12. Correction RED for superseded HTTP snapshots failed at 1 test/6 assertions because stale snapshot A recorded a rejection; GREEN passed at 1/14, with the surrounding review/session set at 21/129. Correction RED for a non-database default queue failed at 1 test/1 assertion because approval persisted without an error; GREEN passed at 1/6, with the decision durability/concurrency/review set at 18/117. Final queue-name RED failed at 1 test/1 assertion because approval returned without a review error when the database queue name was not `default`; GREEN passed at 1 test/6 assertions.
- focused_tests: final decision durability/concurrency/review run passed 21 tests with 134 assertions
- full_tests: `php artisan test --compact` passed 126 tests with 1,038 assertions without command-line environment overrides
- internal_review: read-only pre-handoff review found no critical runtime issue; its sequential-race and missing PHPUnit key findings were fixed with synchronized independent database connections and a non-secret test-only `APP_KEY`. Designated independent Reviewer and QA reruns passed for the reviewed code commit.
- composer_test: could not start PHPUnit because the installed Composer passes the repository's `@no_additional_args` token literally to `artisan config:clear`; the project-standard full Artisan command above was used instead
- formatter: `vendor/bin/pint --dirty --format agent` passed
- syntax: `php -l` passed for all six changed PHP/Blade source and test files
- assets: a fresh isolated Vite 8.2.1 production build passed during deployment with an empty temporary environment directory; the generated output was installed and verified in the live release
- whitespace: `git diff --check` passed
- isolation: the approved feature/staging pushes, live fast-forward, migration, cache refresh, asset activation, and Gym-worker stop/start were performed. No default-branch push, force push, external mail delivery, unknown-job processing, or environment-file access was performed.
