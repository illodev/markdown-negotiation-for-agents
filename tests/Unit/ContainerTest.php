<?php
/**
 * Tests for Container.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit;

use IlloDev\MarkdownNegotiation\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * Class ContainerTest
 */
final class ContainerTest extends TestCase {

	private Container $container;

	protected function setUp(): void {
		parent::setUp();
		$this->container = new Container();
	}

	/**
	 * @test
	 */
	public function it_registers_and_resolves_service(): void {
		$this->container->bind( 'test', fn() => new stdClass() );

		$this->assertTrue( $this->container->has( 'test' ) );
		$this->assertInstanceOf( stdClass::class, $this->container->get( 'test' ) );
	}

	/**
	 * @test
	 */
	public function it_creates_new_instance_each_time_for_bind(): void {
		$this->container->bind( 'test', fn() => new stdClass() );

		$a = $this->container->get( 'test' );
		$b = $this->container->get( 'test' );

		$this->assertNotSame( $a, $b );
	}

	/**
	 * @test
	 */
	public function it_returns_same_instance_for_singleton(): void {
		$this->container->singleton( 'test', fn() => new stdClass() );

		$a = $this->container->get( 'test' );
		$b = $this->container->get( 'test' );

		$this->assertSame( $a, $b );
	}

	/**
	 * @test
	 */
	public function it_registers_existing_instance(): void {
		$obj = new stdClass();
		$obj->name = 'test';

		$this->container->instance( 'test', $obj );

		$resolved = $this->container->get( 'test' );
		$this->assertSame( $obj, $resolved );
		$this->assertSame( 'test', $resolved->name );
	}

	/**
	 * @test
	 */
	public function it_throws_for_unregistered_service(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Service "unknown" is not registered' );

		$this->container->get( 'unknown' );
	}

	/**
	 * @test
	 */
	public function it_checks_has_correctly(): void {
		$this->assertFalse( $this->container->has( 'test' ) );

		$this->container->bind( 'test', fn() => new stdClass() );

		$this->assertTrue( $this->container->has( 'test' ) );
	}

	/**
	 * @test
	 */
	public function it_forgets_services(): void {
		$this->container->bind( 'test', fn() => new stdClass() );
		$this->assertTrue( $this->container->has( 'test' ) );

		$this->container->forget( 'test' );
		$this->assertFalse( $this->container->has( 'test' ) );
	}

	/**
	 * @test
	 */
	public function it_passes_container_to_factory(): void {
		$this->container->singleton( 'dep', fn() => new stdClass() );
		$this->container->bind( 'service', function ( Container $c ) {
			$obj = new stdClass();
			$obj->dep = $c->get( 'dep' );
			return $obj;
		} );

		$service = $this->container->get( 'service' );
		$this->assertInstanceOf( stdClass::class, $service->dep );
	}
}
