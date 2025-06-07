<?php

if (!defined('ABSPATH')) exit;

// სტატუსების რეგისტრაცია
add_action('init', function () {
    register_post_status('wc-approved', [
        'label' => 'დამტკიცებული',
        'public' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('დამტკიცებული <span class="count">(%s)</span>', 'დამტკიცებული <span class="count">(%s)</span>')
    ]);
    register_post_status('wc-unapproved', [
        'label' => 'დაუმტკიცებელი',
        'public' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('დაუმტკიცებელი <span class="count">(%s)</span>', 'დაუმტკიცებელი <span class="count">(%s)</span>')
    ]);
});

// WooCommerce-ში ჩასმა
add_filter('wc_order_statuses', function ($statuses) {
    $new = [];
    foreach ($statuses as $k => $v) {
        $new[$k] = $v;
        if ($k === 'wc-processing') {
            $new['wc-approved'] = 'დამტკიცებული';
            $new['wc-unapproved'] = 'დაუმტკიცებელი';
        }
    }
    return $new;
});

// როლის რეგისტრაცია
register_activation_hook(__FILE__, function () {
    add_role('cheap_company', 'Cheap Company', [
        'read' => true,
        'edit_posts' => true, // საჭიროა wp-admin წვდომისთვის
        'cheap_money_company' => true,
    ]);
});

// ფერის სტილები
add_action('admin_head', function () {
    echo '<style>
        .status-wc-approved { background: #d4f4d4 !important; color: #0f600f !important; }
        .status-wc-unapproved { background: #fbe0e0 !important; color: #a10000 !important; }
    </style>';
});
