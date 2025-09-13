jQuery(document).ready(function($) {
    const settingsForm  = $('#wawp-notif-settings-form');
    const addLangPopup  = $('#wawp-notif-add-lang-popup');
    const editTplPopup  = $('#wawp-notif-edit-template-popup');
    const iconBaseUrl   = wawpNotifData.iconBaseUrl; // from wp_localize_script
    const langs = getConfiguredLanguages(); 

    function toggleLangTabsVisibility () {
        const $tabWrapper = $('.wawp-notif-settings-page .nav-tab-wrapper');
        if (!$tabWrapper.length) return;
    
        const tabCount = $tabWrapper.find('.nav-tab').length;
    
        if (tabCount <= 1) {
            /* 1) hide the tabs */
            $tabWrapper.hide();
    
            /* 2) change each header block */
            $('.wawp-notif-header-text').each(function () {
                const $h3 = $(this).children('h3');
                const $p  = $(this).children('p');
    
                /* “Notification Rules for …”  →  “Setup Notification Passed Trigger” */
                if ($h3.text().trim().indexOf('Notification Rules for') === 0) {
                    $h3.text('Setup Notification Passed Trigger');
                }
    
                /* “Language Code: …”  →  descriptive sentence */
                if ($p.text().trim().indexOf('Language Code:') === 0) {
                    $p.text('Define how and when your notifications are sent.');
                }
            });
        } else {
            /* show the tab bar again when more than one language exists */
            $tabWrapper.show();
        }
    }


    // For Edit Template Popup
    let currentPopupRuleId = null; 
    let currentPopupStep = 0;      // The actual step number (1-4) being displayed
    let activePopupSteps = [];     // Array of active step numbers for the current rule (e.g., [1, 2])
    
    
      function getTinymceContent(editorId) {
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && tinymce.get(editorId).isDirty()) {
            return tinymce.get(editorId).getContent();
        }
        return $('#' + editorId).val(); // Fallback to textarea value
    }
    
    
    
    $(document).on('click', '.add-rule-button', function(e) {
    e.preventDefault();
    const lang = $(this).data('lang');
    if (lang) {
        // This is a new hidden input we will add in the next step
        $('#wawp_notif_add_notification_rule_lang').val(lang);
        settingsForm[0].submit();
    }
});


/******************************************************************
 *  Two small helper maps
 ******************************************************************/
function mapToSenderType(recipient, channel) {
    const r = recipient, c = channel;
    if (r === 'user'  && c === 'whatsapp') return 'user_whatsapp';
    if (r === 'user'  && c === 'email')    return 'user_email';
    if (r === 'user'  && c === 'both')     return 'user_both';
    if (r === 'admin' && c === 'whatsapp') return 'admin_whatsapp';
    if (r === 'admin' && c === 'email')    return 'admin_email';
    if (r === 'admin' && c === 'both')     return 'admin_both';
    if (r === 'both'  && c === 'whatsapp') return 'user_admin_whatsapp';
    if (r === 'both'  && c === 'email')    return 'user_admin_email';
    /* default → everything */
    return 'user_admin_both';
}

function splitSenderType(st) {
    switch (st) {
        case 'user_whatsapp'      : return ['user',  'whatsapp'];
        case 'user_email'         : return ['user',  'email'];
        case 'user_both'          : return ['user',  'both'];
        case 'admin_whatsapp'     : return ['admin', 'whatsapp'];
        case 'admin_email'        : return ['admin', 'email'];
        case 'admin_both'         : return ['admin', 'both'];
        case 'user_admin_whatsapp': return ['both',  'whatsapp'];
        case 'user_admin_email'   : return ['both',  'email'];
        default                   : return ['both',  'both'];
    }
}

/******************************************************************
 *  Initialise the new selects for every rule card
 ******************************************************************/
function initRecipientChannel(ruleId) {

    const $hidden = $('#sender_type_' + ruleId)      // the old field
                       .hide();                      // keep but hide
    if (!$hidden.length) return;

    const [recInit, chanInit] = splitSenderType($hidden.val());

    $('#send_recipient_' + ruleId).val(recInit);
    $('#send_channel_'   + ruleId).val(chanInit);

    $('#send_recipient_' + ruleId + ', #send_channel_' + ruleId)
        .on('change', function () {
            const rec = $('#send_recipient_' + ruleId).val();
            const ch  = $('#send_channel_'  + ruleId).val();
            $hidden.val( mapToSenderType(rec, ch) ).trigger('change');
            refreshCardHeader(ruleId);
        });
}

/* existing rules on first load */
$('.wawp-rule-sender-dropdown').each(function () {
    initRecipientChannel($(this).data('rule-id'));
});

/* freshly added rules */
$(document).on('awpNotifRuleAdded', function (e, ruleId) {
    initRecipientChannel(ruleId);
});


/*****************************************************************
 *  Enhance “Send to”  &  “Send by”  selects with Select2 + icons
 *****************************************************************/
function beautifyRecipientChannel(ruleId){

  ['send_recipient_','send_channel_'].forEach(prefix=>{
      const $sel = $('#'+prefix+ruleId);
      if(!$sel.length || $sel.hasClass('select2-hidden-accessible') || typeof $.fn.select2 === 'undefined'){return;}

      $sel.select2({
        minimumResultsForSearch: Infinity,
        width: 'resolve',
        dropdownParent:$sel.closest('td'),
        templateResult : formatOpt,
        templateSelection: formatOpt
      });
$sel.next('.select2-container')
     .appendTo( $sel.closest('.wawp-send-row') );
      function formatOpt(state){
        if(!state.id){return state.text;}

        const v = state.id.toString();      // value of <option>
        let html = '';

        /* icons for the “recipient” list */
        if(prefix==='send_recipient_'){
            if(v==='user')  html+=`<i class="ri-user-line wawp-sendto-ico"></i>`;
            if(v==='admin') html+=`<i class="ri-group-line wawp-sendto-ico"></i>`;
            if(v==='both')  html+=`<i class="ri-group-line wawp-sendto-ico"></i>`;
        }

        /* icons for the “channel” list */
        if(prefix==='send_channel_'){
            if(v==='whatsapp') html+=`<i class="ri-whatsapp-line wawp-sendto-ico"></i>`;
            if(v==='email')    html+=`<i class="ri-mail-line wawp-sendto-ico"></i>`;
            if(v==='both')     html+=`<i class="ri-whatsapp-line wawp-sendto-ico"></i> <i class="ri-mail-line wawp-sendto-ico"></i>`;
        }

        return $('<span>').html(html + state.text);
      }
  });
}

/* already-existing rules */
$('.wawp-rule-sender-dropdown').each(function(){
     beautifyRecipientChannel($(this).data('rule-id'));
});

/* newly added rules */
$(document).on('awpNotifRuleAdded', function(e, ruleId){
     beautifyRecipientChannel(ruleId);
});


    function setTinymceContent(editorId, content) {
        if (typeof tinymce !== 'undefined') {
            let editor = tinymce.get(editorId);
            if (editor) {
                editor.setContent(content || ''); // Set empty string if content is null/undefined
            } else {
                // If editor not initialized, try to initialize it
                // Ensure the textarea is visible before initializing
                const $textarea = $('#' + editorId);
                if ($textarea.is(':visible') || $textarea.closest('.step.active').length) {
                    // Default teeny settings matching wp_editor
                    tinymce.init({
                        selector: '#' + editorId,
                        height: 200,
                        menubar: false,
                        teeny: true,
                        toolbar: 'bold italic underline | bullist numlist | link unlink | undo redo',
                        branding: false,
                        plugins: 'lists,link,paste,wordpress,wplink',
                        setup: function(ed) {
                            ed.on('init', function() {
                                ed.setContent(content || '');
                            });
                        }
                    });
                } else {
                     $textarea.val(content || ''); // Set on hidden textarea for later init
                }
            }
        } else {
            $('#' + editorId).val(content || ''); // Fallback if tinymce is not loaded
        }
    }


    /*─────────────────────────────────────────────────────────────────────────*/
    /* LANGUAGE TAB FUNCTIONALITY                                              */
    /*─────────────────────────────────────────────────────────────────────────*/
        function activateLangTab(tabLink) {
        const target = $(tabLink).attr('href');
        if (!target || target === '#') return;
        $('.wawp-notif-tabs .nav-tab').removeClass('nav-tab-active');
        $(tabLink).addClass('nav-tab-active');
        $('.wawp-notif-tab-content').removeClass('active').hide();
        $(target).addClass('active').show();
        $('#wawp_active_tab').val(target);
    }
    const initLangTab = $('#wawp_active_tab').val();
    const firstLangTab = initLangTab && $(`.nav-tab[href="${initLangTab}"]`).length ?
        $(`.nav-tab[href="${initLangTab}"]`) :
        $('.wawp-notif-tabs .nav-tab:first');
    if (firstLangTab.length) activateLangTab(firstLangTab);
    $('.wawp-notif-tabs').on('click', '.nav-tab', function(e) {
        e.preventDefault(); activateLangTab(this);
    });

    /* ADD LANGUAGE POPUP */
    $('#wawp-notif-add-lang-button-top, #wawp-notif-add-lang-button').on('click', function(e) {
        e.preventDefault(); addLangPopup.fadeIn();
    });
    $('#wawp-notif-popup-close, #wawp-notif-add-lang-popup').on('click', function(e) {
        if ($(e.target).is('#wawp-notif-popup-close') || $(e.target).is('#wawp-notif-add-lang-popup')) {
            e.preventDefault(); addLangPopup.fadeOut();
        }
    });
    $('#wawp-notif-add-lang-popup .wawp-notif-popup-inner').on('click', function(e) { e.stopPropagation(); });
    $('#wawp-notif-confirm-add-lang').on('click', function() {
        const langCode = $('#wawp-notif-select-language').val();
        if (!langCode) { alert('Please select a language.'); return; }
        $('#wawp_new_language_to_add').val(langCode);
        addLangPopup.fadeOut(); settingsForm[0].submit();
    });


    /*─────────────────────────────────────────────────────────────────────────*/
    /* ADD LANGUAGE POPUP                                                      */
    /*─────────────────────────────────────────────────────────────────────────*/
    $('#wawp-notif-add-lang-button-top, #wawp-notif-add-lang-button').on('click', function(e) {
        e.preventDefault();
        addLangPopup.fadeIn();
    });
    $('#wawp-notif-popup-close, #wawp-notif-add-lang-popup').on('click', function(e) {
        if ($(e.target).is('#wawp-notif-popup-close') || $(e.target).is('#wawp-notif-add-lang-popup')) {
            e.preventDefault();
            addLangPopup.fadeOut();
        }
    });
    $('#wawp-notif-add-lang-popup .wawp-notif-popup-inner').on('click', function(e) { // Prevent closing when clicking inside
        e.stopPropagation();
    });
    $('#wawp-notif-confirm-add-lang').on('click', function() {
        const langCode = $('#wawp-notif-select-language').val();
        if (!langCode) {
            alert('Please select a language from the dropdown.');
            return;
        }
        $('#wawp_new_language_to_add').val(langCode);
        addLangPopup.fadeOut();
        settingsForm[0].submit();
    });

    /*─────────────────────────────────────────────────────────────────────────*/
    /* REMOVE LANGUAGE BUTTON ON TABS                                          */
    /*─────────────────────────────────────────────────────────────────────────*/
    $(document).on('click', '.wawp-notif-remove-lang-button', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const lang      = $(this).data('lang');
        const mainLang  = $('#wawp_notif_main_language_code').val();
        const langName = $(this).closest('.wawp-notif-tab-container')
                                .find('.nav-tab')
                                .text()
                                .replace('(Main)', '')
                                .trim();

        if (lang === mainLang) {
            alert('You cannot remove the Main Language. Please select a different Main Language first.');
            return;
        }

        if (confirm(`Are you sure you want to remove the "${langName}" language configuration and all its rules? This cannot be undone.`)) {
            $('#wawp_remove_language').val(lang);
            settingsForm[0].submit();
        }
    });

    /*─────────────────────────────────────────────────────────────────────────*/
    /* COLLAPSIBLE NOTIFICATION CARDS & ENABLE/DISABLE SWITCH                  */
    /*─────────────────────────────────────────────────────────────────────────*/
    function toggleCardContent($wrapper, expand) {
        if (expand) {
            $wrapper.removeClass('collapsed').addClass('expanded');
            $wrapper.find('.wawp-notif-card-header i.ri-arrow-down-s-line')
                    .removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        } else {
            $wrapper.removeClass('expanded').addClass('collapsed');
            $wrapper.find('.wawp-notif-card-header i.ri-arrow-up-s-line')
                    .removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        }
    }
    
    function scrollToCard($wrapper) {
  const adminBar = $('#wpadminbar').length ? $('#wpadminbar').outerHeight() : 0;
  const pad = 12; // small breathing room
  const top = Math.max(0, $wrapper.offset().top - adminBar - pad);
  $('html, body').stop(true).animate({ scrollTop: top }, 300);
}

