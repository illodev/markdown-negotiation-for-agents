# Contributing to Markdown Negotiation for Agents

Thank you for your interest in contributing! This document provides guidelines and instructions for contributing to this WordPress plugin.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Reporting Bugs](#reporting-bugs)
- [Requesting Features](#requesting-features)
- [Security Vulnerabilities](#security-vulnerabilities)

---

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to [hello@illodev.com](mailto:hello@illodev.com).

---

## Getting Started

### Prerequisites

- **PHP 8.1** or higher
- **WordPress 6.0** or higher
- **Composer 2.x**
- **Git**

### Fork and Clone

```bash
# Fork the repository on GitHub, then:
git clone https://github.com/YOUR-USERNAME/markdown-negotiation-for-agents.git
cd markdown-negotiation-for-agents
git remote add upstream https://github.com/illodev/markdown-negotiation-for-agents.git
```

---

## Development Setup

### 1. Install Dependencies

```bash
composer install
```

This installs both production and development dependencies:

- `league/html-to-markdown` — HTML-to-Markdown conversion
- `phpunit/phpunit` — Testing framework
- `brain/monkey` — WordPress function mocking
- `mockery/mockery` — Test doubles
- `wp-coding-standards/wpcs` — WordPress Coding Standards

### 2. Verify Setup

```bash
# Run tests
composer test

# Run coding standards check
composer phpcs
```

All 82+ tests should pass and no PHPCS violations should appear.

### 3. Local WordPress Environment (Optional)

For manual testing, you can symlink or copy the plugin into a local WordPress installation:

```bash
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/markdown-negotiation-for-agents
```

Then activate the plugin via WP Admin or WP-CLI:

```bash
wp plugin activate markdown-negotiation-for-agents
```

---

## Development Workflow

### Branch Naming

| Type     | Pattern                      | Example                        |
| -------- | ---------------------------- | ------------------------------ |
| Feature  | `feature/short-description`  | `feature/yaml-frontmatter`     |
| Bug fix  | `fix/short-description`      | `fix/token-count-multibyte`    |
| Refactor | `refactor/short-description` | `refactor/cache-manager-split` |
| Docs     | `docs/short-description`     | `docs/deployment-docker`       |

### Workflow Steps

1. **Create a branch** from `main`:

   ```bash
   git checkout main
   git pull upstream main
   git checkout -b feature/my-feature
   ```

2. **Write tests first** (TDD preferred):

   ```bash
   # Create test file mirroring src/ structure
   touch tests/Unit/SubDir/MyClassTest.php
   ```

3. **Implement the change** in `src/`.

4. **Run tests**:

   ```bash
   composer test
   ```

5. **Run linting**:

   ```bash
   composer phpcs
   # Auto-fix what can be fixed:
   composer phpcbf
   ```

6. **Update documentation**:
   - `CHANGELOG.md` — Under `## [Unreleased]`
   - `README.md` / `readme.txt` — If user-facing behavior changes
   - `docs/ARCHITECTURE.md` — If architecture changes
   - `docs/DEPLOYMENT.md` — If configuration/deployment changes

7. **Commit with clear messages**:

   ```bash
   git commit -m "Add YAML frontmatter support for converted Markdown"
   ```

8. **Push and open a Pull Request**.

---

## Coding Standards

This project follows **WordPress Coding Standards (WPCS 3.0)** with these specifics:

### PHP

- `declare(strict_types=1);` at the top of every PHP file.
- PSR-4 autoloading (file names match class names, not WordPress hyphenated style).
- `array()` syntax (WordPress convention) — not `[]`.
- PHP 8.1+ features: typed properties, constructor promotion, match expressions, union types, named arguments.
- Classes are `final` unless they need to be mocked in tests.

### Naming

| Element   | Convention            | Example                  |
| --------- | --------------------- | ------------------------ |
| Classes   | PascalCase            | `CacheManager`           |
| Methods   | snake_case            | `build_key()`            |
| Constants | UPPER_SNAKE           | `SKIP_BLOCKS`            |
| Hooks     | `jetstaa_mna_` prefix | `jetstaa_mna_skip_block` |
| Options   | `jetstaa_mna_` prefix | `jetstaa_mna_settings`   |

### File Header

```php
<?php
/**
 * Short description.
 *
 * @package IlloDev\MarkdownNegotiation\SubNamespace
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\SubNamespace;
```

### PHPDoc

All public methods must have PHPDoc with `@param`, `@return`, and `@throws`.

### Security

- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`.
- Sanitize all input: `sanitize_text_field()`, `absint()`.
- Nonces on all forms.
- Capability checks on privileged operations.
- No `eval()`, `extract()`, `compact()`, or `file_get_contents()` on remote URLs.

---

## Testing

### Test Structure

```
tests/
├── bootstrap.php           # Autoloader + Brain Monkey setup
├── TestCase.php            # Base class (extends PHPUnit, uses Brain Monkey)
├── Unit/                   # Isolated unit tests
│   ├── ContainerTest.php
│   ├── Cache/
│   ├── Converter/
│   ├── Http/
│   └── Security/
└── Integration/            # Multi-component integration tests
    └── ContentNegotiationFlowTest.php
```

### Running Tests

```bash
composer test              # All tests
composer test:unit         # Unit tests only
composer test:integration  # Integration tests only

# Specific test
./vendor/bin/phpunit --filter="it_converts_headings"
```

### Writing Tests

- Extend `IlloDev\MarkdownNegotiation\Tests\TestCase`.
- Use `@test` annotation or `test_` prefix.
- Stub WordPress functions with Brain Monkey.
- Mock classes with Mockery (never mock `final` classes).
- Clean up `$_SERVER` modifications.
- One assertion concept per test method.

### Coverage

Every public method in `src/` should have at least one test. PRs that add code without tests will not be merged.

---

## Submitting Changes

### Pull Request Checklist

Before submitting a PR, ensure:

- [ ] All tests pass: `composer test`
- [ ] No PHPCS violations: `composer phpcs`
- [ ] `CHANGELOG.md` updated under `## [Unreleased]`
- [ ] Documentation updated (README, readme.txt, docs/) if behavior changed
- [ ] New hooks have PHPDoc and are added to `docs/ARCHITECTURE.md`
- [ ] No `TODO` or `FIXME` left in source
- [ ] Commit messages are clear and descriptive

### PR Description

Please include:

- **What** the change does
- **Why** it's needed
- **How** to test it
- **Screenshots** (for admin UI changes)

### Review Process

1. Automated checks (tests, PHPCS) must pass.
2. A maintainer will review the code.
3. Address review feedback with new commits (don't force-push during review).
4. Once approved, the PR will be squash-merged.

---

## Reporting Bugs

Use the [Bug Report issue template](https://github.com/illodev/markdown-negotiation-for-agents/issues/new?template=bug_report.md) and include:

- Plugin version
- PHP version
- WordPress version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Any relevant error messages or logs

---

## Requesting Features

Use the [Feature Request issue template](https://github.com/illodev/markdown-negotiation-for-agents/issues/new?template=feature_request.md) and include:

- Clear description of the feature
- Use case / motivation
- Suggested implementation (optional)
- Whether you'd be willing to implement it

---

## Security Vulnerabilities

**Do not report security vulnerabilities through public issues.** Please see [SECURITY.md](SECURITY.md) for instructions on responsible disclosure.

---

## License

By contributing, you agree that your contributions will be licensed under the [GPL-2.0-or-later](LICENSE) license.

---

## Questions?

If you're unsure about anything, open a [Discussion](https://github.com/illodev/markdown-negotiation-for-agents/discussions) or reach out at [hello@illodev.com](mailto:hello@illodev.com).

Thank you for helping make WordPress better for AI agents! 🤖
