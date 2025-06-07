<?php
/*
Plugin Name: Cheap Money Installment
Description: განვადების მეთოდი WooCommerce-ისთვის
Version: 1.0
Author: შენი სახელი
*/

if (!defined('ABSPATH')) exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// გეითვეი
add_action('plugins_loaded', function () {
    if (class_exists('WC_Payment_Gateway')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-cheap-money-gateway.php';
    }
});

//include_once plugin_dir_path(__FILE__) . 'includes/final-approval.php';
//require_once plugin_dir_path(__FILE__) . 'admin-interface.php';

include_once plugin_dir_path(__FILE__) . 'includes/admin-order-comment.php';

// სტატუსები და როლი
require_once plugin_dir_path(__FILE__) . 'includes/custom-status-company-role.php';

// სტატისტიკა
require_once plugin_dir_path(__FILE__) . 'includes/cheap-money-stats.php';

// სტატუსები და როლი
require_once plugin_dir_path(__FILE__) . 'includes/custom-status-company-role.php';

// კომპანიის "განვადების სია" გვერდი
require_once plugin_dir_path(__FILE__) . 'includes/admin-orders-view.php';

// კომპანიის "შეზღუდვა" გვერდებზე
require_once plugin_dir_path(__FILE__) . 'includes/functions-company-restrict-menu.php';


// გეითვეის რეგისტრაცია WooCommerce-ში
add_filter('woocommerce_payment_gateways', function($methods) {
    $methods[] = 'WC_Gateway_Cheap_Money';
    return $methods;
});


add_action('wp_ajax_get_cheap_money_stats', function () {
    $statuses = ['on-hold', 'approved', 'unapproved'];
    $counts = [
        'on_hold' => 0,
        'approved' => 0,
        'unapproved' => 0,
        'with_limit' => 0,
    ];

    foreach ($statuses as $status) {
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => $status,
            'payment_method' => 'cheap_money',
        ]);

        foreach ($orders as $order) {
            $counts[$status] += 1;

            $limit = get_post_meta($order->get_id(), '_cheap_money_limit', true);
            if ($limit && floatval($limit) > 0) {
                $counts['with_limit'] += 1;
            }
        }
    }

    wp_send_json_success($counts);
});
add_action('admin_post_download_cheap_money_report', function () {
    if (!current_user_can('cheap_money_company')) {
        wp_die('უფლება არ გეძლევა.');
    }

    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

    $args = [
        'limit' => -1,
        'payment_method' => 'cheap_money',
    ];

    if (in_array($status, ['on-hold', 'approved', 'unapproved'])) {
        $args['status'] = $status;
    }

    $orders = wc_get_orders($args);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cheap-money-report-' . $status . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'First Name', 'Last Name', 'Status', 'Total', 'Limit']);

    foreach ($orders as $order) {
        $billing = $order->get_address('billing');
        fputcsv($output, [
            $order->get_id(),
            $billing['first_name'],
            $billing['last_name'],
            $order->get_status(),
            $order->get_total(),
            get_post_meta($order->get_id(), '_cheap_money_limit', true),
        ]);
    }

    fclose($output);
    exit;
});
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();

    if (strpos($screen->id, 'cheap-money-orders') !== false || 
        strpos($screen->id, 'cheap-money-approved') !== false || 
        strpos($screen->id, 'cheap-money-unapproved') !== false || 
        strpos($screen->id, 'cheap-money-all') !== false) {

        wp_enqueue_style('cheap-money-admin-style', plugin_dir_url(__FILE__) . 'vizuali/vizuali.css');
    }
});
/*
add_action('admin_menu', function () {
    if (current_user_can('cheap_money_company')) {
        add_submenu_page('cheap-money-orders', 'ლიმიტიანი განაცხადები', 'ლიმიტიანი განაცხადები', 'cheap_money_company', 'cheap-money-with-limit', function () {
            cheap_money_render_orders_with_limit();
        });
    }
});
*/
add_action('admin_post_cheap_money_pdf', function () {
    if (current_user_can('cheap_money_company') && isset($_GET['order_id'])) {
        require_once plugin_dir_path(__FILE__) . 'funqciebi/search-filter.php';
        cheap_money_generate_pdf(intval($_GET['order_id']));
    }
});

// ღილაკის ფუნქცია - ღილაკი 'final-approved' სტატუსზე გადასატანი
function move_to_final_approved() {
    // აქ უნდა დადგეს მოთხოვნა, რომ გადახვიდეთ "final-approved" სტატუსზე
    if( isset( $_POST['order_id'] ) && current_user_can( 'cheap_company' ) ) {
        $order_id = intval( $_POST['order_id'] );
        $order = wc_get_order( $order_id );

        // აქ გამოიყენეთ `update_status` ფუნქცია, რომ სტატუსი განახლდეს
        if ( $order ) {
            $order->update_status( 'final-approved' ); // სტატიიდან 'final-approved' 
            wp_send_json_success( 'Order moved to final-approved.' );
        }
    }
    wp_send_json_error( 'Something went wrong.' );
}