function closeAllCardsExcept($target) {
  const $pane = $target.closest('.wawp-notif-tab-content');
  $pane.find('.wawp-notif-card-wrapper').each(function () {
    const $w = $(this);
    if ($w[0] !== $target[0]) {
      toggleCardContent($w, false);
    }
  });
}

function openCardAndScroll($wrapper) {
  const $pane   = $wrapper.closest('.wawp-notif-tab-content');
  const tabHref = '#' + $pane.attr('id');
  const $tab    = $('.wawp-notif-tabs .nav-tab[href="' + tabHref + '"]');

  // ensure the correct language tab is active
  if (!$pane.hasClass('active') && $tab.length) {
    activateLangTab($tab);
  }
  $('#wawp_active_tab').val(tabHref);

  // accordion behavior: close others, open this one
  closeAllCardsExcept($wrapper);
  toggleCardContent($wrapper, true);

  // remember which rule was open (hidden field + sessionStorage)
  const rid = String($wrapper.data('rule-id') || '');
  if (rid) {
    $('#wawp_active_rule_id').val(rid);
    try { sessionStorage.setItem('wawp_active_rule_id', rid); } catch(e) {}
  }

  scrollToCard($wrapper);
}


    
     toggleLangTabsVisibility(); 
    $('.wawp-notif-card-wrapper').each(function() {
        if (!$(this).find('.wawp-notif-card-header i.ri-arrow-down-s-line, .wawp-notif-card-header i.ri-arrow-up-s-line').length) {
             $(this).find('.wawp-notif-card-header').append('<i class="ri-arrow-down-s-line"></i>');
        }
        if (!$(this).hasClass('expanded')) { // Ensure initially collapsed if not already marked expanded
            $(this).addClass('collapsed');
        }
    });


    $(document).on('click', '.wawp-notif-card-header', function(e) {
    if ($(e.target).closest('.wawp-notif-remove-rule-button, .wawp-rule-enable-switch, .wawp-slider, .wawp-notif-edit-button').length) {
        return;
    }
    const $wrapper = $(this).closest('.wawp-notif-card-wrapper');
    openCardAndScroll($wrapper); // <- closes others, opens this one, scrolls
});


    function setCardEnabled (ruleId, enabled) {

    const $wrapper  = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);
    const $content  = $wrapper.find('.wawp-notif-card-content');

    /* disable every field **except** the main toggle itself and
       the hidden <input name="[...][enabled]"> that we need to POST */
    const $fields = $content
        .find('input, select, textarea, button')
        .not('[type="hidden"]')   
        .not('.wawp-rule-enable-switch, .wawp-rule-enabled-flag, .wawp-slider');

    $content.toggleClass('disabled', !enabled);
    $fields.prop('disabled', !enabled);

    /* keep the hidden value in sync – but DO NOT disable it */
    $wrapper.find('.wawp-rule-enabled-flag').val(enabled ? '1' : '0');
}

    $('.wawp-rule-enable-switch').each(function() {
        const ruleId = $(this).data('rule-id');
        
        setCardEnabled(ruleId, $(this).is(':checked'));
    });

    $(document).on('change', '.wawp-rule-enable-switch', function() {
          const ruleId = $(this).data('rule-id');
   setCardEnabled(ruleId, $(this).is(':checked'));
    });
    $(document).on('change', '.wawp-notif-send-timing-select, .delay-fields input, .delay-fields select',
    function () {
        const ruleId = $(this).closest('.wawp-notif-card-wrapper').data('rule-id');
        refreshCardHeader(ruleId);
    });

        const mediaFrames = {};   // keyed by selector / editor-id

    /* ---------- 1) custom <Upload Media> buttons ------------------------ */
    $(document).on('click', '.wawp-notif-media-upload-button', function (e) {
        e.preventDefault();
        const $btn    = $(this);
        const target  = String($btn.data('target') || '').trim();
        if (!target) { alert('Upload button is missing data-target'); return; }

        // We store / reuse one frame per input selector to avoid leaks.
        if (!mediaFrames[target]) {
            mediaFrames[target] = wp.media({
                title   : 'Select media',
                library : { type: [ 'image', 'video' ] },
                button  : { text: 'Use this media' },
                multiple: false
            }).on('select', () => {
                const url = mediaFrames[target]
                               .state()
                               .get('selection')
                               .first()
                               .toJSON().url;
                $(target).val(url).trigger('change');
            });
        }
        window.wpActiveEditor = null;      // <- tell WP we insert into a field, not an editor
        mediaFrames[target].open();
    });

    /* ---------- 2) native <Add Media> buttons inside wp_editor ---------- */
    $(document).on('click', '.insert-media', function () {
        // Core handler starts first, but lets bubbling continue. We set
        // wpActiveEditor ASAP so WordPress inserts into the CORRECT editor.
        const editorId = $(this).data('editor');
        if (editorId) { window.wpActiveEditor = editorId; }
    });





    /*─────────────────────────────────────────────────────────────────────────*/
    /* DELAY FIELDS VISIBILITY                                                 */
    /*─────────────────────────────────────────────────────────────────────────*/
    function toggleDelayFields(selectElement) {
        const $selectElement    = $(selectElement);
        const selectedValue     = $selectElement.val();
        const $delayFields      = $selectElement.closest('td').find('.delay-fields');
        const $delayValueInput  = $delayFields.find('input[type="number"]');
        const $delayUnitSelect  = $delayFields.find('select');
        const isDisabled = $selectElement.is(':disabled');


        if (selectedValue === 'delayed' && !isDisabled) {
            $delayFields.addClass('active').slideDown();
            $delayValueInput.prop('disabled', false);
            $delayUnitSelect.prop('disabled', false);
        } else {
            $delayFields.removeClass('active').slideUp();
            $delayValueInput.prop('disabled', true);
            $delayUnitSelect.prop('disabled', true);
        }
    }
    $(document).on('change', '.wawp-notif-send-timing-select', function() {
        toggleDelayFields(this);
    });
    $('.wawp-notif-send-timing-select').each(function() {
        toggleDelayFields(this);
    });

    /*─────────────────────────────────────────────────────────────────────────*/
    /* EMOJIONEAREA INITIALIZATION                                             */
    /*─────────────────────────────────────────────────────────────────────────*/
    function initializeEmojiOneArea(selector = '.wawp-emojione-editor') {
        $(selector + ':not(.emojionearea-initialized)').each(function() {
            if ($.fn.emojioneArea) {
                $(this).emojioneArea({
                    pickerPosition: 'bottom',
                    tonesStyle: 'bullet',
                    search: true,
                    autocomplete: true,
                    attributes: { spellcheck: true }
                });
                $(this).addClass('emojionearea-initialized');
            }
        });
    }
    initializeEmojiOneArea(); // For main page textareas

    function initializeEmojiOneAreaForPopup(textareaId) {
        const $textarea = $(`#${textareaId}`);
        if ($textarea.length && !$textarea.hasClass('emojionearea-initialized') && $.fn.emojioneArea) {
            $textarea.emojioneArea({
                pickerPosition: 'bottom',
                tonesStyle: 'bullet',
                search: true,
                autocomplete: true,
                attributes: { spellcheck: true }
            }).addClass('emojionearea-initialized');

            const editor = $textarea.data('emojioneArea');
            if (editor) {
                // Move the placeholder dropdown next to the emoji button
                const $dropdown = $textarea.next('.placeholder-dropdown');
                if ($dropdown.length) {
                    editor.button.after($dropdown);
                }
                editor.setText($textarea.val()); // Ensure content is set
            }
        } else if ($textarea.hasClass('emojionearea-initialized') && $textarea.data('emojioneArea')) {
            $textarea.data('emojioneArea').setText($textarea.val()); // Refresh content
        }
    }
    
    /*─────────────────────────────────────────────────────────────────────────*/
    /* PLACEHOLDER DROPDOWN HANDLER                                            */
    /*─────────────────────────────────────────────────────────────────────────*/
    $(document).on('change', '#wawp-notif-edit-template-popup .placeholder-dropdown', function() {
        const $dropdown = $(this);
        const placeholder = $dropdown.val();
        const targetId = $dropdown.data('target');

        if (placeholder && targetId) {
            const editor = $('#' + targetId).data('emojioneArea');
            if (editor) {
                editor.editor.focus();
                // Insert text at the current cursor position
                const sel = window.getSelection();
                if (sel.getRangeAt && sel.rangeCount) {
                    const range = sel.getRangeAt(0);
                    range.deleteContents();
                    // Add a space after for better usability
                    range.insertNode(document.createTextNode(placeholder + ' '));
                    range.collapse(false); // Move cursor to the end
                } else {
                    // Fallback for older browsers
                    editor.setText(editor.getText() + placeholder + ' ');
                }
            }
            // Reset dropdown to show the icon again
            $dropdown.val('');
        }
    });
    

    /*─────────────────────────────────────────────────────────────────────────*/
    /* SELECT2 INITIALIZATION                                                  */
    /*─────────────────────────────────────────────────────────────────────────*/
    function initSelect2ForRule(ruleId) {
        const $select = $(`#admin_user_ids_${ruleId}`);
        if (!$select.length || !$.fn.select2) return;
        if ($select.hasClass('select2-hidden-accessible')) return; // Already initialized

        $select.select2({
            placeholder: 'Search users…',
            multiple: true,
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: wawpNotifData.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'wawp_search_users',
                        nonce: wawpNotifData.nonceSearchUsers,
                        q: params.term
                    };
                },
                processResults: function(data) { return { results: data.results || [] }; },
                cache: true
            },
            dropdownParent: $select.closest('td') // Ensure dropdown is within the card
        });
    }

    $('.wawp-admin-user-select').each(function() {
        initSelect2ForRule($(this).data('rule-id'));
    });
    
    function initCountryGatewaySelect2(context = document) {
        if (!$.fn.select2) return;
        $(context).find('.wawp-billing-country-select:not(.select2-hidden-accessible)').each(function() {
            $(this).select2({
                placeholder: 'Select one or more countries…', allowClear: true, width: '100%',
                dropdownParent: $(this).closest('td'), closeOnSelect: false
            });
        });
        $(context).find('.wawp-payment-gateway-select:not(.select2-hidden-accessible)').each(function() {
            $(this).select2({
                placeholder: 'Select one or more gateways…', allowClear: true, width: '100%',
                dropdownParent: $(this).closest('td'), closeOnSelect: false
            });
        });
    }
    initCountryGatewaySelect2();
    
    function initProductSelect2(context=document){
  if(!$.fn.select2) return;
  $(context).find('.wawp-product-select:not(.select2-hidden-accessible)').each(function(){
     $(this).select2({
       placeholder:'Search products…',
       allowClear:true,
       minimumInputLength:2,
       ajax:{
         url: wawpNotifData.ajaxUrl,
         dataType:'json',
         delay:250,
         data: params => ({
           action: 'wawp_search_products',
           nonce : wawpNotifData.nonceSearchProducts, // reuse nonce
           q     : params.term
         }),
         processResults:data=>({results:data.results||[]}),
         cache:true
       },
       dropdownParent: $(this).closest('td'),
       width:'100%',
       closeOnSelect:false
     });
  });
}





