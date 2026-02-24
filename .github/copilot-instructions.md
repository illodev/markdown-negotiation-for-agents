# Copilot Instructions — Markdown Negotiation for Agents

> These instructions apply to **every** interaction with this repository.
> Follow them strictly, regardless of the task scope.

---

## 1. Project Overview

This is a **WordPress plugin** (PHP 8.1+, `declare(strict_types=1)`) that implements HTTP content negotiation to serve WordPress content as Markdown when clients send `Accept: text/markdown` headers. It uses `league/html-to-markdown ^5.1` for conversion.

- **Namespace**: `IlloDev\MarkdownNegotiation`
- **Autoloading**: PSR-4 via Composer (`src/` → namespace root)
- **WordPress prefix**: `jetstaa_mna_` for all options, hooks, transient keys, and meta keys
- **Text domain**: `markdown-negotiation-for-agents`
- **Minimum requirements**: PHP 8.1, WordPress 6.0

---

## 2. Coding Standards

### PHP

- **Always** add `declare(strict_types=1);` at the top of every PHP file.
- Follow **WordPress Coding Standards** (WPCS 3.0) with these exceptions:
  - Short array syntax `[]` is allowed (but WordPress `array()` is preferred for consistency with existing code — follow existing file style).
  - PSR-4 file naming is used (not WordPress hyphenated lowercase).
