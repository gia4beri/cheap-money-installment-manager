<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_cheap_money_add_comment', function () {
    if (!current_user_can('cheap_money_company')) {
        wp_send_json_error('არ გაქვს წვდომა');
    }

    $order_id = intval($_POST['order_id']);
    $comment = sanitize_text_field($_POST['comment']);

    $comments = get_post_meta($order_id, '_cheap_money_comments', true);
    if (!is_array($comments)) {
        $comments = [];
    }

    $current_user = wp_get_current_user();
    $comments[] = [
        'author' => $current_user->display_name,
        'timestamp' => current_time('Y-m-d H:i'),
        'comment' => $comment
    ];

    update_post_meta($order_id, '_cheap_money_comments', $comments);

    wp_send_json_success();
});


add_action('wp_ajax_cheap_money_get_comments', function () {
    if (!current_user_can('cheap_money_company')) {
        wp_send_json_error('არ გაქვს წვდომა');
    }

    $order_id = intval($_GET['order_id']);
    $comments = get_post_meta($order_id, '_cheap_money_comments', true);
    if (!is_array($comments)) {
        $comments = [];
    }

    wp_send_json_success($comments);
});



function cheap_money_get_all_working_users() {
    global $wpdb;
    $meta_key = '_cheap_money_working_user';

    // მივიღოთ უნიკალური მნიშვნელობები სადაც არ არის ცარიელი
    $results = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
        WHERE meta_key = %s AND meta_value != ''
    ", $meta_key));

    return $results ? $results : [];
}


// 1. მენიუ მხოლოდ კომპანიის როლისთვის
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

// 2. სტატუსის ცვლილება და ლიმიტის შენახვა
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
});

// 3. საქმიანობაზე მუშაობის დამუშავება AJAX-ით
add_action('wp_ajax_cheap_money_working_on_it', function () {
    if (!current_user_can('cheap_money_company')) {
        wp_send_json_error('არ გაქვს წვდომა');
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('შეკვეთა ვერ მოიძებნა');
    }

    $already_working = get_post_meta($order_id, '_cheap_money_working_user', true);
    if (!empty($already_working)) {
        wp_send_json_error('უკვე მითითებულია');
    }

    $current_user = wp_get_current_user();
    update_post_meta($order_id, '_cheap_money_working_user', $current_user->display_name);
    wp_send_json_success($current_user->display_name);
});

// 4. სტატისტიკის მოტანა AJAX-ით
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

