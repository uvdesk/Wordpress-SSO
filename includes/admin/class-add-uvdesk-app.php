<?php
/**
*	   Login : Add App
*/

if (!class_exists('WK_Add_App'))
{

  	/**
  	*
  	*/
  	class WK_Add_App
    {

        function mp_add_new_uv_app()
        {
            global $wpdb;

            $table_name = $wpdb->prefix.'oauth_clients';

            if ( isset($_POST['submit_uvapp'] ) )
            {

                if ( ! isset( $_POST['uvapp_nonce'] ) || ! wp_verify_nonce( $_POST['uvapp_nonce'], 'add_uvapp_nonce' ) ) {

                    wp_die( __( 'Cheatin&#8217; huh?', 'wk_uv_app' ) );

                }

                else
                {
                    if ( empty($_POST['app_uname']) && empty($_POST['app_uri']) )
                    {
                        ?>
                        <div class="notice notice-error is-dismissible">
                            <p><?php _e( 'Fill All Fields.', 'wk_uv_app' ); ?></p>
                        </div>
                        <?php
                    }
                    else
                    {
                        $name = $_POST['app_uname'];
                        $url = $_POST['app_uri'];
                        $deny_url = $_POST['deny_uri'];
                        $consumer_key = WK_TokenGenerator::wk_generateToken();
                        $secret_key = WK_TokenGenerator::wk_generateToken();

                        if ( isset( $_GET['app_id'] ) ) {
                            $app_id = $_GET['app_id'];
                    			  $check_val = $wpdb->update($table_name, array( 'name'=>$name, 'redirect_uri'=>$url, 'deny_uri' => $deny_url), array('id'=>$app_id));
                            $last_id = $app_id;
                    		}

                    		else {

                            $check_val = $wpdb->insert( $table_name, array(
                	        			'name' => $name,
                                'consumer_key' => $consumer_key,
                                'secret_key' => $secret_key,
                                'redirect_uri' => $url,
                                'deny_uri' => $deny_url,
                	        			'user_id' => get_current_user_id()
              	        		));
                            $last_id = $wpdb->insert_id;
                        }

                        if ( $check_val )
                        {
                            wp_redirect( 'admin.php?page=add-app&app_id='.$last_id.'&msg=1' );
                            exit;
                        }

                    }

                }

            }

            if ( isset($_GET['msg'] ) && $_GET['msg']==1 ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e( 'App Added Successfully.', 'wk_uv_app' ); ?></p>
                </div>
                <?php
            }

            if ( isset( $_GET['app_id'] ) ) {

                $app_id = $_GET['app_id'];
                $app_data = $wpdb->get_results( "Select name,redirect_uri,deny_uri from $table_name where id = $app_id ", ARRAY_A);

            }
            ?>
            <form action="" method="post">

        				<table class="form-table">

        					<thead>

        						<tr>
        							<th>Fields</th>
        							<th>Options</th>
        						</tr>

        					</thead>

        					<tbody>

        						<tr><td><hr></td><td><hr></td></tr>

        						<tr valign="top">

        							<th scope="row" class="titledesc">
        								  <label for="app_name">Name</label>
        							</th>

        							<td class="forminp">
                          <span class="required">* </span><input type="text" id="app_name" name="app_uname" placeholder="App Name" value="<?php if ( isset( $app_data[0]['name'] ) ) echo $app_data[0]['name']; ?>" />
        							</td>

        						</tr>

                    <tr><td></td><td></td></tr>

                    <tr valign="top">

        							<th scope="row" class="titledesc">
        								  <label for="url">Redirect URI</label>
        							</th>

        							<td class="forminp">
                          <span class="required">* </span><input type="text" id="url" name="app_uri" placeholder="Redirect URI" value="<?php if ( isset( $app_data[0]['redirect_uri'] ) ) echo $app_data[0]['redirect_uri']; ?>" />
                          <p class="description">URI user redirect to on allow the access to data.</p>
        							</td>

        						</tr>

                    <tr><td></td><td></td></tr>

                    <tr valign="top">

        							<th scope="row" class="titledesc">
        								  <label for="deny">Reject URI</label>
        							</th>

        							<td class="forminp">
                          <input type="text" id="deny" name="deny_uri" placeholder="Reject URI" value="<?php if ( isset( $app_data[0]['deny_uri'] ) ) echo $app_data[0]['deny_uri']; ?>" />
                          <p class="description">URI user redirect to on deny the access to data (Optional).</p>
        							</td>

        						</tr>

                    <tr><td><hr></td><td><hr></td></tr>

                  </tbody>

                </table>

                <?php wp_nonce_field( 'add_uvapp_nonce', 'uvapp_nonce' ); ?>

				        <p><input type="submit" name="submit_uvapp" class="button button-primary" value="Save" /></p>

            </form>
            <?php
        }

    }

}
