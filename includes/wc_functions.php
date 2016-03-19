<?php
/**
 *
 * Create Woocommerce new state for order
 * 
 */

function wc_create_custom_order_state() {
	register_post_status( 'wc-want-be-returned', array(
    'label'                     => 'Want to be returned',
    'public'                    => true,
    'exclude_from_search'       => false,
    'show_in_admin_all_list'    => true,
    'show_in_admin_status_list' => true,
    'label_count'               => _n_noop( 'Want to be returned <span class="count">(%s)</span>', 'Want to be returned <span class="count">(%s)</span>' )
  ) );
}

/**
 *
 * Add to a list of WC Order statuses
 *
 */

add_filter( 'wc_order_statuses', 'wc_must_returned' );

function wc_must_returned( $order_statuses ) {
  $new_order_statuses = array();

  // add new order status after processing
  foreach ( $order_statuses as $key => $status ) {
    $new_order_statuses[ $key ] = $status;
    if ( 'wc-completed' === $key ) {
      $new_order_statuses['wc-want-be-returned'] = 'Want to be returned';
    }
  }

  return $new_order_statuses;
}

/**
 *
 * Style of order status icon
 *
 */

add_action( 'wp_print_scripts', 'wc_add_custom_order_status_icon' );
function wc_add_custom_order_status_icon() {
	if( ! is_admin() ) return;
	
	?> <style>
		/* Add custom status order icons */
		.column-order_status mark.want-be-returned,
		.column-order_status mark.building {
			content: url(<?php echo plugins_url(); ?>/wc-return-product/assets/CustomOrderStatus.png);
		}
	
		/* Repeat for each different icon; tie to the correct status */
 
	</style> <?php
}

add_action( 'wc_before_send_email', 'wc_change_order_status', 10, 2 );

function wc_change_order_status( $order_id, $note ) {
	if (!$order_id) return;

	$order = new WC_Order( $order_id );
	$order->update_status('wc-want-be-returned');
	$order->add_order_note($note, 1);
}