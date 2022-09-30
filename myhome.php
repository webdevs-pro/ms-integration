<?php

// if ( class_exists( 'Daft' ) ) {
//    return;
// }

class MSIMyHome {

   public static function publish( $post_ids = [] ) {
      $api_url = 'https://s-feedin.myhome.ie/v2/property';
      $token = self::get_token();
      $company_group = '387583';
      $company_name = 'Ray Cooke Test';

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

         $title = get_the_title( $post_id );
         $location_meta = get_post_meta( $post_id, 'REAL_HOMES_property_location', true );
         $coordinates_arr = explode( ',', $location_meta );

         $addres_arr = explode( ',', $title );

         // $ref_id = get_post_meta( $post_id, 'REAL_HOMES_property_id', true );
         $ref_id = '13571-test-3';

         $price = get_post_meta( $post_id, 'REAL_HOMES_property_price', true );

         $size = get_post_meta( $post_id, 'REAL_HOMES_property_size', true );

         $response = wp_remote_post( $api_url, array(
            'blocking' => true,
            'headers' => array(
               'Accept'=> 'application/json',
               'Content-Type'=> 'application/json',
               'Authorization' => 'Bearer ' . $token,
            ),
            'body' => '{
               "Property":{
                  "Prop_Accommodation": "' . $description . '",
                  "Prop_Address1": "' . $title . '",
                  "Prop_Address2": "",
                  "Prop_Address3": "",
                  "Prop_Bathrooms": "' . get_post_meta( $post_id, 'REAL_HOMES_property_bathrooms', true ) . '",
                  "Prop_Bedrooms": "' . get_post_meta( $post_id, 'REAL_HOMES_property_bedrooms', true ) . '",
                  "Prop_Class": "Residential",
                  "Prop_CompanyGroup": "' . $company_group . '",
                  "Prop_CompanyName": "' . $company_name . '",
                  "Prop_Eircode": "D10P956",
                  "Prop_FullDescription": "Some cool description of this property",
                  "Prop_Price": "' . $price . '",
                  "Prop_Size": "' . $size . '",
                  "Prop_RefId": "' . $ref_id . '",
                  "Prop_SaleOrRent": "let",
                  "Prop_SaleType": "Private",
                  "Prop_ShowPrice": "Y",
                  "Prop_Status": "' . $status . '",
                  "Prop_Type": "Site"
               }
            }',
         ) );

         error_log( "publish response\n" . print_r( $response['body'], true ) . "\n" );
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

   private static function get_token() {
      $stored_token = get_option( 'myhome_api_token' );

      if ( ( isset( $stored_token['time'] ) && $stored_token['time'] + 3600 < time() ) || ! isset( $stored_token['time'] ) ) {
         $new_token = array(
            'token' => self::get_new_token(),
            'time' => time()
         );

         update_option( 'myhome_api_token', $new_token );

         return $new_token['token'];
      }

      return $stored_token['token'];
   }

   private static function get_new_token() {
      $url = 'https://s-identity.myhome.ie/connect/token';

      $request = wp_remote_post( $url, array(
         'body' => array(
            'grant_type' => 'password',
            'client_id' => 'feedin-provider',
            'client_secret' => '6b0a19fe',
            'username' => 'property-provider-test',
            'password' => 'd8SZMk.h$',
         )
      ) );

      if ( ! is_wp_error( $request ) ) {
         $response_arr = json_decode( wp_remote_retrieve_body( $request ) );

         if ( isset( $response_arr->access_token ) ) {
            // error_log( "response_arr['access_token']\n" . print_r( $response_arr->access_token, true ) . "\n" );
            return $response_arr->access_token;
         }
      }
   }

}