# Architectural Design Document

## Markdown Negotiation for Agents — WordPress Plugin

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Design Philosophy](#design-philosophy)
3. [System Architecture](#system-architecture)
4. [Request Lifecycle](#request-lifecycle)
5. [Dependency Injection Container](#dependency-injection-container)
6. [Content Negotiation (RFC 7231)](#content-negotiation-rfc-7231)
7. [Converter Pipeline](#converter-pipeline)
8. [Cache Architecture](#cache-architecture)
9. [Security Model](#security-model)
10. [SEO & AI Considerations](#seo--ai-considerations)
11. [Extensibility & Hooks](#extensibility--hooks)
12. [Compatibility Matrix](#compatibility-matrix)
13. [Performance Characteristics](#performance-characteristics)
14. [Risk Analysis](#risk-analysis)
15. [Future Roadmap](#future-roadmap)

---

## Executive Summary

**Markdown Negotiation for Agents** is a WordPress plugin that implements HTTP content negotiation (RFC 7231) at the application level, serving WordPress content as Markdown when clients send `Accept: text/markdown` headers.

This is functionally equivalent to Cloudflare's "Markdown for Agents", but implemented within WordPress rather than at the edge/CDN layer. The key advantage: it understands WordPress content semantics (Gutenberg blocks, shortcodes, post meta) that edge solutions cannot access.

### Why This Exists

AI agents (GPT, Claude, Gemini, crawlers) prefer Markdown because:

- **Lower token cost**: ~60% fewer tokens vs. HTML for the same content
- **Higher signal-to-noise**: No navigation, sidebars, scripts to filter
- **Better comprehension**: Clean structure maps directly to LLM attention

---

## Design Philosophy

### SOLID Principles

| Principle                 | Implementation                                                                                                    |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| **Single Responsibility** | Each class has one job: `ContentNegotiator` only parses Accept headers, `MarkdownConverter` only converts HTML→MD |
| **Open/Closed**           | Plugin behavior is extensible via 25+ filters/actions without modifying source                                    |
| **Liskov Substitution**   | All core services implement interfaces (`ConverterInterface`, `CacheInterface`, etc.)                             |
| **Interface Segregation** | Four focused interfaces instead of one monolithic interface                                                       |
| **Dependency Inversion**  | High-level modules depend on abstractions (interfaces), not concrete implementations                              |

### WordPress Integration Style

- Follows WordPress Coding Standards (WPCS 3.0)
- Uses WordPress hooks system for extensibility
- Integrates with existing WordPress APIs (Settings API, REST API, Transients)
- Translation-ready with proper text domain handling

---

## System Architecture

```
┌────────────────────────────────────────────────────────────┐
│                    HTTP REQUEST                             │
│                Accept: text/markdown                        │
└────────────────────┬───────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────┐
│                   SECURITY LAYER                            │
│  ┌──────────────┐ ┌──────────────┐ ┌───────────────────┐   │
│  │ HeaderValid. │ │ RateLimiter  │ │  AccessControl    │   │
│  └──────────────┘ └──────────────┘ └───────────────────┘   │
└────────────────────┬───────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────┐
│               CONTENT NEGOTIATION                           │
│  ┌────────────────────────────────────────────────────┐     │
│  │  ContentNegotiator (RFC 7231 §5.3.2)              │     │
│  │  - Parse Accept header quality values              │     │
│  │  - Support text/markdown, text/x-markdown          │     │
│  └────────────────────────────────────────────────────┘     │
└────────────────────┬───────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────┐
│                  CACHE LAYER                                │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐   │
│  │ ObjectCache  │ │ Transient   │ │    FileCache        │   │
│  │(Redis/Memc.) │ │ (DB-backed) │ │ (uploads/md-cache/) │   │
│  └─────────────┘ └─────────────┘ └─────────────────────┘   │
│              ▲    CacheManager orchestrates    ▲             │
└──────────────┼──────────────┬─────────────────┼────────────┘
               │              │ cache miss      │
               │              ▼                 │
┌──────────────┼────────────────────────────────┼────────────┐
│              │    CONVERTER PIPELINE           │            │
│  ┌───────────┴──────────────────────────────┐ │            │
│  │         ContentExtractor                  │ │            │
│  │  1. GutenbergProcessor (parse blocks)     │ │            │
│  │  2. ShortcodeProcessor (expand/strip)     │ │            │
│  │  3. Sanitizer (strip non-content HTML)    │ │            │
│  └───────────────────┬──────────────────────┘ │            │
│                      │ clean HTML              │            │
│                      ▼                         │            │
│  ┌──────────────────────────────────────────┐  │            │
│  │      MarkdownConverter                    │  │            │
│  │  league/html-to-markdown ^5.1             │──┘            │
│  │  + TableConverter                         │  (cache set)  │
│  │  + Post-processing (cleanup, entities)    │               │
│  └──────────────────────────────────────────┘               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌────────────────────────────────────────────────────────────┐
│               RESPONSE HANDLER                              │
│  Content-Type: text/markdown; charset=utf-8                 │
│  Vary: Accept                                               │
│  X-Markdown-Tokens: 1847                                    │
│  ETag: W/"42-a1b2c3..."                                     │
│  Last-Modified: Thu, 01 Jan 2025 00:00:00 GMT               │
│  Link: <url>; rel="alternate"; type="text/markdown"         │
└────────────────────────────────────────────────────────────┘
```

---

## Request Lifecycle

### Phase 1: Interception (`template_redirect`, priority 1)

```
WordPress receives request → is_singular()? → ResponseHandler::handle_request()
```

The plugin hooks into `template_redirect` at priority 1 (before most theme code runs), ensuring the Markdown response bypasses all theme template rendering.

### Phase 2: Validation

```
HeaderValidator::validate_request()
  → Checks Accept header length ≤ 1024 chars
  → Validates header format (no injection attacks)
  → Verifies no malicious characters

RateLimiter::check() (if enabled)
  → Per-IP rate limiting via transients
  → Configurable requests/window (default: 60/60s)

AccessControl::can_access($post)
  → Rejects draft/pending/private posts for non-authenticated users
  → Handles password-protected posts
  → Respects post_password cookie
```

### Phase 3: Negotiation

```
ContentNegotiator::wants_markdown()
  → Parse Accept header into sorted type list
  → Quality values (q=) determine priority
  → text/markdown or text/x-markdown must be highest priority
  → If text/html or */* has higher q-value, serve HTML normally
```

### Phase 4: Conversion

```
CacheManager::get(key)
  → Cache HIT? Return immediately
  → Cache MISS? Continue pipeline...

ContentExtractor::extract($post)
  → GutenbergProcessor: Parse blocks, skip navigation/widgets
  → ShortcodeProcessor: Expand or strip shortcodes
  → Sanitizer: Remove scripts, styles, forms

MarkdownConverter::convert($html)
  → league/html-to-markdown with custom config
  → Post-process: clean blank lines, entities, comments
  → CacheManager::set(key, markdown)
```

### Phase 5: Response

```
ResponseHandler::send_markdown_response()
  → Set Content-Type, Vary, ETag, Last-Modified
  → X-Markdown-Tokens header (estimated via char_count/4)
  → Handle 304 Not Modified (If-None-Match, If-Modified-Since)
  → echo $markdown; exit;
```

---

## Dependency Injection Container

### Why Custom Instead of PSR-11 Package?

WordPress plugins should minimize external dependencies. Our `Container` class provides:

- **`bind(id, factory)`**: Register a service factory
- **`singleton(id, factory)`**: Register a factory that resolves once
- **`instance(id, object)`**: Register a pre-built instance
- **`get(id)`**: Resolve a service (lazy instantiation)
- **`has(id)`**: Check if registered
- **`forget(id)`**: Remove a service

### Service Registration (Plugin.php)

All services are registered in `Plugin::register_services()` with interface-based bindings:

```php
// Concrete implementations bound to interfaces
$container->singleton(ConverterInterface::class, fn($c) => new MarkdownConverter(...));
$container->singleton(CacheInterface::class, fn($c) => $this->resolve_cache_driver());
$container->singleton(NegotiatorInterface::class, fn($c) => new ContentNegotiator());
$container->singleton(SanitizerInterface::class, fn($c) => new Sanitizer());
```

### Extension Point

Developers can replace any service after registration:

```php
add_action('jetstaa_mna_services_registered', function(Container $container) {
    $container->forget(ConverterInterface::class);
    $container->singleton(ConverterInterface::class, fn($c) => new MyCustomConverter());
});
```

---

## Content Negotiation (RFC 7231)

### Implementation: `ContentNegotiator`

Follows RFC 7231, Section 5.3.2 for Accept header parsing:

```
Accept: text/markdown, text/html;q=0.9, */*;q=0.1
```

**Parsing algorithm:**

1. Split by comma
2. Extract media type and quality parameter
3. Sort by quality descending (stable sort for equal quality)
4. Iterate: first matching Markdown type wins
5. If `text/html` or `*/*` appears before any Markdown type → serve HTML

**Supported types:**

- `text/markdown` (RFC 7763, primary)
- `text/x-markdown` (legacy, for backwards compatibility)

### Alternate Access Methods

Beyond Accept header negotiation:

| Method          | Example                                      | Setting         |
| --------------- | -------------------------------------------- | --------------- |
| `.md` extension | `example.com/my-post.md`                     | `endpoint_md`   |
| Query parameter | `example.com/my-post?format=markdown`        | `query_format`  |
| REST API        | `/wp-json/jetstaa-mna/v1/markdown/{id}`      | `rest_markdown` |
| REST field      | `/wp-json/wp/v2/posts/{id}?_fields=markdown` | `rest_markdown` |

---

## Converter Pipeline

### Why league/html-to-markdown?

| Library                     | Downloads | PHP 8+  | Tables | Maintained | Decision             |
| --------------------------- | --------- | ------- | ------ | ---------- | -------------------- |
| **league/html-to-markdown** | **5M+**   | **✓**   | **✓**  | **Active** | **✓ Chosen**         |
| markdownify                 | 500K      | Partial | ✗      | Abandoned  | ✗                    |
| html2text                   | 2M        | ✓       | ✗      | Minimal    | ✗                    |
| Custom DOMDocument          | N/A       | ✓       | Manual | Self       | ✗ Maintenance burden |

### Gutenberg Block Processing

The `GutenbergProcessor` understands WordPress block grammar:

**Skipped blocks** (non-content):

- `core/navigation`, `core/search`, `core/loginout`
- `core/spacer`, `core/separator`, `core/buttons`
- `core/site-logo`, `core/site-title`, `core/template-part`

**Specially processed blocks:**

- `core/code` → Preserves language class for fenced code blocks
- `core/embed` → Extracts URL as link
- `core/gallery` → Renders as individual images
- `core/columns` → Flattens to sequential content
- `core/group` → Unwraps inner content

### Shortcode Processing

The `ShortcodeProcessor` handles WordPress shortcodes:

1. Executes shortcodes via `do_shortcode()` to get HTML output
2. For shortcodes that can't execute (missing plugins), strips them
3. Custom filter `jetstaa_mna_process_shortcode` for per-shortcode control

### Post-Processing

After conversion, the `MarkdownConverter` performs:

- Removes excessive blank lines (3+ → 2)
- Strips surviving HTML comments
- Cleans HTML entities
- Ensures trailing newline
- Fixes broken link references

---

## Cache Architecture

### Three-Tier Strategy

```
Tier 1: Object Cache (Redis/Memcached)  ← Fastest, if available
Tier 2: WordPress Transients            ← DB-backed fallback
Tier 3: File Cache (uploads/md-cache/)  ← Filesystem fallback
```

**Auto-detection** (`cache_driver: 'auto'`):

1. Check if persistent object cache exists (`ObjectCacheDriver::is_available()`)
2. If yes → use Object Cache (sub-millisecond for Redis)
3. If no → fall back to Transients (DB query per hit)

### Cache Key Strategy

```
md_{post_id}  →  "md_42"
```

Keys are intentionally simple. The `jetstaa_mna_cache_key` filter allows customization for multilingual sites or custom variants.

### Automatic Invalidation

Cache is automatically cleared on:

- `save_post` → Post content changes
- `trashed_post` / `deleted_post` → Post removal
- `transition_post_status` → Status changes (publish→draft)
- `set_object_terms` → Category/tag changes
- `updated_post_meta` → Relevant meta updates (excludes `_edit_lock`, etc.)

### CDN/Proxy Integration

The `Vary: Accept` header is added to **all** WordPress responses (not just Markdown ones). This ensures:

- CDNs cache separate variants for HTML and Markdown
- Proxy caches (Varnish, Nginx) respect content negotiation
- Cloudflare, Fastly, KeyCDN will maintain separate cache entries

**Headers set on Markdown responses:**

```
Cache-Control: public, max-age=300, s-maxage=3600
ETag: W/"42-a1b2c3..."
Last-Modified: Thu, 01 Jan 2025 00:00:00 GMT
Vary: Accept
```

---

## Security Model

### Layer 1: Header Validation

`HeaderValidator` prevents header injection attacks:

- Accept header max length: 1024 characters
- Strips null bytes and control characters
- Validates media type format against regex

### Layer 2: Access Control

`AccessControl` enforces WordPress visibility rules:

- Private posts → only accessible to logged-in users with `read_private_posts`
- Draft/pending posts → not accessible via Markdown
- Password-protected → checks `wp-postpass_` cookie
- Filter: `jetstaa_mna_can_access` for custom rules

### Layer 3: Rate Limiting

`RateLimiter` prevents abuse:

- Per-IP tracking via WordPress transients
- Default: 60 requests / 60 seconds
- Returns 429 Too Many Requests with `Retry-After` header
- Configurable via admin settings

### Layer 4: Content Sanitization

`Sanitizer` cleans content before conversion:

- Strips `<script>`, `<style>`, `<form>`, `<input>` elements
- Removes event handlers (`onclick`, `onerror`)
- Prevents XSS through Markdown (sanitizes raw HTML in output)

---

## SEO & AI Considerations

### Duplicate Content Prevention

Content negotiation inherently **does not create duplicate content** because:

1. Same URL serves different representations based on Accept header
2. `Vary: Accept` tells search engines this is the same canonical resource
3. Google and Bing understand content negotiation (HTTP standard since 1999)

### Discovery Mechanisms

The plugin provides three discovery methods for AI agents:

**1. `<link>` tag in HTML `<head>`:**

```html
<link
  rel="alternate"
  type="text/markdown"
  href="https://example.com/my-post"
  title="My Post"
/>
```

**2. `Link` HTTP header:**

```
Link: <https://example.com/my-post>; rel="alternate"; type="text/markdown"
```

**3. `.well-known` compatible** (via `?format=markdown`):
Agents can append `?format=markdown` to any URL without knowing the Accept header mechanism.

### Impact on Search Engine Crawling

- **Googlebot**: Sends `Accept: text/html, */*` — the `*/*` at lower quality means HTML is served normally
- **Bingbot**: Same behavior as Googlebot
- **AI crawlers** (GPTBot, ClaudeBot, etc.): When configured to send `Accept: text/markdown`, receive optimized content

### robots.txt Considerations

No special robots.txt rules needed because:

- The same URL serves both formats
- Content negotiation is transparent to well-behaved crawlers
- AI crawlers that want Markdown must explicitly request it

---

## Extensibility & Hooks

### Filters (modify data)

| Filter                           | Parameters                     | Purpose                             |
| -------------------------------- | ------------------------------ | ----------------------------------- |
| `jetstaa_mna_settings`           | `$settings`                    | Modify loaded settings              |
| `jetstaa_mna_accept_header`      | `$accept`                      | Override Accept header source       |
| `jetstaa_mna_skip_block`         | `$skip, $name, $block`         | Skip specific Gutenberg blocks      |
| `jetstaa_mna_block_html`         | `$html, $name, $block`         | Modify block HTML before conversion |
| `jetstaa_mna_extracted_content`  | `$html, $post, $options`       | Modify extracted HTML               |
| `jetstaa_mna_converted_markdown` | `$md, $html, $options, $post`  | Modify final Markdown               |
| `jetstaa_mna_response_markdown`  | `$md, $post, $media_type`      | Modify response body                |
| `jetstaa_mna_allowed_post_types` | `$types, $post`                | Modify allowed CPTs                 |
| `jetstaa_mna_cache_key`          | `$key, $post_id, $suffix`      | Customize cache keys                |
| `jetstaa_mna_process_shortcode`  | `$html, $tag, $content, $atts` | Per-shortcode processing            |
| `jetstaa_mna_sanitize_html`      | `$html`                        | Custom HTML sanitization            |
| `jetstaa_mna_sanitize_markdown`  | `$markdown`                    | Custom Markdown sanitization        |

### Actions (execute side effects)

| Action                            | Parameters            | Purpose                     |
| --------------------------------- | --------------------- | --------------------------- |
| `jetstaa_mna_initialized`         | `$plugin`             | After plugin bootstrap      |
| `jetstaa_mna_services_registered` | `$container, $plugin` | After DI registration       |
| `jetstaa_mna_send_headers`        | `$post, $markdown`    | Add custom response headers |
| `jetstaa_mna_cache_invalidated`   | `$post_id`            | After single cache clear    |
| `jetstaa_mna_cache_flushed`       | —                     | After full cache flush      |
| `jetstaa_mna_configure_converter` | `$converter`          | Configure league converter  |

---

## Compatibility Matrix

### WordPress Ecosystem

| Component                | Compatible | Notes                                 |
| ------------------------ | ---------- | ------------------------------------- |
| Gutenberg (Block Editor) | ✓          | Full block parsing and processing     |
| Classic Editor           | ✓          | Direct HTML processing                |
| Custom Post Types        | ✓          | Configurable via `post_types` setting |
| WooCommerce Products     | ✓          | `woocommerce` setting toggle          |
| WordPress Multisite      | ✓          | Network-wide activation support       |
| WordPress REST API       | ✓          | Custom endpoint + field registration  |
| WP-CLI                   | ✓          | `wp markdown generate/clear/status`   |

### Caching Solutions

| Solution           | Impact | Notes                                    |
| ------------------ | ------ | ---------------------------------------- |
| WP Super Cache     | ✓      | `Vary: Accept` ensures separate cache    |
| W3 Total Cache     | ✓      | Respects Vary header for page cache      |
| LiteSpeed Cache    | ✓      | ESI-compatible, varies by Accept         |
| Redis Object Cache | ✓      | Used as Tier 1 cache driver              |
| Cloudflare         | ✓      | Respects Vary: Accept for caching        |
| Fastly             | ✓      | Advanced Vary header support             |
| Varnish            | ✓      | Requires `Vary: Accept` in VCL (default) |

### PHP Compatibility

| Version | Status             |
| ------- | ------------------ |
| PHP 8.1 | ✓ Minimum required |
| PHP 8.2 | ✓ Fully compatible |
| PHP 8.3 | ✓ Fully compatible |
| PHP 8.4 | ✓ Tested           |

---

## Performance Characteristics

### Conversion Cost

| Operation                    | Time (typical) | Notes                      |
| ---------------------------- | -------------- | -------------------------- |
| Accept header parsing        | ~0.01ms        | Regex + string operations  |
| Cache lookup (Object Cache)  | ~0.1ms         | Redis/Memcached round-trip |
| Cache lookup (Transients)    | ~1-5ms         | Single DB query            |
| Full conversion (small post) | ~5-15ms        | <1KB HTML                  |
| Full conversion (large post) | ~20-50ms       | 10KB+ HTML with blocks     |
| Post-processing              | ~1-3ms         | Regex cleanup              |

### Memory Usage

- Plugin load overhead: ~200KB
- Per-conversion peak: ~2-5MB (depending on post size)
- Cache footprint: Markdown is typically 40-60% smaller than source HTML

### Scalability

The multi-tier cache ensures:

- **With object cache**: Sub-millisecond responses after first conversion
- **Without object cache**: 1-5ms DB lookups for cached content
- **Cold cache**: Full conversion pipeline runs once, then cached for TTL duration

---

## Risk Analysis

### Technical Risks

| Risk                                    | Probability | Impact | Mitigation                                     |
| --------------------------------------- | ----------- | ------ | ---------------------------------------------- |
| league/html-to-markdown breaking change | Low         | High   | Pinned to ^5.1, tests cover all conversions    |
| Object cache not available              | Medium      | Low    | Automatic fallback to transients/file          |
| Large posts causing timeout             | Low         | Medium | Cache eliminates repeated conversion           |
| Malformed Accept headers                | Medium      | Low    | HeaderValidator rejects invalid input          |
| Rate limit bypass                       | Low         | Low    | Multiple layers (WP transients are atomic-ish) |

### Operational Risks

| Risk                                   | Probability | Impact | Mitigation                                          |
| -------------------------------------- | ----------- | ------ | --------------------------------------------------- |
| CDN caching HTML for Markdown requests | Medium      | High   | `Vary: Accept` header on ALL responses              |
| SEO duplicate content penalty          | Very Low    | High   | Same URL, standard content negotiation              |
| Plugin conflicts                       | Low         | Medium | Namespaced, no global functions, late hook priority |
| Memory exhaustion on very large sites  | Low         | Medium | Cache TTL prevents memory buildup                   |

---

## Future Roadmap

### v1.1.0 (Planned)

- [ ] Sitemap of Markdown-available pages (`/sitemap-markdown.xml`)
- [ ] `robots.txt` AI-specific directives integration
- [ ] Structured front-matter metadata (YAML header with title, date, author, categories)

### v1.2.0 (Planned)

- [ ] Table of Contents auto-generation for long posts
- [ ] Image handling options (inline base64, CDN URLs, placeholder)
- [ ] Custom Markdown templates per post type

### v2.0.0 (Planned)

- [ ] Server-Sent Events for real-time content updates
- [ ] Markdown webhooks for content change notifications
- [ ] Multi-format negotiation (text/plain, application/json+ld, text/markdown)
- [ ] AI agent session tracking and analytics dashboard

---

## Appendix: File Structure

```
markdown-negotiation-for-agents/
├── markdown-negotiation-for-agents.php  # Main plugin bootstrap
├── uninstall.php                        # Clean uninstall handler
├── composer.json                        # Dependencies & autoload
├── phpunit.xml.dist                     # PHPUnit configuration
├── phpcs.xml.dist                       # WPCS configuration
├── src/
│   ├── Container.php                    # DI Container
│   ├── Plugin.php                       # Main orchestrator (596 LOC)
│   ├── Contracts/                       # Interfaces
│   │   ├── CacheInterface.php
│   │   ├── ConverterInterface.php
│   │   ├── NegotiatorInterface.php
│   │   └── SanitizerInterface.php
│   ├── Converter/                       # HTML→Markdown pipeline
│   │   ├── ContentExtractor.php
│   │   ├── GutenbergProcessor.php
│   │   ├── MarkdownConverter.php
│   │   └── ShortcodeProcessor.php
│   ├── Http/                            # Request/Response handling
│   │   ├── AlternateEndpoint.php
│   │   ├── ContentNegotiator.php
│   │   ├── HeaderValidator.php
│   │   └── ResponseHandler.php
│   ├── Cache/                           # Multi-tier caching
│   │   ├── CacheManager.php
│   │   ├── FileCacheDriver.php
│   │   ├── ObjectCacheDriver.php
│   │   └── TransientDriver.php
│   ├── Admin/                           # Settings UI
│   │   ├── MetaBox.php
│   │   ├── SettingsPage.php
│   │   └── SettingsRegistrar.php
│   ├── Rest/                            # REST API integration
│   │   ├── FieldRegistrar.php
│   │   └── MarkdownController.php
│   ├── Cli/                             # WP-CLI commands
│   │   └── MarkdownCommand.php
│   ├── Security/                        # Security layer
│   │   ├── AccessControl.php
│   │   ├── RateLimiter.php
│   │   └── Sanitizer.php
│   └── Multisite/                       # Network support
│       └── NetworkHandler.php
├── tests/
│   ├── bootstrap.php
│   ├── TestCase.php
│   └── Unit/
│       ├── ContainerTest.php
│       ├── Cache/CacheManagerTest.php
│       ├── Converter/MarkdownConverterTest.php
│       ├── Http/ContentNegotiatorTest.php
│       ├── Http/HeaderValidatorTest.php
│       └── Security/SanitizerTest.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── docs/
│   ├── ARCHITECTURE.md                  # This document
│   └── DEPLOYMENT.md                    # Deployment guide
├── languages/
│   └── index.php
├── README.md                            # GitHub README
├── readme.txt                           # WordPress.org README
├── CHANGELOG.md                         # Version history
└── LICENSE                              # GPL-2.0-or-later
```
