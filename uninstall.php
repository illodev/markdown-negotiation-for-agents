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
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jetstaa_mna_%' OR option_name LIKE '_transient_timeout_jetstaa_mna_%'"
);

// Delete post meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_jetstaa_mna_%'"
);

// Clean up file cache directory if exists.
$upload_dir = wp_upload_dir();
$cache_dir  = $upload_dir['basedir'] . '/jetstaa-mna-cache';

if ( is_dir( $cache_dir ) ) {
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getRealPath() );
		} else {
			unlink( $file->getRealPath() );
		}
	}

	rmdir( $cache_dir );
}

// Multisite: clean up across network.
if ( is_multisite() ) {
	$sites = get_sites( array( 'number' => 0 ) );

	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );

		delete_option( 'jetstaa_mna_settings' );

		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_jetstaa_mna_%' OR option_name LIKE '_transient_timeout_jetstaa_mna_%'"
		);

		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_jetstaa_mna_%'"
		);

		restore_current_blog();
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