- Use **`array()`** style (WordPress convention) — match the existing codebase.
- Use **typed properties**, **constructor promotion**, **match expressions**, **named arguments**, and **union/intersection types** where appropriate (PHP 8.1+ features).
- Mark classes `final` unless they need to be mocked in tests or extended. If a class is a dependency injected into another class and needs mocking, do **not** make it `final`.
- All public methods, properties, and constants **must** have PHPDoc blocks with `@param`, `@return`, and `@throws` annotations.
- Use WordPress wrapper functions (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`, `sanitize_text_field()`) for all output and input handling — never raw `echo` or unescaped output.

### Naming Conventions

| Element                 | Convention             | Example                  |
| ----------------------- | ---------------------- | ------------------------ |
| Classes                 | PascalCase             | `CacheManager`           |
| Methods                 | snake_case             | `build_key()`            |
| Constants               | UPPER_SNAKE            | `SKIP_BLOCKS`            |
| Hooks (filters/actions) | `jetstaa_mna_` prefix  | `jetstaa_mna_skip_block` |
| Options                 | `jetstaa_mna_` prefix  | `jetstaa_mna_settings`   |
| Meta keys               | `_jetstaa_mna_` prefix | `_jetstaa_mna_disabled`  |
| Transient keys          | `jetstaa_mna_` prefix  | `jetstaa_mna_cache_*`    |

### File Header

Every PHP file must start with:

```php
<?php
/**
 * Short description of the file.
 *
 * Optional longer description.
 *
 * @package IlloDev\MarkdownNegotiation\SubNamespace
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\SubNamespace;
```

---

## 3. Architecture Rules

### Dependency Injection

- All services are registered in `Plugin::register_services()` via the `Container`.
- Bind implementations to their **interfaces** (`ConverterInterface::class`, not `MarkdownConverter::class`).
- Use `singleton()` for services that should only be instantiated once.
- Never use `new` directly inside business logic classes — inject dependencies via constructor.

### Interfaces (Contracts)

Located in `src/Contracts/`. When adding a new major service:

1. Define an interface in `src/Contracts/`.
2. Implement it in the appropriate `src/` subdirectory.
3. Register it in `Plugin::register_services()`.
4. Document the swap point in `docs/ARCHITECTURE.md`.

### Directory Structure

```
src/
├── Admin/        # WordPress admin UI (settings pages, meta boxes)
├── Cache/        # Cache drivers and manager
├── Cli/          # WP-CLI commands
├── Contracts/    # Interfaces / service contracts
├── Converter/    # HTML→Markdown conversion pipeline
├── Http/         # Request handling, content negotiation, response
├── Multisite/    # WordPress Multisite support
├── Rest/         # REST API endpoints and field registration
├── Security/     # Sanitization, access control, rate limiting
├── Container.php # DI container
└── Plugin.php    # Main orchestrator / bootstrap
```

New classes **must** be placed in the correct subdirectory. If a new category is needed, create the directory and update `docs/ARCHITECTURE.md`.

### Hooks & Filters

When adding new WordPress hooks:

1. Prefix with `jetstaa_mna_`.
2. Add full PHPDoc block above the `apply_filters()` / `do_action()` call, documenting all parameters.
3. Add the hook to the hooks table in `docs/ARCHITECTURE.md` (section "Extensibility & Hooks").

---

## 4. Testing Requirements

### Mandatory: Every Code Change Must Include Tests

- **No PR / commit should modify `src/` without corresponding test changes.**
- Unit tests go in `tests/Unit/` mirroring the `src/` structure.
- Integration tests go in `tests/Integration/`.
- Test files must be named `{ClassName}Test.php`.
- Test classes must extend `IlloDev\MarkdownNegotiation\Tests\TestCase`.
- Test methods must use the `@test` annotation or `test_` prefix.

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration
```

### Test Conventions

- Use **Brain Monkey** for mocking WordPress functions (`add_action`, `apply_filters`, etc.).
- Use **Mockery** for mocking classes (never mock `final` classes — if you need to mock it, remove `final`).
- Stub `apply_filters` to return the first argument by default:
  ```php
  \Brain\Monkey\Functions\stubs([
      'apply_filters' => function(string $tag, ...$args) { return $args[0]; },
  ]);
  ```
- Always clean up `$_SERVER` modifications in tests.
- **After making changes, always run `composer test` and verify all tests pass before considering the task complete.**

### Linting

```bash
# Check coding standards
composer phpcs

# Auto-fix coding standards
composer phpcbf
```

---

## 5. Documentation Updates

### When to Update Documentation

Every change that modifies behavior, adds features, or changes API **must** update:

| Change Type         | Files to Update                                                   |
| ------------------- | ----------------------------------------------------------------- |
| New feature         | `CHANGELOG.md`, `README.md`, `readme.txt`, `docs/ARCHITECTURE.md` |
| New hook/filter     | `docs/ARCHITECTURE.md` (hooks table)                              |
| Bug fix             | `CHANGELOG.md`                                                    |
| New setting         | `README.md`, `readme.txt`, `docs/DEPLOYMENT.md`                   |
| New CLI command     | `README.md`, `docs/DEPLOYMENT.md`                                 |
| Architecture change | `docs/ARCHITECTURE.md`                                            |
| New dependency      | `docs/ARCHITECTURE.md` (converter pipeline or relevant section)   |
| Security fix        | `CHANGELOG.md` (under `### Security`)                             |
| Breaking change     | `CHANGELOG.md` (under `### Changed` or `### Removed`)             |
| Deprecation         | `CHANGELOG.md` (under `### Deprecated`)                           |

### CHANGELOG.md Format

Follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) strictly:

```markdown
## [Unreleased]

### Added

- Description of new feature

### Changed

- Description of change to existing functionality

### Fixed

- Description of bug fix

### Security

- Description of security fix
```

- New changes always go under `## [Unreleased]`.
- Each entry must be a single line starting with `- `.
- Use present tense, imperative mood: "Add support for..." not "Added support for...".
- Reference related hooks/classes when relevant.

### readme.txt (WordPress.org)

Must be kept in sync with `README.md`. Key sections: Description, Installation, FAQ, Changelog.

---

## 6. Version Management

- Follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
- When a release is cut, move `## [Unreleased]` entries to `## [X.Y.Z] - YYYY-MM-DD`.
- Update `JETSTAA_MNA_VERSION` constant in `markdown-negotiation-for-agents.php`.
- Update `version` in the plugin header comment.
- Update `JETSTAA_MNA_VERSION` in `phpunit.xml.dist`.

---

## 7. Security Checklist

Before finalizing any change:

- [ ] No raw `echo` of user input — always escape with `esc_html()`, `esc_attr()`, `esc_url()`.
- [ ] No direct `$_GET`, `$_POST`, `$_REQUEST` usage without `sanitize_text_field()` or equivalent.
- [ ] No `eval()`, `extract()`, or `compact()`.
- [ ] No `file_get_contents()` on remote URLs — use `wp_remote_get()`.
- [ ] Nonce verification on all admin form submissions.
- [ ] Capability checks before any privileged operation.
- [ ] SQL queries use `$wpdb->prepare()`.

---

## 8. Pull Request / Commit Workflow

For every task, follow this exact sequence:

1. **Understand** the requirement fully before writing code.
2. **Write/modify tests first** (TDD when possible).
3. **Implement** the change in `src/`.
4. **Run tests**: `composer test` — all 82+ tests must pass.
5. **Run linting**: `composer phpcs` — fix any violations.
6. **Update documentation** (see section 5 above).
7. **Update CHANGELOG.md** under `## [Unreleased]`.
8. **Verify** the full test suite passes one final time.

---

## 9. Common Patterns

### Adding a New Cache Driver

1. Create `src/Cache/NewDriver.php` implementing `CacheInterface`.
2. Register in `Plugin::resolve_cache_driver()`.
3. Add to settings page options in `src/Admin/SettingsPage.php`.
4. Write tests in `tests/Unit/Cache/NewDriverTest.php`.
5. Document in `docs/ARCHITECTURE.md` and `docs/DEPLOYMENT.md`.

### Adding a New REST Endpoint

1. Add method to `src/Rest/MarkdownController.php` or create a new controller.
2. Register route in `register_hooks()` or `register_routes()`.
3. Use `permission_callback` for access control.
4. Return `WP_REST_Response` or `WP_Error`.
5. Write integration tests.
6. Document in `README.md`.

### Adding a New Block Processor

1. Add the block type to `GutenbergProcessor::SKIP_BLOCKS` (if non-content) or add a `process_*_block()` method.
2. Add to the `match` expression in `render_block()`.
3. Ensure the `jetstaa_mna_skip_block` filter still works.
4. Write tests with block markup samples.

### Adding a New Admin Setting

1. Add default value to `Plugin::load_settings()` defaults array.
2. Add default to activation hook in `markdown-negotiation-for-agents.php`.
3. Register the field in `SettingsRegistrar`.
4. Render the field in `SettingsPage`.
5. Use the setting where needed via `$this->settings['key']`.
6. Document in `docs/DEPLOYMENT.md`.

---

## 10. Key Technical Decisions (Do Not Change Without Discussion)

| Decision                               | Rationale                                                           |
| -------------------------------------- | ------------------------------------------------------------------- |
| `league/html-to-markdown`              | Most mature PHP HTML→MD library (5M+ downloads), active maintenance |
| Custom DI Container                    | Minimizes external dependencies for a WordPress plugin              |
| `template_redirect` hook at priority 1 | Must intercept before theme template rendering                      |
| `Vary: Accept` on ALL responses        | Required for CDN cache correctness                                  |
| Singleton `Plugin` class               | WordPress plugin lifecycle requires single entry point              |
| Brain Monkey for test mocking          | Industry standard for testing WordPress plugins without loading WP  |
