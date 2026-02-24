# Skill: Refactor Code

## When to Use

Use this skill when restructuring existing code without changing external behavior — improving readability, reducing duplication, simplifying complexity, or improving testability.

## Principles

1. **No behavior changes** — refactoring must not alter the plugin's external behavior.
2. **Tests first** — ensure all existing tests pass before starting. They are your safety net.
3. **Small steps** — make one refactoring move at a time, verify tests pass, then proceed.
4. **Preserve interfaces** — do not change method signatures on public interfaces in `src/Contracts/`.

## Steps

### 1. Establish Baseline

```bash
composer test          # All tests must pass BEFORE any change
```

### 2. Identify the Refactoring

Common refactorings in this codebase:

| Pattern                               | When to use                                               |
| ------------------------------------- | --------------------------------------------------------- |
| Extract Method                        | A method is too long (>30 lines) or has distinct sections |
| Extract Class                         | A class has too many responsibilities                     |
| Replace Conditional with Polymorphism | Complex `if/else` or `match` chains                       |
| Introduce Interface                   | A concrete dependency should be swappable                 |
| Move to Correct Directory             | A class is in the wrong `src/` subdirectory               |
| Remove Duplication                    | Same logic exists in multiple places                      |

### 3. Apply the Refactoring

- **Add `declare(strict_types=1);`** if somehow missing.
- **Maintain naming conventions** (snake_case methods, PascalCase classes).
- **Preserve PHPDoc blocks** — update them if signatures change.
- **Preserve hooks** — never remove a public hook (`apply_filters` / `do_action`) without deprecation.
- **Update DI container** in `Plugin::register_services()` if class relationships change.

### 4. Update Tests

- If a class is split, split the test file too.
- If methods are renamed, update test references.
- If a class is moved, update the namespace in its test.
- Keep test coverage at the same level or higher.

### 5. Verify

```bash
composer test          # ALL tests must still pass
composer phpcs         # No new coding standard violations
```

### 6. Document

- `CHANGELOG.md` — Add under `## [Unreleased] → ### Changed` only if the refactoring is notable.
- `docs/ARCHITECTURE.md` — Update file structure or component descriptions if they changed.
- No need to document internal-only refactorings in README or readme.txt.

## Anti-Patterns to Avoid

- **Do not** combine refactoring with feature work in the same commit.
- **Do not** change method signatures on `ConverterInterface`, `CacheInterface`, `NegotiatorInterface`, or `SanitizerInterface` without updating all implementations and tests.
- **Do not** remove a `final` keyword without checking if it's needed for test mockability.
- **Do not** introduce new dependencies (Composer packages) during a refactoring — that's a feature change.
