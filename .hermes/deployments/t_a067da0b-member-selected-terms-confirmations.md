# Feature deployment requirements

- feature: member-selected-terms-confirmations
- task_id: t_a067da0b
- feature_commit: f38d40023d9b9dbec7c4474360f274149b3633e3
- target_branch: staging
- default_branch: origin/main
- staging_branch: staging
- production_changes_required: no

## Integration provenance
- original_feature_base: `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- reviewed_feature_head: `76117e3dfc2fc4ada645a4fa404437a6c9bac91a`
- staging_integration_base: `ce055b2c6d16909709b1ab252ee83395a56f2eb5`
- integration_code_commit: `f38d40023d9b9dbec7c4474360f274149b3633e3`

## Runtime and dependencies
- runtime_versions: PHP 8.4 (existing)
- os_packages: none
- language_dependencies: none
- lockfile_changes: none
- environment_variable_names: none

## Data and asynchronous processing
- database_migrations: none
- database_backfill: none
- database_rollback: none
- database_locking_and_duration: none
- queues: no configuration change; existing member confirmation mail jobs render the selected terms after enrollment approval
- cron: none
- workers: none

## Service and infrastructure changes
- systemd: none
- supervisor: none
- nginx: none
- storage_and_permissions: none
- external_services: none

## Build and release procedure
- build_commands: none; frontend assets are unchanged
- deployment_sequence: use the existing staging release process for integration code commit `f38d40023d9b9dbec7c4474360f274149b3633e3`; no feature-specific step
- cache_actions: use the existing release process; no feature-specific cache action
- restart_actions: use the existing release process; no feature-specific restart
- health_checks: submit and approve a staging enrollment with terms accepted and confirm the exact acceptance text appears in the member HTML/text email and downloaded confirmation PDF; verify an enrollment persisted with `terms_accepted = false` renders neither the Terms accepted section nor the acceptance sentence; exercise approval, PT, and freezing flows and confirm their behavior remains unchanged
- rollback: revert integration code commit `f38d40023d9b9dbec7c4474360f274149b3633e3` and use the existing staging release process; no data rollback is required
- downtime_and_risk: no expected downtime; low presentation risk limited to member confirmation email/PDF output, mitigated by persisted-state and staging regression tests

## Verification
- focused_tests: passed 45 tests with 412 assertions across member email, confirmation PDF, enrollment form, enrollment approval transaction/UI, pending enrollment, and pending approvals dashboard tests
- full_tests: passed 98 tests with 944 assertions
- formatting: `vendor/bin/pint --dirty --format agent` passed
- diff_check: `git diff --check` passed
- assets: no asset, dependency manifest, or lockfile changes; no asset build required

## Gates
- reviewer_signoff: reviewed feature branch `feat/member-selected-terms-confirmations` at `76117e3dfc2fc4ada645a4fa404437a6c9bac91a`
- qa_signoff: integration checks passed on code commit `f38d40023d9b9dbec7c4474360f274149b3633e3`; staging promotion remains approval-gated