function initTriggerSelect2(ruleId) {
    const $sel = $('#trigger_key_' + ruleId);
    if (!$sel.length || $sel.hasClass('select2-hidden-accessible') || !$.fn.select2) { return; }

    $sel.select2({
        width: 'fit-content',
        templateResult: formatTrigger,
        templateSelection: formatTrigger,
        dropdownParent: $sel.closest('td')
    });
    


function formatTrigger(state) {
        if (!state.id) { return state.text; }                   // placeholder etc.
        const iconFile = $(state.element).data('icon') || '';   // ← our new attribute
        const $wrap    = $('<span/>');
        if (iconFile) {
            $('<img>', {
                src  : wawpNotifData.iconBaseUrl + iconFile,
                class: 'wawp-trigger-ico'
            }).appendTo($wrap);
        }
        $wrap.append(document.createTextNode(' ' + state.text));
        return $wrap;
    }
}


/* existing rules on first load */
$('.wawp-rule-trigger-select').each(function () {
    initTriggerSelect2($(this).data('rule-id'));
});

/* freshly added rules */
$(document).on('awpNotifRuleAdded', function (e, ruleId) {
    initTriggerSelect2(ruleId);
});


$(document).on('awpNotifRuleAdded', function(e, ruleId){
    const $pane = $('.wawp-notif-card-wrapper[data-rule-id="'+ruleId+'"]')
                  .closest('.wawp-notif-tab-content');
    initCardSorting($pane);
});
/*─────────────────────────────────────────────────────────────────────────*/
/* COPY LANGUAGE POPUP LOGIC                                               */
/*─────────────────────────────────────────────────────────────────────────*/

