<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MP_REST_Authentication {

	/**
	 * Initialize authentication actions.
	 */
	public function __construct() {
		add_filter( 'determine_current_user', array( $this, 'authenticate' ), 100 );
		add_filter( 'rest_authentication_errors', array( $this, 'check_authentication_error' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'send_unauthorized_headers' ), 50 );
	}

	/**
	 * Authenticate user.
	 */
	public function authenticate( $user_id ) {

		return $this->perform_oauth_authentication();

	}

	/**
	 * Check for authentication error.
	 */
	public function check_authentication_error( $error ) {
		global $wc_rest_authentication_error;

		// Passthrough other errors.
		if ( ! empty( $error ) ) {
			return $error;
		}

		return $wc_rest_authentication_error;
	}

	/**
	 * Perform OAuth 1.0a "one-legged" (http://oauthbible.com/#oauth-10a-one-legged) authentication for non-SSL requests.
	 */
	private function perform_oauth_authentication() {
		global $wc_rest_authentication_error;

		$params = array( 'oauth_consumer_key', 'oauth_code' );

		// Check for required OAuth parameters.
		foreach ( $params as $param ) {
			if ( empty( $_POST[ $param ] ) ) {
				return false;
			}
		}

		// Fetch WP user by consumer key
		$user = $this->get_user_data_by_consumer_key( $_POST['oauth_consumer_key'] );

		if ( empty( $user ) ) {
			$wc_rest_authentication_error = new WP_Error( 'woocommerce_rest_authentication_error', __( 'Consumer Key is invalid.', 'woocommerce' ), array( 'status' => 401 ) );
			return false;
		}

		//Perform OAuth validation.
		// $wc_rest_authentication_error = $this->check_oauth_signature( $user, $_GET );
		// if ( is_wp_error( $wc_rest_authentication_error ) ) {
		// 	return false;
		// }

		// $wc_rest_authentication_error = $this->check_oauth_timestamp_and_nonce( $user, $_GET['oauth_timestamp'], $_GET['oauth_nonce'] );
		// if ( is_wp_error( $wc_rest_authentication_error ) ) {
		// 	return false;
		// }

		// Check API Key permissions.
		// if ( ! $this->check_permissions( $user->permissions ) ) {
		// 	return false;
		// }

		// Update last access.
		// $this->update_last_access( $user->key_id );

		return $user->user_id;
	}

	/**
	 * Verify that the consumer-provided request signature matches our generated signature,
	 * this ensures the consumer has a valid key/secret.
	 */
	private function check_oauth_signature( $user, $params ) {
		$http_method  = strtoupper( $_SERVER['REQUEST_METHOD'] );
		$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$wp_base      = get_home_url( null, '/', 'relative' );
		if ( substr( $request_path, 0, strlen( $wp_base ) ) === $wp_base ) {
			$request_path = substr( $request_path, strlen( $wp_base ) );
		}
		$base_request_uri = rawurlencode( get_home_url( null, $request_path ) );

		// Get the signature provided by the consumer and remove it from the parameters prior to checking the signature.
		$consumer_signature = rawurldecode( $params['oauth_signature'] );
		unset( $params['oauth_signature'] );

		// Sort parameters.
		if ( ! uksort( $params, 'strcmp' ) ) {
			return new WP_Error( 'woocommerce_rest_authentication_error', __( 'Invalid Signature - failed to sort parameters.', 'woocommerce' ), array( 'status' => 401 ) );
		}

		// Normalize parameter key/values.
		$params           = $this->normalize_parameters( $params );
		$query_parameters = array();
		foreach ( $params as $param_key => $param_value ) {
			if ( is_array( $param_value ) ) {
				foreach ( $param_value as $param_key_inner => $param_value_inner ) {
					$query_parameters[] = $param_key . '%255B' . $param_key_inner . '%255D%3D' . $param_value_inner;
				}
			} else {
				$query_parameters[] = $param_key . '%3D' . $param_value; // Join with equals sign.
			}
		}
		$query_string   = implode( '%26', $query_parameters ); // Join with ampersand.
		$string_to_sign = $http_method . '&' . $base_request_uri . '&' . $query_string;

		if ( $params['oauth_signature_method'] !== 'HMAC-SHA1' && $params['oauth_signature_method'] !== 'HMAC-SHA256' ) {
			return new WP_Error( 'woocommerce_rest_authentication_error', __( 'Invalid Signature - signature method is invalid.', 'woocommerce' ), array( 'status' => 401 ) );
		}

		$hash_algorithm = strtolower( str_replace( 'HMAC-', '', $params['oauth_signature_method'] ) );
		$secret         = $user->consumer_secret . '&';
		$signature      = base64_encode( hash_hmac( $hash_algorithm, $string_to_sign, $secret, true ) );

		if ( ! hash_equals( $signature, $consumer_signature ) ) {
			return new WP_Error( 'woocommerce_rest_authentication_error', __( 'Invalid Signature - provided signature does not match.', 'woocommerce' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Normalize each parameter by assuming each parameter may have already been
	 * encoded, so attempt to decode, and then re-encode according to RFC 3986.
	 */
	private function normalize_parameters( $parameters ) {
		$keys       = wc_rest_urlencode_rfc3986( array_keys( $parameters ) );
		$values     = wc_rest_urlencode_rfc3986( array_values( $parameters ) );
		$parameters = array_combine( $keys, $values );

		return $parameters;
	}

	/**
	 * Verify that the timestamp and nonce provided with the request are valid. This prevents replay attacks where
	 * an attacker could attempt to re-send an intercepted request at a later time.
	 */
	private function check_oauth_timestamp_and_nonce( $user, $timestamp, $nonce ) {
		global $wpdb;

		$valid_window = 15 * 60; // 15 minute window.

		if ( ( $timestamp < time() - $valid_window ) || ( $timestamp > time() + $valid_window ) ) {
			return new WP_Error( 'woocommerce_rest_authentication_error', __( 'Invalid timestamp.', 'woocommerce' ), array( 'status' => 401 ) );
		}

		$used_nonces = maybe_unserialize( $user->nonces );

		if ( empty( $used_nonces ) ) {
			$used_nonces = array();
		}

		if ( in_array( $nonce, $used_nonces ) ) {
			return new WP_Error( 'woocommerce_rest_authentication_error', __( 'Invalid nonce - nonce has already been used.', 'woocommerce' ), array( 'status' => 401 ) );
		}

		$used_nonces[ $timestamp ] = $nonce;

		// Remove expired nonces.
		foreach ( $used_nonces as $nonce_timestamp => $nonce ) {
			if ( $nonce_timestamp < ( time() - $valid_window ) ) {
				unset( $used_nonces[ $nonce_timestamp ] );
			}
		}

		$used_nonces = maybe_serialize( $used_nonces );

		$wpdb->update(
			$wpdb->prefix . 'woocommerce_api_keys',
			array( 'nonces' => $used_nonces ),
			array( 'key_id' => $user->key_id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Return the user data for the given consumer_key.
	 */
	private function get_user_data_by_consumer_key( $consumer_key ) {

		global $wpdb;
		$user = $wpdb->get_row( $wpdb->prepare( "SELECT user_id	FROM {$wpdb->prefix}oauth_clients WHERE consumer_key = %s", $consumer_key ) );
		return $user;

	}

	/**
	 * Check that the API keys provided have the proper key-specific permissions to either read or write API resources.
	 */
	private function check_permissions( $permissions ) {
		global $wc_rest_authentication_error;

		$valid = true;

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return false;
		}

		switch ( $_SERVER['REQUEST_METHOD'] ) {

			case 'HEAD' :
			case 'GET' :
				if ( 'read' !== $permissions && 'read_write' !== $permissions ) {
					$wc_rest_authentication_error = new WP_Error( 'woocommerce_rest_authentication_error', __( 'The API key provided does not have read permissions.', 'woocommerce' ), array( 'status' => 401 ) );
					$valid = false;
				}
				break;

			case 'POST' :
			case 'PUT' :
			case 'PATCH' :
			case 'DELETE' :
				if ( 'write' !== $permissions && 'read_write' !== $permissions ) {
					$wc_rest_authentication_error = new WP_Error( 'woocommerce_rest_authentication_error', __( 'The API key provided does not have write permissions.', 'woocommerce' ), array( 'status' => 401 ) );
					$valid = false;
				}
				break;
		}

		return $valid;
	}

	/**
	 * Updated API Key last access datetime.
	 */
	private function update_last_access( $key_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'woocommerce_api_keys',
			array( 'last_access' => current_time( 'mysql' ) ),
			array( 'key_id' => $key_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * If the consumer_key and consumer_secret $_GET parameters are NOT provided
	 * and the Basic auth headers are either not present or the consumer secret does not match the consumer
	 * key provided, then return the correct Basic headers and an error message.
	 */
	public function send_unauthorized_headers( $response ) {
		global $wc_rest_authentication_error;

		if ( is_wp_error( $wc_rest_authentication_error ) && is_ssl() ) {
			$auth_message = __( 'WooCommerce API - Use a consumer key in the username field and a consumer secret in the password field.', 'woocommerce' );
			$response->header( 'WWW-Authenticate', 'Basic realm="' . $auth_message . '"', true );
		}

		return $response;
	}
}
