<?php
/**
 * Uninstall handler for Markdown Negotiation for Agents.
 *
 * Removes all plugin data when the plugin is deleted via WordPress admin.
 *
 * @package IlloDev\MarkdownNegotiation
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'jetstaa_mna_settings' );

// Delete all transients.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_jetstaa_mna_%',
		'_transient_timeout_jetstaa_mna_%'
	)
);

// Delete post meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		'_jetstaa_mna_%'
	)
);

// Clean up file cache directory if exists.
$jetstaa_mna_upload_dir = wp_upload_dir();
$jetstaa_mna_cache_dir  = $jetstaa_mna_upload_dir['basedir'] . '/jetstaa-mna-cache';

if ( is_dir( $jetstaa_mna_cache_dir ) ) {
	$jetstaa_mna_files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $jetstaa_mna_cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $jetstaa_mna_files as $jetstaa_mna_file ) {
		if ( $jetstaa_mna_file->isDir() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct cleanup during uninstall.
			rmdir( $jetstaa_mna_file->getRealPath() );
		} else {
			wp_delete_file( $jetstaa_mna_file->getRealPath() );
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Direct cleanup during uninstall.
	rmdir( $jetstaa_mna_cache_dir );
}

// Multisite: clean up across network.
if ( is_multisite() ) {
	$jetstaa_mna_sites = get_sites( array( 'number' => 0 ) );

	foreach ( $jetstaa_mna_sites as $jetstaa_mna_site ) {
		switch_to_blog( $jetstaa_mna_site->blog_id );

		delete_option( 'jetstaa_mna_settings' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_jetstaa_mna_%',
				'_transient_timeout_jetstaa_mna_%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				'_jetstaa_mna_%'
			)
		);

		restore_current_blog();
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