const copyLangPopup = $('#wawp-notif-copy-lang-popup');

// Show and populate the popup when a "Copy to..." button is clicked
$(document).on('click', '.wawp-notif-copy-lang-button', function(e) {
    e.preventDefault();
    const srcLang = $(this).data('lang');
    const destSelect = copyLangPopup.find('#wawp-notif-copy-destination-lang');

    destSelect.empty(); // Clear previous options

    // Use the helper function to get all available languages
    const allLangs = getConfiguredLanguages();
    let availableDestinations = 0;

    $.each(allLangs, function(code, name) {
        // Add every language that is NOT the source language to the dropdown
        if (code !== srcLang) {
            destSelect.append($('<option>', { value: code, text: name }));
            availableDestinations++;
        }
    });

    // Only show the popup if there is at least one other language to copy to
    if (availableDestinations > 0) {
        copyLangPopup.data('source-lang', srcLang); // Store the source language
        copyLangPopup.fadeIn();
    } else {
        // This should not happen anymore if the button is correctly hidden by PHP,
        // but it's a good safety check.
        alert('No other languages available to copy to.');
    }
});

// Handle the "Confirm" button inside the popup
$('#wawp-notif-copy-lang-confirm').on('click', function() {
    const src = copyLangPopup.data('source-lang');
    const dst = copyLangPopup.find('#wawp-notif-copy-destination-lang').val();

    if (src && dst) {
        $('#wawp_notif_copy_lang_src').val(src);
        $('#wawp_notif_copy_lang_dst').val(dst);
        settingsForm[0].submit();
    }
    copyLangPopup.fadeOut();
});

// Handlers to close the popup
$('#wawp-notif-copy-lang-cancel').on('click', function(e) {
    e.preventDefault();
    copyLangPopup.fadeOut();
});
copyLangPopup.on('click', function(e) {
    if ($(e.target).is(copyLangPopup)) {
        copyLangPopup.fadeOut();
    }
});
copyLangPopup.find('.wawp-notif-popup-inner').on('click', function(e) {
    e.stopPropagation();
});

    /*─────────────────────────────────────────────────────────────────────────*/
    /* SENDER DROPDOWN: SHOW/HIDE ADMIN SELECT & UPDATE TEMPLATE STATUS        */
    /*─────────────────────────────────────────────────────────────────────────*/
    function updateSenderFields(ruleId) {
        const $dropdown = $(`#sender_type_${ruleId}`);
        if (!$dropdown.length) return;
        const chosen = $dropdown.val();
        const $wrapper = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);

        $wrapper.find('.status-item-wrapper').hide(); 

        const userWhatsAppActive  = ['user_whatsapp',  'user_both', 'user_admin_both', 'user_admin_whatsapp'].includes(chosen);
        const userEmailActive     = ['user_email',     'user_both', 'user_admin_both', 'user_admin_email'].includes(chosen);
        const adminWhatsAppActive = ['admin_whatsapp', 'admin_both','user_admin_both', 'user_admin_whatsapp'].includes(chosen);
        const adminEmailActive    = ['admin_email',    'admin_both','user_admin_both', 'user_admin_email'].includes(chosen);

        if (userWhatsAppActive)  $wrapper.find('.status-item-wrapper[data-status-type="user_whatsapp"]').show();
        if (userEmailActive)     $wrapper.find('.status-item-wrapper[data-status-type="user_email"]').show();
        if (adminWhatsAppActive) $wrapper.find('.status-item-wrapper[data-status-type="admin_whatsapp"]').show();
        if (adminEmailActive)    $wrapper.find('.status-item-wrapper[data-status-type="admin_email"]').show();
        
        const $adminSelectRow = $wrapper.find(`.admin-user-row-${ruleId}`);
        if (chosen.includes('admin')) { 
            $adminSelectRow.slideDown(); initSelect2ForRule(ruleId); 
        } else {
            $adminSelectRow.slideUp();
        }
        refreshCardHeader(ruleId);
    }
    $(document).on('change', '.wawp-rule-sender-dropdown', function () {
        const ruleId = $(this).data('rule-id');
        updateSenderFields(ruleId); 
    });
    
    
    
    // Duplicate rule → submit form with idx/lang filled in
