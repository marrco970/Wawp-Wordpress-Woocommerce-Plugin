(function ($) {
    'use strict';

    /* =====================================================
     * Globals
     * ===================================================*/
    let currentStep = parseInt((window.wawpWizardData && wawpWizardData.initialStep) || 1, 10);
    const totalIssues = parseInt((window.wawpWizardData && wawpWizardData.totalIssues) || 0, 10);
    const isQrEnabled = (window.wawpWizardData && wawpWizardData.isQrEnabled) || false;
    const awpAjax = window.wawpWizardAjax || {};
    
    let qrFlowPollInterval = null;

    /* =====================================================
     * AJAX and UI Helpers
     * ===================================================*/
    function displayAdminNotice(msg, type = 'success') {
        const noticeContainer = $('#wawp-wizard-content');
        let notice = noticeContainer.find('.wawp-wizard-notice');
        if (notice.length === 0) {
            notice = $(`<div class="wawp-wizard-notice"></div>`);
            noticeContainer.prepend(notice);
        }
        
        notice.removeClass('notice-success notice-error').addClass(`notice-${type}`);
        notice.html(`<p>${msg}</p>`).fadeIn();

        if (type !== 'error') {
            setTimeout(() => {
                notice.fadeOut(500, () => notice.remove());
            }, 5000);
        }
    }

    function showSpinner(isSilent = false, container = '#wawp-wizard-content') {
        if (!isSilent) {
            let spinner = $(container).find('.awp-loading-spinner-overlay');
            if(spinner.length === 0) {
                 $(container).append('<div class="awp-loading-spinner-overlay" style="display:none;"><div class="awp-loading-spinner"><i class="ri-loader-4-line"></i></div></div>');
                 spinner = $(container).find('.awp-loading-spinner-overlay');
            }
            spinner.fadeIn(200);
        }
    }

    function hideSpinner(isSilent = false, container = '#wawp-wizard-content') {
        if (!isSilent) {
            $(container).find('.awp-loading-spinner-overlay').fadeOut(200);
        }
    }

    function requestAjax(data, onSuccess, isSilent = false, onError = null) {
        showSpinner(isSilent);
        $.post(awpAjax.ajax_url, data)
            .done(res => {
                hideSpinner(isSilent);
                if (res.success) {
                    if (typeof onSuccess === 'function') onSuccess(res);
                } else {
                    const errorMessage = res.data && res.data.message ? res.data.message : (awpAjax.unexpectedError || 'An unexpected error occurred.');
                    if (typeof onError === 'function') {
                        onError(res.data || { message: errorMessage });
                    } else if (!isSilent) {
                        displayAdminNotice(errorMessage, 'error');
                    }
                }
            })
            .fail((jqXHR, textStatus, errorThrown) => {
                hideSpinner(isSilent);
                const errorMsg = `${awpAjax.unexpectedError || 'An unexpected error occurred.'} (Network Error: ${textStatus} ${errorThrown || ''})`;
                if (typeof onError === 'function') {
                    onError({ message: errorMsg, raw_response_body_preview: jqXHR.responseText });
                } else if (!isSilent) {
                    displayAdminNotice(errorMsg, 'error');
                }
            });
    }

    /* =====================================================
     * Wizard Core Logic
     * ===================================================*/
    function updateProgress() {
        $('.wawp-progress-step').removeClass('active');
        for (let i = 1; i <= 5; i++) {
            if (i <= currentStep) {
                $('.wawp-progress-step.step-' + i).addClass('active');
            }
        }
    }

    function updateUrlStep(step) {
        const url = new URL(window.location.href);
        url.searchParams.set('wawp_popup_step', step);
        history.replaceState({}, '', url.toString());
    }

    function removeUrlStep() {
        const url = new URL(window.location.href);
        url.searchParams.delete('wawp_popup_step');
        history.replaceState({}, '', url.toString());
    }

    function showStep(step) {
        $('.wawp-step').removeClass('active');
        $('#wawp-step-' + step).addClass('active');
        currentStep = step;
        if (step <= 5) {
            updateProgress();
        }
        updateUrlStep(step);
        $(document).trigger('wawp-show-step', [step]);
    }

    /* =====================================================
     * QR-FLOW for Step 4
     * ===================================================*/
    function stopQrFlow() {
        if (qrFlowPollInterval) {
            clearInterval(qrFlowPollInterval);
            qrFlowPollInterval = null;
        }
    }

    function startQrFlow() {
        let instId = null, accTok = null, pollCnt = 0;
        const $stat = $('#awp-qr-wizard-status'), $img = $('#awp-qr-wizard-img'), $msg = $('#awp-qr-wizard-msg');
        const T = awpAjax;

        $('#wawp-step-4 .wawp-next-btn').show();

        function fail(msg) {
            $stat.html(`<i class="ri-error-warning-fill" style="color:#c00;font-size:24px;"></i><p>${msg || T.error}</p>`);
            $img.hide();
        }

        function saveInstance(name) {
            stopQrFlow();
            $stat.show().html(`<i class="ri-checkbox-circle-fill" style="color:#28a745;font-size:24px;"></i><p>${T.connected}</p>`);
            $img.hide();
            $msg.text('');
            $.post(T.ajax_url, {
                action: 'awp_qr_save_online_instance_action', nonce: T.nonce,
                instance_name: name || `QR-${instId.substring(0, 8)}`,
                instance_id: instId, access_token: accTok,
            }).always(function (res) {
                $stat.html(`<p>${T.saved}</p>`);
                setTimeout(function () {
                    showStep(5);
                }, 1500);
            });
        }

        function startPolling() {
            stopQrFlow();
            pollCnt = 0;
            qrFlowPollInterval = setInterval(function () {
                $.post(T.ajax_url, {
                    action: 'awp_qr_poll_instance_status', nonce: T.nonce,
                    instance_id: instId, access_token: accTok,
                }).done(function (res) {
                    if (res.success && res.data.status_api === 'success') {
                        stopQrFlow();
                        saveInstance(res.data.data_api?.name || '');
                    }
                });
                if (++pollCnt === 4) {
                    stopQrFlow();
                    fetchQr();
                }
            }, 15000);
        }

        function fetchQr() {
            $stat.html(`<div class="awp-spinner large"></div><p>${T.fetching}</p>`);
            $img.hide();
            $.post(T.ajax_url, {
                action: 'awp_qr_get_code_action', nonce: T.nonce,
                instance_id: instId, access_token: accTok,
            }).done(function (res) {
                if (!res.success) throw res.data?.message || T.error;
                $img.attr('src', res.data.qr_code_base64).show();
                $stat.hide();
                $msg.text(T.scanHint);
                startPolling();
            }).fail(function (xhr) { fail(xhr?.responseJSON?.data?.message); });
        }

        $stat.html(`<div class="awp-spinner large"></div><p>${T.creating}</p>`);
        $.post(T.ajax_url, { action: 'awp_qr_create_new_instance_action', nonce: T.nonce })
            .done(function (res) {
                if (!res.success) throw res.data?.message || T.error;
                instId = res.data.instance_id;
                accTok = res.data.access_token;
                fetchQr();
            }).fail(function (xhr) { fail(xhr?.responseJSON?.data?.message); });
    }

    /* =====================================================
     * Final Checks for Step 6
     * ===================================================*/
    function runFinalChecks() {
        const $finalChecksContainer = $('#wawp-final-checks');
        const $checklist = $finalChecksContainer.find('.wawp-checklist');
        const $progressBar = $finalChecksContainer.find('.wawp-progress-bar');
        const $issuesNotice = $('#wawp-final-issues-notice');
        const $finishScreen = $('#wawp-finish-screen');
        const $finishButton = $('#wawp-step-6 .wawp-next-btn');

        // Reset UI before running checks
        $finalChecksContainer.show();
        $finishScreen.hide();
        $issuesNotice.hide();
        $finishButton.text('Checking...').prop('disabled', true);

        $checklist.find('.icon').removeClass('success error').addClass('loading');
        $progressBar.css('width', '0%');

        const checks = [
            { id: '#check-db', key: 'db_ok' },
            { id: '#check-cron-status', key: 'cron_status_ok' },
            { id: '#check-cron-const', key: 'cron_const_ok' },
            { id: '#check-instances', key: 'instances_ok' },
            { id: '#check-issues', key: 'total_issues' }
        ];
        let completedChecks = 0;
        
        const updateCheckStatus = (selector, isOk) => {
            const $item = $(selector);
            setTimeout(() => {
                $item.find('.icon').removeClass('loading').addClass(isOk ? 'success' : 'error');
                completedChecks++;
                const percentage = (completedChecks / checks.length) * 100;
                $progressBar.css('width', percentage + '%');

                if (completedChecks === checks.length) {
                    $finishButton.prop('disabled', false);
                }
            }, completedChecks * 400); // Stagger the check animations
        };

        requestAjax({ action: 'wawp_wizard_get_final_checks', nonce: awpAjax.nonce }, res => {
            if (res.success) {
                const data = res.data;
                updateCheckStatus('#check-db', data.db_ok);
                updateCheckStatus('#check-cron-status', data.cron_status_ok);
                updateCheckStatus('#check-cron-const', data.cron_const_ok);
                updateCheckStatus('#check-instances', data.instances_ok);
                updateCheckStatus('#check-issues', data.total_issues === 0);

                setTimeout(() => {
                    if (data.total_issues > 0) {
                        $issuesNotice.show();
                    } else {
                        $finalChecksContainer.hide();
                        $finishScreen.show();
                    }
                    $finishButton.text('Finish');
                }, (checks.length + 1) * 400);
            }
        });
    }

    /* =====================================================
     * Event Handlers
     * ===================================================*/
    $(document).ready(function () {
        const urlStep = new URL(window.location.href).searchParams.get('wawp_popup_step');
        if (urlStep) {
            $('#wawp-wizard-modal').fadeIn();
            showStep(parseInt(urlStep, 10));
        }

        $('#wawp-floating-launch').on('click', function () {
            $('#wawp-wizard-modal').fadeIn();
            showStep(currentStep);
        });

        $('#wawp-wizard-close').on('click', function (e) {
            e.preventDefault();
            $('#wawp-wizard-modal').fadeOut();
            removeUrlStep();
        });

        $('.wawp-next-btn').on('click', function (e) {
            e.preventDefault();
            const $this = $(this);

            if ($this.is('#wawp-finish-button') || ($this.closest('.wawp-step').is('#wawp-step-6'))) {
                $('#wawp-wizard-modal').fadeOut();
                removeUrlStep();
                return;
            }

            if (currentStep === 4) {
                stopQrFlow();
            }
            let nextStep = currentStep + 1;
          //  if (currentStep === 3 && !isQrEnabled) {
            //    nextStep = 5;
            //}
            
             if (currentStep === 3) {
                 nextStep = 5;
            }
            showStep(nextStep);
        });

        $('.wawp-prev-btn').on('click', function (e) {
            e.preventDefault();
            if (currentStep === 4) {
                stopQrFlow();
            }
            let prevStep = currentStep - 1;
            //if (currentStep === 5 && !isQrEnabled) {
              ///  prevStep = 3;
            //}
            
             if (currentStep === 5) {
                 prevStep = 3;
            }
            showStep(prevStep);
        });

        $(document).on('wawp-show-step', function (e, stepNo) {
            if (stepNo === 4 && isQrEnabled) {
                startQrFlow();
            } else if (stepNo === 4 && !isQrEnabled) {
                showStep(5);
            }
            
            if (stepNo === 5) {
                if ($.fn.select2) {
                    $('#wawp_notif_selected_instance:not(.select2-hidden-accessible)').select2({
                        placeholder: 'Select instance(s)…',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#wawp_notif_selected_instance').closest('.instance-select')
                    });
                     $('#awp-default-country-code:not(.select2-hidden-accessible)').select2({
                        width: '100%',
                        dropdownParent: $('#awp-default-country-code').closest('.awp-setting-end')
                    });
                }
            }
            
            if (stepNo === 6) {
                runFinalChecks();
            }
        });

        // Save Sender Settings
        $(document).on('submit', '#wawp-wizard-sender-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = $form.serialize();
            const data = formData + '&action=wawp_wizard_save_sender_settings&nonce=' + awpAjax.save_sender_nonce;
            
            requestAjax(data, res => {
                displayAdminNotice(res.data.message || awpAjax.settingsSaved, 'success');
            }, false, err => {
                displayAdminNotice(err.message, 'error');
            });
        });

        // Save Country Settings
        $(document).on('submit', '#wawp-wizard-country-form', function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = $form.serialize();
            const data = formData + '&action=wawp_wizard_save_country&nonce=' + awpAjax.nonce;
            
            requestAjax(data, res => {
                displayAdminNotice(res.data.message || awpAjax.countrySaved, 'success');
            }, false, err => {
                displayAdminNotice(err.message, 'error');
            });
        });

        // Re-run checks button
        $(document).on('click', '#wawp-rerun-checks-btn, #wawp-recheck-status-btn', function(e) {
            e.preventDefault();
            runFinalChecks();
        });

        // Sync/Repair logs accordion
        $(document).on('click', '.wawp-log-toggle', function (e) {
            e.preventDefault();
            const $acc = $(this).closest('.wawp-accordion');
            const open = $acc.toggleClass('open').hasClass('open');
            $(this).text(open ? 'Hide logs' : 'Show logs');
        });
    });

    window.updateWawpLogs = function(html) {
        $('#wawp-sync-logs').html(html);
    };

})(jQuery);
