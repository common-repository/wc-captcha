<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

new Wc_Captcha_Core();

class Wc_Captcha_Core {

	public $session_number = 0;
	public $login_failed = false;
	public $error_messages;
	public $errors;

	/**
	 * 
	 */
	public function __construct() {
		// set instance
		Wc_Captcha()->core = $this;

		// actions
		add_action( 'init', array( &$this, 'load_actions_filters' ), 1 );
		add_action( 'plugins_loaded', array( &$this, 'load_defaults' ) );
		add_action( 'admin_init', array( &$this, 'flush_rewrites' ) );

		// filters
		add_filter( 'shake_error_codes', array( &$this, 'add_shake_error_codes' ), 1 );
		add_filter( 'mod_rewrite_rules', array( &$this, 'block_direct_comments' ) );
	}

	/**
	 * Load defaults.
	 */
	public function load_defaults() {
		$this->error_messages = array(
			'fill'	 => '' . __( 'ERROR', 'wc-captcha' ) . ': ' . __( 'Please enter captcha value.', 'wc-captcha' ),
			'wrong'	 => '' . __( 'ERROR', 'wc-captcha' ) . ': ' . __( 'Invalid captcha value.', 'wc-captcha' ),
			'time'	 => '' . __( 'ERROR', 'wc-captcha' ) . ': ' . __( 'Captcha time expired.', 'wc-captcha' )
		);
	}

	/**
	 * Load required filters.
	 */
	public function load_actions_filters() {
		// Contact Form 7
		if ( Wc_Captcha()->options['general']['enable_for']['contact_form_7'] && class_exists( 'WPCF7_ContactForm' ) )
			include_once(WC_CAPTCHA_PATH . 'includes/integrations/contact-form-7.php');

		if ( is_admin() )
			return;

		$action = (isset( $_GET['action'] ) && $_GET['action'] !== '' ? $_GET['action'] : null);

		// comments
		if ( Wc_Captcha()->options['general']['enable_for']['comment_form'] ) {
			if ( ! is_user_logged_in() )
				add_action( 'comment_form_after_fields', array( &$this, 'add_captcha_form' ) );
			elseif ( ! Wc_Captcha()->options['general']['hide_for_logged_users'] )
				add_action( 'comment_form_logged_in_after', array( &$this, 'add_captcha_form' ) );

			add_filter( 'preprocess_comment', array( &$this, 'add_comment_with_captcha' ) );
		}

		// registration
		if ( Wc_Captcha()->options['general']['enable_for']['registration_form'] && ( ! is_user_logged_in() || (is_user_logged_in() && ! Wc_Captcha()->options['general']['hide_for_logged_users'])) && $action === 'register' ) {
			add_action( 'register_form', array( &$this, 'add_captcha_form' ) );
			add_action( 'register_post', array( &$this, 'add_user_with_captcha' ), 10, 3 );
			add_action( 'signup_extra_fields', array( &$this, 'add_captcha_form' ) );
			add_filter( 'wpmu_validate_user_signup', array( &$this, 'validate_user_with_captcha' ) );
		}

		// lost password
		if ( Wc_Captcha()->options['general']['enable_for']['reset_password_form'] && ( ! is_user_logged_in() || (is_user_logged_in() && ! Wc_Captcha()->options['general']['hide_for_logged_users'])) && $action === 'lostpassword' ) {
			add_action( 'lostpassword_form', array( &$this, 'add_captcha_form' ) );
			add_action( 'lostpassword_post', array( &$this, 'check_lost_password_with_captcha' ) );
		}

		// login
		if ( Wc_Captcha()->options['general']['enable_for']['login_form'] && ( ! is_user_logged_in() || (is_user_logged_in() && ! Wc_Captcha()->options['general']['hide_for_logged_users'])) && $action === null ) {
			add_action( 'login_form', array( &$this, 'add_captcha_form' ) );
			add_filter( 'login_redirect', array( &$this, 'redirect_login_with_captcha' ), 10, 3 );
			add_filter( 'authenticate', array( &$this, 'authenticate_user' ), 1000, 3 );
		}

		// bbPress
		if ( Wc_Captcha()->options['general']['enable_for']['bbpress'] && class_exists( 'bbPress' ) && ( ! is_user_logged_in() || (is_user_logged_in() && ! Wc_Captcha()->options['general']['hide_for_logged_users'])) ) {
			add_action( 'bbp_theme_after_reply_form_content', array( &$this, 'add_bbp_captcha_form' ) );
			add_action( 'bbp_theme_after_topic_form_content', array( &$this, 'add_bbp_captcha_form' ) );
			add_action( 'bbp_new_reply_pre_extras', array( &$this, 'check_bbpress_captcha' ) );
			add_action( 'bbp_new_topic_pre_extras', array( &$this, 'check_bbpress_captcha' ) );
		}
	}

