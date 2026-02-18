# Edit Flow

Editorial workflow plugin with custom statuses, editorial comments, and notifications.

## Project Knowledge

| Property | Value |
|----------|-------|
| **Main file** | `edit_flow.php` |
| **Text domain** | `edit-flow` |
| **Function prefix** | `ef_` |
| **Namespace** | Global (legacy) |
| **Source directory** | `modules/` |
| **Version** | 0.10.3 |
| **Requires PHP** | 7.4+ |
| **Requires WP** | 6.4+ |

### Directory Structure

```
edit-flow/
├── edit_flow.php           # Main plugin file (note: underscore in filename)
├── edit-flow.php           # Redirect file (hyphenated, loads the underscore version)
├── common/                 # Shared utilities and base module class
├── modules/                # Feature modules (each is self-contained)
│   ├── calendar/           # Editorial calendar
│   ├── custom-status/      # Custom post statuses
│   ├── dashboard/          # Dashboard widgets
│   ├── editorial-comments/ # In-post editorial comments
│   ├── editorial-metadata/ # Custom editorial fields
│   ├── notifications/      # Email notifications
│   ├── settings/           # Plugin settings
│   ├── story-budget/       # Story budget overview
│   └── user-groups/        # User group management
├── tests/                  # Integration tests
├── build/                  # Built assets
├── dist/                   # Distribution assets
├── .github/workflows/      # CI: deploy, e2e-tests, integration, js-tests, php-lint
└── .phpcs.xml.dist         # PHPCS configuration
```

### Key Architecture

- `edit_flow` class — Main plugin class, loads and manages modules
- Each module in `modules/` is a self-contained feature with its own views, scripts, and styles
- `common/` contains the base `EF_Module` class and shared utilities

### Dependencies

- **Dev**: `automattic/vipwpcs`, `yoast/wp-test-utils`
- **JS**: Has `package.json` with build tooling and Playwright for E2E tests

## Commands

```bash
composer cs                # Check code standards (PHPCS)
composer cs-fix            # Auto-fix code standard violations
composer lint              # PHP syntax lint
composer test:integration  # Run integration tests (requires wp-env)
composer test:integration-ms  # Run multisite integration tests
composer coverage          # Run tests with HTML coverage report

npm run test-e2e           # Run Playwright E2E tests
npm run build              # Build JS/CSS assets
```

**Note:** This plugin has integration tests and E2E tests, but no separate PHP unit test suite.

## Conventions

Follow the standards documented in `~/code/plugin-standards/` for full details. Key points:

- **Commits**: Use the `/commit` skill. Favour explaining "why" over "what".
- **PRs**: Use the `/pr` skill. Squash and merge by default.
- **Branch naming**: `feature/description`, `fix/description` from `develop`.
- **Testing**: Integration tests for PHP, Playwright E2E tests for UI interactions. New PHP tests should be integration tests.
- **Code style**: WordPress coding standards via PHPCS. Tabs for indentation.
- **i18n**: All user-facing strings must use the `edit-flow` text domain.

## Architectural Decisions

- **Modular architecture**: Each feature (calendar, custom statuses, notifications, etc.) is a self-contained module in `modules/`. This is the plugin's core architectural pattern. New features should be added as new modules, not by extending existing ones.
- **Module base class**: All modules extend `EF_Module` from `common/`. This provides shared infrastructure (settings registration, module activation/deactivation).
- **Two main files**: `edit_flow.php` (underscore) is the real main file. `edit-flow.php` (hyphen) is a redirect for backward compatibility. Do not remove the redirect file.
- **WordPress.org hosted**: This plugin is publicly distributed on WordPress.org. Changes must maintain backward compatibility for all users, not just VIP customers.
- **E2E testing with Playwright**: UI-heavy features (calendar, editorial comments) are tested with Playwright. Use E2E tests for complex UI interactions, integration tests for PHP logic.
- **Global namespace (legacy)**: All classes are in the global namespace with the `EF_` prefix. This is legacy — do not introduce namespaced classes alongside global ones without a migration plan.

## Common Pitfalls

- Do not edit WordPress core files or bundled dependencies in `vendor/`.
- Run `composer cs` before committing. CI will reject code standard violations.
- Integration tests require `npx wp-env start` running first.
- **The main file has an underscore** (`edit_flow.php`), not a hyphen. The hyphenated `edit-flow.php` is just a redirect. Do not add logic to the redirect file.
- **Modules are self-contained**: Do not create cross-dependencies between modules. If module A needs data from module B, use WordPress hooks/filters as the interface.
- **WordPress.org compatibility**: This plugin serves a broad user base. Do not add VIP-specific code without graceful fallbacks for non-VIP environments.
- Custom post statuses interact with WordPress core's status system in complex ways. Test custom status changes with Gutenberg and the Classic Editor — they behave differently.
- The editorial calendar uses JavaScript date handling. Be careful with timezone issues — WordPress stores dates in UTC but displays them in the site's configured timezone.
- Do not add new modules without considering the settings UI, activation/deactivation flow, and module dependencies.
- Asset builds (`npm run build`) must be run before committing changes to JS/CSS source files. Built assets in `build/` and `dist/` should be committed.
