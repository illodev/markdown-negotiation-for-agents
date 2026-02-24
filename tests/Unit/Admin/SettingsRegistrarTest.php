<?php
/**
 * Tests for SettingsRegistrar::handle_endpoint_change().
 *
 * @package IlloDev\MarkdownNegotiation\Tests\Unit\Admin
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use IlloDev\MarkdownNegotiation\Admin\SettingsRegistrar;
use IlloDev\MarkdownNegotiation\Tests\TestCase;

/**
 * Class SettingsRegistrarTest
 */
final class SettingsRegistrarTest extends TestCase {

	/**
	 * Returns a SettingsRegistrar instance.
	 *
	 * @return SettingsRegistrar
	 */
	private function make(): SettingsRegistrar {
		return new SettingsRegistrar();
	}

	/**
	 * @test
	 */
	public function it_does_nothing_when_endpoint_md_did_not_change(): void {
		$registrar = $this->make();

		// flush_rewrite_rules and add_rewrite_rule must NOT be called.
		Functions\expect( 'flush_rewrite_rules' )->never();
		Functions\expect( 'add_rewrite_rule' )->never();

		$registrar->handle_endpoint_change(
			array( 'endpoint_md' => false ),
			array( 'endpoint_md' => false )
		);

		$registrar->handle_endpoint_change(
			array( 'endpoint_md' => true ),
			array( 'endpoint_md' => true )
		);
	}

	/**
	 * @test
	 */
	public function it_registers_md_rules_and_flushes_when_enabling(): void {
		$registrar = $this->make();

		Functions\expect( 'add_rewrite_rule' )->twice();
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'get_post_type_object' )->never();

		$registrar->handle_endpoint_change(
			array( 'endpoint_md' => false ),
			array( 'endpoint_md' => true, 'post_types' => array( 'post', 'page' ) )
		);
	}

	/**
	 * @test
	 */
	public function it_flushes_without_adding_rules_when_disabling(): void {
		$registrar = $this->make();

		Functions\expect( 'add_rewrite_rule' )->never();
		Functions\expect( 'flush_rewrite_rules' )->once();

		$registrar->handle_endpoint_change(
			array( 'endpoint_md' => true ),
			array( 'endpoint_md' => false )
		);
	}

	/**
	 * @test
	 */
	public function it_registers_custom_post_type_rules_when_enabling(): void {
		$registrar = $this->make();

		// base 2 rules + 1 CPT rule = 3 calls to add_rewrite_rule.
		Functions\expect( 'add_rewrite_rule' )->times( 3 );
		Functions\expect( 'flush_rewrite_rules' )->once();

		$cpt_obj          = new \stdClass();
		$cpt_obj->rewrite = array( 'slug' => 'books' );
		Functions\expect( 'get_post_type_object' )
			->once()
			->with( 'book' )
			->andReturn( $cpt_obj );

		$registrar->handle_endpoint_change(
			array( 'endpoint_md' => false ),
			array( 'endpoint_md' => true, 'post_types' => array( 'post', 'page', 'book' ) )
		);
	}

	/**
	 * @test
	 */
	public function it_skips_cpt_rules_when_post_type_has_no_rewrite(): void {
		$registrar = $this->make();

		// Only the base 2 rules.
		Functions\expect( 'add_rewrite_rule' )->twice();
		Functions\expect( 'flush_rewrite_rules' )->once();

		$cpt_obj          = new \stdClass();
		$cpt_obj->rewrite = false; // no rewrite.
		Functions\expect( 'get_post_type_object' )
			->once()
			->with( 'book' )
			->andReturn( $cpt_obj );

		$registrar->handle_endpoint_change(
			array( 'endpoint_md' => false ),
			array( 'endpoint_md' => true, 'post_types' => array( 'post', 'page', 'book' ) )
		);
	}
}
