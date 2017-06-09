<?php
/*
*   Plugin Name: Login with WordPress
*   Author: Webkul
*   Author URI: https://webkul.com
*   Description: Login API for third-party to login using WordPress.
*   Version: 1.0.0
*   Domain Path: plugins/wp-login-with-wordpress-api
*/
ob_start();

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

! defined( 'LOGIN_WITH_WP_PATH' ) && define( 'LOGIN_WITH_WP_PATH', plugin_dir_path(__FILE__) );
! defined( 'LOGIN_WITH_WP_URL' ) && define( 'LOGIN_WITH_WP_URL', plugin_dir_url(__FILE__) );

/**
 * Version number for our API.
 *
 * @var string
 */
define( 'JSON_API_VERSION', '1.0.0' );

/**
 * Include our files for the API.
 */
include_once( dirname( __FILE__ ) . '/includes/class-jsonserializable.php' );

include_once( dirname( __FILE__ ) . '/includes/class-wp-json-responsehandler.php' );
include_once( dirname( __FILE__ ) . '/includes/class-wp-json-server.php' );
include_once( dirname( __FILE__ ) . '/includes/class-wp-json-responseinterface.php' );
include_once( dirname( __FILE__ ) . '/includes/class-wp-json-response.php' );
include_once( dirname( __FILE__ ) . '/includes/class-wc-rest-authentication.php' );

include_once( dirname( __FILE__ ) . '/includes/class-wp-json-access-token.php' );

include_once( dirname( __FILE__ ) . '/includes/class-wp-generate-token.php' );

include_once( dirname( __FILE__ ) . '/includes/admin/class-integrate-menu.php' );

add_filter( 'rewrite_rules_array', 'wp_insert_custom_rules' );

add_filter( 'query_vars', 'wp_insert_custom_vars' );

// Adding the id var so that WP recognizes it
function wp_insert_custom_vars($vars){
    $vars[] = 'main_page';
    $vars[] = 'pagename';
    $vars[] = 'client_id';
    return $vars;
}

function wp_insert_custom_rules($rules) {

    $newrules = array();
    $newrules=array(
    	'my-account/(.+)/(.+)?'                              => 'index.php?pagename=my-account&$matches[1]=$matches[1]&$matches[1]=$matches[2]',
    	'my-account/(.+)/?'                    		  => 'index.php?pagename=my-account&$matches[1]=$matches[1]',
    	'(.+)/(.+)/?'                    		  => 'index.php?pagename=$matches[1]&main_page=$matches[2]'
    );

    return $newrules + $rules;
}

add_action( 'wp_head', function() {
	$a = get_query_var( 'main_page' );
	global $wp_query;
	if ( !empty ( $a ) && $a == 'auth' ) {
			add_shortcode( 'uv_login', 'wk_uvdesk_auth_form' );
	}
	else {
			add_shortcode( 'uv_login', 'wk_uvdesk_login' );
	}
});

add_action( 'template_redirect', function() {
		global $wpdb;
		if ( isset($_GET['oauth_consumer_key']) ) {
				$app_data = $wpdb->get_row( $wpdb->prepare( "SELECT count(*) as count	FROM {$wpdb->prefix}oauth_clients WHERE consumer_key = %s", $_GET['oauth_consumer_key'] ), ARRAY_A );
				if ( get_query_var( 'pagename' ) == 'uv-login' && empty(get_query_var( 'main_page' )) ) :
						if ( $app_data['count'] == 1 && is_user_logged_in() ) {
								wp_redirect(home_url('/uv-login/auth?oauth_consumer_key='.$_GET['oauth_consumer_key']));
								exit;
						}
				endif;
				if ( get_query_var( 'pagename' ) == 'uv-login' && get_query_var( 'main_page' ) == 'auth' ) :
						if ( $app_data['count'] == 1 && !is_user_logged_in() ) {
								wp_redirect(home_url('/uv-login?oauth_consumer_key='.$_GET['oauth_consumer_key']));
								exit;
						}
				endif;
		}
});

// login with woocommerce form
function wk_uvdesk_login()
{
		global $wpdb;
		$app_data = $wpdb->get_row( $wpdb->prepare( "SELECT count(*) as count	FROM {$wpdb->prefix}oauth_clients WHERE consumer_key = %s", $_GET['oauth_consumer_key'] ), ARRAY_A );

		if ( $app_data['count'] == 1 )
		{

				if( isset( $_GET['login'] ) && $_GET['login'] == 'failed' ){
					wc_print_notice( 'Wrong Email or Password.', 'error' );
				}

				if ( ! is_user_logged_in() )
				{
						$args = array(
								'redirect' => home_url('/uv-login/auth?oauth_consumer_key='.$_GET['oauth_consumer_key']),
								'form_id' => 'login-with-wp',
								'label_username' => __( 'Username or email address' ),
								'label_password' => __( 'Password' ),
								'label_log_in' => __( 'Login' )
						);
						wp_login_form( $args );
				}

		}
		else
		{
				wp_redirect(site_url());
				exit;
		}

}

