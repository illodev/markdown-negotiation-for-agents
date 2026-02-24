# Skill: Add a WordPress Hook

## When to Use

Use this skill when adding a new `apply_filters()` or `do_action()` call to make plugin behavior extensible.

## Steps

### 1. Choose Hook Type

| Type              | Use When                                                   |
| ----------------- | ---------------------------------------------------------- |
| `apply_filters()` | External code needs to modify a value (data flows through) |
| `do_action()`     | External code needs to react to an event (side effects)    |

### 2. Name the Hook

- Always prefix with `jetstaa_mna_`.
- Use snake_case.
- Be descriptive but concise.
- Follow existing naming patterns:

```
jetstaa_mna_{noun}                    → jetstaa_mna_settings
jetstaa_mna_{verb}_{noun}             → jetstaa_mna_skip_block
jetstaa_mna_{noun}_{past_participle}  → jetstaa_mna_cache_invalidated
jetstaa_mna_{context}_{noun}          → jetstaa_mna_response_markdown
```

### 3. Add PHPDoc Block

**Every** hook **must** have a PHPDoc block directly above it:

```php
/**
 * Filter the extracted HTML content before Markdown conversion.
 *
 * Allows developers to modify the HTML that will be converted to Markdown.
 * Useful for stripping custom elements or adding content.
 *
 * @since 1.1.0
 *
 * @param string   $html    The extracted HTML content.
 * @param \WP_Post $post    The source WordPress post.
 * @param array    $options Conversion options.
 */
$html = apply_filters( 'jetstaa_mna_extracted_content', $html, $post, $options );
```

For actions:

```php
/**
 * Fires after a post's Markdown cache is invalidated.
 *
 * @since 1.0.0
 *
 * @param int $post_id The post ID whose cache was cleared.
 */
do_action( 'jetstaa_mna_cache_invalidated', $post_id );
```

### 4. Update Architecture Documentation

Add the hook to the appropriate table in `docs/ARCHITECTURE.md` under "Extensibility & Hooks":

**For filters:**

```markdown
| `jetstaa_mna_new_filter` | `$value, $context` | Purpose description |
```

**For actions:**

```markdown
| `jetstaa_mna_new_action` | `$param1, $param2` | Purpose description |
```

### 5. Update CHANGELOG.md

```markdown
## [Unreleased]

### Added

- Add `jetstaa_mna_new_hook` filter/action to allow [purpose]
```

### 6. Write Tests

Test that the hook is called with correct parameters:

```php
/**
 * @test
 */
public function it_applies_custom_filter(): void {
    \Brain\Monkey\Functions\expect( 'apply_filters' )
        ->once()
        ->with( 'jetstaa_mna_new_filter', 'input_value', Mockery::type( 'array' ) )
        ->andReturn( 'filtered_value' );

    $result = $this->service->method_that_uses_hook();

    $this->assertSame( 'filtered_value', $result );
}
```

### 7. Verify

```bash
composer test
composer phpcs
```

## Existing Hooks Reference

### Filters

| Hook                             | Location                                    |
| -------------------------------- | ------------------------------------------- |
| `jetstaa_mna_settings`           | `Plugin::load_settings()`                   |
| `jetstaa_mna_accept_header`      | `ContentNegotiator::get_accept_header()`    |
| `jetstaa_mna_skip_block`         | `GutenbergProcessor::render_block()`        |
| `jetstaa_mna_block_html`         | `GutenbergProcessor::render_block()`        |
| `jetstaa_mna_extracted_content`  | `ContentExtractor::extract()`               |
| `jetstaa_mna_converted_markdown` | `MarkdownConverter::convert()`              |
| `jetstaa_mna_response_markdown`  | `ResponseHandler::send_markdown_response()` |
| `jetstaa_mna_allowed_post_types` | `ResponseHandler::is_post_type_allowed()`   |
| `jetstaa_mna_cache_key`          | `CacheManager::build_key()`                 |
| `jetstaa_mna_process_shortcode`  | `ShortcodeProcessor`                        |
| `jetstaa_mna_sanitize_html`      | `Sanitizer`                                 |
| `jetstaa_mna_sanitize_markdown`  | `Sanitizer`                                 |

### Actions

| Hook                              | Location                                    |
| --------------------------------- | ------------------------------------------- |
| `jetstaa_mna_initialized`         | `Plugin::initialize()`                      |
| `jetstaa_mna_services_registered` | `Plugin::register_services()`               |
| `jetstaa_mna_send_headers`        | `ResponseHandler::send_headers()`           |
| `jetstaa_mna_cache_invalidated`   | `CacheManager::invalidate_post()`           |
| `jetstaa_mna_cache_flushed`       | `CacheManager::flush_all()`                 |
| `jetstaa_mna_configure_converter` | `MarkdownConverter::initialize_converter()` |
