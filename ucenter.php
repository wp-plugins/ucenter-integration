<?php
/**
 * @package Ucenter
 * @author ychen
 * @version 0.3.5
 */
/*
Plugin Name: Ucenter
Plugin URI: http://chenyundong.com
Description: 该插件可以把Wordpress集成到Ucenter当中以使Wordpress可以和Ucenter支持的平台协同工作。使用介绍：<a href="http://chenyundong.com/2010/04/wordpress-plugin-ucenter-integration-使用介绍/">http://chenyundong.com/2010/04/wordpress-plugin-ucenter-integration-使用介绍/</a> 免费版本从0.3.5版本停止维护。从0.3.5以后，来自Wordpress官网的任何更新仅仅是一个通知，除了提示有新版本之外没有任何代码的更新。如果你需要一个更多功能更好UI的支持版本，请购买收费版本。有关免费版捐赠以及收费版购买，请见: <a href="http://chenyundong.com/?p=368">http://chenyundong.com/?p=368</a>
Author: ychen
Version: 0.3.5
Author URI: http://chenyundong.com
*/

if ( !defined('UCENTER_DEFINE_SETTING_NAME') ) :
define('UCENTER_DEFINE_SETTING_NAME', 'plugin_ucenter_define_settings');
define('UCENTER_INTEGRATION_SETTING_NAME', 'plugin_ucenter_integration_settings');

