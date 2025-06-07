<?php
// Final Approved submenu
add_action('admin_menu', function () {
    add_submenu_page(
        'cheap-money-orders',
        'საბოლოოდ დამტკიცებული',
        'საბოლოოდ დამტკიცებული',
        'cheap_money_company',
        'cheap-money-final-approved',
        function () {
            cheap_money_render_orders_by_status('final-approved', '🔒 საბოლოოდ დამტკიცებული');
        }
    );
});

// Show "Final Approve" button on approved orders
add_filter('cheap_money_order_actions', function ($actions_html, $order, $status, $is_company_user) {
    if ($status === 'approved' && !$order->get_meta('_cheap_money_final_approved') && $is_company_user) {
        $actions_html .= '<form method="post" style="display:inline;margin-top:5px;">
            <input type="hidden" name="cheap_final_approve_id" value="' . esc_attr($order->get_id()) . '">
            <button type="submit" name="cheap_final_approve_btn" class="button" style="background-color:#004085;color:white;">🔒 საბოლოოდ დამტკიცება</button>
        </form>';
    } elseif ($order->get_meta('_cheap_money_final_approved')) {
        $actions_html .= '<span style="color:green; font-weight:bold;">დამტკიცდა საბოლოოდ</span>';
    }
    return $actions_html;
}, 10, 4);

// Handle final approve POST
add_action('admin_init', function () {
    if (
        current_user_can('cheap_money_company') &&
        isset($_POST['cheap_final_approve_btn']) &&
        isset($_POST['cheap_final_approve_id'])
    ) {
        $order_id = intval($_POST['cheap_final_approve_id']);
        update_post_meta($order_id, '_cheap_money_final_approved', '1');
    }
});

// Hook into rendering to support filtering by final-approved meta
add_filter('cheap_money_order_query_args', function ($args, $status) {
    if ($status === 'final-approved') {
        $final_approved_ids = get_posts([
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_cheap_money_final_approved',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        $args['status'] = ['any'];
        $args['include'] = $final_approved_ids;
    }
    return $args;
}, 10, 2);
