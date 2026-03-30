<?php
/**
 * Plugin Name: Social Referral H1 Modifier
 * Description: Detects social media and ad platform referrals and adds custom text to H1 headers on landing pages.
 * Version:     2.0.0
 * Author:      Figment Marketing
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-referral-h1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SRH1_VERSION', '2.0.0' );
define( 'SRH1_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRH1_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRH1_COOKIE_NAME', 'srh1_referral' );
define( 'SRH1_COOKIE_DURATION', 1800 ); // 30 minutes

require_once SRH1_PLUGIN_DIR . 'includes/class-social-referral-detector.php';
require_once SRH1_PLUGIN_DIR . 'includes/class-h1-modifier.php';
require_once SRH1_PLUGIN_DIR . 'includes/class-admin-settings.php';

/**
 * Main plugin class.
 */
class Social_Referral_H1 {

	/** @var Social_Referral_Detector */
	private $detector;

	/** @var SRH1_Admin_Settings */
	private $admin;

	/**
	 * Detected referral for this request.
	 * Shape: [ 'group' => 'social'|'ad', 'key' => 'facebook'|'google_ads'|… ]
	 *
	 * @var array|null
	 */
	private $detected = null;

	public function __construct() {
		$this->detector = new Social_Referral_Detector();
		$this->admin    = new SRH1_Admin_Settings();

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( is_admin() ) {
			$this->admin->init();
			return;
		}

		add_action( 'template_redirect', array( $this, 'detect_and_store' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_start_output_buffer' ), 999 );
		add_action( 'shutdown', array( $this, 'maybe_flush_output_buffer' ), 0 );
	}

	/**
	 * Detect referral platform and persist in cookie.
	 */
	public function detect_and_store() {
		$options = $this->admin->get_options();

		// 1. Try to detect from current request.
		$detected = $this->detector->detect( $options );

		if ( $detected ) {
			setcookie(
				SRH1_COOKIE_NAME,
				$detected['group'] . ':' . $detected['key'],
				time() + SRH1_COOKIE_DURATION,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
			$this->detected = $detected;
			return;
		}

		// 2. Fall back to cookie from a previous request/redirect.
		if ( isset( $_COOKIE[ SRH1_COOKIE_NAME ] ) ) {
			$parts = explode( ':', sanitize_text_field( wp_unslash( $_COOKIE[ SRH1_COOKIE_NAME ] ) ), 2 );
			if ( 2 === count( $parts ) ) {
				list( $group, $key ) = $parts;
				if ( $this->is_valid_detection( $group, $key, $options ) ) {
					$this->detected = compact( 'group', 'key' );
				}
			}
		}
	}

	/**
	 * Start output buffering if a referral was detected on a target page.
	 */
	public function maybe_start_output_buffer() {
		if ( ! $this->detected ) {
			return;
		}

		$options = $this->admin->get_options();

		if ( ! $this->is_target_page( $options ) ) {
			return;
		}

		$h1_text = $this->resolve_h1_text( $this->detected, $options );
		if ( ! $h1_text ) {
			return;
		}

		$position = $options['h1_position'];

		ob_start( function( $buffer ) use ( $h1_text, $position ) {
			return SRH1_Modifier::modify_h1( $buffer, $h1_text, $position );
		} );
	}

	/**
	 * Flush our output buffer on shutdown.
	 */
	public function maybe_flush_output_buffer() {
		if ( ob_get_level() > 0 && $this->detected ) {
			ob_end_flush();
		}
	}

	/**
	 * Resolve the configured H1 text for the detected referral.
	 *
	 * @param array $detected ['group' => …, 'key' => …]
	 * @param array $options  Plugin options.
	 * @return string
	 */
	private function resolve_h1_text( array $detected, array $options ) {
		$group = $detected['group'];
		$key   = $detected['key'];

		if ( 'ad' === $group ) {
			return $options['ad_platforms'][ $key ]['h1_text'] ?? '';
		}

		return $options['social_platforms'][ $key ]['h1_text'] ?? '';
	}

	/**
	 * Check whether detected group/key exists in current options.
	 */
	private function is_valid_detection( $group, $key, array $options ) {
		if ( 'ad' === $group ) {
			return isset( $options['ad_platforms'][ $key ] ) && ! empty( $options['ad_platforms'][ $key ]['enabled'] );
		}
		if ( 'social' === $group ) {
			return isset( $options['social_platforms'][ $key ] ) && ! empty( $options['social_platforms'][ $key ]['enabled'] );
		}
		return false;
	}

	/**
	 * Determine whether the current page qualifies as a target.
	 */
	private function is_target_page( array $options ) {
		$target = $options['target_pages'];

		if ( 'all' === $target ) {
			return true;
		}

		if ( 'landing' === $target ) {
			return is_singular();
		}

		if ( 'specific' === $target && ! empty( $options['target_page_ids'] ) ) {
			$ids = array_filter( array_map( 'intval', explode( ',', $options['target_page_ids'] ) ) );
			return is_singular() && in_array( get_queried_object_id(), $ids, true );
		}

		return false;
	}
}

new Social_Referral_H1();
