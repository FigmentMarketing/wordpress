<?php
/**
 * Modifies the first H1 tag in the page HTML.
 *
 * Uses a regex on the buffered output so it works regardless of theme structure.
 *
 * @package Social_Referral_H1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRH1_Modifier {

	/**
	 * Insert $addition text before or after the content of the first <h1>.
	 *
	 * @param string $html      Full page HTML buffer.
	 * @param string $addition  Text to add (already escaped).
	 * @param string $position  'before' or 'after'.
	 * @return string Modified HTML.
	 */
	public static function modify_h1( $html, $addition, $position = 'after' ) {
		if ( empty( $addition ) ) {
			return $html;
		}

		// Match the first <h1 ...>...</h1>, including multi-line content.
		$pattern = '/(<h1(?:\s[^>]*)?>)(.*?)(<\/h1>)/is';

		$replaced = false;

		$modified = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $addition, $position, &$replaced ) {
				if ( $replaced ) {
					// Only modify the first H1.
					return $matches[0];
				}

				$replaced    = true;
				$open_tag    = $matches[1];
				$inner       = $matches[2];
				$close_tag   = $matches[3];

				$span = '<span class="srh1-addition">' . $addition . '</span>';

				if ( 'before' === $position ) {
					return $open_tag . $span . ' ' . $inner . $close_tag;
				}

				// Default: after.
				return $open_tag . $inner . ' ' . $span . $close_tag;
			},
			$html
		);

		return ( null !== $modified ) ? $modified : $html;
	}
}
