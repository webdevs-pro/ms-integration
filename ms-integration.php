<?php
/**
 * Plugin Name: MS Integration
 * Version: 0.4.6
 */


class MS_Integration {

   public function __construct() {
      add_filter( 'bulk_actions-edit-property', array( $this, 'register_integration_actions' ) );
      add_filter( 'handle_bulk_actions-edit-property', array( $this, 'bulk_action_handler' ), 10, 3 );
      add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
      add_action( 'post_submitbox_misc_actions', array( $this, 'add_publish_meta_options' ) );
      add_action( 'save_post', array( $this, 'extra_publish_meta_options_save' ), 10 , 3) ;
      add_filter( 'page_row_actions', array( $this, 'add_custom_row_actions' ), 10, 2 );
      add_action( 'admin_init',  array( $this, 'fire_page_row_action' ) );
      add_filter( 'set_url_scheme', array( $this, 'remove_bulk_actions_query_params' ) );
   }


   /**
    * Register integration actions for properties listing screen.
    *
    * @since 1.0.0
    *
    * @param array $bulk_actions
    * @return array $bulk_actions
    */
   public function register_integration_actions( $bulk_actions ) {
      $bulk_actions['publish_on_servises'] = 'Publish to services';
      $bulk_actions['remove_from_servises'] = 'Remove from services';
      return $bulk_actions;
   }


   /**
    * Handle properties bulk actions.
    *
    * @since 1.0.0
    *
    * @param array $redirect_to
    * @param array $doaction
    * @param array $post_ids
    *
    * @return array $redirect_to
    */
   public function bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
      // exit if not our actions
      if ( ! in_array( $doaction, ['publish_on_servises', 'remove_from_servises'] ) ) {
         return $redirect_to;
      }

      // let's remove query args first
      $redirect_to = remove_query_arg(
         array( 'bulk_publishd_properties', 'bulk_removed_properties', 'post_id', 'property_services_action' ),
         $redirect_to
      );

      // publish
      if ( $doaction === 'publish_on_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'published_on_services', 1 );
         }
         $redirect_to = add_query_arg( 'bulk_publishd_properties', count( $post_ids ), $redirect_to );
         $this->update_properties_on_services( $post_ids, 'publish' );
      }

      // remove
      if ( $doaction === 'remove_from_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'published_on_services', '' );
         }
         $redirect_to = add_query_arg( 'bulk_removed_properties', count( $post_ids ), $redirect_to );
         $this->update_properties_on_services( $post_ids, 'remove' );
      }


      return $redirect_to;
   }


   /**
    * Add admin notices after bulk actions.
    *
    * @since 1.0.0
    *
    * @return void
    */
   function bulk_action_notices() {
      // publishd
      if( ! empty( $_REQUEST[ 'bulk_publishd_properties' ] ) ) {
         $count = (int) $_REQUEST[ 'bulk_publishd_properties' ];
         $message = sprintf(
            _n(
               '%d property has been updated on services.',
               '%d properties has been updated on services.',
               $count
            ),
            $count
         );
         echo "<div class=\"updated notice is-dismissible\"><p>{$message}</p></div>";
      }

      // removed
      if( ! empty( $_REQUEST[ 'bulk_removed_properties' ] ) ) {
         $count = (int) $_REQUEST[ 'bulk_removed_properties' ];
         $message = sprintf(
            _n(
               '%d property has been removed from services.',
               '%d properties has been removed from services.',
               $count
            ),
            $count
         );
         echo "<div class=\"updated notice is-dismissible\"><p>{$message}</p></div>";
      }
   }


   /**
    * Add checkbox to publish section on property edit screen.
    *
    * @since 1.0.0
    *
    * @return void
    */
   public function add_publish_meta_options( $post_obj ) {
      $value = get_post_meta( $post_obj->ID, 'published_on_services', true );
   
      if ( 'property' == $post_obj->post_type ) {
         ?>
         <div class="misc-pub-section misc-pub-section-last" style="padding: 10px; background-color: #e1ffe1;">
            <h4>Publish this property on services?</h4>
            <label>
               <input type="checkbox"<?php echo $value ? ' checked="checked" ' : ''; ?> value="1" name="published_on_services" />
               <b>Publish</b>
            </label>
            <p>If this box is checked, this property will be published on DAFT and MYHOME services othervise properties will be removed from services.</p>

         </div>
         <?php
      }
   }


   /**
    * Process propery publish/update actions.
    *
    * @since 1.0.0
    *
    * @return void
    */
   public function extra_publish_meta_options_save( $post_id, $post, $update ) {
      if ( 'property' != $post->post_type ) {
         return;
      }
   
      if ( wp_is_post_revision( $post_id ) ) {
         return;
      }
   
      if ( isset( $_POST['published_on_services'] ) && $_POST['published_on_services'] == 1 ) {
         update_post_meta( $post_id, 'published_on_services', $_POST['published_on_services'] );
         $this->update_properties_on_services( $post_id, 'publish' );
      } else {
         update_post_meta( $post_id, 'published_on_services', '' );
         $this->update_properties_on_services( $post_id, 'remove' );
      }

   }


   /**
    * Update services.
    *
    * @since 1.0.0
    *
    * @return void
    */
   public function update_properties_on_services( $post_ids, $action ) {
      include_once( 'daft.php' );
      MSIDaft::update_service( (array) $post_ids );

      include( 'myhome.php' );
      if ( $action == 'publish' ) {
         // MSIMyHome::publish( (array) $post_ids );
      }
      if ( $action == 'remove' ) {
         // MSIMyHome::remove( (array) $post_ids );
      }

   }


   /**
    * Add custom property list row actions.
    *
    * @since 1.0.0
    *
    * @param array $actions
    * @param object $post
    *
    * @return array $actions
    */
   public function add_custom_row_actions( $actions, $post ){
      if ( $post->post_type == 'property' ) {
   
         $post_type_object = get_post_type_object( $post->post_type );
         if ( ! $post_type_object ) {
            return;
         }
         if ( ! current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {
            return;
         }
   
         $state = get_post_meta( $post->ID, 'published_on_services', true );
         if ( ! $state ) {
            $url = add_query_arg(
               array(
                 'post_id' => $post->ID,
                 'property_services_action' => 'publish',
               )
             );
            $actions['publish_to_services'] = '<a title="Publish to services" href="' . $url . '">Publish to services</a>';
         } else {
            $url = add_query_arg(
               array(
                 'post_id' => $post->ID,
                 'property_services_action' => 'remove',
               )
             );
            $actions['remove_from_services'] = '<a title="Remove from services" href="' . $url . '">Remove from services</a>';
         }
      }
      return $actions;
   }


   /**
    * Fire row actions.
    *
    * @since 1.0.0
    *
    * @return void
    */
   function fire_page_row_action() {
      if ( ! isset( $_REQUEST['post_id'] ) || ! $_REQUEST['post_id'] ) {
         return;
      }

      $post_id = $_REQUEST['post_id'];

      if ( isset( $_REQUEST['property_services_action'] ) && $_REQUEST['property_services_action'] == 'publish'  ) {
         update_post_meta( $post_id, 'published_on_services', 1 );
         $this->update_properties_on_services( $post_id, 'publish' );
      } elseif ( isset( $_REQUEST['property_services_action'] ) &&  $_REQUEST['property_services_action'] == 'remove' ) {
         update_post_meta( $post_id, 'published_on_services', '' );
         $this->update_properties_on_services( $post_id, 'remove' );
      }

      add_filter( 'removable_query_args', array( $this, 'remove_page_row_action_query_params' ) );
   }


   /**
    * Remove page row action query params after firing action.
    *
    * @since 1.0.0
    *
    * @param array $removable_url_params
    *
    * @return array $removable_url_params
    */
   public function remove_page_row_action_query_params( $removable_url_params ) {
      $remove_params = array('property_services_action', 'post_id' );
      return array_merge( $remove_params, $removable_url_params );
   }


   /**
    * Remove bulk actions query params after firing actions.
    *
    * @since 1.0.0
    *
    * @param array $url
    *
    * @return array $url
    */
   public function remove_bulk_actions_query_params( $url ) {   
      return remove_query_arg( array( 'bulk_publishd_properties', 'bulk_removed_properties' ), $url );
   }

}
new MS_Integration();




























