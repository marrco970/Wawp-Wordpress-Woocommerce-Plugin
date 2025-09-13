<?php
if (!defined('ABSPATH')) exit;

class AWP_Otp_Login {

    private $database_manager;
    private $awp_instances;

    public function __construct($database_manager, $awp_instances) {
        $this->database_manager = $database_manager;
        $this->awp_instances    = $awp_instances;
    }

    /* ---------------------------------------------------------------------
     * Small, safe cache for instance rows to reduce repeated DB hits
     * ------------------------------------------------------------------- */
    private function get_instance_row_cached(string $instance_id) {
        global $wpdb;

        if ($instance_id === '') return null;

        $key = 'awp_inst_' . md5($instance_id);
        $row = get_transient($key);
        if ($row !== false) {
            // we store null as 0 sentinel
            return $row ? $row : null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->database_manager->tables['instance_data']}
                 WHERE instance_id = %s AND status = 'online' LIMIT 1",
                $instance_id
            )
        );

        set_transient($key, $row ?: 0, 10); // very short cache to survive bursts
        return $row ?: null;
    }

    private function get_default_settings() {
        return [
            'instance'               => 0,
            'otp_message_whatsapp'   => __('Your OTP code is: {{otp}}', 'awp'),
            'otp_message_email'      => __('Your OTP code is: {{otp}}', 'awp'),
            'login_method'           => 'whatsapp_otp',
            'enable_whatsapp'        => 1,
            'enable_email'           => 1,
            'enable_email_password'  => 1,
            'redirect_rules'         => [],
            'signup_logo'=>['default'=>AWP_PLUGIN_DIR.'login-WhatsApp_icon.png','sanitize_callback'=>'esc_url_raw'],
            'title'                  => __('Welcome back', 'awp'),
            'description'            => __('Choose a sign-in method to continue', 'awp'),
            'request_otp_button_color' => '#22c55e',
            'verify_otp_button_color'  => '#22c55e',
            'resend_otp_button_color'  => '#22c55e',
            'login_button_color'       => '#22c55e',
            'custom_shortcode'       => '',
            'custom_css'             => '',
        ];
    }

    private function get_plugin_settings() {
        $defaults = $this->get_default_settings();
        $saved    = get_option('awp_otp_settings', []);
        return array_merge($defaults, $saved);
    }

    public function init() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);

        add_action('wp_ajax_awp_request_otp', [$this, 'handle_request_otp']);
        add_action('wp_ajax_nopriv_awp_request_otp', [$this, 'handle_request_otp']);
        add_action('wp_ajax_awp_verify_otp', [$this, 'handle_verify_otp']);
        add_action('wp_ajax_nopriv_awp_verify_otp', [$this, 'handle_verify_otp']);

        add_shortcode('wawp_otp_login', [$this, 'render_otp_login_form']);

        // Force no-cache on our AJAX endpoints to avoid gateway/browser caching
        add_action('admin_init', function () {
            if (defined('DOING_AJAX') && DOING_AJAX
                && isset($_POST['action'])
                && strpos($_POST['action'], 'awp_') === 0) {
                nocache_headers();
                header('Expires: 0');
            }
        }, 1);
    }

    public function register_settings() {
        register_setting('awp_otp_settings_group', 'awp_otp_settings', [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $old_settings    = $this->get_plugin_settings();
        $allowed_methods = ['whatsapp_otp','email_otp','email_password'];
        $sanitized       = [];

        $sanitized['instance']              = isset($input['instance']) ? sanitize_text_field($input['instance']) : '';
        $sanitized['otp_message_whatsapp']  = isset($input['otp_message_whatsapp']) ? wp_kses_post($input['otp_message_whatsapp']) : '';
        $sanitized['otp_message_email']     = isset($input['otp_message_email']) ? wp_kses_post($input['otp_message_email']) : '';

        $method                    = isset($input['login_method']) ? $input['login_method'] : 'whatsapp_otp';
        $sanitized['login_method'] = in_array($method, $allowed_methods, true) ? $method : 'whatsapp_otp';

        $sanitized['enable_whatsapp']        = !empty($input['enable_whatsapp']) ? 1 : 0;
        $sanitized['enable_email']           = !empty($input['enable_email']) ? 1 : 0;
        $sanitized['enable_email_password']  = !empty($input['enable_email_password']) ? 1 : 0;

        $sanitized['redirect_rules'] = [];
        if (!empty($input['redirect_rules']) && is_array($input['redirect_rules'])) {
            foreach ($input['redirect_rules'] as $rule) {
                if (!empty($rule['role']) && !empty($rule['redirect_url'])) {
                    $sanitized['redirect_rules'][] = [
                        'role'         => sanitize_text_field($rule['role']),
                        'redirect_url' => esc_url_raw($rule['redirect_url']),
                    ];
                }
            }
        }

        $sanitized['logo']        = isset($input['logo'])        ? esc_url_raw($input['logo']) : '';
        $sanitized['title']       = isset($input['title'])       ? sanitize_text_field($input['title']) : '';
        $sanitized['description'] = isset($input['description']) ? sanitize_textarea_field($input['description']) : '';

        $color_fields = [
            'request_otp_button_color',
            'verify_otp_button_color',
            'resend_otp_button_color',
            'login_button_color'
        ];
        foreach ($color_fields as $cf) {
            $sanitized[$cf] = !empty($input[$cf]) ? sanitize_hex_color($input[$cf]) : '';
        }

        $sanitized['custom_shortcode'] = isset($input['custom_shortcode']) ? sanitize_text_field($input['custom_shortcode']) : '';
        $sanitized['custom_css']       = isset($input['custom_css']) ? wp_strip_all_tags($input['custom_css']) : '';

        return array_merge($old_settings, $sanitized);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $banned_msg = get_transient('siteB_banned_msg');
        $token      = get_option('mysso_token');
        $user_data  = get_transient('siteB_user_data');

        if ($banned_msg) {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Login', 'awp') . '</h1><p style="color:red;">' . esc_html__('Site is blocked.', 'awp') . '</p></div>';
            return;
        }
        if (!$token) {
            echo '<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('OTP Login', 'awp') . '</h1><p>' . esc_html__('Need login.', 'awp') . '</p></div>';
            return;
        }
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Login', 'awp') . '</h1><p style="color:red;">' . esc_html__('Not an active site.', 'awp') . '</p></div>';
            return;
        }

        $settings      = $this->get_plugin_settings();
        $instance_val  = $settings['instance'];
        $online        = $this->awp_instances->get_online_instances();
        $is_still_online = false;

        if (!empty($instance_val)) {
            foreach ($online as $inst) {
                if ($inst->instance_id === $instance_val) {
                    $is_still_online = true;
                    break;
                }
            }
        }
        if (!$is_still_online && !empty($online)) {
            $instance_val            = $online[0]->instance_id;
            $settings['instance']    = $instance_val;
            $saved_opts              = get_option('awp_otp_settings', []);
            $saved_opts['instance']  = $instance_val;
            update_option('awp_otp_settings', $saved_opts);
        }

        $instance_val          = esc_attr($settings['instance']);
        $otp_msg_w             = $settings['otp_message_whatsapp'];
        $otp_msg_e             = $settings['otp_message_email'];
        $logo                  = $settings['logo'];
        $title                 = $settings['title'];
        $desc                  = $settings['description'];
        $request_col           = $settings['request_otp_button_color'];
        $verify_col            = $settings['verify_otp_button_color'];
        $resend_col            = $settings['resend_otp_button_color'];
        $login_col             = $settings['login_button_color'];
        $custom_css            = $settings['custom_css'];
        $sc                    = $settings['custom_shortcode'];
        $login_method          = $settings['login_method'];
        $enable_whatsapp       = !empty($settings['enable_whatsapp']);
        $enable_email          = !empty($settings['enable_email']);
        $enable_email_password = !empty($settings['enable_email_password']);

        ?>
        <div class="awp-otp-login-settings-wrap">
          <form method="post" action="options.php" id="awp-settings-form">
            <?php
              settings_fields('awp_otp_settings_group');
              do_settings_sections('awp_otp_settings_group');
            ?>
            <div class="instance" style="display:none;">
                <table class="form-table">
                  <tr>
                    <th>
                        <div>
                            <h4><label><?php echo esc_html__('Choose Sender', 'awp'); ?></label></h4>
                            <p><?php echo esc_html__('Select the WhatsApp account to send OTP login codes to users.', 'awp'); ?></p>
                        </div>
                    </th>
                    <td style="width: 22rem;">
                    <?php
                      $online = $this->awp_instances->get_online_instances();
                      if (!$online) {
                          echo '<p>' . esc_html__('No online instances found.', 'awp') . '</p>';
                      } else {
                          echo '<select name="awp_otp_settings[instance]">';
                          echo '<option value="">' . esc_html__('-- Select Instance --', 'awp') . '</option>';
                          foreach ($online as $inst) {
                              $sel = selected($instance_val, $inst->instance_id, false);
                              echo "<option value='".esc_attr($inst->instance_id)."' $sel>".esc_html($inst->name)."</option>";
                          }
                          echo '</select>';
                      }
                    ?>
                    </td>
                  </tr>
                </table>
            </div>
            <table class="form-table">
              <tr>
                <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Primary Login Method', 'awp'); ?></h4>
                            <p><?php echo esc_html__('Choose the login method you’d like your users to see first on the login page.', 'awp'); ?></p>
                        </div>
                    </div>
                </th>
                <td>
                  <?php
                    $methods = [
                      'whatsapp_otp'   => [__('WhatsApp', 'awp'),'ri-whatsapp-line'],
                      'email_otp'      => [__('Email OTP', 'awp'),'ri-mail-line'],
                      'email_password' => [__('Email and Password', 'awp'),'ri-lock-password-line']
                    ];
                  ?>
                    <div class="awp-login-method-radio">
                    <?php foreach ($methods as $key => $val): ?>
                      <label class="awp-radio-button">
                        <input type="radio"
                               name="awp_otp_settings[login_method]"
                               value="<?php echo esc_attr($key); ?>"
                               <?php checked($login_method, $key); ?> />
                        <i class="<?php echo esc_attr($val[1]); ?> awp-radio-icon" aria-hidden="true"></i>
                        <span><?php echo esc_html($val[0]); ?></span>
                      </label>
                    <?php endforeach; ?>
                    </div>
                </td>
              </tr>
            </table>
            <table class="form-table">
              <tr class="tr-hidden"></tr>
              <tr>
                <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Manage Login Methods', 'awp'); ?></h4>
                            <p><?php echo esc_html__('Enable and customize your preferred login options.', 'awp'); ?></p>
                        </div>
                    </div>
                </th>
                <td style="padding-top: 1.25rem !important;border-top: 1px solid #e3e3e3;">
                <div class="awp-login-methods">
                  <div class="wawp-setting-card">
                    <div class="wawp-setting-header awp-toggle-switch">
                        <div class="card-header">
                            <div class="wawp-setting-title"><?php echo esc_html__('Email & Password Login', 'awp'); ?></div>
                            <p><?php echo esc_html__('Standard login form using email and password.', 'awp'); ?></p>
                        </div>
                        <input type="checkbox" name="awp_otp_settings[enable_email_password]" id="awp_otp_enable_email_password" value="1" <?php checked($enable_email_password, true); ?> />
                        <label for="awp_otp_enable_email_password"></label>
                    </div>
                  </div>
                  <div class="wawp-setting-card">
                    <div class="wawp-setting-header awp-toggle-switch">
                        <div class="card-header">
                            <div class="wawp-setting-title"><?php echo esc_html__('WhatsApp Login', 'awp'); ?></div>
                            <p><?php echo esc_html__('Login via a One-Time Password (OTP) sent to user WhatsApp. .', 'awp'); ?></p>
                        </div>
                        <input type="checkbox" name="awp_otp_settings[enable_whatsapp]" id="awp_otp_enable_whatsapp" value="1" <?php checked($enable_whatsapp, true); ?> />
                        <label for="awp_otp_enable_whatsapp"></label>
                    </div>
                    <div class="wawp-setting-content">
                        <label for="awp_notifications_user_login_message"><?php echo esc_html__('Message Template', 'awp'); ?></label>
                        <div style="position:relative;">
                            <div class="placeholder-container"></div>
                            <textarea name="awp_otp_settings[otp_message_whatsapp]" id="awp_otp_message_whatsapp" rows="4" cols="50" class="large-text"><?php echo esc_textarea($otp_msg_w); ?></textarea>
                        </div>
                    </div>
                  </div>
                  <div class="wawp-setting-card">
                    <div class="wawp-setting-header awp-toggle-switch">
                        <div class="card-header">
                            <div class="wawp-setting-title"><?php echo esc_html__('Email OTP Login', 'awp'); ?></div>
                            <p><?php echo esc_html__('Login via a One-Time Password (OTP) sent to user email. ', 'awp'); ?></p>
                        </div>
                        <input type="checkbox" name="awp_otp_settings[enable_email]" id="awp_otp_enable_email" value="1" <?php checked($enable_email, true); ?> />
                        <label for="awp_otp_enable_email"></label>
                    </div>
                    <div class="wawp-setting-content">
                      <?php
                        $editor_conf = [
                          'textarea_name' => 'awp_otp_settings[otp_message_email]',
                          'media_buttons' => false,
                          'textarea_rows' => 10,
                          'tinymce'       => true,
                          'quicktags'     => true,
                        ];
                        wp_editor($otp_msg_e, 'awp_otp_message_email', $editor_conf);
                      ?>
                    </div>
                  </div>
                </div>
                </td>
              </tr>
              <tr>
                <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Redirection Rules', 'awp'); ?></label></h4>
                            <p><?php echo esc_html__('Set up redirection URLs based on user roles.', 'awp'); ?></p>
                        </div>
                        <button type="button" class="add-new-btn awp-btn secondary" id="awp_add_redirect_rule"><i class="ri-add-line"></i><?php echo esc_html__('Add Redirection Rule', 'awp'); ?></button>
                    </div>
                </th>
                <td>
                  <div id="awp_redirect_rules_container">
                  <?php
                  $rules = $settings['redirect_rules'];
                  if (!empty($rules)) {
                    foreach ($rules as $idx => $rule) {
                      echo '<div class="awp_redirect_rule">';
                      echo '<div class="rule-fields">';
                      echo '<select name="awp_otp_settings[redirect_rules]['.esc_attr($idx).'][role]" class="awp_redirect_role">';
                      echo '<option value="all" '.selected($rule['role'], 'all', false).'>'.esc_html__('All Roles', 'awp').'</option>';
                      foreach (wp_roles()->roles as $rk=>$rv) {
                        $sel = selected($rule['role'], $rk, false);
                        echo '<option value="'.esc_attr($rk).'" '.$sel.'>'.esc_html($rv['name']).'</option>';
                      }
                      echo '</select>';
                      echo '<input type="url" name="awp_otp_settings[redirect_rules]['.esc_attr($idx).'][redirect_url]" class="awp_redirect_url" value="'.esc_attr($rule['redirect_url']).'" placeholder="'.esc_attr(__('Enter redirect URL', 'awp')).'" required />';
                      echo '</div>';
                      echo '<button type="button" class="awp_remove_rule"><i class="ri-delete-bin-line"></i></button>';
                      echo '</div>';
                    }
                  }
                  ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Form Style', 'awp'); ?></label></h4>
                            <p><?php echo esc_html__('Customize the appearance of your login form.', 'awp'); ?></p>
                        </div>
                    </div>
                </th>
                <td>
                  <div style="display: flex; flex-direction: column;">
                  <?php
                    $final_logo = $logo ? esc_url($logo) : AWP_PLUGIN_URL.'assets/images/default-logo.png';
                  ?>
                  <div class="awp-logo-upload">
                    <img id="awp_logo_preview" src="<?php echo $final_logo; ?>" alt=" "/>
                    <input type="hidden" name="awp_otp_settings[logo]" id="awp_otp_logo" value="<?php echo esc_attr($logo); ?>" />
                    <button type="button" class="" id="awp_upload_logo_button"><?php echo esc_html__('Upload Logo', 'awp'); ?></button>
                    <button type="button" class="awp_remove_logo_button" style="display:<?php echo !empty($logo) ? 'flex' : 'none !important'; ?>">
                        <i class="ri-close-line"></i>
                    </button>
                  </div>
                  <hr class="h-divider" style="margin: 24px 0;">
                  <div class="txt-setting">
                      <label><?php echo esc_html__('Page Title', 'awp'); ?></label>
                      <input type="text" name="awp_otp_settings[title]" id="awp_otp_title" value="<?php echo esc_attr($title); ?>" />
                      <p class="description"><?php echo esc_html__('Enter a title to display above the login form (optional)', 'awp'); ?></p>
                  </div>
                  <hr class="h-divider" style="margin: 24px 0;">
                  <div class="txt-setting">
                      <label style="position: absolute;top: 0;"><?php echo esc_html__('Page Description', 'awp'); ?></label>
                      <?php
                      $desc_editor_config = [
                        'textarea_name' => 'awp_otp_settings[description]',
                        'media_buttons' => false,
                        'tinymce'       => true,
                        'quicktags'     => true,
                        'textarea_rows' => 5,
                      ];
                      wp_editor($desc, 'awp_otp_description', $desc_editor_config);
                      ?>
                      <p class="description"><?php echo esc_html__('Enter a description to display below the title (optional)', 'awp'); ?></p>
                  </div>
                  </div>
                </td>
              </tr>
              <tr>
                <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Button Color', 'awp'); ?></label></h4>
                            <p><?php echo esc_html__('Change button colors for the OTP login page.', 'awp'); ?></p>
                        </div>
                    </div>
                </th>
                <td>
                    <hr class="h-divider" style="margin-bottom: 1.25rem;">
                    <div class="awp-cards" style="flex-direction: row;justify-content: space-between;">
                      <div class="txt-setting">
                          <label><?php echo esc_html__('Send OTP', 'awp'); ?></label>
                          <input type="text" name="awp_otp_settings[request_otp_button_color]" id="awp_request_otp_button_color"
                                 value="<?php echo esc_attr($request_col); ?>" class="awp-color-field"
                                 data-default-color="#22c55e" />
                      </div>
                      <div class="txt-setting">
                          <label><?php echo esc_html__('Verify OTP', 'awp'); ?></label>
                          <input type="text" name="awp_otp_settings[verify_otp_button_color]" id="awp_verify_otp_button_color"
                                 value="<?php echo esc_attr($verify_col); ?>" class="awp-color-field"
                                 data-default-color="#22c55e" />
                      </div>
                      <div class="txt-setting">
                          <label><?php echo esc_html__('Resend OTP', 'awp'); ?></label>
                          <input type="text" name="awp_otp_settings[resend_otp_button_color]" id="awp_resend_otp_button_color"
                                 value="<?php echo esc_attr($resend_col); ?>" class="awp-color-field"
                                 data-default-color="#22c55e" />
                      </div>
                      <div class="txt-setting">
                          <label><?php echo esc_html__('Regular Login', 'awp'); ?></label>
                          <input type="text" name="awp_otp_settings[login_button_color]" id="awp_login_button_color"
                                 value="<?php echo esc_attr($login_col); ?>" class="awp-color-field"
                                 data-default-color="#22c55e" />
                      </div>
                    </div>
                </td>
              </tr>
              <tr>
                <th>
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Custom Shortcode', 'awp'); ?></label></h4>
                            <p><?php echo esc_html__('Add a shortcode to display extra content below the login options.', 'awp'); ?></p>
                        </div>
                    </div>
                </th>
                <td>
                  <input type="text" name="awp_otp_settings[custom_shortcode]" id="awp_custom_shortcode"
                         value="<?php echo esc_attr($sc); ?>" style="width: 100%;"/>
                </td>
              </tr>
            </table>
            <?php submit_button(); ?>
          </form>
        </div>
        <?php
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('remix-icon', AWP_PLUGIN_URL.'assets/css/resources/remixicon.css', [], '4.6.0');
        wp_enqueue_style('awp-otp-frontend-styles', AWP_PLUGIN_URL.'assets/css/awp-otp-frontend.css', [], AWP_PLUGIN_VERSION);
        wp_enqueue_script('awp-otp-frontend-scripts', AWP_PLUGIN_URL.'assets/js/awp-otp-frontend.js', ['jquery'], AWP_PLUGIN_VERSION, true);

        $settings = $this->get_plugin_settings();

        $login_methods = [];
        foreach (['enable_whatsapp'=>'whatsapp_otp','enable_email'=>'email_otp','enable_email_password'=>'email_password'] as $k => $m) {
            if (!empty($settings[$k])) {
                $login_methods[] = $m;
            }
        }
        $first_login_method = $settings['login_method'];
        if ($first_login_method && false !== ($key = array_search($first_login_method, $login_methods, true))) {
            unset($login_methods[$key]);
            array_unshift($login_methods, $first_login_method);
        }

        wp_localize_script('awp-otp-frontend-scripts', 'awpOtpAjax', [
            'ajax_url'           => add_query_arg('_nocache', time(), admin_url('admin-ajax.php')),
            'nonce'              => wp_create_nonce('awp_otp_nonce'),
            'emptyFields'        => __('Please fill in all required fields.', 'awp'),
            'first_login_method' => $first_login_method,
            'redirect_url'       => esc_url($settings['redirect_url'] ?? home_url('/')),
            'button_colors'      => [
                'request_otp_button_color' => esc_attr($settings['request_otp_button_color']),
                'verify_otp_button_color'  => esc_attr($settings['verify_otp_button_color']),
                'resend_otp_button_color'  => esc_attr($settings['resend_otp_button_color']),
                'login_button_color'       => esc_attr($settings['login_button_color']),
            ],
            'custom_shortcode'   => $settings['custom_shortcode'],
        ]);
        wp_localize_script('awp-otp-frontend-scripts', 'awpOtpL10n', [
            'checkWhatsApp' => __('Check your WhatsApp', 'awp'),
            'checkEmail'    => __('Check your Email', 'awp'),
            'weSentCode'    => __('We’ve sent you a 6-digit code to', 'awp'),
            'resendCode'    => __('Resend Code', 'awp'),
            'progressBridge' => __('We’re building a secure bridge between you and us…', 'awp'),
            'progressAlmost' => __('We’re almost sending the OTP to your WhatsApp…', 'awp'),
            'progressGo'     => __('Here we go…', 'awp'),
            'resendIn'       => __('You can resend in %s s', 'awp'),
        ]);
    }

    public function render_otp_login_form() {
        if (is_user_logged_in()) {
            return esc_html__('You are already logged in.', 'awp');
        }

        $banned_msg = get_transient('siteB_banned_msg');
        $token      = get_option('mysso_token');
        $user_data  = get_transient('siteB_user_data');

        if ($banned_msg) {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Login', 'awp') . '</h1><p style="color:red;">' . esc_html__('Site is blocked.', 'awp') . '</p></div>';
            return;
        }
        if (!$token) {
            echo '<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('OTP Login', 'awp') . '</h1><p>' . esc_html__('Need login to Wawp.', 'awp') . '</p></div>';
            return;
        }
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('OTP Login', 'awp') . '</h1><p style="color:red;">' . esc_html__('Not an active site.', 'awp') . '</p></div>';
            return;
        }

        $settings = $this->get_plugin_settings();
        ob_start(); ?>
        <div class="awp-otp-login-form">
        <?php
          if (!empty($settings['logo']) || !empty($settings['title']) || !empty($settings['description'])) {
              echo '<div class="awp-login-branding">';
              if (!empty($settings['logo'])) {
                  echo '<div class="awp-logo"><img src="'.esc_url($settings['logo']).'" alt="'.esc_attr($settings['title'] ?? __('Logo', 'awp')).'"/></div>';
              } else {
                  echo '<div class="awp-logo"></div>';
              }
              if (!empty($settings['title'])) {
                  echo '<h3 class="awp-login-title">'.esc_html($settings['title']).'</h3>';
              }
              if (!empty($settings['description'])) {
                  echo '<p class="awp-login-description">'.wp_kses_post($settings['description']).'</p>';
              }
              echo '</div>';
          }
          if (!empty($settings['custom_css'])) {
              echo '<style>.awp-otp-login-form { '.esc_html($settings['custom_css']).' }</style>';
          }
          $enabled_methods = [];
          if (!empty($settings['enable_whatsapp']))       $enabled_methods[] = 'whatsapp_otp';
          if (!empty($settings['enable_email']))          $enabled_methods[] = 'email_otp';
          if (!empty($settings['enable_email_password'])) $enabled_methods[] = 'email_password';

          $first_method = $settings['login_method'];
          if ($first_method && false !== ($k = array_search($first_method, $enabled_methods, true))) {
              unset($enabled_methods[$k]);
              array_unshift($enabled_methods, $first_method);
          }

          echo '<div class="awp-tabs"><ul class="awp-tab-list">';
          foreach ($enabled_methods as $m) {
              $active = ($m === $first_method) ? 'active' : '';
              $label  = ($m === 'whatsapp_otp'
                          ? __('WhatsApp', 'awp')
                          : ($m === 'email_otp'
                             ? __('Email OTP', 'awp')
                             : ($m === 'email_password'
                                ? __('Email and Password', 'awp')
                                : ucfirst($m))));
              echo '<li class="awp-tab '.esc_attr($active).'" data-tab="'.esc_attr($m).'">'.esc_html($label).'</li>';
          }
          echo '</ul></div>';
          echo '<div class="awp-tab-content">';
          if (!empty($settings['enable_whatsapp'])): ?>
            <div class="awp-tab-pane <?php echo ($first_method==='whatsapp_otp'?'active':''); ?>" id="whatsapp_otp">
              <form id="awp-otp-login-form-whatsapp">
                <div class="awp-form-group" id="awp_whatsapp_group">
                  <label for="awp_whatsapp"><?php echo esc_html__('WhatsApp Number', 'awp'); ?></label>
                  <input type="text" id="awp_whatsapp" name="whatsapp" placeholder="<?php echo esc_attr(__('Enter your WhatsApp number', 'awp')); ?>" required />
                </div>
                <div class="awp-form-group" id="awp_otp_group_whatsapp" style="display:none;">
                  <input id="awp_otp_whatsapp" type="text" name="otp" placeholder="<?php echo esc_attr(__('Enter OTP code..', 'awp')); ?>" required>
                </div>
                <div class="awp-form-group">
                  <button type="button" class="awp-btn awp-btn-green" id="awp_request_otp_whatsapp"
                          style="background-color:<?php echo esc_attr($settings['request_otp_button_color']); ?>;">
                          <i class="ri-whatsapp-line"></i> <?php echo esc_html__('Send Code', 'awp'); ?>
                  </button>
                  <button type="button" class="awp-submit-button awp-btn" id="awp_verify_otp_whatsapp" style="display:none;">
                          <?php echo esc_html__('Confirm', 'awp'); ?>
                  </button>
                  <button type="button" class="awp-resend-otp-btn awp-btn" id="awp_resend_otp_whatsapp" style="display: none;">
                          <?php echo esc_html__('Resend Code', 'awp'); ?>
                  </button>
                </div>
                <div class="awp-form-group" id="awp_otp_sent_message_whatsapp" style="display:none;">
                  <p class="awp-otp-resend"><?php echo esc_html__('Wrong WhatsApp?', 'awp'); ?>
                    <a type="button" class="awp-edit-button awp-edit-whatsapp"><?php echo esc_html__('Please re-enter your number', 'awp'); ?></a>
                  </p>
                </div>
                <div id="awp_display_whatsapp" style="display:none;"></div>
                <div id="awp_login_message_whatsapp"></div>
              </form>
            </div>
          <?php endif; ?>
          <?php if (!empty($settings['enable_email'])): ?>
            <div class="awp-tab-pane <?php echo ($first_method==='email_otp'?'active':''); ?>" id="email_otp">
              <form id="awp-otp-login-form-email">
                <div class="awp-form-group" id="awp_email_group">
                  <label for="awp_email"><?php echo esc_html__('Email', 'awp'); ?></label>
                  <input type="email" id="awp_email" name="email" placeholder="<?php echo esc_attr(__('Enter your email', 'awp')); ?>" required />
                </div>
                <div class="awp-form-group" id="awp_otp_group_email" style="display:none;">
                  <input type="text" id="awp_otp_email" name="otp" placeholder="<?php echo esc_attr(__('Enter OTP code..', 'awp')); ?>" required />
                </div>
                <div class="awp-form-group">
                  <button type="button" class="awp-btn awp-btn-green" id="awp_request_otp_email"
                          style="background-color:<?php echo esc_attr($settings['request_otp_button_color']); ?>;">
                          <i class="ri-mail-send-line"></i> <?php echo esc_html__('Send Code', 'awp'); ?>
                  </button>
                  <button type="button" class="awp-btn awp-btn-blue" id="awp_verify_otp_email"
                          style="display:none;background-color:<?php echo esc_attr($settings['verify_otp_button_color']); ?>;">
                          <?php echo esc_html__('Confirm', 'awp'); ?>
                  </button>
                  <button type="button" class="awp-resend-otp-btn awp-btn" id="awp_resend_otp_email"
                          style="display:none;">
                          <?php echo esc_html__('Resend Code', 'awp'); ?>
                  </button>
                </div>
                <div class="awp-form-group" id="awp_otp_sent_message_email" style="display:none;">
                  <p class="awp-otp-resend"><?php echo esc_html__('Wrong e-mail?', 'awp'); ?>
                    <a class="awp-edit-button awp-edit-email"><?php echo esc_html__('Please re-enter your email', 'awp'); ?></a>
                  </p>
                </div>
                <div id="awp_display_email" style="display:none;"></div>
                <div id="awp_login_message_email"></div>
              </form>
            </div>
          <?php endif; ?>
          <?php if (!empty($settings['enable_email_password'])): ?>
            <div class="awp-tab-pane <?php echo ($first_method==='email_password'?'active':''); ?>" id="email_password">
              <form id="awp-otp-login-form-email-password">
                <div class="awp-form-group">
                  <label for="awp_login_email"><?php echo esc_html__('Email or Username', 'awp'); ?></label>
                  <input type="text" id="awp_login_email" name="login" placeholder="<?php echo esc_attr(__('Enter your email or username', 'awp')); ?>" required />
                </div>
                <div class="awp-form-group">
                  <label for="awp_password_password"><?php echo esc_html__('Password', 'awp'); ?></label>
                  <div class="password-container">
                    <input type="password" id="awp_password_password" name="password" placeholder="<?php echo esc_attr(__('Enter your password', 'awp')); ?>" required />
                    <i class="ri-eye-line show-hide-icon" id="show-icon"></i>
                    <i class="ri-eye-off-line show-hide-icon hidden" id="hide-icon"></i>
                  </div>
                </div>
                <div class="awp-form-group">
                  <button type="button" class="awp-btn awp-btn-red" id="awp_login_email_password"
                          style="background-color:<?php echo esc_attr($settings['login_button_color']); ?>;">
                          <?php echo esc_html__('Login', 'awp'); ?>
                  </button>
                </div>
                <div id="awp_login_message_email_password"></div>
              </form>
            </div>
          <?php endif; ?>
          <?php
          echo '</div>';
          if (!empty($settings['custom_shortcode'])) {
             echo '<div class="awp-separator"><span>' . esc_html__('OR', 'awp') . '</span></div>';
             echo '<div class="awp-custom-shortcode">'.do_shortcode($settings['custom_shortcode']).'</div>';
          }
        ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_request_otp() {
        check_ajax_referer('awp_otp_nonce', 'nonce');

        $login_method = sanitize_text_field($_POST['login_method'] ?? '');
        $settings     = $this->get_plugin_settings();

        switch ($login_method) {
            case 'whatsapp_otp':
                $instance_id = sanitize_text_field($settings['instance'] ?? '');
                if (empty($instance_id)) {
                    wp_send_json_error(__('No WhatsApp instance selected.', 'awp'));
                }

                // Faster: cached DB lookup
                $instance_row = $this->get_instance_row_cached($instance_id);
                if (!$instance_row) {
                    wp_send_json_error(__('Selected WhatsApp instance is offline or invalid.', 'awp'));
                }
                $access_token = $instance_row->access_token;

                $this->handle_whatsapp_otp($settings, $instance_id, $access_token);
                break;

            case 'email_otp':
                $this->handle_email_otp($settings);
                break;

            case 'email_password':
                $this->handle_email_password_login($settings);
                break;

            default:
                wp_send_json_error(__('Invalid login method.', 'awp'));
        }
    }

    private function handle_whatsapp_otp($settings, $instance_id, $access_token) {
        $whatsapp = sanitize_text_field($_POST['whatsapp'] ?? '');
        if (empty($whatsapp)) {
            wp_send_json_error(__('WhatsApp number is required.', 'awp'));
        }

        $number = preg_replace('/\D/', '', $whatsapp);
        $user   = $this->get_user_by_whatsapp($number);
        if (!$user) {
            // Redirect to signup with prefilled phone if a signup page is set
            $signup_id  = get_option('awp_signup_page_id');
            $signup_url = $signup_id ? get_permalink($signup_id) : '';
            if ($signup_url) {
                $redirect_url = add_query_arg(['pre_phone' => rawurlencode($number)], $signup_url);
                wp_send_json_error([
                    'message'            => __('No user found with this WhatsApp number.', 'awp'),
                    'redirect_to_signup' => esc_url($redirect_url),
                ]);
            }
            wp_send_json_error(__('No user found with this WhatsApp number.', 'awp'));
        }


        $otp = wp_rand(100000, 999999);
        set_transient('awp_otp_' . $number, $otp, 10 * MINUTE_IN_SECONDS);

        $user_replacements = [
            '{{user_name}}'             => $user->user_login,
            '{{user_email}}'            => $user->user_email,
            '{{wc_billing_phone}}'      => get_user_meta($user->ID, 'billing_phone', true),
            '{{user_first_last_name}}'  => trim(get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true)),
            '{{shop_name}}'             => get_bloginfo('name'),
            '{{current_date_time}}'     => date_i18n('Y-m-d H:i:s', current_time('timestamp')),
            '{{site_link}}'             => site_url(),
            '{{otp}}'                   => $otp,
        ];

        $message = AWP_Message_Parser::parse_message_placeholders(
            $settings['otp_message_whatsapp'],
            $user_replacements,
            0,
            $user->ID
        );

        // Single send — the HTTP helper should do a micro-retry on 5xx/429.
        $whatsapp_response = $this->send_whatsapp_otp_message($instance_id, $access_token, $number, $message);

        if ($whatsapp_response['status'] === 'error') {
            $error_message_to_user = __('Failed to send OTP. Please try again.', 'awp');

            if (isset($whatsapp_response['full_response']['status']) && $whatsapp_response['full_response']['status'] === 'blocked') {
                $error_message_to_user = __('This phone number is blocked by the system.', 'awp');
            } elseif (!empty($whatsapp_response['message'])) {
                $error_message_to_user = __('Failed to send OTP: ', 'awp') . $whatsapp_response['message'];
            }
            wp_send_json_error(['message' => $error_message_to_user]);
        }

        $this->log_otp_request(
            $user->ID,
            $number,
            $whatsapp_response['full_response'] ?? $whatsapp_response,
            $message,
            $instance_id,
            $access_token
        );

        wp_send_json_success(__('OTP sent successfully.', 'awp'));
    }

    private function send_whatsapp_otp_message($instance_id, $access_token, $phone_number, $message) {
        $response = Wawp_Api_Url::send_message(
            $instance_id,
            $access_token,
            $phone_number,
            $message
        );
        if ($response['status'] !== 'success') {
            error_log('AWP OTP WhatsApp Error: ' . ($response['message'] ?? 'Unknown'));
        } else {
            // keep logging, but lightweight
            error_log('AWP OTP WhatsApp Sent');
        }
        return $response;
    }

    private function handle_email_otp($settings) {
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            wp_send_json_error(__('Email is required.', 'awp'));
        }
        if (!is_email($email)) {
            wp_send_json_error(__('Invalid email address.', 'awp'));
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            wp_send_json_error(__('No user found with this email address.', 'awp'));
        }

        $otp = wp_rand(100000, 999999);
        set_transient('awp_otp_' . $email, $otp, 10 * MINUTE_IN_SECONDS);

        $user_replacements = [
            '{{user_name}}'            => $user->user_login,
            '{{user_email}}'           => $user->user_email,
            '{{user_first_last_name}}' => trim(get_user_meta($user->ID, 'first_name', true) . ' ' . get_user_meta($user->ID, 'last_name', true)),
            '{{shop_name}}'            => get_bloginfo('name'),
            '{{current_date_time}}'    => date_i18n('Y-m-d H:i:s', current_time('timestamp')),
            '{{site_link}}'            => site_url(),
            '{{otp}}'                  => $otp,
        ];

        $message = AWP_Message_Parser::parse_message_placeholders(
            $settings['otp_message_email'],
            $user_replacements,
            0,
            $user->ID
        );

        $sent = wp_mail($email, __('Your OTP Code', 'awp'), $message, ['Content-Type: text/html; charset=UTF-8']);
        if (!$sent) {
            wp_send_json_error(__('Failed to send OTP. Please try again.', 'awp'));
        }
        wp_send_json_success(__('OTP sent successfully.', 'awp'));
    }

    private function handle_email_password_login($settings) {
        $login    = sanitize_text_field($_POST['login'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        if (empty($login) || empty($password)) {
            wp_send_json_error(__('Email/Username and password are required.', 'awp'));
        }

        $user = wp_authenticate($login, $password);
        if (is_wp_error($user)) {
            wp_send_json_error($user->get_error_message());
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        $redirect_url = $this->get_redirect_url_for_user($user);
        wp_send_json_success(['message' => __('Logged in successfully.', 'awp'), 'redirect_url' => $redirect_url]);
    }

    public function handle_verify_otp() {
        check_ajax_referer('awp_otp_nonce','nonce');

        $login_method = sanitize_text_field($_POST['login_method'] ?? '');
        $settings     = $this->get_plugin_settings();

        switch ($login_method) {
            case 'whatsapp_otp':
                $this->verify_whatsapp_otp($settings);
                break;
            case 'email_otp':
                $this->verify_email_otp($settings);
                break;
            default:
                wp_send_json_error(__('Invalid login method.', 'awp'));
        }
    }

    private function verify_whatsapp_otp($settings) {
        $whatsapp = sanitize_text_field($_POST['whatsapp'] ?? '');
        $otp_input= sanitize_text_field($_POST['otp'] ?? '');

        if (empty($whatsapp) || empty($otp_input)) {
            wp_send_json_error(__('WhatsApp number and OTP are required.', 'awp'));
        }

        $number     = preg_replace('/\D/', '', $whatsapp);
        $otp_stored = get_transient('awp_otp_'.$number);

        if ($otp_stored && $otp_input == $otp_stored) {
            $user = $this->get_user_by_whatsapp($number);
            if ($user) {
                $this->database_manager->update_user_verification($user->ID, 'whatsapp', true);

                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true, is_ssl());
                do_action('wp_login', $user->user_login, $user);

                $redirect_url = $this->get_redirect_url_for_user($user);
                wp_send_json_success([
                    'message'      => __('Logged in successfully.', 'awp'),
                    'redirect_url' => $redirect_url
                ]);
            }
           // Edge case: correct OTP but user still not found — offer signup redirect.
            $signup_id  = get_option('awp_signup_page_id');
            $signup_url = $signup_id ? get_permalink($signup_id) : '';
            if ($signup_url) {
                $redirect_url = add_query_arg(['pre_phone' => rawurlencode($number)], $signup_url);
                wp_send_json_error([
                    'message'            => __('No user found with this WhatsApp number.', 'awp'),
                    'redirect_to_signup' => esc_url($redirect_url),
                ]);
            }
            wp_send_json_error(__('No user found with this WhatsApp number.', 'awp'));

        }

        wp_send_json_error(__('Invalid or expired OTP.', 'awp'));
    }

    private function verify_email_otp($settings) {
        $email     = sanitize_email($_POST['email'] ?? '');
        $otp_input = sanitize_text_field($_POST['otp'] ?? '');
        if (empty($email) || empty($otp_input)) {
            wp_send_json_error(__('Email and OTP are required.', 'awp'));
        }
        $otp_stored = get_transient('awp_otp_'.$email);
        if ($otp_stored && $otp_input == $otp_stored) {
            $user = get_user_by('email', $email);
            if ($user) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true, is_ssl());
                do_action('wp_login', $user->user_login, $user);

                $redirect_url = $this->get_redirect_url_for_user($user);
                wp_send_json_success(['message' => __('Logged in successfully.', 'awp'), 'redirect_url' => $redirect_url]);
            }
            wp_send_json_error(__('No user found with this email.', 'awp'));
        }
        wp_send_json_error(__('Invalid or expired OTP.', 'awp'));
    }

    private function get_redirect_url_for_user($user) {
        $settings = $this->get_plugin_settings();

        if (!empty($settings['redirect_rules']) && is_array($settings['redirect_rules'])) {
            foreach ($settings['redirect_rules'] as $rule) {
                if (in_array($rule['role'], $user->roles, true)) {
                    return esc_url($rule['redirect_url']);
                }
            }
            foreach ($settings['redirect_rules'] as $rule) {
                if ($rule['role'] === 'all') {
                    return esc_url($rule['redirect_url']);
                }
            }
        }
        return '';
    }

    /* ---------------------------------------------------------------------
     * FAST user lookup: short cache + custom table first + usermeta fallback
     * ------------------------------------------------------------------- */
    private function get_user_by_whatsapp($whatsapp) {
        $number = preg_replace('/\D/', '', $whatsapp);
        if ($number === '') {
            return false;
        }

        // 1) small transient cache (ID only)
        $cache_key = 'awp_user_by_phone_' . md5($number);
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached ? get_user_by('ID', (int) $cached) : false;
        }

        // 2) prefer the fast custom table
        if ($u = $this->get_user_from_awp_custom_table($number)) {
            set_transient($cache_key, (int) $u->ID, 300);
            return $u;
        }

        // 3) fallback to usermeta query (can be slow on large sites)
        $meta_keys = ['awp_user_phone','billing_phone'];
        $mq        = ['relation' => 'OR'];
        foreach ($meta_keys as $k) {
            $mq[] = [
                'key'     => $k,
                'value'   => $number,
                'compare' => '='
            ];
        }
        $users = get_users([
            'meta_query'  => $mq,
            'number'      => 1,
            'count_total' => false
        ]);

        if (!empty($users)) {
            set_transient($cache_key, (int) $users[0]->ID, 300);
            return $users[0];
        }

        set_transient($cache_key, 0, 120); // remember miss shortly
        return false;
    }

    private function get_user_from_awp_custom_table($phone) {
        global $wpdb;
        $table  = $this->database_manager->get_user_info_table_name();
        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT user_id FROM $table WHERE phone = %s LIMIT 1", $phone)
        );
        if ($result && !empty($result->user_id)) {
            return get_user_by('ID', (int) $result->user_id);
        }
        return false;
    }

    private function log_otp_request($user_id, $whatsapp_or_email, $status, $otp_message = '', $instance_id = null, $access_token = null) {
        global $wpdb;

        $user_info     = get_userdata($user_id);
        $customer_name = $user_info ? $user_info->display_name : __('Unknown', 'awp');
        $sent_at       = current_time('mysql');
        $table_name    = $this->database_manager->get_log_table_name();

        // Encode response once, truncate to ~64KB to keep inserts snappy
        $status_json = is_array($status) ? wp_json_encode($status, JSON_UNESCAPED_UNICODE) : (string) $status;
        if (strlen($status_json) > 65535) {
            $status_json = substr($status_json, 0, 65500) . '…';
        }

        $data = [
            'user_id'          => $user_id,
            'order_id'         => null,
            'customer_name'    => $customer_name,
            'sent_at'          => $sent_at,
            'whatsapp_number'  => $whatsapp_or_email,
            'message'          => $otp_message,
            'image_attachment' => null,
            'message_type'     => __('OTP Login Request', 'awp'),
            'wawp_status'      => $status_json,
            'resend_id'        => null,
            'instance_id'      => $instance_id,
            'access_token'     => $access_token
        ];

        $wpdb->insert($table_name, $data, [
            '%d','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s'
        ]);

        if (!$wpdb->insert_id) {
            error_log('AWP OTP Log Insertion Failed: ' . print_r($wpdb->last_error, true));
        }
    }
}
