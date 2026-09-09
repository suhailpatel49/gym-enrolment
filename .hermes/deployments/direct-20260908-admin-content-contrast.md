# Feature deployment requirements

- feature: admin-content-contrast
- feature_commit: SELF
- target_branch: main
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
- queues: none
- cron: none
- workers: none

## Service and infrastructure changes

- services: none
- storage_and_permissions: none
- external_services: none

## Build and release procedure

- reason: hosted application code and compiled Vite assets must be released for the contrast correction to reach users
- build_commands: `npm ci --ignore-scripts && npm run build`
- deployment_sequence: deploy the containing application commit through the existing release process and build the Vite assets before making the release current
- cache_actions: none
- restart_actions: none
- health_checks: verify the existing admin login, dashboard, list, view, create, and edit pages in light and dark mode at desktop and mobile widths
- rollback: revert the containing commit, rebuild the Vite assets, and release through the existing process
- downtime_and_risk: no downtime expected; presentation-only risk is covered by the theme contract test and admin test suite

## Verification evidence

- red: `php artisan test --compact tests/Feature/VisualIdentityTest.php --filter=test_filament_theme_keeps_dark_chrome_separate_from_adaptive_content` failed with 0 of 1 tests passing first because the adaptive body contract was absent, then because the chrome rule was not limited to the sidebar
- green: `php artisan test --compact tests/Feature/VisualIdentityTest.php --filter=test_filament_theme_keeps_dark_chrome_separate_from_adaptive_content` passed 1 test with 16 assertions
