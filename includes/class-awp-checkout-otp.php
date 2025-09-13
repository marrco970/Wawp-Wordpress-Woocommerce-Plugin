<?php
if (!defined('ABSPATH')) {
    exit;
}

class awp_checkout_otp {
    
    public function __construct() {
        add_action('init', [$this, 'init_hooks']);
        add_action('init', [$this, 'load_otp_verification']);
    }

    public function init_hooks() {
        add_action('woocommerce_thankyou', [$this, 'update_user_data_after_order'], 10, 1);
    }

    public function update_user_data_after_order($order_id) {
        if (!$order_id) return;
        $order = wc_get_order($order_id);
        if (!$order) return;
        $user_id = $order->get_user_id();
        if (!$user_id) return;

        $first_name = sanitize_text_field($order->get_billing_first_name());
        $last_name  = sanitize_text_field($order->get_billing_last_name());
        $email      = sanitize_email($order->get_billing_email());
        $phone      = sanitize_text_field($order->get_billing_phone());

        wp_update_user([
            'ID'         => $user_id,
            'user_email' => $email ?: get_userdata($user_id)->user_email
        ]);
        if ($first_name) update_user_meta($user_id, 'first_name', $first_name);
        if ($last_name)  update_user_meta($user_id, 'last_name', $last_name);

        global $wpdb;
        $table = $wpdb->prefix . 'awp_user_info';
        $info  = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
        if ($info) {
            $wpdb->update(
                $table,
                [
                    'first_name' => $first_name ?: $info->first_name,
                    'last_name'  => $last_name  ?: $info->last_name,
                    'email'      => $email      ?: $info->email,
                    'phone'      => $phone      ?: $info->phone
                ],
                ['user_id' => $user_id],
                ['%s','%s','%s','%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table, [
                'user_id'                   => $user_id,
                'first_name'                => $first_name ?: '',
                'last_name'                 => $last_name  ?: '',
                'email'                     => $email      ?: '',
                'phone'                     => $phone      ?: '',
                'password'                  => '',
                'otp_verification_whatsapp' => 0,
                'otp_verification_email'    => 0,
                'whatsapp_verified'         => __('Not Verified', 'awp'),
                'created_at'                => current_time('mysql')
            ]);
        }
    }

    public function settings_page_html() {
        if (!current_user_can('manage_options')) return;
        $banned_msg = get_transient('siteB_banned_msg');
        $token      = get_option('mysso_token');
        $user_data  = get_transient('siteB_user_data');

        if ($banned_msg) {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('Checkout OTP', 'awp') . '</h1>
                  <p style="color:red;">' . esc_html(Wawp_Global_Messages::get('blocked_generic')) . '</p></div>';
            return;
        }
        if (!$token) {
            echo '<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('Checkout OTP', 'awp') . '</h1>
                  <p>' . esc_html(Wawp_Global_Messages::get('need_login')) . '</p></div>';
            return;
        }
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('Checkout OTP', 'awp') . '</h1>
                  <p style="color:red;">' . esc_html(Wawp_Global_Messages::get('not_active_site')) . '</p></div>';
            return;
        }