$(document).on('click', '.wawp-notif-duplicate-rule-button', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const ruleId   = $(this).data('rule-id');
    const lang     = $(this).data('lang');
    const $wrapper = $('.wawp-notif-card-wrapper[data-rule-id="' + ruleId + '"]');
    const idx      = $wrapper.closest('.wawp-notif-tab-content')
                             .find('.wawp-notif-card-wrapper')
                             .index($wrapper);     // 0-based

    $('#wawp_notif_duplicate_notification_rule_idx').val(idx);
    $('#wawp_notif_duplicate_notification_rule_lang').val(lang);

    $('#wawp-notif-settings-form').submit();
});

    

/*───────────────────────────────────────────────────────────────*/
/* TRIGGER <select> – show / hide filter-TOGGLES row            */
/*───────────────────────────────────────────────────────────────*/
$(document).on('change', '.wawp-rule-trigger-select', function () {
    const ruleId = $(this).data('rule-id');

    // The refreshFilterVisibility function now handles ALL show/hide logic correctly.
    refreshFilterVisibility(ruleId);

    // Keep the header text in sync
    refreshCardHeader(ruleId);
});
/* ----------------------------------------------------------- */
/* Helpers                                                     */
/* ----------------------------------------------------------- */

/**
 * Return an object like { en_US: "English", fr_FR: "Français" }.
 * 1) use wawpNotifData.configuredLanguages if it exists
 * 2) otherwise build it from the tab markup that is already on the page
 */
function getConfiguredLanguages () {
  if (wawpNotifData && wawpNotifData.configuredLanguages &&
      Object.keys(wawpNotifData.configuredLanguages).length) {
    return wawpNotifData.configuredLanguages;
  }
  const map = {};
  $('.wawp-notif-tab-container .nav-tab').each(function () {
    const code = $(this).attr('href').replace('#tab-', '');
    const name = $(this).text().replace('(Main)', '').trim();
    map[code] = name;
  });
  return map;
}
    
    /*─────────────────────────────────────────────────────────────────────────*/
    /* REMOVE NOTIFICATION RULE                                                */
    /*─────────────────────────────────────────────────────────────────────────*/
    $(document).on('click', '.wawp-notif-remove-rule-button', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const ruleId = $(this).data('rule-id');
        const lang   = $(this).data('lang');
        const $wrapper = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);
        const $tabPane = $wrapper.closest('.wawp-notif-tab-content');
        const idx      = $tabPane.find('.wawp-notif-card-wrapper').index($wrapper);
        const triggerText = $wrapper.find('.wawp-rule-trigger-select option:selected').text().trim() || 'this trigger';

        if (confirm(`Are you sure you want to remove the notification rule for “${triggerText}”?`)) {
            $('#wawp_notif_remove_notification_rule_idx').val(idx);
            $('#wawp_notif_remove_notification_rule_lang').val(lang);
            settingsForm[0].submit();
        }
    });

   /*─────────────────────────────────────────────────────────────────────────*/
/* REFRESH CARD HEADER TEXT                                                */
/*─────────────────────────────────────────────────────────────────────────*/
/*───────────────────────────────────────────────────────────*/
/* REFRESH CARD HEADER TEXT  –  now shows user/admin icons   */
/*   …and “send now / send after …” timing description        */
/*───────────────────────────────────────────────────────────*/
function refreshCardHeader(ruleId) {
    const $wrap = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);
    if (!$wrap.length) return;

    /* ── BASIC DATA ─────────────────────────────────────── */
    const iconBase = wawpNotifData.iconBaseUrl;
    const $triggerOpt  = $wrap.find(`#trigger_key_${ruleId} option:selected`);
    const triggerLabel = ($triggerOpt.text() || '').trim();
    const triggerIconFile = $triggerOpt.data('icon');
    
    let triggerIconHtml = '';
    if (triggerIconFile) {
        triggerIconHtml = '<img class="icon-svg" src="' + iconBase + triggerIconFile + '" alt="' + triggerLabel + '" />';
    }

    const senderType = $(`#sender_type_${ruleId}`).val() || 'user_whatsapp';
    const sendTiming = $(`#send_timing_${ruleId}`).val() || 'instant';

    const $timingCell = $wrap.find(`#send_timing_${ruleId}`).closest('td');
    const delayVal  = parseInt($timingCell.find('input[type="number"]').val() || 1, 10);
    const delayUnit = $timingCell.find('select[name$="[delay_unit]"]').val() || 'minutes';

    /* ── CHANNEL & RECIPIENT FLAGS ──────────────────────── */
    const hasWA   = senderType.includes('whatsapp') || senderType.includes('both');
    const hasEM   = senderType.includes('email')    || senderType.includes('both');

    const toUser  = senderType.includes('user');
    const toAdmin = senderType.includes('admin');

    /* ── TEXT HELPERS (with i18n fallbacks) ─────────────── */
    const T = {
        sentTo:     (wawpNotifData && wawpNotifData.textSentTo)    || 'Send to',
        when:       (wawpNotifData && wawpNotifData.textWhen)      || 'when',
        via:        (wawpNotifData && wawpNotifData.textVia)       || 'via',
        sendNow:    (wawpNotifData && wawpNotifData.textSendNow)   || 'instant',
        sendAfter:  (wawpNotifData && wawpNotifData.textSendAfter) || 'after',
        delayUnits: (wawpNotifData && wawpNotifData.delayUnits)    || {}
    };

    const joinWithAnd = (a, b) => (a && b) ? `${a} & ${b}` : (a || b || '');

    /* ── RECIPIENT TEXT ─────────────────────────────────── */
    let recipientTxt = '';
    if (toUser && toAdmin) recipientTxt = 'User & Admin';
    else if (toUser)       recipientTxt = 'User';
    else if (toAdmin)      recipientTxt = 'Admin';
    else                   recipientTxt = 'User'; // sensible default

    /* ── CHANNEL TEXT ───────────────────────────────────── */
    let channelTxt = '';
    if (hasWA && hasEM) channelTxt = 'WhatsApp & Email';
    else if (hasWA)     channelTxt = 'WhatsApp';
    else if (hasEM)     channelTxt = 'Email';
    else                channelTxt = '—'; // none selected

    /* ── CHANNEL ICON ───────────────────────────────────── */
    let channelIcon = '';
    
    if (hasWA && hasEM) {
        channelIcon =
            '<img class="icon-svg" src="' + iconBase + 'whatsapp.svg" alt="WhatsApp" />' +
            '<img class="icon-svg" src="' + iconBase + 'email.svg" alt="Email" />';
    } else if (hasWA) {
        channelIcon = '<img class="icon-svg" src="' + iconBase + 'whatsapp.svg" alt="WhatsApp" />';
    } else if (hasEM) {
        channelIcon = '<img class="icon-svg" src="' + iconBase + 'email.svg" alt="Email" />';
    } else {
        channelIcon = '—'; // none selected
    }

    /* ── TIMING TEXT ────────────────────────────────────── */
    let timingTxt = 'instant';
    if (sendTiming === 'delayed') {
        const unitLabel = T.delayUnits[delayUnit] || delayUnit;
        timingTxt = `After ${delayVal} ${unitLabel}`;
    }

    /* ── BUILD SENTENCE ─────────────────────────────────── */
    const sentence = `${T.sentTo} ${recipientTxt} ${T.via} ${channelTxt} ${timingTxt}`;
    const triggerLable = `${triggerLabel}`;
    const sendTo = `Send to ${recipientTxt}`;
    const sendTime = `${timingTxt}`;

    /* ── INJECT (text() to avoid HTML injection) ───────── */
    $wrap.find('.wawp-notif-card-header .trigger-icon').html(triggerIconHtml);
    $wrap.find('.wawp-notif-card-header .channel-icon').html(channelIcon);
    $wrap.find('.wawp-notif-card-header .sendto span').text(sendTo);
    $wrap.find('.wawp-notif-card-header .sendtime span').text(sendTime);
    $wrap.find('.wawp-notif-card-header h4').text(triggerLable);
}


    
    // initial pass