// deny or allow form

function wk_uvdesk_auth_form()
{

		global $wpdb;
		$table_name = $wpdb->prefix.'oauth_clients';
		$table_name1 = $wpdb->prefix.'oauth_codes';
		$client_id = $_GET['oauth_consumer_key'];
		$redirect_uri = $wpdb->get_results( "Select redirect_uri, deny_uri from $table_name where consumer_key = $client_id ", ARRAY_A );
		$user = wp_get_current_user();

		if( $user->roles && in_array('customer',$user->roles)) {
				wc_print_notice( 'Authorized Client.', 'success' );
				if ( isset( $_POST['allow_auth']))
				{

						$auth_code = WK_TokenGenerator::wk_generateToken();
						$check_val = $wpdb->insert( $table_name1, array(
								'auth_code' => $auth_code,
								'oauth_consumer_key' => $client_id,
								'redirect_uri' => $redirect_uri[0]['redirect_uri'],
								'user_id' => get_current_user_id(),
								'expire'	=> strtotime("+1 minutes")
						));

						if ( $check_val )
						{
								wp_redirect($redirect_uri[0]['redirect_uri'].'?auth_code='.$auth_code);
								exit;
						}

				}

				if ( isset( $_POST['deny_auth'] ) )
				{

						wp_redirect($redirect_uri[0]['deny_uri']);
						exit;

				}

				?>
				<form method="post">
						<p>Allow app to use data - </p>
						<p><label><input type="submit" name="allow_auth" value="Allow">
		  			<label><input type="submit" name="deny_auth" value="Deny"></p>
				</form>
				<?php
		}
		else {
				wc_print_notice( 'You are not valid customer.', 'error' );
		}
}

// login failed redirect
add_action( 'wp_login_failed', 'wk_front_end_login_failed' );

function wk_front_end_login_failed( $username )
{

		$referrer = $_SERVER['HTTP_REFERER'];

		$referrer = explode('?', $referrer);

		if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') )
		{

				wp_redirect( $referrer[0] . '?'.$referrer[1].'&login=failed' );

				exit;

		}

}

// enqueue style
add_action( 'wp_enqueue_scripts', function()
{
		wp_enqueue_style('loginwp-css', LOGIN_WITH_WP_URL.'/assets/css/style.css');
});

/**
 * Register rewrite rules for the API.
 */
function json_api_init() {
	json_api_register_rewrites();

	global $wp;
	$wp->add_query_var( 'json_route' );
}
add_action( 'init', 'json_api_init', 11 );

/**
 * Add rewrite rules.
 */
function json_api_register_rewrites() {

	add_rewrite_rule( '^' . json_get_url_prefix() . '/?$','index.php?json_route=/','top' );
	add_rewrite_rule( '^' . json_get_url_prefix() . '/(.*)?','index.php?json_route=/$matches[1]','top' );
}

/**
 * Determine if the rewrite rules should be flushed.
 */
function json_api_maybe_flush_rewrites() {
	$version = get_option( 'json_api_plugin_version', null );

	if ( empty( $version ) ||  $version !== JSON_API_VERSION ) {
		flush_rewrite_rules();
		update_option( 'json_api_plugin_version', JSON_API_VERSION );
	}

}
add_action( 'init', 'json_api_maybe_flush_rewrites', 999 );

/**
 * Register the default JSON API filters.
 */
function json_api_default_filters( $server ) {

  global $wp_json_customer_login;

	$wp_json_customer_login = new WP_JSON_Access_Token( $server );

	add_filter( 'json_endpoints', array( $wp_json_customer_login, 'register_routes' ), 0 );

}

add_action( 'wp_json_server_before_serve', 'json_api_default_filters', 10, 1 );

/**
 * Load the JSON API.
 */
function json_api_loaded() {
	if ( empty( $GLOBALS['wp']->query_vars['json_route'] ) )
		return;

	/**
	 * Whether this is a XML-RPC Request.
	 */
	define( 'XMLRPC_REQUEST', true );

	/**
	 * Whether this is a JSON Request.
	 */
	define( 'JSON_REQUEST', true );

	global $wp_json_server;

	// Allow for a plugin to insert a different class to handle requests.
	$wp_json_server_class = apply_filters( 'wp_json_server_class', 'WP_JSON_Server' );
	$wp_json_server = new $wp_json_server_class;

	/**
	 * Fires when preparing to serve an API request.
	 */
	do_action( 'wp_json_server_before_serve', $wp_json_server );

	// Fire off the request.
	$wp_json_server->serve_request( $GLOBALS['wp']->query_vars['json_route'] );

	// We're done.
	die();
}
add_action( 'template_redirect', 'json_api_loaded', -100 );

