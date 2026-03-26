<?php
/**
 * Detects the social media platform from the current HTTP request.
 *
 * Detection order (highest → lowest priority):
 *   1. UTM source parameter  (?utm_source=facebook)
 *   2. Platform-specific click ID parameters (fbclid, twclid, etc.)
 *   3. HTTP Referer header domain match
 *
 * @package Social_Referral_H1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Referral_Detector {

	/**
	 * Attempt to identify a social platform from the current request.
	 *
	 * @param array $platforms Configured platform definitions.
	 * @return string|null Platform key on match, null otherwise.
	 */
	public function detect( array $platforms ) {
		// 1. UTM source.
		$utm_source = isset( $_GET['utm_source'] ) ? sanitize_key( $_GET['utm_source'] ) : '';
		if ( $utm_source ) {
			foreach ( $platforms as $key => $config ) {
				if ( ! empty( $config['enabled'] ) && $this->utm_matches( $utm_source, $key, $config ) ) {
					return $key;
				}
			}
		}

		// 2. Platform click ID parameters.
		foreach ( $platforms as $key => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}
			if ( ! empty( $config['click_id_param'] ) && isset( $_GET[ $config['click_id_param'] ] ) ) {
				return $key;
			}
		}

		// 3. HTTP Referer.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';
		if ( $referer ) {
			$referer_host = strtolower( wp_parse_url( $referer, PHP_URL_HOST ) );
			foreach ( $platforms as $key => $config ) {
				if ( empty( $config['enabled'] ) ) {
					continue;
				}
				if ( $this->referer_matches( $referer_host, $config ) ) {
					return $key;
				}
			}
		}

		return null;
	}

	/**
	 * Check whether a UTM source value matches a platform.
	 *
	 * @param string $utm_source Sanitised UTM source value.
	 * @param string $key        Platform key (e.g. 'facebook').
	 * @param array  $config     Platform configuration.
	 * @return bool
	 */
	private function utm_matches( $utm_source, $key, array $config ) {
		// Direct key match.
		if ( $utm_source === $key ) {
			return true;
		}

		// Match against any utm_aliases defined for the platform.
		if ( ! empty( $config['utm_aliases'] ) ) {
			foreach ( $config['utm_aliases'] as $alias ) {
				if ( $utm_source === strtolower( $alias ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether a referer host matches any of a platform's domains.
	 *
	 * @param string $referer_host Lowercase hostname from referer.
	 * @param array  $config       Platform configuration.
	 * @return bool
	 */
	private function referer_matches( $referer_host, array $config ) {
		if ( empty( $config['domains'] ) || ! is_array( $config['domains'] ) ) {
			return false;
		}

		foreach ( $config['domains'] as $domain ) {
			$domain = strtolower( $domain );
			// Match exact domain or any subdomain.
			if ( $referer_host === $domain || substr( $referer_host, -( strlen( $domain ) + 1 ) ) === '.' . $domain ) {
				return true;
			}
		}

		return false;
	}
}