$('.wawp-filter-toggle-row input[type="checkbox"]').each(function(){
   refreshFilterVisibility($(this).data('rule-id'));
});

// live toggle
$(document).on('change','.wawp-filter-toggle-row input[type="checkbox"]',function(){
   refreshFilterVisibility($(this).data('rule-id'));
});
    
function refreshFilterVisibility(ruleId) {
    const $card = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);
    if (!$card.length) return;

    const triggerKey = $card.find('.wawp-rule-trigger-select').val() || '';

    const isWcTrigger           = triggerKey.startsWith('wc_');
    const isSubscriptionTrigger = triggerKey.startsWith('wc_sub_');
    const isOrderNoteTrigger    = (triggerKey === 'wc_order_note_added');

    const showFilters = isWcTrigger && !isSubscriptionTrigger && !isOrderNoteTrigger;

    const $imageSwitchRow      = $card.find('.wawp-product-image-switch-row');
    const $toggleRow           = $card.find('.wawp-filter-toggle-row');               // inner div
    const $toggleRowContainer  = $card.find('.wawp-filter-toggle-row-container');     // NEW: the table with the label
    const $countryFilterTables = $card.find('.wawp-billing-country-whitelist-filter-table, .wawp-billing-country-blocklist-filter-table');
    const $productFilterTables = $card.find('.wawp-product-whitelist-filter-table, .wawp-product-blocklist-filter-table');
    const $paymentFilterTable  = $card.find('.wawp-payment-gateway-filter-table');

    const $wooFiltersContainer = $card.find('.awp-woo-filters');

    const isToggleEnabled = (slug) => $card.find(`input.${slug}_filter_enabled-input`).is(':checked');

    // Show/Hide the “Product image” row and the WHOLE label+toggles table
    if (showFilters) {
        $imageSwitchRow.stop(true,true).slideDown();
        $toggleRowContainer.stop(true,true).slideDown(); // NEW: hides the "WooCommerce Filters" label + icon
        $toggleRow.stop(true,true).slideDown();
    } else {
        $imageSwitchRow.stop(true,true).slideUp();
        $toggleRowContainer.stop(true,true).slideUp();   // NEW
        $toggleRow.stop(true,true).slideUp();
    }

    // Individual filter tables
    (showFilters && isToggleEnabled('country')) ? $countryFilterTables.slideDown() : $countryFilterTables.slideUp();
    (showFilters && isToggleEnabled('product')) ? $productFilterTables.slideDown() : $productFilterTables.slideUp();
    (showFilters && isToggleEnabled('payment')) ? $paymentFilterTable.slideDown()  : $paymentFilterTable.slideUp();

    // Entire filters container (kept visible for WC triggers so user can enable toggles)
    const anyToggleOn = isToggleEnabled('country') || isToggleEnabled('product') || isToggleEnabled('payment');
    if (showFilters && anyToggleOn) {
        $wooFiltersContainer.stop(true,true).slideDown();
    } else {
        $wooFiltersContainer.stop(true,true).slideUp();
    }
}




    /*─────────────────────────────────────────────────────────────────────────*/
    /* EDIT TEMPLATE POPUP LOGIC (RENAMED FUNCTIONS FOR CLARITY)               */
    /*─────────────────────────────────────────────────────────────────────────*/
        function updateActivePopupStepsForPopup() {
    if (!currentPopupRuleId) {
        console.warn("updateActivePopupStepsForPopup: currentPopupRuleId is not set");
        return;
    }
    const ruleWrapper = $(`.wawp-notif-card-wrapper[data-rule-id="${currentPopupRuleId}"]`);
    if (!ruleWrapper.length) {
        console.warn("updateActivePopupStepsForPopup: Rule wrapper not found for " + currentPopupRuleId);
        return;
    }
    const $senderSelect = ruleWrapper.find(`#sender_type_${currentPopupRuleId}`);
    if (!$senderSelect.length) {
        console.warn("updateActivePopupStepsForPopup: Sender select not found for " + currentPopupRuleId);
        return;
    }

    const senderType = $senderSelect.val();

    // Build the array of “active steps” based on the senderType:
    activePopupSteps = [];
    if (['user_whatsapp',  'user_both', 'user_admin_both', 'user_admin_whatsapp'].includes(senderType)) {
        activePopupSteps.push(1);
    }
    if (['user_email',     'user_both', 'user_admin_both', 'user_admin_email'].includes(senderType)) {
        activePopupSteps.push(2);
    }
    if (['admin_whatsapp', 'admin_both', 'user_admin_both', 'user_admin_whatsapp'].includes(senderType)) {
        activePopupSteps.push(3);
    }
    if (['admin_email',    'admin_both', 'user_admin_both', 'user_admin_email'].includes(senderType)) {
        activePopupSteps.push(4);
    }
    

    const $navTabsContainer = $('#wawp-notif-edit-template-popup .navigation-tabs');

    // As soon as there is exactly one “active” step → hide the tabs
    // If there are two or more → show only the buttons whose data-step we pushed above
    if (activePopupSteps.length <= 1) {
        $navTabsContainer.hide();
    } else {
        $navTabsContainer.show();
        $navTabsContainer.find('button.nav-step-btn').each(function() {
            const s = parseInt($(this).data('step'), 10);
            $(this).toggle(activePopupSteps.includes(s));
        });
    }
}



    function showStepForPopup(stepToShow) {
  // Convert the argument into an integer
  let targetStep = parseInt(stepToShow, 10);

  // If we have at least one active step, but the requested step isn't in that array,
  // force it to be the first item in activePopupSteps:
  if (activePopupSteps.length > 0 && !activePopupSteps.includes(targetStep)) {
    targetStep = activePopupSteps[0];
  }
  // If there are absolutely no active steps at all, use 0 (no step)
  if (activePopupSteps.length === 0) {
    targetStep = 0;
  }

  // ——— CASE A: exactly ONE active step —————————————————————————————
  if (activePopupSteps.length === 1) {
    // 1) Make sure _only_ that one ".step" panel is visible:
    $('#wawp-notif-edit-template-popup .step').removeClass('active');
    $(`#wawp-notif-edit-template-popup .step.step-${activePopupSteps[0]}`)
      .addClass('active');

    // 2) Hide the entire navigation‐tabs bar (because there's no need to switch)
    $('#wawp-notif-edit-template-popup .navigation-tabs').hide();
    $('#wawp-notif-edit-template-popup .navigation-tabs').hide();

    // 4) “Finish” is the only button that should appear:
    $('#popup_finish_btn').show();

    // 5) Initialize any editors (emoji/TinyMCE) for that one step:
    if (activePopupSteps[0] === 1) {
      initializeEmojiOneAreaForPopup('popup_whatsapp_message');
    }
    if (activePopupSteps[0] === 2) {
      // instead, grab the DB‐value from the hidden field #email_body_<ruleId>:
      const rid = $('#popup_rule_id').val();
      const dbHtml = $(`#email_body_${rid}`).val() || '';
      setTinymceContent('popup_email_body', dbHtml);
    }
    if (activePopupSteps[0] === 3) {
      initializeEmojiOneAreaForPopup('popup_admin_whatsapp_message');
    }
     if (activePopupSteps[0] === 4) {
      setTinymceContent('popup_admin_email_body', $('#popup_admin_email_body').val() || '');
      const rid = $('#popup_rule_id').val();
      const dbHtml = $(`#admin_email_body_${rid}`).val() || '';
      setTinymceContent('popup_admin_email_body', dbHtml);
     }

    // — VERY IMPORTANT — stop here. Do not run the multi‐step logic below:
    return;
  }

  // ——— CASE B: two or more active steps —————————————————————————————
  // (This block only runs if activePopupSteps.length >= 2)

  // 1) Set the global tracking variable
  currentPopupStep = targetStep;

  // 2) Show/“activate” exactly that one step‐panel
  $('#wawp-notif-edit-template-popup .step').removeClass('active');
  $(`#wawp-notif-edit-template-popup .step.step-${currentPopupStep}`)
    .addClass('active');

  // 3) Highlight the correct navigation‐tab button
  $('#wawp-notif-edit-template-popup .navigation-tabs button.nav-step-btn')
    .removeClass('active');
  $(`#wawp-notif-edit-template-popup .navigation-tabs button[data-step="${currentPopupStep}"]`)
    .addClass('active');

  // 4) Figure out if we are at the start/end of the activePopupSteps array
  const idx       = activePopupSteps.indexOf(currentPopupStep);
  const atStart   = (idx === 0);
  const atEnd     = (idx === activePopupSteps.length - 1);

  // 5) Only show Finish if we ARE at the last step
  $('#popup_finish_btn').show();

  // 6) Initialize any editors for whichever step we're on:
  if (currentPopupStep === 1) {
    initializeEmojiOneAreaForPopup('popup_whatsapp_message');
  }
   /* User e-mail body (step 2) — only inject the DB value the
      first time, never after the editor already holds edits      */
   if (currentPopupStep === 2 && !tinymce.get('popup_email_body')) {
       const rid   = $('#popup_rule_id').val();
       const dbHtml= $('#email_body_'+rid).val() || '';
       setTinymceContent('popup_email_body', dbHtml);
   }
  if (currentPopupStep === 3) {
    initializeEmojiOneAreaForPopup('popup_admin_whatsapp_message');
  }
   /* Admin e-mail body (step 4) — same idea                        */
   if (currentPopupStep === 4 && !tinymce.get('popup_admin_email_body')) {
       const rid   = $('#popup_rule_id').val();
       const dbHtml= $('#admin_email_body_'+rid).val() || '';
       setTinymceContent('popup_admin_email_body', dbHtml);
   }
}


    
    function populatePopupFields(ruleId, langCode, ruleIndex) {
        const ruleWrapper = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);
        $('#popup_rule_id').val(ruleId);
        $('#popup_rule_lang').val(langCode);
        $('#popup_rule_index').val(ruleIndex);

        $('#popup_whatsapp_message').val(ruleWrapper.find(`#whatsapp_message_${ruleId}`).val() || '');
        $('#popup_whatsapp_media_url').val(ruleWrapper.find(`#whatsapp_media_url_${ruleId}`).val() || '');
        $('#popup_email_subject').val(ruleWrapper.find(`#email_subject_${ruleId}`).val() || '');
        setTinymceContent('popup_email_body', ruleWrapper.find(`#email_body_${ruleId}`).val() || '');
        
        $('#popup_admin_whatsapp_message').val(ruleWrapper.find(`#admin_whatsapp_message_${ruleId}`).val() || '');
        $('#popup_admin_whatsapp_media_url').val(ruleWrapper.find(`#admin_whatsapp_media_url_${ruleId}`).val() || '');
        $('#popup_admin_email_subject').val(ruleWrapper.find(`#admin_email_subject_${ruleId}`).val() || '');
        setTinymceContent('popup_admin_email_body', ruleWrapper.find(`#admin_email_body_${ruleId}`).val() || '');

        // Ensure EmojiOneArea text is updated if already initialized
        ['popup_whatsapp_message', 'popup_admin_whatsapp_message'].forEach(id => {
            if ($(`#${id}`).data('emojioneArea')) {
                $(`#${id}`).data('emojioneArea').setText($(`#${id}`).val());
            }
        });
    }

    function savePopupFields() {
        const ruleId = $('#popup_rule_id').val();
        const ruleWrapper = $(`.wawp-notif-card-wrapper[data-rule-id="${ruleId}"]`);
        
        ruleWrapper.find(`#whatsapp_message_${ruleId}`).val($('#popup_whatsapp_message').val());
        ruleWrapper.find(`#whatsapp_media_url_${ruleId}`).val($('#popup_whatsapp_media_url').val());
        ruleWrapper.find(`#email_subject_${ruleId}`).val($('#popup_email_subject').val());
        ruleWrapper.find(`#email_body_${ruleId}`).val(typeof tinymce !== 'undefined' && tinymce.get('popup_email_body') ? tinymce.get('popup_email_body').getContent() : $('#popup_email_body').val());
        
        ruleWrapper.find(`#admin_whatsapp_message_${ruleId}`).val($('#popup_admin_whatsapp_message').val());
        ruleWrapper.find(`#admin_whatsapp_media_url_${ruleId}`).val($('#popup_admin_whatsapp_media_url').val());
        ruleWrapper.find(`#admin_email_subject_${ruleId}`).val($('#popup_admin_email_subject').val());
        ruleWrapper.find(`#admin_email_body_${ruleId}`).val(typeof tinymce !== 'undefined' && tinymce.get('popup_admin_email_body') ? tinymce.get('popup_admin_email_body').getContent() : $('#popup_admin_email_body').val());

        updateSenderFields(ruleId); // To refresh status indicators on main page
    }

