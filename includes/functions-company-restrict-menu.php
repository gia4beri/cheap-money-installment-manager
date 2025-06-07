<?php
if (!defined('ABSPATH')) exit;

/**
 * Restrict admin menu for 'cheap_company' role.
 * Only show 'განვადების სია' menu created by the plugin.
 */
add_action('admin_menu', 'cheap_money_hide_admin_menus', 999);

function cheap_money_hide_admin_menus() {
    if (!current_user_can('cheap_money_company') || current_user_can('manage_options')) {
        // თუ ეს არ არის company ან არის admin, არ ვმალავთ არაფერს
        return;
    }

    global $menu;

    // დატოვე მხოლოდ განვადების სია (შენი მენიუ)
    foreach ($menu as $key => $value) {
        if (strpos($value[2], 'cheap-money-orders') === false) {
            unset($menu[$key]);
        }
    }
}

