<?php

if ( !class_exists( 'WP_UV_Menu' ) ) {

    /**
     *
     */
    class WP_UV_Menu
    {

        function __construct()
        {
            add_action( 'admin_menu', array( $this, 'wk_uv_integration_menu' ) );
        }

        function wk_uv_integration_menu()
        {

            add_menu_page( 'WordPress SSO', 'WordPress SSO', 'manage_options', 'uvdesk-integration', array( $this, 'wk_uvdesk_integration' ), '', 55 );

            $hook = add_submenu_page( 'uvdesk-integration', 'Uvdesk Integration', 'Apps', 'manage_options', 'uvdesk-integration', array( $this, 'wk_uvdesk_integration' ) );

            add_submenu_page( 'uvdesk-integration', 'Add App', 'Add New', 'manage_options', 'add-app', array( $this, 'wk_uvdesk_add_integration' ) );

            add_action( "load-$hook", array( $this, 'wk_add_rule_screen_option' ) );

			      add_filter( 'set-screen-option', array( $this, 'wk_set_options' ), 10, 3 );

            add_action( 'wk_add_edit_app_app_edit', array( $this, 'wk_add_uv_app' ) );

            add_action( 'wk_add_edit_app_keys', array( $this, 'wk_uv_app_keys' ) );

        }

        function wk_add_rule_screen_option() {

      			$options = 'per_page';

      			$args = array(
      				'label' => 'Product Per Page',
      				'default' => 20,
      				'option' => 'product_per_page'
      			);

      			add_screen_option( $options, $args );

    		}

    		function wk_set_options($status, $option, $value) {

    			   return $value;

    		}

        function wk_uvdesk_integration()
        {
            echo '<div class="wrap">';

                echo '<h1 class="wp-heading-inline">Apps</h1>';

                echo '<a href="admin.php?page=add-app" class="page-title-action">Add New</a>';

                require_once(sprintf("%s/class-uvdesk-app.php", dirname(__FILE__)));

            echo '</div>';
        }

        function wk_uvdesk_add_integration()
        {
            echo '<div class="wrap">';
            echo '<nav class="nav-tab-wrapper">';
            if ( isset( $_GET['app_id'] ) ) :
                echo '<h1 class="wp-heading-inline">Edit App Details</h1>';
                $wk_tabs = array(

                  'app_edit'	=>	__('Edit'),
                  'keys'	=>	__('Keys')

                );
            else :
                echo '<h1 class="wp-heading-inline">Add New App</h1>';
                $wk_tabs = array(

                  'app_edit'	=>	__('Add'),

                );
            endif;

            echo '<p>App Information</p>';

              $current_tab = empty( $_GET['tab'] ) ? 'app_edit' : sanitize_title( $_GET['tab'] );
              $pid = empty( $_GET['app_id'] ) ? '' : '&app_id='.$_GET['app_id'];

              foreach ( $wk_tabs as $name => $label ) {

                echo '<a href="' . admin_url( 'admin.php?page=add-app'.$pid.'&tab=' . $name ) . '" class="nav-tab ' . ( $current_tab == $name ? 'nav-tab-active' : '' ) . '">' . $label . '</a>';

              }
            ?>

            </nav>

            <h1 class="screen-reader-text"><?php echo esc_html( $wk_tabs[ $current_tab ] ); ?></h1>

            <?php

            do_action( 'wk_add_edit_app_' . $current_tab );

            echo '</div>';

        }

        function wk_add_uv_app()
        {
            require_once(sprintf("%s/class-add-uvdesk-app.php", dirname(__FILE__)));
            $ob = new WK_Add_App();
            $ob->mp_add_new_uv_app();
        }

        function wk_uv_app_keys()
        {
            require_once(sprintf("%s/class-uvdesk-app-keys.php", dirname(__FILE__)));
            $ob = new WK_App_Keys();
            $ob->mp_show_uv_app_keys();
        }

    }

    new WP_UV_Menu();

}
