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

		wp_enqueue_style( 'dabar-admin', DABAR_URL . 'assets/css/admin.css', array(), DABAR_VERSION );
		wp_enqueue_script( 'dabar-admin', DABAR_URL . 'assets/js/admin.js', array(), DABAR_VERSION, true );
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
									foreach ( $messages as $message ) {
										$this->message_row( $message );
									}
									?>
								</div>
								<template id="dabar-message-template"><?php $this->message_row( '' ); ?></template>
								<p><button type="button" class="button" id="dabar-add-message"><?php esc_html_e( 'Add message', 'definitive-announcement-bar' ); ?></button></p>
								<p class="description"><?php esc_html_e( 'Multiple messages rotate automatically. Links, bold and italic text are allowed, and shortcodes work too.', 'definitive-announcement-bar' ); ?></p>
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
										'shortcode' => __( 'Manual: only where the shortcode is used', 'definitive-announcement-bar' ),
									)
								);
								?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: shortcode */
										esc_html__( 'For manual placement, add %s to a header template, a Bricks Shortcode element or any block.', 'definitive-announcement-bar' ),
										'<code>[definitive_announcement_bar]</code>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
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
							<th scope="row"><label for="dabar-close_color"><?php esc_html_e( 'Close button color', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->color( 'close_color' ); ?>
								<p class="description"><?php esc_html_e( 'Colors accept hex, rgb(), hsl() or CSS variables such as var(--primary).', 'definitive-announcement-bar' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-font_size"><?php esc_html_e( 'Font size', 'definitive-announcement-bar' ); ?></label></th>
							<td><?php $this->text( 'font_size', '14px' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="dabar-padding"><?php esc_html_e( 'Padding', 'definitive-announcement-bar' ); ?></label></th>
							<td>
								<?php $this->text( 'padding', '10px 24px' ); ?>
								<p class="description"><?php esc_html_e( 'Any CSS value, e.g. 10px 24px, 0.75rem or var(--space-s).', 'definitive-announcement-bar' ); ?></p>
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
							<th scope="row"><?php esc_html_e( 'Dismissible', 'definitive-announcement-bar' ); ?></th>
							<td><?php $this->checkbox( 'dismissible', __( 'Let visitors close the bar', 'definitive-announcement-bar' ) ); ?></td>
						</tr>
						<tr>
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
							<th scope="row"><?php esc_html_e( 'Hide on', 'definitive-announcement-bar' ); ?></th>
							<td>
								<?php $this->location_checkboxes(); ?>
								<p class="description"><?php esc_html_e( 'You can also hide the bar on a single post, page or product from its edit screen.', 'definitive-announcement-bar' ); ?></p>
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
					'<a href="https://leokawa.design" target="_blank" rel="noopener">studio leokawa</a>'
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
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->field_name( $key ) ),
			checked( (bool) DABAR_Settings::get( $key ), true, false ),
			esc_html( $label )
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
	 * @param string $key Setting key.
	 */
	private function color( $key ) {
		$value  = (string) DABAR_Settings::get( $key );
		$picker = '#000000';

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			$picker = $value;
		} elseif ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $value, $m ) ) {
			$picker = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}

		printf(
			'<span class="dabar-color"><input type="color" value="%1$s" tabindex="-1" aria-hidden="true" /><input type="text" id="%2$s" name="%3$s" value="%4$s" class="code dabar-css-value" /></span>',
			esc_attr( $picker ),
			esc_attr( 'dabar-' . $key ),
			esc_attr( $this->field_name( $key ) ),
			esc_attr( $value )
		);
	}

	/**
	 * Prints one message editor row.
	 *
	 * @param string $message Message.
	 */
	private function message_row( $message ) {
		printf(
			'<div class="dabar-message-row"><textarea name="%1$s[]" rows="2" class="large-text" aria-label="%2$s">%3$s</textarea><button type="button" class="button-link button-link-delete dabar-remove-message">%4$s</button></div>',
			esc_attr( $this->field_name( 'messages' ) ),
			esc_attr__( 'Message', 'definitive-announcement-bar' ),
			esc_textarea( $message ),
			esc_html__( 'Remove', 'definitive-announcement-bar' )
		);
	}

	/**
	 * Prints the "Hide on" location checkboxes.
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

		printf(
			'<label><input type="checkbox" name="dabar_hide" value="1" %1$s /> %2$s</label>',
			checked( (bool) get_post_meta( $post->ID, DABAR_Settings::META_HIDE, true ), true, false ),
			esc_html__( 'Hide the announcement bar here', 'definitive-announcement-bar' )
		);
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

		if ( ! empty( $_POST['dabar_hide'] ) ) {
			update_post_meta( $post_id, DABAR_Settings::META_HIDE, '1' );
		} else {
			delete_post_meta( $post_id, DABAR_Settings::META_HIDE );
		}
	}
}
