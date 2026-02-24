# Skill: Write or Update Tests

## When to Use

Use this skill when writing new tests, updating existing tests, or improving test coverage.

## Test Architecture

```
tests/
├── bootstrap.php                     # Composer autoload + Brain Monkey setup
├── TestCase.php                      # Base class with setUp/tearDown for Brain Monkey
├── Unit/                             # Unit tests (isolated, no WP dependencies)
│   ├── ContainerTest.php
│   ├── Cache/CacheManagerTest.php
│   ├── Converter/MarkdownConverterTest.php
│   ├── Http/ContentNegotiatorTest.php
│   ├── Http/HeaderValidatorTest.php
│   └── Security/SanitizerTest.php
└── Integration/                      # Integration tests (multi-component flows)
    └── ContentNegotiationFlowTest.php
```

## Writing a Unit Test

### 1. Create Test File

```bash
# Mirror the src/ structure
# For src/Cache/NewDriver.php → tests/Unit/Cache/NewDriverTest.php
```

### 2. Test Class Skeleton

```php
<?php
/**
 * Tests for NewDriver.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Cache
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Cache;

use IlloDev\MarkdownNegotiation\Cache\NewDriver;
use IlloDev\MarkdownNegotiation\Tests\TestCase;
use Mockery;

final class NewDriverTest extends TestCase {

    private NewDriver $driver;

    protected function setUp(): void {
        parent::setUp();

        // Stub WordPress functions used in the class under test.
        \Brain\Monkey\Functions\stubs( array(
            'apply_filters' => function ( string $tag, ...$args ) {
                return $args[0];
            },
        ) );

        $this->driver = new NewDriver();
    }

    /**
     * @test
     */
    public function it_does_something(): void {
        $result = $this->driver->some_method();

        $this->assertSame( 'expected', $result );
    }
}
```

### 3. Key Conventions

- **Extend** `IlloDev\MarkdownNegotiation\Tests\TestCase` (never PHPUnit's directly).
- **Test naming**: Use `@test` annotation + descriptive snake_case method: `it_converts_headings`.
- **One assertion concept per test** (multiple `assert*` calls are fine if they test the same behavior).
- **Stub WordPress functions** with Brain Monkey before each test.
- **Mock dependencies** with Mockery — never mock `final` classes.
- **Clean up `$_SERVER`** in `tearDown()` or inline with `unset()`.

### 4. Mocking Patterns

```php
// Mock an interface
$cache = Mockery::mock( CacheInterface::class );
$cache->shouldReceive( 'get' )->with( 'key' )->andReturn( 'value' );

// Mock a non-final class
$extractor = Mockery::mock( ContentExtractor::class );
$extractor->shouldReceive( 'extract' )->andReturn( '<p>HTML</p>' );

// Stub WordPress functions
\Brain\Monkey\Functions\stubs( array(
    'get_option'    => array( 'enabled' => true ),
    'esc_html'      => function( $s ) { return htmlspecialchars( $s ); },
    'wp_parse_args' => function( $args, $defaults ) { return array_merge( $defaults, (array) $args ); },
));

// Expect a WordPress action was called
\Brain\Monkey\Functions\expect( 'do_action' )
    ->once()
    ->with( 'jetstaa_mna_cache_invalidated', 42 );
```

## Writing an Integration Test

Integration tests live in `tests/Integration/` and test multi-component flows:

- Build real instances (not mocks) for the components being integrated.
- Only mock external boundaries (WordPress functions, cache drivers).
- Test the full pipeline from input to output.

## Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration

# Specific test file
./vendor/bin/phpunit tests/Unit/Cache/CacheManagerTest.php

# Specific test method
./vendor/bin/phpunit --filter="it_invalidates_cache_on_post_save"

# With coverage (requires Xdebug or PCOV)
./vendor/bin/phpunit --coverage-text
```

## Coverage Requirements

- Every public method in `src/` should have at least one test.
- Critical paths (content negotiation, conversion, cache) must have comprehensive edge case coverage.
- Security classes must test both positive and negative cases.
