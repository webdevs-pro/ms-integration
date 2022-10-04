<?php

// if ( class_exists( 'Daft' ) ) {
//    return;
// }

class MSIMyHome {

   private static $api_url = 'https://s-feedin.myhome.ie/v2/';
   private static $company_group = '387583';
   private static $company_name = 'Ray Cooke Test';

   public static function update_service( $post_ids = [], $action = '' ) {
      $service_properties = self::get_properties_from_service();

      foreach ( $post_ids as $post_id ) {

         $ref_id = get_post_meta( $post_id, 'REAL_HOMES_property_id', true );

         if ( in_array( $ref_id, $service_properties ) ) {
            self::update_property( $post_id, $action );
         } else {
            self::publish_property( $post_id );
         }

      }


   }




   public static function get_property_data( $post_id ) {
      $post = get_post( $post_id );

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

      $content = $post->post_content;
      $content = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $content );
      $content = preg_replace('#\[[^\]]+\]#', '', $content );
      $content = str_replace( ["\n", "\r"], '<br>', $content );
      $content = str_replace( ["<br><br><br><br>", "<br><br><br>", "<br><br>"], '<br>', $content );
      $content = str_replace( ["\n", "\r", "\t", "</li><br>", "&nbsp;", "/li&gt;"], '', $content );
      $content = str_replace( "<ul><br>", '<ul>', $content );
      $content = str_replace( "</ul><br>", '</ul>', $content );
      $content = str_replace( '"', "'", $content );
      $content = strip_tags( $content, '<div><span><br><b><strong><ul><ol><li><i><u>' );





      return array(
         'description' => $post->post_content,
         'status' => $status,
         'title' => $post->post_title,
         'price' => get_post_meta( $post_id, 'REAL_HOMES_property_price', true ),
         'size' => get_post_meta( $post_id, 'REAL_HOMES_property_size', true ),
         'ref_id' => get_post_meta( $post_id, 'REAL_HOMES_property_id', true ),
         'bathrooms' => get_post_meta( $post_id, 'REAL_HOMES_property_bathrooms', true ),
         'bedrooms' => get_post_meta( $post_id, 'REAL_HOMES_property_bedrooms', true ),
         'content' => $content
      );
   }




   public static function publish_property( $post_id ) {
      $token = self::get_token();
      $property_data = self::get_property_data( $post_id );

      $response = wp_remote_post( self::$api_url . 'property', array(
         'blocking' => true,
         'headers' => array(
            'Accept'=> 'application/json',
            'Content-Type'=> 'application/json',
            'Authorization' => 'Bearer ' . $token,
         ),
         'body' => '{
            "Property":{
               "Prop_Address1": "' . $property_data['title'] . '",
               "Prop_Address2": "",
               "Prop_Address3": "",
               "Prop_Bathrooms": "' . $property_data['bathrooms'] . '",
               "Prop_Bedrooms": "' . $property_data['bedrooms'] . '",
               "Prop_Class": "Residential",
               "Prop_CompanyGroup": "' . self::$company_group . '",
               "Prop_CompanyName": "' . self::$company_name . '",
               "Prop_Eircode": "D10P956",
               "Prop_FullDescription": "' . $property_data['content'] . '",
               "Prop_Price": "' . $property_data['price'] . '",
               "Prop_Size": "' . $property_data['size'] . '",
               "Prop_RefId": "' . $property_data['ref_id'] . '",
               "Prop_SaleOrRent": "let",
               "Prop_SaleType": "Private",
               "Prop_ShowPrice": "Y",
               "Prop_Status": "' . $property_data['status'] . '",
               "Prop_Type": "Site"
            }
         }',
      ) );

      error_log( "publish response\n" . print_r( $response['body'], true ) . "\n" );
   }







   public static function update_property( $post_id, $action = '' ) {
      $token = self::get_token();
      $property_data = self::get_property_data( $post_id );

      $response = wp_remote_request( self::$api_url . 'property/' . $property_data['ref_id'], array(
         'method' => 'PUT',
         'headers' => array(
            'Accept'=> 'application/json',
            'Content-Type'=> 'application/json',
            'Authorization' => 'Bearer ' . $token,
         ),
         'body' => '{
            "Property":{
               "Prop_Address1": "' . $property_data['title'] . '",
               "Prop_Address2": "",
               "Prop_Address3": "",
               "Prop_Bathrooms": "' . $property_data['bathrooms'] . '",
               "Prop_Bedrooms": "' . $property_data['bedrooms'] . '",
               "Prop_Class": "Residential",
               "Prop_CompanyGroup": "' . self::$company_group . '",
               "Prop_CompanyName": "' . self::$company_name . '",
               "Prop_Eircode": "D10P956",
               "Prop_FullDescription": "' . $property_data['content'] . '",
               "Prop_Price": "' . $property_data['price'] . '",
               "Prop_Size": "' . $property_data['size'] . '",
               "Prop_RefId": "' . $property_data['ref_id'] . '",
               "Prop_SaleOrRent": "let",
               "Prop_SaleType": "Private",
               "Prop_ShowPrice": "Y",
               "Prop_Status": "' . ( $action == 'remove' ? 'D' : $property_data['status'] ) . '",
               "Prop_Type": "Site"
            }
         }',
      ) );

      error_log( "update response\n" . print_r( $response['body'], true ) . "\n" );
   }



   private static function get_properties_from_service() {

      $token = self::get_token();

      $response = wp_remote_get( self::$api_url . 'properties/' . self::$company_group, array(
         'blocking' => true,
         'headers' => array(
            'Accept'=> 'application/json',
            'Authorization' => 'Bearer ' . $token,
         )
      ) );

      if ( ! is_wp_error( $response ) ) {

         $response_arr = json_decode( wp_remote_retrieve_body( $response ) );

         if ( isset( $response_arr->Properties ) ) {
            // error_log( "response_arr\n" . print_r( array_column( $response_arr->Properties, 'Prop_RefId' ), true ) . "\n" );
            return array_column( $response_arr->Properties, 'Prop_RefId' );
         }

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