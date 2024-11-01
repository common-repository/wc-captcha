<?php
/**
 * A module for [wpcaptcha]
 */

// shortcode handler
add_action( 'init', 'wpcf7_add_shortcode_wpcaptcha', 5 );

function wpcf7_add_shortcode_wpcaptcha() {
	wpcf7_add_form_tag( 'wpcaptcha', 'wpcf7_wpcaptcha_shortcode_handler', true );
}

function wpcf7_wpcaptcha_shortcode_handler( $tag ) {
	if ( ! is_user_logged_in() || (is_user_logged_in() && ! WC_Captcha()->options['general']['hide_for_logged_users']) ) {
		$tag = new WPCF7_FormTag( $tag );

		if ( empty( $tag->name ) )
			return '';

		$validation_error = wpcf7_get_validation_error( $tag->name );
		$class = wpcf7_form_controls_class( $tag->type );

		if ( $validation_error )
			$class .= ' wpcf7-not-valid';

		$atts = array();
		$atts['size'] = 2;
		$atts['maxlength'] = 2;
		$atts['class'] = $tag->get_class_option( $class );
		$atts['id'] = $tag->get_option( 'id', 'id', true );
		$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );
		$atts['aria-required'] = 'true';
		$atts['type'] = 'text';
		$atts['name'] = $tag->name;
		$atts['value'] = '';
		$atts = wpcf7_format_atts( $atts );

		$mc_form = WC_Captcha()->core->generate_captcha_phrase( 'cf7' );
		$mc_form[$mc_form['input']] = '<input %2$s />';

		$WC_Captcha_title = apply_filters( 'WC_Captcha_title', WC_Captcha()->options['general']['title'] );

		return sprintf( ((empty( $WC_Captcha_title )) ? '' : $WC_Captcha_title) . ' <span class="wpcf7-form-control-wrap %1$s">' . $mc_form[1] . $mc_form[2] . $mc_form[3] . '%3$s</span><input type="hidden" value="' . (WC_Captcha()->core->session_number - 1) . '" name="' . $tag->name . '-sn" />', $tag->name, $atts, $validation_error );
	}
}

// validation
add_filter( 'wpcf7_validate_wpcaptcha', 'wpcf7_wpcaptcha_validation_filter', 10, 2 );

function wpcf7_wpcaptcha_validation_filter( $result, $tag ) {
	$tag = new WPCF7_FormTag( $tag );
	$name = $tag->name;

	if ( ! is_admin() && isset( $_POST[$name] ) ) {
		$cf7_version = get_option( 'wpcf7', '1.0.0' );

		if ( is_array( $cf7_version ) && isset( $cf7_version['version'] ) )
			$cf7_version = $cf7_version['version'];

		if ( $_POST[$name] !== '' ) {
			$session_id = (isset( $_POST[$name . '-sn'] ) && $_POST[$name . '-sn'] !== '' ? WC_Captcha()->cookie_session->session_ids['multi'][$_POST[$name . '-sn']] : '');

			if ( $session_id !== '' && get_transient( 'cf7_' . $session_id ) !== false ) {
				if ( strcmp( get_transient( 'cf7_' . $session_id ), sha1( AUTH_KEY . $_POST[$name] . $session_id, false ) ) !== 0 ) {
					if ( version_compare( $cf7_version, '4.1.0', '>=' ) )
						$result->invalidate( $tag, wpcf7_get_message( 'wrong_wpcaptcha' ) );
					else {
						$result['valid'] = false;
						$result['reason'][$name] = wpcf7_get_message( 'wrong_wpcaptcha' );
					}
				}
			} else {
				if ( version_compare( $cf7_version, '4.1.0', '>=' ) )
					$result->invalidate( $tag, wpcf7_get_message( 'time_wpcaptcha' ) );
				else {
					$result['valid'] = false;
					$result['reason'][$name] = wpcf7_get_message( 'time_wpcaptcha' );
				}
			}
		} else {
			if ( version_compare( $cf7_version, '4.1.0', '>=' ) )
				$result->invalidate( $tag, wpcf7_get_message( 'fill_wpcaptcha' ) );
			else {
				$result['valid'] = false;
				$result['reason'][$name] = wpcf7_get_message( 'fill_wpcaptcha' );
			}
		}
	}

	return $result;
}

// messages
add_filter( 'wpcf7_messages', 'wpcf7_wpcaptcha_messages' );

function wpcf7_wpcaptcha_messages( $messages ) {
	return array_merge(
		$messages, array(
		'wrong_wpcaptcha'	 => array(
			'description'	 => __( 'This is Invalid captcha value.', 'wc-captcha' ),
			'default'		 => WC_Captcha()->core->error_messages['wrong']
		),
		'fill_wpcaptcha'	 => array(
			'description'	 => __( 'Please enter captcha value.', 'wc-captcha' ),
			'default'		 => WC_Captcha()->core->error_messages['fill']
		),
		'time_wpcaptcha'	 => array(
			'description'	 => __( 'Captcha time expired. Please try again', 'wc-captcha' ),
			'default'		 => WC_Captcha()->core->error_messages['time']
		)
		)
	);
}

// warning message
add_action( 'wpcf7_admin_notices', 'wpcf7_wpcaptcha_display_warning_message' );

function wpcf7_wpcaptcha_display_warning_message() {
	if ( empty( $_GET['post'] ) || ! ($contact_form = wpcf7_contact_form( $_GET['post'] )) )
		return;

	$has_tags = (bool) $contact_form->form_scan_shortcode( array( 'type' => array( 'wpcaptcha' ) ) );

	if ( ! $has_tags )
		return;
}

// tag generator
add_action( 'admin_init', 'wpcf7_add_tag_generator_wpcaptcha', 45 );

function wpcf7_add_tag_generator_wpcaptcha() {
	if ( ! function_exists( 'wpcf7_add_tag_generator' ) )
		return;

	wpcf7_add_tag_generator( 'wpcaptcha', __( 'WC Captcha', 'wc-captcha' ), 'wpcf7-wpcaptcha', 'wpcf7_tg_pane_wpcaptcha' );
}

function wpcf7_tg_pane_wpcaptcha( $contact_form ) {
	echo '
	<div class="control-box">
		<fieldset>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="tag-generator-panel-wpcaptcha-name">' . esc_html__( 'Name', 'contact-form-7' ) . '</label>
						</th>
						<td>
							<input type="text" name="name" class="tg-name oneline" id="tag-generator-panel-wpcaptcha-name" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tag-generator-panel-wpcaptcha-id">' . esc_html__( 'Id attribute', 'contact-form-7' ) . '</label>
						</th>
						<td>
							<input type="text" name="id" class="idvalue oneline option" id="tag-generator-panel-wpcaptcha-id" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tag-generator-panel-wpcaptcha-class">' . esc_html__( 'Class attribute', 'contact-form-7' ) . '</label>
						</th>
						<td>
							<input type="text" name="class" class="classvalue oneline option" id="tag-generator-panel-wpcaptcha-class" />
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>
	</div>
	<div class="insert-box">
		<input type="text" name="wpcaptcha" class="tag code" readonly="readonly" onfocus="this.select();">
		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="' . esc_attr__( 'Insert Tag', 'contact-form-7' ) . '">
		</div>
		<br class="clear">
	</div>';
}