<?php
/**
 * PHPUnit bootstrap file for Markdown Negotiation for Agents.
 *
 * @package IlloDev\MarkdownNegotiation\Tests
 */

declare(strict_types=1);

// Load Composer autoloader.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $autoloader ) ) {
	echo "Composer autoloader not found. Run 'composer install' first.\n";
	exit( 1 );
}

require_once $autoloader;

// Define ABSPATH for direct file access protection checks in plugin files.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Define WordPress sanitization stubs used across src/ files.
// These are always available in WordPress runtime; we define them
// in the test bootstrap so they work without Brain Monkey stubs.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub for WordPress sanitize_text_field().
	 *
	 * @param string $str Input string.
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Stub for WordPress wp_unslash().
	 *
	 * @param mixed $value Input value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * Stub for WordPress wp_delete_file().
	 *
	 * @param string $file Path to the file to delete.
	 * @return void
	 */
	function wp_delete_file( string $file ): void {
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
	}
}
