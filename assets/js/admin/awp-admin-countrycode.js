(function($){
  $(document).ready(function(){
    // Accordion toggle
    $('.awp-region-title').on('click', function(){
      var $group = $(this).closest('.awp-region-group');
      $group.toggleClass('collapsed');
    });

    // Live search: hide entire region group if no matching country card is found.
    $('#awp-country-search-input').on('input', function(){
      var q = $(this).val().toLowerCase().trim();
      $('.awp-region-group').each(function(){
        var $group = $(this);
        var matchFound = false;
        $group.find('.awp-country-card').each(function(){
          var name = $(this).data('countryname') ? $(this).data('countryname').toString().toLowerCase() : "";
          if(name.indexOf(q) >= 0) {
            $(this).show();
            matchFound = true;
          } else {
            $(this).hide();
          }
        });
        if(matchFound) {
          $group.show().removeClass('collapsed');
          $group.find('.awp-region-title').show();
        } else {
          $group.hide();
        }
      });
    });

    // Toggle selection: update Default Country Code live.
    $('.awp-country-card').on('click', function(e){
      e.preventDefault();
      var $card = $(this);
      var iso2 = $card.data('iso2');
      var $hidden = $('#awp-country-hidden-input-' + iso2);
      if($card.hasClass('selected')) {
        $card.removeClass('selected');
        $hidden.remove();
      } else {
        $card.addClass('selected');
        $('<input>', {
          type: 'hidden',
          id: 'awp-country-hidden-input-' + iso2,
          name: 'woo_intl_tel_options[enabled_countries][]',
          value: iso2
        }).appendTo('#awp-selected-countries-container');
      }
      updateDefaultCountrySelect();
    });

    // Select All
    $('#awp-select-all').on('click', function(e){
      e.preventDefault();
      $('.awp-country-card:not(.selected)').each(function(){
        $(this).trigger('click');
      });
      updateDefaultCountrySelect();
    });

    // Deselect All
    $('#awp-deselect-all').on('click', function(e){
      e.preventDefault();
      $('.awp-country-card.selected').each(function(){
        $(this).trigger('click');
      });
      updateDefaultCountrySelect();
    });

    // Phone fields enable/disable toggles
    $('.awp-phone-enable-btn').on('click', function(){
      var $btn = $(this);
      var rowIndex = $btn.data('rowindex');
      var $hidden = $('#awp_phone_fields_enabled_' + rowIndex);
      if($btn.hasClass('success')) {
        $btn.removeClass('success')
            .addClass('error')
            .text(awpAdminStrings.disabledLabel);
        $hidden.val('0');
      } else {
        $btn.removeClass('error')
            .addClass('success')
            .text(awpAdminStrings.enabledLabel);
        $hidden.val('1');
      }
    });
    
    // Add new phone field row
    $('#awp-add-new-phone-field').on('click', function(e){
      e.preventDefault();
      var $table = $('.awp-phone-fields-table');
      var rowCount = $table.find('tr').length - 1; // subtract header row
      var enabledText = awpAdminStrings.enabledLabel; // "Enabled"
      var newRow = $(
        '<tr>' +
          '<td class="adv-phone-field"><label>Field ID</label><input type="text" name="woo_intl_tel_options[phone_fields][' + rowCount + '][id]" value="" style="width:100%;"></td>' +
          '<td class="adv-phone-field"><label>Field Name</label><input type="text" name="woo_intl_tel_options[phone_fields][' + rowCount + '][name]" value="" style="width:100%;"></td>' +
          '<td class="adv-phone-enable">' +
            '<span class="awp-phone-enable-btn awp-badge success" data-rowindex="' + rowCount + '">' + enabledText + '</span>' +
            '<input type="hidden" id="awp_phone_fields_enabled_' + rowCount + '" name="woo_intl_tel_options[phone_fields][' + rowCount + '][enabled]" value="1">' +
          '</td>' +
        '</tr>'
      );
      $table.append(newRow);
    });
    
    // Function to update the Default Country Code select live.
    function updateDefaultCountrySelect() {
      var enabledCountries = [];
      // Gather enabled country codes from the hidden inputs.
      $('#awp-selected-countries-container input[name="woo_intl_tel_options[enabled_countries][]"]').each(function(){
          enabledCountries.push($(this).val());
      });
      // Update each option in the default select.
      $('#awp-default-country-code option').each(function(){
          var iso = $(this).val();
          if(enabledCountries.indexOf(iso) > -1) {
              $(this).show();
          } else {
              $(this).hide();
          }
      });
      // If no countries are enabled, clear the select.
      if(enabledCountries.length === 0) {
          $('#awp-default-country-code').val('');
      } else {
          // If the current selected default is not enabled, select the first enabled country.
          var currentDefault = $('#awp-default-country-code').val();
          if(enabledCountries.indexOf(currentDefault) === -1) {
              $('#awp-default-country-code').val(enabledCountries[0]);
          }
      }
    }
    
    // Call updateDefaultCountrySelect on page load.
    updateDefaultCountrySelect();
    
  });
})(jQuery);
