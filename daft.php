<?php

// if ( class_exists( 'Daft' ) ) {
//    return;
// }

class MSIDaft {

   public static function update_service( $post_ids = [] ) {

      $XML = new DomDocument( '1.0', 'ISO-8859-1' ); 

      //add daft node
      $daftElement = $XML->appendChild( $XML->createElement( 'daft' ) );

      $daftVersionAttribute = $XML->createAttribute( 'version' );
      $daftVersionAttribute->value = '1';
      $daftElement->appendChild( $daftVersionAttribute );


      // sales
      $sales_posts = get_posts( array(
         'post_type' => 'property',
         'post_status' => 'publish',
         'posts_per_page' => -1,
         'tax_query' => array(
            array(
               'taxonomy' => 'property-status',
               'field' => 'slug',
               'terms' => ['for-sale', 'sale-agreed', 'sold'],
            )
         ),
         'meta_query' => array(
            array(
               'key' => 'published_on_services',
               'value' => 1,
            )
         ),
         'fields' => 'ids',
      ) );

      if ( $sales_posts ) {
         $salesElement = $daftElement->appendChild( $XML->createElement( 'sales' ) );
         foreach ( $sales_posts as $post_id ) {
            $saleAdElement = $XML->createElement( 'sale_ad' );

            // address
            $saleAdElement->appendChild( $XML->createElement( 'address', get_the_title( $post_id ) ) );

            // location
            $location_meta = get_post_meta( $post_id, 'REAL_HOMES_property_location', true );
            if ( $location_meta ) {
               $coordinates_arr = explode( ',', $location_meta );
               $saleAdElement->appendChild( $XML->createElement( 'latitude', $coordinates_arr[0] ) );
               $saleAdElement->appendChild( $XML->createElement( 'longitude', $coordinates_arr[1] ) );
            }

            // county
            $county = get_post_meta( $post_id, 'REAL_HOMES_property_address_daft_county', true ) ?: '1';
            $saleAdElement->appendChild( $XML->createElement( 'county', $county ) );

            // property_type
            $property_type = get_post_meta( $post_id, 'REAL_HOMES_property_type', true ) ?: 'apartment';
            $saleAdElement->appendChild( $XML->createElement( 'property_type', $property_type ) );

            // house_type
            if ( $property_type == 'house' ) {
               $house_type = get_post_meta( $post_id, 'REAL_HOMES_property_house_type_daft', true ) ?: 'detached';
               $saleAdElement->appendChild( $XML->createElement( 'house_type', $house_type ) );
            }


            // area
            $area_terms = wp_get_post_terms( $post_id, 'property-city', ['fields' => 'names'] );
            if ( $area_terms ) {
               $area_terms_string = implode( ', ', $area_terms );
               $saleAdElement->appendChild( $XML->createElement( 'area', $area_terms_string ) );
            }

            // description
            $post = get_post( $post_id );
            $description = $post->post_content;
            if ( $description ) {
               $description = wp_strip_all_tags( $description );
               $description = htmlspecialchars( $description );
               $description = preg_replace('#\[[^\]]+\]#', '', $description );
               $saleAdElement->appendChild( $XML->createElement( 'description', $description ) );
            }

            // price
            $price_meta = get_post_meta( $post_id, 'REAL_HOMES_property_price', true );
            if ( $price_meta ) {
               $saleAdElement->appendChild( $XML->createElement( 'price', $price_meta ) );
            }

            $selling_type = get_post_meta( $post_id, 'REAL_HOMES_property_sale_type_daft', true );
            $saleAdElement->appendChild( $XML->createElement( 'selling_type', $selling_type ) );

            $price_type = 'region';
            $saleAdElement->appendChild( $XML->createElement( 'price_type', $price_type ) );

            // bathroom_number
            $bathrooms_number_meta = get_post_meta( $post_id, 'REAL_HOMES_property_bathrooms', true );
            if ( $bathrooms_number_meta ) {
               $saleAdElement->appendChild( $XML->createElement( 'bathroom_number', $bathrooms_number_meta ) );
            }

            // bedroom_number
            $bedrooms_number_meta = get_post_meta( $post_id, 'REAL_HOMES_property_bedrooms', true );
            if ( $bedrooms_number_meta ) {
               $saleAdElement->appendChild( $XML->createElement( 'bedroom_number', $bedrooms_number_meta ) );
            }

            // square_metres
            $square_metres_meta = get_post_meta( $post_id, 'REAL_HOMES_property_size', true );
            if ( $square_metres_meta ) {
               $saleAdElement->appendChild( $XML->createElement( 'square_metres', $square_metres_meta ) );
            }

            // agent info
            $agent_post_id = get_post_meta( $post_id, 'REAL_HOMES_agents', true );
            if ( $agent_post_id ) {
//                $daft_agent_id = get_post_meta( $agent_post_id, 'REAL_HOMES_agent_id_daft', true );
//                if ( ! $daft_agent_id ) {
//                   continue;
//                }	 
				
               // phone1, phone2
               $agent_phone_1_meta = get_post_meta( $agent_post_id, 'REAL_HOMES_mobile_number', true );
               $agent_phone_2_meta = get_post_meta( $agent_post_id, 'REAL_HOMES_office_number', true );
               if ( $agent_phone_1_meta && $agent_phone_2_meta ) {
                  $saleAdElement->appendChild( $XML->createElement( 'phone1', $agent_phone_1_meta ) );
                  $saleAdElement->appendChild( $XML->createElement( 'phone2', $agent_phone_2_meta ) );
               } elseif ( $agent_phone_1_meta && ! $agent_phone_2_meta ) {
                  $saleAdElement->appendChild( $XML->createElement( 'phone1', $agent_phone_1_meta ) );
               } elseif ( ! $agent_phone_1_meta && $agent_phone_2_meta ) {
                  $saleAdElement->appendChild( $XML->createElement( 'phone1', $agent_phone_2_meta ) );
               }

               // contact_name
               $contact_name = get_the_title( $agent_post_id );
               $saleAdElement->appendChild( $XML->createElement( 'contact_name', $contact_name ) );

               // main_email
               $main_email_meta = get_post_meta( $agent_post_id, 'REAL_HOMES_agent_email', true );
               if ( $main_email_meta ) {
                  $saleAdElement->appendChild( $XML->createElement( 'main_email', $main_email_meta ) );
               }
            }

			// agent for DAFT.IE 
			$agent_daft_id = get_post_meta( $post_id, 'REAL_HOMES_property_agent_id_daft', true );
			if ( $agent_daft_id ) {
			 $saleAdElement->appendChild( $XML->createElement( 'agent_id', $agent_daft_id ) );
			}

            // external_id
            $external_id_meta = get_post_meta( $post_id, 'REAL_HOMES_property_id', true );
            if ( $external_id_meta ) {
               $saleAdElement->appendChild( $XML->createElement( 'external_id', $external_id_meta ) );
            }

            // property_status
            $property_status_terms = wp_get_post_terms( $post_id, 'property-status', ['fields' => 'slugs'] );
            if ( $property_status_terms ) {
               $saleAdElement->appendChild( $XML->createElement( 'property_status', $property_status_terms[0] ) );
            }

            // photos
            $photos = get_post_meta( $post_id, 'REAL_HOMES_property_images' );
            if ( $photos ) {
               $photosElement = $XML->createElement( 'photos' );

               foreach ( $photos as $photo_id ) {
                  $photo_url = wp_get_attachment_url( $photo_id );

                  if ( $photo_url ) {
                     $photosElement->appendChild( $XML->createElement( 'photo', $photo_url ) );
                  }
               }
               
               $saleAdElement->appendChild( $photosElement );

            }

            // append
            $salesElement->appendChild( $saleAdElement );
         }
      }


  
      $XML->formatOutput = true;

      $XML->save( '../raycooke.xml' );
   }
}
