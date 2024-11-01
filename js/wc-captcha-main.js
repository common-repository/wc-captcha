(function ($) {
    jQuery(document).ready(function () {
        $('form').on('submit', function (e) {
            var $this = jQuery(this);
            if ($this.find('.wc_captch-allform').length > 0) {
                e.preventDefault();
                var wc_value = $this.find('input[name="wc-value"]').val();
                jQuery.ajax({
                    url: ajax_obj.ajaxurl,
                    type: 'POST',
                    data: { 'action': 'custom_captcha_error_func', 'wc-value': wc_value, 'nonce': ajax_obj.nonce },
                    success: function (data) {
                        if (data.result == 'success') {
                            jQuery('.wc_captcha-form').removeClass('wc_captch-allform')
                            $this.submit();
                        } else {
                            jQuery('.wc_error-msg').html('<p>'+data.result+'</p>');
                        }
                    },
                    error: function (xhr, status, error) {
                        var errorMessage = xhr.status + ': ' + xhr.statusText;
                        alert('Error - ' + errorMessage);
                    }
                });
            }
        })
        // if ($(".wc_captch-allform")[0]) {
        //     $('form').on('submit', wc_custom_on_submit_function);
        // }
    });
}(jQuery));
