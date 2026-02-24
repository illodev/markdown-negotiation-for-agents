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
