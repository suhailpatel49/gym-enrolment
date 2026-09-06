# Feature deployment requirements

- feature: enrollment-approval-flow
- task_id: t_0afb6ae1
- feature_commit: 2af81a6b73a7077e469dea52e964e5733f597594
- target_branch: staging
- default_branch: main
- staging_branch: staging
- production_changes_required: yes

## Runtime and dependencies
- runtime_versions: PHP 8.4; existing Laravel 13 and Filament 5; existing Node/Vite build toolchain
- os_packages: none
- language_dependencies: none
- lockfile_changes: none
- environment_variable_names: GYM_EMAIL, QUEUE_CONNECTION, DB_QUEUE_CONNECTION, DB_QUEUE_TABLE, DB_QUEUE, MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_URL; existing configuration only, no new variables

## Data and asynchronous processing
- database_migrations: run `database/migrations/2026_09_06_182408_add_approval_fields_to_enrollments_table.php` after existing migrations using `php artisan migrate --force --no-interaction` during the authorized release
- database_backfill: the database default makes existing enrollments approved; generic admin/import inserts default approved. No fabricated approver or approval timestamp. Tablet submissions explicitly become pending.
- database_rollback: the migration down method removes the approver FK, approval-status index, and approval fields. This discards approval audit data; back up first. Do not roll back while pending records remain accessible to old application code.
- database_locking_and_duration: approval uses a row lock and state recheck in a short transaction with up to five deadlock retries. Schema alteration/index creation may lock or rebuild enrollments depending on the database engine and table size; measure in staging. SQLite migration and simultaneous-attempt behavior are tested; production-engine timing has not been measured.
- queues: existing NewEnrollmentNotification is queued to the gym at submission. Existing EnrollmentConfirmation is queued only to the member after approval commits. Repeat approvals do not enqueue again; rolled-back transactions enqueue nothing. Existing approved-only manual resend remains an admin capability.
- cron: none
- workers: existing mail queue workers must remain available; no new queue or worker type

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: existing mail transport and queue backend only

## Build and release procedure
- build_commands: `composer install --no-dev --prefer-dist --no-interaction`; `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: (1) Obtain staging release authorization and back up the database. (2) Build the release from the feature commit and install locked dependencies. (3) Pause tablet intake and drain in-flight requests so old code cannot create approved tablet records during transition. (4) Run the additive migration before exposing new code. (5) Activate the release, refresh normal application caches, and roll existing queue workers onto the release using the established deployment procedure. (6) Complete health checks, then reopen tablet intake. These are future release instructions; no deployment occurred in this task.
- cache_actions: `php artisan optimize:clear`; `php artisan optimize` after migration and release activation
- restart_actions: use the established worker reload procedure after release activation; no service configuration changes
- health_checks: `php artisan migrate:status --no-interaction` shows the approval migration applied; `php artisan test --compact` passes in an isolated test environment. In staging, submit a tablet enrollment and verify pending status, gym-only notification, and exclusion from statistics, renewals, overdue balances, balance settlement, PDFs, and resend. Approve as admin and staff: one member confirmation, actor/time recorded, approved badge, and no repeat approval action. Verify staff still cannot update/delete enrollments or see personal/payment details. Verify existing/imported records remain approved.
- rollback: prefer a forward fix. If reverting code, pause intake and membership operations first; back up all enrollment and approval data; reconcile pending enrollments with authorized reviewers or restore the pre-release database with an explicit reconciliation of submissions since the backup. Never run old code against unresolved pending records, because it ignores approval state. Keep additive columns when possible. Only after data reconciliation and with the previous release ready, run `php artisan migrate:rollback --step=1 --force --no-interaction` if this migration is the latest, isolated migration; otherwise use the established targeted migration rollback procedure. Restore the previous release, caches, and worker code together, verify health, then reopen intake. Do not replay already-delivered confirmations.
- downtime_and_risk: brief intake pause for migration/release consistency; schema duration depends on data size and engine. Approval keeps submitted membership dates unchanged. Approval queueing uses the existing after-commit mechanism: a queue backend failure after commit can leave an approved record without a queued message; inspect delivery/queue state before using the admin resend action. Queue delivery retains the existing backend's retry semantics.

## Gates
- reviewer_signoff: direct Codex diff review; independent reviewer pending
- qa_signoff: local automated verification passed: 41 affected tests / 343 assertions; 50 full-suite tests / 378 assertions, including simultaneous approval and rollback. Pint, asset build, and git diff checks passed. Asset build emitted the existing optional fontaine warning. Laravel checks used isolated test databases with environment-file loading disabled; the asset build used a temporary environment-directory override. Staging QA pending