/* already in the file — we’re just adding the block marked <<<  >>> */
$(document).on('click', '.wawp-notif-edit-button', function (e) {
    e.preventDefault();
    e.stopPropagation();

    currentPopupRuleId = $(this).data('rule-id');
    const langCode     = $(this).data('lang');
    const $ruleCard    = $(`.wawp-notif-card-wrapper[data-rule-id="${currentPopupRuleId}"]`);
    const ruleIndex    = $ruleCard.data('idx');
    
     openCardAndScroll($ruleCard);

    populatePopupFields(currentPopupRuleId, langCode, ruleIndex);
    updateActivePopupStepsForPopup();
    showStepForPopup(activePopupSteps.length ? activePopupSteps[0] : 0);

/* <<< inject the rule’s header (icons + title + badges) into the popup >>> */
const titleText   = ($ruleCard.find('.wawp-notif-card-header h4').text() || '').trim();
const sendToTxt   = ($ruleCard.find('.wawp-notif-card-header .sendto span').text() || '').trim();
const sendTimeTxt = ($ruleCard.find('.wawp-notif-card-header .sendtime span').text() || '').trim();
const triggerIco  = $ruleCard.find('.wawp-notif-card-header .trigger-icon').html() || '';
const channelIco  = $ruleCard.find('.wawp-notif-card-header .channel-icon').html() || '';

const popupHeaderHtml = `
  <div class="notif-header">
    <div class="icons-group">
      <div class="trigger-icon">${triggerIco}</div>
      <i class="ri-arrow-right-s-line"></i>
      <div class="channel-icon">${channelIco}</div>
    </div>
    <div class="notif-header-info">
      <h4>${titleText}</h4>
      <div class="trigger-badges">
        <div class="trigger-badge sendto"><i class="ri-contacts-line"></i><span>${sendToTxt}</span></div>
        <div class="trigger-badge sendtime"><i class="ri-time-line"></i><span>${sendTimeTxt}</span></div>
      </div>
    </div>
  </div>
`;

$('#wawp-popup-rule-header').html(popupHeaderHtml);


    editTplPopup.fadeIn();
});
    $('#popup_cancel_btn').on('click', function() {
        editTplPopup.fadeOut();
        currentPopupRuleId = null; // Reset
    });
    $('#popup_finish_btn').on('click', function() {
  savePopupFields();

  if (currentPopupRuleId) {
    $('#wawp_active_rule_id').val(String(currentPopupRuleId));
    try { sessionStorage.setItem('wawp_active_rule_id', String(currentPopupRuleId)); } catch(e) {}
  }

  editTplPopup.fadeOut();
  currentPopupRuleId = null;
  settingsForm[0].submit();
});


    $('#wawp-notif-edit-template-popup').on('click', function(e) {
        if ($(e.target).is('#wawp-notif-edit-template-popup')) {
            e.preventDefault();
            editTplPopup.fadeOut();
            currentPopupRuleId = null; // Reset
        }
    });
    
    $('#wawp-notif-edit-template-popup .wawp-notif-popup-inner').on('click', function(e) {
        e.stopPropagation();
    });

    // Navigation tab clicks in popup - make them non-navigational
    $('#wawp-notif-edit-template-popup .navigation-tabs').on('click', '.nav-step-btn', function(e) {
        e.preventDefault(); 
        // If you want to allow jumping to VISIBLE tabs:
     const stepToJump = parseInt($(this).data('step'));
        if (activePopupSteps.includes(stepToJump)) {
         showStepForPopup(stepToJump);
         }
        return false; // Disables click-to-navigate on tabs
    });
    
    /*─────────────────────────────────────────────────────────────────────────*/
    /* INITIAL LOAD & POST-LOAD HANDLERS                                       */
    /*─────────────────────────────────────────────────────────────────────────*/
    
    
    /* ───── DRAG-AND-DROP CARD SORTING ───── */
