<?php
/**
*	Seller Auction: Product list
*/
if( !class_exists( 'WP_List_Table' ) ){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );


}

if ( !class_exists( 'WK_Uvdesk_App_List' ) ) {

	/**
	*
	*/
	class WK_Uvdesk_App_List extends WP_List_Table {

		function __construct() {

			parent::__construct( array(
		    	'singular'	=> 'App',
		      	'plural' 	=> 'Apps',
		      	'ajax'   	=> false
		    ) );

		}

		function prepare_items() {

  			global $wpdb;

    		$columns = $this->get_columns();

    		$sortable = $this->get_sortable_columns();

    		$hidden = $this->get_hidden_columns();

				$this->process_bulk_action();

    		$data = $this->table_data();

    		$totalitems = count($data);

    		$user = get_current_user_id();

    		$screen = get_current_screen();

    		$perpage = $this->get_items_per_page('rule_per_page', 20);

    		$this->_column_headers = array($columns,$hidden,$sortable);

    		if ( empty ( $per_page) || $per_page < 1 ) {

      			$per_page = $screen->get_option( 'per_page', 'default' );

    		}

    		function usort_reorder($a,$b) {

  					$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'app_id'; //If no sort, default to title

        		$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc

        		$result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order

        		return ($order==='asc') ? $result : -$result; //Send final sort direction to usort

  			}

    		usort($data, 'usort_reorder');

            $totalpages = ceil($totalitems/$perpage);

            $currentPage = $this->get_pagenum();

            $data = array_slice($data,(($currentPage-1)*$perpage),$perpage);

            $this->set_pagination_args( array(

            	"total_items" => $totalitems,

            	"total_pages" => $totalpages,

            	"per_page" => $perpage,

        	) );

        	$this->items =$data;

    	}

		function extra_tablenav( $which ) {
		   	if ( $which == "top" ) {
		      	echo "List of apps.";
		   	}
		}

		/**
		 * Define the columns that are going to be used in the table
		 * @return array $columns, the array of columns to use with the table
		 */

		function get_columns() {

		   	return $columns= array (
  		   		'cb'         	=> '<input type="checkbox" />', //Render a checkbox instead of text
          	'app_id'			=> __('Id'),
            'name'				=> __('Name'),
						'url'					=> __('Redirect URI'),
            'deny_url'		=> __('Deny URI')
		   	);

		}

		function column_default($item, $column_name) {

    		switch( $column_name ) {

      			case 'app_id':

      			case 'name':

						case 'url':

      			case 'deny_url':

        			return $item[ $column_name ];

      			default:

        			return print_r($item, true);

    		}

  		}

		/**
		 * Decide which columns to activate the sorting functionality on
		 * @return array $sortable, the array of columns that can be sorted by the user
		 */
		public function get_sortable_columns() {

		   	return $sortable = array(
		      	'name'	=> 'name'
		   	);

		}

		public function get_hidden_columns() {

			return array();

		}

		function column_cb($item){

			return sprintf('<input type="checkbox" id="app_%s" name="app[]" value="%s" />',$item['app_id'], $item['app_id']);

		}

		private function table_data() {

	    	global $wpdb;

	    	$data = array();

				if ( isset($_POST['s']) )
				{
						$string = $_POST['s'];
						$wk_posts = $wpdb->get_results("Select * from {$wpdb->prefix}oauth_clients where status = 'publish' and name like '%$string%'", ARRAY_A);
				}
				else
				{
	    			$wk_posts = $wpdb->get_results("Select * from {$wpdb->prefix}oauth_clients where status = 'publish'", ARRAY_A);
				}

	    	$app_id = array();

	    	$name = array();

				$url = array();

    		$deny_url = array();

	    	$i = 0;

	    	foreach ($wk_posts as $key => $value) {

					$app_id[] = $value['id'];

					$name[] = $value['name'];

					$url[] = $value['redirect_uri'];

					$deny_url[] = $value['deny_uri'];

					$data[] = array(

							'app_id'	=> $app_id[$i],
							'name'	=> $name[$i],
							'url' => $url[$i],
							'deny_url' => $deny_url[$i]

					);

					$i++;

	    	}

	    	return $data;

	    }

			function column_app_id($item) {

    		$actions = array(

  					'edit'     => sprintf('<a href="admin.php?page=add-app&app_id=%s&tab=app_edit">Edit</a>', $item['app_id']),

  					'trash'    => sprintf('<a href="admin.php?page=uvdesk-integration&action=trash&rule=%s">Trash</a>',$item['app_id'])

    		);

    		return sprintf('%1$s %2$s', $item['app_id'], $this->row_actions($actions) );

  		}

	}

	$list_obj = new WK_Uvdesk_App_List();

	$list_obj->prepare_items();

	?>

	<form method="POST">

	    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

		<?php

		  $list_obj->search_box('Search', 'search-id');

    	$list_obj->display();

    	?>

    </form>

    <?php

}
