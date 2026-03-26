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

	/**
	 * Default platform definitions.
	 *
	 * Keys:
	 *   label          - Human-readable name.
	 *   domains        - Referer domains that indicate this platform.
	 *   utm_aliases    - Additional UTM source strings (beyond the platform key itself).
	 *   click_id_param - URL parameter whose presence indicates this platform.
	 *   enabled        - Whether detection is active for this platform.
	 *
	 * @return array
	 */
	public static function default_platforms() {
		return array(
			'facebook'  => array(
				'label'          => 'Facebook',
				'domains'        => array( 'facebook.com', 'fb.com', 'l.facebook.com', 'm.facebook.com' ),
				'utm_aliases'    => array( 'fb' ),
				'click_id_param' => 'fbclid',
				'enabled'        => true,
			),
			'instagram' => array(
				'label'          => 'Instagram',
				'domains'        => array( 'instagram.com', 'l.instagram.com' ),
				'utm_aliases'    => array( 'ig' ),
				'click_id_param' => 'igshid',
				'enabled'        => true,
			),
			'twitter'   => array(
				'label'          => 'Twitter / X',
				'domains'        => array( 'twitter.com', 'x.com', 't.co' ),
				'utm_aliases'    => array( 'twitter', 'x' ),
				'click_id_param' => 'twclid',
				'enabled'        => true,
			),
			'linkedin'  => array(
				'label'          => 'LinkedIn',
				'domains'        => array( 'linkedin.com', 'lnkd.in' ),
				'utm_aliases'    => array(),
				'click_id_param' => '',
				'enabled'        => true,
			),
			'pinterest' => array(
				'label'          => 'Pinterest',
				'domains'        => array( 'pinterest.com', 'pin.it' ),
				'utm_aliases'    => array(),
				'click_id_param' => '',
				'enabled'        => true,
			),
			'tiktok'    => array(
				'label'          => 'TikTok',
				'domains'        => array( 'tiktok.com', 'vm.tiktok.com' ),
				'utm_aliases'    => array( 'tt' ),
				'click_id_param' => 'ttclid',
				'enabled'        => true,
			),
			'youtube'   => array(
				'label'          => 'YouTube',
				'domains'        => array( 'youtube.com', 'youtu.be', 'm.youtube.com' ),
				'utm_aliases'    => array( 'yt' ),
				'click_id_param' => '',
				'enabled'        => true,
			),
		);
	}

	/**
	 * Default options.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'platforms'       => self::default_platforms(),
			'h1_text'         => 'Welcome from {platform}!',
			'h1_position'     => 'after',
			'target_pages'    => 'landing',
			'target_page_ids' => '',
		);
	}

	/**
	 * Get merged options (stored options merged over defaults).
	 *
	 * @return array
	 */
	public function get_options() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::default_options();

		$options = wp_parse_args( $stored, $defaults );

		// Merge stored platforms over defaults so new platforms appear automatically.
		if ( ! empty( $stored['platforms'] ) && is_array( $stored['platforms'] ) ) {
			foreach ( $stored['platforms'] as $key => $config ) {
				if ( isset( $options['platforms'][ $key ] ) ) {
					$options['platforms'][ $key ] = wp_parse_args( $config, $defaults['platforms'][ $key ] ?? array() );
				}
			}
		}

		return $options;
	}

	/**
	 * Register hooks for the admin area.
	 */
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
			array( $this, 'sanitize_options' )
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

	/**
	 * Sanitize and validate submitted options.
	 *
	 * @param array $raw Raw POST data.
	 * @return array Sanitized options.
	 */
	public function sanitize_options( $raw ) {
		$defaults = self::default_options();
		$clean    = array();

		$clean['h1_text']         = sanitize_text_field( $raw['h1_text'] ?? $defaults['h1_text'] );
		$clean['h1_position']     = in_array( $raw['h1_position'] ?? '', array( 'before', 'after' ), true )
			? $raw['h1_position']
			: $defaults['h1_position'];
		$clean['target_pages']    = in_array( $raw['target_pages'] ?? '', array( 'all', 'landing', 'specific' ), true )
			? $raw['target_pages']
			: $defaults['target_pages'];
		$clean['target_page_ids'] = sanitize_text_field( $raw['target_page_ids'] ?? '' );

		// Platforms: preserve defaults for any not submitted.
		$clean['platforms'] = $defaults['platforms'];
		if ( ! empty( $raw['platforms'] ) && is_array( $raw['platforms'] ) ) {
			foreach ( $defaults['platforms'] as $key => $default_config ) {
				$submitted = $raw['platforms'][ $key ] ?? array();

				$clean['platforms'][ $key ] = array(
					'label'          => $default_config['label'],
					'domains'        => $default_config['domains'],
					'utm_aliases'    => $default_config['utm_aliases'],
					'click_id_param' => $default_config['click_id_param'],
					'enabled'        => isset( $submitted['enabled'] ) && '1' === $submitted['enabled'],
				);
			}
		}

		return $clean;
	}

	/**
	 * Render the settings page HTML.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options   = $this->get_options();
		$platforms = $options['platforms'];
		?>
		<div class="wrap srh1-wrap">
			<h1><?php esc_html_e( 'Social Referral H1 Modifier', 'social-referral-h1' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'srh1_settings_group' ); ?>

				<!-- H1 Text -->
				<h2><?php esc_html_e( 'H1 Addition', 'social-referral-h1' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="srh1_h1_text"><?php esc_html_e( 'Text to Add', 'social-referral-h1' ); ?></label>
						</th>
						<td>
							<input type="text" id="srh1_h1_text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[h1_text]"
								value="<?php echo esc_attr( $options['h1_text'] ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Use {platform} as a placeholder for the detected platform name. Example: "Welcome from {platform}!"', 'social-referral-h1' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Position', 'social-referral-h1' ); ?></th>
						<td>
							<label>
								<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[h1_position]"
									value="before" <?php checked( $options['h1_position'], 'before' ); ?> />
								<?php esc_html_e( 'Before H1 text', 'social-referral-h1' ); ?>
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[h1_position]"
									value="after" <?php checked( $options['h1_position'], 'after' ); ?> />
								<?php esc_html_e( 'After H1 text', 'social-referral-h1' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<!-- Target Pages -->
				<h2><?php esc_html_e( 'Target Pages', 'social-referral-h1' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Apply To', 'social-referral-h1' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[target_pages]"
										value="landing" <?php checked( $options['target_pages'], 'landing' ); ?> />
									<?php esc_html_e( 'Landing pages (all singular pages/posts)', 'social-referral-h1' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[target_pages]"
										value="all" <?php checked( $options['target_pages'], 'all' ); ?> />
									<?php esc_html_e( 'All pages', 'social-referral-h1' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[target_pages]"
										value="specific" <?php checked( $options['target_pages'], 'specific' ); ?> id="srh1_target_specific" />
									<?php esc_html_e( 'Specific pages (by ID)', 'social-referral-h1' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr id="srh1_specific_ids_row" <?php echo 'specific' !== $options['target_pages'] ? 'style="display:none"' : ''; ?>>
						<th scope="row">
							<label for="srh1_target_page_ids"><?php esc_html_e( 'Page IDs', 'social-referral-h1' ); ?></label>
						</th>
						<td>
							<input type="text" id="srh1_target_page_ids"
								name="<?php echo esc_attr( self::OPTION_KEY ); ?>[target_page_ids]"
								value="<?php echo esc_attr( $options['target_page_ids'] ); ?>"
								class="regular-text" placeholder="e.g. 42, 57, 103" />
							<p class="description"><?php esc_html_e( 'Comma-separated page/post IDs.', 'social-referral-h1' ); ?></p>
						</td>
					</tr>
				</table>

				<!-- Platforms -->
				<h2><?php esc_html_e( 'Social Platforms', 'social-referral-h1' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Enable or disable detection for each platform. Detection uses UTM source parameters, platform click IDs, and the HTTP referrer.', 'social-referral-h1' ); ?>
				</p>
				<table class="form-table srh1-platforms-table" role="presentation">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Platform', 'social-referral-h1' ); ?></th>
							<th><?php esc_html_e( 'Enabled', 'social-referral-h1' ); ?></th>
							<th><?php esc_html_e( 'Detects via', 'social-referral-h1' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $platforms as $key => $config ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $config['label'] ); ?></strong></td>
							<td>
								<label class="srh1-toggle">
									<input type="hidden"
										name="<?php echo esc_attr( self::OPTION_KEY . '[platforms][' . $key . '][enabled]' ); ?>"
										value="0" />
									<input type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY . '[platforms][' . $key . '][enabled]' ); ?>"
										value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
									<span class="srh1-toggle-slider"></span>
								</label>
							</td>
							<td class="srh1-detection-info">
								<?php
								$methods = array();
								$utm_terms = array_merge( array( $key ), $config['utm_aliases'] ?? array() );
								$methods[] = esc_html__( 'UTM source: ', 'social-referral-h1' ) . '<code>' . implode( ', ', array_map( 'esc_html', $utm_terms ) ) . '</code>';
								if ( ! empty( $config['click_id_param'] ) ) {
									$methods[] = esc_html__( 'Click ID param: ', 'social-referral-h1' ) . '<code>' . esc_html( $config['click_id_param'] ) . '</code>';
								}
								if ( ! empty( $config['domains'] ) ) {
									$methods[] = esc_html__( 'Referrer domains: ', 'social-referral-h1' ) . '<code>' . implode( ', ', array_map( 'esc_html', $config['domains'] ) ) . '</code>';
								}
								echo implode( '<br />', $methods ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped above
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>

		<script>
		(function() {
			var radios = document.querySelectorAll('input[name="<?php echo esc_js( self::OPTION_KEY ); ?>[target_pages]"]');
			var idsRow = document.getElementById('srh1_specific_ids_row');
			radios.forEach(function(radio) {
				radio.addEventListener('change', function() {
					idsRow.style.display = (this.value === 'specific') ? '' : 'none';
				});
			});
		})();
		</script>
		<?php
	}
}
