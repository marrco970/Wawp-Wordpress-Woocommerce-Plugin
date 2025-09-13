jQuery(document).ready(function($) {
    
    
    /* ------------------------------------------------------------
 * Resend cooldown: disables button + shows timer under it
 * Linear backoff 5s, 10s, 15s, ...
 * ---------------------------------------------------------- */
const cooldownState = {
    whatsapp_otp: { attempts: 0, timer: null },
    email_otp:    { attempts: 0, timer: null }
};

function startCooldown(method, $btn) {
    const state = cooldownState[method];
    if (!state || !$btn || !$btn.length) return;

    // Increment attempts: 1 => 15s, 2 => 30s, 3 => 45s...
    state.attempts += 1;
    const total = state.attempts * 15;
    let remaining = total;

    // Create / reuse a timer element directly after the button
    let $timer = $btn.siblings('.awp-resend-timer');
    if (!$timer.length) {
        $timer = $('<div class="awp-resend-timer" aria-live="polite" style="margin-top:6px;font-size:.85em;opacity:.85;"></div>');
        $btn.after($timer);
    }

    function label(sec) {
        if (window.awpOtpL10n && awpOtpL10n.resendIn) {
            return awpOtpL10n.resendIn.replace('%s', sec);
        }
        return 'You can resend in ' + sec + ' s';
    }

    // Disable and start the countdown
    $btn.prop('disabled', true);
    $timer.text(label(remaining));

    clearInterval(state.timer);
    state.timer = setInterval(() => {
        remaining -= 1;

        if (remaining <= 0) {
            clearInterval(state.timer);
            $btn.prop('disabled', false);
            $timer.remove(); // remove the timer line when done
            return;
        }

        $timer.text(label(remaining));
    }, 1000);
}

    
    
    
    /* ------------------------------------------------------------
     * Progress timeline messages (multi-step, smooth timings)
     * ---------------------------------------------------------- */
    const PROGRESS_STRINGS = {
        bridge: (window.awpOtpL10n && awpOtpL10n.progressBridge) || "We're building a secure bridge between you and us…",
        almost: (window.awpOtpL10n && awpOtpL10n.progressAlmost) || "We're almost sending the OTP to your WhatsApp…",
        go:     (window.awpOtpL10n && awpOtpL10n.progressGo)     || "Here we go…"
    };

    function ensureProgressBox($target) {
        let $box = $target.find('.awp-progress-box');
        if (!$box.length) {
            $box = $('<div class="awp-progress-box" style="margin:8px 0 0; font-size:.95em; opacity:.9;"></div>');
            $target.append($box);
        }
        return $box;
    }

    // Runs the 3-line flow; loops until stop() is called.
// Runs the 3-line flow; loops until stop() is called, at a calmer pace.
function startProgressTimeline($target) {
    const $box = ensureProgressBox($target);
    let cancelled = false;

    // Tunable timings (ms)
    const DUR = {
        bridge: 1200,   // time to show "building a secure bridge..."
        almost: 1600,   // time to show "we're almost sending your OTP..."
        go: 1600,       // time to show "here we go..."
        pause: 1200,    // short pause before looping again
        fade: 250       // fade in/out duration
    };

    function show(text) {
        $box.stop(true, true).fadeOut(DUR.fade, function () {
            $box.text(text).fadeIn(DUR.fade);
        });
    }

    function runOnce() {
        if (cancelled) return;

        // Start fresh with first line
        $box.stop(true, true).hide().text(PROGRESS_STRINGS.bridge).fadeIn(DUR.fade);

        // Step 2: "almost"
        setTimeout(() => {
            if (cancelled) return;
            show(PROGRESS_STRINGS.almost);
        }, DUR.bridge);

        // Step 3: "go"
        setTimeout(() => {
            if (cancelled) return;
            show(PROGRESS_STRINGS.go);
        }, DUR.bridge + DUR.almost);

        // Loop again after a pause
        setTimeout(() => {
            if (!cancelled) runOnce();
        }, DUR.bridge + DUR.almost + DUR.go + DUR.pause);
    }

    runOnce();

    // Call the returned function to stop & hide the progress box
    return () => {
        cancelled = true;
        $box.stop(true, true).fadeOut(DUR.fade);
    };
}


    /* ------------------------------------------------------------
     * Button spinner helper
     * ---------------------------------------------------------- */
    function withSpinner($btn, fn) {
        const originalHtml = $btn.html();
        $btn.data('original-html', originalHtml);
        $btn.prop('disabled', true).addClass('is-loading').html('<i class="ri-loader-5-line"></i>');
        const finish = () => {
            $btn.prop('disabled', false).removeClass('is-loading').html($btn.data('original-html'));
        };
        return fn(finish);
    }

    function setMessage($el, text, ok = true) {
        if (!$el || !$el.length) return;
        $el.html('<p class="' + (ok ? 'success' : 'error') + '">' + text + '</p>');
    }

    /* ------------------------------------------------------------
     * Centralized AJAX with progress timeline
     * ---------------------------------------------------------- */
    function ajaxPost(data, $msgBox, done) {
        // start the progress timeline immediately
        const stopTimeline = startProgressTimeline($msgBox);

        $.ajax({
            url: awpOtpAjax.ajax_url,
            method: 'POST',
            data: data,
            timeout: 20000
        }).done(function(resp) {
            if (typeof done === 'function') done(null, resp);
        }).fail(function(xhr) {
            const msg = xhr && xhr.responseJSON && xhr.responseJSON.data
                ? xhr.responseJSON.data
                : (xhr.status === 0 ? 'Network error. Please check your connection.' : 'Request failed. Please try again.');
            if ($msgBox) setMessage($msgBox, msg, false);
            if (typeof done === 'function') done(msg);
        }).always(function() {
            // stop and clear timeline
            stopTimeline();
        });
    }

    /* ------------------------------------------------------------
     * Tabs – hide when only one method is enabled
     * ---------------------------------------------------------- */
    function hideTabsIfSingle() {
        const $tabs = $('.awp-tab-list .awp-tab');
        if ($tabs.length <= 1) {
            $('.awp-tabs').hide();
        }
    }

    $('.awp-tab-list .awp-tab').on('click', function() {
        var tab_id = $(this).data('tab');
        $('.awp-tab-list .awp-tab').removeClass('active');
        $('.awp-tab-pane').removeClass('active');
        $(this).addClass('active');
        $('#' + tab_id).addClass('active');
    });

    function activateFirstTab() {
        var firstMethod = awpOtpAjax.first_login_method;
        if (firstMethod) {
            $('.awp-tab-list .awp-tab').removeClass('active');
            $('.awp-tab-pane').removeClass('active');
            $('.awp-tab-list .awp-tab[data-tab="' + firstMethod + '"]').addClass('active');
            $('#' + firstMethod).addClass('active');
        }
        hideTabsIfSingle();
    }
    activateFirstTab();

    /* ------------------------------------------------------------
     * Resend timers (kept simple & smooth)
     * ---------------------------------------------------------- */
    var resendTimers = { whatsapp_otp: null, email_otp: null };
    var resendCooldowns = { whatsapp_otp: 5, email_otp: 5 };

    function startResendTimer(method) {
        var $resendButton = method === 'whatsapp_otp' ? $('#awp_resend_otp_whatsapp') : $('#awp_resend_otp_email');
        var cooldown = resendCooldowns[method];
        var countdown = cooldown;

        $resendButton.prop('disabled', true).html(awpOtpL10n.resendCode);

        clearInterval(resendTimers[method]);
        resendTimers[method] = setInterval(function() {
            countdown--;
            if (countdown <= 0) {
                clearInterval(resendTimers[method]);
                $resendButton.prop('disabled', false).html(awpOtpL10n.resendCode);
            } else {
                // keep same text to avoid layout jumps
                $resendButton.html(awpOtpL10n.resendCode);
            }
        }, 1000);

        // exponential backoff
        resendCooldowns[method] = Math.min(resendCooldowns[method] * 2, 60);
    }

    /* ------------------------------------------------------------
     * Branding originals for “edit” links
     * ---------------------------------------------------------- */
    var originalLogoHTML = $('.awp-login-branding .awp-logo').html() || '';
    var originalTitle = $('.awp-login-branding .awp-login-title').text() || '';
    var originalDesc = $('.awp-login-branding .awp-login-description').text() || '';

    /* ------------------------------------------------------------
     * WhatsApp: Request OTP
     * ---------------------------------------------------------- */
    $('#awp_request_otp_whatsapp').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var whatsapp = $('#awp_whatsapp').val();
        var $msgBox = $('#awp_login_message_whatsapp');

        if (whatsapp.trim() === '') {
            setMessage($msgBox, awpOtpAjax.emptyFields, false);
            return;
        }

        withSpinner($btn, (finish) => {
            $msgBox.html('');
            const data = {
                action: 'awp_request_otp',
                nonce: awpOtpAjax.nonce,
                login_method: 'whatsapp_otp',
                whatsapp: whatsapp
            };

            ajaxPost(data, $msgBox, function(err, response) {
                finish();
                if (err) return;

                if (response && response.success) {
                    $('.awp-tabs').hide(); // once requesting, UX focuses on OTP step
                    $('.awp-login-branding .awp-logo').html('<div class="awp-icon-wrapper"><i id="otp-icon" class="ri-whatsapp-line"></i></div>');
                    $('.awp-login-branding .awp-login-title').text(awpOtpL10n.checkWhatsApp);
                    $('.awp-login-branding .awp-login-description').html(
                        awpOtpL10n.weSentCode + " <br><b>+" + whatsapp + "</b>"
                    );
                    $('#awp_display_whatsapp').text(whatsapp);
                    $('#awp_otp_sent_message_whatsapp').fadeIn(300);
                    $('#awp_whatsapp_group').fadeOut(300, function() {
                        $('#awp_otp_group_whatsapp').fadeIn(300);
                    });
                    $('#awp_verify_otp_whatsapp, #awp_resend_otp_whatsapp').fadeIn(300);
                    $('#awp_request_otp_whatsapp').fadeOut(300);
                    startCooldown('whatsapp_otp', $('#awp_resend_otp_whatsapp'));
                    
                } else {
    // NEW: if backend sent a signup redirect, go there
    if (response && response.data && response.data.redirect_to_signup) {
        window.location.href = response.data.redirect_to_signup;
        return;
    }
    setMessage(
        $msgBox,
        response && response.data ? (response.data.message || response.data) : 'Invalid or expired OTP.',
        false
    );
}
            });
        });
    });

    /* ------------------------------------------------------------
     * WhatsApp: Verify OTP
     * ---------------------------------------------------------- */
    $('#awp_verify_otp_whatsapp').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var whatsapp = $('#awp_whatsapp').val();
        var otp = $('#awp_otp_whatsapp').val();
        var $msgBox = $('#awp_login_message_whatsapp');

        if (whatsapp.trim() === '' || otp.trim() === '') {
            setMessage($msgBox, awpOtpAjax.emptyFields, false);
            return;
        }

        withSpinner($btn, (finish) => {
            $msgBox.html('');
            const data = {
                action: 'awp_verify_otp',
                nonce: awpOtpAjax.nonce,
                login_method: 'whatsapp_otp',
                whatsapp: whatsapp,
                otp: otp
            };

            ajaxPost(data, $msgBox, function(err, response) {
                finish();
                if (err) return;

                if (response && response.success) {
                    setMessage($msgBox, response.data.message, true);
                    window.location.href = response.data.redirect_url;
                } else {
                    setMessage($msgBox, response && response.data ? response.data : 'Invalid or expired OTP.', false);
                }
            });
        });
    });

    /* ------------------------------------------------------------
     * WhatsApp: Resend OTP
     * ---------------------------------------------------------- */
    $('#awp_resend_otp_whatsapp').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var whatsapp = $('#awp_whatsapp').val();
        var $msgBox = $('#awp_login_message_whatsapp');

        if (whatsapp.trim() === '') {
            setMessage($msgBox, awpOtpAjax.emptyFields, false);
            return;
        }

        withSpinner($btn, (finish) => {
            $msgBox.html('');
            const data = {
                action: 'awp_request_otp',
                nonce: awpOtpAjax.nonce,
                login_method: 'whatsapp_otp',
                whatsapp: whatsapp
            };

            ajaxPost(data, $msgBox, function(err, response) {
                finish();
                if (err) return;

                if (response && response.success) {
                    $('#awp_display_whatsapp').text(whatsapp);
                    $('#awp_otp_sent_message_whatsapp').fadeIn(300);
                    setMessage($msgBox, response.data, true);
                   startCooldown('whatsapp_otp', $('#awp_resend_otp_whatsapp'));
                } else {
                    setMessage($msgBox, response && response.data ? response.data : 'Failed to resend OTP.', false);
                }
            });
        });
    });

    /* ------------------------------------------------------------
     * Email: Request OTP
     * ---------------------------------------------------------- */
    $('#awp_request_otp_email').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var email = $('#awp_email').val();
        var $msgBox = $('#awp_login_message_email');

        if (email.trim() === '') {
            setMessage($msgBox, awpOtpAjax.emptyFields, false);
            return;
        }

        withSpinner($btn, (finish) => {
            $msgBox.html('');
            const data = {
                action: 'awp_request_otp',
                nonce: awpOtpAjax.nonce,
                login_method: 'email_otp',
                email: email
            };

            ajaxPost(data, $msgBox, function(err, response) {
                finish();
                if (err) return;

                if (response && response.success) {
                    $('.awp-tabs').hide();
                    $('.awp-login-branding .awp-logo').html('<div class="awp-icon-wrapper"><i id="otp-icon" class="ri-mail-line"></i></div>');
                    $('.awp-login-branding .awp-login-title').text(awpOtpL10n.checkEmail);
                    $('.awp-login-branding .awp-login-description').html(
                        awpOtpL10n.weSentCode + " <br><b>" + email + "</b>"
                    );
                    $('#awp_display_email').text(email);
                    $('#awp_otp_sent_message_email').fadeIn(300);
                    $('#awp_email_group').fadeOut(300, function() {
                        $('#awp_otp_group_email').fadeIn(300);
                    });
                    $('#awp_verify_otp_email, #awp_resend_otp_email').fadeIn(300);
                    $('#awp_request_otp_email').fadeOut(300);
                    startCooldown('email_otp', $('#awp_resend_otp_email'));
                } else {
                    setMessage($msgBox, response && response.data ? response.data : 'Failed to send OTP.', false);
                }
            });
        });
    });

    /* ------------------------------------------------------------
     * Email: Verify OTP
     * ---------------------------------------------------------- */
    $('#awp_verify_otp_email').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var email = $('#awp_email').val();
        var otp = $('#awp_otp_email').val();
        var $msgBox = $('#awp_login_message_email');

        if (email.trim() === '' || otp.trim() === '') {
            setMessage($msgBox, awpOtpAjax.emptyFields, false);
            return;
        }

        withSpinner($btn, (finish) => {
            $msgBox.html('');
            const data = {
                action: 'awp_verify_otp',
                nonce: awpOtpAjax.nonce,
                login_method: 'email_otp',
                email: email,
                otp: otp
            };

            ajaxPost(data, $msgBox, function(err, response) {
                finish();
                if (err) return;

                if (response && response.success) {
                    setMessage($msgBox, response.data.message, true);
                    window.location.href = response.data.redirect_url;
                } else {
                    setMessage($msgBox, response && response.data ? response.data : 'Invalid or expired OTP.', false);
                }
            });
        });
    });

    /* ------------------------------------------------------------
     * Email+Password: Login
     * ---------------------------------------------------------- */
    $('#awp_login_email_password').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var login = $('#awp_login_email').val();
        var password = $('#awp_password_password').val();
        var $msgBox = $('#awp_login_message_email_password');

        if (login.trim() === '' || password.trim() === '') {
            setMessage($msgBox, awpOtpAjax.emptyFields, false);
            return;
        }

        withSpinner($btn, (finish) => {
            $msgBox.html('');
            const data = {
                action: 'awp_request_otp',
                nonce: awpOtpAjax.nonce,
                login_method: 'email_password',
                login: login,
                password: password
            };

            ajaxPost(data, $msgBox, function(err, response) {
                finish();
                if (err) return;

                if (response && response.success) {
                    setMessage($msgBox, response.data.message, true);
                    window.location.href = response.data.redirect_url;
                } else {
                    setMessage($msgBox, response && response.data ? response.data : 'Login failed.', false);
                }
            });
        });
    });

    /* ------------------------------------------------------------
     * Edit actions (restore branding)
     * ---------------------------------------------------------- */
    $('.awp-edit-whatsapp').on('click', function(e) {
        e.preventDefault();
        $('#awp_otp_group_whatsapp').fadeOut(300, function() {
            $('#awp_whatsapp_group').fadeIn(300);
            $('#awp_otp_sent_message_whatsapp').fadeOut(300);
        });
        $('#awp_verify_otp_whatsapp, #awp_resend_otp_whatsapp').fadeOut(300);
        $('#awp_request_otp_whatsapp').fadeIn(300);
        $('.awp-tabs').fadeIn(300);
        $('.awp-login-branding .awp-logo').html(originalLogoHTML);
        $('.awp-login-branding .awp-login-title').text(originalTitle);
        $('.awp-login-branding .awp-login-description').text(originalDesc);
        hideTabsIfSingle();
    });

    $('.awp-edit-email').on('click', function(e) {
        e.preventDefault();
        $('#awp_otp_group_email').fadeOut(300, function() {
            $('#awp_email_group').fadeIn(300);
            $('#awp_otp_sent_message_email').fadeOut(300);
        });
        $('#awp_verify_otp_email, #awp_resend_otp_email').fadeOut(300);
        $('#awp_request_otp_email').fadeIn(300);
        $('.awp-tabs').fadeIn(300);
        $('.awp-login-branding .awp-logo').html(originalLogoHTML);
        $('.awp-login-branding .awp-login-title').text(originalTitle);
        $('.awp-login-branding .awp-login-description').text(originalDesc);
        hideTabsIfSingle();
    });

    /* ------------------------------------------------------------
     * Password show/hide
     * ---------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('awp_password_password');
        const showIcon      = document.getElementById('show-icon');
        const hideIcon      = document.getElementById('hide-icon');
        if (!passwordInput || !showIcon || !hideIcon) return;
        showIcon.addEventListener('click', () => {
            passwordInput.type = 'text';
            showIcon.classList.add('hidden');
            hideIcon.classList.remove('hidden');
        });
        hideIcon.addEventListener('click', () => {
            passwordInput.type = 'password';
            hideIcon.classList.add('hidden');
            showIcon.classList.remove('hidden');
        });
    });
});
