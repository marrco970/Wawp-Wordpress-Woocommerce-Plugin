jQuery(document).ready(function($) {
    var resendDelay = 0;
    var emailDomains = AWP_Signup_Params.email_domains || [];
    var otpMethod = AWP_Signup_Params.otp_method || 'whatsapp';
    var passwordStrong = AWP_Signup_Params.password_strong || 0;
    
// Prefill phone from OTP login redirect (?pre_phone=)
(function prefillPhoneFromQuery(){
    try {
        var params = new URLSearchParams(window.location.search);
        var p = params.get('pre_phone');
        if (p) {
            p = decodeURIComponent(p);
            $('#awp_phone').val(p).trigger('input');
        }
    } catch(e) {}
})();

    function displayMessage(message, type = 'success') {
        var color = (type === 'success') ? '#15803d' : '#b91c1c';
        var background = (type === 'success') ? '#e0faec' : '#fecaca';
        $('.awp-success-message').text(message).css({
            'color': color,
            'padding': '8px 16px',
            'border-radius': '8px',
            'margin-top': '24px',
            'text-align': 'center',
            'font-size': '14px',
            'font-weight': '600',
            'background-color': background
        }).show();
    }

    function clearMessages() {
        $('.awp-success-message').hide().text('');
        $('.awp-error-message').hide().text('');
    }

    function showFieldError($input, errorText) {
        $input.closest('.awp-form-group')
              .next('.awp-error-message')
              .text(errorText)
              .show();
    }

    if ($('#awp_email').length > 0) {
        $('#awp_email').autocomplete({
            source: function(request, response) {
                var term = request.term;
                var atIndex = term.indexOf('@');
                if (atIndex === -1) {
                    if (term.trim() === '') {
                        response([]);
                        return;
                    }
                    var suggestions = [];
                    for (var i = 0; i < emailDomains.length && suggestions.length < 5; i++) {
                        suggestions.push(term + '@' + emailDomains[i]);
                    }
                    response(suggestions);
                } else {
                    var prefix = term.substring(0, atIndex + 1);
                    var suffix = term.substring(atIndex + 1);
                    var suggestions = [];
                    for (var i = 0; i < emailDomains.length && suggestions.length < 5; i++) {
                        if (emailDomains[i].startsWith(suffix)) {
                            suggestions.push(prefix + emailDomains[i]);
                        }
                    }
                    response(suggestions);
                }
            },
            minLength: 1,
            select: function(event, ui) {
                $('#awp_email').val(ui.item.value);
                return false;
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            var atIndex = item.label.indexOf('@');
            var username = item.label.substring(0, atIndex);
            var domain = item.label.substring(atIndex);
            var formatted = '<span>' + username + '<span style="color: green;">' + domain + '</span></span>';
            return $('<li></li>')
                .data("item.autocomplete", item)
                .append(formatted)
                .appendTo(ul);
        };
    }

    $('#awp-signup-form').on('submit', function(e) {
        e.preventDefault();
        clearMessages();
        var form = $(this);
        var data = form.serializeArray();
        data.push({ name: 'action', value: 'awp_signup_form_submit' });
        form.find('.awp-form-control').removeClass('awp-field-error awp-field-success');
        var hasError = false;
        form.find('.awp-form-control[required]').each(function() {
            if ($.trim($(this).val()) === '') {
                $(this).addClass('awp-field-error');
                showFieldError($(this), AWP_Signup_L10n.fieldRequired);
                hasError = true;
            }
        });
        if (hasError) return;
        var submitButton = form.find('.awp-submit-button');
        var originalBtnHtml = submitButton.html();
        submitButton.prop('disabled', true).html('<i class="ri-loader-5-line"></i>');
        $.post(AWP_Signup_Params.ajax_url, data, function(response) {
            submitButton.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                if (response.data.redirect_url) {
                    displayMessage(response.data.message);
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 2000);
                    return;
                }
                if (response.data.otp_transient) {
                    form.hide();
                    $('#awp-signup-branding').hide();
                    $('#awp-otp-section').show();
                    if (response.data.otp_method === 'email') {
                        $('#otp-icon').attr('class', 'ri-mail-line');
                        $('#awp-otp-sent-heading').text(AWP_Signup_L10n.checkYourEmail);
                        $('#awp-otp-sent-message')
                          .html(AWP_Signup_L10n.weSentCode.replace('%s', response.data.email));
                        $('#awp-resend-message').text(AWP_Signup_L10n.wrongEmail);
                        $('#awp-edit-contact-btn').text(AWP_Signup_L10n.reEnterEmail);
                    } else {
                        $('#otp-icon').attr('class', 'ri-whatsapp-line');
                        $('#awp-otp-sent-heading').text(AWP_Signup_L10n.checkYourWhatsApp);
                        $('#awp-otp-sent-message')
                          .html(AWP_Signup_L10n.weSentCode.replace('%s', '+' + response.data.phone));
                        $('#awp-resend-message').text(AWP_Signup_L10n.wrongWhatsApp);
                        $('#awp-edit-contact-btn').text(AWP_Signup_L10n.reEnterNumber);
                    }
                    $('#awp_otp_transient').val(response.data.otp_transient);
                    resendDelay = 0;
                    $('#awp-resend-otp-btn')
                        .prop('disabled', false)
                        .html(AWP_Signup_L10n.resendCode);
                } else {
                    displayMessage(response.data.message);
                    form.find('.awp-form-control').addClass('awp-field-success');
                    form[0].reset();
                }
            } else {
                if (response.data.errors) {
                    $.each(response.data.errors, function(field, message) {
                        var $input = $('#' + field);
                        if ($input.length) {
                            $input.addClass('awp-field-error');
                            showFieldError($input, message);
                        }
                    });
                }
                if (response.data.message) {
                    displayMessage(response.data.message, 'error');
                }
            }
        }).fail(function() {
            submitButton.prop('disabled', false).html(originalBtnHtml);
            displayMessage(AWP_Signup_L10n.unexpectedError, 'error');
        });
    });

    $('#awp-otp-form').on('submit', function(e) {
        e.preventDefault();
        clearMessages();
        var form = $(this);
        var data = form.serializeArray();
        data.push({ name: 'action', value: 'awp_signup_verify_otp' });
        form.find('.awp-form-control').removeClass('awp-field-error awp-field-success');
        var hasError = false;
        form.find('.awp-form-control[required]').each(function() {
            if ($.trim($(this).val()) === '') {
                $(this).addClass('awp-field-error');
                showFieldError($(this), AWP_Signup_L10n.fieldRequired);
                hasError = true;
            }
        });
        if (hasError) return;
        var verifyButton = form.find('.awp-submit-button');
        var originalBtnHtml = verifyButton.html();
        verifyButton.prop('disabled', true).html('<i class="ri-loader-5-line"></i>');
        $.post(AWP_Signup_Params.ajax_url, data, function(response) {
            verifyButton.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                displayMessage(response.data.message);
                if (response.data.redirect_url) {
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 2000);
                } else {
                    $('#awp-signup-container').hide();
                }
            } else {
                if (response.data.errors) {
                    $.each(response.data.errors, function(field, message) {
                        var $input = $('#' + field);
                        if ($input.length) {
                            $input.addClass('awp-field-error');
                            showFieldError($input, message);
                        }
                    });
                }
                if (response.data.message) {
                    displayMessage(response.data.message, 'error');
                }
            }
        }).fail(function() {
            verifyButton.prop('disabled', false).html(originalBtnHtml);
            displayMessage(AWP_Signup_L10n.unexpectedError, 'error');
        });
    });

    $(document).on('click', '#awp-edit-contact-btn', function() {
        $('#awp-otp-section').hide();
        clearMessages();
        $('#awp-otp-form')[0].reset();
        $('#awp_otp_transient').val('');
        $('#awp-signup-branding').show();
        $('#awp-signup-form').show();
        resendDelay = 0;
        $('#awp-resend-otp-btn')
            .prop('disabled', false)
            .html(AWP_Signup_L10n.resendCode);
    });

    $(document).on('click', '#awp-resend-otp-btn', function() {
        var resendButton = $(this);
        var originalBtnHtml = resendButton.html();
        var transientKey = $('#awp_otp_transient').val();
        if (!transientKey) {
            displayMessage(AWP_Signup_L10n.unexpectedError, 'error');
            $('#awp-otp-section').hide();
            $('#awp-signup-form').show();
            return;
        }
        resendButton.prop('disabled', true).html('<i class="ri-loader-5-line"></i>');
        $.post(AWP_Signup_Params.ajax_url, {
            action: 'awp_signup_resend_otp',
            awp_otp_transient: transientKey,
            awp_signup_nonce_field: AWP_Signup_Params.nonce
        }, function(response) {
            if (response.success) {
                if (response.data.otp_method === 'email') {
                    $('#awp-otp-sent-heading').text(AWP_Signup_L10n.checkYourEmail);
                    $('#awp-otp-sent-message')
                          .html(AWP_Signup_L10n.weSentCode.replace('%s', response.data.email));
                } else {
                    $('#awp-otp-sent-heading').text(AWP_Signup_L10n.checkYourWhatsApp);
                    $('#awp-otp-sent-message')
                          .html(AWP_Signup_L10n.weSentCode.replace('%s', '+' + response.data.phone));
                }
                displayMessage(response.data.message);
                $('#awp_otp_transient').val(response.data.otp_transient);
                resendDelay += 5;
                setTimeout(function() {
                    resendButton.prop('disabled', false)
                                .html(AWP_Signup_L10n.resendCode);
                }, resendDelay * 1000);
            } else {
                displayMessage(response.data.message, 'error');
                resendButton.prop('disabled', false).html(originalBtnHtml);
            }
        }).fail(function() {
            displayMessage(AWP_Signup_L10n.unexpectedErrorResend, 'error');
            resendButton.prop('disabled', false).html(originalBtnHtml);
        });
    });

    function getPasswordStrength(password) {
        var strength = { percent: 0, color: 'red', text: 'Weak' };
        var rules = [
            { regex: /[A-Z]/, increment: 20 },
            { regex: /[a-z]/, increment: 20 },
            { regex: /[0-9]/, increment: 20 },
            { regex: /.{8,}/, increment: 20 },
            { regex: /[^A-Za-z0-9]/, increment: 20 }
        ];
        rules.forEach(function(rule) {
            if (rule.regex.test(password)) {
                strength.percent += rule.increment;
            }
        });
        if (strength.percent >= 90) {
            strength.color = '#22c55e';
            strength.text = 'excellent';
        } else if (strength.percent >= 70) {
            strength.color = '#22c55e';
            strength.text = 'Strong';
        } else if (strength.percent >= 50) {
            strength.color = '#f59e0b';
            strength.text = 'Average';
        } else {
            strength.color = '#ef4444';
            strength.text = 'Weak';
        }
        return strength;
    }

    if ($('#awp_password').length > 0 && passwordStrong) {
        $('#awp_password').on('input', function() {
            var password = $(this).val();
            var allValid = true;
            $('#password-requirements li').each(function(index) {
                var requirement = $(this).text();
                var regex;
                switch(index) {
                    case 0: regex = /[A-Z]/; break;
                    case 1: regex = /[a-z]/; break;
                    case 2: regex = /[0-9]/; break;
                    case 3: regex = /.{8,}/; break;
                    case 4: regex = /[^A-Za-z0-9]/; break;
                    default: regex = /.*/; break;
                }
                if (regex.test(password)) {
                    $(this).find('.awp-check').html('<i class="ri-check-line text-success"></i>');
                } else {
                    $(this).find('.awp-check').html('<i class="ri-close-line text-danger"></i>');
                    allValid = false;
                }
            });
            var strength = getPasswordStrength(password);
            $('#password-strength-meter').css({
                width: strength.percent + '%',
                backgroundColor: strength.color,
                height: '6px',
                transition: 'width 0.3s, background-color 0.3s',
                marginTop: '8px',
                borderRadius: '4px'
            });
            $('#password-strength-text').text(strength.text).css('color', strength.color);
        });
    }

    $('.awp-toggle-password').click(function() {
        var passwordInput = $(this).siblings('input[type="password"], input[type="text"]');
        var type = (passwordInput.attr('type') === 'password') ? 'text' : 'password';
        passwordInput.attr('type', type);
        $(this).find('i').toggleClass('ri-eye-off-line');
    });
});
