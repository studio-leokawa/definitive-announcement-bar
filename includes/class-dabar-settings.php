<?php
/**
 * Settings storage, defaults and sanitization.
 *
 * @package DefinitiveAnnouncementBar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All plugin settings live in a single option array.
 */
final class DABAR_Settings {

	const OPTION    = 'dabar_settings';
	const META_HIDE = '_dabar_hide';

	/**
	 * Parsed settings for the current request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'          => true,
			'messages'         => array(),
			'interval'         => 5000,
			'placement'        => 'auto',
			'sticky'           => false,
			'background_color' => '#111111',
			'text_color'       => '#ffffff',
			'close_color'      => '#ffffff',
			'font_size'        => '14px',
			'padding'          => '10px 24px',
			'uppercase'        => false,
			'dismissible'      => true,
			'dismiss_days'     => 0,
			'fade_duration'    => 600,
			'audience'         => 'all',
			'hide_on'          => array(),
			'schedule_start'   => '',
			'schedule_end'     => '',
		);
	}

	/**
	 * Returns all settings, or a single one.
	 *
	 * @param string|null $key Setting key.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}

		if ( null === $key ) {
			return self::$cache;
		}

		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Clears the request cache after the option changes.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * WordPress locations the bar can be hidden on.
	 *
	 * @return array
	 */
	public static function wp_locations() {
		return array(
			'front_page' => __( 'Front page', 'definitive-announcement-bar' ),
			'blog'       => __( 'Blog and archive pages', 'definitive-announcement-bar' ),
			'posts'      => __( 'Single posts', 'definitive-announcement-bar' ),
			'pages'      => __( 'Pages', 'definitive-announcement-bar' ),
			'search_404' => __( 'Search results and 404 page', 'definitive-announcement-bar' ),
		);
	}

	/**
	 * WooCommerce locations the bar can be hidden on.
	 *
	 * @return array
	 */
	public static function wc_locations() {
		return array(
			'wc_shop'     => __( 'Shop and product category pages', 'definitive-announcement-bar' ),
			'wc_product'  => __( 'Single product pages', 'definitive-announcement-bar' ),
			'wc_cart'     => __( 'Cart', 'definitive-announcement-bar' ),
			'wc_checkout' => __( 'Checkout', 'definitive-announcement-bar' ),
			'wc_account'  => __( 'My account', 'definitive-announcement-bar' ),
		);
	}

	/**
	 * HTML allowed inside a message.
	 *
	 * @return array
	 */
	public static function message_allowed_html() {
		return array(
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
			'b'      => array(),
			'strong' => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			's'      => array(),
			'small'  => array(),
			'br'     => array(),
			'span'   => array( 'class' => true ),
		);
	}

	/**
	 * Sanitizes the submitted settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		foreach ( array( 'enabled', 'sticky', 'uppercase', 'dismissible' ) as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] );
		}

		$messages           = isset( $input['messages'] ) ? (array) $input['messages'] : array();
		$messages           = array_map( array( __CLASS__, 'sanitize_message' ), $messages );
		$output['messages'] = array_values( array_filter( $messages, 'strlen' ) );

		$output['interval']      = self::clamp_int( $input, 'interval', 1000, 60000 );
		$output['dismiss_days']  = self::clamp_int( $input, 'dismiss_days', 0, 365 );
		$output['fade_duration'] = self::clamp_int( $input, 'fade_duration', 0, 5000 );

		$output['placement'] = self::choice( $input, 'placement', array( 'auto', 'shortcode' ) );
		$output['audience']  = self::choice( $input, 'audience', array( 'all', 'logged_in', 'logged_out' ) );

		foreach ( array( 'background_color', 'text_color', 'close_color' ) as $key ) {
			$output[ $key ] = self::sanitize_color( isset( $input[ $key ] ) ? $input[ $key ] : '', $defaults[ $key ] );
		}

		foreach ( array( 'font_size', 'padding' ) as $key ) {
			$output[ $key ] = self::sanitize_css_value( isset( $input[ $key ] ) ? $input[ $key ] : '', $defaults[ $key ] );
		}

		$hide_on           = isset( $input['hide_on'] ) ? array_map( 'sanitize_key', (array) $input['hide_on'] ) : array();
		$known             = array_keys( array_merge( self::wp_locations(), self::wc_locations() ) );
		$output['hide_on'] = array_values( array_intersect( $hide_on, $known ) );

		foreach ( array( 'schedule_start', 'schedule_end' ) as $key ) {
			$value          = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
			$output[ $key ] = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? $value : '';
		}

		return $output;
	}

	/**
	 * Sanitizes one message.
	 *
	 * @param mixed $message Raw message.
	 * @return string
	 */
	public static function sanitize_message( $message ) {
		return trim( wp_kses( (string) $message, self::message_allowed_html() ) );
	}

	/**
	 * Accepts hex, rgb(), hsl(), oklch(), CSS variables and named colors.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Value used when the input is invalid.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback ) {
		$value = trim( (string) $value );

		if ( preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value )
			|| preg_match( '/^(rgba?|hsla?|oklch|oklab)\([0-9a-z%.,\s\/+-]*\)$/i', $value )
			|| preg_match( '/^var\(--[a-z0-9_-]+\)$/i', $value )
			|| preg_match( '/^[a-z]{3,30}$/i', $value ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Accepts simple CSS values such as lengths, calc() or var() expressions.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Value used when the input is invalid.
	 * @return string
	 */
	public static function sanitize_css_value( $value, $fallback ) {
		$value = trim( (string) $value );

		if ( '' !== $value && strlen( $value ) <= 100 && preg_match( '/^[a-z0-9.%\s()+*\/,_-]+$/i', $value ) ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Converts a stored schedule value (site time zone) to a Unix timestamp.
	 *
	 * @param string $value Value in Y-m-d\TH:i format.
	 * @return int 0 when empty or invalid.
	 */
	public static function timestamp( $value ) {
		if ( ! $value ) {
			return 0;
		}

		$date = date_create_immutable_from_format( 'Y-m-d\TH:i', $value, wp_timezone() );

		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Short hash of the messages, so a dismissed bar reappears when the content changes.
	 *
	 * @return string
	 */
	public static function content_version() {
		return substr( md5( (string) wp_json_encode( self::get( 'messages' ) ) ), 0, 10 );
	}

	/**
	 * Reads an integer and clamps it to a range.
	 *
	 * @param array  $input Raw input.
	 * @param string $key   Setting key.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return int
	 */
	private static function clamp_int( $input, $key, $min, $max ) {
		$defaults = self::defaults();
		$value    = isset( $input[ $key ] ) && '' !== $input[ $key ] ? (int) $input[ $key ] : $defaults[ $key ];

		return max( $min, min( $max, $value ) );
	}

	/**
	 * Reads a value that must be one of a fixed set.
	 *
	 * @param array  $input   Raw input.
	 * @param string $key     Setting key.
	 * @param array  $choices Allowed values.
	 * @return string
	 */
	private static function choice( $input, $key, $choices ) {
		$defaults = self::defaults();

		return isset( $input[ $key ] ) && in_array( $input[ $key ], $choices, true ) ? $input[ $key ] : $defaults[ $key ];
	}
}
