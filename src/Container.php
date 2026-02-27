<?php
/**
 * Dependency Injection Container.
 *
 * Lightweight service container for managing plugin dependencies.
 * Follows PSR-11 concepts without requiring the interface package.
 *
 * @package IlloDev\MarkdownNegotiation
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
use RuntimeException;

/**
 * Class Container
 *
 * Simple dependency injection container with singleton support.
 */
final class Container {

	/**
	 * Registered service factories.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Services marked as singletons.
	 *
	 * @var array<string, bool>
	 */
	private array $singletons = array();

	/**
	 * Register a service factory.
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory closure that receives the container.
	 *
	 * @return self
	 */
	public function bind( string $id, callable $factory ): self {
		$this->factories[ $id ] = $factory;
		return $this;
	}

	/**
	 * Register a singleton service factory.
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory closure that receives the container.
	 *
	 * @return self
	 */
	public function singleton( string $id, callable $factory ): self {
		$this->factories[ $id ]  = $factory;
		$this->singletons[ $id ] = true;
		return $this;
	}

	/**
	 * Register an existing instance.
	 *
	 * @param string $id       Service identifier.
	 * @param object $instance The instance to register.
	 *
	 * @return self
	 */
	public function instance( string $id, object $instance ): self {
		$this->instances[ $id ]  = $instance;
		$this->singletons[ $id ] = true;
		return $this;
	}

	/**
	 * Resolve a service from the container.
	 *
	 * @param string $id Service identifier.
	 *
	 * @return object The resolved service instance.
	 *
	 * @throws RuntimeException If the service is not found.
	 */
	public function get( string $id ): object {
		// Return existing singleton instance.
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new RuntimeException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- $id is always a class FQCN, not user input.
				sprintf( 'Service "%s" is not registered in the container.', $id )
			);
		}

		$instance = ( $this->factories[ $id ] )( $this );

		// Cache if singleton.
		if ( isset( $this->singletons[ $id ] ) ) {
			$this->instances[ $id ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $id Service identifier.
	 *
	 * @return bool True if registered.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Remove a registered service.
	 *
	 * @param string $id Service identifier.
	 *
	 * @return self
	 */
	public function forget( string $id ): self {
		unset(
			$this->factories[ $id ],
			$this->instances[ $id ],
			$this->singletons[ $id ]
		);
		return $this;
	}
}
