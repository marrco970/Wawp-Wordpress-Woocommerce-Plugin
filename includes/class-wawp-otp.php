<?php
if (!defined('ABSPATH')) exit;

class Wawp_Otp {
    private $login;
    private $signup;
    private $checkout_otp;
    private $custom_pages_settings;
    private $awp_instances;

    public function init( $dm, $awp_instances ) {

    $this->awp_instances = $awp_instances;

    if ( ! get_option( 'awp_wawp_otp_enabled', 1 ) ) {
        return; 
    }

    if ( get_option( 'awp_otp_login_enabled', 1 ) && class_exists( 'AWP_Otp_Login' ) ) {
        $this->login = new AWP_Otp_Login( $dm, $awp_instances );
        $this->login->init();
    }

    if ( get_option( 'awp_signup_enabled', 1 ) && class_exists( 'AWP_Signup' ) ) {
        $this->signup = AWP_Signup::get_instance();
    }

    if ( get_option( 'awp_checkout_otp_enabled', 1 ) && class_exists( 'awp_checkout_otp' ) ) {
        $this->checkout_otp = new awp_checkout_otp();
    }
    
    if ( class_exists( 'AWP_Custom_Login_Pages' ) ) {
    $this->custom_pages_settings = new AWP_Custom_Login_Pages();
    }
}

