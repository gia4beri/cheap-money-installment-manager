<?php

if (!defined('ABSPATH')) exit;

class WC_Gateway_Cheap_Money extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'cheap_money';
        $this->method_title       = 'Cheap Money განვადება';
        $this->method_description = 'გადახდის მეთოდი განვადებით - Cheap Money';
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->handling_fee = floatval($this->get_option('handling_fee'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_handling_fee']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'ჩართვა/გამორთვა',
                'type'    => 'checkbox',
                'label'   => 'ჩართე Cheap Money განვადება',
                'default' => 'yes'
            ],
            'title' => [
                'title'       => 'სათაური',
                'type'        => 'text',
                'default'     => 'Cheap Money განვადება',
            ],
            'description' => [
                'title'       => 'აღწერა',
                'type'        => 'textarea',
                'default'     => 'გადახდა განვადებით Cheap Money-ის დახმარებით',
            ],
            'handling_fee' => [
                'title'       => 'საკომისიო (%)',
                'type'        => 'number',
                'default'     => '0',
            ],
        ];
    }

    public function add_handling_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        if ($this->handling_fee > 0 && isset($_POST['payment_method']) && $_POST['payment_method'] === $this->id) {
            $fee = ($cart->cart_contents_total + $cart->get_shipping_total()) * ($this->handling_fee / 100);
            $cart->add_fee('Cheap Money საკომისიო', $fee, false);
        }
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $order->update_status('on-hold', 'მომხმარებელმა აირჩია განვადება');
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();
        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
}
