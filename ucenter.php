<?php
/**
 * @package Ucenter
 * @author ychen
 * @version 0.2
 */
/*
Plugin Name: Ucenter
Plugin URI: http://chenyundong.com
Description: This plugin integrate wordpress into ucenter and make wordpress can work with ucenter supported platforms.
Author: ychen
Version: 0.2
Author URI: http://chenyundong.com
*/

define( 'UCENTER_DEFINE_SETTING_NAME', 'plugin_ucenter_define_settings' );
define( 'UCENTER_INTEGRATION_SETTING_NAME', 'plugin_ucenter_integration_settings' );

class Ucenter_Integration {
	var $define_settings;
	var $integration_settings;
	var $sync_login_cookie;
	var $sync_logout_cookie;

	function Ucenter_Integration() {
		__construct();
	}

	function __construct() {
		$this->define_settings = get_option( UCENTER_DEFINE_SETTING_NAME );
		$this->integration_settings = get_option( UCENTER_INTEGRATION_SETTING_NAME );

		// Load dialect
		add_action( 'init', array( &$this, 'load_dialect' ) );
		add_action( 'init', array( &$this, 'clear_cookie' ) );
		// Give notice
		add_action( 'admin_notices', array( &$this, 'notices' ) );
		// Add Admin menu for ucenter integration
		add_action( 'admin_menu', array( &$this, 'add_menu_page' ) );
		// Clean up when deactivate
		add_action( 'deactivated_plugin', array( &$this, 'deactivated_plugin' ) );
		// Activate
		add_action( 'activated_plugin', array( &$this, 'activated_plugin' ) );

		if ( file_exists( dirname( __FILE__ ) . '/config.php' ) ) {

			require_once dirname( __FILE__ ) . '/config.php';
			if( !defined( 'UC_KEY' ) ) return;
			if ( empty( $this->define_settings )) {
				if ( defined( 'UC_CONNECT' ) ) {
					$this->define_settings['UC_CONNECT'] = UC_CONNECT;
				}
				if ( defined( 'UC_DBHOST' ) ) {
					$this->define_settings['UC_DBHOST'] = UC_DBHOST;
				}
				if ( defined( 'UC_DBUSER' ) ) {
					$this->define_settings['UC_DBUSER'] = UC_DBUSER;
				}
				if ( defined( 'UC_DBPW' ) ) {
					$this->define_settings['UC_DBPW'] = UC_DBPW;
				}
				if ( defined( 'UC_DBNAME' ) ) {
					$this->define_settings['UC_DBNAME'] = UC_DBNAME;
				}
				if ( defined( 'UC_DBCHARSET' ) ) {
					$this->define_settings['UC_DBCHARSET'] = UC_DBCHARSET;
				}
				if ( defined( 'UC_DBTABLEPRE' ) ) {
					$this->define_settings['UC_DBTABLEPRE'] = UC_DBTABLEPRE;
				}
				if ( defined( 'UC_DBCONNECT' ) ) {
					$this->define_settings['UC_DBCONNECT'] = UC_DBCONNECT;
				}
				if ( defined( 'UC_KEY' ) ) {
					$this->define_settings['UC_KEY'] = UC_KEY;
				}
				if ( defined( 'UC_API' ) ) {
					$this->define_settings['UC_API'] = UC_API;
				}
				if ( defined( 'UC_CHARSET' ) ) {
					$this->define_settings['UC_CHARSET'] = UC_CHARSET;
				}
				if ( defined( 'UC_IP' ) ) {
					$this->define_settings['UC_IP'] = UC_IP;
				}
				if ( defined( 'UC_APPID' ) ) {
					$this->define_settings['UC_APPID'] = UC_APPID;
				}
				if ( defined( 'UC_PPP' ) ) {
					$this->define_settings['UC_PPP'] = UC_PPP;
				}
				update_option( UCENTER_DEFINE_SETTING_NAME, $this->define_settings );
			}

			require_once dirname( __FILE__ ) . '/client/client.php';
			require_once( ABSPATH . WPINC . '/registration.php' );
			
			// Add ucenter authenticate
			add_filter( 'authenticate', array( &$this, 'authenticate_username_password' ), 40, 3 );

			// Echo sync login scripts to wp_head or admin_head
			add_action( 'wp_head', array( &$this, 'sync_login' ) );
			add_action( 'admin_head', array( &$this, 'sync_login' ) );
			add_action( 'login_head', array( &$this, 'sync_login' ) );

			// Add ucenter logout cookie
			add_action( 'wp_logout', array( &$this, 'sync_logout_cookie' ) );

			// Echo ucenter logout scripts
			add_action( 'wp_head', array( &$this, 'sync_logout' ) );
			add_action( 'admin_head', array( &$this, 'sync_logout' ) );
			add_action( 'login_head', array( &$this, 'sync_logout' ) );

			// Delete ucenter user when delete wordpress user
			add_action( 'delete_user', array( &$this, 'delete_user' ) );

			// Insert ucenter user when insert wordpress user
			add_filter( 'ucenter_register_user', array( &$this, 'register_user' ), 10, 3 );

			// Add ucenter registration errors
			add_filter( 'registration_errors', array( &$this, 'registration_errors' ), 10, 3 );
			
			// Update ucenter user when update wordpress user
			add_action( 'user_profile_update_errors', array( &$this, 'update_user' ), 40, 3 );
		}
	}

