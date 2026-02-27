<?php
/**
 * Shortcode Processor.
 *
 * Processes WordPress shortcodes into HTML before conversion.
 *
 * @package IlloDev\MarkdownNegotiation\Converter
 */

declare(strict_types=1);

namespace IlloDev\MarkdownNegotiation\Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ShortcodeProcessor
 *
 * Handles shortcode expansion and cleanup.
 */
final class ShortcodeProcessor {

	/**
	 * Shortcodes that should be stripped (not rendered).
	 *
	 * @var array<string>
	 */
	private const STRIP_SHORTCODES = array(
		'woocommerce_cart',
		'woocommerce_checkout',
		'woocommerce_my_account',
		'woocommerce_order_tracking',
	);

	/**
	 * Process shortcodes in content.
	 *
	 * Renders shortcodes to HTML, strips unwanted ones.
	 *
	 * @param string $content Content with shortcodes.
	 *
	 * @return string Content with shortcodes processed.
	 */
	public function process( string $content ): string {
		/**
		 * Filter the list of shortcodes to strip.
		 *
		 * @param array $shortcodes Shortcode tags to strip.
		 */
		$strip = apply_filters( 'jetstaa_mna_strip_shortcodes', self::STRIP_SHORTCODES );

		// Strip unwanted shortcodes.
		foreach ( $strip as $tag ) {
			$content = $this->strip_shortcode( $content, $tag );
		}

		// Process remaining shortcodes through WordPress.
		$content = do_shortcode( $content );

		return $content;
	}

	/**
	 * Strip a specific shortcode from content.
	 *
	 * @param string $content Content.
	 * @param string $tag     Shortcode tag.
	 *
	 * @return string Content without the shortcode.
	 */
	private function strip_shortcode( string $content, string $tag ): string {
		$pattern = sprintf(
			'/\[%1$s[^\]]*\](?:.*?\[\/%1$s\])?/s',
			preg_quote( $tag, '/' )
		);

		return (string) preg_replace( $pattern, '', $content );
	}
}
