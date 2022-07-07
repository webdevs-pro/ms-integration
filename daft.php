<?php

// if ( class_exists( 'Daft' ) ) {
//    return;
// }

class Daft {

   public static function update_service( $post_ids = [] ) {

      $XML = new DomDocument('1.0', 'ISO-8859-1'); 

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
               'field'    => 'slug',
               'terms'    => ['for-sale']
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

            // area
            $area_terms = wp_get_post_terms( $post_id, 'property-city', ['fields' => 'names'] );
            if ( $area_terms ) {
               $area_terms_string = implode( ', ', $area_terms );
               $saleAdElement->appendChild( $XML->createElement( 'area', $area_terms_string ) );
            }


            // append
            $salesElement->appendChild( $saleAdElement );
         }
      }


  
      $XML->formatOutput = true;

      // $XML->saveXML();
      $XML->save('../raycooke.xml');
   }
}