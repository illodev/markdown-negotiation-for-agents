<?php
/**
 * Tests for ResponseHandler.
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Http
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Http;

use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\CacheInterface;
use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use IlloDev\MarkdownNegotiation\Http\ContentNegotiator;
use IlloDev\MarkdownNegotiation\Http\HeaderValidator;
use IlloDev\MarkdownNegotiation\Http\ResponseHandler;
use IlloDev\MarkdownNegotiation\Security\AccessControl;
use IlloDev\MarkdownNegotiation\Tests\TestCase;
use Mockery;
use RuntimeException;

/**
 * Exception thrown by the http_response_code mock to intercept exit.
 */
final class HttpExitException extends RuntimeException {
	public function __construct( public readonly int $http_code, string $message = '' ) {
		parent::__construct( $message ?: "HTTP exit with {$http_code}" );
	}
}

/**
 * Class ResponseHandlerTest
 */
final class ResponseHandlerTest extends TestCase {

	private ResponseHandler $handler;
	private HeaderValidator $header_validator;
	private ContentNegotiator $negotiator;

	protected function setUp(): void {
		parent::setUp();

		// Stub required WordPress functions.
		\Brain\Monkey\Functions\stubs( array(
			'add_action'    => null,
			'apply_filters' => function ( string $tag, ...$args ) {
				return $args[0];
			},
			'do_action'     => null,
			'esc_html'      => function ( string $text ): string {
				return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
			},
		) );

		$this->header_validator = new HeaderValidator();
		$this->negotiator       = new ContentNegotiator();

		$cache_driver = Mockery::mock( CacheInterface::class );
		$cache_driver->shouldReceive( 'get' )->andReturn( null )->byDefault();
		$cache_driver->shouldReceive( 'set' )->andReturn( true )->byDefault();

		$cache_manager  = new CacheManager( $cache_driver, 3600 );
		// AccessControl is final — instantiate directly since the test path never reaches can_access().
		$access_control = new AccessControl( array() );
		$converter      = Mockery::mock( ConverterInterface::class );

		$this->handler = new ResponseHandler(
			$converter,
			$cache_manager,
			$this->negotiator,
			$access_control,
			$this->header_validator,
			array( 'post_types' => array( 'post', 'page' ) ),
		);
	}

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_ACCEPT'] );
		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_does_nothing_for_non_singular_pages(): void {
		\Brain\Monkey\Functions\when( 'is_singular' )->justReturn( false );
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown,' . str_repeat( 'a', 1100 );

		// Should return without doing anything — no exception means no http_response_code call.
		$this->handler->handle_request();
		$this->assertTrue( true ); // Reached here without exception.
	}

	/**
	 * @test
	 *
	 * When the Accept header is oversized (> 1024 bytes), ResponseHandler must reply 400.
	 * We intercept http_response_code() via Brain Monkey to avoid process exit.
	 */
	public function it_sends_400_for_oversized_accept_header(): void {
		\Brain\Monkey\Functions\when( 'is_singular' )->justReturn( true );

		// Oversized Accept header — exceeds the 1024-byte limit.
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown,' . str_repeat( 'a', 1100 );

		// Mock http_response_code to throw so we can assert the code and avoid exit.
		\Brain\Monkey\Functions\when( 'http_response_code' )->alias(
			static function ( int $code ): void {
				throw new HttpExitException( $code );
			}
		);
		\Brain\Monkey\Functions\when( 'header' )->justReturn( null );

		try {
			$this->handler->handle_request();
			$this->fail( 'Expected HttpExitException to be thrown.' );
		} catch ( HttpExitException $e ) {
			$this->assertSame( 400, $e->http_code, 'Should return HTTP 400 for oversized Accept header.' );
		}
	}

	/**
	 * @test
	 *
	 * A valid Accept header requesting HTML must not trigger a 400 error and must not call
	 * http_response_code() at all (the handler returns early because no Markdown is requested).
	 */
	public function it_does_not_reject_valid_accept_header(): void {
		\Brain\Monkey\Functions\when( 'is_singular' )->justReturn( true );

		$_SERVER['HTTP_ACCEPT'] = 'text/html, application/xhtml+xml';

		// The negotiator will find no Markdown type and return false → handler exits without 400.
		$this->handler->handle_request();
		$this->assertTrue( true ); // Reached without any exception.
	}

	/**
	 * @test
	 *
	 * A request with a null-byte injected into the Accept header must return 400.
	 */
	public function it_sends_400_for_null_byte_in_accept_header(): void {
		\Brain\Monkey\Functions\when( 'is_singular' )->justReturn( true );

		$_SERVER['HTTP_ACCEPT'] = "text/markdown\0injection";

		\Brain\Monkey\Functions\when( 'http_response_code' )->alias(
			static function ( int $code ): void {
				throw new HttpExitException( $code );
			}
		);
		\Brain\Monkey\Functions\when( 'header' )->justReturn( null );

		try {
			$this->handler->handle_request();
			$this->fail( 'Expected HttpExitException to be thrown.' );
		} catch ( HttpExitException $e ) {
			$this->assertSame( 400, $e->http_code, 'Should return HTTP 400 for null-byte Accept header.' );
		}
	}
}
