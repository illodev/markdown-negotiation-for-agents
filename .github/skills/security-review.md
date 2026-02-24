# Skill: Security Review

## When to Use

Use this skill before any PR, after any change that handles user input, modifies output, interacts with the database, or changes access control logic.

## Automated Checks

```bash
# Run coding standards (includes security sniffs)
composer phpcs

# Run full test suite (includes security-related tests)
composer test
```

## Manual Checklist

### Input Handling

- [ ] All `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER` values are sanitized:
  - `sanitize_text_field()` for plain text
  - `absint()` for integers
  - `sanitize_email()` for emails
  - `esc_url_raw()` for URLs before storage
  - `wp_unslash()` before sanitization

- [ ] No direct use of `$_COOKIE` without validation.

- [ ] All REST API endpoints use `sanitize_callback` and `validate_callback` on arguments.

### Output Escaping

- [ ] All echoed content uses appropriate escaping:
  - `esc_html()` for HTML content
  - `esc_attr()` for HTML attributes
  - `esc_url()` for URLs in output
  - `wp_kses()` or `wp_kses_post()` for rich HTML
  - `esc_js()` for inline JavaScript values

- [ ] No raw `echo $variable` anywhere.

- [ ] Markdown output is the only exception (plain text format, escaped via pipeline).

### Database

- [ ] All SQL queries use `$wpdb->prepare()` for parameterized queries.
- [ ] No raw string concatenation in SQL.
- [ ] Table names use `$wpdb->prefix`.

### Authentication & Authorization

- [ ] Admin pages check `current_user_can( 'manage_options' )`.
- [ ] REST endpoints have proper `permission_callback`.
- [ ] Nonce verification on all form submissions:
  ```php
  check_admin_referer( 'jetstaa_mna_settings', 'jetstaa_mna_nonce' );
  ```
- [ ] Private/draft posts are not exposed via Markdown negotiation.

### File System

- [ ] No `file_get_contents()` on remote URLs — use `wp_remote_get()`.
- [ ] No `eval()`, `extract()`, or `compact()`.
- [ ] File cache directory has `.htaccess` / `index.php` protection.
- [ ] File paths are validated before use (no directory traversal).

### Headers

- [ ] Accept header validated by `HeaderValidator` (max length, format).
- [ ] No header injection possible (no user input in `header()` calls without sanitization).
- [ ] `Vary: Accept` present on all responses.

### Rate Limiting

- [ ] Rate limiter active for public-facing Markdown endpoints.
- [ ] Rate limit uses per-IP tracking.
- [ ] `Retry-After` header sent with 429 responses.

### Specific Patterns to Grep For

```bash
# Find potential security issues
grep -rn "echo \$" src/ --include="*.php"
grep -rn "\$_GET\|\$_POST\|\$_REQUEST" src/ --include="*.php"
grep -rn "file_get_contents\|file_put_contents" src/ --include="*.php"
grep -rn "eval\|extract\|compact" src/ --include="*.php"
grep -rn "query(" src/ --include="*.php"
grep -rn "unserialize" src/ --include="*.php"
```

### WordPress-Specific

- [ ] Plugin uses `ABSPATH` check at file tops to prevent direct access.
- [ ] `uninstall.php` cleans up all data properly.
- [ ] No hardcoded secrets or API keys.
- [ ] Text domain matches plugin slug in all `__()` / `esc_html__()` calls.