// 5. ძირითადი ფუნქცია - განაცხადების ჩვენება
function cheap_money_render_orders_by_status($status, $section_title) {
    $filter_only_with_limit = isset($_GET['only_with_limit']) && $_GET['only_with_limit'] == '1';
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $orders_per_page = 10;
// GET პარამეტრების წაღება და სუფთა გაფილტვრა
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
$working_user_filter = isset($_GET['working_user_filter']) ? sanitize_text_field($_GET['working_user_filter']) : '';
$user_search = isset($_GET['user_search']) ? sanitize_text_field($_GET['user_search']) : '';

// მომხმარებლების ჩამონათვალი ვინც მინიმუმ ერთხელ მიუთითებია მუშაობა
$worked_users = cheap_money_get_all_working_users();

echo '
    <style>
        .cheap-money-filters {
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f7fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            font-family: Arial, sans-serif;
        }
        .cheap-money-filters label {
            font-weight: 600;
            margin-right: 5px;
            white-space: nowrap;
        }
        .cheap-money-filters select,
        .cheap-money-filters input[type="text"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 150px;
            transition: border-color 0.3s ease;
        }
        .cheap-money-filters select:hover,
        .cheap-money-filters input[type="text"]:hover,
        .cheap-money-filters select:focus,
        .cheap-money-filters input[type="text"]:focus {
            border-color: #0073aa;
            outline: none;
        }
        .cheap-money-filters button {
            background-color: #0073aa;
            border: none;
            color: white;
            padding: 7px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .cheap-money-filters button:hover {
            background-color: #005177;
        }
        .cheap-money-orders-table td {
  line-height: 1.4em;
}


        
    </style>';

 echo '
<form method="get" class="cheap-money-filters">
    <input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '">

    <label for="user_search">მომხმარებლის ძებნა:</label>
    <input type="text" name="user_search" id="user_search" placeholder="პირადი ნომერი, სახელი ან გვარი"
        value="' . (isset($_GET['user_search']) ? esc_attr($_GET['user_search']) : '') . '"
        class="p-2 border rounded w-64 mr-2"
    >

    <label for="status_filter">სტატუსი:</label>
    <select name="status_filter" id="status_filter">
        <option value="" ' . selected($status_filter, '', false) . '>ყველა</option>
        <option value="on-hold" ' . selected($status_filter, 'on-hold', false) . '>ახალი (on-hold)</option>
        <option value="approved" ' . selected($status_filter, 'approved', false) . '>დამტკიცებული</option>
        <option value="unapproved" ' . selected($status_filter, 'unapproved', false) . '>დაუმტკიცებელი</option>
    </select>

    <label for="working_user_filter">საქმეზე მუშაობს:</label>
    <select name="working_user_filter" id="working_user_filter">
        <option value="" ' . selected($working_user_filter, '', false) . '>ყველა</option>';
        foreach ($worked_users as $user) {
            echo '<option value="' . esc_attr($user) . '" ' . selected($working_user_filter, $user, false) . '>' . esc_html($user) . '</option>';
        }
echo '</select>

    <button type="submit" class="button">ფილტრი</button>
</form>
';

    

    

    
$user_search = isset($_GET['user_search']) ? sanitize_text_field($_GET['user_search']) : '';


    // შეკვეთების წამოღება
    $args = [
        'status' => $status,
        'payment_method' => 'cheap_money',
        'orderby' => 'date',
        'order' => 'DESC',
        'limit' => -1, // Load all for filtering
    ];

    $meta_query = [];
    if (!empty($working_user_filter)) {
        $meta_query[] = [
            'key' => '_cheap_money_working_user',
            'value' => $working_user_filter,
            'compare' => '=',
        ];
    }
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $orders_raw = wc_get_orders($args);

    // In-memory filtering
    $orders_filtered = $orders_raw;

    if (!empty($user_search)) {
        $orders_filtered = array_filter($orders_filtered, function($order) use ($user_search) {
            $billing = $order->get_address('billing');
            return stripos($billing['first_name'], $user_search) !== false
                || stripos($billing['last_name'], $user_search) !== false
                || stripos($billing['company'], $user_search) !== false
                || stripos($billing['phone'], $user_search) !== false
                || stripos($billing['city'], $user_search) !== false
                || stripos($billing['address_1'], $user_search) !== false;
        });
    }

    if (!empty($status_filter)) {
        $orders_filtered = array_filter($orders_filtered, function($order) use ($status_filter) {
            return $order->get_status() === $status_filter;
        });
    }

    if ($filter_only_with_limit) {
        $orders_filtered = array_filter($orders_filtered, function($order) {
            $limit = get_post_meta($order->get_id(), '_cheap_money_limit', true);
            return floatval($limit) > 0;
        });
    }

    // Pagination
    $total_orders = count($orders_filtered);
    $orders_paginated = array_slice($orders_filtered, ($paged - 1) * $orders_per_page, $orders_per_page);

    // ვაბრუნებთ orders ობიექტს ისე, რომ შეინარჩუნოს თავსებადობა JS-თან
    $orders_query = new WC_Order_Query([
        'limit' => -1,
        'return' => 'ids',
    ]);
    $orders_query->orders = $orders_paginated;
    $orders_query->total = $total_orders;
    $orders_query->max_num_pages = ceil($total_orders / $orders_per_page);

    $orders = $orders_query;
    echo "<h2 style='margin-top:20px;'>$section_title</h2>";
    echo '<table class="widefat striped cheap-money-orders-table"><thead>
        <tr>
            <th>შეკვეთა</th>
            <th>მომხმარებელი</th>
            <th>პირადი ნომერი</th>
            <th>ტელეფონი</th>
            <th>მისამართი</th>
            <th>პროდუქტები</th>
            <th>ჯამი</th>
            <th>შეთავაზებული ლიმიტი</th>
            <th>სტატუსი</th>
            <th>საქმეზე მუშაობს</th>
            <th>ქმედება</th>
            <th>კომენტარი</th>
        </tr>
        </thead><tbody>';

    $current_user = wp_get_current_user();
    $is_company_user = in_array('cheap_company', (array) $current_user->roles);

    foreach ($orders->orders as $order) {
        $id = $order->get_id();
        $billing = $order->get_address('billing');
        $changed = $order->get_meta('_cheap_money_status_changed');
        $limit = get_post_meta($id, '_cheap_money_limit', true);
        $status = $order->get_status();
        $working_user = get_post_meta($id, '_cheap_money_working_user', true);

        if ($filter_only_with_limit && floatval($limit) <= 0) continue;

        $items_html = '';
        foreach ($order->get_items() as $item) {
            $items_html .= $item->get_name() . ' × ' . $item->get_quantity() . ' (' . wc_price($item->get_total()) . ')<br>';
        }
        echo '<style>
    tr.status-approved {
        background-color: #d4edda; /* ღია მწვანე */
    }
    tr.status-unapproved {
        background-color: #f8d7da; /* ღია წითელი */
    }
    tr.status-on-hold {
        background-color: white; /* ყვითელი */
    }
</style>';




        echo "<tr class='status-{$status}'>
            <td>{$id}</td>
            <td>{$billing['first_name']} <br> {$billing['last_name']}</td>
            <td>{$billing['company']}</td>
            <td>{$billing['phone']}</td>
            <td>{$billing['city']} <br> {$billing['address_1']}</td>
            <td>{$items_html}</td>
            <td>" . wc_price($order->get_total()) . "</td>
            <td>";
            
        if ($is_company_user) {
            if (empty($limit)) {
                echo '<form method="post" class="cheap-limit-form" style="margin:0;">
                    <input type="hidden" name="cheap_order_id_limit" value="' . esc_attr($id) . '">
                    <input type="number" step="0.01" name="cheap_money_limit" placeholder="დამტკიცებული ლიმიტი" required style="width:80px;">
                    <button type="submit" class="button">შენახვა</button>
                </form>';
            } else {
                echo '<div class="cheap-limit-display" data-order-id="' . esc_attr($id) . '">
                    <span class="limit-amount">ლიმიტი: ' . esc_html($limit) . '</span>
                    <button class="edit-limit button" type="button">✏️</button>
                </div>
                <form method="post" class="cheap-limit-form" style="display:none; margin:0;">
                    <input type="hidden" name="cheap_order_id_limit" value="' . esc_attr($id) . '">
                    <input type="number" step="0.01" name="cheap_money_limit" value="' . esc_attr($limit) . '" required style="width:80px;">
                    <button type="submit" class="button">შენახვა</button>
                </form>';
            }
        } else {
            echo !empty($limit) ? 'დამტკიცებული ლიმიტი: ' . esc_html($limit) : 'დამტკიცებული ლიმიტი არ არის';
        }

        echo "</td>
            <td class='status-cell status-" . esc_attr($status) . "'>" . wc_get_order_status_name($status) . "</td>
            <td>";

        if ($working_user) {
            echo " მუშაობს ({$working_user})";
        } elseif ($is_company_user) {
            echo '<button class="button working-on-it" data-order-id="' . esc_attr($id) . '">სამუშოს <br> დაწყება</button>';
        } else {
            echo '-';
        }

        echo "</td><td>";

        if (!$changed && $is_company_user) {
            echo '<form method="post" style="display:inline;">
                <input type="hidden" name="cheap_order_id" value="' . esc_attr($id) . '">
                <input type="hidden" name="cheap_new_status" value="wc-approved">
                <button type="submit" name="cheap_submit_status" class="button" style="background-color:#28a745; color:white; margin-right:5px;">✅</button>
            </form>';
            echo '<form method="post" style="display:inline;">
                <input type="hidden" name="cheap_order_id" value="' . esc_attr($id) . '">
                <input type="hidden" name="cheap_new_status" value="wc-unapproved">
                <button type="submit" name="cheap_submit_status" class="button" style="background-color:#00001a; color:white;">❌</button>
            </form>';
        } else {
            echo 'პასუხი გაცემულია';
        }

        echo '<br><a href="' . admin_url('admin-post.php?action=cheap_money_pdf&order_id=' . $id) . '" class="button" target="_blank">📄  გადმოწერა</a>';
       echo ' <td>
    <button class="button comment-button" data-order-id="' . esc_attr($id) . '">💬 კომენტარი</button>
</td>';

        echo '</td></tr>';
    }

    // pagination
 // ✨ გაუმჯობესებული pagination
// ✅ თანამედროვე pagination დიზაინი
$total_pages = $orders->max_num_pages;
$current_page = $paged;
$base_url = remove_query_arg('paged');
$visible_range = 2;

if ($total_pages > 1) {
    echo '<style>
        .modern-pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 25px;
            flex-wrap: wrap;
            font-family: "Segoe UI", Tahoma, sans-serif;
        }
        .modern-pagination a {
            text-decoration: none;
            padding: 6px 12px;
            background-color: #e2e8f0;
            border-radius: 6px;
            color: #1e293b;
            transition: background-color 0.2s ease;
            font-weight: 500;
            font-size: 14px;
        }
        .modern-pagination a:hover {
            background-color: #cbd5e1;
        }
        .modern-pagination a.active {
            background-color: #2563eb;
            color: white;
            font-weight: 600;
        }
        .modern-pagination .page-info {
            margin-left: 12px;
            font-size: 13px;
            color: #475569;
        }
    </style>';

    echo '<div class="modern-pagination">';

    if ($current_page > 1) {
        echo '<a href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">&laquo;</a>';
        echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">&lsaquo;</a>';
    }

    $start = max(1, $current_page - $visible_range);
    $end = min($total_pages, $current_page + $visible_range);

    for ($i = $start; $i <= $end; $i++) {
        $url = add_query_arg('paged', $i, $base_url);
        $class = $i == $current_page ? 'active' : '';
        echo '<a href="' . esc_url($url) . '" class="' . $class . '">' . $i . '</a>';
    }

    if ($current_page < $total_pages) {
        echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">&rsaquo;</a>';
        echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">&raquo;</a>';
    }

    echo '<span class="page-info">გვერდი ' . $current_page . ' სულ ' . $total_pages . '</span>';
    echo '</div>';
}

// დასასრული
   // <!-- კომენტარის პოპაპი -->
echo '
<style>
  /* Overlay ფონი */
  #cheap-comment-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9998;
    backdrop-filter: blur(4px);
  }

  /* Popup ფანჯარა */
  #cheap-comment-popup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    max-width: 95%;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    padding: 30px 40px;
    z-index: 9999;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    color: #222;
    user-select: text;
  }

  /* სათაური */
  #cheap-comment-popup h3 {
    margin: 0 0 25px 0;
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b; /* სუფთა მუქი ლურჯი */
  }

  /* ტექსტის ზონა */
  #cheap-comment-text {
    width: 100%;
    height: 140px;
    border: 2px solid #cbd5e1; /* მოყვითალო ნაცრისფერი */
    border-radius: 12px;
    padding: 14px 18px;
    font-size: 1.15rem;
    line-height: 1.5;
    resize: vertical;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    box-sizing: border-box;
  }

  #cheap-comment-text:focus {
    outline: none;
    border-color: #2563eb; /* ბრენდული ლურჯი */
    box-shadow: 0 0 10px rgba(37, 99, 235, 0.6);
  }

  /* კომენტარების ისტორია */
  #cheap-comment-history {
    margin-top: 30px;
    max-height: 180px;
    overflow-y: auto;
    border-top: 1px solid #e2e8f0;
    padding-top: 15px;
    font-size: 1rem;
    color: #475569;
    line-height: 1.4;
  }

  #cheap-comment-history p {
    margin: 8px 0;
    padding-bottom: 6px;
    border-bottom: 1px dotted #cbd5e1;
  }

  /* ღილაკების კონტეინერი */
  .cheap-btn-container {
    margin-top: 25px;
    text-align: right;
  }

  /* ღილაკები საერთო */
  .cheap-btn {
    cursor: pointer;
    border-radius: 10px;
    border: none;
    padding: 12px 28px;
    font-weight: 700;
    font-size: 1.1rem;
    margin-left: 15px;
    transition: background-color 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    user-select: none;
  }

  /* შენახვა ღილაკი */
  #cheap-save-comment {
    background-color: #2563eb;
    color: white;
  }
  #cheap-save-comment:hover {
    background-color: #1e40af;
    box-shadow: 0 6px 16px rgba(30, 64, 175, 0.7);
  }

  /* დახურვა ღილაკი */
  #cheap-close-comment {
    background-color: #e2e8f0;
    color: #334155;
  }
  #cheap-close-comment:hover {
    background-color: #cbd5e1;
    box-shadow: 0 6px 16px rgba(107, 114, 128, 0.5);
  }

  /* Scrollbar სტილი (optional) */
  #cheap-comment-history::-webkit-scrollbar {
    width: 8px;
  }
  #cheap-comment-history::-webkit-scrollbar-thumb {
    background-color: #94a3b8;
    border-radius: 8px;
  }
  #cheap-comment-history::-webkit-scrollbar-track {
    background-color: #f1f5f9;
  }
