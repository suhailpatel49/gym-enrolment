# Feature deployment requirements

This document is mandatory for every coding feature, including features with no production setup or runtime change. Store one unique copy in the feature branch at `.hermes/deployments/<task-id>-<slug>.md`. Never include secret values; environment variables are names only.

- feature: incline-staging-reconciliation
- task_id: t_1a80912a
- feature_commit: SELF (resolves to the containing merge commit)
- target_branch: staging
- default_branch: main
- staging_branch: staging
- production_changes_required: no

## Runtime and dependencies
- runtime_versions: none
- os_packages: none
- language_dependencies: none
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
- build_commands: `npm ci --ignore-scripts && npm run build`
- deployment_sequence: none performed by this task; promotion and deployment remain approval-gated
- cache_actions: none
- restart_actions: none
- health_checks: request `GET /enrol` and expect the existing authentication redirect or page; request `GET /admin` and expect the existing authentication page or redirect; in an approved QA environment, submit an enrollment and confirm the pending-approval success/reference response without a member confirmation email, then approve it and confirm the member email is queued once
- rollback: revert the containing merge commit, rebuild Vite assets with `npm ci --ignore-scripts && npm run build`, and release through the existing approval-gated process
- downtime_and_risk: no downtime expected; risk is limited to presentation changes and the reconciled enrollment success state, mitigated by focused and full regression tests

## Reconciliation rationale
- conflict_scope: one content conflict in `resources/views/components/⚡enrollment-form.blade.php`; all other candidate paths merged automatically
- persistence_and_mail_hunks: retained staging's explicit pending approval value and omission of the pre-approval member confirmation; these non-conflicting semantic changes survived the automatic merge
- success_state_hunk: combined staging's approval-aware heading and confirmation timing copy with the candidate's Incline card, status live region, reference styling, and action treatment
- form_and_terms_hunks: retained the candidate's reviewed visual and accessibility markup while preserving the existing freezing and transfer charge disclaimer in the terms list

## Gates
- reviewer_signoff: pending
- qa_signoff: pending
