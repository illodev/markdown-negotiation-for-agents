# Deployment Guide

## Markdown Negotiation for Agents — WordPress Plugin

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Hosting-Specific Guides](#hosting-specific-guides)
5. [CDN Configuration](#cdn-configuration)
6. [Cache Configuration](#cache-configuration)
7. [Verification](#verification)
8. [Troubleshooting](#troubleshooting)
9. [Uninstallation](#uninstallation)

---

## Requirements

| Requirement  | Minimum | Recommended |
| ------------ | ------- | ----------- |
| PHP          | 8.1     | 8.3+        |
| WordPress    | 6.0     | 6.5+        |
| Memory Limit | 128MB   | 256MB       |
| Composer     | 2.x     | 2.7+        |

### PHP Extensions Required

- `mbstring` (string handling)
- `dom` (HTML parsing)
- `libxml` (XML parsing)
- `json` (settings storage)

---

## Installation

### Via Composer (Recommended)

```bash
cd /path/to/wp-content/plugins/
git clone https://github.com/illodev/markdown-negotiation-for-agents.git
cd markdown-negotiation-for-agents
composer install --no-dev --optimize-autoloader
```

### Via WordPress Admin

1. Download the latest release ZIP
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file
4. Click **Install Now**, then **Activate**

### Via WP-CLI

```bash
# If published to WordPress.org:
wp plugin install markdown-negotiation-for-agents --activate

# From GitHub:
wp plugin install https://github.com/illodev/markdown-negotiation-for-agents/archive/refs/heads/main.zip --activate
```

---

## Configuration

### Admin Settings

Navigate to **Settings → Markdown Negotiation** in WordPress admin.

#### General Settings

| Setting            | Default    | Description                              |
| ------------------ | ---------- | ---------------------------------------- |
| Enable Negotiation | ✓          | Master switch for content negotiation    |
| .md Endpoint       | ✗          | Allow `example.com/post-slug.md` access  |
| ?format=markdown   | ✓          | Allow `?format=markdown` query parameter |
| Post Types         | post, page | Which CPTs to serve as Markdown          |
| WooCommerce        | ✓          | Include product pages                    |

#### REST API Settings

| Setting       | Default | Description                                |
| ------------- | ------- | ------------------------------------------ |
| REST Markdown | ✓       | Add `markdown` field to REST API responses |
| Token Header  | ✓       | Include `X-Markdown-Tokens` header         |

#### Cache Settings

| Setting       | Default | Description                         |
| ------------- | ------- | ----------------------------------- |
| Cache Enabled | ✓       | Enable Markdown caching             |
| Cache Driver  | auto    | auto / object / transient / file    |
| Cache TTL     | 3600    | Seconds to cache converted Markdown |

#### Rate Limiting

| Setting      | Default | Description                 |
| ------------ | ------- | --------------------------- |
| Rate Limit   | ✗       | Enable per-IP rate limiting |
| Max Requests | 60      | Requests per window         |
| Window       | 60      | Window duration in seconds  |

### Programmatic Configuration

Override settings via filter:

```php
add_filter('jetstaa_mna_settings', function(array $settings): array {
    $settings['cache_ttl'] = 7200; // 2 hours
    $settings['post_types'] = ['post', 'page', 'portfolio'];
    return $settings;
});
```

### Per-Post Control

Each post has a **Markdown Negotiation** meta box in the editor where you can:

- Preview the Markdown output
- Disable Markdown for that specific post

---

## Hosting-Specific Guides

### Shared Hosting (cPanel/Plesk)

Most shared hosts work out of the box. Ensure:

1. PHP version is 8.1+ (check in cPanel → PHP Version)
2. `composer install --no-dev` has been run
3. `wp-content/uploads/md-cache/` directory is writable (only if using file cache)

### WP Engine

```bash
# WP Engine has object cache (Memcached) enabled by default
# The plugin will auto-detect and use it as Tier 1

# Verify object cache is available:
wp eval "echo wp_using_ext_object_cache() ? 'yes' : 'no';"
```

No special configuration needed. `Vary: Accept` is respected by WP Engine's CDN.

### Kinsta

Kinsta uses Redis as object cache. The plugin auto-detects this.

```bash
# Verify Redis is active:
wp eval "echo class_exists('Redis') ? 'yes' : 'no';"
```

### DigitalOcean / VPS

If running Nginx:

```nginx
# Ensure Vary header is passed through to the proxy cache
proxy_set_header Accept $http_accept;

# If using Nginx fastcgi_cache, vary on Accept:
fastcgi_cache_key "$scheme$request_method$host$request_uri$http_accept";
```

### Docker

```dockerfile
FROM wordpress:php8.3-apache

# Install required PHP extensions
RUN docker-php-ext-install mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install plugin
COPY . /var/www/html/wp-content/plugins/markdown-negotiation-for-agents/
WORKDIR /var/www/html/wp-content/plugins/markdown-negotiation-for-agents/
RUN composer install --no-dev --optimize-autoloader
```

---

## CDN Configuration

### Cloudflare

Cloudflare respects `Vary: Accept` by default when using the appropriate cache rules.

1. Go to **Caching → Configuration**
2. Ensure "Vary by Header" includes `Accept`
3. Create a **Cache Rule** if needed:
   - When: `(http.request.uri.path matches ".*")`
   - Then: Cache eligible, Vary by Accept header

**Important**: If using Cloudflare's own "Markdown for Agents" feature, disable either that or this plugin to avoid double conversion.

### Fastly

```vcl
sub vcl_recv {
    # Pass Accept header to origin
    set req.http.X-Original-Accept = req.http.Accept;
}

sub vcl_hash {
    # Include Accept header in cache key
    set req.hash += req.http.Accept;
}
```

### AWS CloudFront

1. Go to your Distribution → **Behaviors**
2. Edit the behavior for your WordPress origin
3. Under **Cache key and origin requests**:
   - Headers: Include `Accept`
4. This tells CloudFront to cache separate variants per Accept header

### Nginx (as reverse proxy)

```nginx
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=wp_cache:10m;

location / {
    proxy_pass http://wordpress;
    proxy_cache wp_cache;
    proxy_cache_key "$scheme$request_method$host$request_uri$http_accept";
    proxy_cache_valid 200 1h;
    add_header X-Cache-Status $upstream_cache_status;
}
```

---

## Cache Configuration

### Using Redis

Install and configure Redis Object Cache:

```bash
wp plugin install redis-cache --activate
wp redis enable
```

The plugin will automatically detect Redis and use it as the primary cache driver.

### Using Memcached

Install the Memcached object cache drop-in:

```bash
# Copy the object-cache.php drop-in to wp-content/
wp eval "echo wp_using_ext_object_cache() ? 'OK' : 'Not active';"
```

### File Cache (Fallback)

If neither Object Cache nor Transients are suitable:

1. Set cache driver to "File" in settings
2. Ensure `wp-content/uploads/md-cache/` exists and is writable
3. Add to `.htaccess` (Apache):
   ```apache
   <Directory "/path/to/wp-content/uploads/md-cache">
       Deny from all
   </Directory>
   ```

---

## Verification

### Quick Test

```bash
# Test with curl — should return Markdown
curl -H "Accept: text/markdown" https://your-site.com/sample-post/

# Test with query parameter
curl https://your-site.com/sample-post/?format=markdown

# Test .md endpoint (if enabled)
curl https://your-site.com/sample-post.md

# Verify headers
curl -I -H "Accept: text/markdown" https://your-site.com/sample-post/
```

**Expected headers:**

```
Content-Type: text/markdown; charset=utf-8
Vary: Accept
X-Markdown-Source: wordpress-plugin
X-Markdown-Plugin-Version: 1.0.0
X-Markdown-Tokens: 1847
```

### REST API Test

```bash
# Get Markdown via REST
curl https://your-site.com/wp-json/jetstaa-mna/v1/markdown/42

# Get Markdown field in standard endpoint
curl https://your-site.com/wp-json/wp/v2/posts/42?_fields=id,title,markdown
```

### WP-CLI Test

```bash
# Generate Markdown for a specific post
wp markdown generate --post_id=42

# Generate for all published posts
wp markdown generate --all

# Check cache status
wp markdown status

# Clear all cache
wp markdown clear --all
```

### Health Check

In WordPress admin, go to **Settings → Markdown Negotiation**. The status section shows:

- Plugin version
- Cache driver in use
- Object cache availability
- Number of cached posts
- Last cache flush time

---

## Troubleshooting

### Common Issues

#### "Composer autoloader not found"

```bash
cd wp-content/plugins/markdown-negotiation-for-agents/
composer install --no-dev --optimize-autoloader
```

#### Content negotiation not working

1. Check if another plugin is intercepting `template_redirect` at priority 0
2. Verify no caching plugin is serving cached HTML before the plugin runs
3. Check server logs for PHP errors

```bash
wp eval "var_dump(has_action('template_redirect'));"
```

#### Markdown is empty or incomplete

1. Check if the post uses blocks that are being skipped
2. Debug the conversion:

```bash
wp eval "
\$plugin = IlloDev\MarkdownNegotiation\Plugin::get_instance();
\$converter = \$plugin->container()->get(IlloDev\MarkdownNegotiation\Contracts\ConverterInterface::class);
\$post = get_post(42);
echo \$converter->convert('', ['post' => \$post]);
"
```

#### CDN serving wrong content type

1. Purge CDN cache
2. Verify `Vary: Accept` header is present on ALL responses:

```bash
curl -I https://your-site.com/any-page/
# Should show: Vary: Accept
```

3. Check CDN respects Vary header (some free CDN plans ignore it)

#### Rate limiting too aggressive

Adjust in settings, or programmatically:

```php
add_filter('jetstaa_mna_settings', function($s) {
    $s['rate_limit_requests'] = 120;
    $s['rate_limit_window'] = 60;
    return $s;
});
```

---

## Uninstallation

### Via WordPress Admin

1. **Deactivate** the plugin in Plugins page
2. **Delete** the plugin

On deletion, `uninstall.php` automatically:

- Removes `jetstaa_mna_settings` option
- Clears all `jetstaa_mna_cache_*` transients
- Removes `_jetstaa_mna_disabled` post meta from all posts
- Deletes `wp-content/uploads/md-cache/` directory

### Via WP-CLI

```bash
wp plugin deactivate markdown-negotiation-for-agents
wp plugin delete markdown-negotiation-for-agents
```

### Manual Cleanup

If the uninstaller didn't run:

```bash
wp option delete jetstaa_mna_settings
wp db query "DELETE FROM wp_options WHERE option_name LIKE '%jetstaa_mna%'"
wp db query "DELETE FROM wp_postmeta WHERE meta_key LIKE '%jetstaa_mna%'"
rm -rf wp-content/uploads/md-cache/
```
