=== Markdown Negotiation for Agents ===
Contributors: illodev
Donate link: https://illodev.com
Tags: markdown, ai, agents, content-negotiation, llm, api
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve WordPress content as Markdown via HTTP content negotiation. Perfect for AI agents, LLMs, and developer tools.

== Description ==

**Markdown Negotiation for Agents** enables your WordPress site to serve content as clean Markdown when requested via standard HTTP content negotiation.

When a client sends a request with `Accept: text/markdown`, the plugin responds with the post content converted to well-formatted Markdown instead of the usual HTML. The URL stays the same — this is real content negotiation as defined in RFC 7231.

= Why? =

AI agents, LLMs, and developer tools often prefer Markdown over HTML. Instead of scraping and converting HTML (which is error-prone), they can simply request Markdown directly.

This is similar to [Cloudflare's Markdown for Agents](https://blog.cloudflare.com/markdown-for-agents), but implemented at the WordPress application level — no Cloudflare required.

= Features =

* **Content Negotiation** — `Accept: text/markdown` header support
* **Multiple Access Methods** — Query parameter, .md extension, REST API
* **Gutenberg Support** — Full block processing
* **WooCommerce** — Product details included
* **Caching** — Object cache, transients, or file-based
* **WP-CLI** — Generate, convert, and manage from command line
* **Security** — Rate limiting, sanitization, access control
* **Multisite** — Network activation support
* **SEO Safe** — Proper Vary headers, link alternates
* **Token Estimation** — X-Markdown-Tokens header for LLM planning

= How It Works =

1. Client sends `Accept: text/markdown`
2. Plugin intercepts before template loads
3. Post content is extracted and processed (Gutenberg blocks, shortcodes)
4. HTML is sanitized (scripts, nav, sidebars removed)
5. HTML is converted to Markdown via league/html-to-markdown
6. Response is sent with proper headers

= Requirements =

* PHP 8.1+
* WordPress 6.0+
* Composer (for dependencies)

== Installation ==

1. Download and extract to `/wp-content/plugins/markdown-negotiation-for-agents/`
2. Run `composer install --no-dev` in the plugin directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under Settings → Markdown Negotiation

= Via Composer =

`composer require illodev/markdown-negotiation-for-agents`

= Via WP-CLI =

`wp plugin install markdown-negotiation-for-agents --activate`

== Frequently Asked Questions ==

= Does this affect my normal website? =

No. Standard browser requests (Accept: text/html) continue to work exactly as before. Only requests specifically asking for text/markdown receive Markdown.

= Is it safe for SEO? =

Yes. The plugin uses a Vary: Accept header which tells search engines and CDNs that the content varies based on the request. Search engine crawlers send Accept: text/html and receive normal HTML.

= Which Markdown conversion library is used? =

league/html-to-markdown (5M+ downloads, actively maintained). The converter is abstracted behind an interface, so it can be swapped if needed.

= Does it work with page builders? =

It works with any content that produces HTML through WordPress's standard content pipeline. This includes Gutenberg, Classic Editor, and most page builders that render through the_content filter.

= Can I disable it for specific posts? =

Yes. Each post has a "Markdown Negotiation" meta box where you can disable Markdown for that specific post.

= Does it work with caching plugins? =

Yes. The Vary: Accept header ensures caching plugins and CDNs maintain separate cached copies for HTML and Markdown requests.

= What about password-protected posts? =

Password-protected posts are not served as Markdown unless the password has been provided (via cookie or X-WP-Post-Password header).

== Screenshots ==

1. Settings page — Configure content negotiation, endpoints, caching, and security.
2. Meta box — Per-post Markdown controls and preview link.
3. Terminal — WP-CLI commands for bulk generation and management.

== Changelog ==

= 1.0.0 =
* Initial release.
* HTTP content negotiation (Accept: text/markdown, text/x-markdown).
* Gutenberg block processing.
* WooCommerce product support.
* REST API endpoint and field.
* WP-CLI commands (generate, convert, cache, status).
* Multi-driver caching (object cache, transients, file).
* Rate limiting.
* Multisite support.
* Per-post disable control.
* Comprehensive sanitization.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install and configure from Settings → Markdown Negotiation.
