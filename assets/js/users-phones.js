jQuery(document).ready(function($) {

    // This array holds the selectors of all fields you want to initialize with intlTelInput
    var phoneSelectors = [
        '#id',
        '#awp_user_phone',
        '#billing_phone',
        '#shipping_phone'
    ];

    // Determine our initial default country.
    // We'll check whether WooCommerce/user country is available from awpPhoneData (localized in PHP).
    // If not available, we'll use "auto" to trigger the IP-based lookup from the plugin.
    var defaultCountry = 'auto';
    if (typeof awpPhoneData !== 'undefined' && awpPhoneData.userCountry) {
        // Convert to lower case because intl-tel-input expects "us", "gb", etc.
        defaultCountry = awpPhoneData.userCountry.toLowerCase();
    }

    // Loop through each phone field and initialize intlTelInput
    phoneSelectors.forEach(function(selector) {
        var input = document.querySelector(selector);
        if (!input) {
            return;
        }

        // Initialize intlTelInput
        // For a complete list of options, see: https://github.com/jackocnr/intl-tel-input
        window.intlTelInput(input, {
            // Include any preferred countries at the top of the list
            preferredCountries: ['us', 'gb', 'in', 'au'],

            // If you want to display the country dial code next to the number
            separateDialCode: true,

            // The "initialCountry" can be set to "auto" for IP-based detection, or an ISO2 country code
            initialCountry: defaultCountry,

            // This callback is only triggered when initialCountry = 'auto'
            geoIpLookup: function(callback) {
                // If we already have a valid defaultCountry (not 'auto'), just use it:
                if (defaultCountry !== 'auto') {
                    callback(defaultCountry);
                } else {
                    // Otherwise, do IP-based lookup
                    $.get('https://ipinfo.io', function() {}, 'jsonp').always(function(resp) {
                        var countryCode = (resp && resp.country) ? resp.country.toLowerCase() : 'us';
                        callback(countryCode);
                    });
                }
            },

            // The path to the utils.js script (to enable formatting/validation).
            // Make sure the path is correct relative to your plugin/theme.
            utilsScript: 'PATH/TO/intl-tel-input/js/utils.js'
        });
    });
});
