<?php

// if ( class_exists( 'Daft' ) ) {
//    return;
// }

class MSIMyHome {

   public static function publish( $post_ids = [] ) {

      $api_url = 'https://s-feedin.myhome.ie/v2/property';
      $api_key = '5f4bc74f-8d9a-41cb-ab85-a1b7cfc86622';
      // $api_key = '';

      foreach ( $post_ids as $post_id ) {

         $post = get_post( $post_id );
         $description = $post->post_content;

         $property_status_terms = wp_get_post_terms( $post_id, 'property-status', ['fields' => 'slugs'] );
         switch ( $property_status_terms[0] ) {
            case 'for-rent':
               $status = 'A';
               break;

            case 'for-sale':
               $status = 'A';
               break;
            
            case 'let-agreed':
               $status = 'A';
               break;
            
            case 'sale-agreed':
               $status = 'SA';
               break;
            
            case 'sold':
               $status = 'S';
               break;
            
            default:
               $status = 'A';
               break;
         }

         $location_meta = get_post_meta( $post_id, 'REAL_HOMES_property_location', true );
         $coordinates_arr = explode( ',', $location_meta );

         $response = wp_remote_post( $api_url, array(
            'timeout' => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
               'Accept'=> 'application/json',
               'Content-Type'=> 'application/json',
               'Authorization' => $api_key,
            ),
            'body' => '{
               "Prop_Accommodation": "' . $description . '",
               "Prop_Address1": "' . get_the_title( $post_id ) . '",
               "Prop_Bathrooms": "' . get_post_meta( $post_id, 'REAL_HOMES_property_bathrooms', true ). '",
               "Prop_Bedrooms": "' . get_post_meta( $post_id, 'REAL_HOMES_property_bedrooms', true ). '",
               "Prop_Price": "' . get_post_meta( $post_id, 'REAL_HOMES_property_price', true ). '",
               "Prop_RefId": "' . get_post_meta( $post_id, 'REAL_HOMES_property_id', true ). '",
               "Prop_Size": "' . get_post_meta( $post_id, 'REAL_HOMES_property_size', true ). '",
               "Prop_Status": "' . $status . '",
               }',
         ) );

         // error_log( "response\n" . print_r( $response, true ) . "\n" );
      }

   }


   public static function remove( $post_ids = [] ) {

      // $api_key = '5f4bc74f-8d9a-41cb-ab85-a1b7cfc86622';
      $api_key = '';
      
      foreach ( $post_ids as $post_id ) {
         
         $post = get_post( $post_id );
         $description = $post->post_content;
         
         $property_id = get_post_meta( $post_id, 'REAL_HOMES_property_id', true );

         $api_url = 'https://s-feedin.myhome.ie/v2/property/' . $property_id;

         $location_meta = get_post_meta( $post_id, 'REAL_HOMES_property_location', true );
         $coordinates_arr = explode( ',', $location_meta );


         $response = wp_remote_post( $api_url, array(
            'timeout' => 5,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
               'Accept'=> 'application/json',
               'Content-Type'=> 'application/json',
               'Authorization' => $api_key,
            ),
            'body' => '{
               "Prop_Accommodation": "' . $description . '",
               "Prop_Address1": "' . get_the_title( $post_id ) . '",
               "Prop_Bathrooms": "' . get_post_meta( $post_id, 'REAL_HOMES_property_bathrooms', true ) . '",
               "Prop_Bedrooms": "' . get_post_meta( $post_id, 'REAL_HOMES_property_bedrooms', true ) . '",
               "Prop_Latitude": "' . $coordinates_arr[0] ?? '' . '",
               "Prop_Longitude": "' . $coordinates_arr[1] ?? '' . '",
               "Prop_Price": "' . get_post_meta( $post_id, 'REAL_HOMES_property_price', true ) . '",
               "Prop_RefId": "' . $property_id . '",
               "Prop_Size": "' . get_post_meta( $post_id, 'REAL_HOMES_property_size', true ) . '",
               "Prop_Status": "D",
               }',
         ) );

         // error_log( "response\n" . print_r( $response, true ) . "\n" );
      }

   }

}