<?php
/**
 * Settings page and per-post visibility meta box.
 *
 * @package DefinitiveAnnouncementBar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens.
 */
final class DABAR_Admin {

	const PAGE = 'definitive-announcement-bar';

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DABAR_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds Settings → Announcement Bar.
	 */
	public function add_menu() {
		$this->hook_suffix = add_options_page(
			__( 'Definitive Announcement Bar', 'definitive-announcement-bar' ),
			__( 'Announcement Bar', 'definitive-announcement-bar' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the settings option.
	 */
	public function register_settings() {
		register_setting(
			'dabar',
			DABAR_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'DABAR_Settings', 'sanitize' ),
				'default'           => DABAR_Settings::defaults(),
			)
		);
	}

	/**
	 * Loads admin assets on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		// The preview uses the frontend stylesheet, so it matches the bar on the site.
		wp_enqueue_style( 'dabar', DABAR_URL . 'assets/css/dabar.css', array(), DABAR_VERSION );
		wp_enqueue_style( 'dabar-admin', DABAR_URL . 'assets/css/admin.css', array( 'dabar' ), DABAR_VERSION );
		wp_enqueue_editor();
		wp_enqueue_script( 'dabar-admin', DABAR_URL . 'assets/js/admin.js', array(), DABAR_VERSION, true );

		// Let the preview use the site's fonts and preset variables.
		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			wp_add_inline_style( 'dabar-admin', wp_get_global_stylesheet( array( 'variables' ) ) );
		}
		if ( defined( 'BRICKS_DB_CUSTOM_FONT_FACE_RULES' ) ) {
			wp_add_inline_style( 'dabar-admin', wp_strip_all_tags( (string) get_option( BRICKS_DB_CUSTOM_FONT_FACE_RULES, '' ) ) );
		}
		if ( function_exists( 'wp_print_font_faces' ) ) {
			add_action( 'admin_head', 'wp_print_font_faces' );
		}
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );

		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'definitive-announcement-bar' ) ) );

		return $links;
	}

	/**
	 * Renders the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$messages = DABAR_Settings::get( 'messages' );
		if ( empty( $messages ) ) {
			$messages = array( '' );
		}
		?>
		<div class="wrap dabar-admin">
			<h1><?php esc_html_e( 'Definitive Announcement Bar', 'definitive-announcement-bar' ); ?></h1>

			<?php foreach ( $this->environment_notes() as $note ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $note ); ?></p></div>
			<?php endforeach; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'dabar' ); ?>

				<div class="postbox dabar-section dabar-preview-box">
					<div class="postbox-header"><h2><?php esc_html_e( 'Preview', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
						<?php
						printf(
							'<div class="dabar-preview" id="dabar-preview" data-defaults="%1$s" data-close-label="%2$s" data-empty="%3$s" data-note-on="%4$s" data-note-off="%5$s"></div>',
							esc_attr( wp_json_encode( DABAR_Settings::defaults() ) ),
							esc_attr__( 'Dismiss announcement', 'definitive-announcement-bar' ),
							esc_attr__( 'Your message appears here.', 'definitive-announcement-bar' ),
							esc_attr__( 'Changes show up here right away. Save them to update the site. Shortcodes only run on the site.', 'definitive-announcement-bar' ),
							esc_attr__( 'The bar is turned off, so visitors don’t see it. Turn on “Show the announcement bar” to publish it.', 'definitive-announcement-bar' )
						);
						?>
						<p class="description" id="dabar-preview-note"></p>
					</div>
				</div>

				<div class="postbox dabar-section">
					<div class="postbox-header"><h2><?php esc_html_e( 'Content', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', 'definitive-announcement-bar' ); ?></th>
							<td><?php $this->checkbox( 'enabled', __( 'Show the announcement bar', 'definitive-announcement-bar' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Messages', 'definitive-announcement-bar' ); ?></th>
							<td>
								<div class="dabar-messages" id="dabar-messages">
									<?php
									foreach ( array_values( $messages ) as $index => $message ) {
										$this->message_row( $message, 'dabar-message-' . ( $index + 1 ) );
									}
									?>
								</div>
								<template id="dabar-message-template"><?php $this->message_row( '' ); ?></template>
								<p><button type="button" class="button" id="dabar-add-message"><?php esc_html_e( 'Add message', 'definitive-announcement-bar' ); ?></button></p>
								<p class="description"><?php esc_html_e( 'Multiple messages rotate automatically. Use the toolbar for bold, italic and links. Shortcodes work too.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-interval"><?php esc_html_e( 'Rotation interval', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->number( 'interval', 1000, 60000, 500, __( 'ms', 'definitive-announcement-bar' ) ); ?>
								<p class="description"><?php esc_html_e( 'How long each message is shown.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
					</table>
					</div>
				</div>

				<div class="postbox dabar-section">
					<div class="postbox-header"><h2><?php esc_html_e( 'Button', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dabar-button_text"><?php esc_html_e( 'Button text', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php
								printf(
									'<input type="text" id="dabar-button_text" name="%1$s" value="%2$s" placeholder="%3$s" class="regular-text" />',
									esc_attr( $this->field_name( 'button_text' ) ),
									esc_attr( DABAR_Settings::get( 'button_text' ) ),
									esc_attr__( 'e.g. Shop now', 'definitive-announcement-bar' )
								);
								?>
								<p class="description"><?php esc_html_e( 'Optional. The button appears next to the messages once it has text and a link.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr class="dabar-requires-button">
							<th scope="row"><label for="dabar-button_url"><?php esc_html_e( 'Button link', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php
								printf(
									'<input type="text" id="dabar-button_url" name="%1$s" value="%2$s" placeholder="%3$s" inputmode="url" class="regular-text code" />',
									esc_attr( $this->field_name( 'button_url' ) ),
									esc_attr( DABAR_Settings::get( 'button_url' ) ),
									esc_attr( home_url( '/shop/' ) )
								);
								?>
								<p><?php $this->checkbox( 'button_new_tab', __( 'Open the link in a new tab', 'definitive-announcement-bar' ) ); ?></p>
							</td>
						</tr>
						<tr class="dabar-requires-button">
							<th scope="row"><label for="dabar-button_bg"><?php esc_html_e( 'Button color', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->color( 'button_bg' ); ?></td>
						</tr>
						<tr class="dabar-requires-button">
							<th scope="row"><label for="dabar-button_color"><?php esc_html_e( 'Button text color', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->color( 'button_color' ); ?></td>
						</tr>
					</table>
					</div>
				</div>

				<div class="postbox dabar-section">
					<div class="postbox-header"><h2><?php esc_html_e( 'Placement', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dabar-placement"><?php esc_html_e( 'Position', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php
								$this->select(
									'placement',
									array(
										'auto'      => __( 'Automatic: top of every page', 'definitive-announcement-bar' ),
										'bottom'    => __( 'Automatic: fixed to the bottom of the screen', 'definitive-announcement-bar' ),
										'shortcode' => __( 'Manual: only where the shortcode is used', 'definitive-announcement-bar' ),
									)
								);
								?>
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %s: shortcode */
											esc_html__( 'For manual placement, add %s to a header template, a Bricks Shortcode element or any block.', 'definitive-announcement-bar' ),
											'<code>[definitive_announcement_bar]</code>'
										),
										array( 'code' => array() )
									);
									?>
								</p>
							</td>
						</tr>
						<tr class="dabar-requires-top">
							<th scope="row"><?php esc_html_e( 'Sticky', 'definitive-announcement-bar' ); ?></th>
							<td><?php $this->checkbox( 'sticky', __( 'Keep the bar visible at the top while scrolling', 'definitive-announcement-bar' ) ); ?></td>
						</tr>
					</table>
					</div>
				</div>

				<div class="postbox dabar-section">
					<div class="postbox-header"><h2><?php esc_html_e( 'Appearance', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dabar-background_color"><?php esc_html_e( 'Background color', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->color( 'background_color' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-text_color"><?php esc_html_e( 'Text color', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->color( 'text_color' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-link_color"><?php esc_html_e( 'Link color', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->color( 'link_color', __( 'Text color', 'definitive-announcement-bar' ) ); ?>
								<p class="description"><?php esc_html_e( 'Leave the link color empty to use the text color. Colors accept hex, rgb(), hsl() or CSS variables such as var(--primary).', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-font_family"><?php esc_html_e( 'Font family', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->font_family(); ?>
								<p class="description"><?php esc_html_e( 'Leave empty to use your theme’s font. Pick an installed font from the list, or type any font-family such as "Inter", sans-serif or var(--font-body). Fonts you type in must be loaded by your site.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-font_size"><?php esc_html_e( 'Font size', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->text( 'font_size', '14px' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-height"><?php esc_html_e( 'Height', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->text( 'height', 'auto' ); ?>
								<p class="description"><?php esc_html_e( 'Minimum height, e.g. 40px or 3rem. Leave empty to size the bar from its text and padding. Longer messages can still make the bar taller.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-padding"><?php esc_html_e( 'Padding', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->text( 'padding', '10px 24px' ); ?>
								<p class="description"><?php esc_html_e( 'Any CSS value, e.g. 10px 24px, 0.75rem or var(--space-s).', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-align"><?php esc_html_e( 'Alignment', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php
								$this->select(
									'align',
									array(
										'center' => __( 'Centered', 'definitive-announcement-bar' ),
										'start'  => is_rtl() ? __( 'Right', 'definitive-announcement-bar' ) : __( 'Left', 'definitive-announcement-bar' ),
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Text style', 'definitive-announcement-bar' ); ?></th>
							<td><?php $this->checkbox( 'uppercase', __( 'Uppercase with letter spacing', 'definitive-announcement-bar' ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-fade_duration"><?php esc_html_e( 'Fade duration', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->number( 'fade_duration', 0, 5000, 50, __( 'ms', 'definitive-announcement-bar' ) ); ?>
								<p class="description"><?php esc_html_e( 'Cross-fade between messages. Visitors who prefer reduced motion see no animation.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
					</table>
					</div>
				</div>

				<div class="postbox dabar-section">
					<div class="postbox-header"><h2><?php esc_html_e( 'Close button', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show close button', 'definitive-announcement-bar' ); ?></th>
							<td><?php $this->checkbox( 'dismissible', __( 'Show an × button so visitors can close the bar', 'definitive-announcement-bar' ) ); ?></td>
						</tr>
						<tr class="dabar-requires-dismissible">
							<th scope="row"><label for="dabar-close_color"><?php esc_html_e( 'Button color', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->color( 'close_color' ); ?></td>
						</tr>
						<tr class="dabar-requires-dismissible">
							<th scope="row"><label for="dabar-dismiss_days"><?php esc_html_e( 'Keep closed for', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->number( 'dismiss_days', 0, 365, 1, __( 'days', 'definitive-announcement-bar' ) ); ?>
								<p class="description"><?php esc_html_e( 'Use 0 to show the bar again in the next browser session. The bar always reappears when you change the messages.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
					</table>
					</div>
				</div>

				<div class="postbox dabar-section">
					<div class="postbox-header"><h2><?php esc_html_e( 'Display rules', 'definitive-announcement-bar' ); ?></h2></div>
					<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="dabar-audience"><?php esc_html_e( 'Show to', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php
								$this->select(
									'audience',
									array(
										'all'        => __( 'All visitors', 'definitive-announcement-bar' ),
										'logged_in'  => __( 'Logged-in users only', 'definitive-announcement-bar' ),
										'logged_out' => __( 'Logged-out visitors only', 'definitive-announcement-bar' ),
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-devices"><?php esc_html_e( 'Devices', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php
								$this->select(
									'devices',
									array(
										'all'     => __( 'Desktop and mobile', 'definitive-announcement-bar' ),
										'desktop' => __( 'Desktop only', 'definitive-announcement-bar' ),
										'mobile'  => __( 'Mobile only', 'definitive-announcement-bar' ),
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'Mobile means screens narrower than 768 pixels.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-location_mode"><?php esc_html_e( 'Pages', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<p class="dabar-location-mode">
									<?php
									$this->select(
										'location_mode',
										array(
											'except'  => __( 'Show everywhere except the pages ticked below', 'definitive-announcement-bar' ),
											'only'    => __( 'Show only on the pages ticked below', 'definitive-announcement-bar' ),
										)
									);
									?>
								</p>
								<?php $this->location_checkboxes(); ?>
								<p class="description"><?php esc_html_e( 'Single posts, pages and products can override this from their edit screen: always show or never show the bar there.', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-schedule_start"><?php esc_html_e( 'Schedule', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<div class="dabar-schedule">
									<label><span><?php esc_html_e( 'Start', 'definitive-announcement-bar' ); ?></span> <?php $this->datetime( 'schedule_start' ); ?></label>
									<label><span><?php esc_html_e( 'End', 'definitive-announcement-bar' ); ?></span> <?php $this->datetime( 'schedule_end' ); ?></label>
								</div>
								<p class="description">
									<?php
									printf(
										/* translators: %s: time zone name */
										esc_html__( 'Optional. Leave empty to show the bar right away and without an end date. Times use the site time zone (%s).', 'definitive-announcement-bar' ),
										esc_html( wp_timezone_string() )
									);
									?>
								</p>
							</td>
						</tr>
					</table>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>

			<p class="dabar-admin__credit">
				<?php
				printf(
					/* translators: %s: author link */
					esc_html__( 'Definitive Announcement Bar is made by %s.', 'definitive-announcement-bar' ),
					sprintf( '<a href="%s" target="_blank" rel="noopener">studio leokawa</a>', esc_url( 'https://leokawa.design' ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Notes about detected integrations.
	 *
	 * @return string[]
	 */
	private function environment_notes() {
		$notes = array();

		if ( class_exists( 'WooCommerce' ) ) {
			$notes[] = __( 'WooCommerce detected: shop, product, cart, checkout and account pages are available in the display rules.', 'definitive-announcement-bar' );
		}

		return $notes;
	}

	/**
	 * Name attribute for a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function field_name( $key ) {
		return DABAR_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Prints a checkbox.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Label.
	 */
	private function checkbox( $key, $label ) {
		printf(
			'<label><input type="checkbox" id="%4$s" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->field_name( $key ) ),
			checked( (bool) DABAR_Settings::get( $key ), true, false ),
			esc_html( $label ),
			esc_attr( 'dabar-' . $key )
		);
	}

	/**
	 * Prints a number input.
	 *
	 * @param string $key  Setting key.
	 * @param int    $min  Minimum.
	 * @param int    $max  Maximum.
	 * @param int    $step Step.
	 * @param string $unit Unit shown after the field.
	 */
	private function number( $key, $min, $max, $step, $unit = '' ) {
		printf(
			'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="%6$d" class="small-text" />',
			esc_attr( 'dabar-' . $key ),
			esc_attr( $this->field_name( $key ) ),
			esc_attr( DABAR_Settings::get( $key ) ),
			(int) $min,
			(int) $max,
			(int) $step
		);

		if ( '' !== $unit ) {
			printf( ' <span class="dabar-unit">%s</span>', esc_html( $unit ) );
		}
	}

	/**
	 * Prints a text input.
	 *
	 * @param string $key         Setting key.
	 * @param string $placeholder Placeholder.
	 */
	private function text( $key, $placeholder ) {
		printf(
			'<input type="text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" class="code dabar-css-value" />',
			esc_attr( 'dabar-' . $key ),
			esc_attr( $this->field_name( $key ) ),
			esc_attr( DABAR_Settings::get( $key ) ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * Prints the font family field with a list of installed fonts to pick from.
	 */
	private function font_family() {
		printf(
			'<input type="text" id="dabar-font_family" name="%1$s" value="%2$s" list="dabar-font-families" placeholder="%3$s" autocomplete="off" class="regular-text" />',
			esc_attr( $this->field_name( 'font_family' ) ),
			esc_attr( DABAR_Settings::get( 'font_family' ) ),
			esc_attr__( 'Theme font', 'definitive-announcement-bar' )
		);

		echo '<datalist id="dabar-font-families">';
		foreach ( $this->installed_fonts() as $value => $label ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $value ), esc_html( $label ) );
		}
		echo '</datalist>';
	}

	/**
	 * Fonts available on the site: theme.json and Font Library fonts, fonts uploaded
	 * in Bricks, and a few system stacks. Keyed by CSS value.
	 *
	 * @return array
	 */
	private function installed_fonts() {
		$fonts = array();

		if ( function_exists( 'wp_get_global_settings' ) ) {
			foreach ( (array) wp_get_global_settings( array( 'typography', 'fontFamilies' ) ) as $origin ) {
				foreach ( (array) $origin as $font ) {
					if ( ! empty( $font['fontFamily'] ) && ! empty( $font['name'] ) ) {
						$fonts[ $font['fontFamily'] ] = $font['name'];
					}
				}
			}
		}

		if ( class_exists( '\Bricks\Custom_Fonts' ) && method_exists( '\Bricks\Custom_Fonts', 'get_custom_fonts' ) ) {
			foreach ( (array) \Bricks\Custom_Fonts::get_custom_fonts() as $font ) {
				if ( ! empty( $font['family'] ) ) {
					$fonts[ '"' . $font['family'] . '"' ] = $font['family'];
				}
			}
		}

		$fonts['system-ui, sans-serif']   = __( 'System font', 'definitive-announcement-bar' );
		$fonts['Georgia, serif']          = __( 'Serif', 'definitive-announcement-bar' );
		$fonts['ui-monospace, monospace'] = __( 'Monospace', 'definitive-announcement-bar' );

		foreach ( array_keys( $fonts ) as $value ) {
			if ( DABAR_Settings::sanitize_font_family( $value ) !== $value ) {
				unset( $fonts[ $value ] );
			}
		}

		return $fonts;
	}

	/**
	 * Prints a datetime-local input.
	 *
	 * @param string $key Setting key.
	 */
	private function datetime( $key ) {
		printf(
			'<input type="datetime-local" id="%1$s" name="%2$s" value="%3$s" />',
			esc_attr( 'dabar-' . $key ),
			esc_attr( $this->field_name( $key ) ),
			esc_attr( DABAR_Settings::get( $key ) )
		);
	}

	/**
	 * Prints a select.
	 *
	 * @param string $key     Setting key.
	 * @param array  $choices Value => label.
	 */
	private function select( $key, $choices ) {
		printf( '<select id="%1$s" name="%2$s">', esc_attr( 'dabar-' . $key ), esc_attr( $this->field_name( $key ) ) );

		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( DABAR_Settings::get( $key ), $value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Prints a color text field with a native color picker next to it.
	 *
	 * @param string $key         Setting key.
	 * @param string $placeholder Placeholder for an optional color.
	 */
	private function color( $key, $placeholder = '' ) {
		$value  = (string) DABAR_Settings::get( $key );
		$picker = '#000000';

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			$picker = $value;
		} elseif ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $m ) ) {
			$picker = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		printf(
			'<span class="dabar-color%5$s"><input type="color" value="%1$s" tabindex="-1" aria-hidden="true" /><input type="text" id="%2$s" name="%3$s" value="%4$s" placeholder="%6$s" class="code dabar-css-value" /></span>',
			esc_attr( $picker ),
			esc_attr( 'dabar-' . $key ),
			esc_attr( $this->field_name( $key ) ),
			esc_attr( $value ),
			'' === $value ? ' is-empty' : '',
			esc_attr( $placeholder )
		);
	}

	/**
	 * Prints one message editor row.
	 *
	 * @param string $message Message.
	 * @param string $id      Textarea ID, empty for the row template.
	 */
	private function message_row( $message, $id = '' ) {
		printf(
			'<div class="dabar-message-row"><textarea%5$s name="%1$s[]" rows="2" class="large-text" aria-label="%2$s">%3$s</textarea><button type="button" class="button-link button-link-delete dabar-remove-message">%4$s</button></div>',
			esc_attr( $this->field_name( 'messages' ) ),
			esc_attr__( 'Message', 'definitive-announcement-bar' ),
			esc_textarea( $message ),
			esc_html__( 'Remove', 'definitive-announcement-bar' ),
			$id ? ' id="' . esc_attr( $id ) . '"' : ''
		);
	}

	/**
	 * Prints the location checkboxes for the page rules.
	 */
	private function location_checkboxes() {
		$selected = (array) DABAR_Settings::get( 'hide_on' );
		$groups   = array( __( 'Site', 'definitive-announcement-bar' ) => DABAR_Settings::wp_locations() );

		if ( class_exists( 'WooCommerce' ) ) {
			$groups['WooCommerce'] = DABAR_Settings::wc_locations();
		}

		echo '<div class="dabar-locations-grid">';

		foreach ( $groups as $group => $choices ) {
			printf( '<fieldset class="dabar-locations"><legend>%s</legend>', esc_html( $group ) );

			foreach ( $choices as $value => $label ) {
				printf(
					'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
					esc_attr( $this->field_name( 'hide_on' ) ),
					esc_attr( $value ),
					checked( in_array( $value, $selected, true ), true, false ),
					esc_html( $label )
				);
			}

			echo '</fieldset>';
		}

		echo '</div>';
	}

	/**
	 * Adds the visibility box to all public post types.
	 */
	public function add_meta_box() {
		$post_types = get_post_types( array( 'public' => true ) );
		unset( $post_types['attachment'] );

		add_meta_box(
			'dabar-visibility',
			__( 'Announcement Bar', 'definitive-announcement-bar' ),
			array( $this, 'render_meta_box' ),
			array_values( $post_types ),
			'side',
			'low'
		);
	}

	/**
	 * Renders the visibility box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'dabar_save_meta', 'dabar_meta_nonce' );

		$current = DABAR_Settings::post_visibility( $post->ID );
		$choices = array(
			''     => __( 'Follow the display rules', 'definitive-announcement-bar' ),
			'show' => __( 'Always show the bar here', 'definitive-announcement-bar' ),
			'hide' => __( 'Never show the bar here', 'definitive-announcement-bar' ),
		);

		echo '<fieldset>';
		printf( '<legend class="screen-reader-text">%s</legend>', esc_html__( 'Announcement bar on this page', 'definitive-announcement-bar' ) );
		foreach ( $choices as $value => $label ) {
			printf(
				'<label style="display:block;margin:0 0 6px"><input type="radio" name="dabar_visibility" value="%1$s" %2$s /> %3$s</label>',
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		printf( '<p class="description">%s</p>', esc_html__( 'The schedule and the “Show to” setting still apply.', 'definitive-announcement-bar' ) );
	}

	/**
	 * Saves the visibility box.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['dabar_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dabar_meta_nonce'] ) ), 'dabar_save_meta' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$visibility = isset( $_POST['dabar_visibility'] ) ? sanitize_key( wp_unslash( $_POST['dabar_visibility'] ) ) : '';

		if ( in_array( $visibility, array( 'show', 'hide' ), true ) ) {
			update_post_meta( $post_id, DABAR_Settings::META_VISIBILITY, $visibility );
		} else {
			delete_post_meta( $post_id, DABAR_Settings::META_VISIBILITY );
		}

		// The older checkbox is replaced by the visibility choice.
		delete_post_meta( $post_id, DABAR_Settings::META_HIDE );
	}
}
