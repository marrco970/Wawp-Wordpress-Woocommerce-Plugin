<?php
/**
 * woocommerce-myaccount.php
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'wawp_override_all_woocommerce_logins');
function wawp_override_all_woocommerce_logins() {

    // Only run if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }

    /**
     * A) Override the WC templates (myaccount/form-login.php, checkout/form-login.php)
     */
    add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
        
        // List of template files to override
        $templates_to_override = array(
            'myaccount/form-login.php',
            'checkout/form-login.php',
        );

        if (in_array($template_name, $templates_to_override, true)) {
            // Adjust path as needed; this example uses plugin_dir_path for a plugin.
            // If it's in a theme, you might use get_stylesheet_directory() or get_template_directory()
            $custom_template = plugin_dir_path(__FILE__) . 'templates/' . $template_name;
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }, 999, 3);

    /**
     * B) Replace calls to woocommerce_login_form() with our shortcode
     * 
     * By default, WooCommerce has something like:
     *    add_action('woocommerce_login_form', 'woocommerce_login_form', 10);
     * We'll remove that, and insert our own function that outputs our shortcode instead.
     */
    remove_action('woocommerce_login_form', 'woocommerce_login_form', 10);

    // Then add our own
    add_action('woocommerce_login_form', function() {
        echo do_shortcode('[wawp-fast-login]');
    });
}

/**
 * C) Add inline CSS to style the WooCommerce login form or other elements
 */
add_action('wp_head', 'wawp_add_inline_css');
function wawp_add_inline_css(){
    ?>
    <style type="text/css">
  .create-account-question {
  display: none !important;
}
.woocommerce-FormRow.woocommerce-FormRow--wide.form-row.form-row-wide.form-row-username {
  display: none !important;
}
.woocommerce-FormRow.woocommerce-FormRow--wide.form-row.form-row-wide.form-row-password {
  display: none !important;
}
.login-form-footer {
  display: none !important;
}
.button.woocommerce-button.woocommerce-form-login__submit {
  display: none !important;
}

.woocommerce-form-row.woocommerce-form-row--wide.form-row.form-row-wide {
  display: none !important;
}
.woocommerce-LostPassword.lost_password {
  display: none !important;
}

    </style>
    <?php
}
