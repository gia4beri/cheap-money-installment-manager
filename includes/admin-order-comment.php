<?php
// admin-order-comment.php

if (!defined('ABSPATH')) {
    exit;
}

// კომენტარის რენდერის ფუნქცია
function cheap_money_render_order_comment($id) {
    $comment = get_post_meta($id, '_cheap_money_comment', true);
    $history = get_post_meta($id, '_cheap_money_comment_history', true) ?: [];
    $user_can_edit = current_user_can('cheap_company');
    ?>
    <div class="cheap-money-order-comment" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; position: relative;">
        <strong style="font-size: 16px;">კომენტარი:</strong><br>

        <?php if ($user_can_edit): ?>
            <div id="comment-display-<?php echo esc_attr($id); ?>" style="<?php echo $comment ? '' : 'display:none;'; ?> padding: 10px; background: #fff; border-radius: 6px; border-left: 4px solid #0073aa; margin-top: 8px; position: relative;">
                <?php echo esc_html($comment); ?>
                <?php if ($comment): ?>
                    <button class="edit-comment-btn" data-order-id="<?php echo esc_attr($id); ?>" style="position:absolute; right:8px; top:8px; background:none; border:none; cursor:pointer; font-size: 18px;">✏️</button>
                <?php endif; ?>
            </div>

            <div id="comment-edit-<?php echo esc_attr($id); ?>" style="<?php echo $comment ? 'display:none;' : ''; ?> margin-top: 8px;">
                <textarea 
                    class="cheap-money-comment" 
                    data-order-id="<?php echo esc_attr($id); ?>"
                    style="width:100%; height:80px; border-radius:6px; border:1px solid #ccc; padding:8px; font-size:14px;"><?php echo esc_textarea($comment); ?></textarea>
                <button 
                    class="button save-comment-btn" 
                    data-order-id="<?php echo esc_attr($id); ?>"
                    style="margin-top: 6px;">შენახვა</button>
            </div>
        <?php else: ?>
            <div style="background: #fff; padding: 10px; border-left: 4px solid #999; border-radius: 6px; margin-top: 8px;">
                <?php echo $comment ? esc_html($comment) : '<em>კომენტარი არ არის</em>'; ?>
            </div>
        <?php endif; ?>

<button class="button view-history-btn" data-order-id="<?php echo esc_attr($id); ?>" style="margin-top: 12px; background: #0073aa; color: #fff; border:none; border-radius: 5px; padding: 8px 14px; cursor:pointer; font-weight: bold; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 8px rgba(0,115,170,0.3); transition: background 0.3s ease;">
    <span style="font-size: 18px;">📜</span> <span>კომენტარების ისტორია</span>
</button>
            <div class="history-popup" id="history-popup-<?php echo esc_attr($id); ?>" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border:1px solid #ccc; border-radius:8px; padding:20px; width:800px; max-height:800px; overflow-y:auto; box-shadow: 0 0 30px rgba(0,0,0,0.5); z-index:9999;">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px; position: relative;">
                    <h3 style="margin:0;">ისტორია</h3>
                    <button class="button close-history" style="position: fixed !important; top: 20px; right: 20px; background:#e74c3c; border:none; color:#fff; font-weight:bold; font-size:22px; line-height:1; padding: 6px 14px; border-radius: 4px; cursor:pointer; box-shadow: 0 0 10px rgba(231,76,60,0.8); z-index: 10000;">
                        ✕
                    </button>
                </div>
                <div class="history-entries" style="max-height: 700px; overflow-y: auto;">
                    <?php foreach ($history as $entry) : ?>
                        <div style="border-bottom: 1px solid #eee; padding: 10px 0;">
                            <strong><?php echo esc_html($entry['user']); ?></strong><br>
                            <small style="color:#666;"><?php echo esc_html($entry['time']); ?></small><br>
                            <div style="margin-top: 5px; white-space: pre-wrap;"><?php echo esc_html($entry['text']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
    </div>

    <style>
        .cheap-money-comment.error {
            border: 2px solid red !important;
        }
        .comment-error {
            color: red;
            margin-top: 5px;
        }
        .view-history-btn:hover {
            background: #005f8a;
            box-shadow: 0 6px 12px rgba(0,95,138,0.5);
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            // Edit icon click
            $(document).on('click', '.edit-comment-btn', function () {
                const id = $(this).data('order-id');
                $('#comment-display-' + id).hide();
                $('#comment-edit-' + id).show();
            });

            // Save comment
            $(document).on('click', '.save-comment-btn', function () {
                const id = $(this).data('order-id');
                const textarea = $('#comment-edit-' + id).find('.cheap-money-comment');
                const comment = textarea.val().trim();

                if (comment.length < 1) {
                    textarea.addClass('error');
                    if (!textarea.next('.comment-error').length) {
                        textarea.after('<div class="comment-error">გთხოვ შეიყვანე კომენტარი</div>');
                    }
                    return;
                } else {
                    textarea.removeClass('error');
                    textarea.next('.comment-error').remove();
                }

                const button = $(this);
                button.prop('disabled', true).text('შენახვა...');

                $.post(ajaxurl, {
                    action: 'save_cheap_money_comment',
                    order_id: id,
                    comment: comment
                }, function (response) {
                    button.prop('disabled', false).text('შენახვა');
                    if (response.success) {
                        // კომენტარი ჩაემატოს ზემოთ წარმოდგენილ ველშიც
$('#comment-display-' + id).html(
    $('<div>').text(comment).html() + 
    ' <button class="edit-comment-btn" data-order-id="' + id + '" style="position:absolute; right:8px; top:8px; background:none; border:none; cursor:pointer; font-size: 18px;">✏️</button>'
);

// ტექსტის ველი გაწმინდოს
textarea.val('');
textarea.focus(); // კვლავ ჩასაწერად მზად იყოს

// კომენტარის ბლოკი დარჩეს ღია
$('#comment-display-' + id).hide();
$('#comment-edit-' + id).show();


                        $.post(ajaxurl, {
                            action: 'get_cheap_money_comment_history',
                            order_id: id
                        }, function (hist_response) {
                            if(hist_response.success) {
                                const entriesHtml = hist_response.data.map(function(entry) {
                                    return '<div style="border-bottom: 1px solid #eee; padding: 10px 0;">' +
                                        '<strong>' + $('<div>').text(entry.user).html() + '</strong><br>' +
                                        '<small style="color:#666;">' + $('<div>').text(entry.time).html() + '</small><br>' +
                                        '<div style="margin-top: 5px; white-space: pre-wrap;">' + $('<div>').text(entry.text).html() + '</div>' +
                                    '</div>';
                                }).join('');
                                $('#history-popup-' + id + ' .history-entries').html(entriesHtml);
                            }
                        });
                    } else {
                        alert('შეცდომა: ' + response.data);
                    }
                });
            });

            // Show history popup
            $(document).on('click', '.view-history-btn', function () {
                const id = $(this).data('order-id');
                $('#history-popup-' + id).fadeIn();
            });

            // Close popup
            $(document).on('click', '.close-history', function () {
                $(this).closest('.history-popup').fadeOut();
            });
        });
    </script>
<?php
}

