jQuery(document).ready(function($) {
    var otpSent = false;
    var resendInterval;
    var baseHoldTime = 5;
    var resendCount = 0;
    var currentHoldTime = 5;
    var $awpOtpPopup = $('#awp_otp_popup');
    var $userPhoneNumber = $('#user_phone_number');
    var $awpResendOtpBtn = $('#awp_resend_otp_btn');
    var $awpResendTimer = $('#awp_resend_timer');
    var $awpVerifyOtpBtn = $('#awp_verify_otp_btn');
    var $awpOtpInput = $('#awp_otp_input');
    var $popupMessage = $awpOtpPopup.find('.awp-popup-message');
    var $checkoutForm = $('form.woocommerce-checkout');
    
    const data = {
    // required for WordPress AJAX
    action:   'send_otp',
    security: otpAjax.nonce,

    /* -------------------------------------------------
     * Handy shortcuts used by your PHP handler
     * -------------------------------------------------*/
    phone_number: $('input[name="billing_phone"]').val(),
    first_name:   $('input[name="billing_first_name"]').val(),
    last_name:    $('input[name="billing_last_name"]').val(),
    email:        $('input[name="billing_email"]').val(),

    /* -------------------------------------------------
     * Full billing address block
     * -------------------------------------------------*/
    billing_first_name:  $('input[name="billing_first_name"]').val(),
    billing_last_name:   $('input[name="billing_last_name"]').val(),
    billing_company:     $('input[name="billing_company"]').val(),
    billing_address_1:   $('input[name="billing_address_1"]').val(),
    billing_address_2:   $('input[name="billing_address_2"]').val(),
    billing_city:        $('input[name="billing_city"]').val(),
    billing_state:       $('input[name="billing_state"]').val(),
    billing_postcode:    $('input[name="billing_postcode"]').val(),
    billing_country:     $('select[name="billing_country"]').val(),
    billing_phone:       $('input[name="billing_phone"]').val(),   // duplicate of phone_number for convenience
    billing_email:       $('input[name="billing_email"]').val(),   // duplicate of email        for convenience

    /* -------------------------------------------------
     * Full shipping address block
     * -------------------------------------------------*/
    shipping_first_name: $('input[name="shipping_first_name"]').val(),
    shipping_last_name:  $('input[name="shipping_last_name"]').val(),
    shipping_company:    $('input[name="shipping_company"]').val(),
    shipping_address_1:  $('input[name="shipping_address_1"]').val(),
    shipping_address_2:  $('input[name="shipping_address_2"]').val(),
    shipping_city:       $('input[name="shipping_city"]').val(),
    shipping_state:      $('input[name="shipping_state"]').val(),
    shipping_postcode:   $('input[name="shipping_postcode"]').val(),
    shipping_country:    $('select[name="shipping_country"]').val()
    };


    function showPopupMessage(msg, isSuccess) {
        var successClass = 'custom-message-success';
        var errorClass = 'custom-message-error';
        $popupMessage.removeClass(successClass + ' ' + errorClass);
        $popupMessage.addClass(isSuccess ? successClass : errorClass);
        $popupMessage.text(msg).show();
    }

    function startResendTimer() {
        $awpResendOtpBtn.prop('disabled', true);
        updateResendTimerText();
        clearInterval(resendInterval);
        resendInterval = setInterval(function() {
            currentHoldTime--;
            updateResendTimerText();
            if (currentHoldTime <= 0) {
                clearInterval(resendInterval);
                $awpResendOtpBtn.prop('disabled', false);
                $awpResendTimer.text('');
            }
        }, 1000);
    }

    function updateResendTimerText() {
        $awpResendTimer.text(' (' + currentHoldTime + 's)');
    }

    async function sendOtp(phoneNumber, firstName, lastName, email) {
        try {
            const response = await $.post({
                url: otpAjax.ajaxurl,
                data: {
                    action: 'send_otp',
                    phone_number: phoneNumber,
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    security: otpAjax.nonce
                }
            });
            if (response.success) {
                showPopupMessage(awp_translations.otp_sent_success, true);
                $awpOtpPopup.show();
                otpSent = true;
                startResendTimer();
            } else {
                showPopupMessage(response.data || awp_translations.otp_sent_failure, false);
            }
        } catch (error) {
            showPopupMessage(awp_translations.otp_sent_failure, false);
        }
    }

    async function verifyOtp() {
        var otp = $awpOtpInput.val().trim();
        if (otp.length < 6) {
            showPopupMessage('Please enter the 6-digit OTP.', false);
            return;
        }
        try {
            const response = await $.post({
                url: otpAjax.ajaxurl,
                dataType: 'json',
                data: {
                    action: 'verify_otp',
                    otp: otp,
                    security: otpAjax.nonce
                }
            });
            if (response.success) {
                showPopupMessage(awp_translations.otp_verified_success, true);
                $awpOtpPopup.hide();
                $checkoutForm.submit();
            } else {
                showPopupMessage(response.data || awp_translations.otp_incorrect, false);
            }
        } catch (error) {
            showPopupMessage(awp_translations.otp_incorrect, false);
        }
    }

 // Watch for checkout errors rendered
  $(document).on('checkout_error', function(e, error_messages){
    // If "You changed your phone number..." is in the errors, open OTP popup
    if (error_messages.includes("You changed your phone number. Please verify OTP again.")) {
      // Clear the existing notices so they don't remain on screen
      $('.woocommerce-NoticeGroup-checkout').empty();

      // Force our popup to appear
      var phoneNumber = $('#billing_phone').val();
      var firstName   = $('#billing_first_name').val();
      var lastName    = $('#billing_last_name').val();
      var email       = $('#billing_email').val();

      $('#user_phone_number').text(phoneNumber);
      // Possibly re-send OTP automatically:
      sendOtp(phoneNumber, firstName, lastName, email);

      // Or just show the popup, up to you
      $('#awp_otp_popup').show();
      $('#awp_otp_input').focus();
    }
  });

    $(document).on('click', '#place_order', function(e) {
        if (otpAjax.verified === 'true') return;
        var chosenPaymentMethod = $('input[name="payment_method"]:checked').val();
        if (awpDisable && awpDisable.disableMethods.includes(chosenPaymentMethod)) return;
        var chosenShippingMethod = $('input[name^="shipping_method"]:checked').val() ||
                                   $('select[name^="shipping_method"]').val();
        if (awpDisableShipping && awpDisableShipping.disableMethods.includes(chosenShippingMethod)) return;
        if (!otpSent && $awpOtpPopup.is(':hidden')) {
            e.preventDefault();
            var phoneNumber = $('#billing_phone').val();
            var firstName = $('#billing_first_name').val();
            var lastName = $('#billing_last_name').val();
            var email = $('#billing_email').val();
            $userPhoneNumber.text(phoneNumber);
            sendOtp(phoneNumber, firstName, lastName, email);
            $awpOtpPopup.show();
            $awpOtpInput.focus();
        }
    });

    $(document).on('click', '.awp-otp-popup-close', function() {
        $awpOtpPopup.hide();
        showPopupMessage('', false);
        otpSent = false;
        $awpOtpInput.val('');
    });

    $awpVerifyOtpBtn.on('click', function() {
        verifyOtp();
    });

    $awpResendOtpBtn.on('click', function() {
        resendCount++;
        currentHoldTime = baseHoldTime * resendCount;
        var phoneNumber = $('#billing_phone').val();
        var firstName = $('#billing_first_name').val();
        var lastName = $('#billing_last_name').val();
        var email = $('#billing_email').val();
        sendOtp(phoneNumber, firstName, lastName, email);
    });

    $awpOtpInput.on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyOtp();
        }
    });
});
