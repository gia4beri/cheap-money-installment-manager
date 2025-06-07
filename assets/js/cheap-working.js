jQuery(document).ready(function($) {
    $('.cheap-start-working').on('click', function() {
        const button = $(this);
        const orderId = button.data('order-id');

        $.post(cheapWorkingData.ajax_url, {
            action: 'cheap_mark_working',
            order_id: orderId,
            nonce: cheapWorkingData.nonce
        }, function(response) {
            if (response.success) {
                button.replaceWith('საქმეზე მუშაობს (' + response.data.username + ')');
            } else {
                alert('დაფიქსირდა შეცდომა!');
            }
        });
    });
});
