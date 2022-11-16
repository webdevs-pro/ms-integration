<?php

// if ( class_exists( 'Daft' ) ) {
//    return;
// }

class MSIMyHome {

   private static $api_url = 'https://feedin.myhome.ie/v2/';
   private static $company_group;
   private static $company_name;

   public static function update_service( $post_ids = [], $action = '' ) {
      foreach ( $post_ids as $post_id ) {
         $ref_id = get_post_meta( $post_id, 'REAL_HOMES_property_id', true );
         $prop_comp_meta_value = get_post_meta( $post_id, 'REAL_HOMES_property_companygroup_myhome', true );
         $prop_comp_arr = explode( "|", $prop_comp_meta_value ); 
         
         self::$company_group = $prop_comp_arr[1];
         self::$company_name = $prop_comp_arr[0];
         
         $service_properties = self::get_properties_from_service();
         // error_log( "service_properties\n" . print_r( $service_properties, true ) . "\n" );

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
            $sale_or_rent = 'Rent';
            break;

         case 'for-sale':
            $status = 'A';
            $sale_or_rent = 'Sale';
            break;
         
         case 'let-agreed':
            $status = 'A';
            $sale_or_rent = 'Rent';
            break;
         
         case 'sale-agreed':
            $status = 'SA';
            $sale_or_rent = 'Sale';
            break;
         
         case 'sold':
            $status = 'S';
            $sale_or_rent = 'Sale';
            break;
         
         default:
            $status = 'A';
            $sale_or_rent = 'Sale';
            break;
      }

      $content = $post->post_content;
      $content = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $content );
      $content = preg_replace('#\[[^\]]+\]#', '', $content );
      $content = str_replace( ["\n", "\r"], '<br>', $content );
      // $content = str_replace( ["<br><br><br><br>", "<br><br><br>", "<br><br>"], '<br>', $content );
      $content = str_replace( ["<br><br><br><br>", "<br><br><br>", "<br><br>"], "\r\n", $content );
      // $content = str_replace( ["\n", "\r", "\t", "</li><br>", "&nbsp;", "/li&gt;"], '', $content );
      $content = str_replace( [ "\t", "</li><br>", "&nbsp;", "/li&gt;"], '', $content );
      $content = str_replace( "<ul><br>", '<ul>', $content );
      $content = str_replace( "</ul><br>", '</ul>', $content );
      $content = str_replace( '"', "'", $content );
      $content = strip_tags( $content, '<div><span><br><b><strong><ul><ol><li><i><u>' );
	  
