<?php
/**
 * Plugin Name: MS Integration
 * Version: 0.6.0
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
      $bulk_actions['publish_on_servises'] = 'Publish to Daft and MH';
      $bulk_actions['remove_from_servises'] = 'Remove from Daft and MH';
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
         array( 'bulk_published_properties', 'bulk_removed_properties', 'post_id', 'property_services_action' ),
         $redirect_to
      );

      // publish
      if ( $doaction === 'publish_on_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'published_on_services', 1 );
         }
         $redirect_to = add_query_arg( 'bulk_published_properties', count( $post_ids ), $redirect_to );
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
      if( ! empty( $_REQUEST[ 'bulk_published_properties' ] ) ) {
         $count = (int) $_REQUEST[ 'bulk_published_properties' ];
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
            <h4>Publish to Daft and MH?</h4>
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
      include( 'daft.php' );
//       MSIDaft::update_service( (array) $post_ids );

      include( 'myhome.php' );
      MSIMyHome::update_service( (array) $post_ids, $action );
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
            $actions['publish_to_services'] = '<a title="Publish to Daft and MH" href="' . $url . '">Publish to Daft and MH</a>';
         } else {
            $url = add_query_arg(
               array(
                 'post_id' => $post->ID,
                 'property_services_action' => 'remove',
               )
             );
            $actions['remove_from_services'] = '<a title="Remove from Daft and MH" href="' . $url . '">Remove from Daft and MH</a>';
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
      return remove_query_arg( array( 'bulk_published_properties', 'bulk_removed_properties' ), $url );
   }

}
new MS_Integration();




























add_filter( 'manage_edit-property_columns', function( $columns ) {
   $columns = array_reverse( $columns );

   $position = 1;
   $new_item = ['published' => 'Published on Daft and MH'];

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

add_filter( 'manage_edit-property_sortable_columns', function( $columns ) {
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






add_filter( 'ere_property_metabox_fields', function( $property_metabox_fields ) {

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      0,
      array(
         'id' => 'REAL_HOMES_property_type',
         'name' => 'Property type',
         'type' => 'select',
         // 'std' => '',
         'options' => array(
            'house' => 'House',
            'apartment' => 'Apartment',
            'duplex' => 'Duplex',
            'bungalow' => 'Bungalow',
            'site' => 'Site'
         ),
         'columns' => '6',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      1,
      array(
         'type' => 'divider',
         'columns' => '12',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      2,
      array(
         'type' => 'custom_html',
         'std'  => '<div style="font-size: 16px; font-weight: bold;">Daft Additional Data</div>',
         'columns' => '12',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      3,
      array(
         'id' => 'REAL_HOMES_property_sale_type_daft',
         'name' => 'Sale type',
         'type' => 'select',
         'options' => array(
            'private-treaty' => 'Private',
            'auction' => 'Auction',
            'tender' => 'Tender',
         ),
         'columns' => '6',
         'tab' => 'details',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      4,
      array(
         'id' => 'REAL_HOMES_property_house_type_daft',
         'name' => 'House type',
         'type' => 'select',
         // 'std' => 'thumb-on-right',
         'options' => array(
            'detached' => 'Detached',
            'semi-detached' => 'Semi-detached',
            'terraced' => 'Terraced',
            'end-of-terrace' => 'End-of-terrace',
            'townhouse' => 'Townhouse',
         ),
         'columns' => '6',
         'tab' => 'details',
         'visible' => array(
            'REAL_HOMES_property_type',
             '=',
             'house'
         )
      )
   );


   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      5,
      array(
         'id' => 'REAL_HOMES_property_agent_id_daft',
         'name' => 'Account ID',
         'type' => 'select',
         // 'std' => 'thumb-on-right',
         'options' => array(
            '7569' => 'Ray Cooke Auctioneers Clondalkin',
            '10947' => 'Ray Cooke Auctioneers Finglas',
            '10948' => 'Ray Cooke Auctioneers Tallaght',
            '10949' => 'Ray Cooke Auctioneers Terenure',
            '10994' => 'Ray Cooke Lettings',
         ),
         'columns' => '6',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      6,
      array(
         'type' => 'divider',
         'columns' => '12',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      7,
      array(
         'type' => 'custom_html',
         'std'  => '<div style="font-size: 16px; font-weight: bold;">MyHome Additional Data</div>',
         'columns' => '12',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      8,
      array(
         'id' => 'REAL_HOMES_property_sale_type_myhome',
         'name' => 'Sale type',
         'type' => 'select',
         'desc' => 'Determines the type of sale. Not mandatory when property class is Lettings. Residencial = [Private, Auction], Commercial = [For Sale, To Let, For Auction, For Tender], Overseas = [New - Just Built, Off]',
         'options' => array(
            'Private' => 'Private',
            'Auction' => 'Auction',
            'For Sale' => 'For Sale',
            'For Auction' => 'For Auction',
            'For Tender' => 'For Tender',
            'New - Just Built' => 'New - Just Built',
            'Off' => 'Off',
            '' => 'None'
         ),
         'columns' => '6',
         'tab' => 'details',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      9,
      array(
         'id' => 'REAL_HOMES_property_class_myhome',
         'name' => 'Property class',
         'type' => 'select',
         'options' => array(
            'Residential' => 'Residential',
            'NewHomes' => 'NewHomes',
            'Lettings' => 'Lettings',
            'Commercial' => 'Commercial',
            'Overseas' => 'Overseas',
         ),
         'columns' => '6',
         'tab' => 'details',
      )
   );
   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      10,
      array(
         'id' => 'REAL_HOMES_property_companygroup_myhome',
         'name' => 'Account',
         'type' => 'select',
         'options' => array(
            'Ray Cooke Auctioneers Clondalkin|7031' => 'Ray Cooke Auctioneers Clondalkin',
            'Ray Cooke Auctioneers Terenure|369224' => 'Ray Cooke Auctioneers Terenure',
            'Ray Cooke Auctioneers Tallaght|254910' => 'Ray Cooke Auctioneers Tallaght',
            'Ray Cooke Auctioneers Finglas|386630' => 'Ray Cooke Auctioneers Finglas',
            'Ray Cooke Test|387583' => 'Ray Cooke Test',
         ),
         'columns' => '6',
         'tab' => 'details',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      11,
      array(
         'type' => 'divider',
         'columns' => '12',
         'tab' => 'details',
		    'id' => 'ms-divider-01',
      )
   );



   $REAL_HOMES_property_address_key_position = intval( array_search( 'REAL_HOMES_property_address', array_column( $property_metabox_fields, 'id' ) ) );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      $REAL_HOMES_property_address_key_position + 1,
      array(
         'id' => 'REAL_HOMES_property_address_line_1',
         'name' => 'Address line 1 (street) (for myhome.ie)',
         'type' => 'text',
         'columns' => '6',
         'tab' => 'map-location',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      $REAL_HOMES_property_address_key_position + 2,
      array(
         'id' => 'REAL_HOMES_property_address_line_2',
         'name' => 'Address line 2 (area) (for myhome.ie)',
         'type' => 'text',
         'columns' => '6',
         'tab' => 'map-location',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      $REAL_HOMES_property_address_key_position + 3,
      array(
         'id' => 'REAL_HOMES_property_address_line_3',
         'name' => 'Address line 3 (county) (for myhome.ie)',
         'type' => 'text',
         'columns' => '6',
         'tab' => 'map-location',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      $REAL_HOMES_property_address_key_position + 4,
      array(
         'id' => 'REAL_HOMES_property_eircode',
         'name' => 'Eircode (for myhome.ie)',
         'type' => 'text',
         'columns' => '6',
         'tab' => 'map-location',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      $REAL_HOMES_property_address_key_position + 5,
      array(
         'id' => 'REAL_HOMES_property_address_daft_county',
         'name' => 'County (for daft.ie)',
         'type' => 'select',
         // 'std' => 'thumb-on-right',
         'options' => array(
            '1' => 'Co. Dublin',
            '2' => 'Co. Meath',
            '3' => 'Co. Kildare',
            '4' => 'Co. Wicklow',
            '5' => 'Co. Longford',
            '6' => 'Co. Offaly',
            '7' => 'Co. Westmeath',
            '8' => 'Co. Laois',
            '9' => 'Co. Louth',
            '10' => 'Co. Carlow',
            '11' => 'Co. Kilkenny',
            '12' => 'Co. Waterford',
            '13' => 'Co. Wexford',
            '14' => 'Co. Kerry',
            '15' => 'Co. Cork',
            '16' => 'Co. Clare',
            '17' => 'Co. Limerick',
            '18' => 'Co. Tipperary',
            '19' => 'Co. Galway',
            '20' => 'Co. Mayo',
            '21' => 'Co. Roscommon',
            '22' => 'Co. Sligo',
            '23' => 'Co. Leitrim',
            '24' => 'Co. Donegal',
            '25' => 'Co. Cavan',
            '26' => 'Co. Monaghan',
            '27' => 'Co. Antrim',
            '28' => 'Co. Armagh',
            '29' => 'Co. Tyrone',
            '30' => 'Co. Fermanagh',
            '31' => 'Co. Derry',
            '32' => 'Co. Down',
         ),
         'columns' => '12',
         'tab' => 'map-location',
      )
   );

   $property_metabox_fields = ms_array_insert(
      $property_metabox_fields,
      $REAL_HOMES_property_address_key_position + 6,
      array(
         'type' => 'divider',
         'columns' => '12',
         'tab' => 'map-location',
      )
   );

   return $property_metabox_fields;
}, 9999 );

// add_filter( 'ere_agent_meta_boxes', function( $agent_metabox_fields ) {
//    foreach ( $agent_metabox_fields as $index => $metabox ) {
//       if ( isset( $metabox['id'] ) && $metabox['id'] == 'agent-meta-box' ) {

//          $general_tab = array(
//             'label' => 'General',
//             'icon' => 'dashicons-admin-generic'
//          );
//          ms_array_unshift_assoc( $agent_metabox_fields[$index]['tabs'], 'general', $general_tab );

//          $agent_id_field = array(
//             'id' => 'REAL_HOMES_agent_id_daft',
//             'name' => 'Agent ID (for daft.ie)',
//             'type' => 'text',
//             'columns' => '12',
//             'tab' => 'general',
//          );
//          array_unshift( $agent_metabox_fields[$index]['fields'], $agent_id_field );
//       }
//    }
//    return $agent_metabox_fields;
// }, 9999 );

function ms_array_unshift_assoc( &$arr, $key, $val ) {
   $arr = array_reverse( $arr, true );
   $arr[$key] = $val;
   $arr = array_reverse( $arr, true );
   return count( $arr );
} 

function ms_array_insert( $array, $index, $val ) {
   $size = count( $array ); //because I am going to use this more than one time
   if ( ! is_int( $index ) || $index < 0 || $index > $size ) {
      return -1;
   } else {
      $temp = array_slice( $array, 0, $index );
      $temp[] = $val;
      return array_merge( $temp, array_slice( $array, $index, $size ) );
   }
}
