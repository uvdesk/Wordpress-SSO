<?php

require LOGIN_WITH_WP_PATH . '/vendor/autoload.php';
use \Firebase\JWT\JWT;

class SSO_JSON_Access_Token {

	/**
	 * Register the /wp-json/myplugin/v1/foo route
	 */
	function sso_register_routes() {
		register_rest_route( 'wc/v1', 'oauth/token', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'sso_generate_token' ),
		) );
		register_rest_route( 'wc/v1', 'check/token', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'sso_oauth_validation_check' ),
		) );
	}

	function sso_oauth_validation_check( WP_REST_Request $request ) {

			global $wpdb;
			$consumer_key  =  $_POST['auth_consumer_key'];
			$secret_key    =  $_POST['auth_secret_key'];

			$check_response = array();

			$key_assoc_data = $wpdb->get_results( "SELECT * from {$wpdb->prefix}oauth_clients WHERE consumer_key = '$consumer_key'", ARRAY_A );

			if( $key_assoc_data ) {

					if( $key_assoc_data[0]['secret_key'] === $secret_key ) {

						$check_response['success'] = 'true';
						$check_response['message'] = 'Client verified successfully';
					}
					else {
						$check_response['success'] = 'false';
						$check_response['message'] = 'Invalid credentials provided';
					}

			}
			else{

				$check_response['success'] = 'false';
				$check_response['message'] = 'Client verified successfully';
			}

			return json_encode($check_response);
	}

	function sso_generate_token(WP_REST_Request $request) {

			global $wpdb;
			$oauth_code = $_POST['auth_code'];
			$consumer_key = $_POST['auth_consumer_key'];
			$now = strtotime("now");

			$user_id_data = $wpdb->get_results("SELECT *	FROM {$wpdb->prefix}oauth_codes WHERE auth_code = $oauth_code and oauth_consumer_key = '$consumer_key' and $now < expire", ARRAY_A );

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