      return array(
         'description' => strtok( $content, '.' ),
         'status' => $status,
         'sale_or_rent' => $sale_or_rent,
         'title' => $post->post_title,
         'adsress_1' => get_post_meta( $post_id, 'REAL_HOMES_property_address_line_1', true ),
         'adsress_2' => get_post_meta( $post_id, 'REAL_HOMES_property_address_line_2', true ),
         'adsress_3' => get_post_meta( $post_id, 'REAL_HOMES_property_address_line_3', true ),
         'price' => get_post_meta( $post_id, 'REAL_HOMES_property_price', true ),
         'size' => get_post_meta( $post_id, 'REAL_HOMES_property_size', true ),
         'ref_id' => get_post_meta( $post_id, 'REAL_HOMES_property_id', true ),
         'bathrooms' => get_post_meta( $post_id, 'REAL_HOMES_property_bathrooms', true ),
         'bedrooms' => get_post_meta( $post_id, 'REAL_HOMES_property_bedrooms', true ),
         'eircode' => get_post_meta( $post_id, 'REAL_HOMES_property_eircode', true ),
         'class' => get_post_meta( $post_id, 'REAL_HOMES_property_class_myhome', true ),
         'type' => get_post_meta( $post_id, 'REAL_HOMES_property_type', true ),
         'sale_type' => get_post_meta( $post_id, 'REAL_HOMES_property_sale_type_myhome', true ),
         'content' => $content,
         'comp_group_name' => self::$company_name,
         'comp_group_id' => self::$company_group,
      );

   }



   private static function process_attachments( $property_data, $post_id ) {
      $token = self::get_token();
      $images = get_post_meta( $post_id, 'REAL_HOMES_property_images' );
      $images_arr = array();

      if ( $images ) {
         foreach ( $images as $index => $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );
            $images_arr[] = (object) array(
               'Prim_RefId' => $property_data['ref_id'],
               'Prim_CompanyGroup' => self::$company_group,
               'Prim_Type' => $index == 0 ? 'PM' : 'PA',
               'Prim_Filename' => $image_url,
               'Prim_Name' => $property_data['title'],
               'Prim_Status' => $property_data['status'],
               'Prim_Class' => $property_data['class']
            );
         }
      }

      $images_arr = (object) array(
         'Medias' => $images_arr,
      );

      $json = json_encode( $images_arr, JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES );
      // error_log( "json\n" . print_r( $json, true ) . "\n" );

      $response = wp_remote_request( self::$api_url . 'medias/' . $property_data['ref_id'], array(
         'method' => 'PUT',
         'headers' => array(
            'Accept'=> 'application/json',
            'Content-Type'=> 'application/json',
            'Authorization' => 'Bearer ' . $token,
         ),
         'body' => $json,
      ) );

      // error_log( "media response\n" . print_r( $response, true ) . "\n" );

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
               "Prop_Address1": "' . $property_data['adsress_1'] . '",
               "Prop_Address2": "' . $property_data['adsress_2'] . '",
               "Prop_Address3": "' . $property_data['adsress_3'] . '",
               "Prop_Bathrooms": "' . $property_data['bathrooms'] . '",
               "Prop_Bedrooms": "' . $property_data['bedrooms'] . '",
               "Prop_Class": "' . $property_data['class'] . '",
               "Prop_CompanyGroup": "' . self::$company_group . '",
               "Prop_CompanyName": "' . self::$company_name . '",
               "Prop_Eircode": "' . $property_data['eircode'] . '",
               "Prop_FullDescription": "' . $property_data['content'] . '",
               "Prop_Price": "' . $property_data['price'] . '",
               "Prop_Size": "' . $property_data['size'] . '",
               "Prop_RefId": "' . $property_data['ref_id'] . '",
               "Prop_SaleOrRent": "' . $property_data['sale_or_rent'] . '",
               "Prop_SaleType": "' . $property_data['sale_type'] . '",
               "Prop_ShowPrice": "Y",
               "Prop_Status": "' . $property_data['status'] . '",
               "Prop_Type": "' . $property_data['type'] . '"
            }
         }',
      ) );

      self::process_attachments( $property_data, $post_id );
      // error_log( "property_data\n" . print_r( $property_data, true ) . "\n" );
      // error_log( "publish response\n" . print_r( $response['body'], true ) . "\n" );
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
               "Prop_Address1": "' . $property_data['adsress_1'] . '",
               "Prop_Address2": "' . $property_data['adsress_2'] . '",
               "Prop_Address3": "' . $property_data['adsress_3'] . '",
               "Prop_Bathrooms": "' . $property_data['bathrooms'] . '",
               "Prop_Bedrooms": "' . $property_data['bedrooms'] . '",
               "Prop_Class": "' . $property_data['class'] . '",
               "Prop_CompanyGroup": "' . self::$company_group . '",
               "Prop_CompanyName": "' . self::$company_name . '",
               "Prop_Eircode": "' . $property_data['eircode'] . '",
               "Prop_FullDescription": "' . $property_data['content'] . '",
               "Prop_Price": "' . $property_data['price'] . '",
               "Prop_Size": "' . $property_data['size'] . '",
               "Prop_RefId": "' . $property_data['ref_id'] . '",
               "Prop_SaleOrRent": "' . $property_data['sale_or_rent'] . '",
               "Prop_SaleType": "' . $property_data['sale_type'] . '",
               "Prop_ShowPrice": "Y",
               "Prop_Status": "' . ( $action == 'remove' ? 'D' : $property_data['status'] ) . '",
               "Prop_Type": "' . $property_data['type'] . '"
            }
         }',
      ) );

      self::process_attachments( $property_data, $post_id );

      // error_log( "update response\n" . print_r( $action, true ) . "\n" );
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

      // error_log( "гкд\n" . print_r( self::$api_url . 'properties/' . self::$company_group, true ) . "\n" );
      // error_log( "response\n" . print_r( $response, true ) . "\n" );

      if ( ! is_wp_error( $response ) ) {

         $response_arr = json_decode( wp_remote_retrieve_body( $response ) );

         // error_log( "response_arr\n" . print_r( $response_arr, true ) . "\n" );

         if ( isset( $response_arr->Properties ) ) {
            // error_log( "response_arr\n" . print_r( array_column( $response_arr->Properties, 'Prop_RefId' ), true ) . "\n" );
            return array_column( $response_arr->Properties, 'Prop_RefId' );
         }

      }

      return [];
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
      $url = 'https://identity.myhome.ie/connect/token';

      $request = wp_remote_post( $url, array(
         'body' => array(
            'grant_type' => 'password',
            'client_id' => 'feedin-provider',
            'client_secret' => '6b0a19fe',
            'username' => 'property-provider-cdgbrand',
            'password' => 'Mp6a4Rm#G7',
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
