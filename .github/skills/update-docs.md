# Skill: Update Documentation

## When to Use

Use this skill whenever a code change modifies behavior, adds features, fixes bugs, or changes the API surface.

## Documentation Files

| File                   | Purpose                                     | Format                                                   |
| ---------------------- | ------------------------------------------- | -------------------------------------------------------- |
| `CHANGELOG.md`         | Version history                             | [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) |
| `README.md`            | GitHub repository documentation             | Markdown with badges                                     |
| `readme.txt`           | WordPress.org plugin page                   | WordPress readme format                                  |
| `docs/ARCHITECTURE.md` | Technical architecture decisions            | Markdown                                                 |
| `docs/DEPLOYMENT.md`   | Installation, configuration, hosting guides | Markdown                                                 |

## Decision Matrix

| What Changed            | CHANGELOG         | README | readme.txt | ARCHITECTURE    | DEPLOYMENT        |
| ----------------------- | ----------------- | ------ | ---------- | --------------- | ----------------- |
| New feature             | ✓ Added           | ✓      | ✓          | If arch changes | If config changes |
| Bug fix                 | ✓ Fixed           | —      | —          | —               | —                 |
| New hook/filter         | ✓ Added           | —      | —          | ✓ hooks table   | —                 |
| New setting             | ✓ Added           | ✓      | ✓          | —               | ✓                 |
| New CLI command         | ✓ Added           | ✓      | —          | —               | ✓                 |
| Security fix            | ✓ Security        | —      | —          | —               | —                 |
| Breaking change         | ✓ Changed/Removed | ✓      | ✓          | ✓               | ✓                 |
| Deprecation             | ✓ Deprecated      | ✓      | ✓          | —               | —                 |
| New dependency          | ✓ Added           | —      | —          | ✓               | —                 |
| Performance improvement | ✓ Changed         | —      | —          | ✓ perf section  | —                 |

## CHANGELOG.md Format

```markdown
## [Unreleased]

### Added

- Add support for `core/details` block processing via `GutenbergProcessor`
- Add `jetstaa_mna_new_filter` filter to customize X behavior

### Changed

- Change cache TTL default from 3600 to 7200 seconds

### Fixed

- Fix incorrect token count for multibyte content in `MarkdownConverter::estimate_tokens()`

### Deprecated

- Deprecate `old_method()` in `ClassName` — use `new_method()` instead

### Removed

- Remove legacy `text/x-markdown` support (use `text/markdown`)

### Security

- Fix header injection vulnerability in `HeaderValidator`
```

### Rules

1. All new entries go under `## [Unreleased]` — never create a version heading for unreleased work.
2. Each entry is a single line starting with `- `.
3. Use present tense, imperative mood: "Add" not "Added" or "Adds".
4. Reference class names with backtick-wrapped identifiers when relevant.
5. Group entries by type (Added, Changed, Fixed, etc.) in the order shown above.

## README.md Sections to Check

- **Features** list — add new features
- **Requirements** — if PHP/WP minimums change
- **Installation** — if install steps change
- **Usage** — if usage patterns change
- **Configuration** — if settings change
- **REST API** — if endpoints change
- **WP-CLI** — if commands change
- **Hooks & Filters** — if hooks change
- **FAQ** — if common questions change

## readme.txt Sync

The `readme.txt` file must match `README.md` content for these sections:

- Description
- Installation
- FAQ
- Changelog (simplified format)

Use WordPress readme.txt format (not full Markdown).

## docs/ARCHITECTURE.md Updates

When updating the architecture document:

1. **Hooks table** — Add new filters/actions with parameters and purpose.
2. **System diagram** — Update if new layers or components are added.
3. **Compatibility matrix** — Update if new integrations are added.
4. **Performance section** — Update benchmarks if conversion logic changes.
5. **Risk analysis** — Update if new risks are identified.
6. **File structure appendix** — Update if files are added or removed.

## docs/DEPLOYMENT.md Updates

When updating the deployment guide:

1. **Configuration table** — Add new settings with defaults and descriptions.
2. **Hosting guides** — Update if new hosting-specific notes arise.
3. **CDN section** — Update if cache behavior changes.
4. **Verification commands** — Update curl examples if endpoints change.
5. **Troubleshooting** — Add new common issues and solutions.
