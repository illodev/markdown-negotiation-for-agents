# Skill: Add a New Feature

## When to Use

Use this skill when adding a new user-facing feature to the plugin (new setting, new endpoint, new block support, new CLI command, etc.).

## Steps

### 1. Plan

- Identify which layers are affected (HTTP, Converter, Cache, Admin, REST, CLI, Security).
- Determine if a new interface is needed in `src/Contracts/`.
- Check if existing hooks can support the feature or if new hooks are needed.

### 2. Interface First

If the feature introduces a new swappable component:

```bash
# Create interface
touch src/Contracts/NewServiceInterface.php
```

Define the contract with full PHPDoc.

### 3. Implement

- Place the implementation in the correct `src/` subdirectory.
- Use `declare(strict_types=1);` and the correct namespace.
- Inject dependencies via constructor — never use `new` inside business logic.
- Register the service in `Plugin::register_services()`.

### 4. Write Tests

```bash
# Create test file mirroring src/ structure
mkdir -p tests/Unit/SubDirectory/
touch tests/Unit/SubDirectory/NewClassTest.php
```

- Extend `IlloDev\MarkdownNegotiation\Tests\TestCase`.
- Stub WordPress functions via Brain Monkey.
- Cover happy path, edge cases, and error conditions.

### 5. Register Hooks

If the feature needs WordPress hooks:

- Register in the class's `register_hooks()` method.
- Call `register_hooks()` from `Plugin::register_hooks()`.
- Use `jetstaa_mna_` prefix for all custom hooks.

### 6. Admin UI (if applicable)

- Add setting default to `Plugin::load_settings()` and activation hook defaults.
- Register field in `SettingsRegistrar`.
- Render in `SettingsPage`.

### 7. Run Validation

```bash
composer test          # All tests must pass
composer phpcs         # No coding standard violations
```

### 8. Update Documentation

- `CHANGELOG.md` — Add under `## [Unreleased] → ### Added`
- `README.md` — Update feature list and usage examples
- `readme.txt` — Keep in sync with README
- `docs/ARCHITECTURE.md` — Update if architecture is affected
- `docs/DEPLOYMENT.md` — Update if configuration/deployment is affected

### 9. Final Check

```bash
composer test          # Confirm nothing broke
```
