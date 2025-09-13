jQuery(document).ready(function($) {
    const formStateKey = 'wawpCampaignNewFormData';
    const formStateVersion = '1.1'; // Increment if structure changes significantly

    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    $(document).on('input', '#campaign_name', function() {
        let campaignName = $(this).val().trim();
        let baseTitle = "Add New Campaign";
        if ($('#add_new_campaign_heading').length) { // Only on new campaign page
            if (campaignName) {
                $('#add_new_campaign_heading').html('<i class="bi bi-plus-square"></i> ' + baseTitle + ' - <span class="campaign-name-title">' + esc_html(campaignName) + '</span>');
            } else {
                $('#add_new_campaign_heading').html('<i class="bi bi-plus-square"></i> ' + baseTitle);
            }
        }
        saveFormState(); 
    });

    var $msgInput = $('#message_input');
    if ($msgInput.length > 0 && typeof $.fn.emojioneArea !== 'undefined') {
        $msgInput.emojioneArea({
            pickerPosition: 'right', tonesStyle: 'bullet', searchPlaceholder: 'Search emoji...',
            events: {
                change: function() { $('#message_input').val(this.getText()); saveFormState(); updateRecipientCount(); },
                keyup: function() { $('#message_input').val(this.getText()); saveFormState(); debouncedUpdateRecipientCount(); }
            }
        });
    }
    
    if (typeof tinymce !== 'undefined') {
        tinymce.on('AddEditor', function(event) {
            if (event.editor.id === 'email_message_editor') {
                event.editor.on('change keyup NodeChange ExecCommand', function(e) { 
                    $('#email_message_editor').val(event.editor.getContent());
                    saveFormState();
                    debouncedUpdateRecipientCount();
                });
            }
        });
    }

    var mediaFrame;
    $(document).on('click', '#upload_media_button', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') { alert('WordPress media library not available.'); return; }
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({ title: 'Select or Upload Media', button: { text: 'Use this media' }, multiple: false });
        mediaFrame.on('select', function() {
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#media_url_input').val(attachment.url).trigger('change'); 
            $('#media_preview_container').html('<img src="'+esc_url(attachment.url)+'" class="cex_media_preview_img" alt="Media Preview" />');
            $('#media_file_upload_input').val(''); 
        });
        mediaFrame.open();
    });

    $(document).on('change', '#media_file_upload_input', function(){
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) { $('#media_preview_container').html('<img src="' + e.target.result + '" class="cex_media_preview_img" alt="New File Preview" /><span>New file selected. This will be used if no Media URL is provided.</span>'); }
            reader.readAsDataURL(this.files[0]);
            $('#media_url_input').val('').trigger('change'); 
        }
    });

    $(document).on('change input', '#media_url_input', function() {
        var url = $(this).val();
        if (url) { 
            if ($('#media_file_upload_input').val()) { $('#media_file_upload_input').val(''); }
            if (url.match(/\.(jpeg|jpg|gif|png|webp)$/i) != null) { $('#media_preview_container').html('<img src="'+esc_url(url)+'" class="cex_media_preview_img" alt="Media URL Preview"/>');
            } else if (url.match(/\.(mp4|mov|avi|pdf|doc|docx|xls|xlsx|ppt|pptx)$/i) != null) { $('#media_preview_container').html('<a href="'+esc_url(url)+'" target="_blank" class="cex_media_preview_link"><i class="bi bi-file-earmark-check"></i> View Linked Media</a>');
            } else if (url) { $('#media_preview_container').html('<span class="cex_media_preview_link"><i class="bi bi-link-45deg"></i> Media URL specified</span>');}
        } else if (!$('#media_file_upload_input').val()) { 
             $('#media_preview_container').html('');
        }
        updateUIDisplay(); 
        saveFormState();
    });


    function initSelect2(selector) {
        var $element = $(selector);
        if (!$element.length || typeof $.fn.select2 === 'undefined') return;
        if ($element.hasClass("select2-hidden-accessible")) { 
            try { $element.select2('destroy'); } catch(e) { console.warn("Error destroying select2:", e); } 
        }
        var select2Options = { 
            width: '100%', 
            placeholder: $element.data('placeholder') || 'Select options', 
            allowClear: !$element.prop('multiple') 
        };
        if ($element.find('option').length <= 10 && !$element.prop('multiple')) { 
            select2Options.minimumResultsForSearch = Infinity; 
        }
        $element.select2(select2Options).on('change.select2', function() { 
            clearFieldError($(this).attr('id')); 
            saveFormState();
            updateRecipientCount(); 
        });
    }
    $('.cex_select2_field').each(function() { initSelect2(this); });
    
    $(document).on('click', '.cex_select_all_btn', function(e) { 
        e.preventDefault(); var targetSelector = $(this).data('target');
        if ($(targetSelector).prop('multiple')) { $(targetSelector).find('option').prop('selected', true).trigger('change.select2');}
    });
    $(document).on('click', '.cex_deselect_all_btn', function(e) { 
        e.preventDefault(); var targetSelector = $(this).data('target');
         if ($(targetSelector).prop('multiple')) { $(targetSelector).val(null).trigger('change.select2');}
    });

    function loadPreview(selectElement, previewBoxSelector) { 
        var id = $(selectElement).val(); var $previewBox = $(previewBoxSelector);
        if (!id) { $previewBox.html(''); return; }
        $previewBox.html('Loading preview...');
        $.post(campExtData.ajaxUrl, { action: 'camp_ext_preview', _camp_preview_nonce: campExtData.noncePreview, post_id: id }, 
            function(r) { $previewBox.html(r); }
        ).fail(function() { $previewBox.html('<span class="text-danger">Error loading preview.</span>'); });
    }
    $(document).on('change', '#post_id_select', function() { loadPreview(this, '#post_preview_area'); saveFormState(); updateRecipientCount(); });
    $(document).on('change', '#product_id_select', function() { loadPreview(this, '#product_preview_area'); saveFormState(); updateRecipientCount(); });

    $(document).on('change', '#repeat_type', function() { 
        if ($(this).val() === 'custom') { $('#repeat_days_row').slideDown(); } else { $('#repeat_days_row').slideUp(); }
        saveFormState();
        updateRecipientCount();
    });

    function updateUIDisplay() {
        var isWhatsAppEnabled = $('#send_whatsapp_switch').is(':checked');
        var isEmailEnabled = $('#send_email_switch').is(':checked');
        var messageType = $('#send_type').val();

        // Step 1 conditional fields
        $('.whatsapp-specific-field-step1').toggle(isWhatsAppEnabled); // General class for rows
        
        $('.email-specific-field-step1').toggle(isEmailEnabled);

        // Recipient count display
        $('#whatsapp_recipients_info').toggle(isWhatsAppEnabled);
        $('#email_recipients_info').toggle(isEmailEnabled);

        // Step 4 conditional fields (Message Content)
        $('#whatsapp_content_header, #whatsapp_text_group').toggle(isWhatsAppEnabled);
        $('#whatsapp_media_group').toggle(isWhatsAppEnabled && messageType === 'media');
        $('#email_content_header, #email_subject_group, #email_body_group').toggle(isEmailEnabled);
        
        // Step 5 conditional fields (Scheduling)
        $('#whatsapp_schedule_fields_header').toggle(isWhatsAppEnabled); 
        $('#whatsapp_min_interval_row, #whatsapp_max_interval_row, #whatsapp_daily_limit_row').toggle(isWhatsAppEnabled);
        $('#email_schedule_fields_header').toggle(isEmailEnabled); 
        $('#email_min_interval_row, #email_max_interval_row, #email_daily_limit_row').toggle(isEmailEnabled);

        $('.shared-content-group#field-group-post').toggle(messageType === 'post');
        $('.shared-content-group#field-group-product').toggle(messageType === 'product');
        
        var isAppendPost = $('input[name="append_post"]').is(':checked');
        var isAppendProduct = $('input[name="append_product"]').is(':checked');
        var waTextRequired = false;
        if (isWhatsAppEnabled) {
            if (messageType === 'text' || messageType === 'media' || (messageType === 'post' && isAppendPost) || (messageType === 'product' && isAppendProduct)) {
                waTextRequired = true;
            }
        }
        $('#whatsapp_text_required_star').toggle(waTextRequired);
        $('#whatsapp_media_required_star').toggle(isWhatsAppEnabled && messageType === 'media');
        $('#email_subject_required_star').toggle(isEmailEnabled);
        $('#email_body_required_star').toggle(isEmailEnabled);
        $('#post_id_required_star').toggle(messageType === 'post' && (isWhatsAppEnabled || isEmailEnabled));
        $('#product_id_required_star').toggle(messageType === 'product' && (isWhatsAppEnabled || isEmailEnabled));

        if(isWhatsAppEnabled){
            if ((messageType === 'post' && !isAppendPost) || (messageType === 'product' && !isAppendProduct)) {
                $('#whatsapp_text_description').text('WhatsApp text will be REPLACED by the selected Post/Product content (as "Append" is not checked).');
            } else {
                $('#whatsapp_text_description').text('This text will be used. If Post/Product is selected and "Append" is checked, their content will be added after this text.');
            }
        }
    }

    $(document).on('change', '#send_whatsapp_switch, #send_email_switch', function() {
        updateUIDisplay();
        updateRecipientCount(); // Recalculate when channels change as it affects overall estimates.
        saveFormState();
    });
     $(document).on('change', '#send_type, input[name="append_post"], input[name="append_product"]', function() {
        updateUIDisplay(); // Only UI display, recipient count not directly affected by these.
        saveFormState();
    });


    $(document).on('click', '#btn-now', function(e) { 
        e.preventDefault(); let d = new Date(); let timezoneOffset = d.getTimezoneOffset() * 60000;
        let localISOTime = (new Date(d.getTime() - timezoneOffset)).toISOString().slice(0,16);
        $('#start_datetime').val(localISOTime).trigger('change');
    });
    
    var recipientAjaxRequestWA = null;
    var recipientAjaxRequestEM = null;
    var isFirstRecipientUpdateCall = true; 
    var debouncedUpdateRecipientCount = debounce(updateRecipientCountActual, 750);

    function countValidLines(textValue) {
        if (!textValue || typeof textValue !== 'string') return 0;
        return textValue.split('\n').filter(line => line.trim() !== '').length;
    }
    
    // Shared state for two-part estimate
    var lastWPCountWA = null;
    var lastWPCountEM = null;
    var lastExtWA     = 0;
    var lastExtEM     = 0;

    function estimateFinishPart(channel, wpCount, extCount) {
        // channel === 'WA' or 'EM' or error‐cases
        if (channel === 'WA') {
            lastWPCountWA = wpCount;
            lastExtWA     = extCount;
        }
        if (channel === 'EM') {
            lastWPCountEM = wpCount;
            lastExtEM     = extCount;
        }

        // Once both AJAX calls have succeeded (or errored), recalc the full summary box
        if ((lastWPCountWA !== null || channel === 'WA_error')
         && (lastWPCountEM !== null || channel === 'EM_error')) {
            var isWhatsAppEnabled = $('#send_whatsapp_switch').is(':checked');
            var isEmailEnabled    = $('#send_email_switch').is(':checked');
            var totalWA = isWhatsAppEnabled && lastWPCountWA >= 0
                            ? (lastWPCountWA + lastExtWA)
                            : 0;
            var totalEM = isEmailEnabled    && lastWPCountEM >= 0
                            ? (lastWPCountEM + lastExtEM)
                            : 0;

            var minWaI = parseInt($('#min_whatsapp_interval').val() || '60', 10);
            var maxWaI = parseInt($('#max_whatsapp_interval').val() || '75', 10);
            if (minWaI < 1) minWaI = 1;
            if (maxWaI < minWaI) maxWaI = minWaI + 15;
            var maxWaDay = parseInt($('#max_wa_per_day').val() || '0', 10);

            var minEmI = parseInt($('#min_email_interval').val() || '30', 10);
            var maxEmI = parseInt($('#max_email_interval').val() || '60', 10);
            if (minEmI < 1) minEmI = 1;
            if (maxEmI < minEmI) maxEmI = minEmI + 15;
            var maxEmDay = parseInt($('#max_email_per_day').val() || '0', 10);

            var estText = '';
            var now = new Date();
            var startVal = $('#start_datetime').val();
            var startDate = startVal ? new Date(startVal) : now;
            if (isWhatsAppEnabled && totalWA > 0) {
                var waFinish;
                if (maxWaDay > 0) {
                    var daysWA = Math.ceil(totalWA / maxWaDay);
                    waFinish = new Date(startDate.getTime());
                    waFinish.setDate(waFinish.getDate() + daysWA - 1);
                    estText +=
                      '<p><i class="bi bi-whatsapp"></i> WhatsApp: max ' +
                      maxWaDay +
                      ' msgs/day ⇒ ' +
                      daysWA +
                      ' day(s). Finish: ' +
                      waFinish.toLocaleDateString() +
                      '</p>';
                } else {
                    var avgSecWA = (minWaI + maxWaI) / 2;
                    var totalSecWA = totalWA * avgSecWA;
                    waFinish = new Date(startDate.getTime() + totalSecWA * 1000);
                    estText +=
                      '<p><i class="bi bi-whatsapp"></i> WhatsApp: no daily limit, avg ' +
                      Math.round(avgSecWA) +
                      ' s ⇒ duration ' +
                      formatDuration(totalSecWA) +
                      ', finish ' +
                      waFinish.toLocaleString() +
                      '</p>';
                }
            } else {
                estText += '<p><i class="bi bi-whatsapp"></i> WhatsApp: ' + (isWhatsAppEnabled ? '0 recipients' : 'disabled') + '</p>';
            }

            if (isEmailEnabled && totalEM > 0) {
                var emFinish;
                if (maxEmDay > 0) {
                    var daysEM = Math.ceil(totalEM / maxEmDay);
                    emFinish = new Date(startDate.getTime());
                    emFinish.setDate(emFinish.getDate() + daysEM - 1);
                    estText +=
                      '<p><i class="bi bi-envelope"></i> Email: max ' +
                      maxEmDay +
                      ' msgs/day ⇒ ' +
                      daysEM +
                      ' day(s). Finish: ' +
                      emFinish.toLocaleDateString() +
                      '</p>';
                } else {
                    var avgSecEM = (minEmI + maxEmI) / 2;
                    var totalSecEM = totalEM * avgSecEM;
                    emFinish = new Date(startDate.getTime() + totalSecEM * 1000);
                    estText +=
                      '<p><i class="bi bi-envelope"></i> Email: no daily limit, avg ' +
                      Math.round(avgSecEM) +
                      ' s ⇒ duration ' +
                      formatDuration(totalSecEM) +
                      ', finish ' +
                      emFinish.toLocaleString() +
                      '</p>';
                }
            } else {
                estText += '<p><i class="bi bi-envelope"></i> Email: ' + (isEmailEnabled ? '0 recipients' : 'disabled') + '</p>';
            }

            $('#estimate_finish').html(estText);
            $('#cex_extra_info').html(buildSummaryCard(totalWA, totalEM, maxWaDay, maxEmDay, startDate));

            lastWPCountWA = null;
            lastWPCountEM = null;
        }
    }
    
    function buildSummaryCard(total_whatsapp_recipients, total_email_recipients, max_wa_daily_limit_param, max_email_daily_limit_param, startDateTime_param) {
    let summaryHTML = '<h4><i class="bi bi-clipboard-check-fill"></i> Campaign Configuration Review</h4>';

    // Fetch current values from the form for display
    const isWhatsAppActive = $('#send_whatsapp_switch').is(':checked');
    const isEmailActive = $('#send_email_switch').is(':checked');

    const minWaI = parseInt($('#min_whatsapp_interval').val() || '60', 10);
    const maxWaI = parseInt($('#max_whatsapp_interval').val() || '75', 10);
    // Ensure min/max are valid, adjust if necessary (though validation should handle this before submission)
    const validMinWaI = (minWaI > 0) ? minWaI : 1;
    const validMaxWaI = (maxWaI >= validMinWaI) ? maxWaI : validMinWaI + 15;

    const minEmailI = parseInt($('#min_email_interval').val() || '30', 10);
    const maxEmailI = parseInt($('#max_email_interval').val() || '60', 10);
    const validMinEmailI = (minEmailI > 0) ? minEmailI : 1;
    const validMaxEmailI = (maxEmailI >= validMinEmailI) ? maxEmailI : validMinEmailI + 15;
    
    const external_wa_count = countValidLines($('#external_numbers').val());
    const external_email_count = countValidLines($('#external_emails').val());

    // Stat Cards
    summaryHTML += '<div class="cex_stat_cards_container">';
    summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">WA Recipients</div><div class="cex_stat_card_number">' + (isWhatsAppActive ? total_whatsapp_recipients : 'N/A') + '</div></div>';
    summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">Email Recipients</div><div class="cex_stat_card_number">' + (isEmailActive ? total_email_recipients : 'N/A') + '</div></div>';
    summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">WA Daily Limit</div><div class="cex_stat_card_number">' + (isWhatsAppActive ? (max_wa_daily_limit_param > 0 ? max_wa_daily_limit_param : 'None') : 'N/A') + '</div></div>';
    summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">Email Daily Limit</div><div class="cex_stat_card_number">' + (isEmailActive ? (max_email_daily_limit_param > 0 ? max_email_daily_limit_param : 'None') : 'N/A') + '</div></div>';

    let waDurationTextEst = "N/A";
    if (isWhatsAppActive && total_whatsapp_recipients > 0) {
        if (max_wa_daily_limit_param === 0) {
            let avgSecWaEst = (validMinWaI + validMaxWaI) / 2;
            waDurationTextEst = formatDuration(total_whatsapp_recipients * avgSecWaEst);
        } else {
            let daysNeededWaEst = Math.ceil(total_whatsapp_recipients / max_wa_daily_limit_param);
            waDurationTextEst = daysNeededWaEst + (daysNeededWaEst === 1 ? " day" : " days");
        }
    }
    summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">WA Approx. Duration</div><div class="cex_stat_card_number">' + waDurationTextEst + '</div></div>';

    let emailDurationTextEst = "N/A";
    if (isEmailActive && total_email_recipients > 0) {
        if (max_email_daily_limit_param === 0) {
            let avgSecEmailEst = (validMinEmailI + validMaxEmailI) / 2;
            emailDurationTextEst = formatDuration(total_email_recipients * avgSecEmailEst);
        } else {
            let daysNeededEmailEst = Math.ceil(total_email_recipients / max_email_daily_limit_param);
            emailDurationTextEst = daysNeededEmailEst + (daysNeededEmailEst === 1 ? " day" : " days");
        }
    }
    summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">Email Approx. Duration</div><div class="cex_stat_card_number">' + emailDurationTextEst + '</div></div>';
    summaryHTML += '</div>'; // End of cex_stat_cards_container

    // Basic Info List
    summaryHTML += '<ul class="cex_summary_details_list">';
    summaryHTML += '<li><i class="bi bi-card-text"></i><strong>Name:</strong> <span class="value">' + esc_html($('input[name="name"]').val() || '(Not set)') + '</span></li>';
    let channelsText = [];
    if (isWhatsAppActive) channelsText.push('WhatsApp');
    if (isEmailActive) channelsText.push('Email');
    summaryHTML += '<li><i class="bi bi-broadcast-pin"></i><strong>Channels:</strong> <span class="value">' + (channelsText.length > 0 ? channelsText.join(', ') : '<span class="text-danger">None Selected</span>') + '</span></li>';
    summaryHTML += '</ul>';

    // Audience Definition
    summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-people-fill"></i> Audience Definition</h5><ul class="cex_summary_details_list">';
    let selectedRolesText = ($('#roles_input').val() || []).map(r => $('#roles_input option[value="' + r + '"]').text()).join(', ');
    if (selectedRolesText) summaryHTML += '<li><i class="bi bi-person-badge"></i><strong>Target Roles:</strong> <span class="value">' + esc_html(selectedRolesText) + '</span></li>';
    let selectedUsersText = ($('#users_input').val() || []).map(u => $('#users_input option[value="' + u + '"]').text().split(' (ID:')[0]).join(', ');
    if (selectedUsersText) summaryHTML += '<li><i class="bi bi-person-check"></i><strong>Target Users:</strong> <span class="value">' + esc_html(selectedUsersText) + '</span></li>';
    if ($('#external_numbers').val().trim() && isWhatsAppActive) summaryHTML += '<li><i class="bi bi-telephone-plus"></i><strong>External Numbers (WA):</strong> <span class="value">Provided (' + external_wa_count + ')</span></li>';
    if ($('#external_emails').val().trim() && isEmailActive) summaryHTML += '<li><i class="bi bi-envelope-plus"></i><strong>External Emails:</strong> <span class="value">Provided (' + external_email_count + ')</span></li>';
    if ($('input[name="only_verified_phone"]:checked').length && isWhatsAppActive) summaryHTML += '<li><i class="bi bi-patch-check-fill"></i><strong>Only Verified Phones (WA):</strong> <span class="value">Yes</span></li>';
    summaryHTML += '</ul></div>';

    // Additional Filters
    summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-funnel"></i> Additional Filters</h5><ul class="cex_summary_details_list">';
    let selBillingCountries = ($('#billing_countries_input').val() || []).filter(c => c !== "");
    summaryHTML += '<li><i class="bi bi-globe2"></i><strong>Billing Countries:</strong> <span class="value">' + (selBillingCountries.length > 0 ? esc_html(selBillingCountries.map(c => $('#billing_countries_input option[value="' + c + '"]').text()).join(', ')) : 'All') + '</span></li>';
    let selProfileLangs = ($('#wp_profile_languages_input').val() || []).filter(l => l !== "");
    summaryHTML += '<li><i class="bi bi-translate"></i><strong>Profile Languages:</strong> <span class="value">' + (selProfileLangs.length > 0 ? esc_html(selProfileLangs.map(l => $('#wp_profile_languages_input option[value="' + l + '"]').text().split(' (')[0]).join(', ')) : 'All') + '</span></li>';
    summaryHTML += '</ul></div>';

    // WooCommerce Filters
    if (typeof campExtData !== 'undefined' && campExtData.isWooActive) {
        let wooSummaryContent = '';
        let wooSpentOver = parseFloat($('input[name="woo_spent_over"]').val());
        if (!isNaN(wooSpentOver) && wooSpentOver > 0) wooSummaryContent += '<li><i class="bi bi-cash-coin"></i><strong>Min. Spent:</strong> <span class="value">' + esc_html($('input[name="woo_spent_over"]').val()) + '</span></li>';
        let wooOrdersOver = parseInt($('input[name="woo_orders_over"]').val());
        if (!isNaN(wooOrdersOver) && wooOrdersOver > 0) wooSummaryContent += '<li><i class="bi bi-cart3"></i><strong>Min. Orders:</strong> <span class="value">' + esc_html($('input[name="woo_orders_over"]').val()) + '</span></li>';
        let selWooProdsText = ($('#products_input').val() || []).map(p => $('#products_input option[value="' + p + '"]').text().split(' (ID:')[0]).join(', ');
        if (selWooProdsText) wooSummaryContent += '<li><i class="bi bi-box-seam"></i><strong>Purchased Products:</strong> <span class="value">' + esc_html(selWooProdsText) + '</span></li>';
        let selWooStatusesText = ($('#statuses_input').val() || []).map(s => $('#statuses_input option[value="' + s + '"]').text()).join(', ');
        if (selWooStatusesText) wooSummaryContent += '<li><i class="bi bi-card-checklist"></i><strong>Order Statuses (Product Filter):</strong> <span class="value">' + esc_html(selWooStatusesText) + '</span></li>';
        if (wooSummaryContent) summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-cart-fill"></i> WooCommerce Filters</h5><ul class="cex_summary_details_list">' + wooSummaryContent + '</ul></div>';
    }

    // Scheduling
    summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-alarm-fill"></i> Scheduling</h5><ul class="cex_summary_details_list">';
    if (isWhatsAppActive) summaryHTML += '<li><i class="bi bi-whatsapp"></i><strong>WA Interval:</strong> <span class="value">' + validMinWaI + 's - ' + validMaxWaI + 's</span></li>';
    if (isEmailActive) summaryHTML += '<li><i class="bi bi-envelope"></i><strong>Email Interval:</strong> <span class="value">' + validMinEmailI + 's - ' + validMaxEmailI + 's</span></li>';
    const startVal = $('#start_datetime').val();
    summaryHTML += '<li><i class="bi bi-play-btn-fill"></i><strong>Start:</strong> <span class="value">' + (startVal ? new Date(startVal).toLocaleString() : 'ASAP') + '</span></li>';
    summaryHTML += '<li><i class="bi bi-arrow-repeat"></i><strong>Repeat:</strong> <span class="value">' + $('#repeat_type option:selected').text();
    if ($('#repeat_type').val() === 'custom') summaryHTML += ' (' + ($('input[name="repeat_days"]').val() || '0') + ' days)';
    summaryHTML += '</span></li></ul></div>';

    // Message Details
    summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-file-text-fill"></i> Message Details</h5><ul class="cex_summary_details_list">';
    summaryHTML += '<li><i class="bi bi-chat-square-dots"></i><strong>Message Type Setting:</strong> <span class="value">' + $('#send_type option:selected').text() + '</span></li>';

    if (isWhatsAppActive) {
        const msgPreview = $('#message_input').val();
        if (msgPreview) summaryHTML += '<li><i class="bi bi-fonts"></i><strong>WhatsApp Text:</strong> <span class="value">' + esc_html(msgPreview.substring(0, 70) + (msgPreview.length > 70 ? '...' : '')) + '</span></li>';
        if ($('#send_type').val() === 'media') {
            const mediaUrl = $('#media_url_input').val();
            const mediaFile = $('#media_file_upload_input').val(); // Check if a file is selected
            if (mediaUrl) summaryHTML += '<li><i class="bi bi-paperclip"></i><strong>WA Media URL:</strong> <span class="value">' + esc_html(mediaUrl.substring(0, 50) + (mediaUrl.length > 50 ? '...' : '')) + '</span></li>';
            if (mediaFile) summaryHTML += '<li><i class="bi bi-upload"></i><strong>New WA Media File:</strong> <span class="value">Yes (will be uploaded)</span></li>';
        }
    }
    if (isEmailActive) {
        const emailSub = $('#email_subject_input').val();
        if (emailSub) summaryHTML += '<li><i class="bi bi-envelope-paper"></i><strong>Email Subject:</strong> <span class="value">' + esc_html(emailSub.substring(0, 70) + (emailSub.length > 70 ? '...' : '')) + '</span></li>';
        var emailBodyPreview = (typeof tinymce !== 'undefined' && tinymce.get('email_message_editor')) ? tinymce.get('email_message_editor').getContent({ format: 'text' }) : $('#email_message_editor').val();
        if (emailBodyPreview) summaryHTML += '<li><i class="bi bi-file-earmark-richtext"></i><strong>Email Body:</strong> <span class="value">' + esc_html(emailBodyPreview.substring(0, 70) + (emailBodyPreview.length > 70 ? '...' : '')) + '</span></li>';
    }

    const postId = $('#post_id_select').val();
    const sendTypeVal = $('#send_type').val();
    if (postId && (sendTypeVal === 'post')) summaryHTML += '<li><i class="bi bi-file-post"></i><strong>Shared Post/Page:</strong> <span class="value">' + esc_html($('#post_id_select option:selected').text()) + ($('input[name="append_post"]').is(':checked') ? ' (Append)' : ' (Replace)') + '</span></li>';
    const prodId = $('#product_id_select').val();
    if (prodId && (sendTypeVal === 'product')) summaryHTML += '<li><i class="bi bi-archive"></i><strong>Shared Product:</strong> <span class="value">' + esc_html($('#product_id_select option:selected').text()) + ($('input[name="append_product"]').is(':checked') ? ' (Append)' : ' (Replace)') + '</span></li>';
    summaryHTML += '</ul></div>';

    return summaryHTML;
}

    function updateRecipientCountActual() {
        // Abort any in-flight requests
        if (recipientAjaxRequestWA) recipientAjaxRequestWA.abort();
        if (recipientAjaxRequestEM) recipientAjaxRequestEM.abort();

        // Base data (roles, users, filters, etc.), but we’ll override only_verified later
        var baseData = {
            action: 'camp_ext_calc_recipients',
            _camp_calc_nonce: campExtData.nonceCalc,
            roles: $('#roles_input').val() || [],
            users: $('#users_input').val() || [],
            billing_countries: $('#billing_countries_input').val() || [],
            wp_profile_languages: $('#wp_profile_languages_input').val() || [],
            woo_products: $('#products_input').val() || [],
            woo_statuses: $('#statuses_input').val() || [],
            woo_spent: $('input[name="woo_spent_over"]').val() || '0',
            woo_orders: $('input[name="woo_orders_over"]').val() || '0'
        };

        // Count external lists locally
        var externalWAcount   = countValidLines($('#external_numbers').val());
        var externalEMcount   = countValidLines($('#external_emails').val());
        var isWhatsAppEnabled = $('#send_whatsapp_switch').is(':checked');
        var isEmailEnabled    = $('#send_email_switch').is(':checked');

        // --- 1) Ajax #1: For WhatsApp, include only_verified if checked
        var dataWA = $.extend({}, baseData);
        dataWA.only_verified = $('input[name="only_verified_phone"]').is(':checked') ? 1 : 0;

        recipientAjaxRequestWA = $.post(campExtData.ajaxUrl, dataWA, function(respWA) {
            var wpCountWA = parseInt(respWA) || 0;
            var finalWA = isWhatsAppEnabled ? (wpCountWA + externalWAcount) : 0;

            $('#whatsapp_recipients_count').text(finalWA);

            // Now that we know finalWA, we can estimate finish for WA (we’ll do that after EM is known)
            estimateFinishPart('WA', wpCountWA, externalWAcount);
        }).fail(function(xhr, status, error) {
            if (status !== 'abort') {
                $('#whatsapp_recipients_count').html('<span class="text-danger">Error</span>');
                estimateFinishPart('WA_error', 0, 0);
            }
        });

        // --- 2) Ajax #2: For Email, _ignore_ only_verified altogether
        var dataEM = $.extend({}, baseData);
        dataEM.only_verified = 0; // force “include everyone” for email

        recipientAjaxRequestEM = $.post(campExtData.ajaxUrl, dataEM, function(respEM) {
            var wpCountEM = parseInt(respEM) || 0;
            var finalEM = isEmailEnabled ? (wpCountEM + externalEMcount) : 0;

            $('#email_recipients_count').text(finalEM);

            // now that we know finalEM, we can estimate finish for Email
            estimateFinishPart('EM', wpCountEM, externalEMcount);
        }).fail(function(xhr, status, error) {
            if (status !== 'abort') {
                $('#email_recipients_count').html('<span class="text-danger">Error</span>');
                estimateFinishPart('EM_error', 0, 0);
            }
        });
    }

    function updateRecipientCount() {
        let spinner = '<span class="spinner is-active" style="float:none; vertical-align: middle; margin-right: 5px;"></span>Calculating...';
        if ($('#send_whatsapp_switch').is(':checked')) $('#whatsapp_recipients_count').html(spinner);
        if ($('#send_email_switch').is(':checked')) $('#email_recipients_count').html(spinner);

        if(isFirstRecipientUpdateCall && $('#estimate_finish').length) $('#estimate_finish').html('<em>Calculating initial recipient estimate...</em>');
        debouncedUpdateRecipientCount();
        if(isFirstRecipientUpdateCall) isFirstRecipientUpdateCall = false;
    }
    
    function estimateFinish(wp_user_count_str, external_wa_count, external_email_count, isInitialPageLoad = false) { 
        let wp_users_base; 
        let calculationError = false;

        if (wp_user_count_str === 'Error' || isNaN(parseInt(wp_user_count_str, 10))) { 
            wp_users_base = 0; calculationError = true; 
        } else { 
            wp_users_base = parseInt(wp_user_count_str, 10); 
        }

        let isWhatsAppActive = $('#send_whatsapp_switch').is(':checked');
        let isEmailActive = $('#send_email_switch').is(':checked');

        let total_whatsapp_recipients = isWhatsAppActive ? (wp_users_base + external_wa_count) : 0;
        let total_email_recipients = isEmailActive ? (wp_users_base + external_email_count) : 0;

        let max_wa_daily_limit = parseInt($('#max_wa_per_day').val() || '0', 10);
        let max_email_daily_limit = parseInt($('#max_email_per_day').val() || '0', 10);
        
        let startVal = $('#start_datetime').val();
        
        let minWaI = parseInt($('#min_whatsapp_interval').val() || '60', 10);
        let maxWaI = parseInt($('#max_whatsapp_interval').val() || '75', 10);
        if(minWaI <=0) minWaI = 1; if(maxWaI < minWaI) maxWaI = minWaI + 15;
        
        let minEmailI = parseInt($('#min_email_interval').val() || '30', 10);
        let maxEmailI = parseInt($('#max_email_interval').val() || '60', 10);
        if(minEmailI <=0) minEmailI = 1; if(maxEmailI < minEmailI) maxEmailI = minEmailI + 15;

        let estBox = $('#estimate_finish'); let summaryContainer = $('#cex_extra_info'); 
        if (!summaryContainer.length && !estBox.length) return; 
        
        let startDateTime;
        if (startVal) { startDateTime = new Date(startVal); } else { startDateTime = new Date(); }
        if (isNaN(startDateTime.getTime())) { startDateTime = new Date(); }

        let estimatesHTML = ''; let warningMsg = '';
        let overallLatestFinishDate = null; 

        if (calculationError) { 
            estimatesHTML = "<p class='text-danger'>Could not calculate WordPress user estimate. Please check filters or server logs.</p>";
        } else if (total_whatsapp_recipients > 0 || total_email_recipients > 0) {
            if (isWhatsAppActive && total_whatsapp_recipients > 0) {
                let avgSecWa = (minWaI + maxWaI) / 2; if (avgSecWa <= 0) avgSecWa = 60;
                let waFinishDate;
                if (max_wa_daily_limit > 0) {
                    let daysNeededWa = Math.ceil(total_whatsapp_recipients / max_wa_daily_limit); 
                    waFinishDate = new Date(startDateTime.getTime()); waFinishDate.setDate(waFinishDate.getDate() + daysNeededWa -1);
                    estimatesHTML += '<p><i class="bi bi-whatsapp"></i> WhatsApp: Max ' + max_wa_daily_limit + ' msgs/day. Approx. ' + daysNeededWa + ' day(s). Est. Finish: ' + waFinishDate.toLocaleDateString() + '</p>';
                    if (max_wa_daily_limit > 500) warningMsg += '<p class="cex_warning_text"><i class="bi bi-exclamation-triangle"></i> Sending >500 WA msgs/day increases risk.</p>';
                } else { 
                    let totalSecWa = total_whatsapp_recipients * avgSecWa; 
                    waFinishDate = new Date(startDateTime.getTime() + totalSecWa * 1000);
                    estimatesHTML += '<p><i class="bi bi-whatsapp"></i> WhatsApp: No daily limit. Avg. Interval: ~' + Math.round(avgSecWa) + 's. Approx. duration: ' + formatDuration(totalSecWa) + '. Est. Finish: ' + waFinishDate.toLocaleString() + '</p>';
                    if (total_whatsapp_recipients > 500) warningMsg += '<p class="cex_warning_text"><i class="bi bi-exclamation-triangle"></i> Sending >500 WA msgs without daily limit is risky.</p>';
                }
                if (!overallLatestFinishDate || waFinishDate > overallLatestFinishDate) overallLatestFinishDate = waFinishDate;
            }

            if (isEmailActive && total_email_recipients > 0) {
                let avgSecEmail = (minEmailI + maxEmailI) / 2; if (avgSecEmail <= 0) avgSecEmail = 30;
                let emailFinishDate;
                if (max_email_daily_limit > 0) {
                    let daysNeededEmail = Math.ceil(total_email_recipients / max_email_daily_limit); 
                    emailFinishDate = new Date(startDateTime.getTime()); emailFinishDate.setDate(emailFinishDate.getDate() + daysNeededEmail -1);
                    estimatesHTML += '<p><i class="bi bi-envelope"></i> Email: Max ' + max_email_daily_limit + ' msgs/day. Approx. ' + daysNeededEmail + ' day(s). Est. Finish: ' + emailFinishDate.toLocaleDateString() + '</p>';
                } else { 
                    let totalSecEmail = total_email_recipients * avgSecEmail; 
                    emailFinishDate = new Date(startDateTime.getTime() + totalSecEmail * 1000);
                    estimatesHTML += '<p><i class="bi bi-envelope"></i> Email: No daily limit. Avg. Interval: ~' + Math.round(avgSecEmail) + 's. Approx. duration: ' + formatDuration(totalSecEmail) + '. Est. Finish: ' + emailFinishDate.toLocaleString() + '</p>';
                }
                 if (!overallLatestFinishDate || emailFinishDate > overallLatestFinishDate) overallLatestFinishDate = emailFinishDate;
            }
        } else { 
            estimatesHTML = "<p>No recipients found based on current criteria (WordPress users or external lists).</p>";
            if (isInitialPageLoad) { estimatesHTML = "<p><em>Enter your campaign criteria to see recipient estimates.</em></p>"; }
        }
        if (estBox.length) estBox.html(estimatesHTML + warningMsg);

        let summaryHTML = '<h4><i class="bi bi-clipboard-check-fill"></i> Campaign Configuration Review</h4>';
        summaryHTML += '<div class="cex_stat_cards_container">'; 
        
        summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">WA Recipients</div><div class="cex_stat_card_number">' + (isWhatsAppActive ? total_whatsapp_recipients : 'N/A') + '</div></div>';
        summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">Email Recipients</div><div class="cex_stat_card_number">' + (isEmailActive ? total_email_recipients : 'N/A') + '</div></div>';
        summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">WA Daily Limit</div><div class="cex_stat_card_number">' + (isWhatsAppActive ? (max_wa_daily_limit > 0 ? max_wa_daily_limit : 'None') : 'N/A') + '</div></div>';
        summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">Email Daily Limit</div><div class="cex_stat_card_number">' + (isEmailActive ? (max_email_daily_limit > 0 ? max_email_daily_limit : 'None') : 'N/A') + '</div></div>';
        
        let waDurationTextEst = "N/A";
        if (isWhatsAppActive && total_whatsapp_recipients > 0 && !calculationError) {
            if (max_wa_daily_limit === 0) {
                let avgSecWaEst = (minWaI + maxWaI) / 2; if(avgSecWaEst <=0) avgSecWaEst = 60;
                waDurationTextEst = formatDuration(total_whatsapp_recipients * avgSecWaEst);
            } else {
                let daysNeededWaEst = Math.ceil(total_whatsapp_recipients / max_wa_daily_limit); 
                waDurationTextEst = daysNeededWaEst + (daysNeededWaEst === 1 ? " day" : " days");
            }
        }
        summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">WA Approx. Duration</div><div class="cex_stat_card_number">' + waDurationTextEst + '</div></div>';

        let emailDurationTextEst = "N/A";
        if (isEmailActive && total_email_recipients > 0 && !calculationError) {
            if (max_email_daily_limit === 0) {
                let avgSecEmailEst = (minEmailI + maxEmailI) / 2; if(avgSecEmailEst <=0) avgSecEmailEst = 30;
                emailDurationTextEst = formatDuration(total_email_recipients * avgSecEmailEst);
            } else {
                let daysNeededEmailEst = Math.ceil(total_email_recipients / max_email_daily_limit); 
                emailDurationTextEst = daysNeededEmailEst + (daysNeededEmailEst === 1 ? " day" : " days");
            }
        }
        summaryHTML += '<div class="cex_stat_card"><div class="cex_stat_card_title">Email Approx. Duration</div><div class="cex_stat_card_number">' + emailDurationTextEst + '</div></div>';
        
        summaryHTML += '</div>'; 

        summaryHTML += '<ul class="cex_summary_details_list">';
        summaryHTML += '<li><i class="bi bi-card-text"></i><strong>Name:</strong> <span class="value">' + esc_html($('input[name="name"]').val() || '(Not set)') + '</span></li>';
        let channelsText = [];
        if (isWhatsAppActive) channelsText.push('WhatsApp'); if (isEmailActive) channelsText.push('Email');
        summaryHTML += '<li><i class="bi bi-broadcast-pin"></i><strong>Channels:</strong> <span class="value">' + (channelsText.length > 0 ? channelsText.join(', ') : '<span class="text-danger">None Selected</span>') + '</span></li>';
        summaryHTML += '</ul>';

        summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-people-fill"></i> Audience Definition</h5><ul class="cex_summary_details_list">';
        let selectedRolesText = ($('#roles_input').val() || []).map(r => $('#roles_input option[value="'+r+'"]').text()).join(', ');
        if (selectedRolesText) summaryHTML += '<li><i class="bi bi-person-badge"></i><strong>Target Roles:</strong> <span class="value">' + esc_html(selectedRolesText) + '</span></li>';
        let selectedUsersText = ($('#users_input').val() || []).map(u => $('#users_input option[value="'+u+'"]').text().split(' (ID:')[0]).join(', ');
        if (selectedUsersText) summaryHTML += '<li><i class="bi bi-person-check"></i><strong>Target Users:</strong> <span class="value">' + esc_html(selectedUsersText) + '</span></li>';
        if ($('#external_numbers').val().trim() && isWhatsAppActive) summaryHTML += '<li><i class="bi bi-telephone-plus"></i><strong>External Numbers (WA):</strong> <span class="value">Provided ('+external_wa_count+')</span></li>';
        if ($('#external_emails').val().trim() && isEmailActive) summaryHTML += '<li><i class="bi bi-envelope-plus"></i><strong>External Emails:</strong> <span class="value">Provided ('+external_email_count+')</span></li>';
        if ($('input[name="only_verified_phone"]:checked').length && isWhatsAppActive) summaryHTML += '<li><i class="bi bi-patch-check-fill"></i><strong>Only Verified Phones (WA):</strong> <span class="value">Yes</span></li>';
        summaryHTML += '</ul></div>';
        
        summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-funnel"></i> Additional Filters</h5><ul class="cex_summary_details_list">';
        let selBillingCountries = ($('#billing_countries_input').val() || []).filter(c => c !== ""); 
        summaryHTML += '<li><i class="bi bi-globe2"></i><strong>Billing Countries:</strong> <span class="value">' + (selBillingCountries.length > 0 ? esc_html(selBillingCountries.map(c => $('#billing_countries_input option[value="'+c+'"]').text()).join(', ')) : 'All') + '</span></li>';
        let selProfileLangs = ($('#wp_profile_languages_input').val() || []).filter(l => l !== "");
        summaryHTML += '<li><i class="bi bi-translate"></i><strong>Profile Languages:</strong> <span class="value">' + (selProfileLangs.length > 0 ? esc_html(selProfileLangs.map(l => $('#wp_profile_languages_input option[value="'+l+'"]').text().split(' (')[0] ).join(', ')) : 'All') + '</span></li>';
        summaryHTML += '</ul></div>';

        if (typeof campExtData !== 'undefined' && campExtData.isWooActive) {
            let wooSummaryContent = '';
            let wooSpentOver = parseFloat($('input[name="woo_spent_over"]').val()); if (!isNaN(wooSpentOver) && wooSpentOver > 0) wooSummaryContent += '<li><i class="bi bi-cash-coin"></i><strong>Min. Spent:</strong> <span class="value">' + esc_html($('input[name="woo_spent_over"]').val()) + '</span></li>';
            let wooOrdersOver = parseInt($('input[name="woo_orders_over"]').val()); if (!isNaN(wooOrdersOver) && wooOrdersOver > 0) wooSummaryContent += '<li><i class="bi bi-cart3"></i><strong>Min. Orders:</strong> <span class="value">' + esc_html($('input[name="woo_orders_over"]').val()) + '</span></li>';
            let selWooProdsText = ($('#products_input').val() || []).map(p => $('#products_input option[value="'+p+'"]').text().split(' (ID:')[0]).join(', '); if (selWooProdsText) wooSummaryContent += '<li><i class="bi bi-box-seam"></i><strong>Purchased Products:</strong> <span class="value">' + esc_html(selWooProdsText) + '</span></li>';
            let selWooStatusesText = ($('#statuses_input').val() || []).map(s => $('#statuses_input option[value="'+s+'"]').text()).join(', '); if (selWooStatusesText) wooSummaryContent += '<li><i class="bi bi-card-checklist"></i><strong>Order Statuses (Product Filter):</strong> <span class="value">' + esc_html(selWooStatusesText) + '</span></li>';
            if(wooSummaryContent) summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-cart-fill"></i> WooCommerce Filters</h5><ul class="cex_summary_details_list">' + wooSummaryContent + '</ul></div>';
        }
        summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-alarm-fill"></i> Scheduling</h5><ul class="cex_summary_details_list">';
        if (isWhatsAppActive) summaryHTML += '<li><i class="bi bi-whatsapp"></i><strong>WA Interval:</strong> <span class="value">' + minWaI + 's - ' + maxWaI + 's</span></li>';
        if (isEmailActive) summaryHTML += '<li><i class="bi bi-envelope"></i><strong>Email Interval:</strong> <span class="value">' + minEmailI + 's - ' + maxEmailI + 's</span></li>';
        summaryHTML += '<li><i class="bi bi-play-btn-fill"></i><strong>Start:</strong> <span class="value">' + (startVal ? new Date(startVal).toLocaleString() : 'ASAP') + '</span></li>';
        summaryHTML += '<li><i class="bi bi-arrow-repeat"></i><strong>Repeat:</strong> <span class="value">' + $('#repeat_type option:selected').text();
        if ($('#repeat_type').val() === 'custom') summaryHTML += ' (' + ($('input[name="repeat_days"]').val() || '0') + ' days)';
        summaryHTML += '</span></li></ul></div>';
        
        summaryHTML += '<div class="cex_summary_subsection"><h5><i class="bi bi-file-text-fill"></i> Message Details</h5><ul class="cex_summary_details_list">';
        summaryHTML += '<li><i class="bi bi-chat-square-dots"></i><strong>Message Type Setting:</strong> <span class="value">' + $('#send_type option:selected').text() + '</span></li>';
        if (isWhatsAppActive) {
            const msgPreview = $('#message_input').val();
            if(msgPreview) summaryHTML += '<li><i class="bi bi-fonts"></i><strong>WhatsApp Text:</strong> <span class="value">' + esc_html(msgPreview.substring(0,70) + (msgPreview.length > 70 ? '...' : '')) + '</span></li>';
            if ($('#send_type').val() === 'media') {
                const mediaUrl = $('#media_url_input').val(); const mediaFile = $('#media_file_upload_input').val();
                if(mediaUrl) summaryHTML += '<li><i class="bi bi-paperclip"></i><strong>WA Media URL:</strong> <span class="value">' + esc_html(mediaUrl.substring(0,50) + (mediaUrl.length > 50 ? '...' : '')) + '</span></li>';
                if(mediaFile) summaryHTML += '<li><i class="bi bi-upload"></i><strong>New WA Media File:</strong> <span class="value">Yes</span></li>';
            }
        }
        if (isEmailActive) {
            const emailSub = $('#email_subject_input').val();
            if(emailSub) summaryHTML += '<li><i class="bi bi-envelope-paper"></i><strong>Email Subject:</strong> <span class="value">' + esc_html(emailSub.substring(0,70) + (emailSub.length > 70 ? '...' : '')) + '</span></li>';
            var emailBodyPreview = (typeof tinymce !== 'undefined' && tinymce.get('email_message_editor')) ? tinymce.get('email_message_editor').getContent({format : 'text'}) : $('#email_message_editor').val();
            if(emailBodyPreview) summaryHTML += '<li><i class="bi bi-file-earmark-richtext"></i><strong>Email Body:</strong> <span class="value">' + esc_html(emailBodyPreview.substring(0,70) + (emailBodyPreview.length > 70 ? '...' : '')) + '</span></li>';
        }
        const postId = $('#post_id_select').val(); const sendTypeVal = $('#send_type').val();
        if(postId && (sendTypeVal === 'post')) summaryHTML += '<li><i class="bi bi-file-post"></i><strong>Shared Post/Page:</strong> <span class="value">' + esc_html($('#post_id_select option:selected').text()) + ($('input[name="append_post"]').is(':checked') ? ' (Append)' : ' (Replace)') + '</span></li>';
        const prodId = $('#product_id_select').val();
        if(prodId && (sendTypeVal === 'product')) summaryHTML += '<li><i class="bi bi-archive"></i><strong>Shared Product:</strong> <span class="value">' + esc_html($('#product_id_select option:selected').text()) + ($('input[name="append_product"]').is(':checked') ? ' (Append)' : ' (Replace)')+ '</span></li>';
        summaryHTML += '</ul></div>';

        summaryContainer.html(summaryHTML + '<hr class="cex_hr_tight" style="margin-top:15px;">' + (estBox.length ? estBox.html() : estimatesHTML + warningMsg) ); 
    }


    function formatDuration(totalSec) { 
        if (totalSec <= 0) return "0s";
        const days = Math.floor(totalSec / 86400); const hours = Math.floor((totalSec % 86400) / 3600);
        const minutes= Math.floor((totalSec % 3600) / 60); const seconds= Math.floor(totalSec % 60);
        let parts = [];
        if (days > 0) parts.push(days + (days > 1 ? ' days' : ' day')); if (hours > 0) parts.push(hours + 'h');
        if (minutes > 0) parts.push(minutes + 'm'); if (seconds > 0 || parts.length === 0) parts.push(seconds + 's');
        return parts.join(' ');
    }
    
    const fieldsToMonitorForDebounce = [
        'input[name="woo_spent_over"]', 'input[name="woo_orders_over"]', 
        '#external_numbers', '#external_emails', 'input[name="name"]', '#email_subject_input',
        '#message_input', 
    ];
    $(document).on('keyup input', fieldsToMonitorForDebounce.join(','), function() { 
        saveFormState(); 
        debouncedUpdateRecipientCount(); 
    });
    
    const fieldsForUIConditionUpdateAndSave = [
        '#send_whatsapp_switch', '#send_email_switch', '#send_type', 
        'input[name="append_post"]', 'input[name="append_product"]',
        '#repeat_type'
    ];
    $(document).on('change', fieldsForUIConditionUpdateAndSave.join(','), function() {
        updateUIDisplay(); 
        saveFormState();
    });

    const fieldsForFullRecalcAndUpdateAndSave = [
        'input[name="only_verified_phone"]', 
        'input[name="max_wa_per_day"]', 'input[name="max_email_per_day"]',
        'input[name="min_whatsapp_interval"]', 'input[name="max_whatsapp_interval"]',
        'input[name="min_email_interval"]', 'input[name="max_email_interval"]',
        '#start_datetime', 'input[name="repeat_days"]',
        'input[name^="post_include_"]', 'input[name^="product_include_"]',
        '#roles_input', '#users_input', 
        '#billing_countries_input', '#wp_profile_languages_input', 
        '#products_input', '#statuses_input'
    ];
     $(document).on('change', fieldsForFullRecalcAndUpdateAndSave.join(','), function() { 
        saveFormState();
        updateRecipientCount(); 
    });


    function clearFieldError(fieldId) {
        $('#error_for_' + fieldId).text('').hide(); var $field = $('#' + fieldId);
        $field.removeClass('cex_input_error');
        if ($field.hasClass('cex_select2_field')) $field.next('.select2-container').removeClass('cex_input_error');
        if (fieldId === 'email_message_editor') $('#wp-email_message_editor-wrap').removeClass('cex_input_error');
        if (fieldId === 'channels_selection') $('#send_whatsapp_switch').closest('td').find('.cex_field_error#error_for_channels_selection').hide().text('');
    }
    function clearAllErrors(stepContainer) {
        if(!stepContainer || !stepContainer.length) return; 
        stepContainer.find('.cex_field_error').text('').hide();
        stepContainer.find('.cex_input_error').removeClass('cex_input_error');
        stepContainer.find('.select2-container.cex_input_error').removeClass('cex_input_error');
        stepContainer.find('#wp-email_message_editor-wrap.cex_input_error').removeClass('cex_input_error');
        stepContainer.find('.cex_step_error_summary').hide().find('p').empty();
        $('#cex_form_error_summary').hide().find('p').empty();
    }
    function showFieldError(fieldId, message) {
        var $errorSpan = $('#error_for_' + fieldId); var $field = $('#' + fieldId);
        if (fieldId === 'channels_selection') { 
            $errorSpan = $('#send_whatsapp_switch').closest('td').find('.cex_field_error#error_for_channels_selection');
             if(!$errorSpan.length){ $('#send_whatsapp_switch').closest('td').append('<span class="cex_field_error" id="error_for_channels_selection" style="display:block; width:100%;"></span>'); $errorSpan = $('#error_for_channels_selection'); }
        }
        $errorSpan.text(message).show();
        if ($field.hasClass('cex_select2_field') && $field.data('select2')) { $field.next('.select2-container').addClass('cex_input_error'); 
        } else if (fieldId === 'email_message_editor') {
             $('#wp-email_message_editor-wrap').addClass('cex_input_error');
             if (typeof tinymce !== 'undefined' && tinymce.get(fieldId) && tinymce.get(fieldId).isHidden && !tinymce.get(fieldId).isHidden()) { tinymce.get(fieldId).focus(); } else { $field.focus(); }
        } else { if ($field.length) $field.addClass('cex_input_error').focus(); }
    }
    function showStepSummaryError(stepContainer, message) {
        if(!stepContainer || !stepContainer.length) return;
        stepContainer.find('.cex_step_error_summary').show().find('p').html(message);
    }
    
    function validateStep(stepNumber, isSaveForLaterAction) {
        var currentStepContainer = $('#cex_step_' + stepNumber); clearAllErrors(currentStepContainer); 
        var isValid = true; var errors = [];
        var isWhatsAppEnabled = $('#send_whatsapp_switch').is(':checked');
        var isEmailEnabled = $('#send_email_switch').is(':checked');

        if (stepNumber === 1) { 
            if (!$('#campaign_name').val().trim()) { showFieldError('campaign_name', 'Campaign Name is required.'); isValid = false; errors.push('Campaign Name is required.'); }
            
            if (!isWhatsAppEnabled && !isEmailEnabled) { 
                showFieldError('channels_selection', 'At least one channel (WhatsApp or Email) must be enabled.'); isValid = false; errors.push('At least one sending channel must be selected.');
            }
            if (isWhatsAppEnabled && ($('#instances_input').val() || []).length === 0) {
                showFieldError('instances_input', 'At least one Instance must be selected for WhatsApp.'); isValid = false; errors.push('Instance required for WhatsApp.');
            }

            if (!isSaveForLaterAction) {
                let roles = $('#roles_input').val() || []; let users = $('#users_input').val() || []; 
                let ext_numbers = $('#external_numbers').val().trim();
                let ext_emails = $('#external_emails').val().trim();
                let audienceProvided = roles.length || users.length;
                if(isWhatsAppEnabled && ext_numbers) audienceProvided = true;
                if(isEmailEnabled && ext_emails) audienceProvided = true;

                if (!audienceProvided && (isWhatsAppEnabled || isEmailEnabled)) { // Only require audience if a channel is active
                    let audienceErrorField = isWhatsAppEnabled ? 'external_numbers_combined_audience' : 'external_emails_combined_audience'; 
                     if ($('#external_numbers_combined_audience').length == 0) { // Fallback if the specific error field isn't there
                        $('#roles_input').closest('td').append('<span class="cex_field_error" id="error_for_external_numbers_combined_audience" style="display:block; width:100%;"></span>');
                        audienceErrorField = 'external_numbers_combined_audience';
                     }
                    showFieldError(audienceErrorField, 'Please select Target Roles, Users, or enter External Numbers/Emails for active channels.'); 
                    isValid = false; errors.push('Target audience is required for active channels.'); 
                }
            }
        }
        if (stepNumber === 3 && !isSaveForLaterAction && typeof campExtData !== 'undefined' && campExtData.isWooActive) { 
            const wooSpent = $('input[name="woo_spent_over"]').val(); const wooOrders = $('input[name="woo_orders_over"]').val();
            if (wooSpent.trim() !== '' && (isNaN(parseFloat(wooSpent)) || parseFloat(wooSpent) < 0)) { showFieldError('woo_spent_over', 'Min. Total Spent must be a valid positive number.'); isValid = false; errors.push('Invalid Min. Total Spent.'); }
            if (wooOrders.trim() !== '' && (isNaN(parseInt(wooOrders)) || parseInt(wooOrders) < 0)) { showFieldError('woo_orders_over', 'Min. Order Count must be a valid positive number.'); isValid = false; errors.push('Invalid Min. Order Count.'); }
        }

        if (stepNumber === 4 && !isSaveForLaterAction) {
            const sendType = $('#send_type').val();
            if (isWhatsAppEnabled) {
                const isMessageEmpty = !$('#message_input').val().trim(); 
                const isAppendPost = $('input[name="append_post"]').is(':checked'); 
                const isAppendProduct = $('input[name="append_product"]').is(':checked');
                if (sendType === 'text' && isMessageEmpty) { showFieldError('message_input', 'WhatsApp Text is required.'); isValid = false; errors.push('WhatsApp Text required.'); }
                else if (sendType === 'media') { 
                    if (isMessageEmpty) { showFieldError('message_input', 'WhatsApp Text is required when sending media.'); isValid = false; errors.push('WhatsApp Text required for Media.'); } 
                    if (!$('#media_url_input').val().trim() && !$('#media_file_upload_input').val().trim()) { showFieldError('media_url_input', 'WhatsApp Media URL or File Upload is required for Media type.'); isValid = false; errors.push('WhatsApp Media required.'); } 
                }
                else if (sendType === 'post') { if (!$('#post_id_select').val()) { showFieldError('post_id_select', 'Please select a Post/Page to share.'); isValid = false; errors.push('Post/Page selection required.'); } else if (isAppendPost && isMessageEmpty){ showFieldError('message_input', 'WhatsApp Text is required when "Append" is checked for Post.'); isValid = false; errors.push('WhatsApp Text required for append mode with Post.'); } }
                else if (sendType === 'product') { if (!$('#product_id_select').val()) { showFieldError('product_id_select', 'Please select a Product to share.'); isValid = false; errors.push('Product selection required.'); } else if (isAppendProduct && isMessageEmpty){ showFieldError('message_input', 'WhatsApp Text is required when "Append" is checked for Product.'); isValid = false; errors.push('WhatsApp Text required for append mode with Product.'); } }
            }
            if (isEmailEnabled) {
                if (!$('#email_subject_input').val().trim()) { showFieldError('email_subject_input', 'Email Subject is required.'); isValid = false; errors.push('Email Subject required.'); }
                var emailEditorContent = (typeof tinymce !== 'undefined' && tinymce.get('email_message_editor')) ? tinymce.get('email_message_editor').getContent() : $('#email_message_editor').val();
                if (!emailEditorContent.trim()) { showFieldError('email_message_editor', 'Email Body is required.'); isValid = false; errors.push('Email Body required.'); }
                if (sendType === 'post' && !$('#post_id_select').val()) { showFieldError('post_id_select', 'Please select a Post/Page to share for Email content.'); isValid = false; errors.push('Post/Page selection required for Email with selected message type.'); }
                if (sendType === 'product' && !$('#product_id_select').val()) { showFieldError('product_id_select', 'Please select a Product to share for Email content.'); isValid = false; errors.push('Product selection required for Email with selected message type.'); }
            }
        }
        if (stepNumber === 5 && !isSaveForLaterAction) { 
            if(isWhatsAppEnabled){
                const minWaIntVal = $('#min_whatsapp_interval').val(); const maxWaIntVal = $('#max_whatsapp_interval').val();
                const minWaInt = parseInt(minWaIntVal); const maxWaInt = parseInt(maxWaIntVal);
                const maxWaDay = parseInt($('#max_wa_per_day').val());
                if (minWaIntVal.trim() === '' || isNaN(minWaInt) || minWaInt < 1) { showFieldError('min_whatsapp_interval', 'Min WhatsApp Interval must be a number >= 1.'); isValid = false; errors.push('Invalid Min WhatsApp Interval.'); }
                if (maxWaIntVal.trim() === '' || isNaN(maxWaInt) || maxWaInt < minWaInt) { showFieldError('max_whatsapp_interval', 'Max WhatsApp Interval must be a number and >= Min WA Interval.'); isValid = false; errors.push('Invalid Max WhatsApp Interval.'); }
                if (isNaN(maxWaDay) || maxWaDay < 0) { showFieldError('max_wa_per_day', 'WhatsApp Daily Send Limit must be a number >= 0.'); isValid = false; errors.push('Invalid WhatsApp Daily Send Limit.'); }
            }
            if(isEmailEnabled){
                const minEmailIntVal = $('#min_email_interval').val(); const maxEmailIntVal = $('#max_email_interval').val();
                const minEmailInt = parseInt(minEmailIntVal); const maxEmailInt = parseInt(maxEmailIntVal);
                const maxEmailDay = parseInt($('#max_email_per_day').val());
                if (minEmailIntVal.trim() === '' || isNaN(minEmailInt) || minEmailInt < 1) { showFieldError('min_email_interval', 'Min Email Interval must be a number >= 1.'); isValid = false; errors.push('Invalid Min Email Interval.'); }
                if (maxEmailIntVal.trim() === '' || isNaN(maxEmailInt) || maxEmailInt < minEmailInt) { showFieldError('max_email_interval', 'Max Email Interval must be a number and >= Min Email Interval.'); isValid = false; errors.push('Invalid Max Email Interval.'); }
                 if (isNaN(maxEmailDay) || maxEmailDay < 0) { showFieldError('max_email_per_day', 'Email Daily Send Limit must be a number >= 0.'); isValid = false; errors.push('Invalid Email Daily Send Limit.'); }
            }
        }
        if (!isValid && errors.length > 0) { showStepSummaryError(currentStepContainer, 'Please correct the highlighted errors:<ul><li>' + errors.join('</li><li>') + '</li></ul>'); }
        return isValid;
    }
    
    function navigateToStep(stepNum, fromHistory = false) {
        if (!$('#cex_step_' + stepNum).length) stepNum = 1; 
        $('.cex_step').hide();
        $('#cex_step_' + stepNum).show().find('.cex_select2_field').each(function() { initSelect2(this); }); // Re-init select2 for the current step
        
        if (!fromHistory && window.location.hash !== '#step-' + stepNum) { 
            window.location.hash = 'step-' + stepNum;
        }
        if (stepNum === 6) {
            updateRecipientCount(); 
        }
        $('html, body').animate({ scrollTop: $('.cex_wrap_form').offset().top - 50 }, 0); 
        saveFormState(); // Save state after navigation
    }

    $(document).on('click', '.cex_next_btn', function() { 
        let currentStepNum = parseInt($(this).data('step'));
        if (!validateStep(currentStepNum, false)) return;
        let nextStepNum = $(this).data('next');
        navigateToStep(nextStepNum);
    });
    $(document).on('click', '.cex_prev_btn', function() { 
        let prevStepNum = $(this).data('prev');
        navigateToStep(prevStepNum);
    });

    $(window).on('popstate', function(event) {
        var hash = window.location.hash;
        if ($('#cex_multi_form').length > 0) { // Only for new campaign multi-step form
            var targetStep = 1;
            if (hash && hash.startsWith('#step-')) {
                var stepNum = parseInt(hash.substring(6));
                if (stepNum >= 1 && stepNum <= 6) {
                    targetStep = stepNum;
                }
            }
            navigateToStep(targetStep, true); 
        }
    });

    function saveFormState() {
        if (!$('#cex_multi_form').length) return; 
        var formData = $('#cex_multi_form').serializeArray();
        var dataToStore = { version: formStateVersion }; // Add version
        
        $.each(formData, function(i, field){
            if (dataToStore[field.name] !== undefined) {
                if (!Array.isArray(dataToStore[field.name])) {
                    dataToStore[field.name] = [dataToStore[field.name]];
                }
                dataToStore[field.name].push(field.value);
            } else {
                dataToStore[field.name] = field.value;
            }
        });
        $('#cex_multi_form input[type="checkbox"]').each(function() {
            dataToStore[this.name] = $(this).is(':checked') ? '1' : '0';
        });
        
        $('#cex_multi_form .cex_select2_field[multiple]').each(function() {
            dataToStore[$(this).attr('name').replace('[]','')] = $(this).val() || [];
        });
        if (typeof tinymce !== 'undefined' && tinymce.get('email_message_editor') && tinymce.get('email_message_editor').isDirty()) {
            dataToStore['email_message'] = tinymce.get('email_message_editor').getContent();
        } else if (!dataToStore['email_message'] && $('#email_message_editor').length) { // if not dirty, still try to get initial val if not set
             dataToStore['email_message'] = $('#email_message_editor').val();
        }


        localStorage.setItem(formStateKey, JSON.stringify(dataToStore));
    }

    function loadFormState() {
        if (!$('#cex_multi_form').length) return;
        var storedData = localStorage.getItem(formStateKey);
        if (storedData) {
            var data = JSON.parse(storedData);
            if (data.version !== formStateVersion) { // Clear if version mismatch
                localStorage.removeItem(formStateKey);
                console.warn('Form state version mismatch, cleared stored data.');
                return;
            }
            delete data.version; // Remove version before iterating

            $.each(data, function(name, value) {
                var $field = $('#cex_multi_form [name="' + name + '"], #cex_multi_form [name="' + name + '[]"]');
                if ($field.is(':checkbox')) {
                    $field.prop('checked', value === '1').trigger('change'); // Trigger change for UI updates
                } else if ($field.is('select[multiple]')) {
                     $field.val(value).trigger('change.select2');
                } else if ($field.is('textarea') && name === 'email_message') {
                    if (typeof tinymce !== 'undefined' && tinymce.get('email_message_editor')) {
                        tinymce.get('email_message_editor').setContent(value);
                         // tinymce change event will be triggered by setContent
                    } else {
                        $field.val(value).trigger('input'); // Trigger input for other listeners
                    }
                } else if ($field.length) { // General input/select
                    $field.val(value).trigger('change'); // Trigger change for select2 or other listeners
                }
            });
             $('#campaign_name').trigger('input'); // Ensure title updates
             updateUIDisplay(); // Ensure all conditional UI is correct after loading
        }
    }

    $(document).on('submit', '#cex_multi_form', function(e) { 
        var $form = $(this); var overallValid = true; var firstInvalidStep = 0;
        $('#cex_form_error_summary').hide().find('p').empty(); 
        var $triggeringButton = $(document.activeElement);
        var isSaveForLaterAction = $triggeringButton.is('button[name="save_for_later"]');
        
        if (!validateStep(1, isSaveForLaterAction)) { 
             overallValid = false; if(firstInvalidStep === 0) firstInvalidStep = 1;
        }

        if (!isSaveForLaterAction) { 
            if (!validateStep(1, false)) { overallValid = false; if(firstInvalidStep === 0) firstInvalidStep = 1;} 
            if (!validateStep(2, false)) { overallValid = false; if(firstInvalidStep === 0) firstInvalidStep = 2;}
            if (typeof campExtData !== 'undefined' && campExtData.isWooActive) { if (!validateStep(3, false)) { overallValid = false; if(firstInvalidStep === 0) firstInvalidStep = 3;}}
            if (!validateStep(4, false)) { overallValid = false; if(firstInvalidStep === 0) firstInvalidStep = 4;}
            if (!validateStep(5, false)) { overallValid = false; if(firstInvalidStep === 0) firstInvalidStep = 5;}
        }

        if (!overallValid) {
            e.preventDefault(); let errorSummaryText = 'Please correct the errors in the form before submitting.';
            if (firstInvalidStep > 0) {
                errorSummaryText = 'Please correct errors, starting in Step ' + firstInvalidStep + '.';
                navigateToStep(firstInvalidStep, true); // Use true to avoid double hash change
                
                setTimeout(function() { 
                    let $firstErrorInStep = $('#cex_step_' + firstInvalidStep).find('.cex_input_error:visible:first, #wp-email_message_editor-wrap.cex_input_error:visible:first, .select2-container.cex_input_error:visible:first, .cex_step_error_summary:visible:first').first();
                    if ($firstErrorInStep.length) {
                        $('html, body').animate({ scrollTop: $firstErrorInStep.offset().top - 70 }, 300, function() {
                            // Try focusing after scroll
                            if ($firstErrorInStep.is('input, textarea, select')) {
                                $firstErrorInStep.focus();
                            } else if ($firstErrorInStep.find('input, textarea, select').length) {
                                $firstErrorInStep.find('input, textarea, select').first().focus();
                            }
                         });
                    } else {
                         $('html, body').animate({ scrollTop: $('#cex_step_' + firstInvalidStep).offset().top - 70 }, 300);
                    }
                }, 100);
            }
            $('#cex_form_error_summary').show().find('p').text(errorSummaryText); 
        } else {
            if (!isSaveForLaterAction) { // Only clear storage on full campaign creation
                localStorage.removeItem(formStateKey); 
            }
            $('#cex_form_error_summary').hide();
            var $clickedButton = $triggeringButton.is('button[type="submit"]') ? $triggeringButton : $form.find('button[type="submit"]:first');
            $clickedButton.prop('disabled', true).prepend('<span class="spinner is-active" style="float:left; margin:-2px 5px 0 0;"></span>');
            $form.find('button[type="submit"]').not($clickedButton).prop('disabled', true);
        }
    });
    $(document).on('submit', '#cex_edit_form', function(e) { 
        var $form = $(this); var overallValid = true;
        $('#cex_form_error_summary').hide().find('p').empty();
        
        // For edit form, all fields are visible, so just validate all steps directly
        // Step 1 now includes channels, so it needs to be validated as if not save for later
        if (!validateStep(1, false)) overallValid = false; 
        if (!validateStep(2, false)) overallValid = false; 
        if (typeof campExtData !== 'undefined' && campExtData.isWooActive) { if (!validateStep(3, false)) overallValid = false; }
        if (!validateStep(4, false)) overallValid = false; 
        if (!validateStep(5, false)) overallValid = false; 

        if (!overallValid) {
            e.preventDefault(); $('#cex_form_error_summary').show().find('p').text('Please correct the errors in the form before submitting.');
            let $firstErrorField = $form.find('.cex_input_error:visible:first, #wp-email_message_editor-wrap.cex_input_error:visible:first, .select2-container.cex_input_error:visible:first, .cex_field_error:visible:first').first();
            if ($firstErrorField.length) $('html, body').animate({ scrollTop: $firstErrorField.offset().top - 70 }, 300);
            else $('html, body').animate({ scrollTop: $('#cex_form_error_summary').first().offset().top - 70 }, 300);
        } else {
            $('#cex_form_error_summary').hide();
            var $submitButton = $(document.activeElement).is('button[type="submit"]') ? $(document.activeElement) : $form.find('button[type="submit"]:first');
            $submitButton.prop('disabled', true).prepend('<span class="spinner is-active" style="float:left; margin:-2px 5px 0 0;"></span>');
            $form.find('button[type="submit"]').not($submitButton).prop('disabled', true);
        }
    });
    
    $(document).on('click', '.cex_risky_btn', function() { let campaignId = $(this).data('id'); $('#cex_risky_cid').val(campaignId); $('#cex_risky_modal').fadeIn(); });
    $(document).on('click', '#cex_risky_cancel_modal, .cex_modal_close', function() { $('#cex_risky_modal').fadeOut(); $('#cex_risky_cid').val(''); });
    $(document).on('click', '#cex_risky_agree', function() { 
        let cid = $('#cex_risky_cid').val(); if (cid) {
            $(this).prop('disabled', true).text('Processing...');
            $.post(campExtData.ajaxUrl, { action: 'run_risky_campaign', cid: cid, _ajax_nonce: campExtData.nonceRunRisky }, 
            function(response) {
                if(response.success) { location.reload(); } 
                else { alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Could not run campaign.')); $('#cex_risky_agree').prop('disabled', false).text('Yes, Run Campaign'); }
            }).fail(function(){ alert('Request failed.'); $('#cex_risky_agree').prop('disabled', false).text('Yes, Run Campaign'); });
        }
    });
    $(window).on('click', function(event) { if ($(event.target).is('#cex_risky_modal')) { $('#cex_risky_modal').fadeOut(); }});

    function initializeFormState() {
        if ($('#cex_multi_form').length) { 
            loadFormState(); 
            var hash = window.location.hash;
            var initialStep = 1;
            if (hash && hash.startsWith('#step-')) {
                var stepNum = parseInt(hash.substring(6));
                if (stepNum >= 1 && stepNum <= 6) {
                    initialStep = stepNum;
                }
            }
            // Hide all steps first, then show the target one.
            $('.cex_step').hide();
            navigateToStep(initialStep, true); 
        }
        
        updateUIDisplay(); 
        $('#repeat_type').trigger('change'); 
        $('.cex_select2_field').each(function() { initSelect2(this); });
        
        if ($('#post_id_select').length && $('#post_id_select').val()) { loadPreview('#post_id_select', '#post_preview_area'); }
        if ($('#product_id_select').length && $('#product_id_select').val()) { loadPreview('#product_id_select', '#product_preview_area'); }
        
        if ($('#cex_multi_form').length || $('#cex_edit_form').length) { 
            updateRecipientCount(); 
        }
        $('#campaign_name').trigger('input'); 
    }

    if ($('#cex_multi_form').length || $('#cex_edit_form').length) { 
        initializeFormState(); 
    }

    $(window).on('beforeunload', function() {
        if ($('#cex_multi_form').length) {
            saveFormState(); // Save before leaving the new campaign page
        }
    });


    function esc_html(str) { 
        if (typeof str !== 'string') return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    function esc_url(url) { 
        if (typeof url !== 'string') return '';
        var SCRIPT_REGEX = /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi;
        while (SCRIPT_REGEX.test(url)) {
            url = url.replace(SCRIPT_REGEX, "");
        }
        url = url.replace(/[^-A-Za-z0-9+&@#/%?=~_|!:,.;\(\)\s\[\]]/g, ''); 
        return url;
    }
});


