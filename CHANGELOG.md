# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Remove `load_plugin_textdomain()` call — WordPress 4.6+ auto-loads translations for plugins hosted on WordPress.org
- Update "Tested up to" from 6.7 to 6.9 in `readme.txt` to pass WordPress.org plugin check
- Add direct file access protection (`defined( 'ABSPATH' )` guard) to all PHP files in `src/`
- Replace `unlink()` with `wp_delete_file()` in `FileCacheDriver` and `uninstall.php`
- Replace `is_writable()` with write-test approach in `FileCacheDriver::is_available()`
- Sanitize all `$_SERVER` variable access with `wp_unslash()` + `sanitize_text_field()`
- Use `$wpdb->prepare()` for all direct database queries in `uninstall.php` and deactivation hook
- Add `phpcs:ignore` comments for intentional direct DB queries during cleanup operations
- Prefix all global variables in `uninstall.php` with `jetstaa_mna_` per WPCS naming conventions
- Escape `$id` in `Container` exception message with `phpcs:ignore` (internal FQCN, not user input)
- Reduce `readme.txt` tags from 6 to 5 (WordPress.org limit)

### Added

- `CODE_OF_CONDUCT.md` — Contributor Covenant 2.1 code of conduct
- `SECURITY.md` — Responsible disclosure policy and security contact
- `.editorconfig` — Consistent coding style across editors and IDEs
- `.gitattributes` — LF line ending normalisation and export-ignore rules for releases
- `.github/ISSUE_TEMPLATE/bug_report.md` — Structured bug report template
- `.github/ISSUE_TEMPLATE/feature_request.md` — Structured feature request template
- `.github/ISSUE_TEMPLATE/config.yml` — Issue template chooser with discussion and security links
- `.github/PULL_REQUEST_TEMPLATE.md` — Pull request checklist template
- `sandbox/` — Docker-based WordPress sandbox with Playwright E2E test suite (18 tests)
- `patchwork.json` — Patchwork configuration for mocking PHP built-in functions in unit tests
- `tests/Unit/Http/ResponseHandlerTest.php` — Unit tests for `ResponseHandler` security validations
- `docs/screenshots/` — Visual documentation: HTML response, plugin admin, settings page, metabox, Markdown output, and REST API endpoints
- `tests/Unit/Admin/SettingsRegistrarTest.php` — Unit tests for `handle_endpoint_change()` covering enabled/disabled/CPT scenarios

### Fixed

- `.md` URL endpoint returning 404: `flush_rewrite_rules()` was called inside the sanitize callback (before the new settings were saved), so the rules were flushed without the `.md` rewrite rules being registered. Fixed by moving the flush to `update_option_{option}` action, which fires after the save and where rules can be added beforehand.

- `ObjectCacheDriver::is_available()` returning `null` instead of `bool`, causing a PHP 8.1 TypeError on plugin activation when no external object cache is configured
- `ResponseHandler::handle_request()` silently ignoring malformed `Accept` headers instead of returning HTTP 400; oversized or null-byte-injected headers now correctly return `400 Bad Request`

## [1.0.0] - 2026-02-24

### Added

- HTTP content negotiation for `Accept: text/markdown` and `text/x-markdown`
- RFC 7231 compliant Accept header parsing with quality values
- Gutenberg block processing (headings, lists, tables, code, images, embeds, columns, groups)
- Classic Editor support via standard `the_content` filter pipeline
- WooCommerce product data extraction (price, SKU, stock, attributes)
- REST API endpoint: `GET /wp-json/jetstaa-mna/v1/markdown/<id>`
- REST API list endpoint: `GET /wp-json/jetstaa-mna/v1/markdown`
- REST API status endpoint: `GET /wp-json/jetstaa-mna/v1/status`
- REST API `markdown` field on post type responses
- WP-CLI commands: `generate`, `convert`, `cache`, `status`
- Multi-driver caching (object cache, transients, file system)
- Automatic cache invalidation on post save, delete, status change, term change
- Rate limiting per IP address (configurable)
- HTML sanitization: strips scripts, styles, nav, sidebar, inline events
- Markdown sanitization: removes JavaScript URLs, data URIs, null bytes
- Access control: respects post status, password protection, per-post disable
- Admin settings page under Settings → Markdown Negotiation
- Per-post meta box with disable toggle and preview link
- `Vary: Accept` header on all responses
- `<link rel="alternate" type="text/markdown">` in HTML `<head>`
- HTTP `Link` header for Markdown discovery
- `X-Markdown-Tokens` header with estimated token count
- `X-Markdown-Source: wordpress-plugin` identification header
- ETag and Last-Modified headers for conditional requests (304 Not Modified)
- `.md` URL extension support (optional, requires permalink flush)
- `?format=markdown` query parameter support
- WordPress Multisite network activation support
- PSR-4 autoloading via Composer
- Dependency injection container
- Interface-based architecture for swappable components
- PHP 8.1+ with strict types
- PHPUnit test suite
- PHPCS configuration for WordPress Coding Standards
- Complete inline documentation with PHPDoc
- readme.txt in WordPress.org format
- Developer documentation with hooks/filters reference

### Security

- Accept header length validation (max 1024 bytes)
- Null byte and non-ASCII rejection in headers
- HTML comment removal from content
- Inline event handler stripping
- Non-content element removal (nav, sidebar, widget areas)
- JavaScript and data: URL sanitization in Markdown output
- Password-protected post enforcement
- Rate limiting to prevent abuse
