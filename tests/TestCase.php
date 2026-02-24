<?php
/**
 * Base test case with Brain Monkey setup.
 *
 * @package IlloDev\MarkdownNegotiation\Tests
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Class TestCase
 *
 * Base test case with WordPress function mocking.
 */
abstract class TestCase extends BaseTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
