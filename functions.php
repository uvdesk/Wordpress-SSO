<?php

/*
*   Plugin Name: WordPress SSO
*   Author: Webkul
*   Author URI: https://webkul.com
*   Description: Login API for third-party to login using WordPress.
*   Version: 1.0.0
*   Domain Path: plugins/Wordpress-SSO
*/
ob_start();

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

! defined( 'LOGIN_WITH_WP_PATH' ) && define( 'LOGIN_WITH_WP_PATH', plugin_dir_path(__FILE__) );
! defined( 'LOGIN_WITH_WP_URL' ) && define( 'LOGIN_WITH_WP_URL', plugin_dir_url(__FILE__) );

/**
 * Create tables and flush the rewrite rules on activation.
 */
function sso_api_activation() {
		flush_rewrite_rules();
		require_once( sprintf("%s/install.php", dirname(__FILE__)) );
}
register_activation_hook( __FILE__, 'sso_api_activation' );

/**
 * Version number for our API.
 *
 * @var string
 */
define( 'JSON_API_VERSION', '1.0.0' );

/**
 * Include our files for the API.
 */
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
	if ( get_query_var( 'pagename' ) == 'uv-login' && empty(get_query_var( 'main_page' )) ) {
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
				wc_print_notice( 'You are not valid customer. <a href='.wp_logout_url(site_url().'/uv-login?oauth_consumer_key='.$_GET['oauth_consumer_key']).' class="wc-forward">Logout</a>', 'error' );
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

$wp_json_customer_login = new SSO_JSON_Access_Token( $server );

add_action( 'rest_api_init', array( $wp_json_customer_login, 'sso_register_routes' ) );
