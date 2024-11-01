jQuery(document).ready(function ($) {
    // Handle button click event for "Refund via Wonderful"
    $('.refund-via-wonderful').on('click', function () {
        // Toggle visibility of elements
        $('.wc-order-refund-via-wonderful-items').slideToggle("slow");
        $('.add-items').slideToggle("slow");
        $('.wc-order-totals-items').slideToggle("slow");
    });

    $('.do-wonderful-refund').on('click', function() {
        const orderId = $('#wonderful_order_id').val();
        const wooId = $('#woo_order_id').val();
        const refundAmount = $('#wonderful_refund_amount').val();
        const refundReason = $('#wonderful_refund_reason').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'refund_via_wonderful',
                order_id: orderId,
                woo_id: wooId,
                refund_amount: refundAmount,
                refund_reason: refundReason,
            },
            success: function(response) {
                var refund = JSON.parse(response.data);
                let errorMessage = "";
                if (refund.invalid_fields) {
                    if (refund.invalid_fields.reason) {
                        errorMessage += " " + refund.invalid_fields.reason.join(", ");
                    } else if (refund.invalid_fields.refund_amount) {
                        errorMessage += " " + refund.invalid_fields.refund_amount.join(", ");
                    }
                }
                if (refund.error) {
                    // Backend validation error
                    $('.wc-order-failed-refund-panel').show();
                    $('.wc-order-successful-refund-panel').hide();
                    $('#wonderful-refund-failure-reason').text(refund.message + " " + errorMessage);
                } else {
                    // Successful call
                    $('.wc-order-successful-refund-panel').show();
                    $('.wc-order-failed-refund-panel').hide();
                }
            },
            error: function(xhr, status, error) {
                // Network error or server error
                $('.wc-order-failed-refund-panel').show();
                $('.wc-order-successful-refund-panel').hide();
                $('#wonderful-refund-failure-reason').text(status + "." + error)
            }
        });
    })
});
