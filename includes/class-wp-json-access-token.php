<?php

require LOGIN_WITH_WP_PATH . '/vendor/autoload.php';
use \Firebase\JWT\JWT;

class WP_JSON_Access_Token {
	/**
	 * Server object
	 */
	protected $server;

	/**
	 * Constructor
	 */
	public function __construct(WP_JSON_ResponseHandler $server) {
		$this->server = $server;

	}

	/**
	 * Register the post-related routes
	 */
	public function register_routes( $routes ) {

		$post_routes = array(
			// Post endpoints
			'/oauth/token' => array(
				array( array( $this, 'generate_token' ),      WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON )
			)
		);

		return array_merge( $routes, $post_routes );
	}

	function generate_token() {

			global $wpdb;
			$oauth_code = $_POST['oauth_code'];
			$consumer_key = $_POST['oauth_consumer_key'];
			$now = strtotime("now");

			$user_id_data = $wpdb->get_results("SELECT *	FROM {$wpdb->prefix}oauth_codes WHERE auth_code = $oauth_code and oauth_consumer_key = $consumer_key and $now < expire", ARRAY_A );

			if ( $user_id_data ) {

					$user_id = $user_id_data[0]['user_id'];

					$redirect_uri = $user_id_data[0]['redirect_uri'];

					$user_data = get_user_by('ID', $user_id);

					$secret_data = $wpdb->get_row( $wpdb->prepare( "SELECT secret_key	FROM {$wpdb->prefix}oauth_clients WHERE consumer_key = %s", $consumer_key ) );

					$access_token = $wpdb->get_results("SELECT token_id	FROM {$wpdb->prefix}oauth_jwt_tokens WHERE user_id = $user_id and $now < expire", ARRAY_A );

					$token_table = $wpdb->prefix.'oauth_jwt_tokens';

					$expire_time = strtotime("+7 day");

					if ( $access_token ) {
							$sql = $wpdb->update(
									$token_table,
									array(
										'expire' => $now,
									),
									array( 'token_id' => $access_token[0]['token_id'] ),
									array(
										'%s'
									),
									array( '%d' )
							);
					}
					$key = $secret_data->secret_key;
					$token = array(
					    "iss" => site_url(),
					    "aud" => $redirect_uri,
							"exp"	=> $expire_time,
					    "iat" => strtotime("now"),
							"name"	=> $user_data->display_name,
							"email" => $user_data->user_email
					);

					$jwt = JWT::encode($token, $key);

					$authcode_table = $wpdb->prefix.'oauth_codes';
					if ( $jwt ) {
							$sql = $wpdb->update(
									$authcode_table,
									array(
										'expire' => $now,
									),
									array( 'auth_code' => $oauth_code ),
									array(
										'%s'
									),
									array( '%s' )
							);
					}

					$check_val = $wpdb->insert( $token_table, array(
							'access_token' => $jwt,
							'oauth_consumer_key' => $consumer_key,
							'oauth_code' => $oauth_code,
							'redirect_uri' => $redirect_uri,
							'user_id' => $user_id,
							'expire'	=> $expire_time
					));

					$access_token = $jwt;
					$success = 'true';
					$error = compact( 'success', 'access_token' );
					return json_encode( array( $error ) );

			}
			else {
					$success = 'false';
					$message = 'The Authorisation code has been expired.';
					$error = compact( 'success', 'message' );
					return json_encode( array( $error ) );
			}

	}

}