	function debug( $msg ) {
		if ( is_writable( dirname( __FILE__ ) ) ) {
			$file_name = dirname( __FILE__ ) . '/debug.log';
			$handle = fopen( $file_name, 'a' );
			fwrite( $handle, $msg . "\n" );
			fclose( $handle );
		}
	}

	function hack_core( $action = 'hack' ) {
		if ( is_writable( ABSPATH . WPINC ) ) {
			$file_name = ABSPATH . WPINC . '/registration.php';
			$handle = fopen( $file_name . '.php', 'w' );
			$content = file( $file_name );
			foreach ( $content as $line ) {
				if ( false !== strpos( $line, 'function wp_create_user(' ) ) {
					if ( $action == 'hack' && false === strpos( $line, 'ucenter' ) ) 
						$line = trim( $line ) . '$success = apply_filters( "ucenter_register_user", $username, $password, $email ); if ( !$success ) return;' . "\n";
					elseif ( $action == 'remove' )
						$line = substr( $line, 0, strpos( $line, '{' ) + 1 ) . "\n";
				}
				fwrite( $handle, $line );
			}
			fclose( $handle );
			unlink( $file_name );
			rename( $file_name . '.php', $file_name );
		}
	}

	function deactivated_plugin( $plugin ) {
		if ( $plugin == plugin_basename(__FILE__) ) {
			$this->hack_core( 'remove' );
			delete_option( UCENTER_DEFINE_SETTING_NAME );
			delete_option( UCENTER_INTEGRATION_SETTING_NAME );
			if ( is_writable( dirname( __FILE__ ) ) ) {
				unlink( dirname( __FILE__ ) . '/config.php' );
			}
		}
	}

	function load_dialect() {
		$plugin_dir = basename( dirname( __FILE__ ) );
		load_plugin_textdomain( 'ucenter', 'wp-content/plugins/' . $plugin_dir, $plugin_dir . '/language' );
	}

	function notices() {
		if ( !current_user_can( 'manage_options' )  ) return;
		if ( !file_exists( dirname( __FILE__ ) . '/config.php' ) ) {
			echo "
			<div class='updated'><p>" . sprintf( __( 'Ucenter Integration: Ucenter integration plugin is active now. But you must finish all related <a href="%s">settings</a> to make it work correctly.', 'ucenter' ), "admin.php?page=ucenter-integration" ) . "</p></div>
			";
		} else {
			require_once dirname( __FILE__ ) . '/config.php' ;
			if ( !defined( 'UC_KEY' ) || UC_KEY === '' ) {
				echo "
				<div class='updated'><p>" . sprintf( __( 'Ucenter Integration: Ucenter integration plugin is active now. But you must finish all related <a href="%s">settings</a> to make it work correctly.', 'ucenter' ), "admin.php?page=ucenter-integration" ) . "</p></div>
				";
			}
		}

		if ( $this->integration_settings['hack_core'] && !is_writable( ABSPATH . WPINC ) ) {
			echo "
			<div class='updated'><p><strong>" . sprintf( __( 'Ucenter Integration: You have enabled hack core flag but %s is not writable.', 'ucenter' ), ABSPATH . WPINC ) . "</p></div>
			";
		}

		if ( !is_writable( dirname( __FILE__ ) ) ) {
			echo "
			<div class='updated'><p><strong>" . sprintf( __( 'Ucenter Integration: %s is not writable.', 'ucenter' ), dirname( __FILE__ ) ) . "</p></div>
			";
		}
	}

