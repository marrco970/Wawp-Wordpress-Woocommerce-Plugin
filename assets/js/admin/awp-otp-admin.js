jQuery(document).ready(function($) {
    'use strict';

    // Ensure Lucide icons are initialized after they are loaded
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        lucide.createIcons();
    }
    
        function updateEmailPreview() {
        var emailContent = $('#awp_otp_message_email').val();
        $('#email-preview').html(emailContent);
    }

    // Update preview on page load
    updateEmailPreview();

    // Update preview in real-time as user types
    $('#awp_otp_message_email').on('input', function() {
        updateEmailPreview();
    });

    // --- Modal Overlay CSS & General UI Improvements ---
    const modalCss = `
        /* General Modal Styling */
        #awp-custom-field-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999; /* Ensure this is high enough */
            overflow-y: auto;
            backdrop-filter: blur(5px);
        }

        #awp-custom-field-modal .modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            width: 90%;
            max-width: 650px;
            box-sizing: border-box;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeInScale 0.3s ease-out forwards;
            z-index: 100000; /* Ensure modal content is above other things too */
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        #awp-custom-field-modal .close-button {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
            transition: color 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            border-radius: 50%;
        }

        #awp-custom-field-modal .close-button:hover {
            color: #333;
            transform: rotate(90deg);
        }
        #awp-custom-field-modal .close-button i { width: 24px; height: 24px; }

        #awp-custom-field-modal h3 {
            font-size: 24px;
            margin-bottom: 25px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        /* Form Group & Labels */
        #awp-custom-field-modal .form-group { margin-bottom: 10px; }
        #awp-custom-field-modal .form-group label {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #555;
            gap: 10px;
  margin-bottom: 10px;
        }
        #awp-custom-field-modal .form-group .lucide-icon-inline {
            width: 18px;
            height: 18px;
            color: #666;
        }

        /* Input/Textarea Styling */
        #awp-custom-field-modal .awp-input,
        #awp-custom-field-modal .awp-textarea { /* New classes for consistency */
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 15px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        #awp-custom-field-modal .awp-input:focus,
        #awp-custom-field-modal .awp-textarea:focus {
            border-color: #1fc16b;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.2);
            outline: none;
        }

        #awp-custom-field-modal .form-group .description {
            font-style: italic;
            font-size: 0.85em;
            color: #888;
            margin-top: 8px;
            line-height: 1.4;
        }

        #awp-custom-field-modal .awp-error-message {
            color: #dc3232;
            font-size: 0.8em;
            margin-top: 5px;
            display: none;
        }

        /* --- Custom Select (Field Type) Styling --- */
        .awp-custom-select-wrapper {
            position: relative;
            width: 100%;
        }

        .awp-custom-select-wrapper select.awp-select {
            /* Hide the original select box but keep its value */
            display: none !important; /* Force hide to prevent interference */
            position: absolute; /* Keep it in the flow for validation purposes */
            opacity: 0;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 10; /* Make it clickable, but only for value detection */
            pointer-events: none; /* Prevent mouse events on native select */
        }

        .awp-select-display {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            position: relative;
            z-index: 5; /* Above options list when closed */
        }

        .awp-custom-select-wrapper.active .awp-select-display {
            border-color: #1fc16b;
            box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.2);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .awp-select-display .awp-selected-icon {
            margin-right: 8px;
            display: flex;
            align-items: center;
            color: #666;
        }
        .awp-select-display .awp-selected-icon i {
            width: 18px;
            height: 18px;
        }

        .awp-select-display .awp-selected-text {
            flex-grow: 1;
            font-size: 15px;
            color: #333;
        }

        .awp-select-display .awp-select-arrow {
            margin-left: 10px;
            display: flex;
            align-items: center;
            color: #999;
            transition: transform 0.2s ease;
        }
        .awp-select-display .awp-select-arrow i {
            width: 18px;
            height: 18px;
        }

        .awp-custom-select-wrapper.active .awp-select-arrow {
            transform: rotate(180deg);
        }

        .awp-select-options {
            position: absolute;
            top: 100%; /* Position below the display */
            left: 0;
            width: 100%;
            background-color: #fff;
            border: 1px solid #ddd;
            border-top: none; /* No top border, blends with display */
            border-bottom-left-radius: 6px;
            border-bottom-right-radius: 6px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            list-style: none;
            padding: 0;
            margin: 0; /* Remove default margin */
            max-height: 200px;
            overflow-y: auto;
            z-index: 100; /* Ensure it's above other elements */
            display: none; /* Hidden by default */
        }

        .awp-select-options li {
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            color: #333;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        .awp-select-options li:hover {
            background-color: #f0f0f0;
        }
        .awp-select-options li.selected {
            background-color: #e6f7ff; /* Light blue for selected item */
            color: #1fc16b;
            font-weight: 600;
        }
        .awp-select-options li i {
            width: 18px;
            height: 18px;
            color: #666;
        }
        .awp-select-options li.selected i {
            color: #1fc16b;
        }

        /* --- Toggle Switch Button Styling --- */
        .awp-toggle-switch-group {
            margin-top: 5px;        }
        .awp-switch-label {
            position: relative;
            display: inline-flex; /* Use inline-flex to align with text/icon */
            align-items: center;
            cursor: pointer;
            font-weight: normal;
            color: #555;
            gap: 8px; /* Space between switch and text/icon */
            padding-left: 48px; /* Space for the slider */
            min-height: 28px; /* Ensure enough height for the switch */
        }

        .awp-toggle-checkbox {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .awp-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: auto; /* Changed from right:0 to make it stick to left of label */
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            width: 40px; /* Width of the switch */
            height: 24px; /* Height of the switch */
            border-radius: 34px; /* Make it round */
        }

        .awp-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        .awp-toggle-checkbox:checked + .awp-slider {
            background-color: #1fc16b;
        }

        .awp-toggle-checkbox:focus + .awp-slider {
            box-shadow: 0 0 1px #1fc16b;
        }

        .awp-toggle-checkbox:checked + .awp-slider:before {
            transform: translateX(16px); /* Move slider thumb to the right */
        }

        /* Rounded sliders */
        .awp-slider.round {
            border-radius: 34px;
        }

        .awp-slider.round:before {
            border-radius: 50%;
        }

        /* Adjust icon position for switch labels */
        .awp-switch-label .lucide-icon-inline {
            width: 18px;
            height: 18px;
            color: #666;
            margin-left: 0; /* Reset margin */
        }
        .awp-toggle-checkbox:checked ~ .lucide-icon-inline { /* Use general sibling combinator */
            color: #1fc16b; /* Change icon color when checked */
        }

        /* Styles for Lucide icons within the table (plus, edit, delete) */
        .awp-btn.secondary i[data-lucide] {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 5px;
        }

        .awp-summary-actions .awp-btn.edit-plain,
        .awp-summary-actions .awp-btn.delete-plain {
            background: none;
            border: none;
            color: #1fc16b;
            cursor: pointer;
            padding: 0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            transition: color 0.2s ease;
        }
        .awp-summary-actions .awp-btn.edit-plain:hover,
        .awp-summary-actions .awp-btn.delete-plain:hover {
            color: #005177;
        }
        .awp-summary-actions .awp-btn.delete-plain {
            color: #dc3232;
            margin-left: 10px;
        }
        .awp-summary-actions .awp-btn.delete-plain:hover {
            color: #a00;
        }
        .awp-summary-actions .awp-btn i[data-lucide] {
            width: 16px;
            height: 16px;
            vertical-align: middle;
        }
        .awp-fields-table th, .awp-fields-table td {
            vertical-align: middle;
            padding: 10px 8px;
        }
        .awp-fields-table th:first-child, .awp-fields-table td:first-child {
            width: 40px;
            text-align: center;
        }
        .awp-fields-table .awp-drag-handle {
            color: #999;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%; /* Make handle take full height of cell */
        }
        .awp-fields-table .awp-drag-handle i {
            width: 18px;
            height: 18px;
        }
    `;
    $('head').append(`<style type="text/css">${modalCss}</style>`);


    //-----------------------------------------------------
    // 1) CORE INITIALIZATION FUNCTIONS
    //-----------------------------------------------------

    // Initialize Color Picker
    function initColorPicker() {
        $('.awp-color-field').wpColorPicker();
        $('.awp-color-picker').wpColorPicker();
    }

    // Initialize EmojioneArea
    function initEmojionearea() {
        const emojiSelectors = [
            '#awp_otp_message_whatsapp',
            '#awp_signup_otp_message',
            '#awp_otp_message_template'
        ];

        emojiSelectors.forEach(selector => {
            if ($(selector).length) {
                $(selector).emojioneArea({
                    pickerPosition: "bottom"
                });
            }
        });
    }

    // Create a generic placeholder dropdown
    function createPlaceholderDropdown(placeholdersObj, promptText) {
        let dropdownHTML = `<select class="placeholder-dropdown">
            <option value="" disabled selected>${promptText}</option>`;
        Object.entries(placeholdersObj).forEach(([token, label]) => {
            dropdownHTML += `<option value="${token}">${label}</option>`;
        });
        dropdownHTML += `</select>`;
        return dropdownHTML;
    }

    function initializePlaceholderDropdown(containerClass, placeholdersObj, promptText) {
        $(containerClass).each(function() {
            const dropdownHTML = createPlaceholderDropdown(placeholdersObj, promptText);
            $(this).html(dropdownHTML);
        });

        $(containerClass).on('change', '.placeholder-dropdown', function() {
            const placeholder = $(this).val();
            if (!placeholder) return;

            let $closestTextarea;

            if (containerClass === '.placeholder-container-signup') {
                $closestTextarea = $(this)
                    .closest('td, .form-table, .awp-otp-settings-section')
                    .find('textarea#awp_signup_otp_message')
                    .first();
            } else if (containerClass === '.placeholder-container') {
                $closestTextarea = $(this)
                    .closest('td, .form-table, .awp-otp-settings-section')
                    .find('textarea#awp_otp_message_whatsapp')
                    .first();
            } else if (containerClass === '.placeholder-container-checkout') {
                $closestTextarea = $(this)
                    .closest('td, .form-table, .awp-otp-settings-section')
                    .find('textarea#awp_otp_message_template')
                    .first();
            }

            if ($closestTextarea && $closestTextarea.length) {
                let instance = $closestTextarea.data("emojioneArea");
                if (instance) {
                    let currentText = instance.getText();
                    instance.setText(currentText + " " + placeholder);
                } else {
                    let oldVal = $closestTextarea.val();
                    $closestTextarea.val(oldVal + " " + placeholder);
                }
            }

            $(this).val('');
        });
    }


    // Handle Redirection Rules
    function handleRedirectionRules() {
        $('#awp_add_redirect_rule').on('click', function(e) {
            e.preventDefault();
            const ruleCount = $('#awp_redirect_rules_container .awp_redirect_rule').length;
            const allRoles = awpOtpAdminAjax.all_roles;
            let roleOptions = `<option value="all">${awpOtpAdminAjax.strings.all_roles}</option>`;
            $.each(allRoles, (key, role) => {
                roleOptions += `<option value="${key}">${role.name}</option>`;
            });
            const newRule = `
                <div class="awp_redirect_rule">
                    <div class="rule-fields">
                        <select name="awp_otp_settings[redirect_rules][${ruleCount}][role]" class="awp_redirect_role">
                            ${roleOptions}
                        </select>
                        <input type="url" name="awp_otp_settings[redirect_rules][${ruleCount}][redirect_url]"
                               class="awp_redirect_url"
                               placeholder="${awpOtpAdminAjax.strings.enter_redirect_url}" required />
                    </div>
                    <button type="button" class="awp_remove_rule">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            `;
            $('#awp_redirect_rules_container').append(newRule);
            preventDuplicateRoles();
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        });

        $(document).on('click', '.awp_remove_rule', function(e) {
            e.preventDefault();
            $(this).closest('.awp_redirect_rule').remove();
            updateRedirectRules();
            preventDuplicateRoles();
        });
    }

    function updateRedirectRules() {
        $('#awp_redirect_rules_container .awp_redirect_rule').each((index, rule) => {
            $(rule).find('.awp_redirect_role').attr('name', `awp_otp_settings[redirect_rules][${index}][role]`);
            $(rule).find('.awp_redirect_url').attr('name', `awp_otp_settings[redirect_rules][${index}][redirect_url]`);
        });
    }

    function preventDuplicateRoles() {
        const selectedRoles = [];
        let allRolesSelected = false;
        $('.awp_redirect_role').each(function() {
            const val = $(this).val();
            if (val === 'all') {
                allRolesSelected = true;
            } else {
                selectedRoles.push(val);
            }
        });
        $('.awp_redirect_role').each(function() {
            const currentVal = $(this).val();
            $(this).find('option').each(function() {
                const optionVal = $(this).val();
                if (optionVal !== 'all' && optionVal !== currentVal && selectedRoles.includes(optionVal)) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
                if (optionVal === 'all') {
                    if (allRolesSelected && optionVal !== currentVal) {
                        $(this).prop('disabled', true);
                    } else {
                        $(this).prop('disabled', false);
                    }
                }
            });
        });
    }

    function handleSignupLogoUpload() {
        let mediaUploader;
        $('#upload_logo_button').on('click', function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Logo',
                button: { text: 'Choose Logo' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                if (attachment.url) {
                    $('#signup_logo').val(attachment.url);
                    $('#signup_logo_preview')
                        .attr('src', attachment.url)
                        .on('error', function() {
                            $(this).attr('src', awpOtpAdminAjax.default_logo_url);
                            $('#signup_logo').val('');
                            $('#remove_logo_button').hide();
                            alert(awpOtpAdminAjax.strings.failedToLoadLogo);
                        });
                    $('#remove_logo_button').show();
                }
            });
            mediaUploader.open();
        });

        $('#remove_logo_button').on('click', function(e) {
            e.preventDefault();
            $('#signup_logo').val('');
            $('#signup_logo_preview').attr('src', awpOtpAdminAjax.default_logo_url);
            $(this).hide();
        });
    }

    // Handle Logo Upload
    function handleLogoUpload() {
        let mediaUploader;
        $('#awp_upload_logo_button').on('click', function(e) {
            e.preventDefault();
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Logo',
                button: { text: 'Choose Logo' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                if (attachment.url) {
                    $('#awp_otp_logo').val(attachment.url);
                    $('#awp_logo_preview').attr('src', attachment.url).on('error', function() {
                        $(this).attr('src', awpOtpAdminAjax.default_logo_url);
                        $('#awp_otp_logo').val('');
                        $('.awp_remove_logo_button').hide();
                        alert(awpOtpAdminAjax.strings.failedToLoadLogo);
                    });
                    $('.awp_remove_logo_button').show();
                }
            });
            mediaUploader.open();
        });

        $('.awp_remove_logo_button').on('click', function(e) {
            e.preventDefault();
            $('#awp_otp_logo').val('');
            $('#awp_logo_preview').attr('src', awpOtpAdminAjax.default_logo_url);
            $(this).hide();
        });
    }

    // Initialize Radio Buttons
    function initRadioButtons() {
        $('.awp-login-method-radio .awp-radio-button').on('click', function() {
            $('.awp-login-method-radio .awp-radio-button').removeClass('active');
            $(this).addClass('active');
            $(this).find('input[type="radio"]').prop('checked', true);
            toggleOtpMessageFields();
        });

        $('.awp-login-method-radio .awp-radio-button input[type="radio"]:checked')
            .parent().addClass('active');
    }


    // Initialize Toggle Switches (used for custom field modal and main settings)
    function initToggleSwitches() {
        // This targets checkboxes within the 'awp-switch' label in main settings (like the enable OTP toggle)
        $('.awp-switch input[type="checkbox"]').each(function() {
            const $switch = $(this);
            const $label = $switch.closest('label');
            $switch.on('change', function() {
                $label.toggleClass('checked', $switch.is(':checked'));
            });
            // Initial state
            $label.toggleClass('checked', $switch.is(':checked'));
        });

        // This targets the new toggle-switch checkboxes in the modal (.awp-toggle-switch-group)
        $('.awp-toggle-switch-group .awp-toggle-checkbox').each(function() {
            const $checkbox = $(this);
            const $label = $checkbox.closest('.awp-switch-label');
            $checkbox.on('change', function() {
                $label.toggleClass('checked', $checkbox.is(':checked'));
                // Update Lucide icon color based on checked state
                if (typeof lucide !== 'undefined' && lucide.createIcons) {
                    lucide.createIcons();
                }
            });
            // Initial state
            $label.toggleClass('checked', $checkbox.is(':checked'));
        });
    }

    // =============================
    // DRAG-AND-DROP FOR SIGNUP FIELDS
    // =============================
    function initFieldSorting() {
        const $sortableTable = $('#awp-fields-sortable tbody');
        if (!$sortableTable.length) return;

        $sortableTable.sortable({
            handle: '.awp-drag-handle',
            update: function(event, ui) {
                updateFieldOrderHiddenInput();
            }
        });
    }

    // Initialize CodeMirror for a specific textarea by ID
    let codeMirrorEditors = {};

    function initCodeMirrorForTextarea(textareaId) {
        const el = document.getElementById(textareaId);
        if (!el) return;

        if (codeMirrorEditors[textareaId]) {
            codeMirrorEditors[textareaId].refresh();
            return;
        }

        if (typeof wp !== 'undefined' && wp.codeEditor && wp.codeEditor.initialize) {
            let editorSettings = wp.codeEditor.defaultSettings ?
                jQuery.extend(true, {}, wp.codeEditor.defaultSettings) :
                {};
            editorSettings.codemirror = jQuery.extend(true, editorSettings.codemirror || {}, {
                mode: 'css',
                lineNumbers: true,
                theme: 'default',
                lineWrapping: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                extraKeys: { 'Ctrl-Space': 'autocomplete' }
            });
            const editor = wp.codeEditor.initialize(el, editorSettings);
            codeMirrorEditors[textareaId] = editor.codemirror;
            $(editor.codemirror.getWrapperElement()).css('display', $(el).is(':visible') ? 'block' : 'none');
        } else if (typeof CodeMirror !== 'undefined') {
            const editor = CodeMirror.fromTextArea(el, {
                mode: 'css',
                lineNumbers: true,
                theme: 'default',
                lineWrapping: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                extraKeys: { 'Ctrl-Space': 'Ctrl-Space' }
            });
            editor.setSize('100%', '300px');
            editor.on('change', function(instance) {
                el.value = instance.getValue();
            });
            codeMirrorEditors[textareaId] = editor;
            $(editor.getWrapperElement()).css('display', $(el).is(':visible') ? 'block' : 'none');
        }
    }

    window.refreshVisibleCodeEditors = function() {
        const textareas = ['awp_custom_css', 'signup_custom_css'];
        textareas.forEach(textareaId => {
            const el = document.getElementById(textareaId);
            if (!el) return;

            const $parentTabContent = $(el).closest('.wawp-tab-content');
            const editorInstance = codeMirrorEditors[textareaId];

            if ($parentTabContent.is(':visible') && editorInstance) {
                $(editorInstance.getWrapperElement()).css('display', 'block');
                editorInstance.refresh();
            } else if (editorInstance) {
                $(editorInstance.getWrapperElement()).css('display', 'none');
            }
        });
    };

    function toggleOtpMessageFields() {
        const selectedMethod = $('input[name="awp_signup_settings[otp_method]"]:checked').val();

        if (selectedMethod === 'whatsapp') {
            $('#otp_message_whatsapp_container').show();
            $('#otp_message_email_container').hide();
        } else if (selectedMethod === 'email') {
            $('#otp_message_email_container').show();
            $('#otp_message_whatsapp_container').hide();
        }
    }

    function initPasswordStrengthChecker() {
        $('#awp_password').on('input', function() {
            let strength = 0;
            const password = $(this).val();
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            const meter = $('#password-strength-meter');
            const text = $('#password-strength-text');
            meter.val(strength * 20);

            switch (strength) {
                case 0:
                case 1:
                    meter.css('background-color', '#ff4d4d');
                    text.text('Very Weak').css('color', '#ff4d4d');
                    break;
                case 2:
                    meter.css('background-color', '#ff944d');
                    text.text('Weak').css('color', '#ff944d');
                    break;
                case 3:
                    meter.css('background-color', '#ffcc00');
                    text.text('Moderate').css('color', '#ffcc00');
                    break;
                case 4:
                case 5:
                    meter.css('background-color', '#00b300');
                    text.text('Strong').css('color', '#00b300');
                    break;
            }

            $('#password-requirements li').each(function() {
                const requirementText = $(this).text();
                let passed = false;
                if (requirementText.includes('Upper case letter')) {
                    passed = /[A-Z]/.test(password);
                } else if (requirementText.includes('Lower case letter')) {
                    passed = /[a-z]/.test(password);
                } else if (requirementText.includes('Numbers')) {
                    passed = /[0-9]/.test(password);
                } else if (requirementText.includes('At least 8 characters')) {
                    passed = password.length >= 8;
                } else if (requirementText.includes('Special characters')) {
                    passed = /[^A-Za-z0-9]/.test(password);
                }

                const $icon = $(this).find('.awp-check');
                if (passed) {
                    $icon.html('<i data-lucide="check"></i>').removeClass('text-danger').addClass('text-success');
                } else {
                    $icon.html('<i data-lucide="x"></i>').removeClass('text-success').addClass('text-danger');
                }
            });
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        });

        $('.awp-toggle-password').click(function() {
            const passwordInput = $(this).siblings('input[type="password"], input[type="text"]');
            const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
            passwordInput.attr('type', type);
            $(this).find('i').attr('data-lucide', type === 'password' ? 'eye' : 'eye-off');
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        });
    }

    function initEditFeature() {}

    //-----------------------------------------------------
    // 2) CUSTOM FIELD MANAGEMENT FUNCTIONS
    //-----------------------------------------------------

    let customFields = awpOtpAdminAjax.custom_fields_config || {};

    function updateFieldOrderHiddenInput() {
        let sortedKeys = [];
        $('#awp-fields-sortable tbody tr').each(function() {
            const fieldKey = $(this).attr('data-field-key');
            if (fieldKey) {
                sortedKeys.push(fieldKey);
            }
        });
        $('#awp_field_order').val(sortedKeys.join(','));
    }

    // Function to get Lucide icon for a given field type (same as PHP)
    function getLucideIconForFieldType(type) {
        switch (type) {
            case 'text':
                return 'text';
            case 'textarea':
                return 'align-left';
            case 'email':
                return 'mail';
            case 'number':
                return 'hash';
            case 'checkbox':
                return 'check-square';
            case 'radio':
                return 'circle-dot';
            default:
                return 'circle';
        }
    }

    function renderFieldRow(fieldKey, fieldData, isStandard) {
        const fieldLabel = fieldData.label || fieldKey;
        const fieldType = fieldData.type || 'text';
        const enabledChecked = fieldData.enabled ? 'checked' : '';
        const requiredChecked = fieldData.required ? 'checked' : '';

        const enabledName = isStandard ? `awp_signup_settings[${fieldKey}][enabled]` : `awp_signup_settings[custom_fields][${fieldKey}][enabled]`;
        const requiredName = isStandard ? `awp_signup_settings[${fieldKey}][required]` : `awp_signup_settings[custom_fields][${fieldKey}][required]`;

        let rowHtml = `<tr data-field-key="${fieldKey}" data-field-type="${fieldType}" data-is-standard="${isStandard ? '1' : '0'}">
            <td class="awp-drag-handle" style="cursor: move; text-align: center;"><i data-lucide="grip-vertical"></i></td>
            <td>${fieldLabel}</td>
            <td><label class="awp-switch"><input type="checkbox" name="${enabledName}" value="1" ${enabledChecked} /><span class="awp-slider"></span></label></td>
            <td><label class="awp-switch"><input type="checkbox" name="${requiredName}" value="1" ${requiredChecked} /><span class="awp-slider"></span></label></td>
            <td>`;
        if (!isStandard) {
            rowHtml += `<div class="awp-summary-actions">`;
            rowHtml += `<button type="button" class="awp-btn edit-plain awp-edit-custom-field" data-field-key="${fieldKey}"><i data-lucide="edit"></i>${awpOtpAdminAjax.strings.edit}</button>`;
            rowHtml += `<button type="button" class="awp-btn delete-plain awp-delete-custom-field" data-field-key="${fieldKey}"><i data-lucide="trash-2"></i>${awpOtpAdminAjax.strings.delete}</button>`;
            rowHtml += `</div>`;
            rowHtml += `<input type="hidden" name="awp_signup_settings[custom_fields][${fieldKey}][label]" value="${fieldLabel}" />`;
            rowHtml += `<input type="hidden" name="awp_signup_settings[custom_fields][${fieldKey}][type]" value="${fieldType}" />`;
            if (['checkbox', 'radio'].includes(fieldType) && fieldData.options) {
                const optionsString = fieldData.options.map(opt => `${opt.label}|${opt.value}`).join('\n');
                rowHtml += `<textarea name="awp_signup_settings[custom_fields][${fieldKey}][options]" style="display:none;">${optionsString}</textarea>`;
            }
        } else {
            rowHtml += `${awpOtpAdminAjax.strings.primaryKey}`;
        }
        rowHtml += `</td></tr>`;
        return rowHtml;
    }

    // Function to handle the custom select box behavior
    function initCustomSelect() {
        const $select = $('#awp_modal_field_type');
        const $wrapper = $select.closest('.awp-custom-select-wrapper');
        const $display = $wrapper.find('.awp-select-display');
        const $selectedIcon = $display.find('.awp-selected-icon');
        const $selectedText = $display.find('.awp-selected-text');
        const $optionsList = $wrapper.find('.awp-select-options');

        // Function to update the custom display based on the actual select's value
        function updateCustomSelectDisplay() {
            const selectedOption = $select.find('option:selected');
            const selectedIconData = selectedOption.data('icon');
            const selectedTextContent = selectedOption.text();

            $selectedIcon.html(`<i data-lucide="${selectedIconData}"></i>`);
            $selectedText.text(selectedTextContent);

            // Update selected class in custom list
            $optionsList.find('li').removeClass('selected');
            $optionsList.find(`li[data-value="${selectedOption.val()}"]`).addClass('selected');

            // Re-render Lucide icons for the newly updated display
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }

        // Initialize display on load
        updateCustomSelectDisplay();

        // Toggle options list on click of the custom display
        $display.on('click', function(e) {
            e.stopPropagation(); // Stop propagation for the display click
            const wasActive = $wrapper.hasClass('active');

            // Close all other open custom selects before opening this one
            $('.awp-custom-select-wrapper.active').not($wrapper).each(function() {
                $(this).removeClass('active').find('.awp-select-options').slideUp(150);
                $(this).find('.awp-select-display').attr('aria-expanded', false);
            });

            if (!wasActive) { // Only open if it's not already active
                $wrapper.addClass('active');
                $optionsList.slideDown(150, function() {
                    // Ensure icons are rendered in the dropdown list when opened
                    if (typeof lucide !== 'undefined' && lucide.createIcons) {
                        lucide.createIcons();
                    }
                    // Ensure the selected option is scrolled into view
                    const $selectedLi = $optionsList.find('li.selected');
                    if ($selectedLi.length) {
                        const offsetTop = $selectedLi[0].offsetTop;
                        const listHeight = $optionsList.height();
                        const itemHeight = $selectedLi.outerHeight();
                        $optionsList.scrollTop(offsetTop - (listHeight / 2) + (itemHeight / 2));
                    }
                });
                $(this).attr('aria-expanded', true);
                openingCustomSelect = true; // Set flag when opening
                setTimeout(() => { // Reset flag after a short delay
                    openingCustomSelect = false;
                }, 100); // Adjust delay if needed
            } else { // If it was already active, close it
                $wrapper.removeClass('active');
                $optionsList.slideUp(150);
                $(this).attr('aria-expanded', false);
            }
        });

        // Select an option from the custom list
        $optionsList.on('click', 'li', function(e) {
            e.stopPropagation(); // Stop propagation for list item click
            const $li = $(this);
            const value = $li.data('value');

            // Update the hidden select element's value
            $select.val(value);
            $select.trigger('change'); // Trigger change event on the original select

            // Close the options list
            $wrapper.removeClass('active');
            $optionsList.slideUp(150);
            $display.attr('aria-expanded', false).focus(); // Return focus to display for accessibility
        });



        // Keyboard navigation for accessibility on the custom select display
        $display.on('keydown', function(e) {
            const $currentNativeOption = $select.find('option:selected');
            let $currentCustomOption = $optionsList.find(`li[data-value="${$currentNativeOption.val()}"]`);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault(); // Prevent page scroll
                    if (!$wrapper.hasClass('active')) {
                        $display.trigger('click'); // Open the dropdown
                    } else {
                        const $next = $currentCustomOption.next('li');
                        if ($next.length) {
                            $next.trigger('click');
                        } else {
                            $optionsList.find('li:first').trigger('click'); // Loop to first
                        }
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault(); // Prevent page scroll
                    if (!$wrapper.hasClass('active')) {
                        $display.trigger('click'); // Open the dropdown
                    } else {
                        const $prev = $currentCustomOption.prev('li');
                        if ($prev.length) {
                            $prev.trigger('click');
                        } else {
                            $optionsList.find('li:last').trigger('click'); // Loop to last
                        }
                    }
                    break;
                case 'Enter':
                case ' ': // Space key
                    e.preventDefault(); // Prevent form submission or page scroll
                    if ($wrapper.hasClass('active')) {
                        $currentCustomOption.trigger('click'); // Select the currently highlighted option
                    } else {
                        $display.trigger('click'); // Open the dropdown
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    $wrapper.removeClass('active');
                    $optionsList.slideUp(150);
                    $display.attr('aria-expanded', false).focus();
                    break;
                case 'Tab':
                    if ($wrapper.hasClass('active')) {
                        $wrapper.removeClass('active');
                        $optionsList.slideUp(150);
                        $display.attr('aria-expanded', false);
                    }
                    break;
            }
        });

        // Trigger custom display update when the native select's value changes
        // This handles programmatic changes (e.g., from Edit button) as well as internal JS updates
        $select.on('change', updateCustomSelectDisplay);
    }

    // Handle "Add New Field" button click
    $('#awp-add-custom-field-button').on('click', function() {
        $('#awp-custom-field-modal').css('display', 'flex');

        $('#awp_modal_field_key').val('');
        $('#awp_modal_is_editing').val('0');
        $('#awp-custom-field-form')[0].reset();
        $('#awp-modal-options-group').hide();
        $('#awp_modal_field_id').prop('readonly', false);
        $('.awp-error-message').text('').hide();

        // Reset custom select display to default "Text Input" and trigger its change
        const $select = $('#awp_modal_field_type');
        $select.val('text').trigger('change'); // Trigger change for custom select to update display
        // Ensure switch buttons are initialized correctly for a new field (unchecked by default)
        $('#awp_modal_field_enabled').prop('checked', false).closest('.awp-switch-label').removeClass('checked');
        $('#awp_modal_field_required').prop('checked', false).closest('.awp-switch-label').removeClass('checked');

        // Re-render Lucide icons for the modal contents
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    });

    // Handle "Edit" button click for custom fields
    $(document).on('click', '.awp-edit-custom-field', function() {
        const fieldKey = $(this).data('field-key');
        const fieldData = customFields[fieldKey];

        if (fieldData) {
            $('#awp-custom-field-modal').css('display', 'flex');

            $('#awp_modal_field_key').val(fieldKey);
            $('#awp_modal_is_editing').val('1');

            $('#awp_modal_field_id').val(fieldKey).prop('readonly', true);
            $('#awp_modal_field_label').val(fieldData.label);

            // Set the value of the actual select and trigger change to update custom display
            $('#awp_modal_field_type').val(fieldData.type).trigger('change');

            // Set the state of the switch buttons based on fieldData
            $('#awp_modal_field_enabled').prop('checked', fieldData.enabled);
            $('#awp_modal_field_required').prop('checked', fieldData.required);
            // Manually update the switch label classes
            $('#awp_modal_field_enabled').closest('.awp-switch-label').toggleClass('checked', fieldData.enabled);
            $('#awp_modal_field_required').closest('.awp-switch-label').toggleClass('checked', fieldData.required);


            if (['checkbox', 'radio'].includes(fieldData.type)) {
                $('#awp-modal-options-group').show();
                const optionsText = fieldData.options.map(opt => `${opt.label}|${opt.value}`).join('\n');
                $('#awp_modal_field_options').val(optionsText);
            } else {
                $('#awp-modal-options-group').hide();
                $('#awp_modal_field_options').val('');
            }
            $('.awp-error-message').text('').hide();

            // Re-render Lucide icons inside the modal after populating
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }
    });

    // Handle "Delete" button click for custom fields
    $(document).on('click', '.awp-delete-custom-field', function() {
        if (confirm(awpOtpAdminAjax.strings.confirmDeleteField)) {
            const fieldKeyToDelete = $(this).data('field-key');
            $(`tr[data-field-key="${fieldKeyToDelete}"]`).remove();
            delete customFields[fieldKeyToDelete];
            updateFieldOrderHiddenInput();
        }
    });

    // Modal field type change (now only handles options group, custom select handles display)
    $('#awp_modal_field_type').on('change', function() {
        const selectedType = $(this).val();
        if (['checkbox', 'radio'].includes(selectedType)) {
            $('#awp-modal-options-group').slideDown();
        } else {
            $('#awp-modal-options-group').slideUp();
            $('#awp_modal_field_options').val('');
        }
    });


    // Handle modal form submission (Save Field)
    $('#awp-custom-field-form').on('submit', function(e) {
        e.preventDefault();
        $('.awp-error-message').text('').hide();

        let fieldKey = $('#awp_modal_field_id').val().trim();
        const isEditing = $('#awp_modal_is_editing').val() === '1';
        const fieldLabel = $('#awp_modal_field_label').val().trim();
        const fieldType = $('#awp_modal_field_type').val(); // Get value from actual select
        const fieldEnabled = $('#awp_modal_field_enabled').is(':checked') ? 1 : 0;
        const fieldRequired = $('#awp_modal_field_required').is(':checked') ? 1 : 0;
        const fieldOptionsRaw = $('#awp_modal_field_options').val().trim();
        let fieldOptions = [];
        let hasError = false;

        if (!fieldLabel) {
            $('#awp_modal_field_label_error').text(awpOtpAdminAjax.strings.fieldRequired).show();
            hasError = true;
        }

        if (!isEditing) {
            if (!fieldKey) {
                $('#awp_modal_field_id_error').text(awpOtpAdminAjax.strings.fieldRequired).show();
                hasError = true;
            } else if (!/^[a-z0-9_]+$/.test(fieldKey)) {
                $('#awp_modal_field_id_error').text(awpOtpAdminAjax.strings.invalidMetaKey).show();
                hasError = true;
            } else if (customFields.hasOwnProperty(fieldKey)) {
                $('#awp_modal_field_id_error').text(awpOtpAdminAjax.strings.duplicateMetaKey).show();
                hasError = true;
            }
            const standardFieldKeys = ['first_name', 'last_name', 'email', 'phone', 'password'];
            if (standardFieldKeys.includes(fieldKey)) {
                $('#awp_modal_field_id_error').text(awpOtpAdminAjax.strings.metaKeyConflictStandard).show();
                hasError = true;
            }
        }


        if (['checkbox', 'radio'].includes(fieldType)) {
            if (!fieldOptionsRaw) {
                $('#awp_modal_field_options_error').text(awpOtpAdminAjax.strings.optionsRequired).show();
                hasError = true;
            } else {
                fieldOptions = fieldOptionsRaw.split('\n').map(line => {
                    const parts = line.trim().split('|', 2);
                    const label = parts[0].trim();
                    const value = parts[1] ? parts[1].trim() : label;
                    return { label, value };
                }).filter(opt => opt.label);
                if (fieldOptions.length === 0) {
                    $('#awp_modal_field_options_error').text(awpOtpAdminAjax.strings.optionsRequired).show();
                    hasError = true;
                }
            }
        }

        if (hasError) {
            return;
        }

        const newFieldData = {
            label: fieldLabel,
            type: fieldType,
            enabled: fieldEnabled,
            required: fieldRequired,
            options: fieldOptions
        };

        if (isEditing) {
            customFields[fieldKey] = { ...customFields[fieldKey],
                ...newFieldData
            };
            const $row = $(`#awp-fields-sortable tbody tr[data-field-key="${fieldKey}"]`);
            $row.replaceWith(renderFieldRow(fieldKey, customFields[fieldKey], false));
        } else {
            const newFieldKey = fieldKey;
            customFields[newFieldKey] = newFieldData;
            $('#awp-fields-sortable tbody').append(renderFieldRow(newFieldKey, newFieldData, false));
            updateFieldOrderHiddenInput();
        }

        $('#awp-custom-field-modal').hide();
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }
    });

    // Handle modal close button
    $('#awp-custom-field-modal .close-button').on('click', function() {
        $('#awp-custom-field-modal').hide();
    });

    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).is('#awp-custom-field-modal')) {
            $('#awp-custom-field-modal').hide();
        }
    });


    //-----------------------------------------------------
    // 3) MAIN INIT (runs once on document ready)
    //-----------------------------------------------------

    function mainInit() {
        // Core initializations
        initColorPicker();
        initEmojionearea();
        initRadioButtons();
        toggleOtpMessageFields();
        initToggleSwitches(); // This now handles both types of switches

        // Initialize Lucide icons on page load for static elements
        if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
        }

        initCustomSelect(); // Initialize the new custom select behavior

        // Initialize CodeMirror for ALL potential textareas regardless of current tab visibility
        initCodeMirrorForTextarea('awp_custom_css');
        initCodeMirrorForTextarea('signup_custom_css');

        handleRedirectionRules();
        handleLogoUpload();
        handleSignupLogoUpload();

        // Made globally accessible for tab switching logic
        window.updateWawpOtpHeader = function(tabSelector) {
                var $tabLink = $(".nav-tab").filter(function(){
        try {
            return new URL(this.href).searchParams.get("tab") === tabSelector;
        } catch(e){
            return false;
       }
    });
            var newTitle = $tabLink.data("title") || "Wawp OTP Settings"; // Fallback text
            var newDesc = $tabLink.data("description") || "Select a tab to manage its settings."; // Fallback text
            var newShortcode = $tabLink.data("shortcode") || "Shortcode will appear here"; // Fallback text
            $("#wawp-otp-title").text(newTitle);
            $("#wawp-otp-description").text(newDesc);
            $("#wawp-otp-shortcode").text(newShortcode);
        };


        // Existing tab logic
           var lastHref = localStorage.getItem("wawpOtpActiveTab");
   if (lastHref) {
       try {
           var lastId = new URL(lastHref).searchParams.get("tab");
           if (lastId && $("#" + lastId).length) {
               $(".nav-tab-wrapper a").removeClass("nav-tab-active");
               $(".wawp-tab-content").hide();
               // highlight the same <a> by matching its href
               $(".nav-tab-wrapper a").filter(function(){ return this.href === lastHref; })
                                      .addClass("nav-tab-active");
               // show the content pane
               $("#" + lastId).show();
               window.updateWawpOtpHeader(lastId);
               window.refreshVisibleCodeEditors();
           }
       } catch(e) {
           console.warn("Invalid stored tab URL:", lastHref);
       }
   }

       $(".nav-tab-wrapper a.nav-tab").on("click", function(e) {
       e.preventDefault();
       var href = this.href;
       var tabId = new URL(href).searchParams.get("tab");
       if (!tabId) return;

       $(".nav-tab-wrapper a").removeClass("nav-tab-active");
       $(".wawp-tab-content").hide();
       $(this).addClass("nav-tab-active");

       // show the matching pane
       $("#" + tabId).show();
       // update the header & shortcode area
       window.updateWawpOtpHeader(tabId);

       // remember for next time
       localStorage.setItem("wawpOtpActiveTab", href);
       window.refreshVisibleCodeEditors();
   });

        $("#copy-shortcode").on("click", function() {
            var shortcodeText = $("#wawp-otp-shortcode").text();
            var tempInput = $("<input>");
            $("body").append(tempInput);
            tempInput.val(shortcodeText).select();
            document.execCommand("copy");
            tempInput.remove();
            $(this).text(awpOtpAdminAjax.strings.copied || 'Copied!');
            var that = $(this);
            setTimeout(function() {
                that.text(awpOtpAdminAjax.strings.copy || 'Copy');
            }, 2000);
        });

        initPasswordStrengthChecker();
        initEditFeature();

        const placeholders = {
            '{{otp}}': 'OTP Code',
            '{{user_name}}': 'Username',
            '{{user_first_last_name}}': 'User Full Name',
            '{{user_email}}': 'User Email',
            '{{wc_billing_phone}}': 'Phone Number',
            '{{shop_name}}': 'Shop Name',
            '{{current_date_time}}': 'Current Date & Time',
            '{{site_link}}': 'Website URL'
        };
        const checkoutPlaceholders = {
            '{{name}}': 'Customer’s First Name',
            '{{last}}': 'Customer’s Last Name',
            '{{otp}}': 'Generated OTP Code',
            '{{email}}': 'Customer’s Email'
        };
        const signupWhatsAppPlaceholders = {
            '{{otp}}': 'Generated OTP Code',
            '{{name}}': 'User’s First Name',
            '{{last}}': 'User’s Last Name',
            '{{email}}': 'User’s Email'
        };

        initializePlaceholderDropdown('.placeholder-container', placeholders, '');
        initializePlaceholderDropdown('.placeholder-container-checkout', checkoutPlaceholders, '');
        initializePlaceholderDropdown('.placeholder-container-signup', signupWhatsAppPlaceholders, '');

        initFieldSorting();
    }

    mainInit();
});