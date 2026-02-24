<?php
/**
 * WP-CLI Markdown Command.
 *
 * Provides CLI commands for generating, managing, and testing
 * Markdown content negotiation.
 *
 * @package IlloDev\MarkdownNegotiation\Cli
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Cli;

use IlloDev\MarkdownNegotiation\Cache\CacheManager;
use IlloDev\MarkdownNegotiation\Contracts\ConverterInterface;
use IlloDev\MarkdownNegotiation\Converter\MarkdownConverter;
use WP_CLI;
use WP_CLI\Formatter;
use WP_Post;
use WP_Query;

/**
 * Manage Markdown content negotiation.
 *
 * ## EXAMPLES
 *
 *     # Generate Markdown for a specific post.
 *     $ wp markdown generate 42
 *
 *     # Generate Markdown for all posts.
 *     $ wp markdown generate --all
 *
 *     # Flush the Markdown cache.
 *     $ wp markdown cache flush
 *
 *     # Show plugin status.
 *     $ wp markdown status
 */
final class MarkdownCommand {

	/**
	 * Constructor.
	 *
	 * @param ConverterInterface   $converter     Markdown converter.
	 * @param CacheManager         $cache_manager Cache manager.
	 * @param array<string, mixed> $settings      Plugin settings.
	 */
	public function __construct(
		private readonly ConverterInterface $converter,
		private readonly CacheManager $cache_manager,
		private readonly array $settings,
	) {}

	/**
	 * Generate Markdown for posts.
	 *
	 * ## OPTIONS
	 *
	 * [<post-id>...]
	 * : One or more post IDs.
	 *
	 * [--all]
	 * : Generate for all published posts.
	 *
	 * [--post_type=<type>]
	 * : Post type to process. Default: post,page.
	 *
	 * [--output=<format>]
	 * : Output format. Options: file, stdout, cache. Default: cache.
	 *
	 * [--dir=<directory>]
	 * : Output directory for file output.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate and cache Markdown for post 42.
	 *     $ wp markdown generate 42
	 *     Success: Generated Markdown for post 42 (1,234 tokens).
	 *
	 *     # Generate for all posts and output to files.
	 *     $ wp markdown generate --all --output=file --dir=/tmp/markdown
	 *
	 *     # Generate for all pages.
	 *     $ wp markdown generate --all --post_type=page
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function generate( array $args, array $assoc_args ): void {
		$all       = WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );
		$output    = $assoc_args['output'] ?? 'cache';
		$directory = $assoc_args['dir'] ?? '';
		$post_type = $assoc_args['post_type'] ?? implode( ',', $this->settings['post_types'] ?? array( 'post', 'page' ) );

		if ( 'file' === $output && empty( $directory ) ) {
			WP_CLI::error( 'The --dir argument is required when using --output=file.' );
		}

		// Collect post IDs.
		$post_ids = array();

		if ( $all ) {
			$types = array_map( 'trim', explode( ',', $post_type ) );

			$query = new WP_Query( array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );

			$post_ids = $query->posts;
		} else {
			$post_ids = array_map( 'intval', $args );
		}

		if ( empty( $post_ids ) ) {
			WP_CLI::error( 'No posts found to process.' );
		}

		$count   = count( $post_ids );
		$success = 0;
		$errors  = 0;

		$progress = WP_CLI\Utils\make_progress_bar( "Generating Markdown ($count posts)", $count );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				WP_CLI::warning( "Post $post_id not found." );
				$errors++;
				$progress->tick();
				continue;
			}

			try {
				$markdown = $this->converter->convert( '', array( 'post' => $post ) );

				switch ( $output ) {
					case 'cache':
						$cache_key = $this->cache_manager->build_key( $post->ID );
						$this->cache_manager->set( $cache_key, $markdown );
						break;

					case 'file':
						$filename = sprintf( '%s/%s.md', rtrim( $directory, '/' ), $post->post_name );
						$dir      = dirname( $filename );
						if ( ! is_dir( $dir ) ) {
							wp_mkdir_p( $dir );
						}
						file_put_contents( $filename, $markdown );
						break;

					case 'stdout':
						WP_CLI::log( "--- Post $post_id: {$post->post_title} ---" );
						WP_CLI::log( $markdown );
						WP_CLI::log( '' );
						break;
				}

				$success++;
			} catch ( \Throwable $e ) {
				WP_CLI::warning( "Error processing post $post_id: {$e->getMessage()}" );
				$errors++;
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf(
			'Processed %d posts: %d successful, %d errors.',
			$count,
			$success,
			$errors
		) );
	}

	/**
	 * Manage the Markdown cache.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Cache action. Options: flush, status.
	 *
	 * ## EXAMPLES
	 *
	 *     # Flush all cached Markdown.
	 *     $ wp markdown cache flush
	 *     Success: Markdown cache flushed.
	 *
	 *     # Show cache status.
	 *     $ wp markdown cache status
	 *
	 * @param array $args Positional arguments.
	 *
	 * @return void
	 */
	public function cache( array $args ): void {
		$action = $args[0] ?? 'status';

		switch ( $action ) {
			case 'flush':
				$this->cache_manager->flush_all();
				WP_CLI::success( 'Markdown cache flushed.' );
				break;

			case 'status':
				$stats = $this->cache_manager->get_stats();
				WP_CLI::log( sprintf( 'Cache Driver: %s', $stats['driver'] ) );
				WP_CLI::log( sprintf( 'Available: %s', $stats['available'] ? 'Yes' : 'No' ) );
				break;

			default:
				WP_CLI::error( "Unknown cache action: $action. Use 'flush' or 'status'." );
		}
	}

