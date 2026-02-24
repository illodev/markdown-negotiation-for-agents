<?php
/**
 * Tests for CacheManager.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Cache
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Cache;

use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;
use IlloDev\MarkdownNegotiation\Tests\TestCase;
use Mockery;

/**
 * Class CacheManagerTest
 *
 * Tests for cache orchestration and invalidation.
 */
final class CacheManagerTest extends TestCase {

	private CacheInterface $driver;
	private CacheManager $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->driver = Mockery::mock( CacheInterface::class );

		\Brain\Monkey\Functions\stubs( array(
			'apply_filters' => function ( string $tag, ...$args ) {
				return $args[0];
			},
			'do_action'     => null,
		) );

		$this->manager = new CacheManager( $this->driver, 3600 );
	}

	/**
	 * @test
	 */
	public function it_builds_cache_key_with_post_id(): void {
		$key = $this->manager->build_key( 42 );
		$this->assertSame( 'md_42', $key );
	}

	/**
	 * @test
	 */
	public function it_builds_cache_key_with_suffix(): void {
		$key = $this->manager->build_key( 42, 'rest' );
		$this->assertSame( 'md_42_rest', $key );
	}

	/**
	 * @test
	 */
	public function it_retrieves_cached_content(): void {
		$this->driver->shouldReceive( 'get' )
			->with( 'test_key' )
			->once()
			->andReturn( '# Hello World' );

		$result = $this->manager->get( 'test_key' );
		$this->assertSame( '# Hello World', $result );
	}

	/**
	 * @test
	 */
	public function it_returns_null_for_cache_miss(): void {
		$this->driver->shouldReceive( 'get' )
			->with( 'missing_key' )
			->once()
			->andReturn( null );

		$result = $this->manager->get( 'missing_key' );
		$this->assertNull( $result );
	}

	/**
	 * @test
	 */
	public function it_stores_content_with_default_ttl(): void {
		$this->driver->shouldReceive( 'set' )
			->with( 'test_key', '# Hello', 3600 )
			->once()
			->andReturn( true );

		$result = $this->manager->set( 'test_key', '# Hello' );
		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function it_stores_content_with_custom_ttl(): void {
		$this->driver->shouldReceive( 'set' )
			->with( 'test_key', '# Hello', 7200 )
			->once()
			->andReturn( true );

		$result = $this->manager->set( 'test_key', '# Hello', 7200 );
		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function it_invalidates_post_cache(): void {
		$this->driver->shouldReceive( 'delete' )
			->with( 'md_42' )
			->once()
			->andReturn( true );

		$this->manager->invalidate_post( 42 );
	}

	/**
	 * @test
	 */
	public function it_flushes_all_cache(): void {
		$this->driver->shouldReceive( 'flush' )
			->once()
			->andReturn( true );

		$result = $this->manager->flush_all();
		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function it_returns_cache_stats(): void {
		$this->driver->shouldReceive( 'get_driver_name' )->andReturn( 'transient' );
		$this->driver->shouldReceive( 'is_available' )->andReturn( true );

		$stats = $this->manager->get_stats();

		$this->assertSame( 'transient', $stats['driver'] );
		$this->assertTrue( $stats['available'] );
	}

	/**
	 * @test
	 */
	public function it_returns_driver(): void {
		$this->assertSame( $this->driver, $this->manager->get_driver() );
	}
}
