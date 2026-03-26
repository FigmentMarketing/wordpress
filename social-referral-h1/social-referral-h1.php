<?php
/**
 * Plugin Name: Social Referral H1 Modifier
 * Description: Detects social media referrals and appends custom text to H1 headers on landing pages.
 * Version:     1.0.0
 * Author:      Figment Marketing
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: social-referral-h1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SRH1_VERSION', '1.0.0' );
define( 'SRH1_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRH1_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRH1_COOKIE_NAME', 'srh1_social_platform' );
define( 'SRH1_COOKIE_DURATION', 1800 ); // 30 minutes

require_once SRH1_PLUGIN_DIR . 'includes/class-social-referral-detector.php';
require_once SRH1_PLUGIN_DIR . 'includes/class-h1-modifier.php';
require_once SRH1_PLUGIN_DIR . 'includes/class-admin-settings.php';

/**
 * Main plugin class.
 */
class Social_Referral_H1 {

	/**
	 * @var Social_Referral_Detector
	 */
	private $detector;

	/**
	 * @var SRH1_Admin_Settings
	 */
	private $admin;

	/**
	 * @var string|null Detected platform for this request.
	 */
	private $detected_platform = null;

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

		add_action( 'template_redirect', array( $this, 'detect_and_store_platform' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_start_output_buffer' ), 999 );
		add_action( 'shutdown', array( $this, 'maybe_flush_output_buffer' ), 0 );
	}

	/**
	 * Detect social platform from referer/params and store in cookie.
	 */
	public function detect_and_store_platform() {
		$options = $this->admin->get_options();

		// Try to detect from current request first.
		$platform = $this->detector->detect( $options['platforms'] );

		if ( $platform ) {
			// Store in cookie so detection persists across redirects/page loads.
			setcookie(
				SRH1_COOKIE_NAME,
				$platform,
				time() + SRH1_COOKIE_DURATION,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
			$this->detected_platform = $platform;
		} elseif ( isset( $_COOKIE[ SRH1_COOKIE_NAME ] ) ) {
			// Fall back to previously stored cookie value.
			$cookie_value = sanitize_key( $_COOKIE[ SRH1_COOKIE_NAME ] );
			if ( array_key_exists( $cookie_value, $options['platforms'] ) ) {
				$this->detected_platform = $cookie_value;
			}
		}
	}

	/**
	 * Start output buffering if a platform was detected and current page qualifies.
	 */
	public function maybe_start_output_buffer() {
		if ( ! $this->detected_platform ) {
			return;
		}

		$options = $this->admin->get_options();

		if ( ! $this->is_target_page( $options ) ) {
			return;
		}

		$platform     = $this->detected_platform;
		$h1_text      = $options['h1_text'];
		$h1_position  = $options['h1_position'];
		$platform_label = isset( $options['platforms'][ $platform ]['label'] )
			? $options['platforms'][ $platform ]['label']
			: ucfirst( $platform );

		$addition = str_replace( '{platform}', esc_html( $platform_label ), $h1_text );

		ob_start( function( $buffer ) use ( $addition, $h1_position ) {
			return SRH1_Modifier::modify_h1( $buffer, $addition, $h1_position );
		} );
	}

	/**
	 * Flush the output buffer on shutdown.
	 */
	public function maybe_flush_output_buffer() {
		if ( ob_get_level() > 0 && $this->detected_platform ) {
			ob_end_flush();
		}
	}

	/**
	 * Determine whether the current page is a configured target.
	 *
	 * @param array $options Plugin options.
	 * @return bool
	 */
	private function is_target_page( array $options ) {
		$target = $options['target_pages'];

		if ( 'all' === $target ) {
			return true;
		}

		if ( 'landing' === $target ) {
			// Apply only to pages/posts (not archives, home, etc.).
			return is_singular();
		}

		if ( 'specific' === $target ) {
			$ids = array_map( 'intval', explode( ',', $options['target_page_ids'] ) );
			return is_singular() && in_array( get_queried_object_id(), $ids, true );
		}

		return false;
	}
}

new Social_Referral_H1();