add_filter( 'sanitize_user', 'ucenter_sanitize_user', 3, 3 );
function ucenter_sanitize_user( $username, $raw_username, $strict ) {
	$username = $raw_username;
	$username = wp_strip_all_tags( $username );

	// Kill octets
	$username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );
	$username = preg_replace( '/&.+?;/', '', $username ); // Kill entities

	// If strict, reduce to ASCII and chinese for max portability.
	if ( $strict )
		$username = preg_replace( '|[^a-z0-9 _.\-@\x80-\xFF]|i', '', $username );

	$username = trim( $username );
	// Consolidate contiguous whitespace
	$username = preg_replace( '|\s+|', ' ', $username );

	return $username;
}

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

		if ( !file_exists( dirname( __FILE__ ) . '/config.php' ) && !empty( $this->define_settings ) ) {
			$fp = fopen( dirname( __FILE__ ) . '/config.php', 'w' );
			fwrite( $fp, "<?php\n" );
			foreach ( $this->define_settings as $k => $v ) {
				fwrite( $fp, "define('$k', '$v');\n" );
			}
			fwrite( $fp, "?>\n" );
			fclose( $fp );
		}

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
		// Load css
		add_action( 'admin_head', array( &$this, 'load_css' ) );

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

			// Add Admin menu for ucenter integration
			add_action( 'admin_menu', array( &$this, 'add_user_submenu_page' ) );

			// Add hook for comment credit
			if ( $this->integration_settings['ucenter_enable_credit'] )
				add_action( 'wp_insert_comment', array ( &$this, 'comment_credit' ), 30, 2 );

			// Use costomize icon
			if ( $this->integration_settings['ucenter_enable_customize_icon'] )
				add_filter( 'get_avatar', array( &$this, 'get_avatar' ), 100, 5);
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

	function load_css() {
		echo "
		<style type='text/css'>
		#icon_list {
			float:left;
			border-right: 2px dotted;
			width:220px;

		}
		#icon_show {
			float:left;
			margin:10px;
		}
		.ucenter-ul li {
			float:left;
			margin-left:10px;
		}
		.ucenter-ul .current {
			border: 1px solid;
			padding:5px;
		}
		</style>";
	}

	function comment_credit( $id, $comment ) {
		$credit = get_usermeta( $comment->user_id, 'ucenter_credit' );
		$credit += $this->integration_settings['ucenter_credit_per_comment'];
		update_usermeta( $comment->user_id, 'ucenter_credit', $credit );
	}

	function get_avatar( $avatar, $id_or_email, $size, $default, $alt ) {
		$user_login = '';
		if ( is_numeric( $id_or_email ) ) {
			$id = (int) $id_or_email;
			$user = get_userdata( $id );
			if ( $user )
				$user_login = $user->user_login;
		} elseif ( is_object( $id_or_email ) ) {
			if ( isset( $id_or_email->comment_type ) && '' != $id_or_email->comment_type && 'comment' != $id_or_email->comment_type )
				return false;

			if ( !empty( $id_or_email->user_id ) ) {
				$id = (int) $id_or_email->user_id;
				$user = get_userdata( $id );
				if ( $user )
					$user_login = $user->user_login;
			}
		}
		list( $uid, $_, $_ ) = uc_get_user( $user_login );
		if ( uc_check_avatar( $uid, 'small' ) > 0 ) {
			$src = UC_API . "/avatar.php?uid=$uid&size=small&random=" . rand();
			$avatar = "<img alt='{$alt}' src='{$src}' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";
		}
		return $avatar;
	}

	function deactivated_plugin( $plugin ) {
		if ( $plugin == plugin_basename(__FILE__) ) {
			delete_option( UCENTER_DEFINE_SETTING_NAME );
			delete_option( UCENTER_INTEGRATION_SETTING_NAME );
			if ( is_writable( dirname( __FILE__ ) ) && file_exists( dirname( __FILE__ ) . '/config.php' ) ) {
				unlink( dirname( __FILE__ ) . '/config.php' );
			}
		}
	}

	function activated_plugin( $plugin ) {
	}

	function load_dialect() {
		$plugin_dir = basename( dirname( __FILE__ ) );
		load_plugin_textdomain( 'ucenter', 'wp-content/plugins/' . $plugin_dir, $plugin_dir . '/language' );
	}

	function notices() {
		if ( !current_user_can( 'manage_options' )  ) return;
		if ( !file_exists( dirname( __FILE__ ) . '/config.php' ) ) {
			echo "
			<div class='updated'><p>" . sprintf( __( 'Ucenter Integration: Ucenter integration plugin is active now. But you must finish all related <a href="%s">settings</a> to make it work correctly.', 'ucenter' ), "admin.php?page=ucenter-define-settings" ) . "</p></div>
			";
		} else {
			require_once dirname( __FILE__ ) . '/config.php' ;
			if ( !defined( 'UC_KEY' ) || UC_KEY === '' ) {
				echo "
				<div class='updated'><p>" . sprintf( __( 'Ucenter Integration: Ucenter integration plugin is active now. But you must finish all related <a href="%s">settings</a> to make it work correctly.', 'ucenter' ), "admin.php?page=ucenter-define-settings" ) . "</p></div>
				";
			}
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

				if ( is_a( $user_id, 'WP_Error' ) ) {
					$errors->add( $user_id->get_error_code(), $user_id->get_error_message() );
					$errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you in wordpress... please contact the <a href="mailto:%s">webmaster</a> !', 'ucenter' ), get_option( 'admin_email' ) ) );
				}
			} elseif ( !wp_check_password( $password, $userdata->user_pass, $userdata->ID ) ) {
					// if user exists
					if ( $this->integration_settings['ucenter_password_override'] ) {
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
		$this->sync_login_cookie = stripcslashes( array_key_exists('sync_login', $_COOKIE) ? $_COOKIE['sync_login'] : '' );
		$this->sync_logout_cookie = stripcslashes( array_key_exists('sync_logout', $_COOKIE) ? $_COOKIE['sync_logout'] : '');
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
		if ( $uid < 0 ) {
			list( $id, $_, $_, $_ ) = uc_user_login( $username, $password );
			if ( $id < 0 ) {
				return false;
			}
		}
		return true;
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

	function update_user( $errors, $update, $user ) {
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
			// add admin menu
			add_menu_page( __( 'Ucenter', 'ucenter' ), __( 'Ucenter', 'ucenter' ), 'administrator', 'ucenter-box', '' );

			add_submenu_page( 'ucenter-box', __( 'Introduction', 'ucenter' ) , __( 'Introduction', 'ucenter' ), 'administrator', 'ucenter-box', array( &$this, 'submenu_introduction' ) );

			add_submenu_page( 'ucenter-box', __( 'Define Settings', 'ucenter' ) , __( 'Define Settings', 'ucenter' ), 'administrator', 'ucenter-define-settings', array( &$this, 'submenu_define_settings' ) );

			add_submenu_page( 'ucenter-box', __( 'Integration Settings', 'ucenter' ) , __( 'Integration Settings', 'ucenter' ), 'administrator', 'ucenter-integration-settings', array( &$this, 'submenu_integration_settings' ) );
	}

	function add_user_submenu_page() {
			// add user submenu page
			if ( $this->integration_settings['ucenter_enable_mail_box'] )
				add_submenu_page( 'users.php', __( 'Mail Box', 'ucenter' ) , __( 'Mail Box', 'ucenter' ), 'read', 'ucenter-mail-box', array( &$this, 'submenu_mail_box' ) );

			if ( $this->integration_settings['ucenter_enable_customize_icon'] )
				add_submenu_page( 'users.php', __( 'Customize Icon', 'ucenter' ) , __( 'Customize Icon', 'ucenter' ), 'read', 'ucenter-customize-icon', array( &$this, 'submenu_customize_icon' ) );


			if ( $this->integration_settings['ucenter_enable_friend'] )
				add_submenu_page( 'users.php', __( 'Friend', 'ucenter' ) , __( 'Friend', 'ucenter' ), 'read', 'ucenter-friend', array( &$this, 'submenu_frienD' ) );

			if ( $this->integration_settings['ucenter_enable_credit'] )
				add_submenu_page( 'users.php', __( 'Credit Exchange', 'ucenter' ) , __( 'Credit Exchange', 'ucenter' ), 'read', 'ucenter-credit-exchange', array( &$this, 'submenu_credit_exchange' ) );
	}

	function submenu_customize_icon() {
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Customize Icon', 'ucenter' ) . '</h2>';
		global $current_user;
		wp_get_current_user();
		list( $uid, $_, $_ ) = uc_get_user( $current_user->user_login );

		$icons = array( 'big' => __( 'Big Icon', 'ucenter' ), 'middle' => __( 'Middle Icon', 'ucenter' ), 'small' => __( 'Small Icon', 'ucenter' ) );
		echo '<div id="icon_list">';
		foreach ( $icons as $size => $name ) {
			if ( uc_check_avatar( $uid, $size ) > 0 ) {
				echo "$name<br /><img src='" . UC_API . "/avatar.php?uid=$uid&size=$size&random=" . rand() . "' /><br />";
			}
		}
		echo '</div><div id="icon_show">';
		$html = uc_avatar( $uid );
		echo $html;
		echo '</div></div>';
	}

	function submenu_credit_exchange() {
		echo '<div class=wrap>';
		echo '<h2>' . __( 'Credit Exchange', 'ucenter' ) . '</h2>';
		global $current_user;
		wp_get_current_user();
		list( $uid, $_, $_ ) = uc_get_user( $current_user->user_login );
		$credit = intval( get_usermeta( $current_user->ID, 'ucenter_credit' ) );
		if ( empty( $credit ) )
			$credit = 0;
		echo __( 'Current Credits : ', 'ucenter' ) . $credit . ' ' . $this->integration_settings['ucenter_credit_unit'] . '<br />';
		echo '<br />';
		$apps = uc_app_ls();
		$ratio_array = array();
		foreach ( $this->integration_settings['ucenter_credit_exchange_setting'] as $appid => $appsettings ) {
			if ( $appid == UC_APPID ) {
				foreach ( $appsettings as $appsetting ) {
					foreach ( $apps as $app ) {
						if ( $app['appid'] == $appsetting['appiddesc'] ) {
							echo '<form action="" method="post">';
							printf( __( 'Exchange %s <input type="text" name="amount" size=5 value="0"> %s to %s %s with ratio %s', 'ucenter'), $this->integration_settings['ucenter_credit_name'], $this->integration_settings['ucenter_credit_unit'], $app['name'], $appsetting['title'], $appsetting['ratio'] );
							echo "<input type='hidden' name='to' value='$appsetting[creditdesc]'>";
							echo "<input type='hidden' name='toappid' value='$appsetting[appiddesc]'>";
							echo '<input type="submit"><br />';
							echo '</form>';
							$ratio_array[implode(',',array($appsetting['creditdesc'], $appsetting['appiddesc']))] = $appsetting['ratio'];
						}
					}
				}
			}
		}

		if ( !empty( $_POST['to'] ) && !empty( $_POST['toappid'] ) ) {
			if ( intval( $_POST['amount'] ) >= 0 && intval( $_POST['amount'] ) <= $credit ) {
				$ratio = $ratio_array[implode(',',array($_POST['to'], $_POST['toappid']))];
				if ( uc_credit_exchange_request( $uid, 0, $_POST['to'], $_POST['toappid'], $_POST['amount']/$ratio) ) {
					$credit -= $_POST['amount'];
					update_usermeta( $current_user->ID, 'ucenter_credit', $credit );
					_e( 'Exchange Success!', 'ucenter' );
				}
			}
			else
				_e( 'Invalid Credit Amount!', 'ucenter' );
		}
		echo '</div>';
	}

	function submenu_mail_box() {
		echo '<div class=wrap>';
		echo '<h2>' . __( 'Mail Box', 'ucenter' ) . '</h2>';
		global $current_user;
		wp_get_current_user();
		list( $uid, $_, $_ ) = uc_get_user( $current_user->user_login );

		$timeoffset = get_option( 'gmt_offset' );
		$pm_per_page = 10;
		$max_msg_length = 100;
		$handler = $_SERVER['PHP_SELF'] . '?page=' . $_GET['page'];
		$current_handler = $handler . '&tab='. $_GET['tab'];
		$action = !empty( $_GET['action'] ) ? $_GET['action'] : '';
		$_GET['tab'] = !empty( $_GET['tab'] ) ? $_GET['tab'] : 'inbox';

		$menu = array(
			array( 'inbox', '', __( 'Inbox', 'ucenter' ) ),
			array( 'uread', 'filter=newpm', __( 'Unread Mail', 'ucenter' ) ),
			array( 'announcepm', 'filter=announcepm', __( 'Public Message', 'ucenter' ) ),
			array( 'systempm', 'filter=systempm', __( 'System Message', 'ucenter' ) ),
			array( 'send', 'action=send', __( 'Send Message', 'ucenter' ) ),
			array( 'blacklist', 'action=blacklist', __( 'Black List', 'ucenter' ) ),
		);

		echo '<ul class="ucenter-ul">';
		foreach ( $menu as $item ) {
			printf( "<li><a href='$handler&tab=$item[0]&$item[1]' %s>$item[2]</a></li>", $_GET['tab'] == $item[0] ? 'class="current"' : '');
		}
		echo '</ul><br /><hr />';
		switch ( $action ) {
			case '':
				$_GET['pageid'] =  max( 1, intval( $_GET['pageid'] ) );
				$_GET['filter'] = !empty( $_GET['filter'] ) ? $_GET['filter'] : '';

				$data = uc_pm_list( $uid, $_GET['pageid'], $pm_per_page, $_GET['folder'], $_GET['filter'], $max_msg_length );

				foreach ( $data['data'] as $pm ) {
					if ( $_GET['filter'] == 'announcepm' || $_GET['filter'] == 'systempm' ) {
						$output .= "<li><a href='$current_handler&action=view&subtab=within3days&daterange=3&pmid=$pm[pmid]'>$pm[subject]</a>";
						$output .= '<br /> ' . __( 'Content:', 'ucenter' ) . $pm[message] . '</li>';
					} else {
						$output .= "<li><a href='$current_handler&action=view&subtab=within3days&daterange=3&touid=$pm[touid]'>[$pm[msgfrom]]</a> (" . gmdate( 'Y-m-d H:i:s', $pm['dateline'] + $timeoffset * 3600 ) . ')';
						$pm['new'] && $output .= " New! ";
						$output .= '<br /> ' . __( 'Content: ', 'ucenter' ) . $pm[message] . '</li>';
					}
				}

				$page_n = $data['count'] / $pm_per_page;
				if ( $page_n > 1 ) {
					$output .= '<hr / ><br />';
					$output .= __( 'Page ', 'ucenter' );
					for ( $i = 1; $i <= $page_n; $i++ ) {
						$output .= " <a href='$current_handler&pageid=$i'>$i</a> ";
					}
				}
				break;
			case 'view':
				$pmid = !empty( $_GET['pmid'] ) ? $_GET['pmid'] : '';
				$daterange = !empty( $_GET['daterange'] ) ? $_GET['daterange'] : '1';
				$data = uc_pm_view( $uid, $pmid, $_GET['touid'], $daterange );


				$dateranges = array(
					array( 'within3days', '3', __( 'Within 3 Days', 'ucenter' ) ),
					array( 'within1week', '4', __( 'Within This Week', 'ucenter' ) ),
					array( 'all', '5', __( 'All', 'ucenter' ) ),
				);

				echo '<ul class="ucenter-ul">';
				foreach ( $dateranges as $item ) {
					printf( "<li><a href='$current_handler&action=view&touid=$_GET[touid]&pmid=$pmid&subtab=$item[0]&daterange=$item[1]' %s>$item[2]</a></li>", $_GET['subtab'] == $item[0] ? 'class="current"' : '' );
				}
				echo '</ul><br /><hr />';

				foreach ( $data as $pm ) {
					$output .= "<b>$pm[msgfrom]</b>";
					if ( $_GET['touid'] == $pm['msgfromid'] ) {
						$output .= "<a href='$current_handler&action=addblacklist&user=$pm[msgfrom]'>" . __( ' [ Ban This User ] ', 'ucenter' ) . "</a>";
					}
					$output .= ' ( ' . gmdate('Y-m-d H:i:s', $pm['dateline'] + $timeoffset * 3600 ) . ' ) ';
					$output .= "<br />$pm[message]<br /><br />";
				}

				if ( empty( $_GET['pmid'] ) ) {
					$output .= "
						<a href='$current_handler&action=delete&uid=$_GET[touid]'>" . __( 'Delete All Message From This user', 'ucenter' ) . "</a><br />
						Reply:
						<form method='post' action='$current_handler&action=send'>
						<input name='touid' type='hidden' value='$_GET[touid]'>
						<textarea name='message' cols='30' rows='5'></textarea><br />
						<input type='submit'>
						</form>
						";
				}
				break;
			case 'delete':
				if ( uc_pm_deleteuser( $uid, array( $_GET['uid'] ) ) ) {
					$output .= __( 'Deleted', 'ucenter' );
				}
				break;
			case 'addblacklist':
				$user = !empty( $_GET['user'] ) ? $_GET['user'] : (!empty( $_POST['user'] ) ? $_POST['user'] : '');
				if ( uc_pm_blackls_add( $uid, $user ) ) {
					$output .= $_GET['user'] . __( 'has been added to your black list', 'ucenter' );
				}
				break;
			case 'deleteblacklist':
				if ( uc_pm_blackls_delete( $uid, $_GET['user'] ) ) {
					$output .= $_GET['user'] . __( 'has been removed from your black list', 'ucenter' );
				}
				break;
			case 'blacklist':
				$data = explode( ',', uc_pm_blackls_get( $uid ) );
				foreach ( $data as $ls ) {
					$ls && $output .= "$ls <a href='$current_handler&action=deleteblacklist&user=$ls'>" . __( 'Remove', 'ucenter' ) . "</a>";
				}
				$output .= "
					<form method='post' action='$current_handler&action=addblacklist'>
					<input type='input' name='user' value=''>
					<input type='submit'>
					</form>
					";
				break;
			case 'send':
				if ( !empty( $_POST ) ) {
					if( !empty( $_POST['touser'] ) ) {
						$msgto = $_POST['touser'];
						$isusername = 1;
					} else {
						$msgto = $_POST['touid'];
						$isusername = 0;
					}
					if( uc_pm_send( $uid, $msgto, $_POST['subject'], $_POST['message'], 1, 0, $isusername ) ) {
						$output .= __( 'Sended', 'ucenter' );
					} else {
						$output .= __( 'Failed', 'ucenter' );
					}
				} else {
					$output .= "
						<form method='post' action='$current_handler&action=send'>
						<table>
						<tr><td>" . __( 'to', 'ucenter' ) . ":</td><td><input name='touser' value='$_GET[touser]'></td></tr>
						<tr><td>" . __( 'subject', 'ucenter' ) . ":</td><td><input name='subject' value=''><br></td></tr>
						<tr><td>" . __( 'content', 'ucenter' ) . ":</td><td><textarea name='message' cols='30' rows='5'></textarea></td></tr>
						</table>
						<input type='submit'>
						</form>
						";
				}
				break;
		}
		echo $output;
		echo '</div>';
	}

	function submenu_friend() {
		echo '<div class=wrap>';
		echo '<h2>' . __( 'Friend', 'ucenter' ) . '</h2>';
		global $current_user;
		wp_get_current_user();
		list( $uid, $_, $_ ) = uc_get_user( $current_user->user_login );

		$friends_per_page = 10;
		$handler = $_SERVER['PHP_SELF'] . '?page=' . $_GET['page'];
		$action = !empty( $_GET['action'] ) ? $_GET['action'] : 'view';
		$_GET['tab'] = !empty($_GET['tab']) ? $_GET['tab'] : 'friend';

		$menu = array(
			array( 'friend', '', __( 'Friend', 'ucenter' ) ),
			array( 'focus', '', __( 'Focus', 'ucenter' ) ),
			array( 'add', 'action=add', __( 'Add Friend', 'ucenter' ) ),
		);
		echo '<ul class="ucenter-ul">';
		foreach ( $menu as $item) {
			printf( "<li><a href='$handler&tab=$item[0]&$item[1]' %s>$item[2]</a></li>", $_GET['tab'] == $item[0] ? 'class="current"' : '' );
		}
		echo '</ul><br /><hr />';

		switch ( $action ) {
			case 'view':
				$_GET['pageid'] = !empty( $_GET['pageid'] ) ? $_GET['pageid'] : 1;
				$_GET['tab'] = !empty( $_GET['tab'] ) ? $_GET['tab'] : 'friend';
				$direction = !empty( $_GET['tab'] ) && $_GET['tab'] == 'focus' ? 1 : 3;
				$num = uc_friend_totalnum( $uid, $direction );
				$friendlist = uc_friend_ls( $uid, $_GET['pageid'], $friends_per_page, $num, $direction );
				if ( $friendlist ) {
					echo '<ul>';
					foreach ( $friendlist as $friend ) {
						echo "<li>$friend[username] : $friend[comment] [ <a href='$_SERVER[PHP_SELF]?page=ucenter-mail-box&tab=send&action=send&touser=$friend[username]'>Send Message</a> | <a href='$handler&action=delete&delete=$friend[friendid]'>Delete</a> ] </li>";
					}
					echo '</ul>';
				}
				break;
			case 'add':
				if( $_POST['newfriend'] && $friendid = uc_get_user( $_POST['newfriend'] ) ) {
					if ( uc_friend_add( $uid, $friendid[0], $_POST['newcomment'] ) )
						echo $_POST['newfriend'] . __( ' has been added to your list!', 'ucenter' ) . '<br /><br />';
				}

				echo "<form method='post' action='$handler&action=add&tab=$_GET[tab]'>
					<table>
						<tr><td>" . __( 'Add Friend', 'ucenter' ) . '</td><td><input name="newfriend"></td></tr>
						<tr><td>' . __( 'Description', 'ucenter' ) . '</td><td><input name="newcomment"></td></tr>
					</table>
					<input name="submit" type="submit">
					</form>';
				break;
			case 'delete':
				if ( !is_array( $_GET['delete'] ) ) {
					$_GET['delete'] = array( $_GET['delete'] );
				}
				if( !empty( $_GET['delete'] ) ) {
					if( uc_friend_delete( $uid, $_GET['delete'] ) )
						echo __( 'Removed!', 'ucenter' );
				}
				break;
		}
		echo '</div>';
	}

	function submenu_introduction() {
		$plugin_dir = basename( dirname( __FILE__ ) );
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Ucenter Introduction', 'ucenter' ) . '</h2>';
		_e( '<p>Ucenter Integration Plugin will help you integrate wordpress with ucenter supported platforms. If you find any bug, please email to nkucyd at gmail.com. Your help is appreciated. <br /> <a href="http://chenyundong.com/2010/04/wordpress-plugin-ucenter-integration-使用介绍/" target="_blank">http://chenyundong.com/2010/04/wordpress-plugin-ucenter-integration-使用介绍/</a> </p>', 'ucenter' );
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
		$page_options = 'ucenter_password_override,ucenter_credit_name,ucenter_credit_unit,ucenter_credit_per_comment,ucenter_credit_per_post,ucenter_enable_mail_box,ucenter_enable_customize_icon,ucenter_enable_friend,ucenter_enable_credit';
		$options = get_option( UCENTER_INTEGRATION_SETTING_NAME );

		if ( $_POST['page_options'] )
			$post_options = explode( ',', stripslashes( $_POST['page_options'] ) );

		if ( $post_options ) {
			foreach ( $post_options as $post_option ) {
				$post_option = trim( $post_option );
				$value = isset( $_POST[$post_option] ) ? trim( $_POST[$post_option] ) : false;
				$options[$post_option] = $value;
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
				<td width='150px'><?php _e( 'Password Override', 'ucenter' ) ?></td>
				<td><input type="checkbox" name="ucenter_password_override" value="1" <?php checked( '1', $options['ucenter_password_override'] ); ?> /></td>
			</tr>
			<tr><td></td><td><?php _e( '<strong>RECOMMENDATION: Enable This Option.</strong> If enable this option, user\'s password in ucenter will override that in wordpress when encounter pair(user, password) confliction between ucenter and wordpress. If disable this option, confliction will make login fail.<strong><br >WARNINGS: OPERATION WHEN YOU CLEARLY UNDERSTAND ITS MEANING!</strong>', 'ucenter' ) ?></td></tr>

			<tr>
				<td><?php _e( 'Credit Name', 'ucenter' ) ?></td>
				<td><input type="text" name="ucenter_credit_name" value="<?php echo $options['ucenter_credit_name']; ?>"/></td>
			</tr>
			<tr><td></td><td></td></tr>

			<tr>
				<td><?php _e( 'Credit Unit', 'ucenter' ) ?></td>
				<td><input type="text" name="ucenter_credit_unit" value="<?php echo $options['ucenter_credit_unit']; ?>"/></td>
			</tr>
			<tr><td></td><td></td></tr>

			<tr>
				<td><?php _e( 'Credit Per Comment', 'ucenter' ) ?></td>
				<td><input type="text" name="ucenter_credit_per_comment" value="<?php echo $options['ucenter_credit_per_comment']; ?>"/></td>
			</tr>
			<tr><td></td><td></td></tr>

			<tr>
				<td><?php _e( 'Enable Mail Box', 'ucenter' ) ?></td>
				<td><input type="checkbox" name="ucenter_enable_mail_box" value="1" <?php checked( '1', $options['ucenter_enable_mail_box'] ); ?> /></td>
			</tr>
			<tr><td></td><td></td></tr>

			<tr>
				<td><?php _e( 'Enable Customize Icon', 'ucenter' ) ?></td>
				<td><input type="checkbox" name="ucenter_enable_customize_icon" value="1" <?php checked( '1', $options['ucenter_enable_customize_icon'] ); ?> /></td>
			</tr>
			<tr><td></td><td></td></tr>

			<tr>
				<td><?php _e( 'Enable Friend', 'ucenter' ) ?></td>
				<td><input type="checkbox" name="ucenter_enable_friend" value="1" <?php checked( '1', $options['ucenter_enable_friend'] ); ?> /></td>
			</tr>
			<tr><td></td><td></td></tr>

			<tr>
				<td><?php _e( 'Enable Credit', 'ucenter' ) ?></td>
				<td><input type="checkbox" name="ucenter_enable_credit" value="1" <?php checked( '1', $options['ucenter_enable_credit'] ); ?> /></td>
			</tr>
			<tr><td></td><td></td></tr>

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
endif;
?>
