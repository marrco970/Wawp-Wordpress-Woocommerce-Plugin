jQuery(document).ready(function($){

  /*************************************************
   * 0) SETUP: Chat Icons Dictionary
   *************************************************/
  // We'll have a small map from chat-icon class => display name
  let chatIconsMap = {
    'ri-whatsapp-line':      'WhatsApp',
    'ri-chat-2-line':        'Chat 2',
    'ri-chat-3-line':        'Chat 3',
    'ri-chat-4-line':        'Chat 4',
    'ri-discuss-line':       'Discuss',
    'ri-chat-smile-2-line':  'Chat Smile',
    'ri-send-plane-fill':    'Send Plane',
    'ri-customer-service-2-line': 'Customer Service 2',
    'ri-customer-service-line':   'Customer Service'
  };

  /*************************************************
   * 1) TAB SWITCHING + REMEMBER LAST OPEN TAB
   *************************************************/
  // When user clicks a tab, highlight + show matching .awp-tab panel
  $('.nav-tab').on('click', function(e) {
    e.preventDefault();
    // Switch active class
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');

    // Hide all panels, show the relevant one
    $('.awp-tab').hide();
    let targetTab = $(this).data('tab'); // e.g. "awp-tab-contacts"
    $('#' + targetTab).show();

    // Store last open tab in localStorage
    localStorage.setItem('awp_last_open_tab', '#' + targetTab);

    // Show/hide the “Save Changes” button
    if (targetTab === 'awp-tab-contacts' 
     || targetTab === 'awp-tab-links' 
     || targetTab === 'awp-tab-settings') {
      $('.awp-settings-wrap .submit').show();
    } else {
      $('.awp-settings-wrap .submit').hide();
    }
  });

  // On page load: read localStorage to show last open tab
  let storedTab = localStorage.getItem('awp_last_open_tab');
  if(storedTab && $(storedTab).length) {
    // Show that tab
    $('.awp-tab').hide();
    $(storedTab).show();
    // Mark the nav-tab as active
    $('.nav-tab[data-tab="' + storedTab.replace('#','') + '"]').addClass('nav-tab-active');

    // Show/hide “Save Changes” based on which tab
    let lastTabId = storedTab.replace('#','');
    if (lastTabId === 'awp-tab-contacts' 
     || lastTabId === 'awp-tab-links' 
     || lastTabId === 'awp-tab-settings') {
      $('.awp-settings-wrap .submit').show();
    } else {
      $('.awp-settings-wrap .submit').hide();
    }
  } else {
    // Fallback: Click the first .nav-tab => e.g. the “WhatsApp” tab
    $('.nav-tab:first').click();
  }

  /*************************************************
   * 2) CLEAR STATS BUTTON
   *************************************************/
  $(document).on('click', '#awp-clear-stats', function(e){
    e.preventDefault();
    if (!confirm('Are you sure you want to clear all stats?')) return;

    $.post(ajaxurl, { action: 'awp_clear_stats' }, function(){
        location.reload();
    });
  });

  /*************************************************
   * 3) PAGE CONDITIONS: Exclude Pages
   *************************************************/
  $('#awp_trigger_on_all_pages').on('change', function(){
      if ($(this).is(':checked')) {
          $('#awp-conditions-wrapper')
            .css({'opacity':'0.4','pointer-events':'none'});
      } else {
          $('#awp-conditions-wrapper')
            .css({'opacity':'1','pointer-events':'auto'});
      }
  });

  // Add new <select> for page conditions
  let conditionIndex = $('#awp-condition-fields .awp-condition-group').length;
  $('#awp-add-condition').on('click', function(e){
      e.preventDefault();
      let $template = $('#awp-condition-template .awp-condition-group').clone();
      // e.g. name="awp_page_conditions[5]"
      $template.find('select').attr('name', `awp_page_conditions[${conditionIndex}]`);
      $('#awp-condition-fields').append($template);
      conditionIndex++;
  });
  $(document).on('click', '.awp-remove-condition', function(){
      $(this).closest('.awp-condition-group').remove();
  });

  /*************************************************
   * 4) SOCIAL ICONS - Add/Remove
   *************************************************/
  let socialCount = $('#awp-social-fields .awp-social-group').length;

  $('#awp-add-social-btn').on('click', function(e){
    e.preventDefault();
    socialCount++;

    // A dictionary of icon => label
    let extendedIcons = {
      'ri-facebook-circle-fill': { label:'Facebook' },
      'ri-twitter-fill':         { label:'Twitter (X)' },
      'ri-instagram-fill':       { label:'Instagram' },
      'ri-tiktok-fill':          { label:'TikTok' },
      'ri-linkedin-box-fill':    { label:'LinkedIn' },
      'ri-youtube-fill':         { label:'YouTube' },
      'ri-telegram-fill':        { label:'Telegram' },
      'ri-github-fill':          { label:'GitHub' },
      'ri-mail-fill':            { label:'Email' },
      'ri-phone-fill':           { label:'Phone' },
      'ri-global-fill':          { label:'Website' },
      'ri-whatsapp-line':        { label:'WhatsApp' }
    };

    let iconOptions = '';
    for (let cls in extendedIcons) {
      iconOptions += `<option value="${cls}">
        ${extendedIcons[cls].label})
      </option>`;
    }

    let newSocial = `
      <div class="awp-settings-group awp-social-group">
        <div class="awp-field">
          <label>Icon</label>
          <select name="awp_social_icons[${socialCount}][icon]" class="awp-social-icon-select">
            ${iconOptions}
          </select>
        </div>
        <div class="awp-field">
          <label>Link</label>
          <input type="url" name="awp_social_icons[${socialCount}][link]" value="" style="max-width: 100%;" />
        </div>
        <div class="awp-field" style="max-width: fit-content;">
          <button type="button" class="awp-remove-social delete-plain">
            <i class="ri-delete-bin-line"></i>Delete
          </button>
        </div>
      </div>
    `;
    $('#awp-social-fields').append(newSocial);

    // Re-init the select2 icon dropdown
    let $select = $('#awp-social-fields .awp-social-group:last .awp-social-icon-select');
    initSelect2Icons($select);
  });

  // Remove social row
  $(document).on('click', '.awp-remove-social', function(){
    $(this).closest('.awp-social-group').remove();
  });

  // If user picks “ri-mail-fill” but link is empty => prepend "mailto:"
  // If user picks “ri-phone-fill” but link is empty => prepend "tel:"
  $(document).on('change', '.awp-social-icon-select', function() {
    let iconValue = $(this).val();
    let $container = $(this).closest('.awp-social-group');
    let $linkInput = $container.find('input[type="url"]');
    let currentVal = $linkInput.val().trim();

    if (!currentVal) {
      if (iconValue === 'ri-mail-fill') {
        $linkInput.val('mailto:');
      } else if (iconValue === 'ri-phone-fill') {
        $linkInput.val('tel:');
      }
    }
  });

  /*************************************************
   * 5) WHATSAPP NUMBERS - Add/Remove
   *************************************************/
  let contactCount = $('#awp-repeatable-fields .awp-repeatable-group').length;

  $('.awp-add-plus').on('click', function() {
    contactCount++;
    let newContact = `
      <div class="awp-repeatable-group">
        <div class="awp-field">
          <label><i class="ri-whatsapp-line"></i> WhatsApp Number</label>
          <input type="text" name="awp_whatsapp_numbers[]" 
                 class="awp-whatsapp-number" placeholder="Enter WhatsApp number"/>
        </div>
        <div class="awp-field">
          <label><i class="ri-user-3-line"></i> Name</label>
          <input type="text" name="awp_user_names[]" placeholder="John Doe"/>
        </div>
        <div class="awp-field">
          <label><i class="ri-chat-2-line"></i> Default Message</label>
          <input type="text" name="awp_whatsapp_messages[]" placeholder="Hello!"/>
        </div>
        <div class="awp-field">
          <label><i class="ri-briefcase-line"></i> Role/Support Text</label>
          <input type="text" name="awp_user_roles[]" placeholder="Customer Support"/>
        </div>
        <div class="awp-field">
          <label><i class="ri-image-line"></i> Avatar</label>
          <div class="awp-upload-wrapper" style="background: #fff;">
            <img src="" alt="">
            <span></span>
            <button type="button" class="awp-btn awp-select-avatar-button">
              Upload
            </button>
          </div>
          <input type="hidden" name="awp_user_avatars[]" />
        </div>
        <div class="btn-group" style="justify-content: end;">
          <button type="button" class="delete-plain">
            <i class="ri-delete-bin-line"></i> Delete
          </button>
        </div>
      </div>
    `;
    $('#awp-repeatable-fields').append(newContact);

    // Re-init phone input for new field
    initializeIntlTelInput('.awp-whatsapp-number');
  });

  // “Delete” button for new or existing rows
  $(document).on('click', '.delete-plain', function(){
    $(this).closest('.awp-repeatable-group').remove();
  });

  /*************************************************
   * 6) MEDIA UPLOADER FOR AVATAR
   *************************************************/
  $(document).on('click', '.awp-select-avatar-button', function(e){
    e.preventDefault();
    let $button     = $(this);
    let $parent     = $button.closest('.awp-field');
    let $previewImg = $parent.find('img');
    let $hiddenField= $parent.find('input[type="hidden"]');
    let $textSpan   = $parent.find('span');

    let custom_uploader = wp.media({
      title: 'Select Image',
      button: { text: 'Use this image' },
      multiple: false
    }).on('select', function() {
      let attachment = custom_uploader.state().get('selection').first().toJSON();
      $hiddenField.val(attachment.url);
      $previewImg.attr('src', attachment.url);
      $textSpan.text(attachment.url);
    }).open();
  });

  // “Default Profile Picture” button
  $(document).on('click', '#awp_select_avatar_button', function(e){
    e.preventDefault();
    let custom_uploader = wp.media({
      title: 'Select Image',
      button: { text: 'Use this image' },
      multiple: false
    }).on('select', () => {
      let attachment = custom_uploader.state().get('selection').first().toJSON();
      $('#awp_avatar_url').val(attachment.url);
      $('#awp_avatar_preview').attr('src', attachment.url);
      $('#awp_avatar_preview').next('span').text(attachment.url);
    }).open();
  });

  /*************************************************
   * 7) SELECT2 ICON PICKER 
   *    (For social icons & chat icon)
   *************************************************/
  function initSelect2Icons($select) {
    $select.select2({
      templateResult: formatIconOption,
      templateSelection: formatIconSelection,
      width: 'auto'
    });
  }

  // For “Social Icons,” we typically have “Facebook (ri-facebook-circle-fill)”
  // For Chat Icons, we want to show the name from “chatIconsMap”
  function formatIconOption(state) {
  if (!state.id) {
    return state.text; // e.g. placeholder
  }
  let iconClass = state.id;

  // Use chatIconsMap if it exists:
  let knownLabel = chatIconsMap[iconClass];
  let label = knownLabel ? knownLabel + ' (' + iconClass + ')' : state.text;

  // Return an <i> plus the label
  return $('<span><i class="' + iconClass + '"></i> ' + label + '</span>');
}

  function formatIconSelection(state) {
    if (!state.id) return state.text;
    let iconClass = state.id;

    // If it's the chat icon select (like #awp_whatsapp_icon_class),
    // we can see if there's a known label in chatIconsMap
    let knownLabel = chatIconsMap[iconClass];
    // If known, override the text
    let label = knownLabel ? knownLabel+' ('+iconClass+')' : state.text;
    return $('<span><i class="'+iconClass+'"></i> '+label+'</span>');
  }

  // Initialize select2 on existing .awp-social-icon-select
  $('.awp-social-icon-select').each(function(){
    initSelect2Icons($(this));
  });
  // For the main chat icon
  if ($('#awp_whatsapp_icon_class').length) {
    initSelect2Icons($('#awp_whatsapp_icon_class'));
  }

  /*************************************************
   * 8) INTL-TEL-INPUT FOR PHONE 
   *************************************************/
  function initializeIntlTelInput(selector) {
    $(selector).each(function () {
      if (!$(this).hasClass('iti-loaded')) {
        let iti = window.intlTelInput(this, {
          initialCountry: "auto",
          geoIpLookup: function (success, failure) {
            $.ajax({
              url: "https://ipapi.co/country/",
              type: "GET",
              dataType: "text",
              success: function (countryCode) {
                success(countryCode);
              },
              error: function () {
                failure();
              }
            });
          },
          utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
        });
        $(this).addClass('iti-loaded');

        // On blur, store the properly formatted number (without +)
        $(this).on('blur', () => {
          $(this).val(iti.getNumber().replace('+',''));
        });
        $(this).data('itiInstance', iti);
      }
    });
  }
  // Initialize for existing fields
  initializeIntlTelInput('.awp-whatsapp-number');

  /*************************************************
   * 9) WP COLOR PICKERS
   *************************************************/
  // For “Icon Background” (#awp_button_bg_color) + “Icon Color” (#awp_icon_color)
  if ($('#awp_button_bg_color').length) {
    $('#awp_button_bg_color').wpColorPicker();
  }
  if ($('#awp_icon_color').length) {
    $('#awp_icon_color').wpColorPicker();
  }

  /*************************************************
   * 10) SLIDER: ICON SIZE + CORNER RADIUS
   *************************************************/
  $(document).on('input change', '#awp_button_size', function(){
    $('#awp_button_size_value').text($(this).val() + 'px');
  });
  $(document).on('input change', '#awp_corner_radius', function(){
    $('#awp_corner_radius_value').text($(this).val() + '%');
  });
  
  /*************************************************
 * 11) Toggle expand/collapse for each WhatsApp-contact card
 *************************************************/

/* On load: keep only the collapsed rows visible */
$('.awp-repeatable-group .awp-contact-expanded').hide();
$('.awp-repeatable-group .awp-contact-collapsed').show();

/* — Edit button — open the full form */
$(document).on('click', '.awp-edit-contact', function () {
  const $group = $(this).closest('.awp-repeatable-group');
  $group.find('.awp-contact-collapsed').hide();        // hide summary
  $group.find('.awp-contact-expanded').slideDown(200); // show form
});

/* — Done button — close form, then auto-save via the main submit */
$(document).on('click', '.awp-close-contact', function () {
  const $group = $(this).closest('.awp-repeatable-group');

  // 1) Hide form, show collapsed summary again
  $group.find('.awp-contact-expanded').slideUp(200, function () {
    $group.find('.awp-contact-collapsed').fadeIn(100);
  });

  // 2) Programmatically click the WordPress “Save Changes” button
  $(this)
    .closest('form')              // same <form> wrapper
    .find('input[type="submit"]') // WP-generated submit button
    .first()
    .trigger('click');            // save immediately
});




});
