# Skill: Fix a Bug

## When to Use

Use this skill when fixing a reported bug, test failure, or unexpected behavior.

## Steps

### 1. Reproduce

- Identify the failing test or create a minimal reproduction.
- If no test exists yet, **write a failing test first** that captures the bug.

```bash
# Run the specific test file or method
./vendor/bin/phpunit --filter="test_method_name"
```

### 2. Root Cause Analysis

- Trace the code path from the entry point.
- Check relevant files in this order:
  1. The class directly responsible.
  2. Its dependencies (injected via constructor).
  3. WordPress hooks that interact with it.
  4. Cache layer (stale data?).

### 3. Fix

- Make the minimal change needed to fix the bug.
- Ensure the fix doesn't break the interface contract.
- If modifying a `final` class that needs mocking in tests, remove `final`.
- Use proper WordPress escaping/sanitization for any user input.

### 4. Verify

```bash
# Run the specific test
./vendor/bin/phpunit --filter="test_that_was_failing"

# Run full suite
composer test

# Check coding standards
composer phpcs
```

### 5. Update Documentation

- `CHANGELOG.md` — Add under `## [Unreleased] → ### Fixed`
- Format: `- Fix [description of what was broken] in \`ClassName\``
- If the fix changes behavior, update `README.md` and `readme.txt` as needed.

### 6. Final Check

```bash
composer test          # All 82+ tests must pass
```
