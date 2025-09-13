(function(){
    // Inject inline CSS for flag alignment if not already present.
    function injectFlagAlignmentCSS() {
    if (!document.getElementById('wawp-flag-alignment-style')) {
        var style = document.createElement('style');
        style.id = 'wawp-flag-alignment-style';
        style.type = 'text/css';
        var cssRules =
            ".iti.iti--flag-right .iti__flag-container { left: auto !important; right: 0 !important; }" +
            ".iti.iti--flag-right input { padding-left: 12px !important; padding-right: 44px !important; }" +
            ".iti:not(.iti--flag-right) .iti__flag-container { left: 0 !important; right: auto !important; }" +
            ".iti:not(.iti--flag-right) input { padding-left: 44px !important; padding-right: 12px !important; }" +
            ".iti.iti--flag-right .iti__country-list { text-align: right !important; }";
        
        // Add extra rules only if the settings indicate "left" alignment.
        if (typeof wooIntlTelSettings !== 'undefined' && wooIntlTelSettings.countryCodeAlignment === 'left') {
            cssRules += "#iti-0__country-listbox { margin-right: -400px; }";
            cssRules += ".iti-mobile .iti__country-list { width: 80% !important; }";
        }
        
        style.textContent = cssRules;
        document.head.appendChild(style);
    }
}
injectFlagAlignmentCSS();


    
    // Inject a search field into any intl-tel-input dropdown that does not already have one.
   function addSearchToCountryList(dropdown) {
    if (!dropdown) return;
    if (dropdown.querySelector('.iti__search')) return;

    var searchInput = document.createElement('input');
    searchInput.className = 'iti__search';
    searchInput.placeholder = (typeof awpTelInputStrings !== 'undefined' && awpTelInputStrings.searchPlaceholder)
        ? awpTelInputStrings.searchPlaceholder
        : 'Search country';
    searchInput.style.cssText = "width:90%; margin:5px auto; display:block; border:1px solid #ccc; padding:5px; font-size:14px;";
    searchInput.style.setProperty('color', '#000', 'important');
    searchInput.style.setProperty('background-color', '#fff', 'important');
    // Optional but can help in some dark-mode themes:
    // searchInput.style.setProperty('caret-color', '#000', 'important');

    // Only stop event propagation; DON'T prevent default 
    searchInput.addEventListener('mousedown', function(e) {
        e.stopPropagation();
    });
    searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    dropdown.insertBefore(searchInput, dropdown.firstChild);

    searchInput.addEventListener('keyup', function() {
        var filter = searchInput.value.toLowerCase().trim();
        var countries = dropdown.querySelectorAll('.iti__country');
        countries.forEach(function(country) {
            var countryName = country.getAttribute('data-country-name-local') 
                              || country.getAttribute('data-country-name') 
                              || "";
            var dialCode = country.getAttribute('data-dial-code') || "";
            if (countryName.toLowerCase().indexOf(filter) > -1 || dialCode.toLowerCase().indexOf(filter) > -1) {
                country.style.display = "";
            } else {
                country.style.display = "none";
            }
        });
    });
}

    
    // Ensure that every dropdown gets the search input.
    function ensureSearchBoxes() {
        var dropdowns = document.querySelectorAll('.iti__country-list');
        dropdowns.forEach(function(dropdown) {
            addSearchToCountryList(dropdown);
        });
    }
    
    // Initializes intl-tel-input on an input field.
    function setupIntlTelInputOnField(inputEl) {
        if (!inputEl) return;
        if (inputEl.dataset.intlTelInitialized === 'true') return;
        
        
        var options = {
            initialCountry: (typeof wooIntlTelSettings !== 'undefined' && wooIntlTelSettings.enableIpDetection)
                ? "auto"
                : (typeof wooIntlTelSettings !== 'undefined' && wooIntlTelSettings.defaultCountry ? wooIntlTelSettings.defaultCountry : "us"),
            separateDialCode: true,
            autoPlaceholder: "polite",
            formatOnDisplay: true,
            nationalMode: false,
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/utils.js'
        };
        
        if (typeof wooIntlTelSettings !== 'undefined' && wooIntlTelSettings.enableIpDetection) {
            options.geoIpLookup = function(callback) {
                fetch('https://ipapi.co/country/')
                    .then(function(resp){ return resp.text(); })
                    .then(function(countryCode){
                        if (Array.isArray(wooIntlTelSettings.allowedCountries) && wooIntlTelSettings.allowedCountries.length > 0) {
                            if (wooIntlTelSettings.allowedCountries.indexOf(countryCode.toLowerCase()) === -1) {
                                countryCode = (wooIntlTelSettings.defaultCountry || 'us');
                            }
                        }
                        callback(countryCode);
                    })
                    .catch(function(){ 
                        callback(wooIntlTelSettings.defaultCountry || 'us'); 
                    });
            };
        }
        
        if (typeof wooIntlTelSettings !== 'undefined' && Array.isArray(wooIntlTelSettings.allowedCountries) && wooIntlTelSettings.allowedCountries.length > 0) {
            options.onlyCountries = wooIntlTelSettings.allowedCountries;
        }
        
        var iti = window.intlTelInput(inputEl, options);
        
        if (inputEl.value) {
            iti.setNumber(inputEl.value);
        } else if (!wooIntlTelSettings.enableIpDetection) {
            iti.setCountry(wooIntlTelSettings.defaultCountry);
        }
        inputEl.dataset.intlTelInitialized = 'true';
        
        // Adjust flag alignment using a custom class on the container.
        if (typeof wooIntlTelSettings !== 'undefined') {
            var alignment = wooIntlTelSettings.countryCodeAlignment;
            if (alignment === 'auto') {
                alignment = wooIntlTelSettings.isRTL ? 'right' : 'left';
            }
            var itiContainer = inputEl.closest('.iti');
            if (itiContainer) {
                if (alignment === 'right') {
                    itiContainer.classList.add('iti--flag-right');
                } else {
                    itiContainer.classList.remove('iti--flag-right');
                }
            }
        }
        
        // Remove any status element inside the container and then insert one outside.
        var itiContainer = inputEl.closest('.iti');
        if (itiContainer) {
            var existingStatus = itiContainer.querySelector('.intl-tel-status');
            if (existingStatus) {
                existingStatus.parentNode.removeChild(existingStatus);
            }
            var statusEl = document.createElement('div');
            statusEl.classList.add('intl-tel-status');
            statusEl.style.marginTop = '5px';
            statusEl.style.fontSize = '0.9em';
            itiContainer.parentNode.insertBefore(statusEl, itiContainer.nextSibling);
        }
        
        // Update validation status: while input is focused, show waiting message from localized strings.
        function updateValidationStatus() {
            var val = inputEl.value.trim();
            var statusEl = inputEl.closest('.iti').parentNode.querySelector('.intl-tel-status');
            if (!statusEl) return;
            if (document.activeElement === inputEl && val !== '') {
                statusEl.textContent = (typeof awpTelInputStrings !== 'undefined' && awpTelInputStrings.waiting)
                    ? awpTelInputStrings.waiting
                    : "Waiting for you to finish writing. After you finish, the number will be corrected automatically and the country code will be written.";
                statusEl.style.color = "blue";
                return;
            }
            if (!val) {
                statusEl.textContent = '';
                inputEl.setAttribute('aria-invalid', 'false');
                return;
            }
            var testNumber = (val.charAt(0) !== '+') ? '+' + val : val;
            if (window.intlTelInputUtils && window.intlTelInputUtils.isValidNumber(testNumber, iti.getSelectedCountryData().iso2)) {
                statusEl.textContent = (typeof awpTelInputStrings !== 'undefined' && awpTelInputStrings.valid)
                    ? awpTelInputStrings.valid
                    : '✓ Valid phone number.';
                statusEl.style.color = 'green';
                inputEl.setAttribute('aria-invalid', 'false');
            } else {
                statusEl.textContent = (typeof awpTelInputStrings !== 'undefined' && awpTelInputStrings.invalid)
                    ? awpTelInputStrings.invalid
                    : '✗ Invalid phone number.';
                statusEl.style.color = 'red';
                inputEl.setAttribute('aria-invalid', 'true');
            }
        }
        
        inputEl.addEventListener('input', updateValidationStatus);
        inputEl.addEventListener('countrychange', updateValidationStatus);
        inputEl.addEventListener('blur', function() {
            updateValidationStatus();
            var val = inputEl.value.trim();
            var testNumber = (val.charAt(0) !== '+') ? '+' + val : val;
            if (window.intlTelInputUtils && window.intlTelInputUtils.isValidNumber(testNumber, iti.getSelectedCountryData().iso2)) {
                return;
            }
            var e164 = iti.getNumber();
            var finalNumber = e164.replace(/^\+/, '');
            var nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
            nativeInputValueSetter.call(inputEl, finalNumber);
            inputEl.dispatchEvent(new Event('input', { bubbles: true }));
        });
        updateValidationStatus();
        
        // Ensure search box is added on focus and flag container click.
        inputEl.addEventListener('focus', function(){
            setTimeout(ensureSearchBoxes, 300);
        });
        var flagCont = inputEl.parentNode.querySelector('.iti__flag-container');
        if (flagCont) {
            flagCont.addEventListener('click', function() {
                setTimeout(ensureSearchBoxes, 300);
            });
        }
    }
    
    function observeIntlTelInputs() {
  var billingPhone = document.querySelector('input[name="billing_phone"]') 
                     || document.querySelector('input#billing-phone');
  if (billingPhone) {
    setupIntlTelInputOnField(billingPhone);
  }

  var awpNewPhone = document.querySelector('input#awp-new-phone')
                     || document.querySelector('input[name="awp-new-phone"]');
  if (awpNewPhone) setupIntlTelInputOnField(awpNewPhone);

  var awp_user_phone = document.querySelector('input#awp_user_phone')
                         || document.querySelector('input[name="awp_user_phone"]');
  if (awp_user_phone) setupIntlTelInputOnField(awp_user_phone);

  var whatsappEl = document.querySelector('input#awp_whatsapp');
  if (whatsappEl) {
    setupIntlTelInputOnField(whatsappEl);
  }

  var awpPhoneEl = document.querySelector('input#awp_phone');
  if (awpPhoneEl) {
    setupIntlTelInputOnField(awpPhoneEl);
  }

  var miaPhoneEl = document.querySelector('input#mia_phone');
  if (miaPhoneEl) {
    setupIntlTelInputOnField(miaPhoneEl);
  }

  // ✅ NEW FIELD SUPPORT:
  var customPhoneEl = document.querySelector('input#awp-phone-number')
                     || document.querySelector('input.awp-phone-number');
  if (customPhoneEl) {
    setupIntlTelInputOnField(customPhoneEl);
  }
}


    function observeMutations() {
        var observer = new MutationObserver(function(mutations) {
            observeIntlTelInputs();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        observeIntlTelInputs();
        ensureSearchBoxes();
        observeMutations();
    });
})();
