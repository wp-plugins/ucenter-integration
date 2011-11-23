<?php
define( 'API_DELETEUSER', 0 );
define( 'API_RENAMEUSER', 0 );
define( 'API_UPDATEPW', 0 );
define( 'API_GETTAG', 0 );
define( 'API_SYNLOGIN', 1 );
define( 'API_SYNLOGOUT', 1 );
define( 'API_UPDATEBADWORDS', 0 );
define( 'API_UPDATEHOSTS', 0 );
define( 'API_UPDATEAPPS', 0 );
define( 'API_UPDATECLIENT', 0 );
define( 'API_UPDATECREDIT', 0 );
define( 'API_GETCREDITSETTINGS', 1 );
define( 'API_UPDATECREDITSETTINGS', 1 );

define( 'API_RETURN_SUCCEED', '1' );
define( 'API_RETURN_FAILED', '-1' );
define( 'API_RETURN_FORBIDDEN', '-2' );

define( 'UCENTER_INTEGRATION_SETTING_NAME', 'plugin_ucenter_integration_settings' );

error_reporting(0);

function debug( $s ) {
	$logfile = dirname( __FILE__ ) . '/api.log';
	!file_exists( $logfile ) && @touch( $logfile );
	$str = file_get_contents( $logfile );
	$str = date( 'Y-m-d H:i:s' ) . "\n" . var_export( $s, true ) . "\n\n" . $str;
	@file_put_contents( $logfile, $str );
	unset( $str );
}

define( 'WP_ROOT', join( DIRECTORY_SEPARATOR, array_slice( explode( DIRECTORY_SEPARATOR, dirname( __FILE__ ) ), 0, -4 ) ) );
define( 'UCENTER_ROOT', dirname( dirname( __FILE__ ) ) );

require_once( WP_ROOT . '/wp-load.php' );
$config_file = UCENTER_ROOT . '/config.php';
if ( file_exists( $config_file ) )
	require_once( $config_file );
else
	exit( API_RETURN_FORBIDDEN );

require_once( UCENTER_ROOT . '/client/client.php' );
require_once( UCENTER_ROOT . '/client/lib/xml.class.php' );

$code = $_GET['code'];
parse_str( authcode( $code, 'DECODE', UC_KEY ), $get );

if( get_magic_quotes_gpc() )
	$get = dstripslashes($get);

$timestamp = time();
if ( empty( $get ) )
	exit( 'Invalid Request' );
elseif ( $timestamp - $get['time'] > 3600 )
	exit( 'Authracation has expiried' );

if ( in_array( $get['action'], array( 'test', 'synlogin', 'synlogout', 'getcreditsettings', 'updatecreditsettings' ) ) ) {
	$post = uc_unserialize( file_get_contents( 'php://input' ) );
	$uc_note = new uc_note();
	exit( $uc_note->$get['action']( $get, $post ) );
} else {
	exit( API_RETURN_FORBIDDEN );
}

class uc_note {
	function test( $get, $post ) {
		return API_RETURN_SUCCEED;
	}

	function synlogin( $get, $post ) {
		!API_SYNLOGIN && exit( API_RETURN_FORBIDDEN );

		$user = get_userdatabylogin( $get['username'] );
		if( $user ) {
			header( 'P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"' );
			wp_set_auth_cookie( $user->ID, false, '' );
		}

		exit( API_RETURN_SUCCEED );
	}

	function synlogout( $get, $post ) {
		!API_SYNLOGOUT && exit( API_RETURN_FORBIDDEN );

		header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
		wp_clear_auth_cookie();

		exit( API_RETURN_SUCCEED );
	}

	function getcreditsettings( $get, $post ) {
		!API_GETCREDITSETTINGS && exit( API_RETURN_FORBIDDEN );

		$options = get_option( UCENTER_INTEGRATION_SETTING_NAME );
		if ( empty( $options['ucenter_credit_name'] ) )
			exit( API_RETURN_FAILED );
		$creditsettings[] = array( $options['ucenter_credit_name'], $options['ucenter_credit_unit'] );

		exit( uc_serialize( $creditsettings ) );
	}

	function updatecreditsettings( $get, $post ) {
		!API_UPDATECREDITSETTINGS && exit( API_RETURN_FORBIDDEN );

		if ( !empty( $get['credit'] ) ) {
			$options = get_option( UCENTER_INTEGRATION_SETTING_NAME );
			$options['ucenter_credit_exchange_setting'] = $get['credit'];
			update_option( UCENTER_INTEGRATION_SETTING_NAME, $options );
		}

		exit( API_RETURN_SUCCEED );
	}
}

function authcode( $string, $operation = 'DECODE', $key = '', $expiry = 0 ) {
	$ckey_length = 4;
	$key = md5( $key ? $key : UC_KEY );
	$keya = md5( substr( $key, 0, 16 ) );
	$keyb = md5( substr( $key, 16, 16 ) );
	$keyc = $ckey_length ? ( $operation == 'DECODE' ? substr( $string, 0, $ckey_length ): substr( md5( microtime() ), -$ckey_length ) ) : '';

	$cryptkey = $keya.md5( $keya.$keyc );
	$key_length = strlen( $cryptkey );

	$string = $operation == 'DECODE' ? base64_decode( substr( $string, $ckey_length  )) : sprintf( '%010d', $expiry ? $expiry + time() : 0 ).substr( md5( $string.$keyb ), 0, 16 ).$string;
	$string_length = strlen( $string );

	$result = '';
	$box = range( 0, 255 );

	$rndkey = array();
	for ( $i = 0; $i <= 255; $i++ ) {
		$rndkey[$i] = ord( $cryptkey[$i % $key_length] );
	}

	for ( $j = $i = 0; $i < 256; $i++ ) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}

	for ( $a = $j = $i = 0; $i < $string_length; $i++ ) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr( ord( $string[$i] ) ^ ( $box[($box[$a] + $box[$j] ) % 256] ) );
	}

	if ( $operation == 'DECODE' ) {
		if ( ( substr( $result, 0, 10 ) == 0 || substr( $result, 0, 10 ) - time() > 0 ) && substr( $result, 10, 16 ) == substr( md5( substr( $result, 26 ).$keyb ), 0, 16 ) ) {
			return substr( $result, 26 );
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace( '=', '', base64_encode( $result ) );
	}
}

function dstripslashes( $string ) {
	if( is_array( $string ) ) {
		foreach ( $string as $key => $val ) {
			$string[$key] = dstripslashes( $val );
		}
	} else {
		$string = stripslashes( $string );
	}
	return $string;
}
?>
