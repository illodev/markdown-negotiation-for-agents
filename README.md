# Markdown Negotiation for Agents

[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B.svg)](https://wordpress.org)

**Serve your WordPress content as Markdown via HTTP content negotiation.** Ideal for AI agents, LLMs, developer tools, and any client that prefers Markdown over HTML.

Inspired by [Cloudflare's Markdown for Agents](https://blog.cloudflare.com/markdown-for-agents), but implemented at the application level within WordPress.

---

## How It Works

When a client sends a request with `Accept: text/markdown`, the plugin intercepts WordPress's template loading and responds with clean Markdown instead of HTML. The URL stays the same — this is real HTTP content negotiation.

```
# Standard browser request
GET /my-awesome-post HTTP/1.1
Accept: text/html
→ Returns: Normal HTML page

# AI agent request
GET /my-awesome-post HTTP/1.1
Accept: text/markdown
→ Returns: Clean Markdown content
```

### Response Headers

```
Content-Type: text/markdown; charset=utf-8
Vary: Accept
X-Markdown-Source: wordpress-plugin
X-Markdown-Tokens: 1234
X-Markdown-Plugin-Version: 1.0.0
Cache-Control: public, max-age=300, s-maxage=3600
```

---

## Features

- **Real Content Negotiation** — `Accept: text/markdown` and `text/x-markdown`
- **Alternative Access** — `?format=markdown` query parameter, `.md` URL extension
- **Gutenberg Support** — Full block processing (headings, lists, tables, code, images, embeds, columns)
- **WooCommerce** — Product details, pricing, attributes, stock status
- **REST API** — `/wp-json/jetstaa-mna/v1/markdown/<id>` endpoint + `markdown` field on post responses
- **WP-CLI** — `wp markdown generate`, `wp markdown convert`, `wp markdown cache`
- **Intelligent Caching** — Object cache, transients, or file-based. Auto-invalidation on post save
- **Security** — Rate limiting, sanitization, no private data leakage
- **Multisite** — Network activation support
- **SEO Safe** — Proper `Vary: Accept` headers, `<link rel="alternate">` tags
- **Token Estimation** — `X-Markdown-Tokens` header for LLM context planning

---

## Requirements

- PHP 8.1 or higher
- WordPress 6.0 or higher
- Composer (for autoloading and dependencies)

---

## Installation

### From Source

```bash
cd wp-content/plugins/
git clone https://github.com/illodev/markdown-negotiation-for-agents.git
cd markdown-negotiation-for-agents
composer install --no-dev --optimize-autoloader
```

### Via Composer (as dependency)

```bash
composer require illodev/markdown-negotiation-for-agents
```

Then activate in WordPress admin or via WP-CLI:

```bash
wp plugin activate markdown-negotiation-for-agents
```

---

## Configuration

Navigate to **Settings → Markdown Negotiation** in WordPress admin.

| Setting                    | Description                      | Default    |
| -------------------------- | -------------------------------- | ---------- |
| Enable Content Negotiation | Respond to Accept: text/markdown | ✅         |
| Enable .md Extension       | Access via /post-slug.md         | ❌         |
| Enable ?format=markdown    | Access via query parameter       | ✅         |
| Post Types                 | Which CPTs to enable             | post, page |
| WooCommerce Products       | Include product data             | ✅         |
| REST API Markdown          | /wp-json endpoint & field        | ✅         |
| Token Count Header         | X-Markdown-Tokens header         | ✅         |
| Cache Enabled              | Cache converted Markdown         | ✅         |
| Cache Driver               | auto, object, transient, file    | auto       |
| Cache TTL                  | Seconds to cache                 | 3600       |
| Rate Limiting              | Limit requests per IP            | ❌         |

---

## Usage

### Content Negotiation (Primary)

```bash
curl -H "Accept: text/markdown" https://example.com/my-post/
```

### Query Parameter

```bash
curl https://example.com/my-post/?format=markdown
```

### .md Extension (requires activation)

```bash
curl https://example.com/my-post.md
```

### REST API

```bash
# Dedicated endpoint
curl https://example.com/wp-json/jetstaa-mna/v1/markdown/42

# Standard REST with markdown field
curl https://example.com/wp-json/wp/v2/posts/42?_format=markdown

# List available posts
curl https://example.com/wp-json/jetstaa-mna/v1/markdown?post_type=post

# Plugin status
curl https://example.com/wp-json/jetstaa-mna/v1/status
```

### WP-CLI

```bash
# Convert a specific post
wp markdown convert 42

# Generate and cache all posts
wp markdown generate --all

# Generate specific post types
wp markdown generate --all --post_type=page

# Export to files
wp markdown generate --all --output=file --dir=/tmp/markdown-export

# Flush cache
wp markdown cache flush

# Show status
wp markdown status
```

---

## Hooks & Filters Reference

### Filters

| Filter                              | Description                           | Parameters                                        |
| ----------------------------------- | ------------------------------------- | ------------------------------------------------- |
| `jetstaa_mna_settings`              | Modify plugin settings at runtime     | `array $settings`                                 |
| `jetstaa_mna_accept_header`         | Override the Accept header value      | `string $accept`                                  |
| `jetstaa_mna_allowed_post_types`    | Modify allowed post types             | `array $types, WP_Post $post`                     |
| `jetstaa_mna_skip_block`            | Skip specific Gutenberg blocks        | `bool $skip, string $name, array $block`          |
| `jetstaa_mna_block_html`            | Filter block HTML before conversion   | `string $html, string $name, array $block`        |
| `jetstaa_mna_extracted_html`        | Filter extracted HTML                 | `string $html, WP_Post $post, array $options`     |
| `jetstaa_mna_converted_markdown`    | Filter final Markdown output          | `string $md, string $html, array $opts, ?WP_Post` |
| `jetstaa_mna_response_markdown`     | Filter Markdown before HTTP response  | `string $md, WP_Post $post, string $media_type`   |
| `jetstaa_mna_sanitized_html`        | Filter sanitized HTML                 | `string $html`                                    |
| `jetstaa_mna_sanitized_markdown`    | Filter sanitized Markdown             | `string $markdown`                                |
| `jetstaa_mna_cache_key`             | Modify cache keys                     | `string $key, int $post_id, string $suffix`       |
| `jetstaa_mna_strip_shortcodes`      | Modify list of shortcodes to strip    | `array $shortcodes`                               |
| `jetstaa_mna_can_access`            | Override access control               | `bool $can_access, WP_Post $post`                 |
| `jetstaa_mna_trusted_proxy_headers` | Override trusted proxy headers for IP | `array $headers`                                  |

### Actions

| Action                            | Description                  | Parameters                             |
| --------------------------------- | ---------------------------- | -------------------------------------- |
| `jetstaa_mna_initialized`         | Plugin fully initialized     | `Plugin $plugin`                       |
| `jetstaa_mna_services_registered` | After DI container setup     | `Container $container, Plugin $plugin` |
| `jetstaa_mna_configure_converter` | Configure the HTML converter | `HtmlConverter $converter`             |
| `jetstaa_mna_send_headers`        | After response headers sent  | `WP_Post $post, string $markdown`      |
| `jetstaa_mna_cache_invalidated`   | After post cache invalidated | `int $post_id`                         |
| `jetstaa_mna_cache_flushed`       | After full cache flush       | —                                      |

---

## Architecture

```
src/
├── Contracts/
│   ├── CacheInterface.php          # Cache driver contract
│   ├── ConverterInterface.php      # HTML→Markdown converter contract
│   ├── NegotiatorInterface.php     # Content negotiation contract
│   └── SanitizerInterface.php      # Content sanitization contract
├── Admin/
│   ├── MetaBox.php                 # Per-post controls
│   ├── SettingsPage.php            # Admin settings UI
│   └── SettingsRegistrar.php       # WordPress Settings API integration
├── Cache/
│   ├── CacheManager.php            # Cache orchestrator + invalidation
│   ├── FileCacheDriver.php         # File-based cache
│   ├── ObjectCacheDriver.php       # WP object cache (Redis/Memcached)
│   └── TransientDriver.php         # WP transients (database)
├── Cli/
│   └── MarkdownCommand.php         # WP-CLI commands
├── Converter/
│   ├── ContentExtractor.php        # Post content extraction
│   ├── GutenbergProcessor.php      # Block processing
│   ├── MarkdownConverter.php       # league/html-to-markdown wrapper
│   └── ShortcodeProcessor.php      # Shortcode handling
├── Http/
│   ├── AlternateEndpoint.php       # .md and ?format= handlers
│   ├── ContentNegotiator.php       # RFC 7231 Accept header parsing
│   ├── HeaderValidator.php         # Header security validation
│   └── ResponseHandler.php         # Markdown HTTP response
├── Multisite/
│   └── NetworkHandler.php          # Multisite support
├── Rest/
│   ├── FieldRegistrar.php          # REST API field
│   └── MarkdownController.php      # REST API endpoints
├── Security/
│   ├── AccessControl.php           # Post access enforcement
│   ├── RateLimiter.php             # IP-based rate limiting
│   └── Sanitizer.php               # HTML/Markdown sanitization
├── Container.php                   # Lightweight DI container
└── Plugin.php                      # Main orchestrator (singleton)
```

### Design Principles

- **PSR-4** autoloading via Composer
- **Dependency Injection** via lightweight container
- **Interfaces** for all swappable components (SOLID)
- **Strict types** throughout
- **No global functions** (except bootstrap in main file)
- **WordPress Coding Standards** compatible

---

## Cache Compatibility Guide

### Vary: Accept Header

The plugin adds `Vary: Accept` to all responses. This instructs caches to maintain separate cached copies based on the Accept header.

### Plugin Caches

| Cache Plugin    | Compatible | Notes                                 |
| --------------- | ---------- | ------------------------------------- |
| WP Super Cache  | ✅         | Varies by Accept header automatically |
| W3 Total Cache  | ✅         | Enable "Vary" header support          |
| LiteSpeed Cache | ✅         | Add `Vary: Accept` to cache settings  |
| WP Rocket       | ✅         | Works out of the box                  |

### CDN / Edge Caches

| CDN        | Compatible | Notes                                                    |
| ---------- | ---------- | -------------------------------------------------------- |
| Cloudflare | ✅         | Respects Vary headers. Consider Cache Rules for markdown |
| Fastly     | ✅         | Full Vary support                                        |
| CloudFront | ⚠️         | Must whitelist Accept header in cache policy             |
| Varnish    | ✅         | Varies automatically on Accept                           |

**Important:** If your CDN does not vary on Accept by default, Markdown responses may be served to HTML clients. Configure your CDN to include `Accept` in its cache key.

---

## SEO & AI Considerations

### Search Engines

- **No duplicate content risk** — Same URL serves different formats based on Accept header
- **Vary: Accept** — Tells search engines content varies by request headers
- **`<link rel="alternate">`** — HTML pages include a link to the Markdown representation
- **Link header** — HTTP `Link: <url>; rel="alternate"; type="text/markdown"` header

### AI Agent Discovery

AI agents can discover Markdown availability via:

1. **Link header** in HTTP responses
2. **`<link rel="alternate" type="text/markdown">`** in HTML `<head>`
3. **REST API** `/wp-json/jetstaa-mna/v1/status` endpoint
4. **Content negotiation** — Simply request with `Accept: text/markdown`

The plugin does **not** use User-Agent sniffing to detect AI agents. This is intentional — content negotiation is the correct HTTP mechanism for format selection.

---

## Security

### Threat Model

| Vector           | Mitigation                                           |
| ---------------- | ---------------------------------------------------- |
| Header spoofing  | Strict Accept header validation, length limits       |
| Cache poisoning  | `Vary: Accept` header on all responses               |
| XSS via Markdown | HTML/JS stripped before conversion, output sanitized |
| Data leakage     | Private/draft/password-protected posts excluded      |
| Abuse/scraping   | Optional rate limiting per IP                        |
| Per-post control | Disable Markdown per post via meta box               |

---

## Performance

### TTFB Impact

- **First request:** ~5-20ms overhead for HTML→Markdown conversion (depends on content size)
- **Cached request:** <1ms overhead (object cache) or ~2ms (transient)
- **File cache:** ~1ms overhead

### Scalability for 100k+ Posts

- Cache is per-post-ID, not global
- Invalidation is targeted (only affected post)
- Object cache recommended for high-traffic sites
- Background generation via WP-CLI: `wp markdown generate --all`

### Moving to Background Processing

For enterprise sites, pre-generate Markdown on post save:

```php
add_action( 'save_post', function( int $post_id ): void {
    // Schedule async generation.
    wp_schedule_single_event( time(), 'jetstaa_mna_generate_post', [ $post_id ] );
}, 20 );
```

---

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run unit tests only
composer test:unit

# Run PHPCS
composer phpcs

# Fix coding standards
composer phpcbf
```

---

## Changelog

### 1.0.0 (2026-02-24)

- Initial release
- HTTP content negotiation (Accept: text/markdown, text/x-markdown)
- Gutenberg block processing
- WooCommerce product support
- REST API endpoint and field
- WP-CLI commands
- Multi-driver caching (object cache, transients, file)
- Rate limiting
- Multisite support
- Per-post disable control
- Comprehensive sanitization

---

## Roadmap

- [ ] llms.txt endpoint generation
- [ ] Sitemap for Markdown URLs
- [ ] Background async conversion on post save
- [ ] Edge Worker integration examples (Cloudflare Workers, Lambda@Edge)
- [ ] Pro version: API key authentication, analytics, custom templates
- [ ] OpenAPI/MCP schema endpoint
- [ ] Markdown template customization
- [ ] Bulk export to static site generator formats

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.
