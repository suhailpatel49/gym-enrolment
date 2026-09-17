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
- database_backfill: none; existing `pending` and `approved` values remain unchanged. Before activation, operators must resolve or explicitly accept ownership of every legacy pending row because the direct-only admin UI has no transition action.
- database_rollback: roll back the migration with the release's normal targeted migration procedure; rejected records are converted to pending before the status constraint is restored
- database_locking_and_duration: changing the enrollment status enum/check constraint may rebuild or lock the enrollments table depending on the production database engine and table size; production-engine duration is unmeasured
- queues: public Approve now queues the existing `EnrollmentConfirmation` only; Continue, Go Back, and Reject queue nothing; the former public gym notification and later admin approval mail transition are removed
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
- deployment_sequence: requires separate authorization; back up the database; resolve all legacy pending enrollments under the old workflow; build the release; pause enrollment intake and drain in-flight requests; verify no new pending rows appeared; apply the migration; activate the application and assets; perform the existing cache/worker refresh; run health checks; then reopen intake. No deployment was performed by this task.
- cache_actions: use the existing release cache clear/warm procedure after migration and activation
- restart_actions: use the existing queue-worker reload procedure after activation; no service restart was performed here
- health_checks: authenticate the tablet flow in an approved QA environment; verify Continue shows every entered value and exactly Go Back, Approve, and Reject; verify Go Back preserves values and sends no mail; approve once and verify one approved row and one member confirmation; repeat/refresh and verify no duplicate; reject once and verify one rejected row and no member confirmation. In admin, verify status visibility/filtering includes rejected, approval controls and pending dashboard affordances are absent, and approved-only PDF/resend/balance actions remain gated. Render member HTML/text confirmation and PDF and verify all eight canonical terms appear in order.
- rollback: pause intake; back up and reconcile enrollments created under this flow; roll back application code and this migration together using the authorized release process. Rejected records become pending for the restored admin approval workflow. Rebuild assets, restore caches/workers, verify the former workflow, then reopen intake. Do not replay already queued member confirmations.
- downtime_and_risk: brief intake pause is recommended across the schema/application transition. Primary risks are stranded legacy pending rows, table locking during enum alteration, rejected-to-pending conversion on rollback, and mail queue availability; mitigate with pre-activation pending-row reconciliation, backup, measured staging migration timing, drained requests, and post-release queue checks.

## Gates
- reviewer_signoff: self-review complete; independent review pending
- qa_signoff: local automated verification passed; staging QA and deployment remain approval-gated

## Local verification
- baseline: focused approval/form/mail/PDF suite passed 45 tests with 412 assertions after locked dependencies and assets were installed
- red_green: review/decision tests failed 4/4 on the missing review method, then passed 4/4 with 51 assertions; admin-removal tests failed 6/7 against the old actions/widget/stat/copy, then passed 7/7 with 32 assertions; legal-output tests failed 3/4 against the old single sentence/footer, then passed 4/4 with 99 assertions
- focused_tests: direct workflow and staging regression set passed 40 tests with 347 assertions; stale-request duplicate coverage passed in the final review test run
- full_tests: `php artisan test --compact` passed 106 tests with 932 assertions
- composer_test: could not start PHPUnit because the installed Composer passes the repository's `@no_additional_args` token literally to `artisan config:clear`; the project-standard full Artisan command above was used instead
- formatter: `vendor/bin/pint --dirty --format agent` passed
- assets: `npm run build -- --config /tmp/direct-enrolment-review-vite.config.mjs` passed with Vite 8.2.1, 4 modules transformed; the temporary wrapper redirected `envDir` to an empty directory
- whitespace: `git diff --check` passed
- isolation: no deployment, push, merge, shared migration, service restart, external mail delivery, live-path modification, or environment-file access was performed
