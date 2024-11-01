<?php
/*
Plugin Name: WC Captcha
Description: WC Captcha is the <strong>Most Powerful Mathematical CAPTCHA for WordPress</strong> that integrates into Login/SignIn, Registration/SignUp, Reset Password/Lost Password, Comments Form, Contact Form 7 and bbPress.
Version: 1.5
Author: WebCource
Author URI: https://www.webcource.com/rimuc/
Plugin URI: https://wordpress.org/plugins/wc-captcha/
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wc-captcha
Domain Path: /languages
Shortcode: [wpcaptcha]

WC Captcha
Copyright (C) 2010-2023, WebCource - webcource@gmail.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

define( 'WC_CAPTCHA_URL', plugins_url( '', __FILE__ ) );
define( 'WC_CAPTCHA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_CAPTCHA_REL_PATH', dirname( plugin_basename( __FILE__ ) ) . '/' );

include_once(WC_CAPTCHA_PATH . 'includes/class-cookie-session.php');
include_once(WC_CAPTCHA_PATH . 'includes/class-update.php');
include_once(WC_CAPTCHA_PATH . 'includes/class-core.php');
include_once(WC_CAPTCHA_PATH . 'includes/class-settings.php');

/**
 * WC Captcha class.
 * 
 * @class Wc_Captcha
 * @version 1.2.2
 */
class Wc_Captcha {

	private static $_instance;
	public $core;
	public $cookie_session;
	public $options;
	public $defaults = array(
		'general'	 => array(
			'enable_for'				 => array(
				'login_form'			 => false,
				'registration_form'		 => true,
				'reset_password_form'	 => true,
				'comment_form'			 => true,
				'bbpress'				 => false,
				'contact_form_7'		 => false
			),
			'block_direct_comments'		 => false,
			'hide_for_logged_users'		 => true,
			'title'						 => 'WC Captcha',
			'mathematical_operations'	 => array(
				'addition'		 => true,
				'subtraction'	 => true,
				'multiplication' => false,
				'division'		 => false
			),
			'groups'					 => array(
				'numbers'	 => true,
				'words'		 => false
			),
			'time'						 => 500,
			'deactivation_delete'		 => false,
			'flush_rules'				 => false
		),
		'version'	 => '1.5'
	);

	public static function instance() {
		if ( self::$_instance === null )
			self::$_instance = new self();

		return self::$_instance;
	}

	public function __clone() {}
	public function __wakeup() {}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( &$this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivation' ) );

		// settings
		$this->options = array(
			'general' => array_merge( $this->defaults['general'], get_option( 'wc_captcha_options', $this->defaults['general'] ) )
		);

		// actions
		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_comments_scripts_styles' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'frontend_comments_scripts_styles' ) );
		add_action( 'login_enqueue_scripts', array( &$this, 'frontend_comments_scripts_styles' ) );

		// filters
		add_filter( 'plugin_action_links', array( &$this, 'plugin_settings_link' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_extend_links' ), 10, 2 );
	}

	/**
	 * Activation.
	 */
	public function activation() {
		add_option( 'wc_captcha_options', $this->defaults['general'], '', 'no' );
		add_option( 'wc_captcha_version', $this->defaults['version'], '', 'no' );
	}

	/**
	 * Deactivation.
	 */
	public function deactivation() {
		if ( $this->options['general']['deactivation_delete'] )
			delete_option( 'wc_captcha_options' );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wc-captcha', false, WC_CAPTCHA_REL_PATH . 'languages/' );
	}

	/**
	 * Enqueue admin scripts and styles.
	 * 
	 * @param string $page
	 */
	public function admin_comments_scripts_styles( $page ) {
		if ( $page === 'settings_page_wc-captcha' ) {
			wp_register_style(
				'wc-captcha-admin', WC_CAPTCHA_URL . '/css/admin.css'
			);

			wp_enqueue_style( 'wc-captcha-admin' );

			wp_register_script(
				'wc-captcha-admin-settings', WC_CAPTCHA_URL . '/js/admin-settings.js', array( 'jquery' )
			);

			wp_enqueue_script( 'wc-captcha-admin-settings' );

			wp_localize_script(
				'wc-captcha-admin-settings', 'mcArgsSettings', array(
				'resetToDefaults' => __( 'Are you sure you want to reset these settings to defaults?', 'wc-captcha' )
				)
			);
		}
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function frontend_comments_scripts_styles() {
		wp_register_style(
			'wc-captcha-frontend', WC_CAPTCHA_URL . '/css/frontend.css'
		);

		wp_enqueue_style( 'wc-captcha-frontend' );
	}

	/**
	 * Add links to support forum
	 * 
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	public function plugin_extend_links( $links, $file ) {
		if ( ! current_user_can( 'install_plugins' ) )
			return $links;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin ) {
			return array_merge(
				$links, array( sprintf( '<a href="https://wordpress.org/support/plugin/wc-captcha/" target="_blank">%s</a>', __( 'Support', 'wc-captcha' ) ) )
			);
		}

		return $links;
	}

	/**
	 * Add links to settings page
	 * 
	 * @param array $links
	 * @param string $file
	 * @return array
	 */
	function plugin_settings_link( $links, $file ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) )
			return $links;

		static $plugin;

		$plugin = plugin_basename( __FILE__ );

		if ( $file == $plugin ) {
			$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php' ) . '?page=wc-captcha', __( 'Settings', 'wc-captcha' ) );
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

}


function Wc_Captcha() {
	static $instance;

	// first call to instance() initializes the plugin
	if ( $instance === null || ! ($instance instanceof Wc_Captcha) )
		$instance = Wc_Captcha::instance();

	return $instance;
}

function util_array_trim(array &$array, $filter = false)
{
    array_walk_recursive($array, function (&$value) use ($filter) {
        $value = trim($value);
        if ($filter) {
            $value = filter_var($value, FILTER_SANITIZE_STRING);
        }
    });

    return $array;
}

Wc_Captcha();