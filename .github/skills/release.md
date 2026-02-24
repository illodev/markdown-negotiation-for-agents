# Skill: Prepare a Release

## When to Use

Use this skill when cutting a new version of the plugin for distribution.

## Steps

### 1. Pre-Release Verification

```bash
# Ensure clean working tree
git status

# Run full test suite
composer test

# Run coding standards check
composer phpcs

# Verify no TODO/FIXME left in source
grep -rn "TODO\|FIXME\|HACK\|XXX" src/ --include="*.php"
```

### 2. Determine Version Number

Follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

| Change Type                        | Version Bump | Example       |
| ---------------------------------- | ------------ | ------------- |
| Bug fix only                       | PATCH        | 1.0.0 → 1.0.1 |
| New feature (backwards compatible) | MINOR        | 1.0.0 → 1.1.0 |
| Breaking change                    | MAJOR        | 1.0.0 → 2.0.0 |

### 3. Update Version Numbers

Update these locations with the new version:

#### `markdown-negotiation-for-agents.php`

```php
/**
 * Version: X.Y.Z          ← Plugin header
 */
define( 'JETSTAA_MNA_VERSION', 'X.Y.Z' );  // ← Constant
```

#### `phpunit.xml.dist`

```xml
<const name="JETSTAA_MNA_VERSION" value="X.Y.Z" />
```

### 4. Update CHANGELOG.md

Move entries from `## [Unreleased]` to the new version heading:

```markdown
## [Unreleased]

## [X.Y.Z] - YYYY-MM-DD

### Added

- (moved from Unreleased)

### Fixed

- (moved from Unreleased)
```

Keep the empty `## [Unreleased]` heading for future changes.

### 5. Update readme.txt

Ensure the `== Changelog ==` section in `readme.txt` reflects the new version.
Update the `Stable tag:` header to the new version.

### 6. Final Verification

```bash
composer test          # One last time — all tests pass
composer phpcs         # Clean standards
```

### 7. Commit and Tag

```bash
git add -A
git commit -m "Release vX.Y.Z"
git tag -a vX.Y.Z -m "Version X.Y.Z"
git push origin main --tags
```

### 8. Build Distribution Archive

```bash
# Create clean build without dev dependencies
composer install --no-dev --optimize-autoloader

# Create ZIP excluding dev files
zip -r markdown-negotiation-for-agents-X.Y.Z.zip . \
  -x ".git/*" \
  -x ".github/*" \
  -x "tests/*" \
  -x ".phpunit.cache/*" \
  -x "phpunit.xml.dist" \
  -x "phpcs.xml.dist" \
  -x ".gitignore" \
  -x "composer.lock"

# Restore dev dependencies
composer install
```

## Checklist

- [ ] All tests passing
- [ ] No PHPCS violations
- [ ] Version updated in plugin header, constant, and phpunit.xml.dist
- [ ] CHANGELOG.md updated with release date
- [ ] readme.txt Stable tag updated
- [ ] README.md reflects current feature set
- [ ] Git tag created
- [ ] Distribution ZIP built without dev dependencies
