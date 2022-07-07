<?php

if ( class_exists( 'Daft' ) ) {
   return;
}

class Daft {

   public static function update_service( $post_ids = [] ) {

      error_log( "Daft updated, posts: \n" . print_r( $post_ids, true ) . "\n" );
   }
}