# Feature deployment requirements

- feature: combined enrollment approval and simple personal training release
- task_id: release-approval-pt-combined
- feature_commit: resolve the merge containing this document with `git log -1 --format=%H -- .hermes/deployments/release-approval-pt-combined.md`
- target_branch: staging
- default_branch: origin/main; fetched and verified at `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: staging; explicit release base `94d13a6f1f6e65f24fbd6efe622fce70c792ae2b`
- release_branch: release/approval-pt-combined
- feature_sources: enrollment approval `0db21d70b81b7d091ef1aabd4d678ddbf558dbdc`; personal training `39bf0222e9d50e760966fa7fb2d9ff769f9ae962`; both integrated with merge commits
- production_changes_required: yes
- authorization_scope: local integration and verification only; no push, deployment, live-path write, environment-file access, or shared migration

## Runtime and dependencies
- runtime_versions: PHP 8.4, Laravel 13.26.1, Filament 5.7.6, PHPUnit 12.5.33, Vite 8.2.1; existing locked dependencies
- os_packages: none
- language_dependencies: none changed
- lockfile_changes: none
- environment_variable_names: existing approval mail/queue configuration only; GYM_EMAIL, QUEUE_CONNECTION, DB_QUEUE_CONNECTION, DB_QUEUE_TABLE, DB_QUEUE, MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_URL

## Data and asynchronous processing
- database_migrations: after the existing staging migrations, apply in filename order:
  1. `database/migrations/2026_09_06_000001_create_trainers_table.php`
  2. `database/migrations/2026_09_06_000002_create_personal_training_members_table.php`
  3. `database/migrations/2026_09_06_182408_add_approval_fields_to_enrollments_table.php`
- database_backfill: existing enrollment rows default approved without fabricated approver/time; no PT backfill or seed data
- database_rollback: reverse order: approval fields, PT members, trainers. The PT member foreign key requires dropping members before trainers. Approval rollback removes the approver foreign key before its column. Rollback destroys approval audit and PT data; preserve a verified backup first. Never expose pending enrollments to old code during rollback.
- database_locking_and_duration: PT creates empty tables; approval alteration/index creation can lock or rebuild enrollments depending on engine and table size. Production-engine timing is unmeasured.
- queues: existing gym submission notification and exactly-once member confirmation queueing after approval commit; existing workers required
- cron: none
- workers: no new types; existing mail workers must use the activated release through the normal release procedure

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: existing mail and queue backend only; no PT notifications or trainer login

## Build and release procedure
- build_commands: install unchanged lockfiles with `composer install --no-scripts --no-interaction --prefer-dist` and `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: requires separate authorization. Verify backup and rollback target; build release; pause tablet intake and drain in-flight requests; apply all three migrations in order before activation; activate release and refresh normal caches/workers; verify health checks; reopen tablet intake. No deployment was performed here.
- cache_actions: existing release cache refresh procedure only, after migration and activation
- restart_actions: existing runtime/queue-worker reload procedure only during separately authorized deployment
- health_checks: admin and staff dashboard order is EnrollmentStats, PendingApprovals, PersonalTrainingStats, OverdueBalances, RenewalsDue. Both subheadings mention pending review and personal training; staff copy and enrollment widgets preserve financial/contact restrictions. Verify tablet pending submission, authorized approval, one confirmation after commit, pending row removal, trainer/PT CRUD access, renewal/payment reset, deactivation, and all four PT stats. Guests cannot access administrative resources.
- rollback: pause intake and writes, preserve new records/audit, and review pending enrollments before reverting application code. Prefer retaining additive schema. If schema removal is separately authorized, use the release code to roll back only these three migrations in reverse order after verifying migration batches; do not roll back unrelated changes. Restore backups only through the established recovery process.
- downtime_and_risk: intake pause required across approval transition; destructive schema rollback loses new data. SQLite tests do not establish production-engine lock timing.

## Gates
- reviewer_signoff: pending separate release review
- qa_signoff: local combined verification recorded below; separate release QA approval remains pending

## Local verification
- Focused feature command: `php artisan test --compact --filter='EnrollmentApproval|PendingEnrollment|PendingApprovalsDashboard|EnrollmentFormTest|ImportEnrollmentsCommandTest|AdminDashboardTest|StaffAccessTest|PersonalTraining'`: passed, 74 tests, 802 assertions, 13.625 seconds.
- Full command: `php artisan test --compact`: passed, 88 tests, 871 assertions, 15.979 seconds; no failures or skips reported.
- Test isolation: both commands used `PHP_INI_SCAN_DIR=:/tmp/gym-combined-verification/php`; its temporary PHP bootstrap disables Laravel environment-file loading, supplies a process-only test key, selects SQLite memory databases and array/sync test services, and redirects the configuration cache to an unused temporary path. Transaction tests create and delete their own temporary SQLite database. No shared database was touched.
- Combined regression checks: admin/staff copy tests failed before the copy adjustment and passed after it; both roles verify exact dashboard widget order. The new combined migration test creates a PT member, rolls back the three release migrations, checks existing enrollment/user tables remain, and reapplies all three successfully.
- Formatter: `vendor/bin/pint --dirty --format agent` passed after formatting the added rollback test.
- Build: `npm run build -- --config /tmp/gym-combined-verification/vite.config.mjs` passed with Vite 8.2.1, 3 modules, 1.19 seconds. The temporary wrapper imports the unchanged project configuration and redirects envDir to an empty temporary directory to prevent environment-file reads. Existing optional fontaine warning only.
- Whitespace: `git diff --check` and `git diff --cached --check` passed.
- Preservation: all feature files match their pinned source except the listed combined dashboard/copy/test adjustments and AGENTS.md, which exactly matches staging. Dependency lockfiles and migration files are unchanged. Shared pre-push hook remains executable and push.default remains nothing.
