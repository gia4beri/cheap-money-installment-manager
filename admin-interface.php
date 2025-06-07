<?php
if (!defined('ABSPATH')) exit;

// მენიუ მხოლოდ კომპანიებისთვის
add_action('admin_menu', function () {
    if (current_user_can('cheap_money_company')) {
        add_menu_page('განვადება (ახალი)', 'განვადება (ახალი)', 'cheap_money_company', 'cheap-money-orders', function () {
            cheap_money_render_orders_by_status('on-hold', '🆕 ახალი განაცხადები');
        }, 'dashicons-list-view', 56);

        add_submenu_page('cheap-money-orders', 'დამტკიცებული', 'დამტკიცებული', 'cheap_money_company', 'cheap-money-approved', function () {
            cheap_money_render_orders_by_status('approved', '✅ დამტკიცებული');
        });

        add_submenu_page('cheap-money-orders', 'დაუმტკიცებელი', 'დაუმტკიცებელი', 'cheap_money_company', 'cheap-money-unapproved', function () {
            cheap_money_render_orders_by_status('unapproved', '❌ დაუმტკიცებელი');
        });

        add_submenu_page('cheap-money-orders', 'ყველა', 'ყველა', 'cheap_money_company', 'cheap-money-all', function () {
            cheap_money_render_orders_by_status(['on-hold', 'approved', 'unapproved'], '📋 ყველა განაცხადი');
        });
    }
});

// სტატუსის ცვლილება, ლიმიტის შენახვა, საბოლოო დამტკიცება
add_action('admin_init', function () {
    if (
        isset($_POST['cheap_submit_status']) &&
        current_user_can('cheap_money_company')
    ) {
        $order_id = intval($_POST['cheap_order_id']);
        $new_status = sanitize_text_field($_POST['cheap_new_status']);
        $order = wc_get_order($order_id);

        if ($order && $order->get_payment_method() === 'cheap_money' && !$order->get_meta('_cheap_money_status_changed')) {
            $order->update_status(str_replace('wc-', '', $new_status), 'სტატუსი კომპანიის მიერ შეიცვალა.');
            $order->update_meta_data('_cheap_money_status_changed', true);
            $order->save();
        }
    }

    if (
        current_user_can('cheap_money_company') &&
        isset($_POST['cheap_money_limit']) &&
        isset($_POST['cheap_order_id_limit'])
    ) {
        $order_id = intval($_POST['cheap_order_id_limit']);
        $limit = sanitize_text_field($_POST['cheap_money_limit']);
        update_post_meta($order_id, '_cheap_money_limit', $limit);
    }

    if (
        isset($_POST['cheap_money_final_approve']) &&
        current_user_can('cheap_money_company')
    ) {
        $order_id = intval($_POST['cheap_money_final_approve']);
        update_post_meta($order_id, '_cheap_money_final_approved', 'yes');
    }
});

// AJAX სტატისტიკა
add_action('wp_ajax_get_cheap_money_stats', function () {
    $orders = wc_get_orders([
        'limit' => -1,
        'status' => ['on-hold', 'approved', 'unapproved'],
        'payment_method' => 'cheap_money',
    ]);

    $stats = [
        'on_hold' => 0,
        'approved' => 0,
        'unapproved' => 0,
        'with_limit' => 0,
    ];

    foreach ($orders as $order) {
        $status = $order->get_status();
        $limit = floatval(get_post_meta($order->get_id(), '_cheap_money_limit', true));

        if (isset($stats[$status])) {
            $stats[$status]++;
        }

        if ($limit > 0) {
            $stats['with_limit']++;
        }
    }

    wp_send_json_success($stats);
});

// AJAX საქმის აღება
add_action('wp_ajax_cheap_money_take_action', function () {
    if (!current_user_can('cheap_money_company')) {
        wp_send_json_error('არაა ავტორიზებული');
    }

    $order_id = intval($_POST['order_id']);
    $existing = get_post_meta($order_id, '_cheap_money_working_on', true);
    if (!empty($existing)) {
        wp_send_json_error('უკვე მუშაობს');
    }

    $user = wp_get_current_user();
    $name = $user->display_name;

    update_post_meta($order_id, '_cheap_money_working_on', $name);
    wp_send_json_success(['name' => $name]);
});

// 注释: მთავარი HTML რენდერი
function cheap_money_render_orders_by_status($status, $section_title) {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html($section_title); ?></h1>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Მომხმარებელი</th>
                    <th>Თარიღი</th>
                    <th>ტელეფონი</th>
                    <th>სტატუსი</th>
                    <th>ლიმიტი</th>
                    <th>საქმე</th>
                    <th>საბოლოო დამტკიცება</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $orders = wc_get_orders([
                    'limit' => -1,
                    'status' => $status,
                    'payment_method' => 'cheap_money',
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]);

                foreach ($orders as $order):
                    $order_id = $order->get_id();
                    $billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $date_created = $order->get_date_created()->date_i18n();
                    $phone = $order->get_billing_phone();
                    $working_on = get_post_meta($order_id, '_cheap_money_working_on', true);
                    $limit = get_post_meta($order_id, '_cheap_money_limit', true);
                    $final_approved = get_post_meta($order_id, '_cheap_money_final_approved', true);
                ?>
                <tr>
                    <td><?php echo esc_html($billing_name); ?></td>
                    <td><?php echo esc_html($date_created); ?></td>
                    <td><?php echo esc_html($phone); ?></td>
                    <td>
                        <?php if (!$order->get_meta('_cheap_money_status_changed')): ?>
                            <form method="post">
                                <input type="hidden" name="cheap_order_id" value="<?php echo esc_attr($order_id); ?>">
                                <select name="cheap_new_status">
                                    <option value="wc-approved">დამტკიცება</option>
                                    <option value="wc-unapproved">უარყოფა</option>
                                </select>
                                <button type="submit" name="cheap_submit_status" class="button">OK</button>
                            </form>
                        <?php else: ?>
                            <strong><?php echo esc_html($order->get_status()); ?></strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="cheap_order_id_limit" value="<?php echo esc_attr($order_id); ?>">
                            <input type="number" step="0.01" name="cheap_money_limit" value="<?php echo esc_attr($limit); ?>">
                            <button type="submit" class="button">შენახვა</button>
                        </form>
                    </td>
                    <td>
                        <?php if ($working_on): ?>
                            <strong><?php echo esc_html($working_on); ?></strong>
                        <?php else: ?>
                            <button class="button take-action" data-order-id="<?php echo esc_attr($order_id); ?>">ავიღე</button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($final_approved === 'yes'): ?>
                            ✅
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="cheap_money_final_approve" value="<?php echo esc_attr($order_id); ?>">
                                <button type="submit" class="button">დამტკიცება</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.take-action').on('click', function() {
            var button = $(this);
            var orderId = button.data('order-id');

            $.post(ajaxurl, {
                action: 'cheap_money_take_action',
                order_id: orderId
            }, function(response) {
                if (response.success) {
                    button.replaceWith('<strong>' + response.data.name + '</strong>');
                } else {
                    alert(response.data || 'შეცდომა!');
                }
            });
        });
    });
    </script>
    <?php
}
