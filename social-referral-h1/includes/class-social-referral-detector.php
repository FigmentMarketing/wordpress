<?php
/**
 * Detects social media and ad platform referrals from the current HTTP request.
 *
 * Detection priority (highest → lowest):
 *   1. Ad platforms  — Google Ads, Meta Ads (paid signals take precedence)
 *   2. Social platforms — Facebook, Instagram, Twitter/X, LinkedIn, Pinterest, TikTok, YouTube
 *
 * Within each group, signals are checked in this order:
 *   a. UTM source parameter  (?utm_source=…)
 *   b. Platform click ID parameters (?gclid=…, ?fbclid=…, etc.)
 *   c. HTTP Referer domain match
 *
 * @package Social_Referral_H1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Referral_Detector {

	/**
	 * UTM medium values that indicate paid/ad traffic.
	 */
	const PAID_MEDIUMS = array( 'cpc', 'ppc', 'paid', 'paid_search', 'paid_social', 'display', 'cpv', 'cpm', 'remarketing', 'retargeting' );

	/**
	 * Attempt to identify the referral source from the current request.
	 *
	 * @param array $options Full plugin options (social_platforms, ad_platforms, …).
	 * @return array|null  ['group' => 'ad'|'social', 'key' => string] or null.
	 */
	public function detect( array $options ) {
		$utm_source = isset( $_GET['utm_source'] ) ? strtolower( sanitize_key( $_GET['utm_source'] ) ) : '';
		$utm_medium = isset( $_GET['utm_medium'] ) ? strtolower( sanitize_key( $_GET['utm_medium'] ) ) : '';
		$is_paid    = in_array( $utm_medium, self::PAID_MEDIUMS, true );

		$referer      = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$referer_host = $referer ? strtolower( (string) wp_parse_url( $referer, PHP_URL_HOST ) ) : '';

		// --- 1. Ad platforms (checked before social to avoid misclassification) ---
		if ( ! empty( $options['ad_platforms'] ) ) {
			$result = $this->detect_ad_platform(
				$options['ad_platforms'],
				$utm_source,
				$utm_medium,
				$is_paid,
				$referer_host
			);
			if ( $result ) {
				return array( 'group' => 'ad', 'key' => $result );
			}
		}

		// --- 2. Social platforms ---
		if ( ! empty( $options['social_platforms'] ) ) {
			$result = $this->detect_social_platform(
				$options['social_platforms'],
				$utm_source,
				$referer_host
			);
			if ( $result ) {
				return array( 'group' => 'social', 'key' => $result );
			}
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Ad platform detection
	// -------------------------------------------------------------------------

	/**
	 * @param array  $ad_platforms Configured ad platform definitions.
	 * @param string $utm_source
	 * @param string $utm_medium
	 * @param bool   $is_paid
	 * @param string $referer_host
	 * @return string|null Platform key on match.
	 */
	private function detect_ad_platform( array $ad_platforms, $utm_source, $utm_medium, $is_paid, $referer_host ) {
		foreach ( $ad_platforms as $key => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}

			switch ( $key ) {
				case 'google_ads':
					if ( $this->is_google_ads( $utm_source, $is_paid ) ) {
						return $key;
					}
					break;

				case 'meta_ads':
					if ( $this->is_meta_ads( $utm_source, $is_paid, $referer_host ) ) {
						return $key;
					}
					break;
			}
		}

		return null;
	}

	/**
	 * Google Ads: gclid param, OR utm_source=google with a paid medium.
	 */
	private function is_google_ads( $utm_source, $is_paid ) {
		if ( isset( $_GET['gclid'] ) ) {
			return true;
		}

		if ( $is_paid && in_array( $utm_source, array( 'google', 'google_ads', 'googleads' ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Meta Ads: fbclid + paid medium, OR utm_source in Meta family + paid medium.
	 *
	 * Organic fbclid (no paid medium) is left for social Facebook detection.
	 */
	private function is_meta_ads( $utm_source, $is_paid, $referer_host ) {
		$meta_utm_sources = array( 'facebook', 'fb', 'instagram', 'ig', 'meta' );

		if ( $is_paid && in_array( $utm_source, $meta_utm_sources, true ) ) {
			return true;
		}

		// fbclid with a paid medium = paid Meta traffic.
		if ( $is_paid && isset( $_GET['fbclid'] ) ) {
			return true;
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Social platform detection
	// -------------------------------------------------------------------------

	/**
	 * @param array  $social_platforms Configured social platform definitions.
	 * @param string $utm_source
	 * @param string $referer_host
	 * @return string|null Platform key on match.
	 */
	private function detect_social_platform( array $social_platforms, $utm_source, $referer_host ) {
		// a. UTM source.
		if ( $utm_source ) {
			foreach ( $social_platforms as $key => $config ) {
				if ( empty( $config['enabled'] ) ) {
					continue;
				}
				if ( $this->utm_matches( $utm_source, $key, $config ) ) {
					return $key;
				}
			}
		}

		// b. Platform click ID params.
		foreach ( $social_platforms as $key => $config ) {
			if ( empty( $config['enabled'] ) || empty( $config['click_id_param'] ) ) {
				continue;
			}
			if ( isset( $_GET[ $config['click_id_param'] ] ) ) {
				return $key;
			}
		}

		// c. HTTP Referer.
		if ( $referer_host ) {
			foreach ( $social_platforms as $key => $config ) {
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
	 * Check whether a UTM source value matches a platform key or its aliases.
	 */
	private function utm_matches( $utm_source, $key, array $config ) {
		if ( $utm_source === $key ) {
			return true;
		}

		foreach ( $config['utm_aliases'] ?? array() as $alias ) {
			if ( $utm_source === strtolower( $alias ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a referer hostname matches a platform's domain list.
	 * Matches exact domain or any subdomain.
	 */
	private function referer_matches( $referer_host, array $config ) {
		foreach ( $config['domains'] ?? array() as $domain ) {
			$domain = strtolower( $domain );
			if ( $referer_host === $domain || str_ends_with( $referer_host, '.' . $domain ) ) {
				return true;
			}
		}

		return false;
	}
}
