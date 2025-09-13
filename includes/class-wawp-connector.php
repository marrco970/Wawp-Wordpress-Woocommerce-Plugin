<?php
if (!defined('ABSPATH')) exit;

class Wawp_Global_Messages
{

    private static function messages_map()
    {
        return [
            'need_login' => __('You must be logged in to Wawp to access all features.', 'awp'),
            'blocked_prefix' => __('Your account has been banned for: ', 'awp'),
            'blocked_misuse' => __('Your account has been banned for misuse of the service', 'awp'),
            'blocked_generic' => __('Your account has been banned.', 'awp'),
            'no_subscription' => __('No subscription found.', 'awp'),
            'not_active_site' => __('Current site is not active or is in overload status. Data cannot be displayed.', 'awp'),
            'overloaded_limit_Instances' => __('You have reached the maximum number of instances for all sites.', 'awp'),
            'not_logged_in' => __('You must be logged in to generate a token.', 'awp'),
        ];
    }

    public static function get($key)
    {
        $map = self::messages_map();
        if (isset($map[$key])) return $map[$key];
        return __('Unknown message key', 'awp');
    }

}

class Wawp_connector
{

    private $block_reasons = [];

    public function __construct()
    {
        add_action('admin_init', [$this, 'check_for_token']);
        add_action('wp_ajax_mrsb_auto_check_status', [$this, 'auto_check_status']);
        add_action('rest_api_init', [$this, 'register_push_data_route']);
        add_action('rest_api_init', [$this, 'register_site_features_endpoint_site_b']); 
        add_action('rest_api_init', [$this, 'register_site_info_endpoint']);
        add_action('rest_api_init', [$this, 'register_generate_login_token_endpoint']);
        add_action('init', [$this, 'handle_token_login']); 
        
        add_action('admin_init', [$this, 'handle_remote_support_save']);
        if (!wp_next_scheduled('mrsb_auto_sync_event')) {
            wp_schedule_event(time(), 'hourly', 'mrsb_auto_sync_event');
        }
        add_action('mrsb_auto_sync_event', [$this, 'mrsb_auto_sync_in_background']);
    }

    public function on_activate()
    {
        $token = get_option('mysso_token');
        if ($token) {
            $this->call_wawp_validate($token, $this->get_site_domain(), get_option('admin_email'), 'active');
        }
    }

public function on_deactivate()
{
    $token = get_option('mysso_token');
    $saved = get_option('wawp_last_deactivation_reason', []);
    $reason_text = is_array($saved) && !empty($saved['reason']) ? $saved['reason'] : '';

    if ($token) {
        $this->call_wawp_validate(
            $token,
            $this->get_site_domain(),
            get_option('admin_email'),
            'not active',
            ['mysso_deactivation_reason' => $reason_text]
        );
    }
    delete_option('wawp_last_deactivation_reason');

    wp_clear_scheduled_hook('mrsb_auto_sync_event');
}
    
        public function handle_remote_support_save() {
        if ( isset( $_POST['wawp_remote_support_nonce'] ) && wp_verify_nonce( $_POST['wawp_remote_support_nonce'], 'wawp_save_remote_support' ) ) {
            $new_setting = isset( $_POST['wawp_remote_support_enabled'] ) ? '1' : '0';
            update_option( 'wawp_remote_support_enabled', $new_setting );
            
                    if ( isset($_POST['mysso_shared_key']) ) {
            update_option(
                'mysso_shared_key',
                sanitize_text_field( wp_unslash($_POST['mysso_shared_key']) ),
                'no'
            );
        }
            
        }
    }
    
    

    
    public function render_admin_page()
    {
        $banned_msg = get_transient('siteB_banned_msg');
        $not_logged_in_msg = get_transient('siteB_not_logged_in_msg');
        $user_data = get_transient('siteB_user_data');
        $token = get_option('mysso_token');
        echo '<div class="wrap">';
        $logout_link = add_query_arg(
            [
                'mysso_logout' => '1',
                'awp_section' => 'connector'
            ],
            admin_url('admin.php?page=wawp')
        );
        echo '<div class="disconnect-wrapper">';
        if ($token) {
            echo '<button id="mrsb-refresh-button" class="awp-btn"><i class="ri-refresh-line"></i>' . __('Refresh', 'awp') . '</button>';
            echo '<a href="' . esc_url($logout_link) . '" class="disconnect"><i class="ri-link-unlink"></i>' . __('Disconnect', 'awp') . '</a>';
        }
        echo '</div>';
        echo '<div id="mrsb-status-area">';
        if ($banned_msg) {
            $this->render_banned_message($banned_msg);
        } elseif ($not_logged_in_msg) {
            $this->render_not_logged_in_message_custom($not_logged_in_msg);
        } elseif ($user_data && isset($user_data['user_email'])) {
            $this->render_user_data($user_data);
        } else {
            $this->render_not_logged_in_message();
        }
        echo '</div>';
        echo '</div>';
    }

    private function render_banned_message($banned_msg)
    {
        echo '<div style="width:820px;margin:auto;">';
        echo '<div><h2 style="margin-top:0;color:#cc0000;">' . __("You're not allowed to use Wawp.", 'awp') . '</h2>';
        echo '<p style="max-width:100%;">' . __("We have detected a violation or restriction on your account, which currently prevents you from using Wawp and accessing advanced features. To restore full access, please reach out to our support team.", "awp") . '</p></div>';
        echo '<div style="border:1px solid red;background:#ffe6e6;color:red;padding:10px;margin:10px 0;"><strong>' . esc_html($banned_msg) . '</strong></div>';
        echo '</div>';
    }

