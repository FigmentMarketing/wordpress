<?php
/**
 * Admin settings page for the Social Referral H1 Modifier plugin.
 *
 * @package Social_Referral_H1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRH1_Admin_Settings {

	const OPTION_KEY = 'srh1_options';

	// -------------------------------------------------------------------------
	// Defaults
	// -------------------------------------------------------------------------

	/**
	 * Social platform definitions.
	 * Detection config (domains, utm_aliases, click_id_param) is not user-editable
	 * and always comes from here, not from the DB.
	 */
	public static function social_platform_detection() {
		return array(
			'facebook'  => array(
				'domains'        => array( 'facebook.com', 'fb.com', 'l.facebook.com', 'm.facebook.com' ),
				'utm_aliases'    => array( 'fb' ),
				'click_id_param' => 'fbclid',
			),
			'instagram' => array(
				'domains'        => array( 'instagram.com', 'l.instagram.com' ),
				'utm_aliases'    => array( 'ig' ),
				'click_id_param' => 'igshid',
			),
			'twitter'   => array(
				'domains'        => array( 'twitter.com', 'x.com', 't.co' ),
				'utm_aliases'    => array( 'twitter', 'x' ),
				'click_id_param' => 'twclid',
			),
			'linkedin'  => array(
				'domains'        => array( 'linkedin.com', 'lnkd.in' ),
				'utm_aliases'    => array(),
				'click_id_param' => '',
			),
			'pinterest' => array(
				'domains'        => array( 'pinterest.com', 'pin.it' ),
				'utm_aliases'    => array(),
				'click_id_param' => '',
			),
			'tiktok'    => array(
				'domains'        => array( 'tiktok.com', 'vm.tiktok.com' ),
				'utm_aliases'    => array( 'tt' ),
				'click_id_param' => 'ttclid',
			),
			'youtube'   => array(
				'domains'        => array( 'youtube.com', 'youtu.be', 'm.youtube.com' ),
				'utm_aliases'    => array( 'yt' ),
				'click_id_param' => '',
			),
		);
	}

	public static function default_social_platforms() {
		return array(
			'facebook'  => array( 'label' => 'Facebook',     'h1_text' => 'Welcome from Facebook!',     'enabled' => true ),
			'instagram' => array( 'label' => 'Instagram',    'h1_text' => 'Welcome from Instagram!',    'enabled' => true ),
			'twitter'   => array( 'label' => 'Twitter / X',  'h1_text' => 'Welcome from Twitter / X!',  'enabled' => true ),
			'linkedin'  => array( 'label' => 'LinkedIn',     'h1_text' => 'Welcome from LinkedIn!',     'enabled' => true ),
			'pinterest' => array( 'label' => 'Pinterest',    'h1_text' => 'Welcome from Pinterest!',    'enabled' => true ),
			'tiktok'    => array( 'label' => 'TikTok',       'h1_text' => 'Welcome from TikTok!',       'enabled' => true ),
			'youtube'   => array( 'label' => 'YouTube',      'h1_text' => 'Welcome from YouTube!',      'enabled' => true ),
		);
	}

	public static function default_ad_platforms() {
		return array(
			'google_ads' => array( 'label' => 'Google Ads', 'h1_text' => 'Welcome, Google Ads visitor!', 'enabled' => true ),
			'meta_ads'   => array( 'label' => 'Meta Ads',   'h1_text' => 'Welcome, Meta Ads visitor!',   'enabled' => true ),
		);
	}

	public static function default_options() {
		return array(
			'social_platforms' => self::default_social_platforms(),
			'ad_platforms'     => self::default_ad_platforms(),
			'h1_position'      => 'after',
			'target_pages'     => 'landing',
			'target_page_ids'  => '',
		);
	}

	// -------------------------------------------------------------------------
	// Option access
	// -------------------------------------------------------------------------

	/**
	 * Return options merged with defaults, with detection config injected.
	 */
	public function get_options() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::default_options();
		$options  = wp_parse_args( $stored, $defaults );

		// Merge stored per-platform user settings over defaults.
		foreach ( array( 'social_platforms', 'ad_platforms' ) as $group ) {
			if ( ! empty( $stored[ $group ] ) && is_array( $stored[ $group ] ) ) {
				foreach ( $defaults[ $group ] as $key => $default_config ) {
					if ( isset( $stored[ $group ][ $key ] ) ) {
						$options[ $group ][ $key ] = wp_parse_args( $stored[ $group ][ $key ], $default_config );
					}
				}
			}
		}

		// Always inject immutable detection config for social platforms.
		$detection = self::social_platform_detection();
		foreach ( $options['social_platforms'] as $key => &$config ) {
			if ( isset( $detection[ $key ] ) ) {
				$config = array_merge( $config, $detection[ $key ] );
			}
		}
		unset( $config );

		return $options;
	}

	// -------------------------------------------------------------------------
	// Admin hooks
	// -------------------------------------------------------------------------

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_page() {
		add_options_page(
			__( 'Social Referral H1', 'social-referral-h1' ),
			__( 'Social Referral H1', 'social-referral-h1' ),
			'manage_options',
			'social-referral-h1',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'srh1_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'settings_page_social-referral-h1' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'srh1-admin',
			SRH1_PLUGIN_URL . 'assets/admin.css',
			array(),
			SRH1_VERSION
		);
	}

	// -------------------------------------------------------------------------
	// Sanitization
	// -------------------------------------------------------------------------

	public function sanitize_options( $raw ) {
		$defaults = self::default_options();
		$clean    = array();

		$clean['h1_position']    = in_array( $raw['h1_position'] ?? '', array( 'before', 'after' ), true )
			? $raw['h1_position']
			: $defaults['h1_position'];

		$clean['target_pages']   = in_array( $raw['target_pages'] ?? '', array( 'all', 'landing', 'specific' ), true )
			? $raw['target_pages']
			: $defaults['target_pages'];

		$clean['target_page_ids'] = sanitize_text_field( $raw['target_page_ids'] ?? '' );

		// Social platforms — only store label, h1_text, enabled (not detection config).
		$clean['social_platforms'] = $defaults['social_platforms'];
		if ( ! empty( $raw['social_platforms'] ) && is_array( $raw['social_platforms'] ) ) {
			foreach ( $defaults['social_platforms'] as $key => $default ) {
				$submitted = $raw['social_platforms'][ $key ] ?? array();
				$clean['social_platforms'][ $key ] = array(
					'label'   => $default['label'],
					'h1_text' => sanitize_text_field( $submitted['h1_text'] ?? $default['h1_text'] ),
					'enabled' => ! empty( $submitted['enabled'] ) && '1' === $submitted['enabled'],
				);
			}
		}

		// Ad platforms.
		$clean['ad_platforms'] = $defaults['ad_platforms'];
		if ( ! empty( $raw['ad_platforms'] ) && is_array( $raw['ad_platforms'] ) ) {
			foreach ( $defaults['ad_platforms'] as $key => $default ) {
				$submitted = $raw['ad_platforms'][ $key ] ?? array();
				$clean['ad_platforms'][ $key ] = array(
					'label'   => $default['label'],
					'h1_text' => sanitize_text_field( $submitted['h1_text'] ?? $default['h1_text'] ),
					'enabled' => ! empty( $submitted['enabled'] ) && '1' === $submitted['enabled'],
				);
			}
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Settings page render
	// -------------------------------------------------------------------------

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->get_options();
		$opt_key = self::OPTION_KEY;
		?>
		<div class="wrap srh1-wrap">
			<h1><?php esc_html_e( 'Social Referral H1 Modifier', 'social-referral-h1' ); ?></h1>
			<p class="srh1-intro">
				<?php esc_html_e( 'Automatically personalises the H1 heading on your landing pages based on where the visitor came from.', 'social-referral-h1' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'srh1_settings_group' ); ?>

				<?php $this->render_section_display( $options, $opt_key ); ?>
				<?php $this->render_section_target( $options, $opt_key ); ?>
				<?php $this->render_section_ad_platforms( $options, $opt_key ); ?>
				<?php $this->render_section_social_platforms( $options, $opt_key ); ?>

				<?php submit_button( __( 'Save Settings', 'social-referral-h1' ) ); ?>
			</form>
		</div>

		<script>
		(function () {
			var radios = document.querySelectorAll('input[name="<?php echo esc_js( $opt_key ); ?>[target_pages]"]');
			var idsRow = document.getElementById('srh1_specific_ids_row');
			radios.forEach(function (r) {
				r.addEventListener('change', function () {
					idsRow.style.display = this.value === 'specific' ? '' : 'none';
				});
			});
		}());
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Section renderers
	// -------------------------------------------------------------------------

	private function render_section_display( $options, $opt_key ) {
		?>
		<h2 class="srh1-section-heading"><?php esc_html_e( 'Display', 'social-referral-h1' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Position', 'social-referral-h1' ); ?></th>
				<td>
					<label>
						<input type="radio" name="<?php echo esc_attr( $opt_key ); ?>[h1_position]"
							value="before" <?php checked( $options['h1_position'], 'before' ); ?> />
						<?php esc_html_e( 'Before H1 text', 'social-referral-h1' ); ?>
					</label>
					&nbsp;&nbsp;
					<label>
						<input type="radio" name="<?php echo esc_attr( $opt_key ); ?>[h1_position]"
							value="after" <?php checked( $options['h1_position'], 'after' ); ?> />
						<?php esc_html_e( 'After H1 text', 'social-referral-h1' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Where the custom text is inserted relative to the existing H1 content.', 'social-referral-h1' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_section_target( $options, $opt_key ) {
		?>
		<h2 class="srh1-section-heading"><?php esc_html_e( 'Target Pages', 'social-referral-h1' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Apply To', 'social-referral-h1' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="<?php echo esc_attr( $opt_key ); ?>[target_pages]"
								value="landing" <?php checked( $options['target_pages'], 'landing' ); ?> />
							<?php esc_html_e( 'Landing pages (all singular pages / posts)', 'social-referral-h1' ); ?>
						</label><br />
						<label>
							<input type="radio" name="<?php echo esc_attr( $opt_key ); ?>[target_pages]"
								value="all" <?php checked( $options['target_pages'], 'all' ); ?> />
							<?php esc_html_e( 'All pages', 'social-referral-h1' ); ?>
						</label><br />
						<label>
							<input type="radio" name="<?php echo esc_attr( $opt_key ); ?>[target_pages]"
								value="specific" <?php checked( $options['target_pages'], 'specific' ); ?> />
							<?php esc_html_e( 'Specific pages (by ID)', 'social-referral-h1' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr id="srh1_specific_ids_row"<?php echo 'specific' !== $options['target_pages'] ? ' style="display:none"' : ''; ?>>
				<th scope="row">
					<label for="srh1_page_ids"><?php esc_html_e( 'Page IDs', 'social-referral-h1' ); ?></label>
				</th>
				<td>
					<input type="text" id="srh1_page_ids"
						name="<?php echo esc_attr( $opt_key ); ?>[target_page_ids]"
						value="<?php echo esc_attr( $options['target_page_ids'] ); ?>"
						class="regular-text" placeholder="e.g. 42, 57, 103" />
					<p class="description"><?php esc_html_e( 'Comma-separated page / post IDs.', 'social-referral-h1' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_section_ad_platforms( $options, $opt_key ) {
		$detection_info = array(
			'google_ads' => array(
				__( 'URL param:', 'social-referral-h1' ) . ' <code>gclid</code>',
				__( 'UTM source:', 'social-referral-h1' ) . ' <code>google</code> + paid medium',
			),
			'meta_ads'   => array(
				__( 'URL param:', 'social-referral-h1' ) . ' <code>fbclid</code> + paid medium',
				__( 'UTM source:', 'social-referral-h1' ) . ' <code>facebook, fb, instagram, ig, meta</code> + paid medium',
			),
		);
		?>
		<h2 class="srh1-section-heading"><?php esc_html_e( 'Ad Platforms', 'social-referral-h1' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Ad platform traffic is detected before social traffic. Paid signals (UTM medium = cpc, ppc, paid, paid_social, etc.) are required.', 'social-referral-h1' ); ?>
		</p>

		<table class="srh1-platform-table widefat striped">
			<thead>
				<tr>
					<th class="srh1-col-platform"><?php esc_html_e( 'Platform', 'social-referral-h1' ); ?></th>
					<th class="srh1-col-enabled"><?php esc_html_e( 'Enabled', 'social-referral-h1' ); ?></th>
					<th class="srh1-col-text"><?php esc_html_e( 'H1 Text', 'social-referral-h1' ); ?></th>
					<th class="srh1-col-detect"><?php esc_html_e( 'Detected via', 'social-referral-h1' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $options['ad_platforms'] as $key => $config ) : ?>
				<tr>
					<td class="srh1-col-platform"><strong><?php echo esc_html( $config['label'] ); ?></strong></td>
					<td class="srh1-col-enabled">
						<?php $this->render_toggle( $opt_key . '[ad_platforms][' . $key . '][enabled]', ! empty( $config['enabled'] ) ); ?>
					</td>
					<td class="srh1-col-text">
						<input type="text"
							name="<?php echo esc_attr( $opt_key . '[ad_platforms][' . $key . '][h1_text]' ); ?>"
							value="<?php echo esc_attr( $config['h1_text'] ); ?>"
							class="regular-text srh1-h1-input"
							placeholder="<?php echo esc_attr( self::default_ad_platforms()[ $key ]['h1_text'] ?? '' ); ?>" />
					</td>
					<td class="srh1-col-detect">
						<?php if ( isset( $detection_info[ $key ] ) ) : ?>
							<?php echo implode( '<br>', $detection_info[ $key ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static strings escaped above ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_section_social_platforms( $options, $opt_key ) {
		$detection = self::social_platform_detection();
		?>
		<h2 class="srh1-section-heading"><?php esc_html_e( 'Social Platforms', 'social-referral-h1' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Detected via UTM source parameter, platform click ID, or HTTP referrer domain.', 'social-referral-h1' ); ?>
		</p>

		<table class="srh1-platform-table widefat striped">
			<thead>
				<tr>
					<th class="srh1-col-platform"><?php esc_html_e( 'Platform', 'social-referral-h1' ); ?></th>
					<th class="srh1-col-enabled"><?php esc_html_e( 'Enabled', 'social-referral-h1' ); ?></th>
					<th class="srh1-col-text"><?php esc_html_e( 'H1 Text', 'social-referral-h1' ); ?></th>
					<th class="srh1-col-detect"><?php esc_html_e( 'Detected via', 'social-referral-h1' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $options['social_platforms'] as $key => $config ) :
				$det      = $detection[ $key ] ?? array();
				$utm_vals = array_filter( array_merge( array( $key ), $det['utm_aliases'] ?? array() ) );
				?>
				<tr>
					<td class="srh1-col-platform"><strong><?php echo esc_html( $config['label'] ); ?></strong></td>
					<td class="srh1-col-enabled">
						<?php $this->render_toggle( $opt_key . '[social_platforms][' . $key . '][enabled]', ! empty( $config['enabled'] ) ); ?>
					</td>
					<td class="srh1-col-text">
						<input type="text"
							name="<?php echo esc_attr( $opt_key . '[social_platforms][' . $key . '][h1_text]' ); ?>"
							value="<?php echo esc_attr( $config['h1_text'] ); ?>"
							class="regular-text srh1-h1-input"
							placeholder="<?php echo esc_attr( self::default_social_platforms()[ $key ]['h1_text'] ?? '' ); ?>" />
					</td>
					<td class="srh1-col-detect">
						<span class="srh1-detect-row">
							<?php esc_html_e( 'UTM source:', 'social-referral-h1' ); ?>
							<code><?php echo esc_html( implode( ', ', $utm_vals ) ); ?></code>
						</span>
						<?php if ( ! empty( $det['click_id_param'] ) ) : ?>
						<span class="srh1-detect-row">
							<?php esc_html_e( 'Click ID:', 'social-referral-h1' ); ?>
							<code><?php echo esc_html( $det['click_id_param'] ); ?></code>
						</span>
						<?php endif; ?>
						<?php if ( ! empty( $det['domains'] ) ) : ?>
						<span class="srh1-detect-row">
							<?php esc_html_e( 'Referrer:', 'social-referral-h1' ); ?>
							<code><?php echo esc_html( implode( ', ', $det['domains'] ) ); ?></code>
						</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a CSS toggle switch for a checkbox field.
	 * A hidden input ensures the value is submitted as 0 when unchecked.
	 */
	private function render_toggle( $field_name, $checked ) {
		?>
		<label class="srh1-toggle">
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="0" />
			<input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>"
				value="1" <?php checked( $checked ); ?> />
			<span class="srh1-toggle-slider"></span>
		</label>
		<?php
	}
}