        if (isset($_POST['awp_checkout_settings_submitted']) && check_admin_referer('awp_checkout_settings_save', 'awp_checkout_settings_nonce')) {
            $enable_otp = isset($_POST['awp_enable_otp']) ? 'yes' : 'no';
            update_option('awp_enable_otp', $enable_otp);
            update_option('awp_otp_mode', sanitize_text_field($_POST['awp_otp_mode'] ?? 'enable_for_all'));
            update_option('awp_otp_message_template', sanitize_textarea_field($_POST['awp_otp_message_template'] ?? ''));
            update_option('awp_selected_instance', sanitize_text_field($_POST['awp_selected_instance'] ?? ''));
            $disable_payments = isset($_POST['awp_disable_otp_for_payment_methods']) ? (array)$_POST['awp_disable_otp_for_payment_methods'] : [];
            $disable_shipping = isset($_POST['awp_disable_otp_for_shipping_methods']) ? (array)$_POST['awp_disable_otp_for_shipping_methods'] : [];
            update_option('awp_disable_otp_for_payment_methods', $disable_payments);
            update_option('awp_disable_otp_for_shipping_methods', $disable_shipping);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'awp') . '</p></div>';
        }

        $val_enable_otp  = get_option('awp_enable_otp', 'no');
        $val_otp_mode    = get_option('awp_otp_mode', 'enable_for_all');
        $val_template    = get_option('awp_otp_message_template', __('Hi {{name}} {{last}}, {{otp}} is your checkout Generated OTP code.', 'awp'));
        $val_instance = get_option('awp_selected_instance', '');

        global $wpdb;
        $tbl  = $wpdb->prefix . 'awp_instance_data';
        $rows = $wpdb->get_results("SELECT instance_id, name FROM {$tbl} WHERE status = 'online'");

        $is_still_online = false;
        if ($val_instance && !empty($rows)) {
            foreach ($rows as $r) {
                if ($r->instance_id === $val_instance) {
                    $is_still_online = true;
                    break;
                }
            }
        }

        if (!$is_still_online && !empty($rows)) {
            $val_instance = $rows[0]->instance_id;
            update_option('awp_selected_instance', $val_instance);
        }
        $disable_pay     = (array)get_option('awp_disable_otp_for_payment_methods', []);
        $disable_ship    = (array)get_option('awp_disable_otp_for_shipping_methods', []);

        echo '<div class="wrap">
            <form method="post" action="">
                ' . wp_nonce_field('awp_checkout_settings_save','awp_checkout_settings_nonce', true, false) . '
               
                <div class="instance-card" style="display: none;"> 
                <table class="form-table">
                    <tr>
                        <th>
                            <div>
                            <h4><label>' . esc_html__('Choose Sender', 'awp') . '</label></h4>
                            <p>' . esc_html__('Select the WhatsApp account used to send OTP codes for checkout.', 'awp') . '</p>
                            </div>
                        </th>
                        <td style="width: 22rem;">';
                        global $wpdb;
                        $tbl  = $wpdb->prefix . 'awp_instance_data';
                        $rows = $wpdb->get_results("SELECT instance_id, name FROM {$tbl} WHERE status = 'online'");
                        if (!$rows) {
                            echo '<p>' . esc_html__('No online instances are available.', 'awp') . '</p>';
                        } else {
                            echo '<select name="awp_selected_instance"><option value="">-- ' . esc_html__('Select Instance', 'awp') . ' --</option>';
                            foreach ($rows as $r) {
                                echo '<option value="' . esc_attr($r->instance_id) . '" ' . selected($val_instance, $r->instance_id, false) . '>'
                                    . esc_html($r->name) . '</option>';
                            }
                            echo '</select>';
                        }
                        echo '</td>
                    </tr>
                </table>
                </div>
                
                <div class="awp-card">
                <table class="form-table">
                <tr>
                    <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title">' . esc_html__('Manage Checkout Method', 'awp') . '</h4>
                            <p>' . esc_html__('Configure OTP settings for the checkout process.', 'awp') . '</p>
                        </div>
                    </div>
                    </th>
                    <td>
                        <div class="wawp-setting-card" style="margin: 16px 0;">
                            <div class="wawp-setting-header awp-toggle-switch">
                                <div class="wawp-setting-title">' . esc_html__('Enable OTP verification for Checkout', 'awp') . '</div>
                                <label class="awp-switch">
                                <input type="checkbox" name="awp_enable_otp" value="yes" ' . checked($val_enable_otp, 'yes', false) . ' />
                                <span class="awp-slider round"></span>
                                </label>
                            </div>
                            <div class="wawp-setting-content">
                            <label for="awp_notifications_user_login_message">' . esc_html__('Message Template', 'awp') . '</label>
                            <div style="position:relative;">
                                <div class="placeholder-container-checkout"></div>
                                <textarea id="awp_otp_message_template" class="awp_otp_message_template" name="awp_otp_message_template" rows="5" cols="85">' . esc_textarea($val_template) . '</textarea>
                            </div>
                            </div>
                        </div>
                        <hr class="h-divider" style="margin: 24px 0;">
                        <div class="wawp-option-row">
                            <div class="wawp-setting-title">' . esc_html__('OTP Verification Mode', 'awp') . '</div>
                            <div>
                                <select name="awp_otp_mode">
                                    <option value="enable_for_all" ' . selected($val_otp_mode, 'enable_for_all', false) . '>' . esc_html__('Enable for all users', 'awp') . '</option>
                                    <option value="enable_for_guests" ' . selected($val_otp_mode, 'enable_for_guests', false) . '>' . esc_html__('Enable for guests only', 'awp') . '</option>
                                    <option value="enable_for_loggedin" ' . selected($val_otp_mode, 'enable_for_loggedin', false) . '>' . esc_html__('Enable for logged-in users only', 'awp') . '</option>
                                </select>
                            </div>
                        </div>
                        <hr class="h-divider" style="margin: 24px 0;">
                        <div class="wawp-option-row">
                            <div class="wawp-setting-title">' . esc_html__('Disable OTP for Payment Methods', 'awp') . '</div>
                            <div>';
                                $payment_gateways = WC()->payment_gateways->payment_gateways();
                                if (empty($payment_gateways)) {
                                    echo '<p>' . esc_html__('No payment methods found.', 'awp') . '</p>';
                                } else {
                                    echo '<select name="awp_disable_otp_for_payment_methods[]" class="selectpicker" multiple data-live-search="true" data-width="auto">';
                                    foreach ($payment_gateways as $gateway_id => $gateway) {
                                        echo '<option value="' . esc_attr($gateway_id) . '" ' . selected(in_array($gateway_id, $disable_pay, true), true, false) . '>' 
                                             . esc_html($gateway->title) . ' (' . esc_html($gateway_id) . ')</option>';
                                    }
                                    echo '</select>';
                                    echo '<script>jQuery(document).ready(function(){ jQuery(".selectpicker").selectpicker(); });</script>';
                                }
                            echo '</div>
                        </div>
                        <hr class="h-divider" style="margin: 24px 0;">
                        <div class="wawp-option-row">
                            <div class="wawp-setting-title">' . esc_html__('Disable OTP for Shipping Methods', 'awp') . '</div>
                            <div>';
                                $disabled_shipping_methods = $disable_ship;
                                $all_methods = [];
                                $shipping_zones = WC_Shipping_Zones::get_zones();
                                foreach ($shipping_zones as $zone_data) {
                                    $zone = new WC_Shipping_Zone($zone_data['id']);
                                    $zone_methods = $zone->get_shipping_methods();
                                    foreach ($zone_methods as $method) {
                                        $method_id = method_exists($method, 'get_method_id') ? $method->get_method_id() : (isset($method->id) ? $method->id : __('unknown_method', 'awp'));
                                        $instance_id = isset($method->instance_id) ? $method->instance_id : 0;
                                        $method_id_instance = $method_id . ':' . $instance_id;
                                        $method_title = method_exists($method, 'get_title') ? $method->get_title() : (isset($method->title) ? $method->title : __('Unknown Title','awp'));
                                        $all_methods[$method_id_instance] = $method_title . ' (' . $method_id_instance . ')';
                                    }
                                }
                                $default_zone = new WC_Shipping_Zone(0);
                                $default_zone_methods = $default_zone->get_shipping_methods();
                                foreach ($default_zone_methods as $method) {
                                    $method_id = method_exists($method, 'get_method_id') ? $method->get_method_id() : (isset($method->id) ? $method->id : __('unknown_method', 'awp'));
                                    $instance_id = isset($method->instance_id) ? $method->instance_id : 0;
                                    $method_id_instance = $method_id . ':' . $instance_id;
                                    $method_title = method_exists($method, 'get_title') ? $method->get_title() : (isset($method->title) ? $method->title : __('Unknown Title','awp'));
                                    $all_methods[$method_id_instance] = $method_title . ' (' . $method_id_instance . ')';
                                }
                                if (empty($all_methods)) {
                                    echo '<p>' . esc_html__('No shipping methods found.', 'awp') . '</p>';
                                } else {
                                    echo '<select name="awp_disable_otp_for_shipping_methods[]" class="selectpicker" multiple data-live-search="true" data-width="auto">';
                                    foreach ($all_methods as $id_instance => $label) {
                                        echo '<option value="' . esc_attr($id_instance) . '" ' . selected(in_array($id_instance, $disabled_shipping_methods, true), true, false) . '>' . esc_html($label) . '</option>';
                                    }
                                    echo '</select>';
                                    echo '<script>jQuery(document).ready(function(){ jQuery(".selectpicker").selectpicker(); });</script>';
                                }
                            echo '</div>
                        </div>

                        
                    </td>
                </table>
                </div>
                <input type="hidden" name="awp_checkout_settings_submitted" value="1" />
                <p class="submit"><button type="submit" class="awp-btn primary">' . esc_html__('Save Settings','awp') . '</button></p>
            </form>
        </div>';
    }

    public function load_otp_verification() {
        $enable_otp = get_option('awp_enable_otp', 'no');
        if ($enable_otp !== 'yes') return;
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('woocommerce_after_order_notes', [$this, 'add_otp_verification_popup']);
        add_action('wp_ajax_send_otp', [$this, 'send_otp_ajax_handler']);
        add_action('wp_ajax_nopriv_send_otp', [$this, 'send_otp_ajax_handler']);
        add_action('wp_ajax_verify_otp', [$this, 'verify_otp_ajax_handler']);
        add_action('wp_ajax_nopriv_verify_otp', [$this, 'verify_otp_ajax_handler']);
        add_action('woocommerce_checkout_process', [$this, 'check_if_otp_verified']);
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_script('awp-checkout-js', AWP_PLUGIN_URL . 'assets/js/checkout.js', ['jquery'], '1.0', true);
        wp_enqueue_style('awp-checkout-css', AWP_PLUGIN_URL . 'assets/css/checkout.css', [], '1.0', 'all');
        $trans = [
            'otp_sent_success'     => esc_html__('OTP sent successfully via WhatsApp.', 'awp'),
            'otp_sent_failure'     => esc_html__('Failed to send OTP. Please try again.', 'awp'),
            'otp_verified_success' => esc_html__('OTP verified successfully.', 'awp'),
            'otp_incorrect'        => esc_html__('Incorrect OTP. Please try again.', 'awp')
        ];
        wp_localize_script('awp-checkout-js', 'awp_translations', $trans);
        $otp_mode = get_option('awp_otp_mode', 'enable_for_all');
        $already_verified = 'false';
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $info = $this->get_user_info_from_db($u);
            if ($info && $info->whatsapp_verified === __('Verified', 'awp') && (int)$info->otp_verification_whatsapp === 1) {
                $already_verified = 'true';
            }
        }
        wp_localize_script('awp-checkout-js', 'otpAjax', [
            'ajaxurl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('otp-ajax-nonce'),
            'isLoggedIn' => is_user_logged_in() ? 'true' : 'false',
            'otpMode'    => $otp_mode,
            'verified'   => $already_verified
        ]);
        $disabled_payment_methods = (array) get_option('awp_disable_otp_for_payment_methods', []);
        wp_localize_script('awp-checkout-js', 'awpDisable', [
            'disableMethods' => $disabled_payment_methods,
        ]);
        $disabled_shipping_methods = (array) get_option('awp_disable_otp_for_shipping_methods', []);
        wp_localize_script('awp-checkout-js', 'awpDisableShipping', [
            'disableMethods' => $disabled_shipping_methods,
        ]);
    }

    public function add_otp_verification_popup() {
        
        echo '<div id="awp_otp_popup" class="awp-otp-popup" style="display:none;">
            <div class="awp-otp-popup-content">
                <div class="awp-popup-message"></div>
                <div class="awp-otp-box">
                    <div class="awp-icon-wrapper"><i id="otp-icon" class="ri-whatsapp-line"></i></div>
                    <h3 class="awp-title">' . esc_html__('Confirm your order', 'awp') . '</h3>
                    <p class="awp-desc">' . wp_kses_post(__('To complete your order, enter the 6-digit code sent via WhatsApp to <span id="user_phone_number"></span>', 'awp')) . '</p>
                    <span class="awp-otp-popup-close">&times;</span>
                </div>
                <div class="awp-otp-content">
                    <input type="tel" id="awp_otp_input" pattern="[0-9]*" inputmode="numeric" maxlength="6" placeholder="' . esc_attr__('Enter 6-digit code', 'awp') . '" />
                    <div class="awp-btn-group">
                        <button type="button" class="awp-btn" id="awp_verify_otp_btn">'
                            . esc_html__('Confirm order', 'awp') .
                        '</button>
                        <button type="button" class="awp-btn" id="awp_resend_otp_btn" disabled>'
                            . esc_html__('Resend code', 'awp') .
                            '<span id="awp_resend_timer"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>';
    }

    public function send_otp_ajax_handler() {
        check_ajax_referer('otp-ajax-nonce', 'security');
        $phone_number   = sanitize_text_field($_POST['phone_number'] ?? '');
        $checkout_first = sanitize_text_field($_POST['first_name']   ?? '');
        $checkout_last  = sanitize_text_field($_POST['last_name']    ?? '');
        $checkout_email = sanitize_email($_POST['email']             ?? '');

        if (!$phone_number) {
            wp_send_json_error(__('Phone number is missing.', 'awp'));
        }

        $numeric_phone = preg_replace('/\D/', '', $phone_number);
        $block_list = get_option('awp_block_list', []);
        if (in_array($numeric_phone, $block_list, true)) {
            wp_send_json_error(__('This phone number is blocked by the system.', 'awp'));
        }

        if (is_user_logged_in()) {
            $this->sync_logged_in_user_data($checkout_first, $checkout_last, $checkout_email, $phone_number);
        }

        $otp = rand(100000, 999999);
        WC()->session->set('otp', $otp);
        WC()->session->set('otp_verified', false);

        $sel = get_option('awp_selected_instance', '');
        if (!$sel) {
            wp_send_json_error(__('No instance selected. Please choose an instance in settings.', 'awp'));
        }

        global $wpdb;
        $tbl  = $wpdb->prefix . 'awp_instance_data';
        $inst = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tbl} WHERE instance_id = %s AND status = 'online'",
            $sel
        ));
        if (!$inst) {
            wp_send_json_error(__('Selected instance is not available or offline.', 'awp'));
        }

        $template = get_option('awp_otp_message_template', __('Hi {{name}} {{last}}, {{otp}} is your checkout OTP.', 'awp'));
        // NEW
        $base_replacements = [
        '{{otp}}'   => $otp,
        '{{name}}'  => $checkout_first ?: '',
        '{{last}}'  => $checkout_last  ?: '',
        '{{email}}' => $checkout_email ?: '',
    
        '{{wc_billing_first_name}}' => $checkout_first ?: '',
        '{{wc_billing_last_name}}'  => $checkout_last  ?: '',
        '{{wc_billing_phone}}'      => $phone_number   ?: '',
        '{{wp-first-name}}'         => $checkout_first ?: '',
        '{{wp-last-name}}'          => $checkout_last  ?: '',
        '{{wp-email}}'              => $checkout_email ?: '',
        '{{user_first_last_name}}'  => trim("$checkout_first $checkout_last"),
    ];
    
    $base_replacements += $this->build_checkout_replacements();
        
        /**
         * Let the central parser do all the work.
         *  - 0  -> no order object (checkout page)
         *  - current user ID (or 0 for guest) so user placeholders are filled if possible
         */
        $msg = AWP_Message_Parser::parse_message_placeholders(
            $template,
            $base_replacements,
            0,
            is_user_logged_in() ? get_current_user_id() : 0
        );


        $response = Wawp_Api_Url::send_message(
            $inst->instance_id,
            $inst->access_token,
            $phone_number,
            $msg
        );

        if ($response['status'] !== 'success') {
            wp_send_json_error(__('Failed to send OTP via WhatsApp. Please try again.', 'awp'));
        }

        $response_to_log = $response;
        if (isset($response['full_response'])) {
            $response_to_log = $response['full_response'];
        }
        
        $this->log_message_send([
            'user_id'         => is_user_logged_in() ? get_current_user_id() : 0,
            'order_id'        => 0,
            'customer_name'   => trim($checkout_first . ' ' . $checkout_last),
            'whatsapp_number' => $phone_number,
            'message'         => $msg, // Log the actual OTP message
            'message_type'    => __('Checkout - OTP Request', 'awp'),
            'wawp_status'     => wp_json_encode($response_to_log),
            'instance_id'     => $inst->instance_id,
            'access_token'    => $inst->access_token,
        ]);

        wp_send_json_success(__('OTP sent successfully via WhatsApp.', 'awp'));
    }
    
    /**
     * Build replacements for checkout form values (guests or logged-in).
     * Turns billing_first_name → {{wc_billing_first_name}} + {{wc-billing-first-name}}
     *      shipping_city      → {{wc_shipping_city}}        + {{wc-shipping-city}}
     */
    private function build_checkout_replacements() {
        $map = [];
    
        foreach ($_POST as $key => $value) {
            // only capture the standard checkout fields
            if (preg_match('/^(billing|shipping)_[a-z0-9_]+$/i', $key)) {
                $san = sanitize_text_field($value);
    
                // underscore version (legacy placeholders)
                $place_underscore = '{{' . str_replace('_', ' ', $key) . '}}'; // e.g. {{billing first_name}}
                $place_wc         = '{{wc_' . $key . '}}';       
                
             $place_underscore = '{{' . $key . '}}';                        
    
                // hyphen version (new placeholders)
                $place_hyphen = '{{wc-' . str_replace('_', '-', $key) . '}}'; // e.g. {{wc-billing-first-name}}
    
                $map[$place_underscore] = $san;
                $map[$place_wc]         = $san;
                $map[$place_hyphen]     = $san;
            }
        }
    
        // for convenience map first/last/email to the wp-* aliases
        if (isset($map['{{wc_billing_first_name}}'])) {
            $map['{{wp-first-name}}'] = $map['{{wc_billing_first_name}}'];
        }
        if (isset($map['{{wc_billing_last_name}}'])) {
            $map['{{wp-last-name}}']  = $map['{{wc_billing_last_name}}'];
        }
        if (isset($_POST['billing_email'])) {
            $email                    = sanitize_email($_POST['billing_email']);
            $map['{{wp-email}}']      = $email;
            $map['{{email}}']         = $email; // if you still use {{email}}
        }
    
        return $map;
    }

    public function verify_otp_ajax_handler() {
        check_ajax_referer('otp-ajax-nonce', 'security');
        $user_otp = sanitize_text_field($_POST['otp'] ?? '');
        $correct  = WC()->session->get('otp');
        if ($user_otp == $correct) {
            WC()->session->set('otp_verified', true);
            if (is_user_logged_in()) {
                $this->update_whatsapp_verification(get_current_user_id());
            }
            wp_send_json_success(esc_html__('OTP verified successfully.', 'awp'));
        }
        wp_send_json_error(esc_html__('Incorrect OTP. Please try again.', 'awp'));
    }

    public function check_if_otp_verified() {
        $enable_otp = get_option('awp_enable_otp', 'no');
        $mode       = get_option('awp_otp_mode', 'enable_for_all');
        if ($enable_otp !== 'yes') return;

        $disabled_payments       = (array) get_option('awp_disable_otp_for_payment_methods', []);
        $chosen_payment_method   = WC()->session->get('chosen_payment_method');
        if (in_array($chosen_payment_method, $disabled_payments, true)) {
            return;
        }
        $disabled_shippings      = (array) get_option('awp_disable_otp_for_shipping_methods', []);
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (!empty($chosen_shipping_methods) && is_array($chosen_shipping_methods)) {
            $chosen_shipping = $chosen_shipping_methods[0] ?? '';
            if (in_array($chosen_shipping, $disabled_shippings, true)) {
                return;
            }
        }
        $logged_in = is_user_logged_in();
        if ($logged_in) {
            global $wpdb;
            $u = get_current_user_id();
            $info = $this->get_user_info_from_db($u);
            $checkout_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';
            if ($info && !empty($checkout_phone) && $checkout_phone !== $info->phone) {
                $wpdb->update(
                    $wpdb->prefix . 'awp_user_info',
                    ['whatsapp_verified' => __('Not Verified', 'awp'), 'otp_verification_whatsapp' => 0],
                    ['user_id' => $u],
                    ['%s','%d'],
                    ['%d']
                );
                WC()->session->set('otp_verified', false);
            }
        }
        switch ($mode) {
            case 'enable_for_all':
                $this->verify_or_abort();
                break;
            case 'enable_for_guests':
                if (!$logged_in) $this->verify_or_abort();
                break;
            case 'enable_for_loggedin':
                if ($logged_in) $this->verify_or_abort();
                break;
        }
    }

    private function verify_or_abort() {
        if (is_user_logged_in()) {
            $u = get_current_user_id();
            $info = $this->get_user_info_from_db($u);
            $checkout_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';

            if ($info && !empty($checkout_phone) && $checkout_phone !== $info->phone) {
                wc_add_notice(
                    __('You changed your phone number. Please verify OTP again.', 'awp'),
                    'error'
                );
                WC()->session->set('otp_verified', false);
                return; 
            }

            if ($info && $info->whatsapp_verified === __('Verified', 'awp') && (int)$info->otp_verification_whatsapp === 1) {
                return;
            }

            if (!WC()->session->get('otp_verified')) {
                wc_add_notice(
                    __('Please verify the OTP before placing your order.', 'awp'),
                    'error'
                );
            }
        } else {
            if (!WC()->session->get('otp_verified')) {
                wc_add_notice(
                    __('Please verify the OTP before placing your order.', 'awp'),
                    'error'
                );
            }
        }
    }

    private function sync_logged_in_user_data($first, $last, $email, $phone) {
        $uid = get_current_user_id();
        $checkout_email = sanitize_email($email ?: '');
        $checkout_first = sanitize_text_field($first ?: '');
        $checkout_last  = sanitize_text_field($last  ?: '');
        $wp_user = get_userdata($uid);
        $wp_email = $wp_user->user_email;
        $final_email = $checkout_email ?: $wp_email;
        wp_update_user(['ID' => $uid, 'user_email' => $final_email]);
        if ($checkout_first) update_user_meta($uid, 'first_name', $checkout_first);
        if ($checkout_last)  update_user_meta($uid, 'last_name', $checkout_last);

        global $wpdb;
        $table = $wpdb->prefix . 'awp_user_info';
        $info  = $this->get_user_info_from_db($uid);
        if ($info) {
            $wpdb->update(
                $table,
                [
                    'first_name' => $checkout_first ?: $info->first_name,
                    'last_name'  => $checkout_last  ?: $info->last_name,
                    'email'      => $final_email,
                    'phone'      => $phone
                ],
                ['user_id' => $uid],
                ['%s','%s','%s','%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table, [
                'user_id'                   => $uid,
                'first_name'                => $checkout_first,
                'last_name'                 => $checkout_last,
                'email'                     => $final_email,
                'phone'                     => $phone,
                'password'                  => '',
                'otp_verification_whatsapp' => 0,
                'otp_verification_email'    => 0,
                'whatsapp_verified'         => __('Not Verified', 'awp'),
                'created_at'                => current_time('mysql'),
            ]);
        }
    }

    private function log_message_send($data = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'awp_notifications_log';
    $insert_data = [
        'user_id'         => isset($data['user_id']) ? (int) $data['user_id'] : 0,
        'order_id'        => isset($data['order_id']) ? (int) $data['order_id'] : 0,
        'customer_name'   => isset($data['customer_name']) ? sanitize_text_field($data['customer_name']) : '',
        'sent_at'         => current_time('mysql'),
        'whatsapp_number' => isset($data['whatsapp_number']) ? sanitize_text_field($data['whatsapp_number']) : '',
        'message'         => isset($data['message']) ? wp_kses_post($data['message']) : '',
        'image_attachment'=> '',
        'message_type'    => isset($data['message_type']) ? sanitize_text_field($data['message_type']) : __('Checkout - OTP Request', 'awp'),
        'wawp_status'     => isset($data['wawp_status']) ? sanitize_text_field($data['wawp_status']) : __('Unknown', 'awp'),
        'resend_id'       => null,
        'instance_id'     => isset($data['instance_id']) ? sanitize_text_field($data['instance_id']) : null,
        'access_token'    => isset($data['access_token']) ? sanitize_text_field($data['access_token']) : null,
    ];
    $wpdb->insert($table, $insert_data, ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
}

    private function get_user_info_from_db($user_id) {
        global $wpdb;
        $t = $wpdb->prefix . 'awp_user_info';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id = %d", $user_id));
    }

    private function update_whatsapp_verification($user_id) {
        global $wpdb;
        $t = $wpdb->prefix . 'awp_user_info';
        $wpdb->update(
            $t,
            ['whatsapp_verified' => __('Verified', 'awp'), 'otp_verification_whatsapp' => 1],
            ['user_id' => $user_id],
            ['%s','%d'],
            ['%d']
        );
    }
    
}

new awp_checkout_otp();
