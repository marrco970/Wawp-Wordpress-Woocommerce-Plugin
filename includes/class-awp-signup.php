<?php

if (!defined('ABSPATH')) exit;

if (!class_exists('AWP_Signup')) {
    class AWP_Signup {
        
        private static $instance = null;
        private $table_name;
        private $nonce_action = 'awp_signup_nonce';
        private $nonce_field = 'awp_signup_nonce_field';
        private $db_manager;

        public static function get_instance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct() {
            $this->db_manager = new AWP_Database_Manager();
            $this->table_name = $this->db_manager->get_signup_settings_table_name();
            add_shortcode('wawp_signup_form', [$this, 'render_signup_form']);
            add_action('wp_ajax_awp_signup_form_submit', [$this, 'ajax_signup_form_submit']);
            add_action('wp_ajax_nopriv_awp_signup_form_submit', [$this, 'ajax_signup_form_submit']);
            add_action('wp_ajax_awp_signup_verify_otp', [$this, 'ajax_verify_otp']);
            add_action('wp_ajax_nopriv_awp_signup_verify_otp', [$this, 'ajax_verify_otp']);
            add_action('wp_ajax_awp_signup_resend_otp', [$this, 'ajax_resend_otp']);
            add_action('wp_ajax_nopriv_awp_signup_resend_otp', [$this, 'ajax_resend_otp']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_login', [$this, 'user_logged_in'], 10, 2);
            add_action('awp_cron_clear_expired_otps', [$this, 'clear_expired_otps']);
            add_action('show_user_profile', [$this, 'add_custom_user_profile_fields']);
            add_action('edit_user_profile', [$this, 'add_custom_user_profile_fields']);
            add_action('personal_options_update', [$this, 'save_custom_user_profile_fields']);
            add_action('edit_user_profile_update', [$this, 'save_custom_user_profile_fields']);
        }

        public function __clone() {}
        
        public function __wakeup() {}

        public function enqueue_scripts() {
            wp_enqueue_style('bootstrap-icons', AWP_PLUGIN_URL . 'assets/css/resources/bootstrap-icons.css', [], '1.11.3');
            wp_enqueue_style('awp-signup-style', AWP_PLUGIN_URL . 'assets/css/awp-signup.css', [], AWP_PLUGIN_VERSION);
            wp_enqueue_style('jquery-ui-autocomplete', AWP_PLUGIN_URL . 'assets/css/resources/jquery-ui.css', [], '1.14.1');
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_enqueue_script('awp-signup-script', AWP_PLUGIN_URL . 'assets/js/awp-signup.js', ['jquery', 'jquery-ui-autocomplete'], AWP_PLUGIN_VERSION, true);
            $settings = $this->get_settings();
            wp_localize_script('awp-signup-script', 'AWP_Signup_Params', [
                'ajax_url'          => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce($this->nonce_action),
                'redirect_url'      => esc_url($settings['signup_redirect_url']),
                'email_domains'     => ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com'],
                'otp_method'        => $settings['otp_method'],
                'password_strong'   => $settings['enable_strong_password'],
                'password_enabled'  => $settings['password_enabled'],
                'password_required' => $settings['password_required'],
            ]);
            wp_localize_script('awp-signup-script', 'AWP_Signup_L10n', [
                'fieldRequired'          => __('This field is required.', 'awp'),
                'checkYourEmail'         => __('Check your Email', 'awp'),
                'checkYourWhatsApp'      => __('Check your WhatsApp', 'awp'),
                'weSentCode'            => __('We’ve sent you a 6-digit code to <br><b>%s</b>', 'awp'),
                'resendCode'             => __('Resend Code', 'awp'),
                'wrongEmail'             => __('Wrong e-mail?', 'awp'),
                'reEnterEmail'           => __('Please re-enter your email', 'awp'),
                'wrongWhatsApp'          => __('Wrong WhatsApp?', 'awp'),
                'reEnterNumber'          => __('Please re-enter your number', 'awp'),
                'unexpectedError'        => __('An unexpected error occurred. Please try again.', 'awp'),
                'unexpectedErrorResend'  => __('An unexpected error occurred while resending OTP. Please try again.', 'awp'),
                'invalidEmail'           => __('Please enter a valid email address.', 'awp'),
                'invalidNumber'          => __('Please enter a valid number.', 'awp'),
            ]);
            add_action('wp_head', [$this, 'inject_custom_button_styles']);
        }

        private function get_lucide_icon_for_field_type($type) {
        switch ($type) {
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

        public function get_settings() {
            $settings = $this->db_manager->get_signup_settings();
            $defaults = [
                'selected_instance' => 0,
                'enable_otp' => 1,
                'otp_method' => 'whatsapp',
                'otp_message' => __('Your OTP code is: {{otp}}', 'awp'),
                'otp_message_email' => __('Your OTP code is: {{otp}}', 'awp'),
                'field_order' => 'first_name,last_name,email,phone,password',
                'signup_redirect_url' => home_url(),
                'signup_logo' => '',
                'signup_title' => '',
                'signup_description' => '',
                'button_background_color' => '#0073aa',
                'button_text_color' => '#ffffff',
                'button_hover_background_color' => '#005177',
                'button_hover_text_color' => '#ffffff',
                'enable_strong_password' => 0,
                'enable_password_reset' => 1,
                'auto_login' => 1,
                'first_name_enabled' => 1,
                'first_name_required' => 1,
                'last_name_enabled' => 1,
                'last_name_required' => 1,
                'email_enabled' => 1,
                'email_required' => 1,
                'phone_enabled' => 1,
                'phone_required' => 1,
                'password_enabled' => 1,
                'password_required' => 1,
                'signup_custom_css' => '',
                'custom_fields' => [] 
            ];

            $merged_settings = wp_parse_args($settings, $defaults);
            if (!is_array($merged_settings['custom_fields'])) {
                $merged_settings['custom_fields'] = json_decode($merged_settings['custom_fields'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $merged_settings['custom_fields'] = [];
                }
            }
            return $merged_settings;
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'awp'));
            }
            $banned_msg = get_transient('siteB_banned_msg');
            $token = get_option('mysso_token');
            $user_data = get_transient('siteB_user_data');
            if ($banned_msg) {
                echo '<div class="wrap">
                    <h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Signup', 'awp') . '</h1>
                    <p style="color:red;">' . esc_html(Wawp_Global_Messages::get('blocked_generic')) . '</p>
                </div>';
                return;
            }
            if (!$token) {
                echo '<div class="wrap">
                    <h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('OTP Signup', 'awp') . '</h1>
                    <p>' . esc_html(Wawp_Global_Messages::get('need_login')) . '</p>
                </div>';
                return;
            }
            $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
            if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
                echo '<div class="wrap">
                    <h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Signup', 'awp') . '</h1>
                    <p style="color:red;">' . esc_html(Wawp_Global_Messages::get('not_active_site')) . '</p>
                </div>';
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['awp_signup_settings'])) {
                $this->save_signup_settings();
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'awp') . '</p></div>';
            }
            $settings = $this->get_settings();
            echo '<div class="wrap awp-admin-page"><form method="post" action="" id="awp-settings-form">';
            wp_nonce_field('awp_save_signup_settings', 'awp_save_signup_settings_nonce');
            echo '<div class="instance" style="display: none;">';
            echo '<table class="form-table">';
            echo '<tr style="flex-wrap: nowrap;">
                    <th>
                        <div>
                        <h4><label>' . esc_html__('Choose Sender', 'awp') . '</label></h4>
                        <p>' . esc_html__('Select the WhatsApp account used to send OTP codes for signup.', 'awp') . '</p>
                        </div>
                    </th>
                    <td style="max-width: 22rem;">';
            $selected_instance = (int) $settings['selected_instance'];
            global $wpdb;
            $online_instances = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}awp_instance_data WHERE status = 'online'");
            if (!$online_instances) {
                echo '<p>' . esc_html__('No online instances available.', 'awp') . '</p>';
            } else {
                if (!$selected_instance && !empty($online_instances)) {
                    $selected_instance = $online_instances[0]->id;
                }
                echo '<select name="awp_signup_settings[selected_instance]" id="selected_instance">';
                foreach ($online_instances as $instance) {
                    $sel = selected($selected_instance, $instance->id, false);
                    echo '<option value="' . esc_attr($instance->id) . '"' . $sel . '>' . esc_html($instance->name) . '</option>';
                }
                echo '</select>';
            }
            echo '</td></tr>';
            echo '</table>';
            echo '</div>';
            
            echo '<table class="form-table">';
            echo '<tr><td>';
                echo '<div class="card-header_row">';
                echo '<div class="card-header">
                        <h4 class="card-title">' . esc_html__('Enable Signup Verification', 'awp') . '</h4>
                        <p>' . esc_html__('Enable the OTP verification for new users signup.', 'awp') . '</p>
                    </div>';
                    $enabled_otp = !empty($settings['enable_otp']);
                echo '<label class="awp-switch"><input type="checkbox" name="awp_signup_settings[enable_otp]" value="1" ' . checked($enabled_otp, true, false) . ' /><span class="awp-slider"></span></label>
                </div>';
            echo '</td></tr>';

            echo '<tr>
                    <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title">' . esc_html__('Standard Signup Form Fields', 'awp') . '</h4>
                            <p>' . esc_html__('Customize the default fields for your signup form.', 'awp') . '</p>
                        </div>
                         <div class="card-header_row">
                        <div class="card-header">
                            <button type="button" class="awp-btn secondary" id="awp-add-custom-field-button">           
                       <span class="dashicons dashicons-plus"></span> ' . esc_html__('Add New Custom Field', 'awp') . '            
                       </button>
                        </div>
                    </div>
                    </th>
                    <td>';
            echo '<div class="wawp-setting-card" style="padding: 0; overflow: hidden;">';    
            $fields = [
                'first_name' => __('First Name', 'awp'),
                'last_name'  => __('Last Name', 'awp'),
                'email'      => __('Email', 'awp'),
                'phone'      => __('Phone', 'awp'),
                'password'   => __('Password', 'awp')
            ];
            $field_order = explode(',', $settings['field_order']);
            $available_standard_fields = array_keys($fields);

            $reordered_field_order = [];
            foreach ($field_order as $key) {
                if (in_array($key, $available_standard_fields)) {
                    $reordered_field_order[] = $key;
                } elseif (isset($settings['custom_fields'][$key])) {
                    $reordered_field_order[] = $key;
                }
            }
            $settings['field_order'] = implode(',', $reordered_field_order);

            echo '<table class="awp-fields-table" id="awp-fields-sortable"><thead><tr><th>&#9776;</th><th>' . esc_html__('Field Name', 'awp') . '</th><th>' . esc_html__('Enable', 'awp') . '</th><th>' . esc_html__('Required', 'awp') . '</th><th>' . esc_html__('Actions', 'awp') . '</th></tr></thead><tbody>';

            foreach ($reordered_field_order as $field_key) {
                $is_standard = in_array($field_key, $available_standard_fields);
                $field_settings = $is_standard ? $settings : ($settings['custom_fields'][$field_key] ?? []);
                $field_label = $is_standard ? $fields[$field_key] : ($field_settings['label'] ?? $field_key);
                $field_type = $is_standard ? 'standard' : ($field_settings['type'] ?? 'text');

                $enabled = isset($field_settings["{$field_key}_enabled"]) ? $field_settings["{$field_key}_enabled"] : ($field_settings['enabled'] ?? 0);
                $required = isset($field_settings["{$field_key}_required"]) ? $field_settings["{$field_key}_required"] : ($field_settings['required'] ?? 0);

                $enabled_name = $is_standard ? "awp_signup_settings[{$field_key}][enabled]" : "awp_signup_settings[custom_fields][{$field_key}][enabled]";
                $required_name = $is_standard ? "awp_signup_settings[{$field_key}][required]" : "awp_signup_settings[custom_fields][{$field_key}][required]";

                echo '<tr data-field-key="' . esc_attr($field_key) . '" data-field-type="' . esc_attr($field_type) . '" data-is-standard="' . ($is_standard ? '1' : '0') . '">';
                echo '<td class="awp-drag-handle" style="cursor: move; text-align: center;">&#9776;</td>';
                echo '<td>' . esc_html($field_label) . '</td>';
                echo '<td><label class="awp-switch"><input type="checkbox" name="' . esc_attr($enabled_name) . '" value="1" ' . checked($enabled, true, false) . ' /><span class="awp-slider"></span></label></td>';
                echo '<td><label class="awp-switch"><input type="checkbox" name="' . esc_attr($required_name) . '" value="1" ' . checked($required, true, false) . ' /><span class="awp-slider"></span></label></td>';
                echo '<td>';
                if ($is_standard) {
                 
                    echo esc_html__("Primary Key Can't edit or deleted", 'awp');
                } else {
                    echo '<div class="awp-summary-actions">';
                    echo '<button type="button" class="awp-btn edit-plain awp-edit-custom-field" data-field-key="' . esc_attr($field_key) . '">' . esc_html__('Edit', 'awp') . '</button>';
                    echo '<button type="button" class="awp-btn delete-plain awp-delete-custom-field" data-field-key="' . esc_attr($field_key) . '">' . esc_html__('Delete', 'awp') . '</button>';
                 echo '</div>';
                 
                    echo '<input type="hidden" name="awp_signup_settings[custom_fields][' . esc_attr($field_key) . '][label]" value="' . esc_attr($field_settings['label'] ?? '') . '" />';
                    echo '<input type="hidden" name="awp_signup_settings[custom_fields][' . esc_attr($field_key) . '][type]" value="' . esc_attr($field_settings['type'] ?? 'text') . '" />';
                    if (in_array($field_type, ['checkbox', 'radio']) && !empty($field_settings['options'])) {
                        $options_string = '';
                        foreach($field_settings['options'] as $option) {
                            $options_string .= esc_attr($option['label']) . '|' . esc_attr($option['value']) . "\n";
                        }
                        echo '<textarea name="awp_signup_settings[custom_fields][' . esc_attr($field_key) . '][options]" style="display:none;">' . trim($options_string) . '</textarea>';
                    }
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<input type="hidden" id="awp_field_order" name="awp_signup_settings[field_order]" value="' . esc_attr($settings['field_order']) . '" />';                
            echo '</div>'; 
            echo '</td></tr>';


            
            $method = isset($settings['otp_method']) ? $settings['otp_method'] : 'whatsapp';
            $otp_msg = isset($settings['otp_message']) ? $settings['otp_message'] : '';
            $otp_msg_email = isset($settings['otp_message_email']) ? $settings['otp_message_email'] : '';
            
            echo '<tr><th>
                <div class="card-header_row">
                    <div class="card-header">
                        <h4 class="card-title">' . esc_html__('OTP Verification Method', 'awp') . '</h4>
                        <p>' . esc_html__('Select the signup verification method.', 'awp') . '</p>
                    </div>
                </div></th>';
            
            echo '<td class="awp-cards"><div class="awp-login-method-radio">';
            echo '<label class="awp-radio-button whatsapp">
              <input type="radio" name="awp_signup_settings[otp_method]" value="whatsapp" ' . checked($method, 'whatsapp', false) . '>
              <i class="ri-whatsapp-line awp-radio-icon" aria-hidden="true"></i>
              <span>' . esc_html__('WhatsApp', 'awp') . '</span>
            </label>';
            echo '<label class="awp-radio-button email">
              <input type="radio" name="awp_signup_settings[otp_method]" value="email" ' . checked($method, 'email', false) . '>
              <i class="ri-mail-line awp-radio-icon" aria-hidden="true"></i>
              <span>' . esc_html__('Email', 'awp') . '</span>
            </label>';
            echo '</div>';
            echo '<div class="txt-setting">';
            echo '<div id="otp_message_whatsapp_container">
            <label>' . esc_html__('Message Template', 'awp') . '</label>
            <div style="position:relative;">
            <div class="placeholder-container-signup"></div>
            <textarea id="awp_signup_otp_message" name="awp_signup_settings[otp_message]" rows="4" cols="50" class="large-text">' . esc_textarea($otp_msg) . '</textarea></div>';
            echo '</div>';
            
            echo '<div id="otp_message_email_container">';
            echo '<label>' . esc_html__('Email Template', 'awp') . '</label>';
            wp_editor($otp_msg_email, 'awp_signup_otp_message_email', [
                'textarea_name' => 'awp_signup_settings[otp_message_email]',
                'media_buttons' => true,
                'textarea_rows' => 5,
            ]);
            echo '</div>';
            echo '</div>';
            echo '</td></tr>';

            echo '<tr><th>
                <div class="card-header_row">
                    <div class="card-header">
                        <h4 class="card-title">' . esc_html__('Advanced Settings', 'awp') . '</h4>
                        <p>' . esc_html__('Set up additional options for signup.', 'awp') . '</p>
                    </div>
                </div></th>';

            echo'<td><div class="txt-setting">
                <label>' . esc_html__('Redirect URL', 'awp') . '</label>';
                $redirect_url = $settings['signup_redirect_url'];
            echo '<input type="url" name="awp_signup_settings[signup_redirect_url]" value="' . esc_attr($redirect_url) . '" class="regular-text" />
                <p class="description">' . esc_html__('Set the URL where users will be redirected after signing up. (optional)', 'awp') . '</p>
            </div>
            <hr class="h-divider" style="margin: 24px 0;">';
            echo' <div class="txt-setting" style="flex-direction: row;align-items: center;">
                <label style="margin: 0 !important;width: 22rem;">' . esc_html__('Enable Strong Passwords', 'awp') . '</label>';
                $strong_pass = !empty($settings['enable_strong_password']);
                echo '<label class="awp-switch"><input type="checkbox" name="awp_signup_settings[enable_strong_password]" value="1" ' . checked($strong_pass, true, false) . ' /><span class="awp-slider"></span></label>
            </div>
            <hr class="h-divider" style="margin: 24px 0;">';
            echo' <div class="txt-setting" style="flex-direction: row;align-items: center;">
                <label style="margin: 0 !important;width: 22rem;">' . esc_html__('Enable Password Reset Link', 'awp') . '</label>';
                $pwd_reset = !empty($settings['enable_password_reset']);
                echo '<label class="awp-switch"><input type="checkbox" name="awp_signup_settings[enable_password_reset]" value="1" ' . checked($pwd_reset, true, false) . ' /><span class="awp-slider"></span></label>
            </div>
            <hr class="h-divider" style="margin: 24px 0;">';
            echo' <div class="txt-setting" style="flex-direction: row;align-items: center;">
                <label style="margin: 0 !important;width: 22rem;">' . esc_html__('Enable Auto Login After Signup', 'awp') . '</label>';
                $auto_login = !empty($settings['auto_login']);
                echo '<label class="awp-switch"><input type="checkbox" name="awp_signup_settings[auto_login]" value="1" ' . checked($auto_login, true, false) . ' /><span class="awp-slider"></span></label>
            </div>';
            echo '</td></tr>';

            echo '<tr><th>
                <div class="card-header_row">
                    <div class="card-header">
                        <h4 class="card-title">' . esc_html__('Form Style', 'awp') . '</h4>
                        <p>' . esc_html__('Customize the appearance of your signup form.', 'awp') . '</p>
                    </div>
                </div></th><td>
                <div style="display: flex; flex-direction: column;">';
            $logo_url = $settings['signup_logo'];
            echo '<div class="awp-logo-upload">';
            if ($logo_url) {
                echo '<img id="signup_logo_preview" src="' . esc_url($logo_url) . '" />';
            } else {
                echo '<img id="signup_logo_preview" />';
            }
            echo '<input type="hidden" id="signup_logo" name="awp_signup_settings[signup_logo]" value="' . esc_attr($logo_url) . '" class="regular-text" />';
            echo ' <button type="button" class="button" id="upload_logo_button">' . esc_html__('Upload Logo', 'awp') . '</button>';
            echo ' <button type="button" class="button" id="remove_logo_button"><i class="ri-close-line"></i></button>';
            echo '</div>';
            echo '<hr class="h-divider" style="margin: 24px 0;">';
            $title = $settings['signup_title'];
            echo '<div class="txt-setting">
              <label>' . esc_html__('Page Title', 'awp') . '</label>
              <input type="text" id="signup_title" name="awp_signup_settings[signup_title]" value="' . esc_attr($title) . '" class="regular-text" />
              <p>' . esc_html__('Enter a title to display above the signup form (optional)', 'awp') . '</p>
            </div>';
            echo '<hr class="h-divider" style="margin: 24px 0;">';
            echo '<div class="txt-setting">
              <label style="position: absolute;top: 0;">' . esc_html__('Description', 'awp') . '</label>';
            $description = $settings['signup_description'];
            wp_editor($description, 'signup_description', [
                'textarea_name' => 'awp_signup_settings[signup_description]',
                'media_buttons' => false,
                'textarea_rows' => 6
            ]);
            echo '<p>' . esc_html__('Enter a description to display below the title (optional)', 'awp') . '</p>
            </div>';
            echo '</div>';
            echo '</td></tr>';

            echo '<tr><th>
                <div class="card-header_row">
                    <div class="card-header">
                        <h4 class="card-title">' . esc_html__('Button Color', 'awp') . '</h4>
                        <p>' . esc_html__('Customize the colors of buttons on the signup form.', 'awp') . '</p>
                    </div>
                </div></th><td>';
                
            echo '<hr class="h-divider" style="margin-bottom: 1.25rem;">';
            echo '<div class="awp-cards" style="flex-direction: row;justify-content: space-between;">';
            echo '<div class="txt-setting">';
            echo '<label>' . esc_html__('Background Color', 'awp') . '</label>';
            $bg = $settings['button_background_color'];
            echo '<input type="text" name="awp_signup_settings[button_background_color]" value="' . esc_attr($bg) . '" class="awp-color-picker" data-default-color="#0073aa" />';
            echo '</div>';
            echo '<div class="txt-setting">';
            echo '<label>' . esc_html__('Background Hover Color', 'awp') . '</label>';
            $hbg = $settings['button_hover_background_color'];
            echo '<input type="text" name="awp_signup_settings[button_hover_background_color]" value="' . esc_attr($hbg) . '" class="awp-color-picker" data-default-color="#005177" />';
            echo '</div>';
            echo '<div class="txt-setting">';
            echo '<label>' . esc_html__('Text Color', 'awp') . '</label>';
            $tc = $settings['button_text_color'];
            echo '<input type="text" name="awp_signup_settings[button_text_color]" value="' . esc_attr($tc) . '" class="awp-color-picker" data-default-color="#ffffff" />';
            echo '</div>';
            echo '<div class="txt-setting">';
            echo '<label>' . esc_html__('Text Hover Color', 'awp') . '</label>';
            $htc = $settings['button_hover_text_color'];
            echo '<input type="text" name="awp_signup_settings[button_hover_text_color]" value="' . esc_attr($htc) . '" class="awp-color-picker" data-default-color="#ffffff" />';
            echo '</div>';
            echo '</div>';
            echo '</td></tr>';
            echo '</table>';

            submit_button();
            echo '</form></div>';
            $this->render_custom_field_modal();
        }

        public function save_signup_settings() {
            if (!isset($_POST['awp_save_signup_settings_nonce']) || !wp_verify_nonce($_POST['awp_save_signup_settings_nonce'], 'awp_save_signup_settings')) {
                return;
            }

            $input = $_POST['awp_signup_settings'];
            $sanitized = [
                'selected_instance' => isset($input['selected_instance']) ? intval($input['selected_instance']) : 0,
                'enable_otp' => isset($input['enable_otp']) ? 1 : 0,
                'otp_method' => (isset($input['otp_method']) && in_array($input['otp_method'], ['whatsapp', 'email'])) ? sanitize_text_field($input['otp_method']) : 'whatsapp',
                'otp_message' => isset($input['otp_message']) ? sanitize_textarea_field($input['otp_message']) : __('Your OTP code is: {{otp}}', 'awp'),
                'otp_message_email' => isset($input['otp_message_email']) ? wp_kses_post($input['otp_message_email']) : __('Your OTP code is: {{otp}}', 'awp'),
                'field_order' => isset($input['field_order']) ? sanitize_text_field($input['field_order']) : 'first_name,last_name,email,phone,password',
                'signup_redirect_url' => isset($input['signup_redirect_url']) ? esc_url_raw($input['signup_redirect_url']) : home_url(),
                'signup_logo' => isset($input['signup_logo']) ? esc_url_raw($input['signup_logo']) : '',
                'signup_title' => isset($input['signup_title']) ? sanitize_text_field( wp_unslash($input['signup_title']) ) : '',
                'signup_description' => isset($input['signup_description']) ? wp_kses_post($input['signup_description']) : '',
                'button_background_color' => isset($input['button_background_color']) ? sanitize_hex_color($input['button_background_color']) : '#0073aa',
                'button_text_color' => isset($input['button_text_color']) ? sanitize_hex_color($input['button_text_color']) : '#ffffff',
                'button_hover_background_color' => isset($input['button_hover_background_color']) ? sanitize_hex_color($input['button_hover_background_color']) : '#005177',
                'button_hover_text_color' => isset($input['button_hover_text_color']) ? sanitize_hex_color($input['button_hover_text_color']) : '#ffffff',
                'enable_strong_password' => isset($input['enable_strong_password']) ? 1 : 0,
                'enable_password_reset' => isset($input['enable_password_reset']) ? 1 : 0,
                'auto_login' => isset($input['auto_login']) ? 1 : 0,
                'first_name_enabled' => isset($input['first_name']['enabled']) ? 1 : 0,
                'first_name_required' => isset($input['first_name']['required']) ? 1 : 0,
                'last_name_enabled' => isset($input['last_name']['enabled']) ? 1 : 0,
                'last_name_required' => isset($input['last_name']['required']) ? 1 : 0,
                'email_enabled' => isset($input['email']['enabled']) ? 1 : 0,
                'email_required' => isset($input['email']['required']) ? 1 : 0,
                'phone_enabled' => isset($input['phone']['enabled']) ? 1 : 0,
                'phone_required' => isset($input['phone']['required']) ? 1 : 0,
                'password_enabled' => isset($input['password']['enabled']) ? 1 : 0,
                'password_required' => isset($input['password']['required']) ? 1 : 0,
                'signup_custom_css' => isset($input['signup_custom_css']) ? $input['signup_custom_css'] : ''
            ];

            $new_custom_fields = [];
            if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
                foreach ($input['custom_fields'] as $field_key => $field_data) {
                    $new_custom_fields[$field_key] = [
                        'label'    => sanitize_text_field($field_data['label'] ?? ''),
                        'type'     => sanitize_text_field($field_data['type'] ?? 'text'),
                        'enabled'  => !empty($field_data['enabled']) ? 1 : 0,
                        'required' => !empty($field_data['required']) ? 1 : 0,
                    ];

                    if (in_array($new_custom_fields[$field_key]['type'], ['checkbox', 'radio'])) {
                        $options_raw = $field_data['options'] ?? '';
                        $options_array = [];
                        foreach (explode("\n", $options_raw) as $option_line) {
                            $option_line = trim($option_line);
                            if (empty($option_line)) continue;

                            $parts = explode('|', $option_line, 2);
                            $label = sanitize_text_field($parts[0]);
                            $value = isset($parts[1]) ? sanitize_text_field($parts[1]) : $label;
                            $options_array[] = ['label' => $label, 'value' => $value];
                        }
                        $new_custom_fields[$field_key]['options'] = $options_array;
                    }
                }
            }
            $sanitized['custom_fields'] = $new_custom_fields;

            global $wpdb;
            if ($sanitized['selected_instance'] > 0) {
                $is_online = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}awp_instance_data WHERE id = %d AND status = 'online'", $sanitized['selected_instance']));
                if (!$is_online) {
                    $sanitized['selected_instance'] = 0;
                }
            }

            $current_field_order = explode(',', $sanitized['field_order']);
            $updated_field_order = [];
            $standard_fields_keys = ['first_name', 'last_name', 'email', 'phone', 'password'];
            $custom_field_keys = array_keys($sanitized['custom_fields']);

            foreach ($current_field_order as $key) {
                if (in_array($key, $standard_fields_keys) || in_array($key, $custom_field_keys)) {
                    $updated_field_order[] = $key;
                }
            }
            foreach ($custom_field_keys as $key) {
                if (!in_array($key, $updated_field_order)) {
                    $updated_field_order[] = $key;
                }
            }
            $sanitized['field_order'] = implode(',', array_unique($updated_field_order));
            
            if ( isset( $sanitized['custom_fields'] ) ) {
                $sanitized['custom_fields'] = wp_json_encode( $sanitized['custom_fields'] );
            }
                        

            $this->db_manager->update_signup_settings($sanitized);
        }

        public function render_signup_form() {
            $banned_msg = get_transient('siteB_banned_msg');
            $token = get_option('mysso_token');
            $user_data = get_transient('siteB_user_data');
            if ($banned_msg) {
                echo '<div class="wrap">
                    <h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Signup', 'awp') . '</h1>
                    <p style="color:red;">' . esc_html(Wawp_Global_Messages::get('blocked_generic')) . '</p>
                </div>';
                return;
            }
            if (!$token) {
                echo '<div class="wrap">
                    <h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('OTP Signup', 'awp') . '</h1>
                    <p>' . esc_html(Wawp_Global_Messages::get('need_login')) . '</p>
                </div>';
                return;
            }
            $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
            if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
                echo '<div class="wrap">
                    <h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Signup', 'awp') . '</h1>
                    <p style="color:red;">' . esc_html(Wawp_Global_Messages::get('not_active_site')) . '</p>
                </div>';
                return;
            }
            $settings = $this->get_settings();
            $passwordStrong = isset($settings['enable_strong_password']) ? $settings['enable_strong_password'] : 0;
            ob_start();
            if (is_user_logged_in()) {
                echo '<p>' . esc_html__('You are already logged in.', 'awp') . '</p>';
                return ob_get_clean();
            }
            if (empty($settings['selected_instance']) && !empty($settings['enable_otp']) && (empty($settings['phone_enabled']) && empty($settings['email_enabled']))) {
                echo '<p>' . esc_html__('Please enable Phone or Email field and choose sender then click "Save changes" from Wawp > OTP Verification > Signup. to display this form.', 'awp') . '</p>';
                return ob_get_clean();
            }
            
            $field_order = isset($settings['field_order']) ? explode(',', $settings['field_order']) : [];

            $standard_fields_renderers = [
                'first_name' => function () use ($settings) {
                    if (!empty($settings['first_name_enabled'])) {
                        echo '<div class="awp-form-group">';
                        echo '<label for="awp_first_name">' . esc_html__('First Name', 'awp');
                        if (!empty($settings['first_name_required'])) {
                            echo '<span class="awp-required">*</span>';
                        }
                        echo '</label>';
                        echo '<input type="text" name="awp_first_name" id="awp_first_name" class="awp-form-control"'
                            . (!empty($settings['first_name_required']) ? ' required' : '') . ' />';
                        echo '</div>';
                        echo '<div class="awp-error-message"></div>';
                    }
                },
                'last_name' => function () use ($settings) {
                    if (!empty($settings['last_name_enabled'])) {
                        echo '<div class="awp-form-group">';
                        echo '<label for="awp_last_name">' . esc_html__('Last Name', 'awp');
                        if (!empty($settings['last_name_required'])) {
                            echo '<span class="awp-required">*</span>';
                        }
                        echo '</label>';
                        echo '<input type="text" name="awp_last_name" id="awp_last_name" class="awp-form-control"'
                            . (!empty($settings['last_name_required']) ? ' required' : '') . ' />';
                        echo '</div>';
                        echo '<div class="awp-error-message"></div>';
                    }
                },
                'email' => function () use ($settings) {
                    if (!empty($settings['email_enabled'])) {
                        echo '<div class="awp-form-group">';
                        echo '<label for="awp_email">' . esc_html__('Email', 'awp');
                        if (!empty($settings['email_required'])) {
                            echo '<span class="awp-required">*</span>';
                        }
                        echo '</label>';
                        echo '<input type="email" name="awp_email" id="awp_email" class="awp-form-control"'
                            . (!empty($settings['email_required']) ? ' required' : '')
                            . ' placeholder="' . esc_attr__('Enter your email address', 'awp') . '" autocomplete="off" />';
                        echo '</div>';
                        echo '<div class="awp-error-message"></div>';
                    }
                },
                'phone' => function () use ($settings) {
                    if (!empty($settings['phone_enabled'])) {
                        echo '<div class="awp-form-group">';
                        echo '<label for="awp_phone">' . esc_html__('Phone', 'awp');
                        if (!empty($settings['phone_required'])) {
                            echo '<span class="awp-required">*</span>';
                        }
                        echo '</label>';
                        echo '<input type="text" name="awp_phone" id="awp_phone" class="awp-form-control"'
                            . (!empty($settings['phone_required']) ? ' required' : '')
                            . ' placeholder="' . esc_attr__('Enter your phone number', 'awp') . '" />';
                        echo '</div>';
                        echo '<div class="awp-error-message"></div>';
                    }
                },
                'password' => function () use ($settings, $passwordStrong) {
                    if (!empty($settings['password_enabled'])) {
                        echo '<div class="awp-form-group">';
                        echo '<label for="awp_password">' . esc_html__('Password', 'awp');
                        if (!empty($settings['password_required'])) {
                            echo '<span class="awp-required">*</span>';
                        }
                        echo '</label>';
                        echo '<div class="awp-password-container">';
                        echo '<input type="password" name="awp_password" id="awp_password" class="awp-form-control"'
                            . (!empty($settings['password_required']) ? ' required' : '') . ' />';
                        echo '<span class="awp-toggle-password"><i class="ri-eye-line"></i></span>';
                        echo '</div>';
                        echo '<div id="password-strength-bar">';
                        echo '<div id="password-strength-meter" class="awp-password-strength"></div>';
                        echo '</div>';
                        echo '<div id="password-strength-text" class="awp-password-strength-text"></div>';
                        if ($passwordStrong) {
                            echo '<ul id="password-requirements" class="awp-password-requirements">';
                            echo '<span style="width: 100%;">' . esc_html__('Your password must contain:', 'awp') . '</span>';
                            echo '<li class="awp-requirement"><span class="awp-check"></span>' . esc_html__('Upper case letter (A-Z)', 'awp') . '</li>';
                            echo '<li class="awp-requirement"><span class="awp-check"></span>' . esc_html__('Lower case letter (a-z)', 'awp') . '</li>';
                            echo '<li class="awp-requirement"><span class="awp-check"></span>' . esc_html__('Numbers (0-9)', 'awp') . '</li>';
                            echo '<li class="awp-requirement"><span class="awp-check"></span>' . esc_html__('At least 8 characters', 'awp') . '</li>';
                            echo '<li class="awp-requirement"><span class="awp-check"></span>' . esc_html__('Special characters (e.g. !@#$%^&*)', 'awp') . '</li>';
                            echo '</ul>';
                        }
                        echo '</div>';
                        echo '<div class="awp-error-message"></div>';
                    }
                },
            ];

            echo '<div id="awp-signup-container">';
            echo '<div id="awp-signup-branding">';
            $logo_url = $settings['signup_logo'] ?? '';
            if ($logo_url) {
                echo '<div class="Wawp-signup-logo-container"><img src="' . esc_url($logo_url) . '" alt="" class="Wawp-signup-logo" style="max-width:200px"/></div>';
            }
            $title = $settings['signup_title'] ?? '';
            if ($title) {
                echo '<h3 class="wawp-signup-title">' . esc_html($title) . '</h3>';
            }
            $description = $settings['signup_description'] ?? '';
            if ($description) {
                echo '<p class="awp-description">' . wp_kses_post($description) . '</p>';
            }
            echo '</div>';

            echo '<form id="awp-signup-form" class="awp-form">';
            echo '<input type="hidden" name="awp_signup_nonce_field" value="' . esc_attr(wp_create_nonce($this->nonce_action)) . '" />';

            foreach ($field_order as $field_key) {
                if (isset($standard_fields_renderers[$field_key])) {
                    $standard_fields_renderers[$field_key]();
                } elseif (isset($settings['custom_fields'][$field_key]) && $settings['custom_fields'][$field_key]['enabled']) {
                    $field = $settings['custom_fields'][$field_key];
                    $field_id = 'awp_custom_field_' . esc_attr($field_key);
                    $field_name = 'awp_custom_fields[' . esc_attr($field_key) . ']';
                    $is_required = !empty($field['required']) ? ' required' : '';
                    $required_span = !empty($field['required']) ? '<span class="awp-required">*</span>' : '';

                    echo '<div class="awp-form-group">';
                    echo '<label for="' . $field_id . '">' . esc_html($field['label']) . $required_span . '</label>';

                    switch ($field['type']) {
                        case 'text':
                        case 'email':
                        case 'number':
                            echo '<input type="' . esc_attr($field['type']) . '" name="' . $field_name . '" id="' . $field_id . '" class="awp-form-control"' . $is_required . ' />';
                            break;
                        case 'textarea':
                            echo '<textarea name="' . $field_name . '" id="' . $field_id . '" class="awp-form-control"' . $is_required . '></textarea>';
                            break;
                        case 'checkbox':
                            if (!empty($field['options']) && is_array($field['options'])) {
                                foreach ($field['options'] as $option) {
                                    echo '<label class="awp-checkbox-label">';
                                    echo '<input type="checkbox" name="' . $field_name . '[]" value="' . esc_attr($option['value']) . '" class="awp-form-control-checkbox"' . $is_required . ' />';
                                    echo esc_html($option['label']);
                                    echo '</label><br />';
                                }
                            }
                            break;
                        case 'radio':
                            if (!empty($field['options']) && is_array($field['options'])) {
                                foreach ($field['options'] as $option) {
                                    echo '<label class="awp-radio-label">';
                                    echo '<input type="radio" name="' . $field_name . '" value="' . esc_attr($option['value']) . '" class="awp-form-control-radio"' . $is_required . ' />';
                                    echo esc_html($option['label']);
                                    echo '</label><br />';
                                }
                            }
                            break;
                    }
                    echo '</div>';
                    echo '<div class="awp-error-message"></div>';
                }
            }
            echo '<div class="awp-form-group"><button type="submit" class="awp-submit-button awp-btn"><i class="ri-user-add-line"></i> ' . esc_html__('Create New Account', 'awp') . '</button></div>';
            echo '</form>';
            if ($settings['enable_otp'] && (!empty($settings['phone_enabled']) || !empty($settings['email_enabled']))) {
                echo '<div id="awp-otp-section">';
                echo '<div class="awp-otp-header">';
                echo '<div class="awp-icon-wrapper"><i id="otp-icon"></i></div>';
                echo '<h3 id="awp-otp-sent-heading"></h3>';
                echo '<p id="awp-otp-sent-message"></p>';
                echo '</div>';
                echo '<form id="awp-otp-form" class="awp-form">';
                echo '<input type="hidden" name="awp_otp_nonce_field" value="' . esc_attr(wp_create_nonce($this->nonce_action)) . '" />';
                echo '<div class="awp-form-group">';
                echo '<input type="text" name="awp_otp_code" id="awp_otp_code" class="awp-form-control" placeholder="' . esc_attr__('Enter OTP code..', 'awp') . '" maxlength="6" required />';
                echo '</div>';
                echo '<div class="awp-error-message"></div>';
                echo '<div class="awp-form-group">';
                echo '<button type="submit" class="awp-submit-button awp-btn">' . esc_html__('Confirm', 'awp') . '</button>';
                echo '<button type="button" id="awp-resend-otp-btn" class="awp-resend-otp-btn awp-btn">' . esc_html__('Resend Code', 'awp') . '</button>';
                echo '</div>';
                echo '<input type="hidden" id="awp_otp_transient" name="awp_otp_transient" value="" />';
                echo '</form>';
                echo '<p class="awp-otp-resend"><span id="awp-resend-message"></span><a id="awp-edit-contact-btn" class="awp-edit-contact-btn"></a></p>';
                echo '</div>';
            }
            echo '<div class="awp-success-message" style="display:none;"></div>';
            echo '<div class="awp-password-reset-status" style="display:none; color: blue; margin-top: 10px;"></div>';
            echo '</div>';
            return ob_get_clean();
        }

        public function ajax_signup_form_submit() {
        if (!isset($_POST['awp_signup_nonce_field']) || !wp_verify_nonce($_POST['awp_signup_nonce_field'], $this->nonce_action)) {
            wp_send_json_error(['message' => __('Invalid request. Please try again.', 'awp')]);
        }
        $settings = $this->get_settings();
        $first_name = !empty($settings['first_name_enabled']) ? sanitize_text_field($_POST['awp_first_name'] ?? '') : '';
        $last_name = !empty($settings['last_name_enabled']) ? sanitize_text_field($_POST['awp_last_name'] ?? '') : '';
        $email = !empty($settings['email_enabled']) ? sanitize_email($_POST['awp_email'] ?? '') : '';
        $phone = !empty($settings['phone_enabled']) ? sanitize_text_field($_POST['awp_phone'] ?? '') : '';
        $password = !empty($settings['password_enabled']) ? $_POST['awp_password'] ?? '' : '';

        $custom_fields_data = [];
        if (isset($_POST['awp_custom_fields']) && is_array($_POST['awp_custom_fields'])) {
            foreach ($_POST['awp_custom_fields'] as $key => $value) {
                $field_config = $settings['custom_fields'][$key] ?? null;
                if ($field_config && $field_config['enabled']) {
                    if (is_array($value)) {
                        $custom_fields_data[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $custom_fields_data[$key] = sanitize_text_field($value);
                    }
                }
            }
        }

        $errors = [];
        $form_fields = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password
        ];

        foreach ($form_fields as $field => $value) {
            $enabled = !empty($settings["{$field}_enabled"]);
            $required = !empty($settings["{$field}_required"]);
            if ($enabled && $required && empty($value)) {
                $errors["awp_{$field}"] = __('This field is required.', 'awp');
            }
        }

        foreach ($settings['custom_fields'] as $field_key => $field_config) {
            if ($field_config['enabled'] && $field_config['required']) {
                if (empty($custom_fields_data[$field_key])) {
                    $errors['awp_custom_field_' . $field_key] = __('This field is required.', 'awp');
                } elseif (is_array($custom_fields_data[$field_key]) && count($custom_fields_data[$field_key]) === 0) {
                    $errors['awp_custom_field_' . $field_key] = __('This field is required.', 'awp');
                }
            }
            if ($field_config['type'] === 'email' && !empty($custom_fields_data[$field_key]) && !is_email($custom_fields_data[$field_key])) {
                $errors['awp_custom_field_' . $field_key] = __('Please enter a valid email address.', 'awp');
            }
            if ($field_config['type'] === 'number' && !empty($custom_fields_data[$field_key]) && !is_numeric($custom_fields_data[$field_key])) {
                $errors['awp_custom_field_' . $field_key] = __('Please enter a valid number.', 'awp');
            }
        }

        if (!empty($settings['email_enabled']) && !empty($email)) {
            if (!is_email($email)) {
                $errors['awp_email'] = __('Please enter a valid email address.', 'awp');
            } elseif (email_exists($email)) {
                $errors['awp_email'] = __('Email already in use.', 'awp');
            }
        }

        if (!empty($settings['password_enabled']) && !empty($password)) {
            if (strlen($password) < 6) {
                $errors['awp_password'] = __('Password must be at least 8 characters long.', 'awp');
            }
            if (!empty($settings['enable_strong_password'])) {
                if (!preg_match('/[A-Z]/', $password)) {
                    $errors['awp_password'] = __('Password must include at least one uppercase letter.', 'awp');
                }
                if (!preg_match('/[a-z]/', $password)) {
                    $errors['awp_password'] = __('Password must include at least one lowercase letter.', 'awp');
                }
                if (!preg_match('/[0-9]/', $password)) {
                    $errors['awp_password'] = __('Password must include at least one number.', 'awp');
                }
                if (!preg_match('/[^A-Za-z0-9]/', $password)) {
                    $errors['awp_password'] = __('Password must include at least one special character.', 'awp');
                }
            }
        }
        if (!empty($settings['phone_enabled']) && !empty($phone)) {
            $numeric_phone = preg_replace('/\D/', '', $phone);
            $block_list = get_option('awp_block_list', []);
            if (in_array($numeric_phone, $block_list, true)) {
                $errors['awp_phone'] = __('This phone number is blocked by the system.', 'awp');
            }
            elseif (!$this->is_valid_phone($phone)) {
                $errors['awp_phone'] = __('Please enter a valid phone number.', 'awp');
            }
            elseif ($this->phone_in_use($phone)) {
                $errors['awp_phone'] = __('Phone number already in use.', 'awp');
            }
        }
        if ($settings['enable_otp']) {
            $otp_method = $settings['otp_method'] ?? 'whatsapp';
            if ($otp_method === 'whatsapp' && empty($phone)) {
                $errors['awp_phone'] = __('Phone number is required for OTP confirmation.', 'awp');
            }
            if ($otp_method === 'email' && empty($email)) {
                $errors['awp_email'] = __('Email is required for OTP confirmation.', 'awp');
            }
        }
        
        $base_identifier = '';

        if (!empty($settings['phone_enabled']) && !empty($phone)) {
            $base_identifier = preg_replace('/\D/', '', $phone);
        }
        
        elseif (!empty($settings['first_name_enabled']) && !empty($first_name)) {
            $base_identifier = sanitize_title($first_name); 
        }
     
        elseif (!empty($settings['email_enabled']) && !empty($email)) {
            $email_parts = explode('@', $email);
            $base_identifier = sanitize_title($email_parts[0]); 
        } else {
          
            $base_identifier = 'user';
        }

       
        $username_base = 'wa.' . $base_identifier;
        $username = $username_base;
        $counter = 1;
        while (username_exists($username)) {
            $username = $username_base . $counter;
            $counter++;
        }

        if (empty($settings['password_enabled']) || empty($settings['password_required'])) {
            $password = wp_generate_password(12, false);
        }

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
        }

        if ($settings['enable_otp']) {
            $otp_code = random_int(100000, 999999);
            $transient_key = 'awp_signup_otp_' . md5(uniqid('', true));
            set_transient($transient_key, [
                'otp' => $otp_code,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'username' => $username,
                'custom_fields_data' => $custom_fields_data,
            ], 15 * MINUTE_IN_SECONDS);
            if ($settings['otp_method'] === 'email') {
                $otp_message_email = $settings['otp_message_email'] ?? __('Your OTP code is: {{otp}}', 'awp');
                $otp_message_email = str_replace('{{otp}}', $otp_code, $otp_message_email);
                if (!$this->send_email_otp($email, $otp_message_email)) {
                    wp_send_json_error(['message' => __('Failed to send OTP email. Please try again later.', 'awp')]);
                }
            } elseif ($settings['otp_method'] === 'whatsapp') {
                    $otp_message = $settings['otp_message'] ?? __('Your OTP code is: {{otp}}', 'awp');
                    $otp_message = str_replace('{{otp}}', $otp_code, $otp_message);
                    $selected_instance_id = (int) ($settings['selected_instance'] ?? 0);

                    if ($selected_instance_id <= 0) {
                        wp_send_json_error(['message' => __('No WhatsApp instance selected for OTP sending.', 'awp')]);
                    }
                    
                    $instance = $this->db_manager->get_instance_by_id($selected_instance_id);
                    if (!$instance) {
                        wp_send_json_error(['message' => __('Selected WhatsApp instance is offline or invalid.', 'awp')]);
                    }

                    $whatsapp_response = ['success' => false, 'message' => ''];
                    $max_retries = 3; 
                    $retry_delay = 1; 

                    for ($i = 0; $i < $max_retries; $i++) {
                        $whatsapp_response = $this->send_whatsapp_otp($instance, $phone, $otp_message);
                        if ($whatsapp_response['success']) {
                            break; 
                        }
                 
                        if (isset($whatsapp_response['response']['full_response']['status']) && $whatsapp_response['response']['full_response']['status'] === 'blocked') {
                            break; 
                        }

                        error_log('AWP_Signup: WhatsApp OTP retry attempt ' . ($i + 1) . ' failed for ' . $phone . ': ' . ($whatsapp_response['message'] ?? 'Unknown error.'));
                        if ($i < $max_retries - 1) {
                            sleep($retry_delay); 
                        }
                    }

                    if (!$whatsapp_response['success']) {
                    
                        $error_message = __('Failed to send OTP via WhatsApp. Please try again. If the problem persists, contact support.', 'awp');
                        if (isset($whatsapp_response['response']['full_response']['status']) && $whatsapp_response['response']['full_response']['status'] === 'blocked') {
                            $error_message = __('This phone number is blocked by the system.', 'awp');
                        } elseif (!empty($whatsapp_response['message'])) {
                             $error_message = __('Failed to send OTP: ', 'awp') . $whatsapp_response['message'];
                        }
                        wp_send_json_error(['message' => $error_message]);
                    }

                    $customer_name = $this->determine_customer_name($first_name, $last_name, $email, $phone);
                    $this->log_otp_sent_whatsapp(
                    $customer_name,
                    $phone,
                    $otp_message, // Use the actual message content
                    $whatsapp_response['response'],
                    $instance->instance_id,
                    $instance->access_token
                );
                }
            wp_send_json_success([
                'message' => __('OTP sent successfully! Please enter the code below to complete your signup.', 'awp'),
                'otp_transient' => $transient_key,
                'phone' => $phone,
                'email' => $email,
                'otp_method' => $settings['otp_method'],
            ]);
        } else {
            $userdata = [
                'user_login' => $username, 
                'user_email' => $email,
                'user_pass' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
            ];
            $user_id = wp_insert_user($userdata);
            if (is_wp_error($user_id)) {
                wp_send_json_error(['message' => $user_id->get_error_message()]);
            }
            $this->db_manager->insert_user_info($user_id, $first_name, $last_name, $email, $phone, $password);
            do_action('user_register', $user_id);
            update_user_meta($user_id, 'first_name', $first_name);
            update_user_meta($user_id, 'last_name', $last_name);
            if (class_exists('WooCommerce')) {
                update_user_meta($user_id, 'billing_first_name', $first_name);
                update_user_meta($user_id, 'billing_last_name', $last_name);
                update_user_meta($user_id, 'billing_phone', $phone);
            }
            if (!empty($settings['phone_enabled']) && !empty($phone)) {
                update_user_meta($user_id, 'awp-user-phone', $phone);
            }
            foreach ($custom_fields_data as $meta_key => $value) {
                update_user_meta($user_id, $meta_key, $value);
            }
            if (!empty($settings['auto_login'])) {
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                $user_info = get_user_by('id', $user_id);
                do_action('wp_login', $user_info->user_login, $user_info);
            }
            $redirect_url = !empty($settings['signup_redirect_url']) ? $settings['signup_redirect_url'] : home_url();
            do_action('awp_after_user_signup', $user_id, $userdata);
            wp_send_json_success(['message' => __('Signup successful! Redirecting...', 'awp'), 'redirect_url' => $redirect_url]);
        }
    }

        public function ajax_verify_otp() {
        if (!isset($_POST['awp_otp_nonce_field']) || !wp_verify_nonce($_POST['awp_otp_nonce_field'], $this->nonce_action)) {
            wp_send_json_error(['message' => __('Invalid request. Please try again.', 'awp')]);
        }
        $settings = $this->get_settings();
        $otp_transient = sanitize_text_field($_POST['awp_otp_transient'] ?? '');
        $entered_otp = sanitize_text_field($_POST['awp_otp_code'] ?? '');
        if (empty($otp_transient) || empty($entered_otp)) {
            wp_send_json_error(['message' => __('OTP and Transient Key are required.', 'awp')]);
        }
        $stored_data = get_transient($otp_transient);
        if (!$stored_data) {
            wp_send_json_error(['message' => __('OTP has expired or is invalid. Please sign up again.', 'awp')]);
        }
        $attempts = $this->increment_otp_attempts($otp_transient);
        $max_attempts = 5;
        if ($attempts > $max_attempts) {
            wp_send_json_error(['message' => __('Maximum OTP attempts exceeded. Please sign up again.', 'awp')]);
        }
        if ($entered_otp != $stored_data['otp']) {
            wp_send_json_error(['message' => __('Incorrect OTP. Please try again.', 'awp')]);
        }

        $instance = null;
        if ($settings['otp_method'] === 'whatsapp') {
            global $wpdb;
            $instance = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}awp_instance_data WHERE id = %d AND status = 'online'", $settings['selected_instance']));
            if (!$instance) {
                wp_send_json_error(['message' => __('Selected instance is not available. Please contact the administrator.', 'awp')]);
            }
        }
        
        $userdata = [
            'user_login' => $stored_data['username'],
            'user_email' => $stored_data['email'],
            'user_pass' => $stored_data['password'],
            'first_name' => $stored_data['first_name'],
            'last_name' => $stored_data['last_name'],
        ];
        $user_id = wp_insert_user($userdata);
        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
        }
        $this->db_manager->insert_user_info($user_id, $stored_data['first_name'], $stored_data['last_name'], $stored_data['email'], $stored_data['phone'], $stored_data['password']);
        do_action('user_register', $user_id);
        update_user_meta($user_id, 'first_name', $stored_data['first_name']);
        update_user_meta($user_id, 'last_name', $stored_data['last_name']);
        if (class_exists('WooCommerce')) {
            update_user_meta($user_id, 'billing_first_name', $stored_data['first_name']);
            update_user_meta($user_id, 'billing_last_name', $stored_data['last_name']);
            update_user_meta($user_id, 'billing_phone', $stored_data['phone']);
        }
        if (!empty($stored_data['phone'])) {
            update_user_meta($user_id, 'awp-user-phone', $stored_data['phone']);
        }

        if (!empty($stored_data['custom_fields_data']) && is_array($stored_data['custom_fields_data'])) {
            foreach ($stored_data['custom_fields_data'] as $meta_key => $value) {
                update_user_meta($user_id, $meta_key, $value);
            }
        }

        $otp_method = $settings['otp_method'] ?? 'whatsapp';
        $this->db_manager->update_user_verification($user_id, $otp_method, true);
        delete_transient($otp_transient);
        delete_transient($otp_transient . '_attempts');
        do_action('awp_after_user_signup', $user_id, $userdata);
        $combined_status_message = '';
        if (!empty($settings['enable_password_reset'])) {
            $has_email = !empty($stored_data['email']);
            $has_phone = !empty($stored_data['phone']);
            $reset_url = '';
            $reset_key = '';
            $user_obj = get_user_by('id', $user_id);
            if ($user_obj) {
                $reset_key = get_password_reset_key($user_obj);
                if (is_wp_error($reset_key)) {
                    wp_send_json_error(['message' => __('Failed to generate password reset key.', 'awp')]);
                }
                $reset_url = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode($stored_data['username']), 'login');
            }
            $reset_link_status = [];
            if ($has_email) {
                $email_subject = __('Set Your Password', 'awp');
                $email_message = __('Please set your password using the following link:', 'awp') . ' ' . $reset_url;
                $email_headers = ['Content-Type: text/html; charset=UTF-8'];
                if (wp_mail($stored_data['email'], $email_subject, nl2br($email_message), $email_headers)) {
                    $reset_link_status['email'] = __('Password reset link sent successfully via Email.', 'awp');
                } else {
                    error_log("AWP_Signup: Failed to send Password Reset Email to {$stored_data['email']}");
                    $reset_link_status['email'] = __('Failed to send password reset link via Email.', 'awp');
                }
            }
            if ($has_phone && $instance) {
                $password_set_message = __('Please set your password using the following link:', 'awp') . ' ' . $reset_url;
                $whatsapp_response = $this->send_whatsapp_message($stored_data['phone'], $password_set_message, $instance);
                if ($whatsapp_response['success']) {
                    $customer_name = $this->determine_customer_name($stored_data['first_name'], $stored_data['last_name'], $stored_data['email'], $stored_data['phone']);
                    $this->log_otp_sent_whatsapp($customer_name, $stored_data['phone'], __('Password Reset Link', 'awp'), $whatsapp_response['response']);
                    $reset_link_status['whatsapp'] = __('Password reset link sent successfully via WhatsApp.', 'awp');
                } else {
                    error_log("AWP_Signup: Failed to send Password Reset via WhatsApp to {$stored_data['phone']}");
                    $reset_link_status['whatsapp'] = __('Failed to send password reset link via WhatsApp.', 'awp');
                }
            }
            if (!empty($reset_link_status)) {
                $combined_status_message = implode(' ', $reset_link_status);
            }
        }
        if (!empty($settings['auto_login'])) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            $user_info = get_user_by('id', $user_id);
            do_action('wp_login', $user_info->user_login, $user_info);
        }
        $response = [
            'message' => __('Signup successful! Redirecting...', 'awp'),
            'redirect_url' => $settings['signup_redirect_url'] ?? home_url(),
        ];
        if (!empty($settings['enable_password_reset']) && !empty($combined_status_message)) {
            $response['password_reset_status'] = $combined_status_message;
        }
        wp_send_json_success($response);
    }

        public function ajax_resend_otp() {
            if (!isset($_POST['awp_signup_nonce_field']) || !wp_verify_nonce($_POST['awp_signup_nonce_field'], $this->nonce_action)) {
                wp_send_json_error(['message' => __('Invalid request. Please try again.', 'awp')]);
            }
            $settings = $this->get_settings();
            $transient_key = sanitize_text_field($_POST['awp_otp_transient'] ?? '');
            if (empty($transient_key)) {
                wp_send_json_error(['message' => __('Invalid request. Please try again.', 'awp')]);
            }
            $stored_data = get_transient($transient_key);
            if (!$stored_data) {
                wp_send_json_error(['message' => __('OTP has expired or is invalid. Please sign up again.', 'awp')]);
            }
            global $wpdb;
            $instance = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}awp_instance_data WHERE id = %d AND status = 'online'", $settings['selected_instance']));
            if (!$instance) {
                wp_send_json_error(['message' => __('Selected instance is not available. Please contact the administrator.', 'awp')]);
            }
            $otp_method = $settings['otp_method'] ?? 'whatsapp';
            if (($otp_method === 'email' && empty($stored_data['email'])) || ($otp_method === 'whatsapp' && empty($stored_data['phone']))) {
                wp_send_json_error(['message' => __('Required contact info is missing for OTP resending.', 'awp')]);
            }
            $otp_code = random_int(100000, 999999);
            $stored_data['otp'] = $otp_code;
            set_transient($transient_key, $stored_data, 15 * MINUTE_IN_SECONDS);
            if ($otp_method === 'email') {
                $otp_message_email = $settings['otp_message_email'] ?? __('Your OTP code is: {{otp}}', 'awp');
                $otp_message_email = str_replace('{{otp}}', $otp_code, $otp_message_email);
                if (!$this->send_email_otp($stored_data['email'], $otp_message_email)) {
                    wp_send_json_error(['message' => __('Failed to resend OTP email. Please try again later.', 'awp')]);
                }
            } elseif ($otp_method === 'whatsapp') {
                $otp_message = $settings['otp_message'] ?? __('Your OTP code is: {{otp}}', 'awp');
                $otp_message = str_replace('{{otp}}', $otp_code, $otp_message);
                $whatsapp_response = $this->send_whatsapp_otp($instance, $stored_data['phone'], $otp_message);
                if (!$whatsapp_response['success']) {
                    wp_send_json_error(['message' => __('Failed to resend OTP. Please try again later.', 'awp')]);
                }
                $customer_name = $this->determine_customer_name($stored_data['first_name'], $stored_data['last_name'], $stored_data['email'], $stored_data['phone']);
                $this->log_otp_sent_whatsapp(
                $customer_name,
                $stored_data['phone'],
                $otp_message,
                $whatsapp_response['response'],
                $instance->instance_id,
                $instance->access_token
            );
            }
            wp_send_json_success([
                'message' => __('OTP resent successfully! Please enter the new code below to complete your signup.', 'awp'),
                'otp_transient' => $transient_key,
                'phone' => $stored_data['phone'],
                'email' => $stored_data['email'],
                'otp_method' => $otp_method,
            ]);
        }

        private function send_email_otp($email, $message) {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            if (wp_mail($email, __('Your OTP Code', 'awp'), nl2br($message), $headers)) {
                return true;
            }
            error_log("AWP_Signup: Failed to send OTP email to {$email}");
            return false;
        }

        private function send_whatsapp_otp($instance, $phone, $message) {
            return $this->send_whatsapp_message($phone, $message, $instance);
        }

        private function send_whatsapp_message($phone, $message, $instance) {
            $response = Wawp_Api_Url::send_message(
                $instance->instance_id,
                $instance->access_token,
                $phone,
                $message
            );
            if ($response['status'] === 'success') {
                error_log("AWP_Signup: WhatsApp OTP Sent OK: " . print_r($response, true));
                return [
                    'success'  => true,
                    'response' => $response
                ];
            } else {
                error_log("AWP_Signup: WhatsApp OTP Error: " . $response['message']);
                return [
                    'success'  => false,
                    'response' => $response
                ];
            }
        }

        private function phone_in_use($phone) {
            $args = [
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'billing_phone','value' => $phone,'compare' => '='],
                    ['key' => 'awp-user-phone','value' => $phone,'compare' => '='],
                ],
                'fields' => 'ID',
                'number' => 1,
            ];
            return !empty(get_users($args));
        }

        private function is_valid_phone($phone) {
            return preg_match('/^\+?[0-9]{7,15}$/', $phone);
        }

        public function user_logged_in($user_login, $user) {
            do_action('awp_user_logged_in', $user->ID);
        }

        public function inject_custom_button_styles() {
            $settings = $this->get_settings();
            $background_color = sanitize_hex_color($settings['button_background_color'] ?? '#0073aa');
            $text_color = sanitize_hex_color($settings['button_text_color'] ?? '#ffffff');
            $hover_background_color = sanitize_hex_color($settings['button_hover_background_color'] ?? '#005177');
            $hover_text_color = sanitize_hex_color($settings['button_hover_text_color'] ?? '#ffffff');
            echo '<style>#awp-signup-container .awp-submit-button{background-color:' . esc_attr($background_color) . ';color:' . esc_attr($text_color) . ';border:none;cursor:pointer;transition:background-color 0.3s ease,color 0.3s ease;}#awp-signup-container .awp-submit-button:hover{background-color:' . esc_attr($hover_background_color) . ';color:' . esc_attr($hover_text_color) . ';}</style>';
            if (!empty($settings['signup_custom_css'])) {
                echo '<style>' . $settings['signup_custom_css'] . '</style>';
            }
        }

        private function increment_otp_attempts($transient_key) {
            $attempts = get_transient($transient_key . '_attempts') ?: 0;
            $attempts++;
            set_transient($transient_key . '_attempts', $attempts, 15 * MINUTE_IN_SECONDS);
            return $attempts;
        }

        public function clear_expired_otps() {
            global $wpdb;
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE %s", $wpdb->esc_like('_transient_awp_signup_otp_') . '%'));
        }

        private function log_otp_sent_whatsapp($customer_name, $whatsapp_number, $otp_message, $whatsapp_response, $instance_id = null, $access_token = null) {
        global $wpdb;
        $log_table = $this->db_manager->get_log_table_name();
    
        // Unwrap the response if it's nested inside 'full_response'
        $response_to_log = $whatsapp_response;
        if (isset($whatsapp_response['full_response'])) {
            $response_to_log = $whatsapp_response['full_response'];
        }
        $status_json = wp_json_encode($response_to_log, JSON_PRETTY_PRINT);
    
        $wpdb->insert($log_table, [
            'user_id'          => null,
            'order_id'         => null,
            'customer_name'    => sanitize_text_field($customer_name),
            'sent_at'          => current_time('mysql'),
            'whatsapp_number'  => sanitize_text_field($whatsapp_number),
            'message'          => sanitize_text_field($otp_message),
            'message_type'     => __('Signup OTP Message', 'awp'),
            'image_attachment' => null,
            'wawp_status'      => $status_json,
            'resend_id'        => null,
            'instance_id'      => $instance_id,
            'access_token'     => $access_token,
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
    
        if ($wpdb->last_error) {
            error_log('AWP_Signup: ' . __('Failed to log WhatsApp message - ', 'awp') . $wpdb->last_error);
        }
    }

        private function determine_customer_name($first_name, $last_name, $email, $phone) {
            if (!empty($first_name) && !empty($last_name)) {
                return $first_name . ' ' . $last_name;
            } elseif (!empty($email)) {
                return $email;
            } elseif (!empty($phone)) {
                return $phone;
            }
            return __('N/A', 'awp');
        }

        public function add_custom_user_profile_fields($user) {
            $settings = $this->get_settings();
            $custom_fields = $settings['custom_fields'];

            if (empty($custom_fields)) {
                return;
            }

            ?>
            <h2><?php esc_html_e('Additional Profile Information', 'awp'); ?></h2>
            <table class="form-table">
                <?php
                foreach ($custom_fields as $field_key => $field) {
                    $user_meta_value = get_user_meta($user->ID, $field_key, true);

                    if (!empty($field['enabled']) || (!empty($user_meta_value) && $user_meta_value !== '')) {
                        $field_id = 'awp_custom_field_' . esc_attr($field_key);
                        $field_name = esc_attr($field_key);
                        ?>
                        <tr>
                            <th><label for="<?php echo $field_id; ?>"><?php echo esc_html($field['label']); ?></label></th>
                            <td>
                                <?php
                                switch ($field['type']) {
                                    case 'text':
                                    case 'email':
                                    case 'number':
                                        ?>
                                        <input type="<?php echo esc_attr($field['type']); ?>" name="<?php echo $field_name; ?>" id="<?php echo $field_id; ?>" value="<?php echo esc_attr($user_meta_value); ?>" class="regular-text" />
                                        <?php
                                        break;
                                    case 'textarea':
                                        ?>
                                        <textarea name="<?php echo $field_name; ?>" id="<?php echo $field_id; ?>" rows="5" cols="30" class="large-text"><?php echo esc_textarea($user_meta_value); ?></textarea>
                                        <?php
                                        break;
                                    case 'checkbox':
                                        $current_values = is_array($user_meta_value) ? $user_meta_value : [];
                                        if (!empty($field['options']) && is_array($field['options'])) {
                                            foreach ($field['options'] as $option) {
                                                $checked = in_array($option['value'], $current_values) ? 'checked="checked"' : '';
                                                ?>
                                                <label><input type="checkbox" name="<?php echo $field_name; ?>[]" value="<?php echo esc_attr($option['value']); ?>" <?php echo $checked; ?> /> <?php echo esc_html($option['label']); ?></label><br />
                                                <?php
                                            }
                                        }
                                        break;
                                    case 'radio':
                                        $current_value = $user_meta_value;
                                        if (!empty($field['options']) && is_array($field['options'])) {
                                            foreach ($field['options'] as $option) {
                                                $checked = ($option['value'] === $current_value) ? 'checked="checked"' : '';
                                                ?>
                                                <label><input type="radio" name="<?php echo $field_name; ?>" value="<?php echo esc_attr($option['value']); ?>" <?php echo $checked; ?> /> <?php echo esc_html($option['label']); ?></label><br />
                                                <?php
                                            }
                                        }
                                        break;
                                }
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </table>
            <?php
        }

        public function save_custom_user_profile_fields($user_id) {
            if (!current_user_can('edit_user', $user_id)) {
                return;
            }

            $settings = $this->get_settings();
            $custom_fields = $settings['custom_fields'];

            foreach ($custom_fields as $field_key => $field_config) {
                $submitted_value = $_POST[$field_key] ?? null;

                if ($submitted_value !== null) {
                    if (is_array($submitted_value)) {
                        $sanitized_value = array_map('sanitize_text_field', $submitted_value);
                    } else {
                        $sanitized_value = sanitize_text_field($submitted_value);
                    }
                    update_user_meta($user_id, $field_key, $sanitized_value);
                } else {
                    if ($field_config['type'] === 'checkbox') {
                         delete_user_meta($user_id, $field_key);
                    }
                }
            }
        }

        private function render_custom_field_modal() {
            $field_types = [
                'text'        => __('Text Input', 'awp'),
                'textarea'    => __('Text Area', 'awp'),
                'email'       => __('Email Input', 'awp'),
                'number'      => __('Number Input', 'awp'),
                'checkbox'    => __('Checkbox (Multi-select)', 'awp'),
                'radio'       => __('Radio Button (Single-select)', 'awp'),
            ];
            ?>
          <div id="awp-custom-field-modal" style="display:none;">
    <div class="modal-content">
        <button type="button" class="close-button" aria-label="<?php esc_attr_e('Close', 'awp'); ?>">
            <i data-lucide="x"></i>
        </button>
        <h3><?php esc_html_e('Manage Custom Field', 'awp'); ?></h3>
        <form id="awp-custom-field-form">
            <input type="hidden" id="awp_modal_field_key" value="" />
            <input type="hidden" id="awp_modal_is_editing" value="0" />

            <div class="form-group">
                <label for="awp_modal_field_id">
                    <i data-lucide="key" class="lucide-icon-inline"></i>
                    <?php esc_html_e('Meta Key (Unique ID)', 'awp'); ?>
                </label>
                <input type="text" id="awp_modal_field_id" class="awp-input" required />
                <p class="description"><?php esc_html_e('This will be the unique meta key for the user profile. (e.g., `my_custom_field`). Use lowercase, no spaces, no special characters except underscore.', 'awp'); ?></p>
                <div class="awp-error-message" id="awp_modal_field_id_error"></div>
            </div>

            <div class="form-group">
                <label for="awp_modal_field_label">
                    <i data-lucide="tag" class="lucide-icon-inline"></i>
                    <?php esc_html_e('Field Label', 'awp'); ?>
                </label>
                <input type="text" id="awp_modal_field_label" class="awp-input" required />
                <p class="description"><?php esc_html_e('This will be the Name for the user frontend. (e.g., `Your Name`). Allowed every Thing.', 'awp'); ?></p>

                <div class="awp-error-message" id="awp_modal_field_label_error"></div>
            </div>

            <div class="form-group">
                <label for="awp_modal_field_type">
                    <i data-lucide="type" class="lucide-icon-inline"></i>
                    <?php esc_html_e('Field Type', 'awp'); ?>
                </label>
                <div class="awp-custom-select-wrapper">
                    <select id="awp_modal_field_type" class="awp-select" aria-hidden="true" tabindex="-1">
                        <?php
                        foreach ($field_types as $type_value => $type_label) : ?>
                            <option value="<?php echo esc_attr($type_value); ?>" data-icon="<?php echo esc_attr($this->get_lucide_icon_for_field_type($type_value)); ?>">
                                <?php echo esc_html($type_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="awp-select-display" tabindex="0" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-controls="awp_modal_field_type_listbox">
                        <span class="awp-selected-icon"><i data-lucide="text"></i></span>
                        <span class="awp-selected-text">Text Input</span>
                        <span class="awp-select-arrow"><i data-lucide="chevron-down"></i></span>
                    </div>
                    <ul class="awp-select-options" role="listbox" id="awp_modal_field_type_listbox" aria-labelledby="awp_modal_field_type_label">
                        <?php foreach ($field_types as $type_value => $type_label) : ?>
                            <li data-value="<?php echo esc_attr($type_value); ?>" data-icon="<?php echo esc_attr($this->get_lucide_icon_for_field_type($type_value)); ?>" role="option">
                                <i data-lucide="<?php echo esc_attr($this->get_lucide_icon_for_field_type($type_value)); ?>"></i>
                                <?php echo esc_html($type_label); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="form-group" id="awp-modal-options-group" style="display:none;">
                <label for="awp_modal_field_options">
                    <i data-lucide="list" class="lucide-icon-inline"></i>
                    <?php esc_html_e('Options (one per line)', 'awp'); ?>
                </label>
                <textarea id="awp_modal_field_options" rows="5" class="awp-textarea"></textarea>
                <p class="description"><?php esc_html_e('Enter each option on a new line. For example: `Option 1 | Value1`, `Option 2 | Value2`. If no value is provided, the option text will be used as the value.', 'awp'); ?></p>
                <div class="awp-error-message" id="awp_modal_field_options_error"></div>
            </div>

            <div class="form-group awp-toggle-switch-group">
                <label for="awp_modal_field_enabled" class="awp-switch-label">
                    <input type="checkbox" id="awp_modal_field_enabled" class="awp-toggle-checkbox" />
                    <span class="awp-slider round"></span>
                    <i data-lucide="eye" class="lucide-icon-inline"></i>
                    <?php esc_html_e('Enable Field on Signup Form', 'awp'); ?>
                </label>
            </div>

            <div class="form-group awp-toggle-switch-group">
                <label for="awp_modal_field_required" class="awp-switch-label">
                    <input type="checkbox" id="awp_modal_field_required" class="awp-toggle-checkbox" />
                    <span class="awp-slider round"></span>
                    <i data-lucide="asterisk" class="lucide-icon-inline"></i>
                    <?php esc_html_e('Required Field', 'awp'); ?>
                </label>
            </div>

            <button type="submit" class="awp-btn primary">
                <i data-lucide="save"></i>
                <?php esc_html_e('Save Field', 'awp'); ?>
            </button>
        </form>
    </div>
</div>
            <?php
        }
        
    }
    AWP_Signup::get_instance();
}