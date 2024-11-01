<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

new Wc_Captcha_Update();

class Wc_Captcha_Update {

	public function __construct() {
		// actions
		add_action( 'init', array( &$this, 'check_update' ) );
	}

	/**
	 * Check update.
	 */
	public function check_update() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) )
			return;

		// gets current database version
		$current_db_version = get_option( 'wc_captcha_version', '1.4' );

		// new version?
		if ( version_compare( $current_db_version, Wc_Captcha()->defaults['version'], '<' ) ) {
			if ( version_compare( $current_db_version, '1.3', '<' ) ) {
				update_option( 'Wc_Captcha_options', Wc_Captcha()->options['general'] );
				delete_option( 'wc_options' );
			}

			// updates plugin version
			update_option( 'wc_captcha_version', Wc_Captcha()->defaults['version'] );
		}
	}

}