/**
 * Register routes and flush the rewrite rules on activation.
 */
function json_api_activation( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			$mu_blogs = wp_get_sites();

			foreach ( $mu_blogs as $mu_blog ) {
				switch_to_blog( $mu_blog['blog_id'] );

				json_api_register_rewrites();
				update_option( 'json_api_plugin_version', null );
			}

			restore_current_blog();
		} else {
			json_api_register_rewrites();
			update_option( 'json_api_plugin_version', null );
		}
		require_once( sprintf("%s/install.php", dirname(__FILE__)) );
}
register_activation_hook( __FILE__, 'json_api_activation' );

/**
 * Flush the rewrite rules on deactivation.
 */
function json_api_deactivation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {
			switch_to_blog( $mu_blog['blog_id'] );
			delete_option( 'json_api_plugin_version' );
		}

		restore_current_blog();
	} else {
		delete_option( 'json_api_plugin_version' );
	}
}
register_deactivation_hook( __FILE__, 'json_api_deactivation' );

/**
 * Get the URL prefix for any API resource.
 */
function json_get_url_prefix() {
	/**
	 * Filter the JSON URL prefix.
	 */

	return apply_filters( 'json_url_prefix', 'wp-json' );
}

/**
 * Get URL to a JSON endpoint on a site.
 */
function get_json_url( $blog_id = null, $path = '', $scheme = 'json' ) {

	if ( get_option( 'permalink_structure' ) ) {
		$url = get_home_url( $blog_id, json_get_url_prefix(), $scheme );

		if ( ! empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false )
			$url .= '/' . ltrim( $path, '/' );
	} else {
		$url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );

		if ( empty( $path ) ) {
			$path = '/';
		} else {
			$path = '/' . ltrim( $path, '/' );
		}

		$url = add_query_arg( 'json_route', $path, $url );
	}

	/**
	 * Filter the JSON URL.
	 */
	return apply_filters( 'json_url', $url, $path, $blog_id, $scheme );
}

/**
 * Get URL to a JSON endpoint.
 */
function json_url( $path = '', $scheme = 'json' ) {
	return get_json_url( null, $path, $scheme );
}

/**
 * Ensure a JSON response is a response object.
 */
function json_ensure_response( $response ) {
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( $response instanceof WP_JSON_ResponseInterface ) {
		return $response;
	}

	return new WP_JSON_Response( $response );
}

/**
 * Check if we have permission to interact with the post object.
 */
function json_check_post_permission( $post, $capability = 'read' ) {

	$permission = false;

	switch ( $capability ) {
		case 'read' :

			// if ( current_user_can( 'read', $post->id ) ) {
				$permission = true;
			// }

			break;

		case 'edit' :

			// if ( current_user_can( 'manage_options' ) ) {
				$permission = true;
			// }
			break;

		case 'create' :

			// if ( current_user_can( 'manage_options' ) ) {
				$permission = true;
			// }
			break;

		case 'delete' :

			// if ( current_user_can( 'manage_options', $post->id ) ) {
				$permission = true;
			// }
			break;

		default :

			// if ( current_user_can( $post_type->cap->$capability ) ) {
				$permission = true;
			// }
	}

	return apply_filters( "json_check_post_{$capability}_permission", $permission, $post );
}

/**
 * Send Cross-Origin Resource Sharing headers with API requests
 */
function json_send_cors_headers( $value ) {
	$origin = get_http_origin();

	if ( $origin ) {
		header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
		header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE' );
		header( 'Access-Control-Allow-Credentials: true' );
	}

	return $value;
}

/**
 * Handle OPTIONS requests for the server
 */
function json_handle_options_request( $response, $handler ) {
	if ( ! empty( $response ) || $handler->method !== 'OPTIONS' ) {
		return $response;
	}

	$response = new WP_JSON_Response();

	$accept = array();

	$handler_class = get_class( $handler );
	$class_vars = get_class_vars( $handler_class );
	$map = $class_vars['method_map'];

	foreach ( $handler->get_routes() as $route => $endpoints ) {
		$match = preg_match( '@^' . $route . '$@i', $handler->path, $args );

		if ( ! $match ) {
			continue;
		}

		foreach ( $endpoints as $endpoint ) {
			foreach ( $map as $type => $bitmask ) {
				if ( $endpoint[1] & $bitmask ) {
					$accept[] = $type;
				}
			}
		}
		break;
	}
	$accept = array_unique( $accept );

	$response->header( 'Accept', implode( ', ', $accept ) );

	return $response;
}
