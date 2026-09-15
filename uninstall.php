<?php
/**
 * Removes all plugin data when the plugin is deleted.
 *
 * @package DefinitiveAnnouncementBar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'dabar_settings' );
delete_post_meta_by_key( '_dabar_hide' );
