<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

new Wc_Captcha_Settings();

class Wc_Captcha_Settings {

	public $mathematical_operations;
	public $groups;
	public $forms;

	public function __construct() {
		// actions
		add_action( 'init', array( &$this, 'load_defaults' ) );
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu_options' ) );
	}

	/**
	 * Load defaults.
	 */
	public function load_defaults() {
		if ( ! is_admin() )
			return;

		$this->forms = array(
			'login_form'			 => __( 'Login form', 'wc-captcha' ),
			'registration_form'		 => __( 'Registration form', 'wc-captcha' ),
			'reset_password_form'	 => __( 'Reset password form', 'wc-captcha' ),
			'comment_form'			 => __( 'Comment form', 'wc-captcha' ),
			'contact_form_7'		 => __( 'Contact form 7', 'wc-captcha' ),
			'bbpress'				 => __( 'bbpress', 'wc-captcha' )
		);

		$this->mathematical_operations = array(
			'addition'		 => __( 'Addition (+)', 'wc-captcha' ),
			'subtraction'	 => __( 'Subtraction (-)', 'wc-captcha' ),
			'multiplication' => __( 'Multiplication (&#215;)', 'wc-captcha' ),
			'division'		 => __( 'Division (&#247;)', 'wc-captcha' )
		);

		$this->groups = array(
			'numbers'	 => __( 'Numbers', 'wc-captcha' ),
			'words'		 => __( 'Words', 'wc-captcha' )
		);
	}

	/**
	 * Add options menu.
	 */
	public function admin_menu_options() {
		add_options_page(
			__( 'WC Captcha', 'wc-captcha' ), __( 'WC Captcha', 'wc-captcha' ), 'manage_options', 'wc-captcha', array( &$this, 'options_page' )
		);
	}