function initCardSorting ( $tabPane ) {
if (typeof $.fn.sortable !== 'function') { return; }
  $tabPane.sortable({
      items:            '.wawp-notif-card-wrapper',
      handle:           '.wawp-notif-card-header',
      placeholder:      'wawp-card-placeholder',
      forcePlaceholderSize: true,
      tolerance:        'pointer',
      start:  function(e, ui){ ui.placeholder.height(ui.item.outerHeight()); },
      update: function(e, ui){ renumberCards($tabPane); }
  });
}

function renumberCards ( $pane ) {
  $pane.find('.wawp-notif-card-wrapper').each(function(idx){
      const rid = $(this).data('rule-id');
      /* 1) visible text */
      refreshCardHeader(rid);

      /* 2) hidden sort_order field */
      $(this).find('.wawp-sort-order').val(idx);
  });
}

    
    
    function runInitializations() {
        toggleLangTabsVisibility();
        initializeEmojiOneArea();
        initCountryGatewaySelect2(document);
        initProductSelect2(document);

        $('.wawp-notif-send-timing-select').each(function() { toggleDelayFields(this); });
        $('.wawp-rule-sender-dropdown').each(function() { updateSenderFields($(this).data('rule-id')); });
        
        $('.wawp-admin-user-select').each(function() { initSelect2ForRule($(this).data('rule-id')); });
        
        $('.wawp-notif-card-wrapper').each(function() {
            const ruleId = $(this).data('rule-id');
            if (ruleId) {
                 // Ensure collapse icon is present and state is correct
                if (!$(this).find('.wawp-notif-card-header i[class*="ri-arrow-"]').length) {
                    $(this).find('.wawp-notif-card-header').append('<i class="ri-arrow-down-s-line"></i>');
                }
                if (!$(this).hasClass('expanded') && !$(this).hasClass('collapsed')) {
                     $(this).addClass('collapsed'); // Default to collapsed
                } else if ($(this).hasClass('expanded')) {
                     toggleCardContent($(this), true); // Ensure icon matches state
                } else {
                     toggleCardContent($(this), false);
                }
                
                

                refreshCardHeader(ruleId);
                
                initCardSorting( $(this).closest('.wawp-notif-tab-content') );
                // Trigger change on trigger select to ensure filter visibility is correct on load
                $(this).find('.wawp-rule-trigger-select').trigger('change');
            }
        });
        
        const $mainSaveButton = $('#save_settings_button');
        if ($mainSaveButton.length && !$mainSaveButton.find('i.ri-save-line').length) {
            $mainSaveButton.html('<i class="ri-save-line"></i> ' + $mainSaveButton.text());
        }
    }

    runInitializations(); // Run on initial page load

    $(document.body).on('post-load', function() { // For AJAX content reloads (if any)
        runInitializations();
    });

    // Fallback for localized strings if not provided by PHP (should be in wp_localize_script)
    window.wawpNotifData = $.extend({
        textWhatsAppTemplateSet: 'WhatsApp Template Set',
        textWhatsAppNotSet: 'WhatsApp Not Set',
        textEmailTemplateSet: 'Email Template Set',
        textEmailNotSet: 'Email Not Set',
        textAdminWhatsAppTemplateSet: 'Admin WhatsApp Set',
        textAdminWhatsAppNotSet: 'Admin WhatsApp Not Set',
        textAdminEmailTemplateSet: 'Admin Email Set',
        textAdminEmailNotSet: 'Admin Email Not Set',
        textWhen: 'When', // Ensure these are localized in PHP
        textRule: 'Rule'  // Ensure these are localized in PHP
    }, wawpNotifData || {});

    /*
    * ──────────────────────────────────────────────────
    * RE-OPEN THE CORRECT CARD ON PAGE LOAD
    * Priority 1: The last card that was edited/saved.
    * Priority 2: The newest card if one was just created.
    * ──────────────────────────────────────────────────
    */
    let cardWasOpened = false;

    // PRIORITY 1: Check for a specific rule ID from an edit/save action.
    const activeRuleIdOnLoad = wawpNotifData.activeRuleId || sessionStorage.getItem('wawp_active_rule_id');
    if (activeRuleIdOnLoad) {
        const $cardToOpen = $('.wawp-notif-card-wrapper[data-rule-id="' + activeRuleIdOnLoad + '"]');
        if ($cardToOpen.length) {
            setTimeout(function() {
                openCardAndScroll($cardToOpen);
            }, 150);
            cardWasOpened = true;
        }
    }

    // PRIORITY 2: If no specific card was opened, check if a new one was just added.
    if (!cardWasOpened && $('#wawp_active_tab').val().indexOf('#tab-') === 0) {
        const $cardsInActiveTab = $('.wawp-notif-tab-content.active .wawp-notif-card-wrapper');
        const $lastCard = $cardsInActiveTab.last();
        
        // This logic is mainly for when a new card is added, which always appears last.
        if ($lastCard.length) {
            // Check if this new card has a default trigger key, indicating it's likely new.
            const triggerKey = $lastCard.find('.wawp-rule-trigger-select').val();
            if (triggerKey === 'user_login') { // 'user_login' is the default for a new rule.
                 setTimeout(function() {
                    openCardAndScroll($lastCard);
                }, 150);
            }
        }
    }

});