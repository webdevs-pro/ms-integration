<?php
/**
 * Plugin Name: MS Integration
 * Version: 0.0.2
 */


class MS_Integration {

   public function __construct() {
      add_filter( 'bulk_actions-edit-property', array( $this, 'register_integration_actions' ) );
      add_filter( 'handle_bulk_actions-edit-property', array( $this, 'bulk_action_handler' ), 10, 3 );
      add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
      add_action( 'post_submitbox_misc_actions', array( $this, 'add_publish_meta_options' ) );
      add_action( 'save_post', array( $this, 'extra_publish_meta_options_save' ), 10 , 3) ;
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
      $bulk_actions['publish_on_servises'] = 'Publish on services';
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
         array( 'bulk_publishd_properties', 'bulk_removed_properties' ),
         $redirect_to
      );

      // publish
      if ( $doaction === 'publish_on_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'publishd_on_services', 1 );
         }
         $redirect_to = add_query_arg( 'bulk_publishd_properties', count( $post_ids ), $redirect_to );
      }

      // remove
      if ( $doaction === 'remove_from_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'publishd_on_services', '' );
         }
         $redirect_to = add_query_arg( 'bulk_removed_properties', count( $post_ids ), $redirect_to );
      }

      include( 'daft.php' );
      Daft::update_service( $post_ids );

      // include( 'my-home.php' );
      // MyHome::update_service( $post_ids );

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

   public function add_publish_meta_options( $post_obj ) {
      $value = get_post_meta( $post_obj->ID, 'publishd_on_services', true );
   
      if ( 'property' == $post_obj->post_type ) {
         ?>
         <div class="misc-pub-section misc-pub-section-last" style="padding: 10px; background-color: #e1ffe1;">
            <h4>Publish this property on services?</h4>
            <label>
               <input type="checkbox"<?php echo $value ? ' checked="checked" ' : ''; ?> value="1" name="publishd_on_services" />
               <b>Publish</b>
            </label>
            <p>If this box is checked, this property will be published on DAFT and MYHOME services othervise properties will be removed from services.</p>

         </div>
         <?php
      }
   }
   
   
   
   public function extra_publish_meta_options_save( $post_id, $post, $update ) {
      if ( 'property' != $post->post_type ) {
         return;
      }
   
      if ( wp_is_post_revision( $post_id ) ) {
         return;
      }
   
      if ( isset( $_POST['publishd_on_services'] ) && $_POST['publishd_on_services'] == 1 ) {
         update_post_meta( $post_id, 'publishd_on_services', $_POST['publishd_on_services'] );
      } else {
         update_post_meta( $post_id, 'publishd_on_services', '' );
      }

      include( 'daft.php' );
      Daft::update_service( (array) $post_id );

      // include( 'my-home.php' );
      // MyHome::update_service( (array) $post_id );

   }

}
new MS_Integration();