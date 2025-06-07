<?php

use Dompdf\Dompdf;
use Dompdf\Options;

function cheap_money_generate_pdf($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $limit = get_post_meta($order_id, '_cheap_money_limit', true);
    $billing = $order->get_address('billing');
    $date = $order->get_date_created()->date('Y-m-d');

    // 1. HTML სტრუქტურა ლამაზი ინვოისისთვის
    ob_start();
    ?>
<html>
<head>
    <style>
        @font-face {
            font-family: 'BPG Glaho';
            src: url('<?php echo plugin_dir_url(__FILE__) . "../assets/fonts/BPG_Glaho.ttf"; ?>') format('truetype');
        }
        body {
            font-family: 'BPG Glaho', DejaVu Sans, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 2px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items to the top */
            margin-bottom: 5px;
        }
        .logo {
            max-width: 100px;
        }
        .contact-info {
            margin-top: -70px;
            text-align: right;
            font-size: 12px;
            display: flex;
            flex-direction: column; /* Vertically align the items */
            justify-content: flex-start; /* Align the information to the top */
        }
        .contact-info p {
            margin: 0;
            margin-bottom: 5px; /* Add some spacing between the contact info items */
        }
        .info, .products {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .info td {
            padding: 5px;
            vertical-align: top;
        }
        .products th, .products td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .total {
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with Logo and Contact Information -->
        <div class="header">
            <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/logo.png'; ?>" alt="Logo" class="logo" />
            <div class="contact-info">
                <p><strong>მეილი:</strong> info@pchous.ge</p>
                <p><strong>ტელეფონი:</strong> +995 577 386 236</p>
                <p><strong>ანგარიშის ნომერი:</strong> GE96TB7878645068100044</p>
                <p><strong>მისამართი:</strong> ოზურგეთი, შემოქმედი</p>
            </div>
        </div>

        <!-- Order Information -->
        <h1>განვადების ხელშეკრულება</h1>
        <table class="info">
            <tr><td><strong>შეკვეთის ID:</strong></td><td><?= esc_html($order_id) ?></td></tr>
            <tr><td><strong>თარიღი:</strong></td><td><?= esc_html($date) ?></td></tr>
            <tr><td><strong>სახელი გვარი:</strong></td><td><?= esc_html($billing['first_name'] . ' ' . $billing['last_name']) ?></td></tr>
            <tr><td><strong>პირადი ნომერი:</strong></td><td><?= esc_html($billing['company']) ?></td></tr>
            <tr><td><strong>ტელეფონი:</strong></td><td><?= esc_html($billing['phone']) ?></td></tr>
            <tr><td><strong>მისამართი:</strong></td><td><?= esc_html($billing['address_1'] . ', ' . $billing['city']) ?></td></tr>
            <tr><td><strong>შეთავაზებული ლიმიტი:</strong></td><td><?= esc_html($limit) ?></td></tr>
        </table>

        <!-- Products List -->
        <h3>პროდუქტების სია:</h3>
        <table class="products">
            <thead>
                <tr><th>პროდუქტი</th><th>რაოდენობა</th><th>ჯამი</th></tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item): ?>
                    <tr>
                        <td><?= esc_html($item->get_name()) ?></td>
                        <td><?= esc_html($item->get_quantity()) ?></td>
                        <td><?= number_format($item->get_total(), 2) ?> ლარი</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="total">სულ გადასახდელი: <?= number_format($order->get_total(), 2) ?> ლარი</p>
    </div>
</body>
</html>


    <?php
    $html = ob_get_clean();

    // 2. PDF პარამეტრები
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'BPG Glaho'); // სავალდებულოა

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // 3. გაგზავნა ბრაუზერში
    $dompdf->stream("agreement-order-{$order_id}.pdf", ['Attachment' => false]);
    exit;
}
