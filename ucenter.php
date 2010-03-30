<?php
/**
 * @package Ucenter
 * @author ychen
 * @version 0.1
 */
/*
Plugin Name: Ucenter
Plugin URI: http://chenyundong.com
Description: This plugin integrate wordpress into ucenter and make wordpress can work with ucenter supported platforms.
Author: ychen
Version: 0.1
Author URI: http://chenyundong.com
*/

define( 'UCENTER_DEFINE_SETTING_NAME', 'plugin_ucenter_define_settings' );
define( 'UCENTER_INTEGRATION_SETTING_NAME', 'plugin_ucenter_integration_settings' );

$define_settings = get_option( UCENTER_DEFINE_SETTING_NAME );
$integration_settings = get_option( UCENTER_INTEGRATION_SETTING_NAME );

function ucenter_load_lang() {
	$plugin_dir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'ucenter', 'wp-content/plugins/' . $plugin_dir, $plugin_dir . '/language');
}
add_action('init', 'ucenter_load_lang');

function ucenter_notices() {
	global $define_settings, $integration_settings;

	if ( ( empty( $define_settings['UC_DBHOST'] ) || empty( $define_settings['UC_DBUSER'] ) || empty( $define_settings['UC_KEY'] ) ) || ! file_exists( dirname( __FILE__ ) . '/config.php' ) )
		echo "
		<div class='updated fade'><p>" . sprintf( __( 'Ucenter Integration: Ucenter integration plugin is active now. But you must finish all related <a href="%s">settings</a> to make it work correctly.', 'ucenter' ), "admin.php?page=ucenter-integration" ) . "</p></div>
		";

	if ( $integration_settings['hack_core'] && !is_writable( ABSPATH . WPINC ) ) {
		echo "
		<div class='updated fade'><p><strong>" . sprintf( __( 'Ucenter Integration: You have enabled hack core flag but %s is not writable.', 'ucenter' ), ABSPATH . WPINC ) . "</p></div>
		";
	}

	if ( !is_writable( dirname( __FILE__ ) ) ) {
		echo "
		<div class='updated fade'><p><strong>" . sprintf( __( 'Ucenter Integration: %s is not writable.', 'ucenter' ), dirname( __FILE__ ) ) . "</p></div>
		";
	}
}
// Add ucenter notices
add_action( 'admin_notices', 'ucenter_notices' );

