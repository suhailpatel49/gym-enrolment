# Feature deployment requirements

This document is mandatory for every coding feature, including features with no production setup or runtime change. Store one unique copy in the feature branch at `.hermes/deployments/<task-id>-<slug>.md`. Never include secret values; environment variables are names only.

- feature: incline-visual-system
- task_id: t_bac629bb
- feature_commit: SELF (resolves to the containing candidate commit; no amend is required)
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
- deployment_sequence: deploy the containing application commit through the existing release process; build the Vite assets before making the release current
- cache_actions: none
- restart_actions: none
- health_checks: request `GET /enrol` and expect HTTP 200; request `GET /admin` and expect the existing authentication page or redirect; submit a test enrollment only in an approved QA environment and confirm the existing success/reference response
- rollback: revert the containing candidate commit, rebuild Vite assets with `npm ci --ignore-scripts && npm run build`, and release through the existing process
- downtime_and_risk: no downtime expected; risk is limited to browser, HTML email, and PDF presentation, mitigated by contract tests and unchanged application behavior

## Gates
- reviewer_signoff: pending independent Reviewer t_a590e2e1
- qa_signoff: pending independent QA t_9cc794ea