// AJAX: კომენტარის შენახვა და ისტორიის დამატება
add_action('wp_ajax_save_cheap_money_comment', function () {
    if (!current_user_can('cheap_company')) {
        wp_send_json_error('ნებართვა არ გაქვს.');
    }

    $id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $comment = isset($_POST['comment']) ? sanitize_text_field($_POST['comment']) : '';

    if (!$id || strlen($comment) < 1) {
        wp_send_json_error('მონაცემები არ არის სრულყოფილი.');
    }

    update_post_meta($id, '_cheap_money_comment', $comment);

    $history = get_post_meta($id, '_cheap_money_comment_history', true) ?: [];
    $last_entry = end($history);

    if (!$last_entry || $last_entry['text'] !== $comment) {
        $history[] = [
            'text' => $comment,
            'time' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login,
        ];
        update_post_meta($id, '_cheap_money_comment_history', $history);
    }

    wp_send_json_success();
});

// AJAX: ისტორიის მიღება
add_action('wp_ajax_get_cheap_money_comment_history', function () {
    $id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$id) {
        wp_send_json_error('არ არის order ID');
    }
    $history = get_post_meta($id, '_cheap_money_comment_history', true) ?: [];
    wp_send_json_success(array_reverse($history)); // ✅ უკანასკნელი კომენტარი პირველად მოდის JavaScript-ში
});