    private function render_meta_lines($data)
    {
    }

    private function render_not_logged_in_message()
    {
        echo '<div class="awp-cards">';
        echo '<div class="awp-card">';
        echo '<div class="card-header_row">';
        echo '<div class="card-header">';
        echo '<h2 class="page-title">' . esc_html__('Get Started for Free', 'awp') . '</h2>';
        echo '<p>' . esc_html__('You must be logged in to Wawp to access all features.', 'awp') . '</p>';
        echo '</div>';
        $login_url = add_query_arg(
            [
                'action' => 'siteA_sso',
                'redirect_to' => urlencode(admin_url('admin.php?page=wawp&awp_section=connector'))
            ],
            'https://wawp.net/'
        );
        echo '<a href="' . esc_url($login_url) . '" class="wawp-login-btn primary">'
            . esc_html__('Login to Wawp', 'awp') . '</a>';
        echo '</div>';
        echo '<hr class="h-divider">';
        echo '<ul class="plan-features">';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Order Update notifications', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Admin Order Alerts', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Follow-up Notifications', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Advanced Phone Fields', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('WhatsApp OTP Login/signup', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('Checkout OTP Verification', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('Advanced Message History', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('WhatsApp Chat Widget', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('Country Code Selector', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('and more...', 'awp') . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '<p style="text-align:center; max-width: 100%;">' . Wawp_Global_Messages::get('need_login') . '</p>';
        echo '</div>';
    }

    private function render_not_logged_in_message_custom($not_logged_in_msg)
    {
        echo '<div class="awp-cards">';
        echo '<div class="awp-card">';
        echo '<div class="card-header_row">';
        echo '<div class="card-header">';
        echo '<h2 class="page-title">' . esc_html__('Get Started for Free', 'awp') . '</h2>';
        echo '<p>' . esc_html__('You must be logged in to Wawp to access all features.', 'awp') . '</p>';
        echo '</div>';
        $login_url = add_query_arg(
            [
                'action' => 'siteA_sso',
                'redirect_to' => urlencode(admin_url('admin.php?page=wawp&awp_section=connector'))
            ],
            'https://wawp.net/'
        );
        echo '<a href="' . esc_url($login_url) . '" class="wawp-login-btn primary">'
            . esc_html__('Login to Wawp', 'awp') . '</a>';
        echo '</div>';
        echo '<hr class="h-divider">';
        echo '<ul class="plan-features">';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Order Update notifications', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Admin Order Alerts', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Follow-up Notifications', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . esc_html__('Advanced Phone Fields', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('WhatsApp OTP Login/signup', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('Checkout OTP Verification', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('Advanced Message History', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('WhatsApp Chat Widget', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('Country Code Selector', 'awp') . '</li>';
        echo '<li><i class="ri-check-line"></i>' . __('and more...', 'awp') . '</li>';
        echo '</ul>';
        echo '</div>';
        echo '<p style="text-align:center; max-width: 100%;">' . Wawp_Global_Messages::get('need_login') . '</p>';
        echo '</div>';
    }

    private function render_user_data($data)
    {
        
        $current_domain = $this->get_site_domain();
        $sites = is_array($data['sites']) ? $data['sites'] : [];
        $domain_status = isset($sites[$current_domain]) ? $sites[$current_domain] : '';
        $lifetime = !empty($data['is_lifetime']);


        if ($domain_status !== 'active') {
            $this->render_site_not_active_promo();
            return;
        }

        echo '<div class="page-header_row">';
        echo '<div class="page-header">';
        echo '<h2 class="page-title">' . esc_html__('Subscription', 'awp') . '</h2>';
        echo '<p>' . esc_html__("You can manage your subscription, update payment method and view your invoices right from ", 'awp')
            . '<a href="https://wawp.net/account" target="_blank" >'
            . esc_html__("your account.", 'awp') . '</a></p>';
        echo '</div>';
        echo '</div>';

        $subscriptions = !empty($data['subscriptions']) ? $data['subscriptions'] : [];

        if (!$subscriptions && $lifetime) {
            $this->render_meta_lines($data);
            echo '<div class="awp-card"><div class="card-header_row"><div class="card-header">';
            echo '<h4 class="card-title">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(31,193,107,1)">
 <path d="M2.00488 19H22.0049V21H2.00488V19ZM2.00488 5L7.00488 8L12.0049 2L17.0049 8L22.0049 5V17H2.00488V5Z"></path>
</svg>
 ' . esc_html($data['plan_name'] ?? __('Wawp Lifetime', 'awp')) . '</h4>';
            echo '<p style="width:100%;text-align:start;">' . esc_html__('Great, you now have lifetime access', 'awp') . '</p>';
            echo '</div>';
            echo '<span class="active-plan"><span class="awp-pro-status plan-dot" style="background-color:#008a45;"></span>' . __('Active', 'awp') . '</span></div>';
            echo '</div>';
            return;
        }

        if (!$subscriptions && !$lifetime) {
            echo '<div style="margin:auto;width:820px;text-align:center;">';
            echo '<div class="awp-card flex align-center justify-between">';
            echo '<div class="flex align-center" style="gap:8px;">';
            echo '<i class="ri-information-line"></i>';
            echo '<p style="max-width:fit-content;color:var(--heading);"><b>'
                . __("You're currently on a Free Plan.", 'awp') . '</b> '
                . __("With these limits: 250 messages/mo, 1 WhatsApp number.", 'awp') . '</p>';
            echo '</div>';
            echo '<a href="https://wawp.net/pricing" target="_blank" >' . __('Learn More', 'awp') . '</a>';
            echo '</div>';

            echo '<div class="awp-card" style="border:0;margin:16px 0;background:var(--dark-bg);color:#fff;">';
            echo '<div class="awp-cards" style="align-items:center;">';
            echo '<div>';
            echo '<h2 style="color:#fff !important;">' . __('Get 2000x more usage with Wawp Pro', 'awp') . '</h2>';
            echo '<ul class="plan-features" style="grid-template-rows:repeat(7,1fr);">';
            echo '<li><i class="ri-check-line"></i>' . __('500,000 Messages/month', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Connect 10 WhatsApp numbers', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Unlimited Site Licenses', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Order Update notifications', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Admin Order Alerts', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Follow-up Notifications', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('WhatsApp OTP Login/signup', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Custom Login/Signup Pages', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Checkout OTP Verification', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Advanced Message History', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('WhatsApp Chat Widget', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Country Code Selector', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('24/7 Priority Ticket Support', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('and more...', 'awp') . '</li>';
            echo '</ul>';
            echo '</div>';
            echo '<h1 style="font-size:120px;font-weight:700;color:#fff;line-height:1;margin:0;padding:0;">$1</h1>';
            echo '</div>';
            echo '<a href="https://wawp.net/pricing" target="_blank" class="wawp-login-btn">' . __('Choose Plan', 'awp') . '<i class="ri-arrow-right-line"></i></a>';
            echo '</div>';
            echo '<a href="https://wawp.net/pricing" target="_blank" >' . __('Learn about Wawp Pro', 'awp') . '</a>';
            return;
        }

        $activeSubscriptions = [];
        $otherSubscriptions = [];
        foreach ($subscriptions as $sub) {
            if (!empty($sub['status']) && $sub['status'] === 'active') {
                $activeSubscriptions[] = $sub;
            } else {
                $otherSubscriptions[] = $sub;
            }
        }

        if ($lifetime) {
            $this->render_meta_lines($data);
            echo '<div class="awp-card"><div class="card-header_row"><div class="card-header">';
            echo '<h4 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(31,193,107,1)">
            <path d="M2.00488 19H22.0049V21H2.00488V19ZM2.00488 5L7.00488 8L12.0049 2L17.0049 8L22.0049 5V17H2.00488V5Z"></path>
            </svg>
            ' . esc_html__('Wawp Pro Lifetime', 'awp') . '</h4>';
            echo '<p style="width:100%;text-align:start;">' . esc_html__('Great, you now have lifetime access', 'awp') . '</p>';
            echo '</div>';
            echo '<span class="active-plan"><span class="awp-pro-status plan-dot" style="background-color:#008a45;"></span>' . __('Active', 'awp') . '</span></div>';
            echo '</div>';
            return;
        } elseif (!empty($activeSubscriptions)) {
            $sub = reset($activeSubscriptions);
            $next = !empty($sub['next']) ? date('d M, Y', strtotime($sub['next'])) : '—';
            $this->render_meta_lines($data);
            echo '<div class="awp-card"><div class="card-header_row"><div class="card-header">';
            echo '<h4 class="card-title">
 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(31,193,107,1)">
 <path d="M2.00488 19H22.0049V21H2.00488V19ZM2.00488 5L7.00488 8L12.0049 2L17.0049 8L22.0049 5V17H2.00488V5Z"></path>
 </svg>
 ' . esc_html($data['plan_name'] ?? __('Wawp Subscription', 'awp')) . '</h4>';
            echo '<p style="width:100%;text-align:start;">' . __('Your next bill is due on ', 'awp') . '<b>' . esc_html($next) . '</b></p>';
            echo '</div>';
            echo '<span class="active-plan"><span class="awp-pro-status plan-dot" style="background-color:#008a45;"></span>' . __('Active', 'awp') . '</span></div>';
            echo '</div>';
        }

        if (!empty($otherSubscriptions)) {
            $sub = reset($otherSubscriptions);
            $status = esc_html($sub['status'] ?? '—');
            echo '<div>';
            echo '<div class="awp-card">';
            echo '<div>';
            echo '<i class="ri-information-line"></i>';
            echo '<p>'
                . __("Your subscription is ", 'awp')
                . '<b>' . $status . '</b></p>';
            echo '</div>';
            echo '<a href="https://wawp.net/account/subscription" target="_blank" >Manage Subscription</a>';
            echo '</div>';

            echo '<div class="awp-card" style="border:0;margin:16px 0;background:var(--dark-bg);color:#fff;">';
            echo '<div class="awp-cards" style="align-items:center;">';
            echo '<div>';
            echo '<h2 style="color:#fff !important;">' . __('Get 2000x more usage with Wawp Pro', 'awp') . '</h2>';
            echo '<ul class="plan-features" style="grid-template-rows:repeat(7,1fr);">';
            echo '<li><i class="ri-check-line"></i>' . __('500,000 Messages/month', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Connect 10 WhatsApp numbers', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Unlimited Site Licenses', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Order Update notifications', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Admin Order Alerts', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Follow-up Notifications', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('WhatsApp OTP Login/signup', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Custom Login/Signup Pages', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Checkout OTP Verification', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Advanced Message History', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('WhatsApp Chat Widget', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('Country Code Selector', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('24/7 Priority Ticket Support', 'awp') . '</li>';
            echo '<li><i class="ri-check-line"></i>' . __('and more...', 'awp') . '</li>';
            echo '</ul>';
            echo '</div>';
            echo '<h1 style="font-size:120px;font-weight:700;color:#fff;line-height:1;margin:0;padding:0;">$1</h1>';
            echo '</div>';
            echo '<a href="https://wawp.net/pricing" target="_blank" class="wawp-login-btn">' . __('Choose Plan', 'awp') . '<i class="ri-arrow-right-line"></i></a>';
            echo '</div>';
            echo '<a href="https://wawp.net/pricing" target="_blank" >' . __('Learn about Wawp Pro', 'awp') . '</a>';
        }

        $overloaded_domains = [];
        foreach ($sites as $domain => $st) {
            if ($st === 'overload') {
                $overloaded_domains[] = $domain;
            }
        }
        if ($overloaded_domains) {
            echo '<div style="border:1px solid red;background:#ffe6e6;color:red;padding:10px;margin:10px 0;">';
            echo '<strong>' . Wawp_Global_Messages::get('overloaded_limit_Instances') . implode(', ', $overloaded_domains) . '</strong>';
            echo '</div>';
        }

    }

    public function check_for_token()
    {
        if (!empty($_GET['token'])) {
            $token = sanitize_text_field($_GET['token']);
            update_option('mysso_token', $token, 'no');
            $this->call_wawp_validate($token, $this->get_site_domain(), get_option('admin_email'), 'active');
            if (!empty($_GET['redirect_to'])) {
                $redirect_url = esc_url_raw($_GET['redirect_to']);
            } else {
                $redirect_url = remove_query_arg(['token', 'redirect_to'], wp_unslash($_SERVER['REQUEST_URI']));
            }
            wp_safe_redirect($redirect_url);
            exit;
        }
        if (isset($_GET['mysso_logout']) && $_GET['mysso_logout'] === '1') {
            $token = get_option('mysso_token');
            if ($token) {
                $this->call_wawp_validate($token, $this->get_site_domain(), get_option('admin_email'), 'not active');
                delete_option('mysso_token');
                delete_transient('siteB_banned_msg');
                delete_transient('siteB_user_data');
                delete_transient('siteB_not_logged_in_msg');
            }
            if (!empty($_GET['redirect_to'])) {
                $redirect_url = esc_url_raw($_GET['redirect_to']);
            } else {
                $redirect_url = remove_query_arg(['mysso_logout', 'redirect_to'], wp_unslash($_SERVER['REQUEST_URI']));
            }
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    
    private function get_local_features_status_payload() {
    $arr = $this->get_local_features_status_summary_array(); 
    $map = [
        'Multilang'      => 'multilang_enabled',
        'Seg Notifs'     => 'segnotifs_enabled',
        'Sub Notifs'     => 'subnotifs_enabled',
        'Abandoned Cart' => 'abandonedcart_enabled',
        'Email Smtp'     => 'emailsmtp_enabled',
        'Meta Sender'    => 'metasender_enabled',
        'Wawp Sender'    => 'wawpsender_enabled',
        'Countrycode'    => 'countrycode_enabled',
        'Chat Button'    => 'chatbutton_enabled',
        'Email Log'      => 'emaillog_enabled',
        'Wawp Log'       => 'wawplog_enabled',
        'Meta Log'       => 'metalog_enabled',
        'Block Manager'  => 'blockmanager_enabled',
        'Notifications'  => 'notifications_enabled',
        'Login Otp'      => 'loginotp_enabled',
        'Checkout Otp'   => 'checkoutotp_enabled',
        'Signup Otp'     => 'signupotp_enabled',
    ];
    $out = [];
    foreach ($map as $label => $key) {
        $status = isset($arr[$label]) ? strtolower($arr[$label]) : 'disabled';
        $out[$key] = ($status === 'enabled') ? 'enabled' : 'disabled';
    }
    return $out;
}

private function sync_local_options($user_data) {
    $options_map = [
        'multilang_enabled' => 'awp_multilang_enabled',
        'segnotifs_enabled' => 'awp_campaigns_enabled',
        'subnotifs_enabled' => 'awp_sub_notifs_enabled',
        'abandonedcart_enabled' => 'awp_abandoned_carts_enabled',
        'countrycode_enabled' => 'awp_countrycode_enabled',
        'chatbutton_enabled' => 'awp_chat_widget_enabled',
        'notifications_enabled' => 'awp_notifications_enabled',
        'loginotp_enabled' => 'awp_otp_login_enabled',
        'signupotp_enabled' => 'awp_signup_enabled',
        'checkoutotp_enabled' => 'awp_checkout_otp_enabled',
        'custom_pages_enabled' => 'awp_custom_pages_enabled',
        'system_info_enabled' => 'awp_system_info_enabled',
        'emailsmtp_enabled'    => 'awp_senders_enabled', 
        'wawpsender_enabled'   => 'awp_senders_enabled',
        'metasender_enabled'   => 'awp_senders_enabled',
        'blockmanager_enabled' => 'awp_senders_enabled',
    ];

    $senders_opts = get_option('awp_senders_enabled', []);
    $senders_option_exists = get_option('awp_senders_enabled') !== false;

    foreach ($options_map as $remote_key => $local_option) {
        $remote_status = $user_data[$remote_key] ?? 'disabled';

        if ($local_option !== 'awp_senders_enabled') {
            $current_local_value = get_option($local_option, null);
            $is_user_set = $current_local_value !== null;
            
            if ($remote_status === 'enabled') {
                if (!$is_user_set) {
                    update_option($local_option, 1);
                }
            } else {
                if ($current_local_value !== 0) {
                    update_option($local_option, 0);
                }
            }
        } else {
            $key_map = [
                'emailsmtp_enabled'    => 'email',
                'wawpsender_enabled'   => 'wa',
                'metasender_enabled'   => 'meta',
                'blockmanager_enabled' => 'block',
            ];
            $sender_key = $key_map[$remote_key];
            $is_user_set = isset($senders_opts[$sender_key]);
            
            if ($remote_status === 'enabled') {
                if (!$is_user_set) {
                    $senders_opts[$sender_key] = 1;
                }
            } else {
                if ($is_user_set && $senders_opts[$sender_key] !== 0) {
                    $senders_opts[$sender_key] = 0;
                }
            }
        }
    }
        update_option('awp_senders_enabled', $senders_opts);
    $this->wawp_store_allowed_numbers_limit_locally($user_data);
}


private function call_wawp_validate($token, $siteDomain, $adminEmail, $status, $extra = [])
{
    $body = [
        'token'                   => $token,
        'mysso_site_domain'       => $siteDomain,
        'mysso_site_status'       => $status,
        'mysso_site_admin_email'  => $adminEmail,
        'mysso_wp_version'        => get_bloginfo('version'),
        'mysso_instance_count'    => $this->get_local_instance_count(),
        'mysso_instance_ids'      => $this->get_local_instance_ids(),
        'mysso_wawp_version'      => defined('AWP_PLUGIN_VERSION') ? AWP_PLUGIN_VERSION : '',
        'mysso_remote_support_status'=> get_option('wawp_remote_support_enabled') ? 'enabled' : 'disabled',
        'mysso_features_summary'  => $this->get_local_features_status_summary_text(),
        'mysso_features'          => $this->get_local_features_status_payload(),
        'site_info'               => $this->get_site_info_payload(),
        'mysso_site_info'         => $this->get_site_info_payload(),
    ];
    if (is_array($extra) && $extra) {
        $body = array_merge($body, $extra);
    }

    $response = wp_remote_post('https://wawp.net/wp-json/my-sso/v1/validate', [
        'body' => $body
    ]);

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($code === 403) {
                $json_data = json_decode($body, true);

                if (isset($json_data['access_token']) && $json_data['access_token']) {
                    update_option('wawp_access_token', sanitize_text_field($json_data['access_token']), 'no');
                }

                if (json_last_error() === JSON_ERROR_NONE && !empty($json_data['ban_reason'])) {
                    $ban_message = Wawp_Global_Messages::get('blocked_prefix') . esc_html($json_data['ban_reason']);
                } else {
                    $ban_message = Wawp_Global_Messages::get('blocked_misuse');
                }
                set_transient('siteB_banned_msg', $ban_message, 3600);
                $this->store_block_reason('Received 403 from Wawp: ' . $body);
                delete_transient('siteB_not_logged_in_msg');
                delete_transient('siteB_user_data');
                return;
            }
            if ($code === 401) {
                $not_logged_in_message = Wawp_Global_Messages::get('not_logged_in');
                set_transient('siteB_not_logged_in_msg', $not_logged_in_message, 3600);
                delete_transient('siteB_banned_msg');
                delete_transient('siteB_user_data');
                return;
            }
            $data = json_decode($body, true);
            if (isset($data['user_id'])) {
                set_transient('siteB_user_data', $data, 3600);
                $this->sync_local_options($data); 
                delete_transient('siteB_not_logged_in_msg');
            }
        }
    }

    public function auto_check_status()
    {
        check_ajax_referer('mrsb_auto_check_nonce', 'security');
        $token = get_option('mysso_token');
        $old_banned = get_transient('siteB_banned_msg');
        $old_not_logged_in = get_transient('siteB_not_logged_in_msg');
        $old_data = get_transient('siteB_user_data');

        if ($token) {
            delete_transient('siteB_banned_msg');
            delete_transient('siteB_not_logged_in_msg');
            $this->call_wawp_validate($token, $this->get_site_domain(), get_option('admin_email'), 'active');
        }
        $new_banned = get_transient('siteB_banned_msg');
        $new_not_logged_in = get_transient('siteB_not_logged_in_msg');
        $new_data = get_transient('siteB_user_data');
        if (!$new_banned && !$new_not_logged_in && !$new_data) {
            $new_banned = $old_banned;
            $new_not_logged_in = $old_not_logged_in;
            $new_data = $old_data;
        }
        ob_start();
        wp_send_json_success(['html' => $this->get_current_status_html()]);
    }

    private function get_current_status_html()
    {
        ob_start();
        $banned_msg = get_transient('siteB_banned_msg');
        $not_logged_in_msg = get_transient('siteB_not_logged_in_msg');
        $user_data = get_transient('siteB_user_data');
        if ($banned_msg) {
            $this->render_banned_message($banned_msg);
        } elseif ($not_logged_in_msg) {
            $this->render_not_logged_in_message_custom($not_logged_in_msg);
        } elseif ($user_data && isset($user_data['user_email'])) {
            $this->render_user_data($user_data);
        } else {
            $this->render_not_logged_in_message();
        }
        return ob_get_clean();
    }

    private function get_site_domain()
    {
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        return $domain ? $domain : get_site_url();
    }

    private function get_local_instance_count()
    {
        return count($this->get_local_instance_ids());
    }

    private function get_local_instance_ids()
    {
        global $wpdb;
        $table_name = "{$wpdb->prefix}awp_instance_data";
        $rows = $wpdb->get_results("SELECT instance_id FROM $table_name WHERE status='online'");
        if (!$rows) return [];
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row->instance_id;
        }
        return array_unique($ids);
    }

    public function register_push_data_route()
    {
        register_rest_route('my-remote-subs/v1', '/push-data', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_push_data'],
            'permission_callback' => function ($req) {
                $api_key = $req->get_header('x-api-key') ?: $req->get_param('api_key');
                return ($api_key === 'some-secret-string');
            }
        ]);
    }

    public function register_site_features_endpoint_site_b() {
    register_rest_route('my-sso/v1', '/site-features', [
     'methods'  => 'GET',
     'callback' => [$this, 'handle_get_site_features_site_b'],
     'permission_callback' => function($req) {
      $api_key = $req->get_header('x-api-key') ?: $req->get_param('api_key');
      if ($api_key === get_option('mysso_shared_key')) return true;
      $token = $req->get_param('token');
      return $token && hash_equals(get_option('mysso_token'), $token);
    },
    ]);
        }


    public function handle_get_site_features_site_b( $request ) {
        $payload = $this->get_local_features_status_payload(); 
        $payload['edit_done'] = 'true';
        return new WP_REST_Response($payload, 200);
    }



    public function handle_push_data($req)
    {
        $token = $req->get_param('mysso_token');
        $ban = $req->get_param('banned_msg') ?: '';
        $ud = $req->get_param('user_data') ?: [];
        if ($ban) {
            set_transient('siteB_banned_msg', $ban, 3600);
            delete_transient('siteB_not_logged_in_msg');
        } else {
            delete_transient('siteB_banned_msg');
        }
        if (!empty($ud) && is_array($ud)) {
            set_transient('siteB_user_data', $ud, 3600);
            $this->sync_local_options($ud);
            delete_transient('siteB_not_logged_in_msg');
        } else {
            delete_transient('siteB_user_data');
        }
        if ($token) {
            update_option('mysso_token', $token, 'no');
        }
        return ['ok' => true, 'msg' => __('Data pushed successfully', 'awp')];
    }

    public function mrsb_auto_sync_in_background()
    {
        $token = get_option('mysso_token');
        if (!$token) return;
                $response = wp_remote_post('https://wawp.net/wp-json/my-sso/v1/validate', [
            'timeout' => 15,
            'body' => [
                'token' => $token,
                'mysso_site_domain' => $this->get_site_domain(),
                'mysso_site_status' => 'active',
                'mysso_site_admin_email' => get_option('admin_email'),
                'mysso_wp_version' => get_bloginfo('version'),
                
                'mysso_instance_count' => $this->get_local_instance_count(),
                'mysso_instance_ids' => $this->get_local_instance_ids(),
                'mysso_wawp_version' => defined('AWP_PLUGIN_VERSION') ? AWP_PLUGIN_VERSION : '',
                'mysso_remote_support_status' => get_option('wawp_remote_support_enabled') ? 'enabled' : 'disabled',
                'mysso_features_summary' => $this->get_local_features_status_summary_text(),
                'mysso_features'           => $this->get_local_features_status_payload(), 
                
            ]
        ]);
        if (is_wp_error($response)) return;
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code === 403) {
            $json_data = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($json_data['ban_reason'])) {
                $ban_message = Wawp_Global_Messages::get('blocked_prefix') . esc_html($json_data['ban_reason']);
            } else {
                $ban_message = Wawp_Global_Messages::get('blocked_generic');
            }
            set_transient('siteB_banned_msg', $ban_message, 3600);
            $this->store_block_reason('Received 403 from Wawp during auto sync: ' . $body);
            delete_transient('siteB_not_logged_in_msg');
            delete_transient('siteB_user_data');
            return;
        }
        if ($code === 401) {
            $not_logged_in_message = Wawp_Global_Messages::get('not_logged_in');
            set_transient('siteB_not_logged_in_msg', $not_logged_in_message, 3600);
            delete_transient('siteB_banned_msg');
            delete_transient('siteB_user_data');
            return;
        }
        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['user_email'])) {
            set_transient('siteB_user_data', $data, 3600);
            $this->sync_local_options($data);
            delete_transient('siteB_not_logged_in_msg');
        }
    }

    private function render_site_not_active_promo()
    {
        echo '<div style="border:2px dashed #ffa500;background:#fffdee;padding:15px;margin-bottom:10px;border-radius:5px;">';
        echo '<h2 style="margin-top:0;color:#cc0000;font-size:1.2em;">' . __('Site Not Active Promotion', 'awp') . '</h2>';
        echo '<p>' . __('This site is inactive or you have no active subscription. Check our next-level subscription to activate more sites:', 'awp') . '</p>';
        echo '<ul style="list-style:disc;margin-left:20px;">';
        echo '<li>' . __('Activate multiple domains', 'awp') . '</li>';
        echo '<li>' . __('Premium site analytics', 'awp') . '</li>';
        echo '</ul>';
        echo '<p style="margin-bottom:0;">' . __('Upgrade now to activate!', 'awp') . '</p>';
        echo '</div>';
        echo '<div style="border:1px solid red;background:#ffe6e6;color:red;padding:10px;margin:10px 0;">';
        echo '<strong>' . Wawp_Global_Messages::get('not_active_site') . '</strong> ';
        echo '<a href="https://docs.example.com" class="button" style="margin-left:10px;">' . __('Learn More', 'awp') . '</a>';
        echo '</div>';
    }

    private function store_block_reason($reason)
    {
        $this->block_reasons = get_option('siteB_block_reasons', []);
        $this->block_reasons[] = [
            'reason' => $reason,
            'time' => current_time('mysql')
        ];
        update_option('siteB_block_reasons', $this->block_reasons, 'no');
    }
    
    
    
    private function wawp_store_allowed_numbers_limit_locally(array $user_data){
        $is_pro = !empty($user_data['is_lifetime']);
        if (!$is_pro && !empty($user_data['subscriptions']) && is_array($user_data['subscriptions'])) {
            foreach ($user_data['subscriptions'] as $sub) {
                if (!empty($sub['status']) && strtolower($sub['status']) === 'active') {
                    $is_pro = true; break;
                }
            }
        }
        $tier  = $is_pro ? 'pro' : 'free';
        $limit = $is_pro ? 10 : 1;
    
        update_option('awp_plan_tier', $tier, 'no');
        update_option('awp_allowed_numbers_limit', $limit, 'no');
    }
    private function get_local_features_status_summary_array()
    {
        $senders_opts = get_option('awp_senders_enabled', ['email' => 1, 'wa' => 1, 'meta' => 1, 'block' => 1]);
        $user_data = get_transient('siteB_user_data');

        return [
            'Auto Inst.' => 'N/A (User-specific)',
            'External QR' => 'N/A (User-specific)',
            'Multilang' => (!empty($user_data['multilang_enabled']) && $user_data['multilang_enabled'] === 'enabled') ? 'Enabled' : 'Disabled',
            'Seg Notifs' => (!empty($user_data['segnotifs_enabled']) && $user_data['segnotifs_enabled'] === 'enabled') ? 'Enabled' : 'Disabled',
            'Sub Notifs' => (!empty($user_data['subnotifs_enabled']) && $user_data['subnotifs_enabled'] === 'enabled') ? 'Enabled' : 'Disabled',
            'Abandoned Cart' => get_option('awp_abandoned_carts_enabled', 1) ? 'Enabled' : 'Disabled',
            'Email Smtp' => !empty($senders_opts['email']) ? 'Enabled' : 'Disabled',
            'Meta Sender' => !empty($senders_opts['meta']) ? 'Enabled' : 'Disabled',
            'Wawp Sender' => !empty($senders_opts['wa']) ? 'Enabled' : 'Disabled',
            'Countrycode' => get_option('awp_countrycode_enabled', 1) ? 'Enabled' : 'Disabled',
            'Chat Button' => get_option('awp_chat_widget_enabled', 1) ? 'Enabled' : 'Disabled',
            'Email Log' => !empty($senders_opts['email']) ? 'Enabled' : 'Disabled',
            'Wawp Log' => !empty($senders_opts['wa']) ? 'Enabled' : 'Disabled',
            'Meta Log' => !empty($senders_opts['meta']) ? 'Enabled' : 'Disabled',
            'Block Manager' => !empty($senders_opts['block']) ? 'Enabled' : 'Disabled',
            'Notifications' => get_option('awp_notifications_enabled', 1) ? 'Enabled' : 'Disabled',
            'Login Otp' => get_option('awp_wawp_otp_enabled', 1) && get_option('awp_otp_login_enabled', 1) ? 'Enabled' : 'Disabled',
            'Checkout Otp' => get_option('awp_wawp_otp_enabled', 1) && get_option('awp_checkout_otp_enabled', 1) ? 'Enabled' : 'Disabled',
            'Signup Otp' => get_option('awp_wawp_otp_enabled', 1) && get_option('awp_signup_enabled', 1) ? 'Enabled' : 'Disabled',
        ];
    }
    
    public function register_generate_login_token_endpoint() {
        register_rest_route('my-sso/v1', '/generate-login-token', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_login_token'],
            'permission_callback' => function ($req) {
                $api_key = $req->get_header('x-api-key');
                $shared_key = get_option('mysso_shared_key'); 
                return !empty($shared_key) && hash_equals($shared_key, $api_key);
            }
        ]);
    }

    public function handle_generate_login_token($request) {

        $admins = get_users(['role' => 'administrator', 'number' => 1]);
        if (empty($admins)) {
            return new WP_Error('no_admin', 'No administrator account found.', ['status' => 500]);
        }
        $admin_user = $admins[0];
        
        $token = wp_generate_password(40, false);
        $expiration = time() + 60;

        update_user_meta($admin_user->ID, '_wawp_remote_login_token', $token);
        update_user_meta($admin_user->ID, '_wawp_remote_login_token_expires', $expiration);

        return new WP_REST_Response(['token' => $token], 200);
    }

    public function handle_token_login() {
        if (isset($_GET['wawp_remote_login_token'])) {
            $token = sanitize_text_field($_GET['wawp_remote_login_token']);
            
            $users = get_users([
                'meta_key' => '_wawp_remote_login_token',
                'meta_value' => $token,
                'number' => 1,
            ]);

            if (!empty($users)) {
                $user = $users[0];
                $expires = get_user_meta($user->ID, '_wawp_remote_login_token_expires', true);
                delete_user_meta($user->ID, '_wawp_remote_login_token');
                delete_user_meta($user->ID, '_wawp_remote_login_token_expires');

                if ($expires && time() < $expires) {
                    wp_set_current_user($user->ID, $user->user_login);
                    wp_set_auth_cookie($user->ID);
                    wp_safe_redirect(admin_url());
                    exit;
                }
            }
            wp_safe_redirect(wp_login_url());
            exit;
        }
    }
    
    
    public function register_site_info_endpoint() {
    register_rest_route('my-sso/v1', '/site-info', [
        'methods'  => 'GET',
        'callback' => [$this, 'handle_get_site_info'],
        'permission_callback' => function($req) {
            $api_key = $req->get_header('x-api-key') ?: $req->get_param('api_key');
            if ($api_key && hash_equals(get_option('mysso_shared_key'), $api_key)) {
                return true;
            }
            $token = $req->get_param('token');
            return $token && hash_equals(get_option('mysso_token'), $token);
        }
    ]);
}

public function handle_get_site_info( $request ) {
    $theme = wp_get_theme();
    $parent = $theme->parent();
    $locale        = get_locale();     
    $language_tag = get_bloginfo('language');  
    $is_multisite = is_multisite();
    $plugin_counts = $this->get_plugin_counts();
    $ml = $this->detect_multilang();

    $payload = [
        'site_url'  => get_site_url(),
        'home_url'  => home_url(),
        'wp_version'=> get_bloginfo('version'),
        'is_multisite' => (bool) $is_multisite,

        'theme' => [
            'name'         => $theme->get('Name'),
            'version'      => $theme->get('Version'),
            'stylesheet'   => $theme->get_stylesheet(),
            'template'     => $theme->get_template(),
            'is_child'     => (bool) $parent,
            'parent'       => $parent ? $parent->get('Name') : null,
        ],

        'language' => [
            'locale'       => $locale,        
            'language_tag' => $language_tag,  
        ],

        'plugins' => $plugin_counts,       

        'multilang' => [
            'active' => (bool) $ml['active'],
            'plugin' => $ml['plugin'],      
        ],
    ];

    return new WP_REST_Response($payload, 200);
}

    private function get_plugin_counts() {
    if ( ! function_exists('get_plugins') ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins(); 
    $installed   = is_array($all_plugins) ? count($all_plugins) : 0;

    $active_site = get_option('active_plugins', []);
    $active_site_count = is_array($active_site) ? count($active_site) : 0;

    $network_active_count = 0;
    if ( is_multisite() ) {
        $network_active = (array) get_site_option('active_sitewide_plugins', []);
        $network_active_count = count($network_active);
    }

    return [
        'installed'       => (int) $installed,
        'active'          => (int) $active_site_count,
        'network_active'  => (int) $network_active_count,
        'active_total'    => (int) ($active_site_count + $network_active_count),
    ];
}

private function detect_multilang() {
    if ( ! function_exists('is_plugin_active') ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $checks = [
        'sitepress-multilingual-cms/sitepress.php' => 'WPML',
        'polylang/polylang.php'                    => 'Polylang',
        'translatepress-multilingual/index.php'    => 'TranslatePress',
        'weglot/weglot.php'                        => 'Weglot',
        'multilingualpress/multilingualpress.php'  => 'MultilingualPress',
        'qtranslate-x/qtranslate.php'              => 'qTranslate X',
    ];

    foreach ($checks as $file => $name) {
        $active = is_plugin_active($file)
            || (is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($file));

        if ($active) {
            return ['active' => true, 'plugin' => $name];
        }
    }

    if ( defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') ) {
        return ['active' => true, 'plugin' => 'WPML'];
    }
    if ( function_exists('pll_the_languages') || defined('POLYLANG_VERSION') ) {
        return ['active' => true, 'plugin' => 'Polylang'];
    }
    if ( defined('TRP_PLUGIN_VERSION') || function_exists('trp_get_languages') ) {
        return ['active' => true, 'plugin' => 'TranslatePress'];
    }
    if ( defined('WEGLOT_VERSION') ) {
        return ['active' => true, 'plugin' => 'Weglot'];
    }

    return ['active' => false, 'plugin' => null];
}

    private function get_site_info_payload() {
    if ( ! function_exists('get_plugins') ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $theme    = wp_get_theme();
    $is_child = is_child_theme();
    $parent   = $is_child ? $theme->parent() : null;

    $installed = 0;
    $active    = 0;
    $all_plugins = function_exists('get_plugins') ? get_plugins() : [];
    $installed  = is_array($all_plugins) ? count($all_plugins) : 0;

    $active_plugins = (array) get_option('active_plugins', []);
    if ( is_multisite() ) {
        $network = (array) get_site_option('active_sitewide_plugins', []);
        $active_plugins = array_unique( array_merge( $active_plugins, array_keys( $network ) ) );
    }
    $active = count($active_plugins);

    $multilang = ['active' => false, 'plugin' => null];
    $candidates = [
        'sitepress-multilingual-cms/sitepress.php' => 'WPML',
        'polylang/polylang.php'                    => 'Polylang',
        'translatepress-multilingual/index.php'    => 'TranslatePress',
    ];
    foreach ($candidates as $slug => $name) {
        if ( is_plugin_active($slug) ) {
            $multilang = ['active' => true, 'plugin' => $name];
            break;
        }
    }

    return [
        'site_url'     => site_url(),
        'home_url'     => home_url(),
        'is_multisite' => is_multisite(),
        'wp_version'   => get_bloginfo('version'),
        'theme' => [
            'name'     => $theme->get('Name'),
            'version'  => $theme->get('Version'),
            'is_child' => $is_child,
            'parent'   => $parent ? $parent->get('Name') : '',
        ],
        'plugins' => [
            'installed'     => $installed,
            'active_total'  => $active,
        ],
        'language' => [
            'locale'       => get_locale(),
            'language_tag' => get_locale(),
        ],
        'multilang' => $multilang,
    ];
}

    private function get_local_features_status_summary_text()
    {
        $summary_array = $this->get_local_features_status_summary_array();
        $summary_lines = [];
        foreach ($summary_array as $feature => $status) {
            $summary_lines[] = "$feature: $status";
        }
        $summary_lines[] = "edit done: true";
        return implode('; ', $summary_lines);
    }

}

new Wawp_connector();
