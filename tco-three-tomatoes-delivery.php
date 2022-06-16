<?php
/**
 * Plugin Name: Three Tomatoes Catering Delivery
 * Plugin URI: https://theme.co/
 * Description: Official plugin housing customized functions built to satisfy the business process of Three Tomatoes
 * Version: 1.0.0.0
 * Author: ThemeCo Elite
 * Author URI: https://theme.co/
 * Text Domain: tco_three_tomatoes
 *
 * @package TCoThreeTomatoesCateringDelivery
 */

function tco_ttcd_curl_delivery($data, $post_type = 'posts') {
    $username = 'johndoe';
    $password = 'CK2L g6Jd ay6n Ffq8 0fAt 1hVU';

    $url = get_field('three_tomatoes_main_url', 'option' );

    $curl_handle=curl_init();
//    curl_setopt($curl_handle,CURLOPT_URL,'http://three-tomatoes.local/wp-json/wp/v2/catering');
    curl_setopt($curl_handle,CURLOPT_URL,$url . 'wp-json/wp/v2/' . $post_type);
    // curl -X POST --user username:password http://yourdomain.com/wp-json/wp/v2/posts/PostID -d '{"title":"My New Title"}'
    //Specify the username and password using the CURLOPT_USERPWD option.
    curl_setopt($curl_handle, CURLOPT_USERPWD, $username . ":" . $password);
    curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
    curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);


    $payload = json_encode( $data );
    curl_setopt( $curl_handle, CURLOPT_POSTFIELDS, $payload );
    curl_setopt( $curl_handle, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    $buffer = curl_exec($curl_handle);
    curl_close($curl_handle);

//    if (empty($buffer)){
//        print "Nothing returned from url.";
//    }
//    else{
//        print "<pre>$buffer</pre>";
//    }
//    die();

}

add_action('woocommerce_thankyou', 'tco_ttcd_create_delivery_post', 10, 1);

function tco_ttcd_create_delivery_post($order_id) {

    if ( ! $order_id )
        return;

    // Allow code execution only once
    if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {

        // Get an instance of the WC_Order object
        $order = wc_get_order( $order_id );

        // Get the order key
        $order_key = $order->get_order_key();

        // Get the order number
        $order_key = $order->get_order_number();

        if($order->is_paid())
            $paid = __('yes');
        else
            $paid = __('no');

        $customer_name = get_post_meta($order_id,'_billing_first_name', true) . " " . get_post_meta($order_id,'_billing_last_name', true);

        $delivery_title = "#{$order_id} $customer_name";
        $status = $order->is_paid() ? 'publish' : 'draft';

        $times = get_post_meta($order_id,'_delivery_window', true);
        // Retrieve start time:
        $time_window = explode(':', $times);

        switch($time_window[0]){
            case 'Breakfast':
                $start_time = '5:00 AM';
                $end_time = '8:00 AM';
                break;
            case 'Lunch':
                $start_time = '10:30 AM';
                $end_time = '12:30 PM';
                break;
            case 'Dinner':
                $start_time = '4:00 PM';
                $end_time = '6:30 PM';
                break;
        }

        /*
        Breakfast: 5:00 AM - 8:00 AM
        Lunch: 10:30 AM - 12:30 PM
        Dinner: 4:00 PM - 6:30 PM
        */

        $delivery_date = get_post_meta($order_id,'_delivery_date', true);

        $delivery_date = date_i18n('Ymd', strtotime($delivery_date));

        $delivery_post = array(
            'title' => $delivery_title,
            'status' => $status,
            'meta' => [
                'start_date'    => $delivery_date,
                'end_date'      => $delivery_date,
                'start_time'    => $start_time,
                'end_time'      => $end_time,
                'order_id'      => $order_id,
                'customer_name' => $customer_name
            ]
        );

        $custom_order_details = array(
            'Do you need basic plastic utensils with your food delivery?' => get_post_meta($order_id,'_utensils_condiments', true),
            'Order total'       => get_post_meta($order_id,'_order_total', true),
            'Order tax'         => get_post_meta($order_id,'_order_tax', true),
            'Payment method'    => get_post_meta($order_id,'_payment_method_title', true),
            'Order Items'       => []
        );

        // Loop through order items
        foreach ( $order->get_items() as $item_id => $item )
        {
            $custom_order_details['Order Items'][] = $item->get_name() . ' x' . $item->get_quantity();
        }

        $delivery_post['meta']['custom_order_details'] = $custom_order_details;

        tco_ttcd_curl_delivery($delivery_post, 'delivery');

        $order->update_meta_data( '_thankyou_action_done', true );
        $order->save();
    }

}

add_action('acf/init', 'tco_ttcd_add_themes_options_page' );

function tco_ttcd_add_themes_options_page()
{
    // Check function exists.
    if (function_exists('acf_add_options_page')) {

        // Register options page.
        $option_page = acf_add_options_page(array(
            'page_title' => __('Three Tomatoes Delivery Settings'),
            'menu_title' => __('Three Tomatoes'),
            'menu_slug' => 'tco-ttcd-settings',
            'capability' => 'edit_posts',
            'redirect' => false
        ));
    }
}