	/**
	 * Add lost password errors.
	 * 
	 * @param array $errors
	 * @return array
	 */
	public function add_lostpassword_captcha_message( $errors ) {
		return $errors . $this->errors->errors['wc_captcha-error'][0];
	}

	/**
	 * Add lost password errors (special way)
	 * 
	 * @return array
	 */
	public function add_lostpassword_wp_message() {
		return $this->errors;
	}

	/**
	 * Validate lost password form.
	 */
	public function check_lost_password_with_captcha() {
		$this->errors = new WP_Error();
		$user_error = false;
		$user_data = null;

		// checks captcha
		if ( isset( $_POST['wc-value'] ) && $_POST['wc-value'] !== '' ) {
			if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
				if ( strcmp( get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) !== 0 )
					$this->errors->add( 'wc_captcha-error', $this->error_messages['wrong'] );
			} else
				$this->errors->add( 'wc_captcha-error', $this->error_messages['time'] );
		} else
			$this->errors->add( 'wc_captcha-error', $this->error_messages['fill'] );

		// checks user_login (from wp-login.php)
		if ( empty( $_POST['user_login'] ) )
			$user_error = true;
		elseif ( strpos( $_POST['user_login'], '@' ) ) {
			$user_data = get_user_by( sanitize_email('email', trim( $_POST['user_login'] ) ));

			if ( empty( $user_data ) )
				$user_error = true;
		} else
			$user_data = get_user_by( sanitize_user('login', trim( $_POST['user_login'] ) ));

		if ( ! $user_data )
			$user_error = true;

