# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

### Fixed

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
