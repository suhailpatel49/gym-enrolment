# Feature deployment requirements

This document is mandatory for every coding feature, including features with no production setup or runtime change. Store one unique copy in the feature branch at `.hermes/deployments/<task-id>-<slug>.md`. Never include secret values; environment variables are names only.

- feature: admin-content-contrast
- task_id: direct-20260908
- feature_commit: 77aa840e6179b28f2b8b99ca0f5677d1cb2d2156
- target_branch: staging
- default_branch: origin/main; exact implementation base `36dc6ef6a8dceb1e6df23e67c8e2d5ec5bd5b50b`
- staging_branch: staging
- production_changes_required: yes

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

- build_commands: `npm ci --ignore-scripts`; `npm run build`
- deployment_sequence: merge the feature and this deployment document into `staging`; install locked frontend dependencies; build the Vite assets; release through the existing staging process; perform the health checks before any separately authorized production promotion
- cache_actions: none
- restart_actions: none
- health_checks: verify the existing admin login, dashboard, list, view, create, and edit pages render successfully in light and dark mode at desktop and mobile widths; confirm the sidebar remains dark while adaptive content remains legible
- rollback: revert the feature commit and this documentation correction from `staging`, rebuild the Vite assets with `npm ci --ignore-scripts` and `npm run build`, and release through the existing process
- downtime_and_risk: no downtime expected; presentation-only risk is limited to admin theme contrast and is mitigated by the focused theme contract test and admin test suite

## Gates

- reviewer_signoff: pending
- qa_signoff: pending