	function authenticate_username_password( $user, $username, $password ) {
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
					if ( $this->integration_settings['password_override'] ) {
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

	function sync_login() {
		if ( !empty( $this->sync_login_cookie ) ) {
			echo $this->sync_login_cookie;
			$this->sync_login_cookie = '';
		}
	}

	function sync_logout_cookie() {
		setcookie( 'sync_logout', uc_user_synlogout(), 0, '/' );
	}

	function sync_logout() {
		if ( !empty( $this->sync_logout_cookie ) ) {
			echo $this->sync_logout_cookie;
			$this->sync_logout_cookie = '';
		}
	}

	function clear_cookie() {
		$this->sync_login_cookie = stripcslashes( $_COOKIE['sync_login'] );
		$this->sync_logout_cookie = stripcslashes( $_COOKIE['sync_logout'] );
		setcookie( 'sync_login', '', 0, '/' );
		setcookie( 'sync_logout', '', 0, '/' );
	}

	function delete_user( $user_id ) {
		$user_data = get_userdata( $user_id );
		list( $uid, $user_name, $email ) = uc_get_user( $user_data->user_login );
		uc_user_delete( $uid );
	}

	function register_user( $username, $password, $email ) {
		$uid = uc_user_register( $username, $password, $email );
		if ( $uid > 0 )
			return true;
		else
			return false;
	}

	function registration_errors( $errors, $user_login, $user_email ) {
		if( uc_user_checkname( $user_login ) < 0 ) {
			$errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.', 'ucenter' ) );
		}

		if( uc_user_checkemail( $user_email ) < 0 ) {
			$errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.', 'ucenter' ) );
		}
		return $errors;
	}

	function update_user( $error, $update, $user ) {
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

	function add_menu_page() {
			add_menu_page( 'Ucenter', __( 'Ucenter', 'ucenter' ), 'administrator', 'ucenter-integration', '' );

			add_submenu_page( 'ucenter-integration', __( 'Introduction', 'ucenter' ) , __( 'Introduction', 'ucenter' ), 'administrator', 'ucenter-integration', array( &$this, 'submenu_introduction' ) );

			add_submenu_page( 'ucenter-integration', __( 'Define Settings', 'ucenter' ) , __( 'Define Settings', 'ucenter' ), 'administrator', 'define-settings', array( &$this, 'submenu_define_settings' ) );

			add_submenu_page( 'ucenter-integration', __( 'Integration Settings', 'ucenter' ) , __( 'Integration Settings', 'ucenter' ), 'administrator', 'integration-settings', array( &$this, 'submenu_integration_settings' ) );
	}

	function submenu_introduction() {
		$plugin_dir = basename( dirname( __FILE__ ) );
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Ucenter Introduction', 'ucenter' ) . '</h2>';
		_e( '<p>Ucenter Integration Plugin will help you integrate wordpress with ucenter supported platforms. If you find any bug, please email to nkucyd at gmail.com. Your help is appreciated. </p>', 'ucenter' );
		_e( 'You should follow there steps to make plugin work well:', 'ucenter' );
		printf( __( "<br>1. Login ucenter to add wordpress as app. NOTICE: you should fill APP'S URL with http://yourdomain/wp-content/plugins/%s<br>", 'ucenter' ), $plugin_dir );
		_e( '2. Finish define setting accoding to ucenter.<br>', 'ucenter' );
		_e( '3. Change integration setting according to your preferrence.', 'ucenter' );
		echo '</div>';
	}

	function submenu_define_settings() {
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

	function submenu_integration_settings() {
		$page_options = 'password_override,hack_core';
		$options = get_option( UCENTER_INTEGRATION_SETTING_NAME );

		if ( $_POST['page_options'] )
			$post_options = explode( ',', stripslashes( $_POST['page_options'] ) );

		if ( $post_options ) {
			foreach ( $post_options as $post_option ) {
				$post_option = trim( $post_option );
				$value = isset( $_POST[$post_option] ) ? trim( $_POST[$post_option] ) : false;
				$options[$post_option] = $value;
				if ( $post_option == 'hack_core' ) {
					if ( $value ) 
						$this->hack_core();
					else
						$this->hack_core( 'remove' );
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
		<input type="submit" name="Submit" value="<?php _e( 'Update Options', 'ucenter' ) ?>" />
		</p>

	</form>
	</div>
	<?php
	}
}

$ucenter_integration = new Ucenter_Integration;

?>
