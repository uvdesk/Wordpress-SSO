<?php

if ( !class_exists( 'WK_UV_Integration_Install' ) )
{

    /**
     *
     */
    class WK_UV_Integration_Install
    {

        function wk_create_uvapp_tables()
        {
            global $table_prefix, $wpdb;

            $tblname = 'oauth_clients';

            $oauth_clients_table = $table_prefix . "$tblname ";

            $charset_collate = $wpdb->get_charset_collate();

  	        if( $wpdb->get_var("show tables like '$oauth_clients_table'") != $oauth_clients_table ) {

  	            $sql = "CREATE TABLE $oauth_clients_table (
  	                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `email` varchar(255) NOT NULL,
  	                `consumer_key` varchar(80) NOT NULL,
  	                `secret_key` varchar(80) NOT NULL,
  	                `redirect_uri` varchar(2000) NOT NULL,
                    `deny_uri` varchar(2000) NOT NULL,
                    `user_id` bigint(20) NOT NULL,
  	                `status` varchar(20) NOT NULL DEFAULT 'publish',
  	                PRIMARY KEY (id)
  	            ) $charset_collate;";

  	            if ( !function_exists( 'dbDelta' ) ) {
  	                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  	            }

  	            dbDelta( $sql );

  	        }

            $tblname1 = 'oauth_codes';

            $oauth_code_table = $table_prefix . "$tblname1";

            if( $wpdb->get_var("show tables like '$oauth_code_table'") != $oauth_code_table ) {

  	            $sql = "CREATE TABLE $oauth_code_table (
  	                `authcode_id` bigint(20) NOT NULL AUTO_INCREMENT,
  	                `auth_code` varchar(80) NOT NULL,
                    `oauth_consumer_key` varchar(80) NOT NULL,
  	                `user_id` varchar(80) NOT NULL,
  	                `redirect_uri` varchar(2000) NOT NULL,
  	                `expire` bigint(20) NOT NULL,
  	                PRIMARY KEY (authcode_id)
  	            ) $charset_collate;";
                if ( !function_exists( 'dbDelta' ) ) {
  	                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  	            }
  	            dbDelta( $sql );

  	        }

            $tblname2 = 'oauth_jwt_tokens';

            $oauth_jwt_table = $table_prefix . "$tblname2";

            if( $wpdb->get_var("show tables like '$oauth_jwt_table'") != $oauth_jwt_table ) {

  	            $sql = "CREATE TABLE $oauth_jwt_table (
  	                `token_id` bigint(20) NOT NULL AUTO_INCREMENT,
  	                `access_token` varchar(2000) NOT NULL,
                    `oauth_consumer_key` varchar(80) NOT NULL,
                    `oauth_code` varchar(80) NOT NULL,
  	                `user_id` varchar(80) NOT NULL,
  	                `redirect_uri` varchar(2000) NOT NULL,
  	                `expire` bigint(20) NOT NULL,
  	                PRIMARY KEY (token_id)
  	            ) $charset_collate;";
                if ( !function_exists( 'dbDelta' ) ) {
  	                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
  	            }
  	            dbDelta( $sql );
  	        }

        }

        public function wk_create_pages() {

           $pages = apply_filters( 'wl_login_with_wp_create_pages',array(
      			    'uv_login' => array(
      				      'name' =>  _x( 'UV Login','Page slug', 'wk_uv_app' ),
      				      'title'=> _x( 'UV Login','Page title', 'wk_uv_app' ),
      				      'content' => '[uv_login]')
      			     )
      		  );
      		  foreach ( $pages as $key => $page ){
      			   $this->wk_page_creation( esc_sql( $page['name'] ), 'wk_login_with_wp_' . $key . '_page_id', $page['title'], $page['content'] );
      		  }
      	}

        public function wk_page_creation( $slug, $option = '', $page_title = '', $page_content = '')
        {

            global $wpdb;
        		$option_value = get_option( $option );

            if ( $option_value > 0 && get_post( $option_value ) )
        		   return -1;

            $page_found = null;

            if ( strlen( $page_content ) > 0 ) 	{

        		   $page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_type='page' AND post_content LIKE %s LIMIT 1;", "%{$page_content}%" ) );

            }
        		else
        		{

        		   $page_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_type='page' AND post_name = %s LIMIT 1;", $slug ) );

            }

            if ( $page_found )
            {
        		   if ( ! $option_value )
        			    update_option( $option, $page_found );
        				return $page_found;
        		}

            $user_id = is_user_logged_in();

            $mp_post_type = 'page';

        		$page_data = array(
        	        'post_status'       => 'publish',
        		      'post_type'         => $mp_post_type,
        	        'post_author'       => $user_id,
        	        'post_name'         => $slug,
        	        'post_title'        => $page_title,
        	        'post_content'      => $page_content,
        	        'post_parent'       => $post_parent,
        	        'comment_status'    => 'closed'
        	   );
        	   $page_id = wp_insert_post( $page_data );
        	   if ( $option )
        	       update_option( $option, $page_id );
        	   return $page_id;
        	}

    }

    $ob = new WK_UV_Integration_Install();

    $ob->wk_create_uvapp_tables();

    $ob->wk_create_pages();
}
