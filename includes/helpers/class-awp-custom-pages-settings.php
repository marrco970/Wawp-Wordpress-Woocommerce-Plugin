<?php

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AWP_Custom_Login_Pages {


    private $admin_texts;

    private $option_group = 'awp_settings_group';
    private $login_page_option = 'awp_login_page_id';
    private $signup_page_option = 'awp_signup_page_id';
    private $fast_login_page_option = 'awp_fast_login_page_id';
    private $replace_wc_forms_option = 'awp_replace_wc_forms';
    private $show_phone_bar_option = 'awp_show_phone_bar_in_account';
    private $show_prefs_option = 'awp_show_prefs_in_account';
    private $redirect_wp_login_option = 'awp_redirect_wp_login';
    private $redirect_my_account_option = 'awp_redirect_my_account';

    public function __construct() {
        
        add_action('init', [ $this, 'setup_admin_texts' ]);
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_awp_create_login_page', [ $this, 'handle_create_login_page' ] );
        add_action( 'admin_post_awp_create_signup_page', [ $this, 'handle_create_signup_page' ] );
        add_action( 'admin_post_awp_create_fast_login_page', [ $this, 'handle_create_fast_login_page' ] );
        add_action( 'template_redirect', [ $this, 'handle_redirects' ] );
        add_action( 'login_init', [ $this, 'handle_login_page_redirect' ] );
        add_action( 'init', [ $this, 'setup_integrations' ] );
        add_action( 'wp_ajax_awp_save_settings', [ $this, 'ajax_save_settings' ] );
    }

    public function setup_admin_texts() {
        $this->admin_texts = [
            'login_page_title' => __( 'Login', 'awp' ),
            'signup_page_title' => __( 'Sign Up', 'awp' ),
            'fast_login_page_title' => __( 'Fast Login', 'awp' ),
            'login_settings_title' => __( 'Login Page Settings', 'awp' ),
            'signup_settings_title' => __( 'Signup Page Settings', 'awp' ),
            'fast_login_settings_title' => __( 'Fast Login Page Settings', 'awp' ),
            'redirect_settings_title' => __( 'Redirect & Integration Settings', 'awp' ),
            'shortcodes_section_title' => __( 'Available Shortcodes', 'awp' ),
            'select_login_page_label' => __( 'Select Login Page', 'awp' ),
            'select_signup_page_label' => __( 'Select Signup Page', 'awp' ),
            'select_fast_login_page_label' => __( 'Select Fast Login Page', 'awp' ),
            'redirect_wp_login_label' => __( 'Redirect wp-login.php', 'awp' ),
            'redirect_my_account_label' => __( 'Redirect My Account', 'awp' ),
            'replace_wc_forms_label' => __( 'Replace WC Forms', 'awp' ),
            'show_phone_bar_label' => __( 'Phone Verification Bar', 'awp' ),
            'show_prefs_label' => __( 'Notification Preferences', 'awp' ),
            'login_page_desc' => __( 'Select a page to use as the main login page. This will redirect /wp-login.php and /my-account (for logged-out users).', 'awp' ),
            'signup_page_desc' => __( 'Select a page to use as the main signup page.', 'awp' ),
            'fast_login_page_desc' => __( 'Select a page for the [wawp-fast-login] shortcode. This setting does not enable any redirects.', 'awp' ),
            'redirect_wp_login_desc' => __( 'Redirect default Login Page (wp-login.php & wp-admin)', 'awp' ),
            'redirect_my_account_desc' => __( 'Redirect logged-out users from the "My Account" page to the custom login page.', 'awp' ),
            'replace_wc_forms_desc' => __( 'Replace the default WC login & registration forms on the "My Account" and "Checkout" pages.', 'awp' ),
            'show_phone_bar_desc' => __( 'Display the phone verification bar in the "My Account" area for unverified users.', 'awp' ),
            'show_prefs_desc' => __( 'Display the notification preferences section on the "My Account" page.', 'awp' ),
            'save_settings_btn' => __( 'Save Settings', 'awp' ),
            'create_login_page_btn' => __( 'Create & Set', 'awp' ),
            'create_signup_page_btn' => __( 'Create & Set', 'awp' ),
            'create_fast_login_page_btn' => __( 'Create & Set', 'awp' ),
            'edit_btn' => __( 'Edit', 'awp' ),
            'copy_btn' => __( 'Copy', 'awp' ),
            'copied_btn' => __( 'Copied!', 'awp' ),
            'otp_login_title' => __( 'OTP Login Form', 'awp' ),
            'otp_login_desc' => __( 'Allow your users to login via WhatsApp / Email OTP by using this shortcode on any page.', 'awp' ),
            'otp_signup_title' => __( 'OTP Signup Form', 'awp' ),
            'otp_signup_desc' => __( 'Allow new users to sign up using their phone number and an OTP verification.', 'awp' ),
            'checkout_otp_title' => __( 'Checkout OTP', 'awp' ),
            'checkout_otp_desc' => __( 'Use the standard WooCommerce checkout form. OTP functionality is integrated if enabled.', 'awp' ),
            'fast_login_title' => __( 'Login/Signup Form', 'awp' ),
            'fast_login_desc' => __( 'Add login and signup forms on the same page for quick access.', 'awp' ),
            'user_prefs_title' => __( 'User Notifications Preferences', 'awp' ),
            'user_prefs_desc' => __( 'Allow your clients to enable/disable receiving notifications from their account page.', 'awp' ),
            'verify_phone_title' => __( 'Verify User Number', 'awp' ),
            'verify_phone_desc' => __( 'Request that your clients confirm their phone number. Can be used on a popup, front page, or user account area.', 'awp' ),
            'dont_have_account_text' => __( "Don't have an account? <a href='%s'>Sign Up</a>", 'awp' ),
            'already_have_account_text' => __( "Already have an account? <a href='%s'>Login</a>", 'awp' ),
            'page_created_success' => __( '%s page has been created and set successfully.', 'awp' ),
            'page_creation_error' => __( 'There was an error creating or updating the page.', 'awp' ),
            'security_check_failed' => __( 'Security check failed!', 'awp' ),
            'no_permission_error' => __( 'You do not have permission to create pages.', 'awp' ),
            'copy_error' => __( 'Oops, unable to copy', 'awp' ),
            'select_page_placeholder' => __( '&mdash; Select a Page &mdash;', 'awp' ),
            'unsaved_changes_warning' => __( 'You have unsaved changes. Are you sure you want to leave?', 'awp' ),
            'settings_saved_notice' => __( 'Settings saved.', 'awp' ),
        ];
    }

    private function get_text( $key ) {
        return isset( $this->admin_texts[ $key ] ) ? $this->admin_texts[ $key ] : '';
    }

    public function activate_plugin() {
        $login_page_slug = 'login';
        $login_page_title = $this->get_text('login_page_title');
        $login_shortcode = '[wawp_otp_login]';
        $existing_login_page = get_page_by_path($login_page_slug, OBJECT, 'page');

        if (!$existing_login_page) {
            $login_page_id = wp_insert_post([
                'post_title'     => $login_page_title,
                'post_name'      => $login_page_slug,
                'post_content'   => $login_shortcode,
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ]);
        } else {
            $login_page_id = $existing_login_page->ID;
        }

        $signup_page_slug = 'signup';
        $signup_page_title = $this->get_text('signup_page_title');
        $signup_shortcode = '[wawp_signup_form]';
        $existing_signup_page = get_page_by_path($signup_page_slug, OBJECT, 'page');

        if (!$existing_signup_page) {
            $signup_page_id = wp_insert_post([
                'post_title'     => $signup_page_title,
                'post_name'      => $signup_page_slug,
                'post_content'   => $signup_shortcode,
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ]);
        } else {
            $signup_page_id = $existing_signup_page->ID;
        }

        if ($login_page_id && !is_wp_error($login_page_id)) {
            update_option($this->login_page_option, $login_page_id);
        }
        if ($signup_page_id && !is_wp_error($signup_page_id)) {
            update_option($this->signup_page_option, $signup_page_id);
        }

        if ($login_page_id && !is_wp_error($login_page_id)) {
            $login_content = $login_shortcode;
            if ($signup_page_id && ($signup_url = get_permalink($signup_page_id))) {
                $login_content .= "\n\n" . '<p>' . sprintf( $this->get_text('dont_have_account_text'), esc_url($signup_url) ) . '</p>';
            }
            wp_update_post(['ID' => $login_page_id, 'post_content' => $login_content]);
        }

        if ($signup_page_id && !is_wp_error($signup_page_id)) {
            $signup_content = $signup_shortcode;
            if ($login_page_id && ($login_url = get_permalink($login_page_id))) {
                $signup_content .= "\n\n" . '<p>' . sprintf( $this->get_text('already_have_account_text'), esc_url($login_url) ) . '</p>';
            }
            wp_update_post(['ID' => $signup_page_id, 'post_content' => $signup_content]);
        }
    }

    public function deactivate_plugin() {
        flush_rewrite_rules();
    }
    
    public function ajax_save_settings() {
        check_ajax_referer('awp-admin-nonce', 'security');
        
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Permission denied.');
        }

        $options_to_save = [
            $this->redirect_wp_login_option,
            $this->redirect_my_account_option,
            $this->replace_wc_forms_option,
            $this->show_phone_bar_option,
            $this->show_prefs_option,
        ];

        foreach( $options_to_save as $option_name ) {
            if ( isset( $_POST[$option_name] ) && $_POST[$option_name] === '1' ) {
                update_option( $option_name, 1 );
            } else {
                update_option( $option_name, 0 );
            }
        }
        
        wp_send_json_success(['message' => $this->get_text('settings_saved_notice')]);
    }

    public function render_tab_content() {
        ?>
        <div class="wrap">
            <?php settings_errors(); ?>
            <form id="awp-settings-form" method="post" action="options.php">
                <?php settings_fields( $this->option_group ); ?>


                    <table class="form-table" role="presentation">
                        <?php do_settings_fields( 'awp_login_signup_settings', 'awp_login_settings_section' ); ?>
                    </table>


                    <table class="form-table" role="presentation">
                        <?php do_settings_fields( 'awp_login_signup_settings', 'awp_signup_settings_section' ); ?>
                    </table>
    
                
            
                    <table class="form-table" role="presentation">
                        <?php do_settings_fields( 'awp_login_signup_settings', 'awp_fast_login_settings_section' ); ?>
                    </table>
       

                <div class="awp-card">
                  <div class="awp-settings-grid-wrapper">
    <div class="awp-settings-grid">
        <table class="form-table" role="presentation">
            <?php do_settings_fields( 'awp_login_signup_settings', 'awp_wc_integration_section' ); ?>
        </table>
    </div>
</div>
</div>
                <?php submit_button( $this->get_text('save_settings_btn') ); ?>
                
            </form>
        </div>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('awp-settings-form');
                let isDirty = false;

                const pageSelectors = form.querySelectorAll('.awp-page-selector');
                pageSelectors.forEach(selector => {
                    selector.addEventListener('change', () => { isDirty = true; });
                });

                form.addEventListener('submit', () => { isDirty = false; });

                window.addEventListener('beforeunload', (event) => {
                    if (isDirty) {
                        event.preventDefault();
                        event.returnValue = '<?php echo esc_js($this->get_text("unsaved_changes_warning")); ?>';
                        return '<?php echo esc_js($this->get_text("unsaved_changes_warning")); ?>';
                    }
                });

                const autoSaveSwitches = form.querySelectorAll('.awp-autosave-switch');
                autoSaveSwitches.forEach(switchInput => {
                    switchInput.addEventListener('change', () => {
                        const formData = new FormData();
                        formData.append('action', 'awp_save_settings');
                        formData.append('security', '<?php echo wp_create_nonce("awp-admin-nonce"); ?>');
                        
                        autoSaveSwitches.forEach(el => {
                            formData.append(el.name, el.checked ? '1' : '0');
                        });

                        const notice = document.getElementById('awp-autosave-notice');
                        notice.style.display = 'inline-block';
                        notice.textContent = 'Saving...';

                        fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => {
                            if(data.success) {
                                notice.textContent = data.data.message;
                            } else {
                                notice.textContent = 'Error!';
                                notice.style.background = '#dc3545';
                            }
                            setTimeout(() => {
                                notice.style.display = 'none';
                                notice.style.background = '#28a745';
                            }, 2500);
                        });
                    });
                });
                
                const copyButtons = document.querySelectorAll('.awp-copy-button');
                copyButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const shortcode = this.dataset.shortcode;
                        navigator.clipboard.writeText(shortcode).then(() => {
                            const originalText = this.textContent;
                            this.textContent = '<?php echo esc_js( $this->get_text("copied_btn") ); ?>';
                            setTimeout(() => { this.textContent = originalText; }, 2000);
                        }).catch(() => {
                             alert('<?php echo esc_js( $this->get_text("copy_error") ); ?>');
                        });
                    });
                });
            });
        </script>
        <?php
    }

    public function register_settings() {
        register_setting( $this->option_group, $this->login_page_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->signup_page_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->fast_login_page_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->replace_wc_forms_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->show_phone_bar_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->show_prefs_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->redirect_wp_login_option, [ 'sanitize_callback' => 'absint' ] );
        register_setting( $this->option_group, $this->redirect_my_account_option, [ 'sanitize_callback' => 'absint' ] );

        add_settings_section( 'awp_login_settings_section', '', null, 'awp_login_signup_settings' );
        add_settings_section( 'awp_signup_settings_section', '', null, 'awp_login_signup_settings' );
        add_settings_section( 'awp_fast_login_settings_section', '', null, 'awp_login_signup_settings' );
        add_settings_section( 'awp_wc_integration_section', '', null, 'awp_login_signup_settings' );
        add_settings_section( 'awp_shortcodes_section', '', [ $this, 'render_shortcodes_section' ], 'awp_login_signup_settings' );

        add_settings_field( $this->login_page_option, $this->get_text('select_login_page_label'), [ $this, 'render_login_page_field' ], 'awp_login_signup_settings', 'awp_login_settings_section' );
        add_settings_field( $this->signup_page_option, $this->get_text('select_signup_page_label'), [ $this, 'render_signup_page_field' ], 'awp_login_signup_settings', 'awp_signup_settings_section' );
        add_settings_field( $this->fast_login_page_option, $this->get_text('select_fast_login_page_label'), [ $this, 'render_fast_login_page_field' ], 'awp_login_signup_settings', 'awp_fast_login_settings_section' );

        $desc_style = '<p class="description" style="font-weight:normal; margin-top:5px; padding:0;">';
        
        add_settings_field( $this->redirect_wp_login_option, $this->get_text('redirect_wp_login_label') . $desc_style . $this->get_text('redirect_wp_login_desc') . '</p>', [ $this, 'render_switch_field' ], 'awp_login_signup_settings', 'awp_wc_integration_section', ['option_name' => $this->redirect_wp_login_option, 'default' => 0, 'class' => 'awp-autosave-switch'] );
        add_settings_field( $this->redirect_my_account_option, $this->get_text('redirect_my_account_label') . $desc_style . $this->get_text('redirect_my_account_desc') . '</p>', [ $this, 'render_switch_field' ], 'awp_login_signup_settings', 'awp_wc_integration_section', ['option_name' => $this->redirect_my_account_option, 'default' => 0, 'class' => 'awp-autosave-switch'] );
        
        if ( class_exists( 'WooCommerce' ) ) {
            add_settings_field( $this->replace_wc_forms_option, $this->get_text('replace_wc_forms_label') . $desc_style . $this->get_text('replace_wc_forms_desc') . '</p>', [ $this, 'render_switch_field' ], 'awp_login_signup_settings', 'awp_wc_integration_section', ['option_name' => $this->replace_wc_forms_option, 'class' => 'awp-autosave-switch'] );
            add_settings_field( $this->show_phone_bar_option, $this->get_text('show_phone_bar_label') . $desc_style . $this->get_text('show_phone_bar_desc') . '</p>', [ $this, 'render_switch_field' ], 'awp_login_signup_settings', 'awp_wc_integration_section', ['option_name' => $this->show_phone_bar_option, 'default' => 0, 'class' => 'awp-autosave-switch'] );
            add_settings_field( $this->show_prefs_option, $this->get_text('show_prefs_label') . $desc_style . $this->get_text('show_prefs_desc') . '</p>', [ $this, 'render_switch_field' ], 'awp_login_signup_settings', 'awp_wc_integration_section', ['option_name' => $this->show_prefs_option, 'default' => 0, 'class' => 'awp-autosave-switch'] );
        }
    }

    public function render_switch_field( $args ) {
        $option_name = $args['option_name'];
        $default = isset($args['default']) ? $args['default'] : 0;
        $class = isset($args['class']) ? $args['class'] : '';
        $option_value = get_option( $option_name, $default );
        ?>
        <label class="awp-switch">
            <input type="checkbox" class="<?php echo esc_attr($class); ?>" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="1" <?php checked( $option_value, 1 ); ?> />
            <span class="awp-slider"></span>
        </label>
        <?php
    }

    public function render_login_page_field() {
        $selected_page_id = get_option( $this->login_page_option );
        $this->render_page_selector( $this->login_page_option, $selected_page_id );
        $create_url = add_query_arg( [ 'action' => 'awp_create_login_page', '_wpnonce' => wp_create_nonce('awp_create_login_page_nonce') ], admin_url('admin-post.php') );
        echo ' <a href="' . esc_url($create_url) . '" class="awp-btn secondary">' . esc_html($this->get_text('create_login_page_btn')) . '</a>';
        if ( $selected_page_id ) {
            $edit_link = get_edit_post_link( $selected_page_id );
            if ( $edit_link ) echo ' <a href="' . esc_url( $edit_link ) . '" class="awp-btn" target="_blank">' . esc_html($this->get_text('edit_btn')) . '</a>';
        }
        echo '<p class="description">' . esc_html($this->get_text('login_page_desc')) . '</p>';
    }

    public function render_signup_page_field() {
        $selected_page_id = get_option( $this->signup_page_option );
        $this->render_page_selector( $this->signup_page_option, $selected_page_id );
        $create_url = add_query_arg( [ 'action' => 'awp_create_signup_page', '_wpnonce' => wp_create_nonce('awp_create_signup_page_nonce') ], admin_url('admin-post.php') );
        echo ' <a href="' . esc_url($create_url) . '" class="awp-btn secondary">' . esc_html($this->get_text('create_signup_page_btn')) . '</a>';
        if ( $selected_page_id ) {
            $edit_link = get_edit_post_link( $selected_page_id );
            if ( $edit_link ) echo ' <a href="' . esc_url( $edit_link ) . '" class="awp-btn" target="_blank">' . esc_html($this->get_text('edit_btn')) . '</a>';
        }
        echo '<p class="description">' . esc_html($this->get_text('signup_page_desc')) . '</p>';
    }

    public function render_fast_login_page_field() {
        $selected_page_id = get_option( $this->fast_login_page_option );
        $this->render_page_selector( $this->fast_login_page_option, $selected_page_id );
        $create_url = add_query_arg( [ 'action' => 'awp_create_fast_login_page', '_wpnonce' => wp_create_nonce('awp_create_fast_login_page_nonce') ], admin_url('admin-post.php') );
        echo ' <a href="' . esc_url($create_url) . '" class="awp-btn secondary">' . esc_html($this->get_text('create_fast_login_page_btn')) . '</a>';
        if ( $selected_page_id ) {
            $edit_link = get_edit_post_link( $selected_page_id );
            if ( $edit_link ) echo ' <a href="' . esc_url( $edit_link ) . '" class="awp-btn" target="_blank">' . esc_html($this->get_text('edit_btn')) . '</a>';
        }
        echo '<p class="description">' . esc_html($this->get_text('fast_login_page_desc')) . '</p>';
    }

    private function render_page_selector( $name, $selected_page_id ) {
        $pages = get_pages();
        ?>
        <select name="<?php echo esc_attr( $name ); ?>" class="awp-page-selector" style="width: 25em;">
            <option value=""><?php echo esc_html( $this->get_text('select_page_placeholder') ); ?></option>
            <?php foreach ( $pages as $page ) : ?>
                <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $selected_page_id, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_shortcodes_section() {
        $shortcodes = [
            [ 'title' => $this->get_text('otp_login_title'), 'description' => $this->get_text('otp_login_desc'), 'code' => '[wawp_otp_login]' ],
            [ 'title' => $this->get_text('otp_signup_title'), 'description' => $this->get_text('otp_signup_desc'), 'code' => '[wawp_signup_form]' ],
            [ 'title' => $this->get_text('checkout_otp_title'), 'description' => $this->get_text('checkout_otp_desc'), 'code' => '[woocommerce_checkout]' ],
            [ 'title' => $this->get_text('fast_login_title'), 'description' => $this->get_text('fast_login_desc'), 'code' => '[wawp-fast-login]' ],
            [ 'title' => $this->get_text('user_prefs_title'), 'description' => $this->get_text('user_prefs_desc'), 'code' => '[awp_notification_prefs]' ],
            [ 'title' => $this->get_text('verify_phone_title'), 'description' => $this->get_text('verify_phone_desc'), 'code' => '[wawp_phone_verification_bar]' ],
        ];
        foreach ($shortcodes as $shortcode) {
            $this->render_shortcode_block($shortcode['title'], $shortcode['description'], $shortcode['code']);
        }
    }

    private function render_shortcode_block($title, $description, $code) {
        ?>
        <div class="awp-card">
            <h4 class="awp-card-title"><?php echo esc_html($title); ?></h4>
            <p class="description"><?php echo esc_html($description); ?></p>
            <div class="awp-shortcode-display">
                <code><?php echo esc_html($code); ?></code>
                <button class="awp-btn awp-copy-button" data-shortcode="<?php echo esc_attr($code); ?>"><?php echo esc_html($this->get_text('copy_btn')); ?></button>
            </div>
        </div>
        <?php
    }

    public function handle_create_login_page() { $this->handle_create_page('login'); }
    
    public function handle_create_signup_page() { $this->handle_create_page('signup'); }
    
    public function handle_create_fast_login_page() { $this->handle_create_page('fast_login'); }

    private function handle_create_page( $type ) {
        $nonce_action = "awp_create_{$type}_page_nonce";
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_action ) ) {
            wp_die( $this->get_text('security_check_failed') );
        }
        if ( ! current_user_can( 'publish_pages' ) ) {
            wp_die( $this->get_text('no_permission_error') );
        }

        $option_name = "awp_{$type}_page_id";
        $login_page_id = get_option($this->login_page_option);
        $signup_page_id = get_option($this->signup_page_option);
             
        $post_content = '';
        if ($type === 'login') {
            $page_slug = 'login';
            $page_title = $this->get_text('login_page_title');
            $post_content = '[wawp-fast-login]';
        } elseif ($type === 'signup') {
            $page_slug = 'signup';
            $page_title = $this->get_text('signup_page_title');
            $post_content = '[wawp_signup_form]';
            if ($login_page_id && ($login_url = get_permalink($login_page_id))) {
                $post_content .= "\n\n" . '<p>' . sprintf( $this->get_text('already_have_account_text'), esc_url($login_url) ) . '</p>';
            }
        } elseif ($type === 'fast_login') {
            $page_slug = 'fast-login';
            $page_title = $this->get_text('fast_login_page_title');
            $post_content = '[wawp-fast-login]';
        }

        $existing_page = get_page_by_path( $page_slug, OBJECT, 'page' );
        if ( $existing_page ) {
            $page_id = $existing_page->ID;
            wp_update_post([ 'ID' => $page_id, 'post_content' => $post_content ]);
        } else {
            $page_id = wp_insert_post([ 'post_title' => $page_title, 'post_name' => $page_slug, 'post_content' => $post_content, 'post_status' => 'publish', 'post_author' => get_current_user_id(), 'post_type' => 'page', 'comment_status' => 'closed', 'ping_status' => 'closed' ]);
        }

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( $option_name, $page_id );
            add_settings_error('awp_page_creation', 'settings_updated', sprintf( $this->get_text('page_created_success'), $page_title ), 'success');
        } else {
            add_settings_error('awp_page_creation', 'settings_error', $this->get_text('page_creation_error'), 'error');
        }
             
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect( admin_url( 'admin.php?page=wawp&awp_section=otp_messages&tab=tab-login-signup-pages' ) );
        exit;
    }

    public function handle_login_page_redirect() {
        if ( ! get_option( $this->redirect_wp_login_option, 1 ) ) {
            return;
        }

        $login_page_id = get_option( $this->login_page_option );
        if ( ! $login_page_id || ! ($login_page_url = get_permalink( $login_page_id )) ) {
            return;
        }
       
        $allowed_actions = [ 'logout', 'postpass', 'rp', 'resetpass', 'confirm_admin_email', 'lostpassword' ];
        $action = isset( $_GET['action'] ) ? $_GET['action'] : '';

        if ( ! in_array( $action, $allowed_actions, true ) ) {
            $redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( $_REQUEST['redirect_to'] ) : '';
            $redirect_url = $redirect_to ? add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_page_url ) : $login_page_url;
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    public function handle_redirects() {
        if ( get_option( $this->redirect_my_account_option, 1 ) && ! is_user_logged_in() && function_exists( 'is_account_page' ) && is_account_page() ) {
            $login_page_id = get_option( $this->login_page_option );
            if ( $login_page_id && ($login_page_url = get_permalink( $login_page_id )) ) {
                wp_safe_redirect( $login_page_url );
                exit;
            }
        }
    }

    public function setup_integrations() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        if ( get_option( $this->replace_wc_forms_option ) ) {
            remove_action('woocommerce_login_form', 'woocommerce_login_form', 10);
            add_action('woocommerce_login_form', [ $this, 'output_login_shortcode' ]);
            add_action('woocommerce_register_form_start', [ $this, 'output_signup_shortcode' ]);
            add_action('wp_head', [ $this, 'add_wc_hiding_css' ]);
        }

        if ( get_option( $this->show_prefs_option, 1 ) ) {
            add_action( 'woocommerce_account_content', [ $this, 'display_notification_prefs_shortcode' ] );
        }
    }

    public function output_login_shortcode() {
        static $has_run = false;
        if ( $has_run ) {
            return;
        }
        echo do_shortcode('[wawp-fast-login]');
        $has_run = true;
    }

    public function output_signup_shortcode() {
        static $has_run = false;
        if ( $has_run ) {
            return;
        }
        echo do_shortcode('[wawp_signup_form]');
        $has_run = true;
    }

    public function display_notification_prefs_shortcode() {
        static $has_run = false;
        if ( $has_run ) {
            return;
        }
        if ( is_user_logged_in() ) {
            echo do_shortcode('[awp_notification_prefs]');
            $has_run = true;
        }
    }

    public function add_wc_hiding_css() {
            ?>
            <style type="text/css">
            .woocommerce-form.woocommerce-form-login.login .form-row.form-row-first {
              display: none !important;
            }
            .woocommerce-form.woocommerce-form-login.login .form-row.form-row-last {
              display: none !important;
            }
            .woocommerce-form.woocommerce-form-login.login .woocommerce-form-row.woocommerce-form-row--wide.form-row.form-row-wide {
              display: none !important;
            }
            .woocommerce-button.button.woocommerce-form-login__submit.wp-element-button {
              display: none !important;
            }
            .woocommerce-Button.woocommerce-button.button.wp-element-button.woocommerce-form-register__submit {
              display: none !important;
            }
            #reg_email {
              display: none !important;
            }
            #customer_login .woocommerce-FormRow.woocommerce-FormRow--wide.form-row.form-row-wide.form-row-username {
              display: none !important;
            }
            #customer_login .woocommerce-FormRow.woocommerce-FormRow--wide.form-row.form-row-wide.form-row-password {
              display: none !important;
            }
            .wd-col.col-login .button.woocommerce-button.woocommerce-form-login__submit {
              display: none !important;
            }
            .button.woocommerce-button.woocommerce-form-login__submit {
              display: none !important;
            }
            .woocommerce-Button.woocommerce-button.button.woocommerce-form-register__submit {
              display: none !important;
            }
            .u-column2.col-2.register-column {
              display: none !important;
            }
            .u-column1.col-1.login-column {
              width: calc(100% - 30px);
              float: inherit;
              border: 0px !important;
            }
            </style>
            <?php
        if ( function_exists('is_checkout') && is_checkout() ) {
            ?>
            <style type="text/css"></style>
            <?php
        }
    }
}

$awp_custom_login_pages_instance = new AWP_Custom_Login_Pages();