if ( !empty( $define_settings['UC_DBHOST'] ) && !empty( $define_settings['UC_DBUSER'] ) && !empty( $define_settings['UC_KEY'] ) && file_exists( dirname( __FILE__ ) . '/config.php' ) ) {
	require_once dirname( __FILE__ ) . '/config.php';
	require_once dirname( __FILE__ ) . '/client/client.php';
	require_once( ABSPATH . WPINC . '/registration.php' );

	function ucenter_authenticate_username_password( $user, $username, $password ) {
		$options = get_option( DEFINE_SETTING_NAME );

		if ( is_a( $user, 'WP_User' ) ) {

			if ( !uc_get_user( $user->user_login ) ) {
				$uid = uc_user_register( $username, $password, $user->user_email );

				if( $uid > 0 )
					return $user;
				else
					new WP_Error( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you in ucenter... please contact the <a href="mailto:%s">webmaster</a> !', 'ucenter' ), get_option( 'admin_email' ) ) );
			}
		}

		if ( empty( $username ) || empty( $password ) ) {
			$error = new WP_Error();

			if ( empty( $username ) )
				$error->add( 'empty_username', __( '<strong>ERROR</strong>: The username field is empty.', 'ucenter' ) );

			if ( empty( $password ) )
				$error->add( 'empty_password', __( '<strong>ERROR</strong>: The password field is empty.', 'ucenter' ) );

			return $error;
		}

		list( $uid, $_, $_, $email ) = uc_user_login( $username, $password );

		$errors = new WP_Error();
		if ( $uid > 0 ) {
			// success login ucenter
			$userdata = get_userdatabylogin( $username );
			$user_id = $userdata->ID;

			if( !$userdata ) {
				// if user does not exist, create it
				$user_id = wp_create_user( $username, $password, $email );

				if ( !$user_id )
					$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you in wordpress... please contact the <a href="mailto:%s">webmaster</a> !', 'ucenter' ), get_option( 'admin_email' ) ) );

			} elseif ( !wp_check_password( $password, $userdata->user_pass, $userdata->ID ) ) {
					// if user exists
					if ( $options['override'] ) {
						// if override, update wordpress user's password to ucenter's password
						 $userdata->user_pass = wp_hash_password( $password );
						 $user_id = wp_update_user( get_object_vars( $userdata ) );

					} else {
						// if not override, throw an error
						$errors->add( 'password_confliction', sprintf( __( '<strong>ERROR</strong>: User password conflict between wordpress and ucenter. please contact the <a href="mailto:%s">webmaster</a> !', 'ucenter' ), get_option( 'admin_email' ) ) );
					}
			}
		} elseif ( $uid == -1 ) {
			$errors->add( 'invalid_username', sprintf( __( '<strong>ERROR</strong>: Invalid username. <a href="%s" title="Password Lost and Found">Lost your password</a>?' ), site_url( 'wp-login.php?action=lostpassword', 'login' ) ) );
		} else {
			$errors->add( 'incorrect_password', sprintf( __( '<strong>ERROR</strong>: Incorrect password. <a href="%s" title="Password Lost and Found">Lost your password</a>?', 'ucenter' ), site_url( 'wp-login.php?action=lostpassword', 'login' ) ) );
		}
		if ( $errors->get_error_code() ) {
			return $errors;
		} else {
			setcookie( 'sync_login', uc_user_synlogin( $uid ), 0, '/' );
			return new WP_User( $user_id );
		}
	}
	// Add ucenter authenticate
	add_filter( 'authenticate', 'ucenter_authenticate_username_password', 40, 3 );

	function ucenter_sync_login() {
		if ( !empty( $_COOKIE['sync_login'] ) ) {
			echo stripcslashes( $_COOKIE['sync_login'] );
		}
	}
	// Add sync login scripts to wp_head or admin_head
	add_action( 'wp_head', 'ucenter_sync_login' );
	add_action( 'admin_head', 'ucenter_sync_login' );

	function ucenter_sync_logout_cookie() {
		setcookie( 'sync_logout', uc_user_synlogout(), 0, '/' );
	}
	// Add ucenter logout cookie
	add_action( 'wp_logout', 'ucenter_sync_logout_cookie' );

	function ucenter_sync_logout() {
		if ( !empty( $_COOKIE['sync_logout'] ) ) {
			echo stripcslashes( $_COOKIE['sync_logout'] );
		}
	}
	// Echo ucenter logout scripts
	add_action( 'login_head', 'ucenter_sync_logout' );

	function ucenter_delete_user( $user_id ) {
		$user_data = get_userdata( $user_id );
		list( $uid, $user_name, $email ) = uc_get_user( $user_data->user_login );
		uc_user_delete( $uid );
	}
	// Delete ucenter user when delete wordpress user
	add_action( 'delete_user', 'ucenter_delete_user' );

	function ucenter_register_user( $username, $password, $email ) {
		$uid = uc_user_register( $username, $password, $email );
		if ( $uid > 0 )
			return true;
		else
			return false;
	}
	// Insert ucenter user when insert wordpress user
	add_filter( 'ucenter_register_user', 'ucenter_register_user', 10, 3 );

	function ucenter_register_error( $errors, $user_login, $user_email ) {
		if( uc_user_checkname( $user_login ) < 0 ) {
			$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.', 'ucenter' ) );
		}

		if( uc_user_checkemail( $user_email ) < 0 ) {
			$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.', 'ucenter' ) );
		}
		return $errors;
	}
	add_filter( 'registration_errors', 'ucenter_register_error', 10, 3 );

	function ucenter_update_user( $error, $update, $user ) {
		if ( $update ) {
			if ( $data = uc_get_user( $user->user_login ) ) {
				$result = uc_user_edit( $user->user_login, '', $user->user_pass, $user->user_email, 1 );
				if ( $result < 0 )
					$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.', 'ucenter' ) );
			} else {
				$uid = uc_user_register( $user->user_login, $user->user_pass, $user->user_email );
				if ( $uid <= 0 ) {
					if ( $uid == -3 ) {
						$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.', 'ucenter' ) );
					} elseif ( $uid == -6 ) {
						$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.', 'ucenter' ) );
					} else {
						$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you in ucenter... please contact the <a href="mailto:%s">webmaster</a> !', 'ucenter' ), get_option( 'admin_email' ) ) );
					}
				}
			}
		} else {
			$uid = uc_user_register( $user->user_login, $user->user_pass, $user->user_email );
			if ( $uid <= 0 ) {
				if ( $uid == -3 ) {
					$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.', 'ucenter' ) );
				} elseif ( $uid == -6 ) {
					$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.', 'ucenter' ) );
				} else {
					$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you in ucenter... please contact the <a href="mailto:%s">webmaster</a> !', 'ucenter' ), get_option( 'admin_email' ) ) );
				}
			}
		}
	}
	// Update ucenter user when update wordpress user
	add_action( 'user_profile_update_errors', 'ucenter_update_user', 40, 3 );
}

// Add Admin menu for ucenter setting
add_action( 'admin_menu', 'ucenter_add_menu_page' );

function ucenter_add_menu_page() {
		add_menu_page( 'Ucenter Setting', 'Ucenter', 'administrator', 'ucenter-integration', '' );

		add_submenu_page( 'ucenter-integration', 'Introduction', 'Introduction', 'administrator', 'ucenter-integration', 'ucenter_submenu_introduction' );

		add_submenu_page( 'ucenter-integration', 'Define Settings', 'Define Settings', 'administrator', 'define-settings', 'ucenter_submenu_define_settings' );

		add_submenu_page( 'ucenter-integration', 'Integration Settings', 'Integration Settings', 'administrator', 'integration-settings', 'ucenter_submenu_integration_settings' );
}

function ucenter_submenu_introduction() {
	$plugin_dir = basename( dirname( __FILE__ ) );
	echo '<div class="wrap">';
	echo '<h2>' . __( 'Ucenter Introduction', 'ucenter' ) . '</h2>';
	_e( "<p>Ucenter Integration Plugin will help you integrate wordpress with ucenter supported platforms. If you find any bug, please email to nkucyd at gmail.com. Your help is appreciated. </p>
	You should follow there steps to make plugin work well:<br>
	1. Login ucenter to add wordpress as app. NOTICE: you should fill APP'S URL with http://yourdomain/wp-content/plugins/$plugin_dir<br>
	2. Finish define setting accoding to ucenter.<br>
	3. Change integration setting according to your preferrence.
	", 'ucenter' );
	echo '</div>';
}

function ucenter_submenu_define_settings() {
	$page_options = 'UC_CONNECT,UC_DBHOST,UC_DBUSER,UC_DBPW,UC_DBNAME,UC_DBCHARSET,UC_DBTABLEPRE,UC_DBCONNECT,UC_KEY,UC_API,UC_CHARSET,UC_IP,UC_APPID,UC_PPP';
	$options = get_option( UCENTER_DEFINE_SETTING_NAME );

	if ( $_POST['page_options'] )
		$post_options = explode( ',', stripslashes( $_POST['page_options'] ) );

	if ( $post_options ) {
		foreach ( $post_options as $post_option ) {
			$post_option = trim( $post_option );
			$value = isset( $_POST[$post_option] ) ? trim( $_POST[$post_option] ) : false;
			$options[$post_option] = $value;
		}
		update_option( UCENTER_DEFINE_SETTING_NAME, $options );
		$fp = fopen( dirname( __FILE__ ) . '/config.php', 'w' );
		fwrite( $fp, "<?php\n" );
		foreach ( $options as $k => $v ) {
			fwrite( $fp, "define('$k', '$v');\n" );
		}
		fwrite( $fp, "?>\n" );
		fclose( $fp );
?>
<div class="updated"><p><strong><?php _e('Options saved.', 'ucenter' ); ?></strong></p></div>
<?php
	}
	echo '<div class="wrap">';
	echo "<h2>" . __( 'Ucenter Define Settings', 'ucenter' ) . "</h2>";
?>
<form name="ucenter-setting" method="post" action="">
	<input type="hidden" name="page_options" value="<?php echo $page_options ?>">

	<table>
	<?php foreach ( explode( ',', stripslashes( $page_options ) ) as $option ):?>
		<tr>
			<td><?php echo $option ?> </td>
			<td><input type="text" name="<?php echo $option ?>" value="<?php echo $options[$option]; ?>" size="50"></td>
		</tr>
	<?php endforeach ?>
</table>

	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e( 'Update Options', 'ucenter' ) ?>" />
	</p>

</form>
</div>
<?php
}

function ucenter_submenu_integration_settings() {
	$page_options = 'password_override,hack_core';
	$options = get_option( UCENTER_INTEGRATION_SETTING_NAME );

	if ( $_POST['page_options'] )
		$post_options = explode( ',', stripslashes( $_POST['page_options'] ) );

	if ( $post_options ) {
		foreach ( $post_options as $post_option ) {
			$post_option = trim( $post_option );
			$value = isset( $_POST[$post_option] ) ? trim( $_POST[$post_option] ) : false;
			$options[$post_option] = $value;
			if ( $post_option == 'hack_core' && is_writable( ABSPATH . WPINC ) ) {
				$file_name = ABSPATH . WPINC . '/registration.php';
				$handle = fopen( $file_name . '.php', 'w' );
				$content = file( $file_name );
				foreach ( $content as $line ) {
					if ( false !== strpos( $line, 'function wp_create_user( $username, $password' ) ) {
						if ( $value && false === strpos( $line, 'ucenter' ) )
							$line = trim( $line ) . '$success = apply_filters( "ucenter_register_user", $username, $password, $email ); if ( !$success ) return;' . "\n";
						else
							$line = substr( $line, 0, strpos( $line, '{' ) + 1 ) . "\n";
					}
					fwrite( $handle, $line );
				}
				fclose( $handle );
				unlink( $file_name );
				rename( $file_name . '.php', $file_name );
			}
		}
		update_option( UCENTER_INTEGRATION_SETTING_NAME, $options );
?>
<div class="updated"><p><strong><?php _e( 'Options saved.', 'ucenter' ); ?></strong></p></div>
<?php
	}
	echo '<div class="wrap">';
	echo "<h2>" . __( 'Ucenter Integration Settings', 'ucenter' ) . "</h2>";
?>
<form name="ucenter-setting" method="post" action="">
	<input type="hidden" name="page_options" value="<?php echo $page_options ?>">

	<table>
		<tr>
			<td>Password Override</td>
			<td><input type="checkbox" name="password_override" value="1" <?php checked( '1', $options['password_override'] ); ?> /></td>
		</tr>
		<tr><td></td><td><?php _e( '<strong>RECOMMENDATION: Enable This Option.</strong> If enable this option, user\'s password in ucenter will override that in wordpress when encounter pair(user, password) confliction between ucenter and wordpress. If disable this option, confliction will make login fail.<strong><br >WARNINGS: OPERATION WHEN YOU CLEARLY UNDERSTAND ITS MEANING!</strong>', 'ucenter' ) ?></td></tr>

		<tr>
			<td>Hack Core</td>
			<td><input type="checkbox" name="hack_core" value="1" <?php checked( '1', $options['hack_core'] ); ?> /></td>
		</tr>
		<tr><td></td><td><?php printf( __( '<strong>RECOMMENDATION: Enable This Option.</strong> If enable this option, ucenter integration plugin will hack wp core file "%s" to add some filter that isn\'t supplied by offical wp in order to make plugin work with entire functions. If disable this option, changes of user infomation will not go into ucenter when register/add/edit user in wordpress.', 'ucenter' ), ABSPATH . WPINC . '/registration.php' ) ?></td></tr>

	</table>

	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e( 'Update Options', 'ucente' ) ?>" />
	</p>

</form>
</div>
<?php
}
?>
