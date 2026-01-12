# Edit Flow

Editorial workflow plugin with custom statuses, editorial comments, and notifications.

## Plugin Details

| Property | Value |
|----------|-------|
| **Main file** | `edit_flow.php` |
| **Text domain** | `edit-flow` |
| **Function prefix** | `ef_` |
| **Namespace** | Global (legacy) |
| **Source directory** | `modules/` |
| **Version** | 0.10.3 |

## Architecture

- Modular architecture in `modules/` directory
- Each feature is a separate module
- Main class: `edit_flow` (note underscore in filename)

## Testing

```bash
composer test:unit          # Unit tests
composer test:integration   # Integration tests
npm run test-e2e           # Playwright E2E tests
```

## Notes

- Tier 1 plugin (well-maintained)
- WordPress.org hosted
- Has E2E tests with Playwright

## Standards

Follow the standards documented in `~/code/plugin-standards/`.