	/**
	 * Show plugin status information.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp markdown status
	 *     Plugin Version: 1.0.0
	 *     Content Negotiation: Enabled
	 *     Converter: league/html-to-markdown
	 *     Cache Driver: object-cache
	 *     Post Types: post, page
	 *
	 * @return void
	 */
	public function status(): void {
		$cache_stats = $this->cache_manager->get_stats();

		$data = array(
			array( 'Key' => 'Plugin Version', 'Value' => JETSTAA_MNA_VERSION ),
			array( 'Key' => 'Content Negotiation', 'Value' => ( $this->settings['enabled'] ?? true ) ? 'Enabled' : 'Disabled' ),
			array( 'Key' => 'Converter', 'Value' => $this->converter->get_name() ),
			array( 'Key' => 'Converter Available', 'Value' => $this->converter->is_available() ? 'Yes' : 'No' ),
			array( 'Key' => 'Cache Driver', 'Value' => $cache_stats['driver'] ),
			array( 'Key' => 'Cache Available', 'Value' => $cache_stats['available'] ? 'Yes' : 'No' ),
			array( 'Key' => 'Post Types', 'Value' => implode( ', ', $this->settings['post_types'] ?? array( 'post', 'page' ) ) ),
			array( 'Key' => '.md Endpoint', 'Value' => ( $this->settings['endpoint_md'] ?? false ) ? 'Enabled' : 'Disabled' ),
			array( 'Key' => '?format=markdown', 'Value' => ( $this->settings['query_format'] ?? true ) ? 'Enabled' : 'Disabled' ),
			array( 'Key' => 'REST API', 'Value' => ( $this->settings['rest_markdown'] ?? true ) ? 'Enabled' : 'Disabled' ),
			array( 'Key' => 'Rate Limiting', 'Value' => ( $this->settings['rate_limit_enabled'] ?? false ) ? 'Enabled' : 'Disabled' ),
		);

		$formatter = new Formatter(
			$assoc_args ?? array(),
			array( 'Key', 'Value' ),
			'table'
		);

		$formatter->display_items( $data );
	}

	/**
	 * Convert a single post to Markdown and display.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : The post ID to convert.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp markdown convert 42
	 *
	 * @param array $args Positional arguments.
	 *
	 * @return void
	 */
	public function convert( array $args ): void {
		$post_id = (int) ( $args[0] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			WP_CLI::error( "Post $post_id not found." );
		}

		$markdown = $this->converter->convert( '', array( 'post' => $post ) );

		WP_CLI::log( $markdown );

		if ( $this->converter instanceof MarkdownConverter ) {
			$tokens = $this->converter->estimate_tokens( $markdown );
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( '---' ) );
			WP_CLI::log( sprintf( 'Characters: %d', mb_strlen( $markdown ) ) );
			WP_CLI::log( sprintf( 'Estimated tokens: %d', $tokens ) );
		}
	}
}
