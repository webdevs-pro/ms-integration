<?php
/**
 * Plugin Name: MS Integration
 * Version: 0.0.1
 */


class MS_Integration {

   public function __construct() {
      add_filter( 'bulk_actions-edit-property', array( $this, 'register_integration_actions' ) );
      add_filter( 'handle_bulk_actions-edit-property', array( $this, 'bulk_action_handler' ), 10, 3 );
      add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
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
      $bulk_actions['expose_on_servises'] = 'Expose on services';
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
      if ( ! in_array( $doaction, ['expose_on_servises', 'remove_from_servises'] ) ) {
         return $redirect_to;
      }

      // let's remove query args first
      $redirect_to = remove_query_arg(
         array( 'bulk_exposed_properties', 'bulk_removed_properties' ),
         $redirect_to
      );

      // expose
      if ( $doaction === 'expose_on_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'exposed_on_services', 1 );
         }
         $redirect_to = add_query_arg( 'bulk_exposed_properties', count( $post_ids ), $redirect_to );
      }

      // remove
      if ( $doaction === 'remove_from_servises' ) {
         foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'exposed_on_services', '' );
         }
         $redirect_to = add_query_arg( 'bulk_removed_properties', count( $post_ids ), $redirect_to );
      }

      include( 'daft.php' );
      Daft::update_service();

      // include( 'my-home.php' );
      // MyHome::update_service();

      return $redirect_to;
   }
}
new MS_Integration();