	/**
	 * Render options page.
	 * 
	 * @return mixed
	 */
	public function options_page() {
		echo '
		<div class="wrap">
			<h2><b>' . __( 'WC Captcha', 'wc-captcha' ) . '</b></h2>
			<div class="wc-captcha-settings">
				<div class="wc-credits">
					<h3 class="hndle">' . __( 'WC Captcha', 'wc-captcha' ) . ' ' . Wc_Captcha()->defaults['version'] . '</h3>
					<div class="inside">
						<h4 class="inner"><label for="wc">' . __( 'Shortcode: <input id="wc" value="[wpcaptcha wc]"/>', 'wc-captcha' ) . '</label></h4>
						<h3 class="inner">'. __('We have some suggestions for your setup. Let us know if you have a suggestion for <a target="_blank" href="https://webcource.com/contact-us/">us</a>!', 'wc-captcha' ) . '</h3>
						<h4 class="inner text-center">'. __('You can Donate here for this plugin <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=FGEHDRXC93W6C&source=url"><img  src="' . WC_CAPTCHA_URL . '/images/btn_donate.gif" title="Donate for Inspiration Developing Plugin to WebCource" alt="Donate WebCource - Quality plugins for WordPress"/></a>', 'wc-captcha' ) . '</h4>
						<h4 class="inner">' . __( 'Need support?', 'wc-captcha' ) . '</h4>
						<p class="inner">' . __( 'If you are having problems with this plugin, please talk about them in the', 'wc-captcha' ) . ' <a href="https://wordpress.org/support/plugin/wc-captcha/" target="_blank" title="' . __( 'Support forum', 'wc-captcha' ) . '">' . __( 'Support forum', 'wc-captcha' ) . '</a></p>
						<hr/>
						<h4 class="inner">' . __( 'Do you like this plugin?', 'wc-captcha' ) . '</h4>
						<p class="inner"><a href="http://wordpress.org/support/view/plugin-reviews/wc-captcha" target="_blank" title="' . __( 'Rate it 5', 'wc-captcha' ) . '">' . __( 'Rate it 5', 'wc-captcha' ) . '</a> ' . __( 'on WordPress.org', 'wc-captcha' ) . '<br/>' .
		__( 'Blog about it & link to the', 'wc-captcha' ) . ' <a href="https://wordpress.org/plugins/wc-captcha/" target="_blank" title="' . __( 'plugin page', 'wc-captcha' ) . '">' . __( 'plugin page', 'wc-captcha' ) . '</a><br/>' .
		__( 'Check out our other', 'wc-captcha' ) . ' <a href="https://wordpress.org/plugins/wc-captcha/" target="_blank" title="' . __( 'WordPress plugins', 'wc-captcha' ) . '">' . __( 'WordPress plugins', 'wc-captcha' ) . '</a>
						</p>
						<hr/>
						<p class="wc-link inner text-center">Created & Developed by <a href="https://webcource.com/" target="_blank" title="WebCource - Quality plugins for WordPress"><img width="125" src="' . WC_CAPTCHA_URL . '/images/logo-webcource.png" title="WebCource - Quality plugins for WordPress" alt="WebCource - Quality plugins for WordPress"/></a></p>
					</div>
				</div>
				<form action="options.php" method="post">';

		wp_nonce_field( 'update-options' );
		settings_fields( 'Wc_Captcha_options' );
		do_settings_sections( 'Wc_Captcha_options' );

		echo '
					<p class="submit">';

		submit_button( '', 'primary', 'save_wc_general', false );

		echo ' ';

		submit_button( __( 'Reset to defaults', 'wc-captcha' ), 'secondary reset_wc_settings', 'reset_wc_general', false );

		echo '
					</p>
				</form>
			</div>
			<div class="clear"></div>
		</div>';
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// general settings
		register_setting( 'Wc_Captcha_options', 'Wc_Captcha_options', array( &$this, 'validate_settings' ) );
		add_settings_section( 'Wc_Captcha_Settings', __( 'WC Captcha settings', 'wc-captcha' ), '', 'Wc_Captcha_options' );
		add_settings_field( 'wc_general_enable_captcha_for', __( 'Enable WC Captcha for', 'wc-captcha' ), array( &$this, 'wc_general_enable_captcha_for' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_hide_for_logged_users', __( 'Hide for logged in users', 'wc-captcha' ), array( &$this, 'wc_general_hide_for_logged_users' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_mathematical_operations', __( 'Mathematical operations', 'wc-captcha' ), array( &$this, 'wc_general_mathematical_operations' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_groups', __( 'Display captcha as', 'wc-captcha' ), array( &$this, 'wc_general_groups' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_title', __( 'Captcha field title', 'wc-captcha' ), array( &$this, 'wc_general_title' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_time', __( 'Captcha time', 'wc-captcha' ), array( &$this, 'wc_general_time' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_block_direct_comments', __( 'Block Direct Comments', 'wc-captcha' ), array( &$this, 'wc_general_block_direct_comments' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
		add_settings_field( 'wc_general_deactivation_delete', __( 'Deactivation', 'wc-captcha' ), array( &$this, 'wc_general_deactivation_delete' ), 'Wc_Captcha_options', 'Wc_Captcha_Settings' );
	}

	public function wc_general_enable_captcha_for() {
		echo '
		<div id="wc_general_enable_captcha_for">
			<fieldset>';

		foreach ( $this->forms as $val => $trans ) {
			echo '
				<input id="wc-general-enable-captcha-for-' . $val . '" type="checkbox" name="Wc_Captcha_options[enable_for][]" value="' . $val . '" ' . checked( true, Wc_Captcha()->options['general']['enable_for'][$val], false ) . ' ' . disabled( (($val === 'contact_form_7' && ! class_exists( 'WPCF7_ContactForm' )) || ($val === 'bbpress' && ! class_exists( 'bbPress' )) ), true, false ) . '/><label for="wc-general-enable-captcha-for-' . $val . '">' . esc_html( $trans ) . '</label>';
		}

		echo '
				<br/>
				<span class="description">' . __( 'Select where you\'d like to use WC Captcha.', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_hide_for_logged_users() {
		echo '
		<div id="wc_general_hide_for_logged_users">
			<fieldset>
				<input id="wc-general-hide-for-logged" type="checkbox" name="Wc_Captcha_options[hide_for_logged_users]" ' . checked( true, Wc_Captcha()->options['general']['hide_for_logged_users'], false ) . '/><label for="wc-general-hide-for-logged">' . __( 'Enable to hide captcha for logged in users.', 'wc-captcha' ) . '</label>
				<br/>
				<span class="description">' . __( 'Would you like to hide captcha for logged in users?', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_mathematical_operations() {
		echo '
		<div id="wc_general_mathematical_operations">
			<fieldset>';

		foreach ( $this->mathematical_operations as $val => $trans ) {
			echo '
				<input id="wc-general-mathematical-operations-' . $val . '" type="checkbox" name="Wc_Captcha_options[mathematical_operations][]" value="' . $val . '" ' . checked( true, Wc_Captcha()->options['general']['mathematical_operations'][$val], false ) . '/><label for="wc-general-mathematical-operations-' . $val . '">' . esc_html( $trans ) . '</label>';
		}

		echo '
				<br/>
				<span class="description">' . __( 'Select which mathematical operations to use in your captcha.', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_groups() {
		echo '
		<div id="wc_general_groups">
			<fieldset>';

		foreach ( $this->groups as $val => $trans ) {
			echo '
				<input id="wc-general-groups-' . $val . '" type="checkbox" name="Wc_Captcha_options[groups][]" value="' . $val . '" ' . checked( true, Wc_Captcha()->options['general']['groups'][$val], false ) . '/><label for="wc-general-groups-' . $val . '">' . esc_html( $trans ) . '</label>';
		}

		echo '
				<br/>
				<span class="description">' . __( 'Select how you\'d like to display you captcha.', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_title() {
		echo '
		<div id="wc_general_title">
			<fieldset>
				<input type="text" name="Wc_Captcha_options[title]" value="' . Wc_Captcha()->options['general']['title'] . '"/>
				<br/>
				<span class="description">' . __( 'How to enter title field with captcha?', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_time() {
		echo '
		<div id="wc_general_time">
			<fieldset>
				<input type="text" name="Wc_Captcha_options[time]" value="' . Wc_Captcha()->options['general']['time'] . '"/>
				<br/>
				<span class="description">' . __( 'Enter the time (in seconds) a user has to enter captcha value.', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_block_direct_comments() {
		echo '
		<div id="wc_general_block_direct_comments">
			<fieldset>
				<input id="wc-general-block-direct-comments" type="checkbox" name="Wc_Captcha_options[block_direct_comments]" ' . checked( true, Wc_Captcha()->options['general']['block_direct_comments'], false ) . '/><label for="wc-general-block-direct-comments">' . __( 'Block direct access to wp-comments-post.php.', 'wc-captcha' ) . '</label>
				<br/>
				<span class="description">' . __( 'Enable this to prevent spambots from posting to Wordpress via a URL.', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	public function wc_general_deactivation_delete() {
		echo '
		<div id="wc_general_deactivation_delete">
			<fieldset>
				<input id="wc-general-deactivation-delete" type="checkbox" name="Wc_Captcha_options[deactivation_delete]" ' . checked( true, Wc_Captcha()->options['general']['deactivation_delete'], false ) . '/><label for="wc-general-deactivation-delete">' . __( 'Delete settings on plugin deactivation.', 'wc-captcha' ) . '</label>
				<br/>
				<span class="description">' . __( 'Delete settings on plugin deactivation', 'wc-captcha' ) . '</span>
			</fieldset>
		</div>';
	}

	/**
	 * Validate settings.
	 * 
	 * @param array $input
	 * @return array
	 */
	public function validate_settings( $input ) {
		if ( isset( $_POST['save_wc_general'] ) ) {
			// enable captcha forms
			$enable_for = array();

			if ( empty( $input['enable_for'] ) ) {
				foreach ( Wc_Captcha()->defaults['general']['enable_for'] as $enable => $bool ) {
					$input['enable_for'][$enable] = false;
				}
			} else {
				foreach ( $this->forms as $enable => $trans ) {
					$enable_for[$enable] = (in_array( $enable, $input['enable_for'] ) ? true : false);
				}

				$input['enable_for'] = $enable_for;
			}

			if ( ! class_exists( 'WPCF7_ContactForm' ) && Wc_Captcha()->options['general']['enable_for']['contact_form_7'] )
				$input['enable_for']['contact_form_7'] = true;

			if ( ! class_exists( 'bbPress' ) && Wc_Captcha()->options['general']['enable_for']['bbpress'] )
				$input['enable_for']['bbpress'] = true;

			// enable mathematical operations
			$mathematical_operations = array();

			if ( empty( $input['mathematical_operations'] ) ) {
				add_settings_error( 'empty-operations', 'settings_updated', __( 'You need to check at least one mathematical operation. Defaults settings of this option restored.', 'wc-captcha' ), 'error' );

				$input['mathematical_operations'] = Wc_Captcha()->defaults['general']['mathematical_operations'];
			} else {
				foreach ( $this->mathematical_operations as $operation => $trans ) {
					$mathematical_operations[$operation] = (in_array( $operation, $input['mathematical_operations'] ) ? true : false);
				}

				$input['mathematical_operations'] = $mathematical_operations;
			}

			// enable groups
			$groups = array();

			if ( empty( $input['groups'] ) ) {
				add_settings_error( 'empty-groups', 'settings_updated', __( 'You need to check at least one group. Defaults settings of this option restored.', 'wc-captcha' ), 'error' );

				$input['groups'] = Wc_Captcha()->defaults['general']['groups'];
			} else {
				foreach ( $this->groups as $group => $trans ) {
					$groups[$group] = (in_array( $group, $input['groups'] ) ? true : false);
				}

				$input['groups'] = $groups;
			}

			// hide for logged in users
			$input['hide_for_logged_users'] = isset( $input['hide_for_logged_users'] );

			// block direct comments access
			$input['block_direct_comments'] = isset( $input['block_direct_comments'] );

			// deactivation delete
			$input['deactivation_delete'] = isset( $input['deactivation_delete'] );

			// captcha title
			$input['title'] = trim( $input['title'] );

			// captcha time
			$time = (int) $input['time'];
			$input['time'] = ($time < 0 ? Wc_Captcha()->defaults['general']['time'] : $time);

			// flush rules
			$input['flush_rules'] = true;
		} elseif ( isset( $_POST['reset_wc_general'] ) ) {
			$input = Wc_Captcha()->defaults['general'];

			add_settings_error( 'settings', 'settings_reset', __( 'Settings restored to defaults.', 'wc-captcha' ), 'updated' );
		}

		return $input;
	}

}