add_filter( 'manage_edit-property_columns', function( $columns ) {
   $columns = array_reverse( $columns );

   $position = 1;
   $new_item = ['published' => 'Published on services'];

   $columns = array_slice( $columns, 0, $position ) + $new_item + array_slice( $columns, $position );

   return array_reverse( $columns );
} );
 
add_action( 'manage_property_posts_custom_column', function( $column_key, $post_id ) {
   if ( $column_key == 'published' ) {
      $published_on_services = get_post_meta( $post_id, 'published_on_services', true );
      if ( $published_on_services ) {
         echo '<span style="background-color:#cbf4cb; padding: 5px 15px;border-radius: 3px;">Yes</span>';
      } else {
         echo '<span style="background-color:#ffd5d5; padding: 5px 15px;border-radius: 3px;">No</span>';

      }
   }
}, 10, 2);

add_filter('manage_edit-property_sortable_columns', function($columns) {
   $columns['published'] = 'published';
   return $columns;
});

add_action( 'pre_get_posts', function($query) {
   if ( ! is_admin() ) {
       return;
   }

   $orderby = $query->get( 'orderby' );
   if ( $orderby == 'published' ) {
       $query->set( 'meta_key', 'published_on_services' );
       $query->set( 'orderby', 'meta_value_num' );
   }
});




require 'plugin-update-checker/plugin-update-checker.php';
$cpfeUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/webdevs-pro/ms-integration/',
	__FILE__,
	'ms-integration'
);

//Set the branch that contains the stable release.
$cpfeUpdateChecker->setBranch('main');





// add_filter( 'ere_property_meta_boxes', function( $property_meta_boxes ) {
//    error_log( "property_meta_boxes\n" . print_r( $property_meta_boxes, true ) . "\n" );

//    return $property_meta_boxes;
// }, 9999 );


// add_filter( 'ere_property_metabox_fields', function( $property_metabox_fields ) {
//    error_log( "property_metabox_fields\n" . print_r( $property_metabox_fields, true ) . "\n" );

//    return $property_metabox_fields;
// }, 9999 );
