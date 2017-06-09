<?php
/**
*	   Login : Add App
*/

if (!class_exists('WK_App_Keys'))
{

  	/**
  	*
  	*/
  	class WK_App_Keys
    {

        function mp_show_uv_app_keys()
        {
            global $wpdb;

            $table_name = $wpdb->prefix.'oauth_clients';

            if ( isset( $_GET['app_id'] ) ) {

                $app_id = $_GET['app_id'];
                $app_data = $wpdb->get_results( "Select consumer_key,secret_key from $table_name where id = $app_id ", ARRAY_A);

            }
            ?>

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
        								  <label for="app_c_key">Consumer Key</label>
        							</th>

        							<td class="forminp">
                          <input type="text" value="<?php if ( isset( $app_data ) ) echo $app_data[0]['consumer_key']; ?>" readonly="true" />
        							</td>

        						</tr>

                    <tr valign="top">

        							<th scope="row" class="titledesc">
        								  <label for="app_s_key">Secret Key</label>
        							</th>

        							<td class="forminp">
                          <input type="text" value="<?php if ( isset( $app_data ) ) echo $app_data[0]['secret_key']; ?>" readonly="true" />
        							</td>

        						</tr>

                    <tr><td><hr></td><td><hr></td></tr>

                  </tbody>

                  <tfoot>

        						<tr>
        							<th>Fields</th>
        							<th>Options</th>
        						</tr>

        					</tfoot>

                </table>
            <?php
        }

    }

}