		// something went wrong?
		if ( ! empty( $this->errors->errors ) ) {
			// nasty hack (captcha is invalid but user_login is fine)
			if ( $user_error === false )
				add_filter( 'allow_password_reset', array( &$this, 'add_lostpassword_wp_message' ) );
			else
				add_filter( 'login_errors', array( &$this, 'add_lostpassword_captcha_message' ) );
		}
	}

	/**
	 * Validate registration form.
	 * 
	 * @param string $login
	 * @param string $email
	 * @param array $errors
	 * @return array
	 */
	public function add_user_with_captcha( $login, $email, $errors ) {
		if ( isset( $_POST['wc-value'] ) && $_POST['wc-value'] !== '' ) {
			if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
				if ( strcmp( get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) !== 0 )
					$errors->add( 'wc_captcha-error', $this->error_messages['wrong'] );
			} else
				$errors->add( 'wc_captcha-error', $this->error_messages['time'] );
		} else
			$errors->add( 'wc_captcha-error', $this->error_messages['fill'] );

		return $errors;
	}

	/**
	 * Validate registration form.
	 * 
	 * @param array $result
	 * @return array
	 */
	public function validate_user_with_captcha( $result ) {
		if ( isset( $_POST['wc-value'] ) && $_POST['wc-value'] !== '' ) {
			if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
				if ( strcmp( get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) !== 0 )
					$result['errors']->add( 'wc_captcha-error', $this->error_messages['wrong'] );
			} else
				$result['errors']->add( 'wc_captcha-error', $this->error_messages['time'] );
		} else
			$result['errors']->add( 'wc_captcha-error', $this->error_messages['fill'] );

		return $result;
	}

	/**
	 * Posts login form
	 * 
	 * @param string $redirect
	 * @param bool $bool
	 * @param array $errors
	 * @return array
	 */
	public function redirect_login_with_captcha( $redirect, $bool, $errors ) {
		if ( $this->login_failed === false && ! empty( $_POST ) ) {
			$error = '';

			if ( isset( $_POST['wc-value'] ) && $_POST['wc-value'] !== '' ) {
				if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
					if ( strcmp( get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) !== 0 )
						$error = 'wrong';
				} else
					$error = 'time';
			} else
				$error = 'fill';

			if ( is_wp_error( $errors ) && ! empty( $error ) )
				$errors->add( 'wc_captcha-error', $this->error_messages[$error] );
		}

		return $redirect;
	}

	/**
	 * Authenticate user.
	 * 
	 * @param WP_Error $user
	 * @param string $username
	 * @param string $password
	 * @return \WP_Error
	 */
	public function authenticate_user( $user, $username, $password ) {
		// user gave us valid login and password
		if ( ! is_wp_error( $user ) ) {
			if ( ! empty( $_POST ) ) {
				if ( isset( $_POST['wc-value'] ) && $_POST['wc-value'] !== '' ) {
					if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
						if ( strcmp( get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) !== 0 )
							$error = 'wrong';
					} else
						$error = 'time';
				} else
					$error = 'fill';
			}

			if ( ! empty( $error ) ) {
				// destroy cookie
				wp_clear_auth_cookie();

				$user = new WP_Error();
				$user->add( 'wc_captcha-error', $this->error_messages[$error] );

				// inform redirect function that we failed to login
				$this->login_failed = true;
			}
		}

		return $user;
	}

	/**
	 * Add shake.
	 * 
	 * @param array $codes
	 * @return array
	 */
	public function add_shake_error_codes( $codes ) {
		$codes[] = 'wc_captcha-error';

		return $codes;
	}

	/**
	 * Add captcha to comment form.
	 * 
	 * @param array $comment
	 * @return array
	 */
	public function add_comment_with_captcha( $comment ) {
		if ( isset( $_POST['wc-value'] ) && ( ! is_admin() || DOING_AJAX) && ($comment['comment_type'] === '' || $comment['comment_type'] === 'comment') ) {
			if ( $_POST['wc-value'] !== '' ) {
				if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
					if ( strcmp( get_transient( 'wc_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) === 0 )
						return $comment;
					else
						wp_die( $this->error_messages['wrong'] );
				} else
					wp_die( $this->error_messages['time'] );
			} else
				wp_die( $this->error_messages['fill'] );
		} else
			return $comment;
	}

	/**
	 * Display and generate captcha.
	 * 
	 * @return mixed
	 */
	public function add_captcha_form() {
		if ( is_admin() )
			return;

		$captcha_title = apply_filters( 'Wc_Captcha_title', Wc_Captcha()->options['general']['title'] );

		echo '
		<p class="wc_captcha-form">';

		if ( ! empty( $captcha_title ) )
			echo '
			<label>' . $captcha_title . '<br/></label>';

		echo '
			<span>' . $this->generate_captcha_phrase( 'default' ) . '</span>
		</p>';
	}

	/**
	 * Display and generate captcha for bbPress forms.
	 * 
	 * @return mixed
	 */
	public function add_bbp_captcha_form() {
		if ( is_admin() )
			return;

		$captcha_title = apply_filters( 'Wc_Captcha_title', Wc_Captcha()->options['general']['title'] );

		echo '
		<p class="wc_captcha-form">';

		if ( ! empty( $captcha_title ) )
			echo '
			<label>' . $captcha_title . '<br/></label>';

		echo '
			<span>' . $this->generate_captcha_phrase( 'bbpress' ) . '</span>
		</p>';
	}

	/**
	 * Validate bbpress topics and replies.
	 */
	public function check_bbpress_captcha() {
		if ( isset( $_POST['wc-value'] ) && $_POST['wc-value'] !== '' ) {
			if ( Wc_Captcha()->cookie_session->session_ids['default'] !== '' && get_transient( 'bbp_' . Wc_Captcha()->cookie_session->session_ids['default'] ) !== false ) {
				if ( strcmp( get_transient( 'bbp_' . Wc_Captcha()->cookie_session->session_ids['default'] ), sha1( AUTH_KEY . $_POST['wc-value'] . Wc_Captcha()->cookie_session->session_ids['default'], false ) ) !== 0 )
					bbp_add_error( 'wc_captcha-wrong', $this->error_messages['wrong'] );
			} else
				bbp_add_error( 'wc_captcha-wrong', $this->error_messages['time'] );
		} else
			bbp_add_error( 'wc_captcha-wrong', $this->error_messages['fill'] );
	}

	/**
	 * Encode chars.
	 * 
	 * @param string $string
	 * @return string
	 */
	private function encode_operation( $string ) {
		$chars = str_split( $string );
		$seed = mt_rand( 0, (int) abs( crc32( $string ) / strlen( $string ) ) );

		foreach ( $chars as $key => $char ) {
			$ord = ord( $char );

			// ignore non-ascii chars
			if ( $ord < 128 ) {
				// pseudo "random function"
				$r = ($seed * (1 + $key)) % 100;

				if ( $r > 60 && $char !== '@' ) {
					
				} // plain character (not encoded), if not @-sign
				elseif ( $r < 45 )
					$chars[$key] = '&#x' . dechex( $ord ) . ';'; // hexadecimal
				else
					$chars[$key] = '&#' . $ord . ';'; // decimal (ascii)
			}
		}

		return implode( '', $chars );
	}

	/**
	 * Convert numbers to words.
	 * 
	 * @param int $number
	 * @return string
	 */
	private function numberToWords( $number ) {
		$words = array(
			1	 => __( 'one', 'wc-captcha' ),
			2	 => __( 'two', 'wc-captcha' ),
			3	 => __( 'three', 'wc-captcha' ),
			4	 => __( 'four', 'wc-captcha' ),
			5	 => __( 'five', 'wc-captcha' ),
			6	 => __( 'six', 'wc-captcha' ),
			7	 => __( 'seven', 'wc-captcha' ),
			8	 => __( 'eight', 'wc-captcha' ),
			9	 => __( 'nine', 'wc-captcha' ),
			10	 => __( 'ten', 'wc-captcha' ),
			11	 => __( 'eleven', 'wc-captcha' ),
			12	 => __( 'twelve', 'wc-captcha' ),
			13	 => __( 'thirteen', 'wc-captcha' ),
			14	 => __( 'fourteen', 'wc-captcha' ),
			15	 => __( 'fifteen', 'wc-captcha' ),
			16	 => __( 'sixteen', 'wc-captcha' ),
			17	 => __( 'seventeen', 'wc-captcha' ),
			18	 => __( 'eighteen', 'wc-captcha' ),
			19	 => __( 'nineteen', 'wc-captcha' ),
			20	 => __( 'twenty', 'wc-captcha' ),
			30	 => __( 'thirty', 'wc-captcha' ),
			40	 => __( 'forty', 'wc-captcha' ),
			50	 => __( 'fifty', 'wc-captcha' ),
			60	 => __( 'sixty', 'wc-captcha' ),
			70	 => __( 'seventy', 'wc-captcha' ),
			80	 => __( 'eighty', 'wc-captcha' ),
			90	 => __( 'ninety', 'wc-captcha' )
		);

		if ( isset( $words[$number] ) )
			return $words[$number];
		else {
			$reverse = false;

			switch ( get_bloginfo( 'language' ) ) {
				case 'de-DE':
					$spacer = 'und';
					$reverse = true;
					break;

				case 'nl-NL':
					$spacer = 'en';
					$reverse = true;
					break;

				case 'ru-RU':
				case 'pl-PL':
				case 'en-EN':
				default:
					$spacer = ' ';
			}

			$first = (int) (substr( $number, 0, 1 ) * 10);
			$second = (int) substr( $number, -1 );

			return ($reverse === false ? $words[$first] . $spacer . $words[$second] : $words[$second] . $spacer . $words[$first]);
		}
	}

	/**
	 * Generate captcha phrase.
	 * 
	 * @param string $form
	 * @return array
	 */
	public function generate_captcha_phrase( $form = '' ) {
		$ops = array(
			'addition'		 => '+',
			'subtraction'	 => '&#8722;',
			'multiplication' => '&#215;',
			'division'		 => '&#247;',
		);

		$operations = $groups = array();
		$input = '<input type="text" size="2" length="2" id="wc-input" class="wc-input" name="wc-value" value="" aria-required="true"/>';

		// available operations
		foreach ( Wc_Captcha()->options['general']['mathematical_operations'] as $operation => $enable ) {
			if ( $enable === true )
				$operations[] = $operation;
		}

		// available groups
		foreach ( Wc_Captcha()->options['general']['groups'] as $group => $enable ) {
			if ( $enable === true )
				$groups[] = $group;
		}

		// number of groups
		$ao = count( $groups );

		// operation
		$rnd_op = $operations[mt_rand( 0, count( $operations ) - 1 )];
		$number[3] = $ops[$rnd_op];

		// place where to put empty input
		$rnd_input = mt_rand( 0, 2 );

		// which random operation
		switch ( $rnd_op ) {
			case 'addition':
				if ( $rnd_input === 0 ) {
					$number[0] = mt_rand( 1, 10 );
					$number[1] = mt_rand( 1, 89 );
				} elseif ( $rnd_input === 1 ) {
					$number[0] = mt_rand( 1, 89 );
					$number[1] = mt_rand( 1, 10 );
				} elseif ( $rnd_input === 2 ) {
					$number[0] = mt_rand( 1, 9 );
					$number[1] = mt_rand( 1, 10 - $number[0] );
				}

				$number[2] = $number[0] + $number[1];
				break;

			case 'subtraction':
				if ( $rnd_input === 0 ) {
					$number[0] = mt_rand( 2, 10 );
					$number[1] = mt_rand( 1, $number[0] - 1 );
				} elseif ( $rnd_input === 1 ) {
					$number[0] = mt_rand( 11, 99 );
					$number[1] = mt_rand( 1, 10 );
				} elseif ( $rnd_input === 2 ) {
					$number[0] = mt_rand( 11, 99 );
					$number[1] = mt_rand( $number[0] - 10, $number[0] - 1 );
				}

				$number[2] = $number[0] - $number[1];
				break;

			case 'multiplication':
				if ( $rnd_input === 0 ) {
					$number[0] = mt_rand( 1, 10 );
					$number[1] = mt_rand( 1, 9 );
				} elseif ( $rnd_input === 1 ) {
					$number[0] = mt_rand( 1, 9 );
					$number[1] = mt_rand( 1, 10 );
				} elseif ( $rnd_input === 2 ) {
					$number[0] = mt_rand( 1, 10 );
					$number[1] = ($number[0] > 5 ? 1 : ($number[0] === 4 && $number[0] === 5 ? mt_rand( 1, 2 ) : ($number[0] === 3 ? mt_rand( 1, 3 ) : ($number[0] === 2 ? mt_rand( 1, 5 ) : mt_rand( 1, 10 )))));
				}

				$number[2] = $number[0] * $number[1];
				break;

			case 'division':
				$divide = array( 1 => 99, 2 => 49, 3 => 33, 4 => 24, 5 => 19, 6 => 16, 7 => 14, 8 => 12, 9 => 11, 10 => 9 );

				if ( $rnd_input === 0 ) {
					$divide = array( 2 => array( 1, 2 ), 3 => array( 1, 3 ), 4 => array( 1, 2, 4 ), 5 => array( 1, 5 ), 6 => array( 1, 2, 3, 6 ), 7 => array( 1, 7 ), 8 => array( 1, 2, 4, 8 ), 9 => array( 1, 3, 9 ), 10 => array( 1, 2, 5, 10 ) );
					$number[0] = mt_rand( 2, 10 );
					$number[1] = $divide[$number[0]][mt_rand( 0, count( $divide[$number[0]] ) - 1 )];
				} elseif ( $rnd_input === 1 ) {
					$number[1] = mt_rand( 1, 10 );
					$number[0] = $number[1] * mt_rand( 1, $divide[$number[1]] );
				} elseif ( $rnd_input === 2 ) {
					$number[2] = mt_rand( 1, 10 );
					$number[0] = $number[2] * mt_rand( 1, $divide[$number[2]] );
					$number[1] = (int) ($number[0] / $number[2]);
				}

				if ( ! isset( $number[2] ) )
					$number[2] = (int) ($number[0] / $number[1]);

				break;
		}

		// words
		if ( $ao === 1 && $groups[0] === 'words' ) {
			if ( $rnd_input === 0 ) {
				$number[1] = $this->numberToWords( $number[1] );
				$number[2] = $this->numberToWords( $number[2] );
			} elseif ( $rnd_input === 1 ) {
				$number[0] = $this->numberToWords( $number[0] );
				$number[2] = $this->numberToWords( $number[2] );
			} elseif ( $rnd_input === 2 ) {
				$number[0] = $this->numberToWords( $number[0] );
				$number[1] = $this->numberToWords( $number[1] );
			}
		}
		// numbers and words
		elseif ( $ao === 2 ) {
			if ( $rnd_input === 0 ) {
				if ( mt_rand( 1, 2 ) === 2 ) {
					$number[1] = $this->numberToWords( $number[1] );
					$number[2] = $this->numberToWords( $number[2] );
				} else
					$number[$tmp = mt_rand( 1, 2 )] = $this->numberToWords( $number[$tmp] );
			}
			elseif ( $rnd_input === 1 ) {
				if ( mt_rand( 1, 2 ) === 2 ) {
					$number[0] = $this->numberToWords( $number[0] );
					$number[2] = $this->numberToWords( $number[2] );
				} else
					$number[$tmp = array_rand( array( 0 => 0, 2 => 2 ), 1 )] = $this->numberToWords( $number[$tmp] );
			}
			elseif ( $rnd_input === 2 ) {
				if ( mt_rand( 1, 2 ) === 2 ) {
					$number[0] = $this->numberToWords( $number[0] );
					$number[1] = $this->numberToWords( $number[1] );
				} else
					$number[$tmp = mt_rand( 0, 1 )] = $this->numberToWords( $number[$tmp] );
			}
		}

		if ( in_array( $form, array( 'default', 'bbpress' ), true ) ) {
			// position of empty input
			if ( $rnd_input === 0 )
				$return = $input . ' ' . $number[3] . ' ' . $this->encode_operation( $number[1] ) . ' = ' . $this->encode_operation( $number[2] );
			elseif ( $rnd_input === 1 )
				$return = $this->encode_operation( $number[0] ) . ' ' . $number[3] . ' ' . $input . ' = ' . $this->encode_operation( $number[2] );
			elseif ( $rnd_input === 2 )
				$return = $this->encode_operation( $number[0] ) . ' ' . $number[3] . ' ' . $this->encode_operation( $number[1] ) . ' = ' . $input;

			$transient_name = ($form === 'bbpress' ? 'bbp' : 'wc');
			$session_id = Wc_Captcha()->cookie_session->session_ids['default'];
		}
		elseif ( $form === 'cf7' ) {
			$return = array();

			if ( $rnd_input === 0 ) {
				$return['input'] = 1;
				$return[2] = ' ' . $number[3] . ' ' . $this->encode_operation( $number[1] ) . ' = ';
				$return[3] = $this->encode_operation( $number[2] );
			} elseif ( $rnd_input === 1 ) {
				$return[1] = $this->encode_operation( $number[0] ) . ' ' . $number[3] . ' ';
				$return['input'] = 2;
				$return[3] = ' = ' . $this->encode_operation( $number[2] );
			} elseif ( $rnd_input === 2 ) {
				$return[1] = $this->encode_operation( $number[0] ) . ' ' . $number[3] . ' ';
				$return[2] = $this->encode_operation( $number[1] ) . ' = ';
				$return['input'] = 3;
			}

			$transient_name = 'cf7';
			$session_id = Wc_Captcha()->cookie_session->session_ids['multi'][$this->session_number ++];
		}

		set_transient( $transient_name . '_' . $session_id, sha1( AUTH_KEY . $number[$rnd_input] . $session_id, false ), apply_filters( 'Wc_Captcha_time', Wc_Captcha()->options['general']['time'] ) );

		return $return;
	}

	/**
	 * FLush rewrite rules.
	 */
	public function flush_rewrites() {
		if ( Wc_Captcha()->options['general']['flush_rules'] ) {
			global $wp_rewrite;

			$wp_rewrite->flush_rules();

			Wc_Captcha()->options['general']['flush_rules'] = false;
			update_option( 'Wc_Captcha_options', Wc_Captcha()->options['general'] );
		}
	}

	/**
	 * Block direct comments.
	 * 
	 * @param string $rules
	 * @return string
	 */
	public function block_direct_comments( $rules ) {
		if ( Wc_Captcha()->options['general']['block_direct_comments'] ) {
			$new_rules = <<<EOT
\n# BEGIN WC Captcha
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_METHOD} POST
RewriteCond %{REQUEST_URI} .wp-comments-post.php*
RewriteCond %{HTTP_REFERER} !.*{$_SERVER['HTTP_HOST']}.* [OR]
RewriteCond %{HTTP_USER_AGENT} ^$
RewriteRule (.*) ^http://%{REMOTE_ADDR}/$ [R=301,L]
</IfModule>
# END WC Captcha\n\n
EOT;

			return $new_rules . $rules;
		}

		return $rules;
	}

}