</style>

<div id="cheap-comment-overlay"></div>

<div id="cheap-comment-popup" role="dialog" aria-modal="true" aria-labelledby="cheap-comment-title">
  <h3 id="cheap-comment-title">კომენტარის დამატება</h3>
  <textarea id="cheap-comment-text" placeholder="გთხოვთ დაწერეთ კომენტარი..."></textarea>

  <div class="cheap-btn-container">
    <button id="cheap-save-comment" class="cheap-btn">შენახვა</button>
    <button id="cheap-close-comment" class="cheap-btn">დახურვა</button>
  </div>

  <div id="cheap-comment-history" aria-live="polite" aria-relevant="additions"></div>
</div>
';



    // JS: ლიმიტის რედაქტორი და საქმიანობაზე მუშაობა
    echo '
<script>
document.addEventListener("DOMContentLoaded", function () {
    let currentOrderId = null;

    // კომენტარის ღილაკი - popup-ის გახსნა და კომენტარების წამოღება
    document.querySelectorAll(".comment-button").forEach(btn => {
        btn.addEventListener("click", function () {
            currentOrderId = this.dataset.orderId;
            document.getElementById("cheap-comment-popup").style.display = "block";
            document.getElementById("cheap-comment-overlay").style.display = "block";

            document.getElementById("cheap-comment-text").value = "";
            const messageBox = document.getElementById("cheap-comment-message");
            if (messageBox) messageBox.textContent = "";

            document.getElementById("cheap-comment-history").innerHTML = "<em>იტვირთება...</em>";

            fetch(ajaxurl + "?action=cheap_money_get_comments&order_id=" + currentOrderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        // სიას ვაბრუნებთ, რომ ბოლო კომენტარი იყოს პირველი
                        const commentsReversed = data.data.reverse();
                        const commentsHtml = commentsReversed.map(c =>
                            `<p><strong>${c.author}</strong> (${c.timestamp}):<br>${c.comment}</p>`
                        ).join("<hr>");
                        document.getElementById("cheap-comment-history").innerHTML = commentsHtml;
                    } else {
                        document.getElementById("cheap-comment-history").innerHTML = "<em>კომენტარები არ არის</em>";
                    }
                })
                .catch(() => {
                    document.getElementById("cheap-comment-history").innerHTML = "<em>შეცდომა კომენტარების წამოღებისას</em>";
                });
        });
    });

    // დამატების ღილაკი - ახალი კომენტარის დამატება
    document.getElementById("cheap-save-comment").addEventListener("click", function () {
        const commentInput = document.getElementById("cheap-comment-text");
        const comment = commentInput.value.trim();
        const messageBox = document.getElementById("cheap-comment-message");

        if (messageBox) {
            messageBox.textContent = "";
            messageBox.style.color = "";
        }

        if (!comment) {
            if (messageBox) {
                messageBox.textContent = "გთხოვთ, დაწერეთ კომენტარი";
                messageBox.style.color = "red";
            }
            commentInput.focus();
            return;
        }

        const formData = new URLSearchParams();
        formData.append("action", "cheap_money_add_comment");
        formData.append("order_id", currentOrderId);
        formData.append("comment", comment);

        fetch(ajaxurl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (messageBox) {
                    messageBox.textContent = "კომენტარი შენახულია";
                    messageBox.style.color = "green";
                }

                // ახალი კომენტარის პირდაპირ ჩასმა ზედა პოზიციაზე
                const history = document.getElementById("cheap-comment-history");

                // თუ შენს AJAX პასუხში არ გაქვს author და timestamp, გამოიყენე ფიქსირებული ტექსტი
                const author = (data.data && data.data.author) ? data.data.author : "თქვენ";
                const timestamp = (data.data && data.data.timestamp) ? data.data.timestamp : new Date().toLocaleString();

                const newCommentHtml = `<p><strong>${author}</strong> (${timestamp}):<br>${comment}</p><hr>`;
                history.innerHTML = newCommentHtml + history.innerHTML;

                commentInput.value = "";
            } else {
                if (messageBox) {
                    messageBox.textContent = "შეცდომა: " + (data.data || "დაფიქსირდა შეცდომა");
                    messageBox.style.color = "red";
                }
            }
        })
        .catch(() => {
            if (messageBox) {
                messageBox.textContent = "დაფიქსირდა ქსელის შეცდომა";
                messageBox.style.color = "red";
            }
        });
    });

    // დახურვის ღილაკი - popup-ის დახურვა
    document.getElementById("cheap-close-comment").addEventListener("click", function () {
        document.getElementById("cheap-comment-popup").style.display = "none";
        document.getElementById("cheap-comment-overlay").style.display = "none";

        const messageBox = document.getElementById("cheap-comment-message");
        if (messageBox) messageBox.textContent = "";
    });
});
</script>
';

}
add_action('admin_footer', function () {
    if (!current_user_can('cheap_money_company')) return;
    ?>
    <script>
    jQuery(document).ready(function ($) {
        $('.working-on-it').on('click', function () {
            if (!confirm('დარწმუნებული ხარ, რომ გინდა მუშაობის დაწყება ამ განაცხადზე?')) return;

            var button = $(this);
            var orderId = button.data('order-id');

            $.post(ajaxurl, {
                action: 'cheap_money_working_on_it',
                order_id: orderId,
            }, function (response) {
                if (response.success) {
                    button.closest('td').html('მუშაობს (' + response.data + ')');
                } else {
                    alert('შეცდომა: ' + response.data);
                }
            });
        });
    });
    </script>
    <?php
});


