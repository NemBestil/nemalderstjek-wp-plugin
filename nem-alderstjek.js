jQuery(($) => {
    $(document).on('checkout_error', () => {
        const el = $('.na8k-redirect-link');
        $('body').css('opacity', 1);
        if (!el.length) return; // not relevant error

        $('body').css('opacity', 0.5);
        const href = el.attr('href')
        window.location = href;
    });

    setTimeout(() => {
        // on load: if query args contains 'na8k_verified' then show the success window.alert
        if (window.wc_stripe_payment_request_params && wc_stripe_payment_request_params.na8k_age_verified) {
            $('#wc-stripe-payment-request-wrapper').prepend('<div class="woocommerce-message">Du opfylder produktets krav til minimumsalder.</div>');
        }
    })
})