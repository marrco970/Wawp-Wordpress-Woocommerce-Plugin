jQuery(document).ready(function($) {
    let awpOverLimit = false;
    let awpCriticalError = false;
    let qrPollingInterval = null;
    let currentQrInstanceId = null;
    let currentQrAccessToken = null;
    let qrPollingCounter = 0;
    const QR_POLLING_MAX_ATTEMPTS_BEFORE_REFRESH = 6;
    const QR_POLLING_INTERVAL_MS = 15000;         
    let awpPrevSnapshot   = null;
    let awpInitialSnapDone = false; 
function awpDetectChangeAndMaybeReload(rows) {

    const snap = {};
    rows.forEach(r => snap[r.instance_id] = String(r.status).toLowerCase());

    if (!awpInitialSnapDone) {
        awpPrevSnapshot   = snap;
        awpInitialSnapDone = true;
        return;
    }

    let changed = false;

    Object.keys(snap).forEach(id => {
        if (!awpPrevSnapshot[id] || awpPrevSnapshot[id] !== snap[id]) {
            changed = true;
        }
    });

    Object.keys(awpPrevSnapshot).forEach(id => {
        if (!snap[id]) { changed = true; }
    });

    if (changed) {
        sessionStorage.setItem('awpJustReloaded', '1');
        location.reload();
    } else {
        awpPrevSnapshot = snap; 
    }
}

if (sessionStorage.getItem('awpJustReloaded')) {
    sessionStorage.removeItem('awpJustReloaded');
}

    function translateStatus(rawStatus) {
        const lower = String(rawStatus).toLowerCase();
        if (lower === 'online') {
            return awpAjax.online || 'Online';
        } else if (lower === 'offline') {
            return awpAjax.offline || 'Offline';
        } else if (lower === 'checking') {
            return awpAjax.checking || 'Checking';
        }
        return awpAjax.unknown || 'Unknown';
    }

    function autoCheckAllInstances() {
        if (awpCriticalError || (typeof awpAjax === 'undefined' || !awpAjax.nonce)) return;
        requestAjax(
            {
                action: 'awp_auto_check_all_instance_status',
                nonce: awpAjax.nonce
            },
            function (res) {
                if (res.success) {
                    if (awpAjax.debugMode) console.log('AutoCheckAllInstances:', res.data.message);
                    fetchInstances();
                    
                }
            },
            true
        );
    }

    if (typeof awpAjax !== 'undefined' && awpAjax.nonce) { 
        setInterval(() => {
            autoCheckAllInstances();
        }, 60000);
    }

    function displayAdminNotice(msg, type = 'success', isGlobal = true) {
        if (isGlobal) {
            $('.awp-admin-notice-global .awp-admin-notice').remove();
            const notice = $(`<div class="notice notice-${type} is-dismissible awp-admin-notice"><p>${msg}</p></div>`);
            $('.awp-admin-notice-global').first().append(notice);
            if (type === 'success' || type === 'info') { 
                setTimeout(function() {
                    notice.fadeOut(500, function() { $(this).remove(); });
                }, 10000); 
            }
        } else {
            if (awpAjax.debugMode) console.log(`Modal Notice (${type}): ${msg}`);
        }
    }

    function showSpinner(isSilent = false) {
        if (!isSilent) {
            $('#awp-loading-spinner').fadeIn(200);
        }
    }

    function hideSpinner(isSilent = false) {
       if (!isSilent) {
            $('#awp-loading-spinner').fadeOut(200);
        }
    }

    function requestAjax(data, onSuccess, isSilent = false, onError = null) {
        showSpinner(isSilent);
        $.post(awpAjax.ajax_url, data)
            .done(res => {
                hideSpinner(isSilent);
                if (res.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(res);
                    }
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
                    onError({message: errorMsg, raw_response_body_preview: jqXHR.responseText});
                } else if (!isSilent) {
                    displayAdminNotice(errorMsg, 'error');
                }
            });
    }

    function updatePageWithErrorState (msg) {
        awpCriticalError = true;
        $('#awp-table-body').html(`<tr><td colspan="5" style="color:red;text-align:center;">${msg}</td></tr>`);
        $('#awp-open-add-modal, #awp-open-auto-modal, #awp_import_btn, #awp-connect-by-qr-btn, .edit-instance, #awp-over-limit-msg').hide();
        $('#awp-instance-count-wrapper').html(msg);
        $('#awp-instance-limit-wrapper').empty();
        $('.awp-table-container .card-header p').first().hide();
        displayAdminNotice(msg, 'error');
    }

    function fetchLimitDetails(callback) {
        requestAjax({ action: 'awp_background_fetch_limits', nonce: awpAjax.nonce }, res => {
            const data = res.data;
            awpCriticalError = false;

            if (data.not_logged_in_locally) {
                updatePageWithErrorState(data.api_error_message || awpAjax.notLoggedInLocally);
                if (typeof callback === 'function') callback(true);
                return;
            }
             if (data.is_banned) {
                updatePageWithErrorState(data.api_error_message || awpAjax.accountBanned);
                if (typeof callback === 'function') callback(true);
                return;
            }
            if (data.sso_login_required) {
                updatePageWithErrorState(data.api_error_message || awpAjax.ssoLoginRequired);
                if (typeof callback === 'function') callback(true);
                return;
            }
            if (data.site_not_active_on_sso) {
                updatePageWithErrorState(data.api_error_message || awpAjax.siteNotActiveOnSSO);
                if (typeof callback === 'function') callback(true);
                return;
            }
            if (data.api_error_message && data.api_error_message.length > 0) {
                displayAdminNotice(data.api_error_message, 'warning');
            }

            const instanceCount = parseInt(data.instance_count, 10);
            let instanceLimitDisplay = data.limit;
            let isUnlimited = false;

            if (data.limit === 'unlimited' || data.limit === 0 || data.limit === '0') {
                instanceLimitDisplay = awpAjax.unlimited || 'Unlimited';
                isUnlimited = true;
            } else {
                instanceLimitDisplay = parseInt(data.limit, 10) || 0;
            }

            $('#awp-instance-count-wrapper').text(instanceCount);
            $('#awp-instance-limit-wrapper').text(instanceLimitDisplay);
            $('.awp-table-container .card-header p').first().show();

            if (data.is_over && !isUnlimited) {
                awpOverLimit = true;
                $('#awp-over-limit-msg').html(awpAjax.overLimitMessage || 'Instance limit reached.').show();
                $('#awp-open-add-modal, #awp-open-auto-modal, #awp_import_btn, #awp-connect-by-qr-btn').hide();
            } else {
                awpOverLimit = false;
                $('#awp-over-limit-msg').hide();
                $('#awp-open-add-modal, #awp-open-auto-modal, #awp_import_btn, #awp-connect-by-qr-btn').show().prop('disabled', false);            }

            if (typeof callback === 'function') {
                callback(false);
            }
        });
    }

    function fetchInstances() {
        if (awpCriticalError) return;

        requestAjax({ action: 'awp_get_all_instances', nonce: awpAjax.nonce }, res => {
            const tbody = $('#awp-table-body');
            tbody.empty();

            if (!res.data || !res.data.length) {
                tbody.append(`<tr><td colspan="5" style="text-align:center;">${awpAjax.noInstancesFound || 'No instances found.'}</td></tr>`);
                return;
            }

            res.data.forEach(inst => {
                let row = `<tr data-id="${inst.id}">
                    <td>${inst.name || ''}</td>
                    <td>${inst.instance_id || ''}</td>
                    <td>${inst.access_token || ''}</td>
                    <td class="status awp-status-${String(inst.status).toLowerCase()}">
                        <span class="awp-badge">${translateStatus(inst.status)}</span>`;

                if (inst.message && String(inst.status).toLowerCase() === 'offline') {
                    row += `<div class="awp-status-reason">${inst.message}</div>`;
                }

                row += `</td><td>
                    <div class="dropdown">
                        <button class="dropdown-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-ellipsis"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
                            </svg>
                        </button>
                        <div class="dropdown-menu">`;

                if (!awpOverLimit || (awpOverLimit && String(inst.status).toLowerCase() === 'online')) {
                    row += `<button class="edit-instance">
                                <i class="ri-edit-line"></i> ${awpAjax.edit || 'Edit'}
                            </button>`;
                }

                row += `
                            <button class="check-status">
                                <i class="ri-restart-line"></i> ${awpAjax.checkStatus || 'Check Status'}
                            </button>
                            <button class="send-test-message">
                                <i class="ri-send-plane-2-line"></i> ${awpAjax.sendTestMessage || 'Send Test'}
                            </button>
                            <button class="delete-instance">
                                <i class="ri-delete-bin-6-line"></i>${awpAjax.delete || 'Delete'}
                            </button>
                        </div>
                    </div></td></tr>`;
                tbody.append(row);
            });
            awpDetectChangeAndMaybeReload(res.data);
            
        });
    }
    
    $(document).on('click', '#awp-open-auto-modal', e => {
        e.preventDefault();
        if (awpCriticalError || awpOverLimit) return;

        $('#awp-auto-table tbody').html(`<tr><td colspan="4" style="text-align:center">Loading…</td></tr>`);
        $('#awp-auto-modal').fadeIn(200);

        requestAjax({ action: 'awp_get_auto_instances', nonce: awpAjax.nonce }, res => {
            const tbody = $('#awp-auto-table tbody').empty();
            if (!res.data?.length) {
                tbody.append(`<tr><td colspan="4" style="text-align:center">${awpAjax.noDataReturned || 'No data returned.'}</td></tr>`);
                return;
            }
            res.data.forEach(r => {
                tbody.append(`
                    <tr>
                        <td>${r.number}</td>
                        <td>${r.instance_id}</td>
                        <td>${r.access_token}</td>
                        <td><button class="awp-btn primary awp-insert-auto"
                                 data-number="${r.number}"
                                 data-id="${r.instance_id}"
                                 data-token="${r.access_token}">
                                 ${awpAjax.insertLabel || 'Insert'}
                            </button></td>
                    </tr>
                `);
            });
        });
    });

    $(document).on('click', '#awp-close-auto-modal', () => $('#awp-auto-modal').fadeOut(200));

    $(document).on('click', '.awp-insert-auto', function () {
        const btn = $(this);
        const modalContent = btn.closest('.awp-modal-content');
        const loader = modalContent.find('.awp-modal-loader');
        const allButtons = modalContent.find('.awp-insert-auto');

        loader.show();
        allButtons.prop('disabled', true);

        const onComplete = () => {
            loader.hide();
            allButtons.prop('disabled', false);
        };

        requestAjax(
            {
                action: 'awp_add_auto_instance',
                nonce: awpAjax.nonce,
                whatsapp_number: btn.data('number'),
                instance_id: btn.data('id'),
                access_token: btn.data('token'),
            },
            function (res) {
                onComplete(); 
                displayAdminNotice(res.data.message, 'success');
                $('#awp-auto-modal').fadeOut(200);
                fetchLimitDetails(fetchInstances);
            },
            false, 
            function(err) {
                onComplete(); 
            }
        );
    });

    $(document).on('click', '.dropdown-toggle', function (event) {
        event.stopPropagation();
        let dropdownMenu = $(this).next('.dropdown-menu');
        $('.dropdown-menu').not(dropdownMenu).removeClass('show');
        dropdownMenu.toggleClass('show');
    });

    $(document).on('click', function (event) {
        if (!$(event.target).closest('.dropdown').length) {
            $('.dropdown-menu').removeClass('show');
        }
    });

    if (typeof awpAjax !== 'undefined' && awpAjax.nonce) {
        fetchLimitDetails((hasError) => {
            if (!hasError) {
                fetchInstances();
                autoCheckAllInstances();
            }
        });

        setInterval(() => {
            fetchLimitDetails((hasError) => {
                 if (!hasError) { }
            });
        }, 60000); 
    }

    $(document).on('click', '#awp_import_btn', () => {
        if (awpCriticalError) return;
        $('#awp_csv_file').click();
    });

    $(document).on('change', '#awp_csv_file', function() {
        if (awpCriticalError) return;
        const file = this.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('action', 'awp_import_csv_via_ajax');
        fd.append('security', (typeof awpCSVImport !== 'undefined' ? awpCSVImport.nonce : '')); 
        fd.append('csv_file', file);

        showSpinner();
        $.ajax({
            url: (typeof awpCSVImport !== 'undefined' ? awpCSVImport.ajaxUrl : awpAjax.ajax_url),
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: r => {
                hideSpinner();
                if (r.success) {
                    displayAdminNotice(`${awpAjax.importedRows || 'Imported rows'}: ${r.data.imported_count}`, 'success');
                    fetchLimitDetails(fetchInstances);
                } else {
                    displayAdminNotice(`${awpAjax.importFailed || 'Import failed:'} ${r.data.message || r.data}`, 'error');
                }
            },
            error: () => {
                hideSpinner();
                displayAdminNotice(awpAjax.uploadError || 'Upload error.', 'error');
            }
        });
        $(this).val('');
    });

    $('#awp-add-modal, #awp-edit-modal, #awp-qr-modal').on('click', function(e) {
        if ($(e.target).is('.awp-modal')) {
            $(this).fadeOut(200);
            if ($(this).is('#awp-qr-modal')) {
                stopQrPolling(true); 
            }
        }
    });

    $(document).on('click', '#awp-open-add-modal', e => {
        e.preventDefault();
        if (awpCriticalError || awpOverLimit) return;
        $('#awp-name, #awp-instance-id, #awp-access-token').val('');
        $('#awp-add-modal').fadeIn(200);
    });

    $(document).on('click', '#awp-close-add-modal, #awp-close-edit-modal, #awp-close-qr-modal', e => {
        e.preventDefault();
        $(e.target).closest('.awp-modal').fadeOut(200);
        if ($(e.target).is('#awp-close-qr-modal') || $(e.target).closest('#awp-close-qr-modal').length) {
            stopQrPolling(true); 
        }
    });

    $(document).on('click', '#awp-save-add-btn', () => {
        if (awpCriticalError) return;
        const name = $('#awp-name').val().trim();
        const instanceId = $('#awp-instance-id').val().trim();
        const accessToken = $('#awp-access-token').val().trim();
        if (!name || !instanceId || !accessToken) {
            displayAdminNotice(awpAjax.emptyFields || 'All fields are required.', 'error');
            return;
        }
        $('#awp-add-modal').fadeOut(0);
        requestAjax({
            action: 'awp_add_instance',
            nonce: awpAjax.nonce,
            name: name,
            instance_id: instanceId,
            access_token: accessToken
        }, res => {
            displayAdminNotice(res.data.text, 'success');
            fetchLimitDetails(fetchInstances);
        });
    });

    $(document).on('click', '.delete-instance', function() {
        if (awpCriticalError) return;
        const rowId = $(this).closest('tr').data('id');
        if (!confirm(awpAjax.confirmDelete || 'Are you sure you want to delete this instance?')) return;
        requestAjax({
            action: 'awp_delete_instance',
            nonce: awpAjax.nonce,
            id: rowId
        }, res => {
            displayAdminNotice(res.data.message, 'success');
            fetchLimitDetails(fetchInstances);
        });
    });

    $(document).on('click', '.edit-instance', function() {
        if (awpCriticalError) return;
        const row = $(this).closest('tr');
        $('#edit-id').val(row.data('id'));
        $('#edit-name').val(row.find('td:nth-child(1)').text().trim());
        $('#edit-instance-id').val(row.find('td:nth-child(2)').text().trim());
        $('#edit-access-token').val(row.find('td:nth-child(3)').text().trim());
        $('#awp-edit-modal').fadeIn(200);
    });

    $(document).on('click', '#awp-save-edit-btn', () => {
        if (awpCriticalError) return;
        const id = $('#edit-id').val().trim();
        const name = $('#edit-name').val().trim();
        const instanceId = $('#edit-instance-id').val().trim();
        const accessToken = $('#edit-access-token').val().trim();
        if (!name || !instanceId || !accessToken) {
            displayAdminNotice(awpAjax.emptyFields || 'All fields are required.', 'error');
            return;
        }
        $('#awp-edit-modal').fadeOut(0);
        requestAjax({
            action: 'awp_edit_instance',
            nonce: awpAjax.nonce,
            id: id,
            name: name,
            instance_id: instanceId,
            access_token: accessToken
        }, res => {
            displayAdminNotice(res.data.text, 'success');
            fetchLimitDetails(fetchInstances);
        });
    });

    $(document).on('click', '.check-status', function() {
        if (awpCriticalError) return;
        const row = $(this).closest('tr');
        const instanceId = row.find('td:nth-child(2)').text().trim();
        const accessToken = row.find('td:nth-child(3)').text().trim();
        requestAjax({
            action: 'awp_update_status',
            nonce: awpAjax.nonce,
            instance_id: instanceId,
            access_token: accessToken
        }, r => {
            const statusCell = row.find('td.status');
            statusCell.find('.awp-badge')
                .text(translateStatus(r.data.status))
                .parent()
                .removeClass (function (index, className) {
                    return (className.match (/(^|\s)awp-status-\S+/g) || []).join(' ');
                })
                .addClass('awp-status-' + String(r.data.status).toLowerCase());

            statusCell.find('.awp-status-reason').remove();
            if ((String(r.data.status).toLowerCase() === 'offline' || String(r.data.status).toLowerCase() === 'checking') && r.data.message) {
                statusCell.append(`<div class="awp-status-reason">${r.data.message}</div>`);
            }
            displayAdminNotice(`${awpAjax.statusUpdated || 'Status Updated'}: ${translateStatus(r.data.status)}`, 'info');
            fetchLimitDetails(fetchInstances);
        });
    });

    $(document).on('click', '.send-test-message', function() {
        if (awpCriticalError) return;
        const row = $(this).closest('tr');
        const instanceId = row.find('td:nth-child(2)').text().trim();
        const accessToken = row.find('td:nth-child(3)').text().trim();
        requestAjax({
            action: 'awp_send_test_message',
            nonce: awpAjax.nonce,
            instance_id: instanceId,
            access_token: accessToken
        }, r => {
            displayAdminNotice(r.data.message, 'success');
        });
    });

    function stopQrPolling(hardStop = false) {
        if (qrPollingInterval) {
            clearInterval(qrPollingInterval);
            qrPollingInterval = null;
        }
        qrPollingCounter = 0;
        
        if (hardStop) {
            currentQrInstanceId = null;
            currentQrAccessToken = null;
            $('#awp-qr-instance-id-display').html('');
            $('#awp-qr-polling-message').html('');
            if ($('#awp-qr-modal').is(':visible')) {
                 if (!$('#awp-qr-status-message').find('i.awp-icon-success').length && 
                     !$('#awp-qr-status-message').find('i.awp-icon-error').length &&
                     !($('#awp-qr-status-message').text().includes(awpAjax.qrInstanceOnline || 'WhatsApp Connected!')) ) {
                    $('#awp-qr-status-message').html(`<i class="ri-information-line awp-icon-warning"></i> ${awpAjax.qrCancelled || 'QR process cancelled.'}`);
                }
            }
            $('#awp-qr-code-img').hide();
        } else {
            $('#awp-qr-polling-message').html('');
        }
    }
    
    function fetchAndDisplayQrCode(instanceId, accessToken) {
        $('#awp-qr-status-message').html(`<div class="awp-spinner small inline"></div> ${awpAjax.qrFetchingNew || 'Fetching new QR code...'}`);
        $('#awp-qr-instance-id-display').html(`<strong>${awpAjax.instanceIdText || 'Instance ID'}:</strong> <code>${instanceId}</code>`);
        $('#awp-qr-code-img').hide().attr('src','');
        $('#awp-qr-polling-message').html('');

        requestAjax({
            action: 'awp_qr_get_code_action',
            nonce: awpAjax.nonce,
            instance_id: instanceId,
            access_token: accessToken
        }, (res) => { 
            if (res.success && res.data.qr_code_base64) {
                $('#awp-qr-code-img').attr('src', res.data.qr_code_base64).show();
                $('#awp-qr-status-message').html(`<i class="ri-qr-scan-2-line"></i> ${awpAjax.qrScanInstruction || 'Scan this QR code with your WhatsApp app.'}`);
                startQrStatusPolling(instanceId, accessToken, false); 
            } else {
                const errorMsg = res.data && res.data.message ? res.data.message : (awpAjax.qrFetchFailed || 'Failed to fetch QR code.');
                let detailedErrorForModal = `<i class="ri-error-warning-fill awp-icon-error"></i> ${errorMsg}`;
                if (res.data && (res.data.raw_response_body_preview || res.data.raw_response) ) {
                    const rawResponse = res.data.raw_response_body_preview || res.data.raw_response;
                    const sanitizedRawResponse = $('<div>').text(rawResponse).html(); 
                    detailedErrorForModal += `<br/><small class="awp-debug-label">${awpAjax.rawResponseText || 'Raw API Response Preview:'}</small><pre class="awp-raw-response-preview">${sanitizedRawResponse}</pre>`;
                }
                
                const msgLower = String(res.data && res.data.message ? res.data.message : "").toLowerCase();
                const statusFromApiLower = String(res.data && res.data.status_from_api ? res.data.status_from_api : "").toLowerCase();

                if (msgLower.includes("instance id has been used") || msgLower.includes("already authenticated") || (statusFromApiLower === 'error' && msgLower.includes("instance id has been used"))) {
                    $('#awp-qr-status-message').html(`<i class="ri-information-line awp-icon-warning"></i> ${res.data.message}. <br/>${awpAjax.qrAttemptingReconnect || 'Attempting to verify connection...'}`);
                    $('#awp-qr-code-img').hide(); 
                    startQrStatusPolling(instanceId, accessToken, true); 
                } else {
                    $('#awp-qr-status-message').html(detailedErrorForModal);
                    displayAdminNotice(errorMsg, 'error');
                    stopQrPolling(true); 
                }
            }
        }, false, (errData) => { 
            const errorMsg = errData && errData.message ? errData.message : awpAjax.qrFetchFailed;
            let detailedErrorForModal = `<i class="ri-error-warning-fill awp-icon-error"></i> ${errorMsg}`;
             if (errData && (errData.raw_response_body_preview || errData.raw_response) ) {
                 const rawResponse = errData.raw_response_body_preview || errData.raw_response;
                 const sanitizedRawResponse = $('<div>').text(rawResponse).html();
                 detailedErrorForModal += `<br/><small class="awp-debug-label">${awpAjax.rawResponseText || 'Raw API Response Preview:'}</small><pre class="awp-raw-response-preview">${sanitizedRawResponse}</pre>`;
            }
            $('#awp-qr-status-message').html(detailedErrorForModal);
            displayAdminNotice(errorMsg, 'error');
            stopQrPolling(true); 
        });
    }
    
    function startQrStatusPolling(passedInstanceId, passedAccessToken, checkImmediately = false) {
        if (qrPollingInterval) { 
            clearInterval(qrPollingInterval);
        }
        
        if (!checkImmediately) { 
            qrPollingCounter = 0; 
        }
        
        currentQrInstanceId = passedInstanceId || currentQrInstanceId;
        currentQrAccessToken = passedAccessToken || currentQrAccessToken;

        if (!currentQrInstanceId || !currentQrAccessToken) {
            console.error("QR Polling Error: Instance ID or Access Token for polling is missing.");
            $('#awp-qr-polling-message').html(`<i class="ri-error-warning-fill awp-icon-error"></i> ${awpAjax.qrPollingError || 'Error: Critical data missing for polling.'}`);
            stopQrPolling(true);
            return;
        }

        const performStatusCheck = () => {
            if (!currentQrInstanceId) { 
                stopQrPolling(true); 
                return;
            }
            if(!checkImmediately || qrPollingCounter > 0) { 
            }
            if(checkImmediately && qrPollingCounter === 0) {
            } else {
                 qrPollingCounter++;
            }
            if (checkImmediately) {
                 checkImmediately = false; 
            }

            requestAjax({
                action: 'awp_qr_poll_instance_status', 
                nonce: awpAjax.nonce,
                instance_id: currentQrInstanceId, 
                access_token: currentQrAccessToken
            }, (res) => { 
                let apiMsg = awpAjax.qrPollingChecking || 'Checking connection status...'; 
                let anErrorOccurredInApi = false;

                if (res.success && res.data.status_api) { 
                    const statusApiLower = res.data.status_api.toLowerCase();
                    apiMsg = res.data.message_api || (statusApiLower === 'success' ? awpAjax.qrPollingOnlineCheck : awpAjax.qrPollingOfflineCheck);
                    
                    if (statusApiLower === 'success' && res.data.data_api && res.data.data_api.name) {
                        clearInterval(qrPollingInterval); 
                        qrPollingInterval = null; 

                        $('#awp-qr-status-message').html(`<i class="ri-checkbox-circle-fill awp-icon-success"></i> ${awpAjax.qrInstanceOnline || 'WhatsApp Connected!'}`);
                        $('#awp-qr-code-img').hide();
                        $('#awp-qr-polling-message').html(`<div class="awp-spinner small inline"></div> ${awpAjax.qrSavingInstance || 'Saving instance...'}`);
                        displayAdminNotice(awpAjax.qrInstanceOnlineNotice || 'New WhatsApp number connected. Saving...', 'success');
                        
                        const instanceName = res.data.data_api.name || `QR-${currentQrInstanceId.substring(0,8)}`;
                        
                        requestAjax({
                            action: 'awp_qr_save_online_instance_action',
                            nonce: awpAjax.nonce,
                            instance_name: instanceName,
                            instance_id: currentQrInstanceId,
                            access_token: currentQrAccessToken
                        }, addRes => { 
                            if (addRes.success) {
                                $('#awp-qr-status-message').html(`<i class="ri-checkbox-circle-fill awp-icon-success"></i> ${addRes.data.message || awpAjax.qrInstanceSaved}`);
                                displayAdminNotice(`${addRes.data.message || awpAjax.qrInstanceSaved} ${awpAjax.qrPageRefresh || 'Page will refresh.'}`, 'success');
                                setTimeout(() => {
                                    $('#awp-qr-modal').fadeOut(200, () => stopQrPolling(true)); 
                                    location.reload();
                                }, 2500);
                            } else { 
                                const saveErrorMsg = addRes.data && addRes.data.message ? addRes.data.message : awpAjax.qrAddFailed;
                                $('#awp-qr-status-message').html(`<i class="ri-error-warning-fill awp-icon-error"></i> ${saveErrorMsg}`);
                                $('#awp-qr-polling-message').html(''); 
                                displayAdminNotice(`${awpAjax.qrAddFailed || 'Save failed'}: ${saveErrorMsg}`, 'error');
                            }
                        }, false, addErr => { 
                            const saveErrorMsg = addErr && addErr.message ? addErr.message : awpAjax.unexpectedError;
                            $('#awp-qr-status-message').html(`<i class="ri-error-warning-fill awp-icon-error"></i> ${saveErrorMsg}`);
                            $('#awp-qr-polling-message').html('');
                            displayAdminNotice(`${awpAjax.qrAddFailed || 'Save failed'}: ${saveErrorMsg}`, 'error');
                        });
                        return; 
                    } else if (statusApiLower === 'error') {
                        apiMsg = `<i class="ri-error-warning-fill awp-icon-error"></i> API: ${res.data.message_api || 'Unknown API error from /reconnect'}`;
                        if(res.data.raw_response_body_preview && (awpAjax.debugMode || true) ){ 
                             const sanitizedRawResponse = $('<div>').text(res.data.raw_response_body_preview).html();
                             apiMsg += `<br/><small class="awp-debug-label">Raw /reconnect Response:</small><pre class="awp-raw-response-preview">${sanitizedRawResponse}</pre>`;
                        }
                        anErrorOccurredInApi = true;
                    }
                } else if(res.data && res.data.message_api) { 
                    apiMsg = `<i class="ri-error-warning-fill awp-icon-error"></i> ${res.data.message_api}`;
                    anErrorOccurredInApi = true;
                } else if (res.data && res.data.message) { 
                     apiMsg = `<i class="ri-error-warning-fill awp-icon-error"></i> ${res.data.message}`;
                     anErrorOccurredInApi = true;
                } else {
                    apiMsg = `<i class="ri-error-warning-fill awp-icon-error"></i> ${awpAjax.unexpectedResponse || 'Unexpected response from server.'}`;
                    anErrorOccurredInApi = true;
                }

                $('#awp-qr-polling-message').html(`${anErrorOccurredInApi ? '' : '<div class="awp-spinner small inline"></div>'} ${apiMsg} (Attempt: ${qrPollingCounter})`);
                
                if (qrPollingCounter >= QR_POLLING_MAX_ATTEMPTS_BEFORE_REFRESH) {
                    clearInterval(qrPollingInterval); 
                    qrPollingInterval = null;
                    $('#awp-qr-polling-message').append(`<br/><i class="ri-refresh-line awp-icon-warning"></i> ${awpAjax.qrMaxAttempts || 'Max attempts reached. Fetching new QR...'}`);
                    fetchAndDisplayQrCode(currentQrInstanceId, currentQrAccessToken); 
                    return; 
                }
            }, 
            true, 
            (errData) => { 
                let pollingErrorMsg = errData.message || (awpAjax.qrPollingError || 'Error checking instance status.');
                let detailedPollingError = `<i class="ri-error-warning-fill awp-icon-error"></i> ${pollingErrorMsg}`;

                if (errData.raw_response_body_preview) {
                    const sanitizedRawResponse = $('<div>').text(errData.raw_response_body_preview).html();
                    detailedPollingError += `<br/><small class="awp-debug-label">${awpAjax.rawResponseText || 'Raw API Response Preview:'}</small><pre class="awp-raw-response-preview">${sanitizedRawResponse}</pre>`;
                    if (errData.json_decode_error && errData.json_decode_error.toLowerCase() !== "no error") {
                       detailedPollingError += `<small style="display:block; color:red;">JSON Decode Error: ${errData.json_decode_error}</small>`;
                    }
                    if(errData.raw_response_code){
                         detailedPollingError += `<small style="display:block;">Response Code: ${errData.raw_response_code}</small>`;
                    }
                } else if (errData.api_response_data) { 
                    const sanitizedApiResponse = $('<div>').text(JSON.stringify(errData.api_response_data, null, 2)).html();
                    detailedPollingError += `<br/><small class="awp-debug-label">${awpAjax.apiResponseDataText || 'API Response Data:'}</small><pre class="awp-raw-response-preview">${sanitizedApiResponse}</pre>`;
                }
                
                $('#awp-qr-polling-message').html(detailedPollingError);

                 if (qrPollingCounter >= QR_POLLING_MAX_ATTEMPTS_BEFORE_REFRESH) {
                    clearInterval(qrPollingInterval);
                    qrPollingInterval = null;
                    $('#awp-qr-polling-message').append(`<br/><i class="ri-refresh-line awp-icon-warning"></i> ${awpAjax.qrMaxAttempts || 'Max attempts reached. Fetching new QR...'}`);
                    fetchAndDisplayQrCode(currentQrInstanceId, currentQrAccessToken);
                    return;
                }
            });
        }; 

        if (checkImmediately) {
            $('#awp-qr-polling-message').html(`<div class="awp-spinner small inline"></div> ${awpAjax.qrAttemptingReconnect || 'Verifying connection...'}`);
            performStatusCheck(); 
        } else {
            $('#awp-qr-polling-message').html(`<div class="awp-spinner small inline"></div> ${awpAjax.qrPollingStart || 'Waiting for QR scan... Checking status periodically.'}`);
        }

        if (!qrPollingInterval) { 
            qrPollingInterval = setInterval(performStatusCheck, QR_POLLING_INTERVAL_MS);
        }
    } 
    $(document).on('click', '#awp-connect-by-qr-btn', function(e) {
        e.preventDefault();
        if (awpCriticalError || awpOverLimit) {
            displayAdminNotice(awpAjax.qrNotAllowed || 'Cannot connect by QR due to limit or error.', 'warning');
            return;
        }
        
        const siteAccessToken = awpAjax.apiAccessToken || $('#wawp-api-access-token-display').text().trim();
        if (!siteAccessToken || siteAccessToken === "false" || siteAccessToken.length < 5) { 
             displayAdminNotice(awpAjax.noApiAccessToken || 'API Access Token is missing or invalid.', 'error');
            return;
        }

        $('#awp-qr-status-message').html(`<div class="awp-spinner small inline"></div> ${awpAjax.qrCreatingInstance || 'Creating instance, please wait...'}`);
        $('#awp-qr-instance-id-display').html('');
        $('#awp-qr-code-img').hide().attr('src', '');
        $('#awp-qr-polling-message').text('');
        $('#awp-qr-modal').fadeIn(200);

        requestAjax({
            action: 'awp_qr_create_new_instance_action',
            nonce: awpAjax.nonce
        }, (res) => {
            if (res.success && res.data.instance_id && res.data.access_token) {
                currentQrInstanceId = res.data.instance_id;
                currentQrAccessToken = res.data.access_token; 
                $('#awp-qr-instance-id-display').html(`<strong>${awpAjax.instanceIdText || 'Instance ID'}:</strong> <code>${currentQrInstanceId}</code>`);
                fetchAndDisplayQrCode(currentQrInstanceId, currentQrAccessToken);
            } else {
                const errorMsg = res.data && res.data.message ? res.data.message : awpAjax.qrCreateFailed;
                let detailedErrorForModal = `<i class="ri-error-warning-fill awp-icon-error"></i> ${errorMsg}`;
                 if (res.data && (res.data.raw_response_body_preview || res.data.raw_response) ) {
                    const rawResponse = res.data.raw_response_body_preview || res.data.raw_response;
                    const sanitizedRawResponse = $('<div>').text(rawResponse).html();
                    detailedErrorForModal += `<br/><small class="awp-debug-label">${awpAjax.rawResponseText || 'Raw API Response Preview:'}</small><pre class="awp-raw-response-preview">${sanitizedRawResponse}</pre>`;
                     if (res.data.json_decode_error) {
                         detailedErrorForModal += `<small style="display:block; color:red;">JSON Decode Error: ${res.data.json_decode_error}</small>`;
                    }
                }
                $('#awp-qr-status-message').html(detailedErrorForModal);
                displayAdminNotice(errorMsg, 'error');
            }
        }, false, (errData) => {
             const errorMsg = errData && errData.message ? errData.message : awpAjax.qrCreateFailed;
             let detailedErrorForModal = `<i class="ri-error-warning-fill awp-icon-error"></i> ${errorMsg}`;
             if (errData && (errData.raw_response_body_preview || errData.raw_response) ) {
                 const rawResponse = errData.raw_response_body_preview || errData.raw_response;
                 const sanitizedRawResponse = $('<div>').text(rawResponse).html();
                 detailedErrorForModal += `<br/><small class="awp-debug-label">${awpAjax.rawResponseText || 'Raw API Response Preview:'}</small><pre class="awp-raw-response-preview">${sanitizedRawResponse}</pre>`;
                  if (errData.json_decode_error) {
                     detailedErrorForModal += `<small style="display:block; color:red;">JSON Decode Error: ${errData.json_decode_error}</small>`;
                }
             }
            $('#awp-qr-status-message').html(detailedErrorForModal);
            $('#awp-qr-instance-id-display').html('');
            displayAdminNotice(errorMsg, 'error');
        });
    });
    
    jQuery(function ($) {
        if ($.fn.select2 && $('#wawp_notif_selected_instance').length) {
            $('#wawp_notif_selected_instance').select2({
                placeholder: 'Select instance(s)…',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#wawp_notif_selected_instance').closest('.instance-select')
            });
        }
    });

    function awpToggleSmtpFields() {
        const on  = jQuery('#awp-smtp-enabled').is(':checked');
        const aut = jQuery('#awp-smtp-auth').is(':checked');
        jQuery('#awp-smtp-settings-fields').toggle(on);
        jQuery('.awp-smtp-auth-fields').toggle(aut && on);
    }
    jQuery(document).on('change', '#awp-smtp-enabled, #awp-smtp-auth', awpToggleSmtpFields);
    jQuery(awpToggleSmtpFields);       

    $(document).on('click', '#awp-smtp-test-btn', function (e) {
        e.preventDefault();
        const $btn  = $(this).prop('disabled', true);
        const $out  = $('#awp-smtp-test-status').text('…');
        const dest  = $('#awp-smtp-test-to').val().trim();
        if (!dest) { alert('Enter an email.'); $btn.prop('disabled', false); return; }
        $.post(awpAjax.ajax_url, {
            action : 'awp_smtp_send_test_email',
            nonce  : awpAjax.nonce,
            to     : dest
        }).done(r => {
            $out.text(r.success ? r.data : r.data.message);
        }).fail(() => {
            $out.text('AJAX error.');
        }).always(() => $btn.prop('disabled', false));
    });

    $(document).on('click', '#awp-smtp-test-conn', function (e) {
        e.preventDefault();
        const $btn = $(this).prop('disabled', true);
        const $out = $('#awp-smtp-test-status').text('…');

        const grab = id => jQuery(id).val();
        const data = {
            host       : grab('[name="awp_smtp[host]"]'),
            port       : grab('[name="awp_smtp[port]"]'),
            encryption : grab('[name="awp_smtp[encryption]"]'),
            auth       : jQuery('#awp-smtp-auth').is(':checked') ? 1 : 0,
            user       : grab('[name="awp_smtp[user]"]'),
            pass       : grab('[name="awp_smtp[pass]"]')
        };

        jQuery.post(awpAjax.ajax_url, {
            action : 'awp_smtp_test_connection',
            nonce  : awpAjax.nonce,
            smtp   : data
        }).done(r => {
            $out.text(r.success ? r.data : r.data);
        }).fail(() => {
            $out.text('AJAX error.');
        }).always(() => $btn.prop('disabled', false));
    });
    
    $(document).on('click', '#awp-qr-recheck-status-btn', function() {
        if (currentQrInstanceId && currentQrAccessToken) {
            $('#awp-qr-status-message').html(`<div class="awp-spinner small inline"></div> ${awpAjax.qrManuallyCheckingStatus || 'Checking status...'}`);
            $('#awp-qr-polling-message').html(''); 
            startQrStatusPolling(currentQrInstanceId, currentQrAccessToken, true); 
        } else {
            displayAdminNotice(awpAjax.qrNoInstanceToCheck || 'No active instance information to check.', 'error');
        }
    });
});