    public function render_tabs_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized user', 'awp'));
    }

    $banned_msg     = get_transient('siteB_banned_msg');
    $token          = get_option('mysso_token');
    $user_data      = get_transient('siteB_user_data');
    if ($banned_msg) {
        echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('Wawp OTP Messages', 'awp') . '</h1><p style="color:red;">' . esc_html(Wawp_Global_Messages::get('blocked_generic')) . '</p></div>';
        return;
    }
    if (!$token) {
        echo '<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('Wawp OTP Messages', 'awp') . '</h1><p>' . esc_html(Wawp_Global_Messages::get('need_login')) . '</p></div>';
        return;
    }
    $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
    if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
        echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('Wawp OTP Messages', 'awp') . '</h1><p style="color:red;">' . esc_html(Wawp_Global_Messages::get('not_active_site')) . '</p></div>';
        return;
    }

    if (!AWP_Admin_Notices::require_online_instance($this->awp_instances)) {
        return;
    }

    $tabs = [];
    if ($this->login) {
        $tabs['tab-otp-login'] = [
            'title'       => esc_html__('OTP Login', 'awp'),
            'description' => esc_html__('Login using OTP via WhatsApp or email to ensure a better user experience', 'awp'),
            'shortcode'   => '[wawp_otp_login]',
            'render'      => function() { $this->login->render_admin_page(); }
        ];
    }
    if ($this->signup) {
        $tabs['tab-signup'] = [
            'title'       => esc_html__('Signup', 'awp'),
            'description' => esc_html__("Customize the Signup experience on your site.", 'awp'),
            'shortcode'   => '[wawp_signup_form]',
            'render'      => function() { $this->signup->render_admin_page(); }
        ];
    }
    if ($this->checkout_otp) {
        $tabs['tab-checkout'] = [
            'title'       => esc_html__('Checkout OTP', 'awp'),
            'description' => esc_html__('Verify customer numbers on checkout and reduce fake orders ', 'awp'),
            'shortcode'   => '[woocommerce_checkout]',
            'render'      => function() { $this->checkout_otp->settings_page_html(); }
        ];
    }
    if ($this->custom_pages_settings) {
        $tabs['tab-login-signup-pages'] = [
            'title'       => esc_html__('Login/Signup Pages', 'awp'),
            'description' => esc_html__('Configure custom pages for login, signup, redirects, and integrations.', 'awp'),
            'shortcode'   => '[wawp-fast-login]',
            'render'      => function() { $this->custom_pages_settings->render_tab_content(); }
        ];
    }

    if (empty($tabs)) {
        echo '<div class="wrap"><p>' . esc_html__('All OTP features are disabled. Enable them from the Dashboard toggles.', 'awp') . '</p></div>';
        return;
    }

    $default_tab = array_key_first($tabs);
    $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $tabs) ? sanitize_key($_GET['tab']) : $default_tab;

    echo '<div class="wrap">';
    echo '<div class="otp-content">
            <div id="sub-header" class="page-header_row">
                <div class="page-header">
                    <h2 id="wawp-otp-title" class="page-title"></h2>
                    <p id="wawp-otp-description"></p>
                </div>
                <div id="wawp-otp-shortcode-container" style="display: none;">
                    <span id="wawp-otp-shortcode"></span>
                    <button id="copy-shortcode" class="hint-btn awp-btn secondary">' . esc_html__('Copy', 'awp') . '</button>
                </div>
            </div>';

    $base_url = admin_url('admin.php?page=wawp&awp_section=otp_messages');
    echo '<div class="nav-tab-wrapper" style="margin-bottom: 1.5rem;">';
    foreach ($tabs as $id => $data) {
        $active_class = ($active_tab === $id) ? ' nav-tab-active' : '';
        $tab_url = add_query_arg('tab', $id, $base_url);

        echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . $active_class . '" 
                   data-title="' . esc_attr($data['title']) . '" 
                   data-description="' . esc_attr($data['description']) . '" 
                   data-shortcode="' . esc_attr($data['shortcode']) . '">'
               . esc_html($data['title'])
             . '</a>';
    }
    echo '</div>'; 

    foreach ($tabs as $id => $data) {
        $display_style = ($active_tab === $id) ? 'block' : 'none';
        echo '<div id="' . esc_attr($id) . '" class="wawp-tab-content" style="display:' . $display_style . ';">';
        call_user_func($data['render']);
        echo '</div>';
    }

    echo '</div>'; 
    echo '</div>'; 
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabWrapper = document.querySelector('.nav-tab-wrapper');
        if (!tabWrapper) return;

        const titleEl = document.getElementById('wawp-otp-title');
        const descEl = document.getElementById('wawp-otp-description');
        const shortcodeEl = document.getElementById('wawp-otp-shortcode');
        const shortcodeContainer = document.getElementById('wawp-otp-shortcode-container');
        const copyButton = document.getElementById('copy-shortcode');

        const updateHeader = (tab) => {
            if (!tab) return;
            const title = tab.dataset.title;
            const description = tab.dataset.description;
            const shortcode = tab.dataset.shortcode;

            if (titleEl) titleEl.textContent = title;
            if (descEl) descEl.textContent = description;
            
            if (shortcodeContainer) {
                if (shortcode) {
                    shortcodeContainer.style.display = '';
                    if (shortcodeEl) shortcodeEl.textContent = shortcode;
                } else {
                    shortcodeContainer.style.display = 'none';
                }
            }
        };

        const initialActiveTab = tabWrapper.querySelector('.nav-tab-active');
        updateHeader(initialActiveTab);

        tabWrapper.addEventListener('click', function(e) {
            const targetTab = e.target.closest('.nav-tab');
            if (!targetTab) return;

            e.preventDefault();

            tabWrapper.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
            targetTab.classList.add('nav-tab-active');
            
            const tabUrl = new URL(targetTab.href);
            const targetContentId = tabUrl.searchParams.get('tab');
            
            document.querySelectorAll('.wawp-tab-content').forEach(content => {
                content.style.display = (content.id === targetContentId) ? 'block' : 'none';
            });

            if (window.history.pushState) {
                window.history.pushState({ path: targetTab.href }, '', targetTab.href);
            }
            
            updateHeader(targetTab);
        });

        if (copyButton) {
            copyButton.addEventListener('click', function() {
                if (shortcodeEl && navigator.clipboard) {
                    navigator.clipboard.writeText(shortcodeEl.textContent).then(() => {
                        const originalText = this.textContent;
                        this.textContent = '<?php echo esc_js(__('Copied!', 'awp')); ?>';
                        setTimeout(() => { this.textContent = originalText; }, 1500);
                    });
                }
            });
        }
    });
    </script>
    <?php
}

}
