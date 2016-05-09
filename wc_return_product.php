<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/*
Plugin Name: WC Return products
Plugin URL: http://castillogomez.com/
Description: Adds a form to order for return product
Version: 1.3.3
Author: Paco Castillo
Author URI: http://castillogomez.com/
Text Domain: wc_return
Domain Path: languages
*/ 

/**
 * Check if WooCommerce is active
 **/
if (!function_exists('is_plugin_active_for_network'))
  require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || is_plugin_active_for_network('woocommerce/woocommerce.php') ) {

  require_once( plugin_dir_path( __FILE__ ) . 'includes/wc_functions.php' );
  
  add_action('wp_head','wc_return_products_ajaxurl');

  function wc_return_products_ajaxurl() {
    echo '<script type="text/javascript">';
    echo 'var wc_return_ajaxurl = "' , admin_url('admin-ajax.php') , '";';
    echo '</script>';
  }

  add_action( 'init', 'wc_return_form_init' );

  function wc_return_form_init() {
    load_theme_textdomain('wc_return', plugin_dir_path( __FILE__ ) . 'languages');
    wp_enqueue_script( 'wc_return_form', plugins_url( 'assets/wc_return_form.js', __FILE__ ) , array('jquery'), false, true );
    wc_create_custom_order_state();
  }

  // Add form to each order in user account
  add_action( 'woocommerce_order_details_after_order_table', 'wc_return_form_template', 5 , 1 );

  function wc_return_form_template( $order ) {
    // Get WooCommerce Global
    global $woocommerce;

    // if order status is not selected in wc return options
    if (!in_array( 'wc-' . $order->get_status(), get_option( 'wc_return_statuses' ) ))
      return;
    // is order date is out of range of number of days in wc return options
    $last_day = (get_option( 'wc_return_days' ) == '') ? '' : date('Y-m-d', strtotime($order->post->post_date . ' + '. get_option( 'wc_return_days' ) .' days'));
    if ( ($last_day != '') && ($last_day < date("Y-m-d")) )
      return;

    $products = $order->get_items();
    echo '<a class="button return-form-product" href="#">' . __('Return order','wc_return') . '</a>';
    ?>
    <form id="wc-form-return" action="" method="post">
      <label><?php _e('Select products for return','wc_return') ?></label>
      <select id="wc_products[]" name="wc_products" class="wc_products" multiple="multiple">
        <?php
        if ( sizeof( $products ) > 0 ) { 
          foreach( $products as $item ) { ?>
            <option value="<?php echo $item['item_meta']['_product_id'][0]; ?>"><?php echo __(esc_html($item['name']), 'wc_return'); ?></option>
          <?php 
          }
        }
        ?>
      </select>
      <small><?php _e('You can select multiple by holding down the CMD or Ctrl key.','wc_return'); ?></small>
      <input type="hidden" name="order" value="<?php echo $order->id; ?>" />
      <input type="hidden" name="customer" value="<?php echo $order->billing_email; ?>" />
      <input type="submit" name="submit" value="<?php _e('Submit','wc_return'); ?>" />
    </form>
    <div class="message"></div>
    <?php
  }

  // Send form AJAX --------------------------
  if (defined('DOING_AJAX') && DOING_AJAX) { 

    add_action( 'wp_ajax_wc_return_form', 'send_wc_return_form' );
    add_action( 'wp_ajax_nopriv_wc_return_form', 'send_wc_return_form' );

    function send_wc_return_form()
    { 
      global $woocommerce;
      $customer = $woocommerce->customer;
      $json = array();
      $json['result'] = false;
      $to = is_email( sanitize_email( $_POST['customer'] ) );
      $order = new WC_Order( (int)$_POST['order'] );
      $order_id = ($order != null) ? $_POST['order'] : false;

      // check if selected some product
      if ( count($_POST['wc_products']) == 0  ) {
        $json['response'] = __('You must select some product','wc_return');
      }
      else if ( !$to ) {
        $json['response'] = __('You must enter a valid email','wc_return'); 
      }
      else if ( !$order_id ) {
        $json['response'] = __('You must enter a valid order id','wc_return');  
      }
      else {
        $headers = 'From: '.$to."\r\n".
            'Reply-To: '.$to."\r\n".
            'X-Mailer: PHP/'.phpversion()."\r\n".
            'Content-Type: text/html; charset=UTF-8';

        $to = (get_option( 'wc_return_email' ) != '') ? get_option( 'wc_return_email' ) : get_option( 'admin_email' );
        $subject = __('Return Order in ','wc_return') . get_bloginfo( 'name', 'display' );

        ob_start();
        echo $subject;
        $email_heading = __('Return Order ', 'wc_return') . $order_id;
        include_once(dirname(__FILE__) . "/template/email_header.php");

        $products = array();
        $items = $order->get_items();
        // Create note for add to order
        $note = __('These products want to be returned: ', 'wc_return');
        $n_prod = 0;
        foreach ($_POST['wc_products'] as $prod_id) {
          $p = new WC_Product( (int)sanitize_text_field($prod_id) );
          if ( $p ) {
            $note .= (!$n_prod) ? $p->get_title() : ', '.$p->get_title();
            $n_prod++;
            array_push($products, $p->id );
          }
        }
        include_once(dirname(__FILE__) . "/template/email_items.php");
        
        include_once(dirname(__FILE__) . "/template/email_footer.php");
        $message = ob_get_contents();
        ob_end_clean();

        // get CSS styles
        ob_start();
        include_once(dirname(__FILE__) . "/template/email_styles.php");
        $css = ob_get_contents();
        ob_end_clean();
        $css = apply_filters( 'woocommerce_email_styles', $css );
        // apply CSS styles inline for picky email clients
        include_once(plugin_dir_path( __FILE__ ) . '/../woocommerce/includes/libraries/class-emogrifier.php');
        $emogrifier = new Emogrifier( $message, $css );
        $message = $emogrifier->emogrify();

        add_filter( 'wp_mail_content_type', 'wc_return_form_set_html_content_type' );
        $send = wp_mail($to, $subject, $message, $headers);
        $send = 1;
        remove_filter( 'wp_mail_content_type', 'wc_return_form_set_html_content_type' );

        // Change status and add note to order
        do_action('wc_before_send_email', $order_id, $note);

        $json['send'] = $send;
        if ($send) {
          $json['response'] = __('Your order return request was send successfully and we contact you soon. Thank you.','wc_return');
          $json['result'] = true;
        } else {
          $json['response'] = __('Has encountered an unexpected error and was not able to send email','wc_return');
        }

      }
      echo json_encode($json);
      die();
    }

    function wc_return_form_set_html_content_type() {
      return 'text/html';
    }
  }

  // Admin menu and page ---------------
  add_action('admin_menu', 'wc_return_form_menu');

  function wc_return_form_menu() {
      add_submenu_page( 'woocommerce', 'WC Return products Options', 'WC Return products', 'manage_options', 'wc-return-form-menu', 'wc_return_form_menu_callback' ); 
  }

  function wc_return_form_menu_callback() {
    if ( !current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    else {
      $the_email = get_option( 'wc_return_email' );
      $days = get_option( 'wc_return_days' );
      if ( $_POST ) {
        $save = null;
        if ( isset( $_POST['wc_return_email'] ) ) {
          $the_email = sanitize_email( $_POST['wc_return_email'] );
          if ( is_email( $the_email ) ) {
            update_option( 'wc_return_email', $the_email );
            $save = true;
          }
          else {
            echo '<div class="error message">' . __('You must enter a valid email','wc-return') . '</div>';
          }
        }
        if ( isset( $_POST['wc_return_days'] ) ) {
          $days = sanitize_text_field( $_POST['wc_return_days'] );
          if ( is_numeric( $days ) ) {
            update_option( 'wc_return_days', intval( $days ) );
            $days = intval( $days );
            $save = true;
          }
          else {
            echo '<div class="error message">' . __('You must enter a valid number','wc-return') . '</div>';
          }
        }
        if( isset( $_POST['wc_return_statuses'] ) ){
          update_option('wc_return_statuses', $_POST['wc_return_statuses']);
        }
        if ( $save )
          echo '<div class="updated message">' . __('Changes saved','wc-return') . '</div>';
      }

      echo '<form action="" method="post" accept-charset="utf-8">';
      echo '<h3>WC Return Products Options</h3>';
      echo '<table class="form-table">';
      echo '  <tbody>';
      echo '    <tr>';
      echo '    <th scope="row">';
      echo '      <label for="wc_return_email">'.__('Enter email to send return orders','wc_return').'</label>';
      echo '    </th>';
      echo '    <td>';
      echo '      <input id="wc_return_email" name="wc_return_email" type="text" value="' . $the_email . '" />';
      echo '      <br>';
      echo '      <span class="description">'.__('This email will receive notices of return.','clever').'</span>';
      echo '    </td>';
      echo '    </tr>';
      echo '    <tr>';
      echo '    <th scope="row">';
      echo '      <label for="wc_return_statuses">'.__('What status must have the order?','wc_return').'</label>';
      echo '    </th>';
      echo '    <td>';
      echo '      <select name="wc_return_statuses[]" class="widefat" multiple="multiple" id="wc_return_statuses">';
      $statusses = wc_get_order_statuses();
      $statusses_sel = get_option( 'wc_return_statuses' );
      foreach ($statusses as $key => $value) {
        echo '      <option value="' . $key . '" ' . ((in_array($key, $statusses_sel)) ? 'selected="selected"' : '') . '>' . $value . '</option>';
      }
      echo '      </select>';
      echo '      <br>';
      echo '      <span class="description">'.__('The WC Return form will be available for orders with this status. You can select multiple by holding down the CMD or Ctrl key.','clever').'</span>';
      echo '    </td>';
      echo '    </tr>';
      echo '    <tr>';
      echo '    <tr>';
      echo '    <th scope="row">';
      echo '      <label for="wc_return_days">'.__('How many days will be active this form after the order is completed?','wc_return').'</label>';
      echo '    </th>';
      echo '    <td>';
      echo '      <input id="wc_return_days" name="wc_return_days" type="number" value="' . $days . '" />';
      echo '      <br>';
      echo '      <span class="description">'.__('Number of days that the form will be active after the order has been completed.','clever').'</span>';
      echo '    </td>';
      echo '    </tr>';
      echo '  </tbody>';
      echo '</table>';
      echo '    <p class="submit"><input type="submit" value="Save Changes" class="button-primary" name="Submit"></p>';
      echo '</form>';
    }
  }
}

function wc_error_email() {
  echo '<div class="error">';
  echo '  <p>' . __( 'You must enter a valid email', 'wc-return' ) . '</p>';
  echo '</div>';
}
function wc_error_number() {
  echo '<div class="error">';
  echo '  <p>' . __( 'You must enter a valid number', 'wc-return' ) . '</p>';
  echo '</div>';
}