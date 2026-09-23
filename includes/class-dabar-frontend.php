<?php
/**
 * Frontend output: placement, display rules and assets.
 *
 * @package DefinitiveAnnouncementBar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the announcement bar.
 */
final class DABAR_Frontend {

	/**
	 * Whether the bar was already printed on this request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Cached result of the display rules.
	 *
	 * @var bool|null
	 */
	private $display = null;

	/**
	 * Registers hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Bricks renders its own header, so place the bar right before it.
		// Every other theme gets the standard wp_body_open hook.
		if ( self::is_bricks_theme() ) {
			add_action( 'bricks_before_header', array( $this, 'print_bar' ), 20 );
		} else {
			add_action( 'wp_body_open', array( $this, 'print_bar' ), 20 );
		}

		// Themes without wp_body_open: print in the footer and move it to the top with JS.
		add_action( 'wp_footer', array( $this, 'print_fallback' ), 5 );

		add_shortcode( 'definitive_announcement_bar', array( $this, 'shortcode' ) );
	}

	/**
	 * Whether Bricks (or a Bricks child theme) is the active theme.
	 *
	 * @return bool
	 */
	public static function is_bricks_theme() {
		return 'bricks' === get_template();
	}

	/**
	 * Whether the current request is the Bricks builder.
	 *
	 * @return bool
	 */
	private static function is_bricks_builder() {
		return ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() )
			|| ( function_exists( 'bricks_is_builder_call' ) && bricks_is_builder_call() );
	}

	/**
	 * Whether the bar should appear on the current request.
	 *
	 * @return bool
	 */
	public function should_display() {
		if ( null === $this->display ) {
			/**
			 * Filters whether the announcement bar is displayed on the current request.
			 *
			 * @param bool $display Result of the configured display rules.
			 */
			$this->display = (bool) apply_filters( 'dabar_should_display', $this->evaluate_rules() );
		}

		return $this->display;
	}

	/**
	 * Applies the display rules from the settings page.
	 *
	 * @return bool
	 */
	private function evaluate_rules() {
		$settings = DABAR_Settings::get();

		if ( ! $settings['enabled'] || empty( $settings['messages'] ) ) {
			return false;
		}

		if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || self::is_bricks_builder() ) {
			return false;
		}

		$end = DABAR_Settings::timestamp( $settings['schedule_end'] );
		if ( $end && $end <= time() ) {
			return false;
		}

		if ( ( 'logged_in' === $settings['audience'] && ! is_user_logged_in() )
			|| ( 'logged_out' === $settings['audience'] && is_user_logged_in() ) ) {
			return false;
		}

		// A choice made on the post's edit screen wins over the page rules.
		$post_id    = $this->current_post_id();
		$visibility = $post_id ? DABAR_Settings::post_visibility( $post_id ) : '';
		if ( '' !== $visibility ) {
			return 'show' === $visibility;
		}

		$matches = false;
		foreach ( (array) $settings['hide_on'] as $location ) {
			if ( $this->is_location( $location ) ) {
				$matches = true;
				break;
			}
		}

		return 'only' === $settings['location_mode'] ? $matches : ! $matches;
	}

	/**
	 * ID of the post or page being viewed, including the WooCommerce shop page.
	 *
	 * @return int
	 */
	private function current_post_id() {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return (int) wc_get_page_id( 'shop' );
		}

		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		if ( is_home() && ! is_front_page() ) {
			return (int) get_option( 'page_for_posts' );
		}

		return 0;
	}

	/**
	 * Checks whether the current request matches a location key.
	 *
	 * @param string $location Location key.
	 * @return bool
	 */
	private function is_location( $location ) {
		$wc         = function_exists( 'is_woocommerce' );
		$wc_archive = $wc && ( is_shop() || is_product_taxonomy() );
		$wc_page    = $wc && ( is_cart() || is_checkout() || is_account_page() );

		switch ( $location ) {
			case 'front_page':
				return is_front_page();
			case 'blog':
				return ( is_home() && ! is_front_page() ) || ( is_archive() && ! $wc_archive );
			case 'posts':
				return is_singular( 'post' );
			case 'pages':
				return is_page() && ! $wc_page;
			case 'search_404':
				return is_search() || is_404();
			case 'wc_shop':
				return $wc_archive;
			case 'wc_product':
				return $wc && is_product();
			case 'wc_cart':
				return $wc && is_cart();
			case 'wc_checkout':
				return $wc && is_checkout();
			case 'wc_account':
				return $wc && is_account_page();
		}

		return false;
	}

	/**
	 * Enqueues the stylesheet and script when the bar is displayed.
	 */
	public function enqueue_assets() {
		if ( ! $this->should_display() ) {
			return;
		}

		$settings = DABAR_Settings::get();

		wp_enqueue_style( 'dabar', DABAR_URL . 'assets/css/dabar.css', array(), DABAR_VERSION );
		wp_add_inline_style( 'dabar', $this->inline_css( $settings ) );

		wp_enqueue_script(
			'dabar',
			DABAR_URL . 'assets/js/dabar.js',
			array(),
			DABAR_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( $settings['dismissible'] ) {
			// Runs in <head> so a dismissed bar never flashes, even on cached pages.
			wp_register_script( 'dabar-dismissed', false, array(), DABAR_VERSION, false );
			wp_enqueue_script( 'dabar-dismissed' );
			wp_add_inline_script(
				'dabar-dismissed',
				sprintf(
					'(function(){var k="dabar_dismissed",v=%s;function c(s){var d=(s.getItem(k)||"").split("|");return d[0]===v&&(!d[1]||+d[1]>Date.now());}try{if(c(localStorage)||c(sessionStorage)){document.documentElement.classList.add("dabar-dismissed");}}catch(e){}})();',
					wp_json_encode( DABAR_Settings::content_version() )
				)
			);
		}
	}

	/**
	 * CSS custom properties built from the settings.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function inline_css( $settings ) {
		$defaults = DABAR_Settings::defaults();
		$optional = '';

		$font_family = DABAR_Settings::sanitize_font_family( $settings['font_family'] );
		if ( '' !== $font_family ) {
			$optional .= '--dabar-font-family:' . $font_family . ';';
		}

		$link_color = DABAR_Settings::sanitize_color( $settings['link_color'], '' );
		if ( '' !== $link_color ) {
			$optional .= '--dabar-link-color:' . $link_color . ';';
		}

		$height = DABAR_Settings::sanitize_css_value( $settings['height'], '' );
		if ( '' !== $height ) {
			$optional .= '--dabar-min-height:' . $height . ';';
		}

		return sprintf(
			'#dabar{--dabar-bg:%1$s;--dabar-color:%2$s;--dabar-close-color:%3$s;--dabar-font-size:%4$s;--dabar-padding:%5$s;--dabar-fade:%6$dms;--dabar-button-bg:%7$s;--dabar-button-color:%8$s;%9$s}',
			DABAR_Settings::sanitize_color( $settings['background_color'], $defaults['background_color'] ),
			DABAR_Settings::sanitize_color( $settings['text_color'], $defaults['text_color'] ),
			DABAR_Settings::sanitize_color( $settings['close_color'], $defaults['close_color'] ),
			DABAR_Settings::sanitize_css_value( $settings['font_size'], $defaults['font_size'] ),
			DABAR_Settings::sanitize_css_value( $settings['padding'], $defaults['padding'] ),
			(int) $settings['fade_duration'],
			DABAR_Settings::sanitize_color( $settings['button_bg'], $defaults['button_bg'] ),
			DABAR_Settings::sanitize_color( $settings['button_color'], $defaults['button_color'] ),
			$optional
		);
	}

	/**
	 * Prints the bar at the automatic position.
	 */
	public function print_bar() {
		if ( $this->rendered || 'auto' !== DABAR_Settings::get( 'placement' ) || ! $this->should_display() ) {
			return;
		}

		echo wp_kses( $this->get_bar_html( false ), self::allowed_html() );
	}

	/**
	 * Prints the bar in the footer when the theme never fired the placement hook.
	 */
	public function print_fallback() {
		$placement = DABAR_Settings::get( 'placement' );

		if ( $this->rendered || ! in_array( $placement, array( 'auto', 'bottom' ), true ) || ! $this->should_display() ) {
			return;
		}

		// A bottom bar is fixed to the screen, so it can stay in the footer.
		echo wp_kses( $this->get_bar_html( 'auto' === $placement ), self::allowed_html() );
	}

	/**
	 * Shortcode handler for manual placement, e.g. inside a Bricks header template.
	 *
	 * @return string
	 */
	public function shortcode() {
		if ( $this->rendered || ! $this->should_display() ) {
			return '';
		}

		return wp_kses( $this->get_bar_html( false ), self::allowed_html() );
	}

	/**
	 * Builds the bar markup.
	 *
	 * @param bool $relocate Whether JS should move the bar to the top of <body>.
	 * @return string
	 */
	private function get_bar_html( $relocate ) {
		$this->rendered = true;

		$settings = DABAR_Settings::get();
		$start    = DABAR_Settings::timestamp( $settings['schedule_start'] );
		$end      = DABAR_Settings::timestamp( $settings['schedule_end'] );

		$classes = array( 'dabar' );
		if ( 'bottom' === $settings['placement'] ) {
			$classes[] = 'dabar--bottom';
		} elseif ( $settings['sticky'] ) {
			$classes[] = 'dabar--sticky';
		}
		if ( 'start' === $settings['align'] ) {
			$classes[] = 'dabar--align-start';
		}
		if ( $settings['uppercase'] ) {
			$classes[] = 'dabar--uppercase';
		}
		if ( $settings['dismissible'] ) {
			$classes[] = 'dabar--dismissible';
		}
		if ( 'desktop' === $settings['devices'] ) {
			$classes[] = 'dabar--hide-mobile';
		} elseif ( 'mobile' === $settings['devices'] ) {
			$classes[] = 'dabar--hide-desktop';
		}

		$button = '';
		if ( '' !== $settings['button_text'] && '' !== $settings['button_url'] ) {
			$button = sprintf(
				'<a class="dabar__button" href="%1$s"%2$s>%3$s</a>',
				esc_url( $settings['button_url'] ),
				$settings['button_new_tab'] ? ' target="_blank" rel="noopener"' : '',
				esc_html( $settings['button_text'] )
			);
		}

		$messages = '';
		foreach ( array_values( $settings['messages'] ) as $index => $message ) {
			$messages .= sprintf(
				'<div class="dabar__message%1$s"%2$s>%3$s</div>',
				0 === $index ? ' is-active' : '',
				0 === $index ? '' : ' aria-hidden="true"',
				do_shortcode( nl2br( DABAR_Settings::sanitize_message( $message ) ) )
			);
		}

		$close = '';
		if ( $settings['dismissible'] ) {
			$close = sprintf(
				'<button type="button" class="dabar__close" aria-label="%s"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M13.5 4.5l-9 9M4.5 4.5l9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg></button>',
				esc_attr__( 'Dismiss announcement', 'definitive-announcement-bar' )
			);
		}

		return sprintf(
			'<div id="dabar" class="%1$s" role="region" aria-label="%2$s" data-interval="%3$d" data-dismiss-days="%4$d" data-version="%5$s" data-start="%6$d" data-end="%7$d"%8$s%9$s><div class="dabar__inner"><div class="dabar__content"><div class="dabar__messages">%10$s</div>%12$s</div>%11$s</div></div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr__( 'Announcement', 'definitive-announcement-bar' ),
			(int) $settings['interval'],
			(int) $settings['dismiss_days'],
			esc_attr( DABAR_Settings::content_version() ),
			$start,
			$end,
			$relocate ? ' data-relocate="1"' : '',
			$start > time() ? ' hidden' : '',
			$messages,
			$close,
			$button
		);
	}

	/**
	 * HTML allowed in the final bar output.
	 *
	 * @return array
	 */
	private static function allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['div'] = array_merge(
			isset( $allowed['div'] ) ? $allowed['div'] : array(),
			array(
				'id'          => true,
				'class'       => true,
				'role'        => true,
				'aria-label'  => true,
				'aria-hidden' => true,
				'hidden'      => true,
				'data-*'      => true,
			)
		);

		$allowed['button'] = array(
			'type'       => true,
			'class'      => true,
			'aria-label' => true,
		);

		$allowed['svg'] = array(
			'width'       => true,
			'height'      => true,
			'viewbox'     => true,
			'fill'        => true,
			'aria-hidden' => true,
			'focusable'   => true,
		);

		$allowed['path'] = array(
			'd'               => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
		);

		return $allowed;
	}
}
