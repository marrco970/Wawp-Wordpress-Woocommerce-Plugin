<?php

if (!defined('ABSPATH')) {
    exit;
}

define('WAWP_NOTIF_SLUG', 'awp');
define('WAWP_NOTIF_OPTION_SETTINGS', 'wawp_notif_settings');
define('WAWP_NOTIF_RULES_TABLE_NAME', 'awp_notif_notification_rules');
define('WAWP_NOTIF_CRON_HOOK', 'wawp_notif_send_scheduled_notification_action');

class Wawp_df_Notifications {

    private $awp_db_manager;
    private $awp_log_manager;

    public function __construct() {
        if (class_exists('AWP_Database_Manager')) {
            $this->awp_db_manager = new AWP_Database_Manager();
        }
        if (class_exists('AWP_Log_Manager')) {
            $this->awp_log_manager = new AWP_Log_Manager();
        }

        add_action(WAWP_NOTIF_CRON_HOOK, [$this, 'process_scheduled_notification'], 10, 1);
        add_action('wp_ajax_wawp_search_products', [$this, 'ajax_search_products']);
        add_action('wp_ajax_wawp_search_users', [$this, 'ajax_search_users']);
        add_action('wp_login', [$this, 'handle_user_login_trigger'], 20, 2);
        add_action('user_register', [$this, 'handle_user_signup_trigger'], 20, 1);
        
        add_action('woocommerce_order_action_send_order_details', [$this, 'handle_wc_manual_status_notify'], 10, 1);
        add_action('woocommerce_order_action_resend_new_order_notification', [$this, 'handle_wc_manual_status_notify'], 10, 1);
        
        add_action('plugins_loaded', function () {
            if (class_exists('WooCommerce')) {
                add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 20, 4);
                add_action('woocommerce_new_customer_note', [$this, 'handle_new_customer_note_trigger'], 20, 1);
                if (class_exists('WC_Subscriptions') && $this->is_subscription_notifs_active()) {
                add_action('woocommerce_subscription_status_updated', [$this, 'handle_subscription_status_updated'], 20, 3);
                add_action('woocommerce_subscription_renewal_payment_complete', [$this, 'handle_subscription_renewal_complete'], 20, 2);
            }
            }
        }, 99);
    }
    
    
    public function handle_wc_manual_status_notify( $order ) {
        // $order can be WC_Order or an ID, handle both
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order );
            if ( ! $order ) {
                return;
            }
        }
    
        $order_id   = $order->get_id();
        $status     = $order->get_status(); // e.g., 'processing', 'completed', etc.
    
        // Reuse the same code path as a real status change.
        // Old == New here; your handler will pick the rule for the current status.
        $this->handle_order_status_change( $order_id, $status, $status, $order );
    }

    public function render_settings_page() {
    if (!$this->is_sso_logged_in()) {
        ?>
        <div class="wawp-notif-settings-page">
            <div class="page-header_row">
                <div class="page-header">
                    <h2 class="page-title"><?php esc_html_e('Notifications Bulider', 'awp'); ?></h2>
                </div>
            </div>
            <div class="notice notice-warning" style="padding: 1rem;">
                <p><strong><?php esc_html_e('You must Login to use Notifications Builder.', 'awp'); ?></strong></p>
            </div>
        </div>
        <?php
        return; 
    }
            $banned_msg  = get_transient('siteB_banned_msg');
        $token       = get_option('mysso_token');
        $user_data   = get_transient('siteB_user_data');
        if ($banned_msg) {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('Whatsapp Activity Logs', 'awp') . '</h1><p style="color:red;">' . esc_html(Wawp_Global_Messages::get('blocked_generic')) . '</p></div>';
            return;
        }
        if (!$token) {
            echo '<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('Whatsapp Activity Logs', 'awp') . '</h1><p>' . esc_html(Wawp_Global_Messages::get('need_login')) . '</p></div>';
            return;
        }
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('Whatsapp Activity Logs', 'awp') . '</h1><p style="color:red;">' . esc_html(Wawp_Global_Messages::get('not_active_site')) . '</p></div>';
            return;
        }
    if ( ! AWP_Admin_Notices::require_online_instance( null) ) {
        return;                   
    }
    global $wpdb;
    $configured_languages_for_js = [];
    foreach ($this->get_settings()['configured_languages'] as $code => $data) {
        $configured_languages_for_js[$code] = $data['name'];
    }
    ?>
    <script type="text/javascript">
        var wawpNotifData = window.wawpNotifData || {};
        wawpNotifData.iconBaseUrl = '<?php echo esc_url(AWP_PLUGIN_URL . 'assets/icons/'); ?>';
        wawpNotifData.ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        wawpNotifData.nonceSearchUsers    = '<?php echo esc_js(wp_create_nonce('wawp_search_users_nonce')); ?>';
        wawpNotifData.nonceSearchProducts = '<?php echo esc_js(wp_create_nonce('wawp_search_products_nonce')); ?>';
        wawpNotifData.textWhen = '<?php echo esc_js(__('When', WAWP_NOTIF_SLUG)); ?>';
        wawpNotifData.textRule = '<?php echo esc_js(__('Rule', WAWP_NOTIF_SLUG)); ?>';
    </script>
    <?php
    $icon_base = AWP_PLUGIN_URL . 'assets/icons/';

    $wa = 'whatsapp.svg';
    $em = 'email.svg';
    $settings = $this->get_settings();
    $single_lang = ( count( $settings['configured_languages'] ) === 1 ); 
    if (
        isset($_POST['wawp_notif_save_settings_nonce']) &&
        wp_verify_nonce($_POST['wawp_notif_save_settings_nonce'], 'wawp_notif_save_settings_action')
    ) {
        $this->process_form_submission($settings);
        $settings = $this->get_settings();
    }

    $inst = $this->get_random_online_instance();
    $instance_table = $wpdb->prefix . 'awp_instance_data'; 
    $online_instances = $wpdb->get_results(
        "SELECT id, name, instance_id FROM {$instance_table} WHERE status = 'online'"
    );

    if (!function_exists('wp_get_available_translations')) {
        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
    }
    $available_translations = wp_get_available_translations();
    $available_triggers = $this->get_available_triggers();
    $all_wc_countries = [];
    if (class_exists('WC_Countries')) {
        $wc_countries = new WC_Countries();
        $all_wc_countries = $wc_countries->get_allowed_countries();
    }
    $all_gateways = [];
    if (class_exists('WC_Payment_Gateways')) {
        $gateway_objects = WC()->payment_gateways()->payment_gateways();
        foreach ($gateway_objects as $gateway) {
            $all_gateways[$gateway->id] = $gateway->get_title();
        }
    }
    ?>
    <div class="wawp-notif-settings-page wrap">

        <div class="page-header_row">
            <div class="page-header">
                <h2 class="page-title"><?php esc_html_e('Notifications Bulider', 'awp'); ?></h2>
                <p><?php esc_html_e('Unlimited notification options.', 'awp'); ?>
                    <a href="https://wawp.net/get-started" target="_blank"><?php esc_html_e('Learn more', 'awp'); ?></a>
                </p>
            </div>
        </div>
        <form id="wawp-notif-settings-form" method="POST" action="">
        <div class="wawp-notif-global-flex">
            <?php if ($this->is_multi_lang_active()) : ?>
            <div class="wawp-notif-global-item">
                <div class="card-header_row">
                    <div class="card-header">
                <h4 class="card-title">
                    <?php esc_html_e('Expand your global reach', 'awp'); ?>
                </h4>
                <p><?php esc_html_e('Send notifications to your customers wherever they are, in their local language.', 'awp'); ?>
                </p>
                    </div>
                </div>
                <button type="button" id="wawp-notif-add-lang-button-top" class="awp-btn secondary">
                    <i class="ri-add-line"></i>
                    <?php _e('Add language', 'awp'); ?>
                </button>
            </div>
            <?php else : ?>

        <div class="wawp-notif-global-item">
            <div class="card-header_row">
                <div class="card-header">
                    <h4 class="card-title"><?php esc_html_e( 'Unlock Multilingual Notifications', 'awp' ); ?></h4>
                    <p><?php esc_html_e( 'Speak to customers in their own language and boost engagement.', 'awp' ); ?></p>
                </div>
            </div>

            <a href="https://wawp.net/product/multilingual-notifications-package/"
               target="_blank"
               class="awp-btn secondary">
                <i class="ri-global-line" style="vertical-align:middle;margin-right:4px;"></i>
                <?php esc_html_e( 'Get the Multilingual Add-on', 'awp' ); ?>
            </a>
        </div>
        
            <?php endif; ?>

         <?php if ($this->is_multi_lang_active() && count($settings['configured_languages']) > 1) : ?>
            <div class="wawp-notif-global-item" style="padding-top: 1.25rem;margin-top: 1.25rem;border-top: 1px solid #DBDFE9;">
                <div class="card-header_row">
                    <div class="card-header">
                <h4 class="card-title">
                    <?php esc_html_e('Main Language', 'awp'); ?>
                </h4>
                <p><?php esc_html_e('Select the default language for notifications if a language is not set.', 'awp'); ?>
                </p>
                    </div>
                </div>
                <select name="wawp_notif_main_language_code" id="wawp_notif_main_language_code" style="max-width: 280px !important;">
                    <?php foreach ($settings['configured_languages'] as $lang_code => $lang_data) : ?>
                <option value="<?php echo esc_attr($lang_code); ?>" <?php selected($settings['main_language_code'], $lang_code); ?>>
                    <?php echo esc_html($lang_data['name']); ?>
                </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        </div>
        
            <?php wp_nonce_field('wawp_notif_save_settings_action', 'wawp_notif_save_settings_nonce'); ?>
            <input type="hidden" name="wawp_notif_add_notification_rule_lang" id="wawp_notif_add_notification_rule_lang" value="" />

            <input type="hidden" name="wawp_notif_duplicate_notification_rule_idx"
                   id="wawp_notif_duplicate_notification_rule_idx" value="" />
            <input type="hidden" name="wawp_notif_duplicate_notification_rule_lang"
                   id="wawp_notif_duplicate_notification_rule_lang" value="" />


            <input type="hidden" name="wawp_notif_remove_notification_rule_idx" id="wawp_notif_remove_notification_rule_idx" value="" />
            <input type="hidden" name="wawp_notif_remove_notification_rule_lang" id="wawp_notif_remove_notification_rule_lang" value="" />

            <input type="hidden" name="wawp_remove_language" id="wawp_remove_language" value="" />
            <input type="hidden" name="wawp_notif_copy_lang_src" id="wawp_notif_copy_lang_src" value="" />
            <input type="hidden" name="wawp_notif_copy_lang_dst" id="wawp_notif_copy_lang_dst" value="" />
            <input type="hidden" name="wawp_active_tab" id="wawp_active_tab" value="<?php echo esc_attr(
                isset($_POST['wawp_active_tab'])
                ? $_POST['wawp_active_tab']
                : (!empty($settings['configured_languages'])
                    ? '#tab-' . array_key_first($settings['configured_languages'])
                    : '')
            ); ?>" />
            
            <input type="hidden" name="wawp_new_language_to_add" id="wawp_new_language_to_add" value="" />

            <?php if (empty($settings['configured_languages'])) : ?>
                <p>
                    <?php _e('No languages configured yet. Please add a language using the button above and save settings.', WAWP_NOTIF_SLUG); ?>
                </p>
            <?php else : ?>
                <div class="wawp-notif-tabs">
                    <div class="nav-tab-wrapper" <?php echo $single_lang ? 'style="display:none;"' : ''; ?> style="margin-bottom: 1.25rem;">
                <?php foreach ($settings['configured_languages'] as $lang_code => $lang_data) : ?>
                    <div class="wawp-notif-tab-container">
               <a href="#tab-<?php echo esc_attr($lang_code); ?>" class="nav-tab">
                   <?php echo esc_html($lang_data['name']); ?>
                   <?php if ($lang_code === $settings['main_language_code']) : ?>
                       <span class="awp-badge wawp-notif-main-badge">
                           <?php _e('Main', WAWP_NOTIF_SLUG); ?>
                       </span>
                   <?php endif; ?>
                   <?php if ($this->is_multi_lang_active() && $lang_code !== $settings['main_language_code']) : ?>
                       <button type="button" class="wawp-notif-remove-lang-button" data-lang="<?php echo esc_attr($lang_code); ?>" title="<?php esc_attr_e('Remove this language configuration', WAWP_NOTIF_SLUG); ?>">
                           <i class="ri-close-line"></i>
                       </button>
                   <?php endif; ?>
               </a>
                    </div>
                <?php endforeach; ?>
                    </div>

                    <?php foreach ($settings['configured_languages'] as $lang_code => $lang_data) : ?>
                <div id="tab-<?php echo esc_attr($lang_code); ?>" class="wawp-notif-tab-content">
                    <div class="wawp-notif-header-wrapper">
               <div class="wawp-notif-header-text">
                   <h4 class="card-title">
                    <i class="ri-notification-line"></i>
                    <?php echo $single_lang
                        ? __('Setup Notification Passed Trigger', WAWP_NOTIF_SLUG)
                        : sprintf( __('Notifications for %s', WAWP_NOTIF_SLUG), esc_html($lang_data['name']) ); ?>
                </h4>

                   <p>
                    <i class="ri-code-s-slash"></i>
                    <?php echo $single_lang
                        ? __('Define how and when your notifications are sent.', WAWP_NOTIF_SLUG)
                        : sprintf( __('Language Code: %s', WAWP_NOTIF_SLUG), esc_html($lang_code) ); ?>
                </p>

               </div>
               <div class="wawp-notif-header-action">
                  <?php if ( ($this->is_pro_user() || count($lang_data['notifications']) < 5) && ($lang_code === $settings['main_language_code'] || $this->is_multi_lang_active()) ) : ?>
                       <button type="button" data-lang="<?php echo esc_attr($lang_code); ?>" class="awp-btn primary add-rule-button">
                           <i class="ri-add-line"></i>
                           <?php _e('New Notification', WAWP_NOTIF_SLUG); ?>
                       </button>
                   <?php endif; ?>
                  <?php if ($this->is_multi_lang_active() && count($settings['configured_languages']) > 1) : ?>
                       <button type="button" class="wawp-notif-copy-lang-button awp-btn secondary" data-lang="<?php echo esc_attr($lang_code); ?>">
                           <i class="ri-file-copy-line"></i>
                           <?php _e('Duplicate', WAWP_NOTIF_SLUG); ?>
                       </button>
                   <?php endif; ?>
               </div>
                    </div>

                    <?php if (!$this->is_pro_user() && count($lang_data['notifications']) >= 5) : ?>
               <div class="awp-card" style="border-left: 4px solid #d63638; margin: 15px 0; padding: 1rem; background: #fff; display: flex; align-items: center; justify-content: space-between;">
                   <p style="margin: 0;">
                       <strong><?php esc_html_e('You have reached the maximum cards.', 'awp'); ?></strong><br>
                       <?php esc_html_e('Upgrade to the Pro plan to add more cards.', 'awp'); ?>
                   </p>
                   <a href="https://wawp.net/pricing/" target="_blank" class="button button-primary">
                       <?php esc_html_e('Upgrade to Pro', 'awp'); ?>
                   </a>
               </div>
                    <?php endif; ?>


                    <?php if (empty($lang_data['notifications'])) : ?>
               <p>
                   <?php _e(
                       'No notifications configured for this language yet. Click "New Notification" to create one.',
                       WAWP_NOTIF_SLUG
                   ); ?>
               </p>
                    <?php else : ?>
               <?php foreach ($lang_data['notifications'] as $rule_idx => $rule) :
                   $rule_id = esc_attr($rule['id']);
                   $send_timing = $rule['send_timing'] ?? 'instant';
                   $current_sender_type = $rule['sender_type'] ?? 'user_whatsapp';
                   $enabled_flag = isset($rule['enabled'])
                       ? (bool)$rule['enabled']
                       : true;
                   $trigger_key = $rule['trigger_key'];
                   $trigger_icon_html = ' ';
                   $trigger_label_txt = $trigger_key;
                   $selected_countries = [];
                   if (!empty($rule['billing_countries'])) {
                       $selected_countries = array_map('sanitize_text_field', explode(',', $rule['billing_countries']));
                   }
                   $selected_gateways = [];
                   if (!empty($rule['payment_gateways'])) {
                       $selected_gateways = array_map('sanitize_text_field', explode(',', $rule['payment_gateways']));
                   }
                   if (isset($available_triggers[$trigger_key]['icon_file'])) {
                       $trigger_icon_html = $available_triggers[$trigger_key]['icon_file'];
                   }
                   if (isset($available_triggers[$trigger_key]['label'])) {
                       $trigger_label_txt = $available_triggers[$trigger_key]['label'];
                   }
                   $wa_img = '<img class="icon-svg" src="' . esc_url($icon_base . 'whatsapp.svg') . '" alt="WA" />';
                   $em_img = '<img class="icon-svg" src="' . esc_url($icon_base . 'email.svg') . '" alt="EM" />';

                   switch ($current_sender_type) {
                       case 'user_whatsapp':
                           $sender_icons_text = $wa_img . ' ' . esc_html__('User WhatsApp', WAWP_NOTIF_SLUG);
                           break;
                       case 'admin_whatsapp':
                           $sender_icons_text = $wa_img . ' ' . esc_html__('Admins WhatsApp', WAWP_NOTIF_SLUG);
                           break;
                       case 'user_email':
                           $sender_icons_text = $em_img . ' ' . esc_html__('User Email', WAWP_NOTIF_SLUG);
                           break;
                       case 'admin_email':
                           $sender_icons_text = $em_img . ' ' . esc_html__('Admins Email', WAWP_NOTIF_SLUG);
                           break;
                       case 'user_both':
                           $sender_icons_text =
                       $wa_img . ' ' . esc_html__('User WhatsApp', WAWP_NOTIF_SLUG) .
                       ' &nbsp; ' .
                       $em_img . ' ' . esc_html__('User Email', WAWP_NOTIF_SLUG);
                           break;
                       case 'admin_both':
                           $sender_icons_text =
                       $wa_img . ' ' . esc_html__('Admins WhatsApp', WAWP_NOTIF_SLUG) .
                       ' &nbsp; ' .
                       $em_img . ' ' . esc_html__('Admins Email', WAWP_NOTIF_SLUG);
                           break;
                       case 'user_admin_whatsapp':
                           $sender_icons_text = $wa_img . ' ' . esc_html__('User & Admins WhatsApp', WAWP_NOTIF_SLUG);
                           break;
                       case 'user_admin_email':
                           $sender_icons_text = $em_img . ' ' . esc_html__('User & Admins Email', WAWP_NOTIF_SLUG);
                           break;
                       case 'user_admin_both':
                       default:
                           $sender_icons_text =
                       $wa_img . ' ' . esc_html__('User WhatsApp & Admins WhatsApp', WAWP_NOTIF_SLUG) .
                       ' &nbsp; ' .
                       $em_img . ' ' . esc_html__('User Email & Admins Email', WAWP_NOTIF_SLUG);
                   }
                   $rule_number = $rule_idx + 1;
                   $rule_header_text = sprintf(
                       '%1$s %2$s %3$s %4$s %5$s %6$s #%7$d',
                       $trigger_icon_html,
                       __('When', WAWP_NOTIF_SLUG),
                       esc_html($trigger_label_txt),
                       __('send to', WAWP_NOTIF_SLUG),
                       wp_kses_post($sender_icons_text),
                       __('Rule', WAWP_NOTIF_SLUG),
                       $rule_number
                   );

                   $trigger_key = $rule['trigger_key'];
                   $is_wc_trigger = strpos($trigger_key, 'wc_') === 0;
                   $is_subscription_trigger = strpos($trigger_key, 'wc_sub_') === 0;
                   $is_order_note_trigger = ($trigger_key === 'wc_order_note_added');
                   $show_filters_on_load = ($is_wc_trigger && !$is_subscription_trigger && !$is_order_note_trigger);
                   $filter_style = $show_filters_on_load ? '' : 'style="display:none;"';
               ?>
               
                   <div class="wawp-notif-card-wrapper collapsed" data-rule-id="<?php echo $rule_id; ?>" data-trigger-key="<?php echo esc_attr($trigger_key); ?>" data-lang="<?php echo esc_attr($lang_code); ?>" data-idx="<?php echo esc_attr($rule_idx); ?>">
                       <div class="wawp-notif-card-header">
                           <div class="notif-header">
                                <div class="icons-group">
                                    <div class="trigger-icon"></div>
                                    <i class="ri-arrow-right-s-line"></i>
                                    <div class="channel-icon"></div>
                                </div>
                                <div class="notif-header-info">
                                <h4></h4>
                                <div class="trigger-badges">
                                    <div class="trigger-badge sendto"><i class="ri-contacts-line"></i><span></span></div>
                                    <div class="trigger-badge sendtime"><i class="ri-time-line"></i><span></span></div>
                                </div>
                                </div>
                           </div>
                           <div class="awp-notif-actions">
                       <label class="awp-switch">
                           <input type="checkbox" class="wawp-rule-enable-switch" data-rule-id="<?php echo $rule_id; ?>" <?php checked($enabled_flag, true); ?> />
                           <span class="awp-slider"></span>
                       </label>
                       
                       <?php if ($this->is_multi_lang_active() || $lang_code === $settings['main_language_code']) : ?>
                           <button type="button" class="awp-btn button-small wawp-notif-edit-button" data-rule-id="<?php echo esc_attr($rule_id); ?>" data-lang="<?php echo esc_attr($lang_code); ?>" title="<?php esc_attr_e('Edit Templates', WAWP_NOTIF_SLUG); ?>">
                               <i class="ri-edit-2-line"></i>
                           </button>
                       <?php endif; ?>
                       
                       <?php if ( $this->is_pro_user() || count( $lang_data['notifications'] ) < 5 ) : ?>
                           <button type="button"
                                   class="awp-btn wawp-notif-duplicate-rule-button"
                                   data-rule-id="<?php echo esc_attr( $rule_id ); ?>"
                                   data-lang="<?php echo esc_attr( $lang_code ); ?>"
                                   title="<?php esc_attr_e( 'Duplicate this notification rule', WAWP_NOTIF_SLUG ); ?>">
                               <i class="ri-file-copy-line"></i>
                           </button>
                       <?php endif; ?>

                       <button type="button" class="awp-btn wawp-notif-remove-rule-button" data-rule-id="<?php echo $rule_id; ?>" data-lang="<?php echo esc_attr($lang_code); ?>" style="color:#dc3232;" title="<?php esc_attr_e('Remove this notification rule', WAWP_NOTIF_SLUG); ?>">
                           <i class="ri-delete-bin-line"></i>
                       </button>
                       
                       </div>
                       </div>

                       <div class="wawp-notif-card-content <?php echo $enabled_flag ? '' : 'disabled'; ?>">
                            <input type="hidden" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][id]" value="<?php echo $rule_id; ?>" />
                               
                            <input type="hidden" class="wawp-sort-order" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][sort_order]" value="<?php echo $rule_idx; ?>" /> 
                               
                            <input type="hidden" class="wawp-rule-enabled-flag" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][enabled]" value="<?php echo $enabled_flag ? '1' : '0'; ?>" />
                            
                            <table class="form-table">
                            <tr>
                               <th scope="row">
                                   <label for="trigger_key_<?php echo $rule_id; ?>">
                                       <i class="ri-focus-3-line"></i>
                                       <?php _e('Trigger Event', WAWP_NOTIF_SLUG); ?>
                                   </label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][trigger_key]" id="trigger_key_<?php echo $rule_id; ?>" class="wawp-rule-trigger-select" data-rule-id="<?php echo $rule_id; ?>" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                       <?php foreach ($available_triggers as $key => $trigger_data) : ?>
                                  <option value="<?php echo esc_attr($key); ?>" data-icon="<?php echo esc_attr($trigger_data['icon_file'] ?? ''); ?>" <?php selected($rule['trigger_key'], $key); ?>>
                                      <?php echo esc_html($trigger_data['label']); ?>
                                  </option>
                                       <?php endforeach; ?>
                                   </select>
                               </td>
                            </tr>
                            <tr>
                               <th scope="row">
                                   <label for="send_timing_<?php echo $rule_id; ?>">
                                       <i class="ri-time-line"></i>
                                       <?php _e('Waiting Time', WAWP_NOTIF_SLUG); ?>
                                   </label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][send_timing]" id="send_timing_<?php echo $rule_id; ?>" class="wawp-notif-send-timing-select" data-rule-id="<?php echo $rule_id; ?>" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                       <option value="instant" <?php selected($send_timing, 'instant'); ?>>
                                  <?php _e('instant', WAWP_NOTIF_SLUG); ?>
                                       </option>
                                       <option value="delayed" <?php selected($send_timing, 'delayed'); ?>>
                                  <?php _e('After a Delay', WAWP_NOTIF_SLUG); ?>
                                       </option>
                                   </select>
                                   <div class="delay-fields <?php echo ($send_timing === 'delayed') ? 'active' : ''; ?>">
                                       <input type="number" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][delay_value]" value="<?php echo esc_attr($rule['delay_value'] ?? 1); ?>" min="1" style="width:70px;" <?php echo $enabled_flag ? '' : 'disabled'; ?> />
                                       <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][delay_unit]" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                  <option value="minutes" <?php selected($rule['delay_unit'] ?? 'minutes', 'minutes'); ?>>
                                      <?php _e('Minutes', WAWP_NOTIF_SLUG); ?>
                                  </option>
                                  <option value="hours" <?php selected($rule['delay_unit'] ?? '', 'hours'); ?>>
                                      <?php _e('Hours', WAWP_NOTIF_SLUG); ?>
                                  </option>
                                  <option value="days" <?php selected($rule['delay_unit'] ?? '', 'days'); ?>>
                                      <?php _e('Days', WAWP_NOTIF_SLUG); ?>
                                  </option>
                                       </select>
                                   </div>
                               </td>
                            </tr>
                               </table>
                            
                               <table class="form-table">
                            <tr>
                               <th scope="row">
                                   <label for="sender_type_<?php echo $rule_id; ?>">
                                       <i class="ri-send-plane-line"></i>
                                       <?php _e('Send Via', WAWP_NOTIF_SLUG); ?>
                                   </label>
                               </th>
                               <td class="wawp-send-to-cell">
                                    <div class="wawp-send-row">
                                        <select id="send_channel_<?php echo $rule_id; ?>" class="wawp-send-channel" data-rule-id="<?php echo $rule_id; ?>">
                                           <option value="whatsapp"><?php _e( 'WhatsApp',            WAWP_NOTIF_SLUG ); ?></option>
                                           <option value="email">   <?php _e( 'E‑mail',              WAWP_NOTIF_SLUG ); ?></option>
                                           <option value="both">    <?php _e( 'WhatsApp &amp; E‑mail', WAWP_NOTIF_SLUG ); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="template-status-wrapper">
                                       <label>
                                           <?php _e('Template Status:', WAWP_NOTIF_SLUG); ?>
                                       </label>
                                       <div>
                                           <div class="status-item-wrapper" data-status-type="user_whatsapp">
                                               <span class="wawp-template-status" data-type="user_whatsapp">
                                          <?php echo (!empty($rule['whatsapp_message']))
                                              ? '<i class="ri-checkbox-circle-fill" style="color:#0b7;"></i> ' . esc_html__('WhatsApp for User', WAWP_NOTIF_SLUG)
                                              : '<i class="ri-close-circle-fill" style="color:#dc3545;"></i> ' . esc_html__('WhatsApp for User', WAWP_NOTIF_SLUG); ?>
                                               </span>
                                           </div>
                                           <div class="status-item-wrapper" data-status-type="admin_whatsapp">
                                               <span class="wawp-template-status" data-type="admin_whatsapp">
                                          <?php echo (!empty($rule['admin_whatsapp_message']))
                                              ? '<i class="ri-checkbox-circle-fill" style="color:#0b7;"></i> ' . esc_html__('WhatsApp for Admins', WAWP_NOTIF_SLUG)
                                              : '<i class="ri-close-circle-fill" style="color:#dc3545;"></i> ' . esc_html__('WhatsApp for Admins', WAWP_NOTIF_SLUG); ?>
                                               </span>
                                           </div>
                                           <div class="status-item-wrapper" data-status-type="user_email">
                                               <span class="wawp-template-status" data-type="user_email">
                                          <?php echo (!empty($rule['email_subject']) && !empty($rule['email_body']))
                                              ? '<i class="ri-checkbox-circle-fill" style="color:#0b7;"></i> ' . esc_html__('Email for User', WAWP_NOTIF_SLUG)
                                              : '<i class="ri-close-circle-fill" style="color:#dc3545;"></i> ' . esc_html__('Email for User', WAWP_NOTIF_SLUG); ?>
                                               </span>
                                           </div>
                                           <div class="status-item-wrapper" data-status-type="admin_email">
                                               <span class="wawp-template-status" data-type="admin_email">
                                          <?php echo (!empty($rule['admin_email_subject']) && !empty($rule['admin_email_body']))
                                              ? '<i class="ri-checkbox-circle-fill" style="color:#0b7;"></i> ' . esc_html__('Email for Admins', WAWP_NOTIF_SLUG)
                                              : '<i class="ri-close-circle-fill" style="color:#dc3545;"></i> ' . esc_html__('Email for Admins', WAWP_NOTIF_SLUG); ?>
                                               </span>
                                           </div>
                                           <p>
                                               <?php _e('Click the Edit button above to open the template editor popup.', WAWP_NOTIF_SLUG); ?>
                                           </p>
                                       </div>
                                    </div>
                                    
                                    
                               </td>
                            </tr>
                                   
                            <tr style="margin: 0;">
                               <th scope="row">
                                   <label for="sender_type_<?php echo $rule_id; ?>">
                                       <i class="ri-contacts-line"></i>
                                       <?php _e('Send To', WAWP_NOTIF_SLUG); ?>
                                   </label>
                               </th>
                               <td class="wawp-send-to-cell">
                                   <div class="wawp-send-row">
                                       <input type="hidden" name="configured_languages[<?php echo esc_attr( $lang_code ); ?>][notifications][<?php echo $rule_idx; ?>][sender_type]" id="sender_type_<?php echo $rule_id; ?>" value="<?php echo esc_attr( $current_sender_type ); ?>" class="wawp-rule-sender-dropdown" data-rule-id="<?php echo $rule_id; ?>" />
                                       <select id="send_recipient_<?php echo $rule_id; ?>" class="wawp-send-recipient" data-rule-id="<?php echo $rule_id; ?>">
                                          <option value="user"><?php _e( 'User', WAWP_NOTIF_SLUG ); ?></option>
                                          <option value="admin"><?php _e( 'Admins', WAWP_NOTIF_SLUG ); ?></option>
                                          <option value="both"><?php _e( 'User & Admins', WAWP_NOTIF_SLUG ); ?></option>
                                       </select>
                                   </div>
                                   <div class="admin-user-row-<?php echo $rule_id; ?>" style="<?php echo (strpos($current_sender_type, 'admin') !== false) ? '' : 'display:none;'; ?>">
                                       <label for="admin_user_ids_<?php echo $rule_id; ?>">
                                           <?php _e('Select Admins to Get Notifications:', WAWP_NOTIF_SLUG); ?>
                                       </label>
                                       <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][admin_user_ids][]" id="admin_user_ids_<?php echo $rule_id; ?>" class="wawp-admin-user-select" multiple="multiple" data-rule-id="<?php echo $rule_id; ?>" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                           <?php
                                           $existing_ids = [];
                                           if (!empty($rule['admin_user_ids'])) {
                                      $existing_ids = array_filter(array_map('intval', explode(',', $rule['admin_user_ids'])));
                                           }
                                           if (!empty($existing_ids)) {
                                      foreach ($existing_ids as $uid) {
                                          $u = get_userdata($uid);
                                          if ($u) {
                                      echo '<option value="' . esc_attr($u->ID) . '" selected>'
                                          . esc_html($u->display_name . ' (' . $u->user_email . ' – ID:' . $u->ID . ')')
                                          . '</option>';
                                          }
                                      }
                                           }
                                           ?>
                                       </select>
                                       <p class="description">
                                           <?php _e('Search any user by name, email, or ID. You can select multiple users.', WAWP_NOTIF_SLUG); ?>
                                       </p>
                                   </div>
                               </td>
                            </tr>
                            
                               </table>
                            
                               <input type="hidden" id="whatsapp_message_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][whatsapp_message]" value="<?php echo esc_attr($rule['whatsapp_message']); ?>" />
                               <input type="hidden" id="whatsapp_media_url_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][whatsapp_media_url]" value="<?php echo esc_url($rule['whatsapp_media_url']); ?>" />
                               <input type="hidden" id="email_subject_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][email_subject]" value="<?php echo esc_attr($rule['email_subject']); ?>" />
                               <input type="hidden" id="email_body_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][email_body]" value="<?php echo esc_textarea($rule['email_body']); ?>" />
                               <input type="hidden" id="admin_whatsapp_message_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][admin_whatsapp_message]" value="<?php echo esc_attr($rule['admin_whatsapp_message']); ?>" />
                               <input type="hidden" id="admin_whatsapp_media_url_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][admin_whatsapp_media_url]" value="<?php echo esc_url($rule['admin_whatsapp_media_url']); ?>" />
                               <input type="hidden" id="admin_email_subject_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][admin_email_subject]" value="<?php echo esc_attr($rule['admin_email_subject']); ?>" />
                               <input type="hidden" id="admin_email_body_<?php echo $rule_id; ?>" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][admin_email_body]" value="<?php echo esc_textarea($rule['admin_email_body']); ?>" />
                            
                               <?php
                               $trigger_key = $rule['trigger_key'];
                               $is_wc_trigger = (strpos($trigger_key, 'wc_') === 0);
                               $is_subscription_trigger = (strpos($trigger_key, 'wc_sub_') === 0);
                               $is_order_note_trigger = ($trigger_key === 'wc_order_note_added');
                            
                               $show_wc_filters_on_load = ($is_wc_trigger && !$is_subscription_trigger && !$is_order_note_trigger);
                            
                               $table_style = $show_wc_filters_on_load ? '' : 'display:none;';
                               ?>
                               <?php
                               $table_style = $show_wc_filters_on_load ? '' : 'display:none;';
                               ?>
                            
                               <table class="form-table wawp-product-image-switch-row" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                            <tr>
                               <th scope="row">
                                   <label for="send_product_image_<?php echo $rule_id; ?>">
                                       <i class="ri-image-line"></i>
                                       <?php _e('Send Product Image', WAWP_NOTIF_SLUG); ?>
                                   </label>
                                   <p><?php _e('If enabled, the featured image of the first product in the order will be sent with the WhatsApp notification.', WAWP_NOTIF_SLUG); ?></p>
                               </th>
                               <td>
                                   <label class="awp-switch">
                                       <input type="checkbox" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][send_product_image]" id="send_product_image_<?php echo $rule_id; ?>" value="1" <?php checked(!empty($rule['send_product_image'])); ?> <?php echo $enabled_flag ? '' : 'disabled'; ?> />
                                       <span class="awp-slider"></span>
                                   </label>
                               </td>
                            </tr>
                               </table>
                               <table class="form-table wawp-filter-toggle-row-container" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                               <tr>
                            <th>
                                   <label>
                                       <i class="ri-filter-line"></i>
                                       <?php _e('WooCommerce Filters', WAWP_NOTIF_SLUG); ?>
                                   </label>

                            </th>
                            <td>
                            
                               <div class="wawp-filter-toggle-row">
                                   <?php
                                   $toggles = [
                                       'country' => ['label' => __('Country Filter',  WAWP_NOTIF_SLUG), 'icon' => 'ri-earth-line'],
                                       'product' => ['label' => __('Product Filter', WAWP_NOTIF_SLUG), 'icon' => 'ri-shopping-bag-3-line'],
                                       'payment' => ['label' => __('Payment Filter',  WAWP_NOTIF_SLUG), 'icon' => 'ri-bank-card-line'],
                                   ];
                                   foreach ($toggles as $slug => $data) :
                                       $label = $data['label'];
                                       $icon_class = $data['icon'];
                                       $flag = "{$slug}_filter_enabled";
                                   ?>
                            
                                    <div class="wawp-filter-toggle-item">
                            
                                        <span class="toggle-label">
                                            <i class="<?php echo esc_attr($icon_class); ?>"></i>
                                            <?php echo esc_html($label); ?>
                                        </span>
                                
                                        <label class="awp-switch wawp-filter-toggle <?= $slug ?>-toggle">
                                            <input type="checkbox" class="<?= $flag ?>-input" name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][<?= $flag ?>]" id="<?= $flag ?>_<?php echo $rule_id; ?>" data-rule-id="<?php echo $rule_id; ?>" data-filter="<?= $slug ?>" <?php checked(!empty($rule[$flag])); ?> />
                                            <span class="awp-slider"></span>
                                        </label>
                            
                                    </div>
                            
                                   <?php endforeach; ?>
                               </div>
                            </td>
                               </tr>
                            </table>
                            <div class="awp-woo-filters" style="<?php echo $table_style; ?>">
                               <table class="form-table wawp-billing-country-whitelist-filter-table" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                            <tr>
                               <th>
                                   <label><i class="ri-earth-line"></i> <?php _e('Billing Country – Only send if', WAWP_NOTIF_SLUG); ?></label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][billing_countries_whitelist][]" class="wawp-billing-country-select" multiple="multiple" style="width:100%;" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                       <?php
                                       $billing_whitelist = array_filter(explode(',', $rule['billing_countries_whitelist'] ?? ''));
                                       ?><option value="" <?php echo empty($billing_whitelist) ? 'selected' : ''; ?>>
                                  <?php esc_html_e('Any country', WAWP_NOTIF_SLUG); ?></option><?php
                                                      foreach ($all_wc_countries as $code => $label) : ?>
                                  <option value="<?php echo esc_attr($code); ?>" <?php selected(in_array($code, $billing_whitelist, true)); ?>>
                                      <?php echo esc_html("{$label} ({$code})"); ?></option>
                                       <?php endforeach; ?>
                                   </select>
                                   <p class="description"><?php _e('Send only if the customer’s billing country is one of these. Leave empty to apply to all countries.', WAWP_NOTIF_SLUG); ?></p>
                               </td>
                            </tr>
                               </table>
                            
                            
                               <table class="form-table wawp-billing-country-blocklist-filter-table" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                            <tr>
                               <th>
                                   <label><i class="ri-forbid-2-line"></i> <?php _e('Billing Country – DO NOT send if', WAWP_NOTIF_SLUG); ?></label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][billing_countries_blocklist][]" class="wawp-billing-country-select" multiple="multiple" style="width:100%;" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                       <?php
                                       $billing_blocklist = array_filter(explode(',', $rule['billing_countries_blocklist'] ?? ''));
                                       foreach ($all_wc_countries as $code => $label) : ?>
                                  <option value="<?php echo esc_attr($code); ?>" <?php selected(in_array($code, $billing_blocklist, true)); ?>>
                                      <?php echo esc_html("{$label} ({$code})"); ?></option>
                                       <?php endforeach; ?>
                                   </select>
                                   <p class="description"><?php _e('Do NOT send if the customer’s billing country is one of these. This overrides the whitelist.', WAWP_NOTIF_SLUG); ?></p>
                               </td>
                            </tr>
                               </table>
                            <hr class="h-divider wawp-product-whitelist-filter-table wawp-product-blocklist-filter-table">
                               <table class="form-table wawp-product-whitelist-filter-table" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                            <tr>
                               <th>
                                   <label><i class="ri-shopping-bag-3-line"></i> <?php _e('Products – Only send if', WAWP_NOTIF_SLUG); ?></label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][product_ids_whitelist][]" multiple="multiple" class="wawp-product-select" style="width:100%;">
                                       <?php foreach (array_filter(explode(',', $rule['product_ids_whitelist'] ?? '')) as $pid) :
                                  $p = wc_get_product($pid);
                                  if (!$p) continue; ?>
                                  <option value="<?php echo esc_attr($pid); ?>" selected><?php echo esc_html($p->get_name() . ' (ID:' . $pid . ')'); ?></option>
                                       <?php endforeach; ?>
                                   </select>
                                   <p class="description"><?php _e('Send only if the order contains at least one of the selected products. Leave empty to allow any product.', WAWP_NOTIF_SLUG); ?></p>
                               </td>
                            </tr>
                               </table>
                            
                            
                               <table class="form-table wawp-product-blocklist-filter-table" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                            <tr>
                               <th>
                                   <label><i class="ri-forbid-2-line"></i> <?php _e('Products – DO NOT send if', WAWP_NOTIF_SLUG); ?></label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][product_ids_blocklist][]" multiple="multiple" class="wawp-product-select" style="width:100%;">
                                       <?php foreach (array_filter(explode(',', $rule['product_ids_blocklist'] ?? '')) as $pid) :
                                  $p = wc_get_product($pid);
                                  if (!$p) continue; ?>
                                  <option value="<?php echo esc_attr($pid); ?>" selected><?php echo esc_html($p->get_name() . ' (ID:' . $pid . ')'); ?></option>
                                       <?php endforeach; ?>
                                   </select>
                                   <p class="description"><?php _e('Do NOT send if the order contains any of the selected products. This overrides the whitelist.', WAWP_NOTIF_SLUG); ?></p>
                               </td>
                            </tr>
                               </table>
                               <hr class="h-divider wawp-payment-gateway-filter-table">
                               <table class="form-table wawp-payment-gateway-filter-table" data-rule-id="<?php echo esc_attr($rule_id); ?>" style="<?php echo $table_style; ?>">
                            <tr>
                               <th>
                                   <label><i class="ri-bank-card-line"></i> <?php _e('Payment Gateway Filter', WAWP_NOTIF_SLUG); ?></label>
                               </th>
                               <td>
                                   <select name="configured_languages[<?php echo esc_attr($lang_code); ?>][notifications][<?php echo $rule_idx; ?>][payment_gateways][]" id="payment_gateways_<?php echo esc_attr($rule_id); ?>" class="wawp-payment-gateway-select" multiple="multiple" style="width:100%;" <?php echo $enabled_flag ? '' : 'disabled'; ?>>
                                       <option value="" <?php echo empty($selected_gateways) ? 'selected' : ''; ?>>
                                  <?php esc_html_e('All Gateways', WAWP_NOTIF_SLUG); ?>
                                       </option>
                                       <?php
                                       if (!empty($all_gateways)) {
                                  foreach ($all_gateways as $gw_id => $gw_label) :
                                       ?>
                                      <option value="<?php echo esc_attr($gw_id); ?>" <?php
                                                             $safe_selected_gateways = is_array($selected_gateways) ? $selected_gateways : [];
                                                             echo in_array($gw_id, $safe_selected_gateways, true) ? 'selected' : '';
                                                             ?>>
                                  <?php echo esc_html($gw_label . ' (' . $gw_id . ')'); ?>
                                      </option>
                                       <?php endforeach;
                                       } ?>
                                   </select>
                                   <p class="description">
                                       <?php _e('Send only for orders paid with the selected gateways. Leave empty to apply to all payment methods.', WAWP_NOTIF_SLUG); ?>
                                   </p>
                               </td>
                            </tr>
                           </table>
                           </div>
                       </div>
                   </div>
               <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php submit_button(
                __('Save All Settings', WAWP_NOTIF_SLUG),
                'awp-btn primary',
                'save_settings_button',
                true
            ); ?>
        </form>


        <div id="wawp-notif-copy-lang-popup" style="display:none;">
            <div class="wawp-notif-popup-inner">
                <h4 class="card-title">
                    <i class="ri-file-copy-line"></i>
                    <?php _e('Copy All Notifications to Another Language', WAWP_NOTIF_SLUG); ?>
                </h4>
                <p><?php _e('This will copy all notifications from this language to the destination language you select below.', WAWP_NOTIF_SLUG); ?></p>
                <p style="margin: 1.25rem 0 !important;">
                    <label for="wawp-notif-copy-destination-lang" style="display:block; margin-bottom: 5px;">
                <strong><?php _e('Destination Language:', WAWP_NOTIF_SLUG); ?></strong>
                    </label>
                    <select id="wawp-notif-copy-destination-lang" style="width: 100%;"></select>
                </p>
                <p class="btn-group">
                    <button id="wawp-notif-copy-lang-confirm" class="awp-btn primary">
                <i class="ri-check-line"></i>
                <?php _e('Confirm Copy', WAWP_NOTIF_SLUG); ?>
                    </button>
                    <button id="wawp-notif-copy-lang-cancel" class="awp-btn secondary">
                <i class="ri-close-line"></i>
                <?php _e('Cancel', WAWP_NOTIF_SLUG); ?>
                    </button>
                </p>
            </div>
        </div>
    </div>

    <?php if ($this->is_multi_lang_active()) : ?>
        <div id="wawp-notif-add-lang-popup" style="display:none;">
            <div class="wawp-notif-popup-inner">
                <div class="card-header_row" style="justify-content: start;gap: .5rem;">
                    <i class="ri-translate-2" style="font-size: 2.25rem !important;color: var(--heading);"></i>
                    <div class="card-header">
                <h4 class="card-title">
                    <?php _e('Add Language', 'awp'); ?>
                </h4>
                <p>
                    <?php _e("Select the language you'll translate the notifications to.", 'awp'); ?>
                </p>
                    </div>
                </div>
                <select id="wawp-notif-select-language" style="width: 100%; max-width: 100%;">
                    <option value=""><?php _e('-- Select a Language --', WAWP_NOTIF_SLUG); ?></option>
                    <?php
                    foreach ($available_translations as $code => $details) {
                if (!isset($settings['configured_languages'][$code])) {
                    echo '<option value="' . esc_attr($code) . '">'
               . esc_html($details['native_name'])
               . ' ('
               . esc_html($details['english_name'])
               . ' - '
               . esc_attr($code)
               . ')</option>';
                }
                    }
                    if (!isset($available_translations['en_US']) && !isset($settings['configured_languages']['en_US'])) {
                echo '<option value="en_US">English (US) (en_US)</option>';
                    }
                    ?>
                </select>
                <div class="btn-group" style="justify-content: end;">
                    <button id="wawp-notif-confirm-add-lang" class="awp-btn primary">
                <?php _e('Add', WAWP_NOTIF_SLUG); ?>
                    </button>
                    <button id="wawp-notif-popup-close" class="awp-btn secondary">
                <?php _e('Cancel', WAWP_NOTIF_SLUG); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="wawp-notif-edit-template-popup" style="display:none;">
        <div class="wawp-notif-popup-inner">
           <h4 class="card-title">
    <i class="ri-edit-2-line"></i>
    <?php _e( 'Edit Notification', WAWP_NOTIF_SLUG ); ?>
    <div id="wawp-popup-rule-header" style="margin-top:4px;"></div>
</h4>
            <div class="navigation-tabs">
                <button class="nav-step-btn active" data-step="1">
                    <?php _e('User WhatsApp', WAWP_NOTIF_SLUG); ?>
                </button>
                <button class="nav-step-btn" data-step="2">
                    <?php _e('User Email', WAWP_NOTIF_SLUG); ?>
                </button>
                <button class="nav-step-btn" data-step="3">
                    <?php _e('Admin WhatsApp', WAWP_NOTIF_SLUG); ?>
                </button>
                <button class="nav-step-btn" data-step="4">
                    <?php _e('Admin Email', WAWP_NOTIF_SLUG); ?>
                </button>
            </div>

            <div id="wawp-template-steps-container">
                <div class="step step-1" data-step="1">
                    <h4><?php _e('User WhatsApp Message', WAWP_NOTIF_SLUG); ?></h4>
                    <table class="form-table">
                <tr>
                    <th scope="row">
               <label for="popup_whatsapp_message"><?php _e('WhatsApp Message', WAWP_NOTIF_SLUG); ?></label>
                    </th>
               <td>
                   <div style="position: relative;">
                           <textarea id="popup_whatsapp_message" rows="4" class="large-text wawp-emojione-editor"></textarea>
                       <div class="placeholder-container">
                           <?php $placeholders = $this->get_available_placeholders(); ?>
                           <select class="placeholder-dropdown" data-target="popup_whatsapp_message" title="<?php esc_attr_e('Insert a placeholder', 'awp'); ?>">
                       <option value=""></option> <?php foreach ($placeholders as $group => $options) : ?>
                           <optgroup label="<?php echo esc_attr($group); ?>">
                               <?php foreach ($options as $value => $label) : ?>
                           <option value="<?php echo esc_attr($value); ?>">
                               <?php echo esc_html($label); ?>
                           </option>
                       <?php endforeach; ?>

                           </optgroup>
                       <?php endforeach; ?>
                           </select>
                       </div>
                       </div>
                   </td>
                </tr>
                    </table>
                </div>

                <div class="step step-2" data-step="2">
                    <h4><?php _e('User Email Message', WAWP_NOTIF_SLUG); ?></h4>
                    <table class="form-table">
                <tr>
                    <th scope="row">
               <label for="popup_email_subject"><?php _e('Email Subject', WAWP_NOTIF_SLUG); ?></label>
                    </th>
                    <td>
               <input type="text" id="popup_email_subject" class="large-text" />
                    </td>
                </tr>
                
                
                  <tr>
                    <th scope="row">
               <label for="popup_email_body"><?php _e('Email Body', WAWP_NOTIF_SLUG); ?></label>
                    </th>
                    <td>
               <input type="text" id="popup_email_body" class="large-text" />
               
               
                    </td>
                    <td style="display: none;">
               <?php
               wp_editor(
                   '',
                   'popup_email_body',
                   [
                       'textarea_name' => 'popup_email_body',
                       'media_buttons' => false,
                       'textarea_rows' => 7,
                       'teeny'         => false,
                   ]
               );
               ?>
                
                    </td>
               <p class="description"><?php _e('HTML is allowed. Use same placeholders as above.', WAWP_NOTIF_SLUG); ?></p>
                </tr>
                    
                    </table>
                </div>

                <div class="step step-3" data-step="3">
                    <h4><?php _e('Admin WhatsApp Message', WAWP_NOTIF_SLUG); ?></h4>
                    <table class="form-table">
                <tr>
                    <th scope="row">
                    </th>
                    <td>
                        <div style="position: relative;">
                        <textarea id="popup_admin_whatsapp_message" rows="4" class="large-text wawp-emojione-editor"></textarea>
                       <div class="placeholder-container">
                           <select class="placeholder-dropdown" data-target="popup_admin_whatsapp_message" title="<?php esc_attr_e('Insert a placeholder', 'awp'); ?>">
                       <option value=""></option> <?php foreach ($placeholders as $group => $options) : ?>
                           <optgroup label="<?php echo esc_attr($group); ?>">
                               <?php foreach ($options as $value => $label) : ?>
                                   <option value="<?php echo esc_attr($value); ?>">
                              <?php echo esc_html($label); ?> (<?php echo esc_html($value); ?>)
                                   </option>
                               <?php endforeach; ?>
                           </optgroup>
                       <?php endforeach; ?>
                           </select>
                       </div>
                       </div>
                   </td>
                </tr>
                    </table>
                </div>

                <div class="step step-4" data-step="4">
                    <h4><?php _e('Admin Email Message', WAWP_NOTIF_SLUG); ?></h4>
                    <table class="form-table">
                <tr>
                    <th scope="row">
               <label for="popup_admin_email_subject"><?php _e('Admin Email Subject', WAWP_NOTIF_SLUG); ?></label>
                    </th>
                    <td>
               <input type="text" id="popup_admin_email_subject" class="large-text" />
                    </td>
                </tr>
                    <tr>
                    <th scope="row">
               <label for="popup_admin_email_body"><?php _e('Admin Email Body', WAWP_NOTIF_SLUG); ?></label>
                    </th>
                    <td>
               <input type="text" id="popup_admin_email_body" class="large-text" />
                    </td>
               <p class="description"><?php _e('HTML is allowed. Use same placeholders as above.', WAWP_NOTIF_SLUG); ?></p>
                </tr>
                    </table>
                </div>
            </div>

            <div class="navigation-buttons">

                <button type="button" class="awp-btn primary" id="popup_finish_btn" style="display:none;">
                    <i class="ri-check-line"></i> <?php _e('Finish & Save', WAWP_NOTIF_SLUG); ?>
                </button>
                <button type="button" class="awp-btn secondary" id="popup_cancel_btn" style="margin-left:8px;">
                    <i class="ri-close-line"></i> <?php _e('Cancel', WAWP_NOTIF_SLUG); ?>
                </button>

            </div>
            <input type="hidden" id="popup_rule_id" value="" />
            <input type="hidden" id="popup_rule_index" value="" />
            <input type="hidden" id="popup_rule_lang" value="" />

        </div>
    </div>

    </div>
<?php
}

    private function get_available_placeholders() {

    $td = WAWP_NOTIF_SLUG; 
    $placeholders = [
        __( 'Always available', $td ) => [
            '{{sitename}}' => __( 'Site Title', $td ),
            '{{siteurl}}' => __( 'Home URL', $td ),
            '{{tagline}}' => __( 'Site Tagline', $td ),
            '{{privacy-policy}}' => __( 'Privacy Policy', $td ),
        ],

        __( 'User Login/Signup', $td ) => [
            '{{user_name}}'           => __( 'Username', $td ),
            '{{user_first_last_name}}'       => __( 'Display Name', $td ),
            '{{wp-email}}'         => __( 'User Email', $td ),
            '{{wp-first-name}}' => __( 'First Name', $td ),
            '{{wp-last-name}}'  => __( 'Last Name', $td ),
            '{{awp_user_phone}}'  => __( 'Phone number', $td ),
        ],

        __( 'Admin only', $td ) => [
            '{admin_display_name}' => __( 'Admin Name', $td ),
        ],
    ];

    if ( class_exists( 'WooCommerce' ) ) {

        $placeholders[ __( 'WooCommerce Order', $td ) ] = [
            '{{order_id}}'           => __( 'Order ID', $td ),
            '{{wc-order-amount}}'        => __( 'Order Total', $td ),
            '{{currency}}' => __( 'Currency', $td ),
            '{{order_date}}' => __( 'Order Date', $td ),
            '{{billing_first_name}}' => __( 'First Name', $td ),
            '{{billing_last_name}}' => __( 'Last Name', $td ),
            '{{billing_phone}}' => __( 'Phone', $td ),
            '{{billing_email}}' => __( 'Email', $td ),
            '{{product_name}}' => __( 'Product Name', $td ),
            '{{customer_note}}'      => __( 'Customer Note', $td ),
        ];

        if ( class_exists( 'WC_Subscriptions' ) && $this->is_subscription_notifs_active() ) {

            $placeholders[ __( 'WooCommerce Subscription', $td ) ] = [
                '{{subscription_id}}'           => __( 'Subscription ID', $td ),
                '{{subscription_total}}'        => __( 'Subscription Total', $td ),
                '{{subscription_status_label}}' => __( 'Subscription Status', $td ),
                '{{next_payment_date}}'         => __( 'Next Payment Date', $td ),
                '{{renewal_order_id}}'          => __( 'Renewal Order ID', $td ),
                '{{renewal_amount}}'            => __( 'Renewal Amount', $td ),
            ];
        }
    }

    return apply_filters( 'wawp_notif_available_placeholders', $placeholders );
}

    private function process_form_submission(array &$settings)
    {
        if (empty($this->awp_db_manager)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Database manager not initialised – cannot save.', WAWP_NOTIF_SLUG) . '</p></div>';
            return;
        }
        $action_taken = false;
        if (!empty($_POST['wawp_notif_add_notification_rule_lang'])) {
            $action_taken = true;
            $lang = sanitize_text_field($_POST['wawp_notif_add_notification_rule_lang']);
            
            if (!$this->is_pro_user() && isset($settings['configured_languages'][$lang]) && count($settings['configured_languages'][$lang]['notifications']) >= 5) {
                echo '<div class="awp-card flex align-center justify-between"><p>' . esc_html__('You have reached the maximum of 5 notifications for the Free plan. Please upgrade to add more.', WAWP_NOTIF_SLUG) . '</p></div>';
            } elseif (isset($settings['configured_languages'][$lang])) {
                $settings['configured_languages'][$lang]['notifications'][] = $this->get_default_rule();
                $_POST['wawp_active_tab'] = '#tab-' . $lang;
                echo '<div class="awp-badge success" style="position: fixed;bottom: 24px;padding: .5rem;border-radius: .5rem;"><span>' . esc_html__('New notification added. Please configure and save.', WAWP_NOTIF_SLUG) . '</span></div>';
            }

        } elseif (!empty($_POST['wawp_remove_language'])) {
            $action_taken = true;
            $rm = sanitize_text_field($_POST['wawp_remove_language']);
            if (isset($settings['configured_languages'][$rm])) {
                unset($settings['configured_languages'][$rm]);
                if ($settings['main_language_code'] === $rm) {
                    $settings['main_language_code'] = array_key_first($settings['configured_languages']) ?: '';
                }
                global $wpdb;
                $wpdb->delete($wpdb->prefix . 'awp_notif_languages', ['language_code' => $rm], ['%s']);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Language removed.', WAWP_NOTIF_SLUG) . '</p></div>';
            }
        } elseif (!empty($_POST['wawp_new_language_to_add'])) {
            $action_taken = true;
            $new = sanitize_text_field($_POST['wawp_new_language_to_add']);
            if (!isset($settings['configured_languages'][$new])) {
                $det = $this->get_language_details($new);
                $name = $det ? $det['native_name'] . ' (' . $det['english_name'] . ')' : $new;
                $settings['configured_languages'][$new] = ['name' => $name, 'notifications' => []];
                if (empty($settings['main_language_code'])) {
                    $settings['main_language_code'] = $new;
                }
                $_POST['wawp_active_tab'] = '#tab-' . $new;
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Language added.', WAWP_NOTIF_SLUG) . '</p></div>';
            }
        } elseif (isset($_POST['wawp_notif_remove_notification_rule_idx']) && $_POST['wawp_notif_remove_notification_rule_idx'] !== '') {
            $action_taken = true;
            $idx = intval($_POST['wawp_notif_remove_notification_rule_idx']);
            $lang = sanitize_text_field($_POST['wawp_notif_remove_notification_rule_lang']);
            if ($idx >= 0 && isset($settings['configured_languages'][$lang]['notifications'][$idx])) {
                $rule = $settings['configured_languages'][$lang]['notifications'][$idx];
                $this->clear_crons_for_rule($rule['id']);
                unset($settings['configured_languages'][$lang]['notifications'][$idx]);
                $settings['configured_languages'][$lang]['notifications'] = array_values($settings['configured_languages'][$lang]['notifications']);
                $_POST['wawp_active_tab'] = '#tab-' . $lang;
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rule removed.', WAWP_NOTIF_SLUG) . '</p></div>';
            }
        } elseif (isset($_POST['wawp_notif_duplicate_notification_rule_idx']) && $_POST['wawp_notif_duplicate_notification_rule_idx'] !== '') {
            $action_taken = true;
            $idx = intval($_POST['wawp_notif_duplicate_notification_rule_idx']);
            $lang = sanitize_text_field($_POST['wawp_notif_duplicate_notification_rule_lang']);
            if ($idx >= 0 && isset($settings['configured_languages'][$lang]['notifications'][$idx])) {
                $copy = $settings['configured_languages'][$lang]['notifications'][$idx];
                $copy['id'] = $this->generate_unique_id();
                $settings['configured_languages'][$lang]['notifications'][] = $copy;
                $_POST['wawp_active_tab'] = '#tab-' . $lang;
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rule duplicated.', WAWP_NOTIF_SLUG) . '</p></div>';
            }
        } elseif (!empty($_POST['wawp_notif_copy_lang_src']) && !empty($_POST['wawp_notif_copy_lang_dst'])) {
            $action_taken = true;
            $src = sanitize_text_field($_POST['wawp_notif_copy_lang_src']);
            $dst = sanitize_text_field($_POST['wawp_notif_copy_lang_dst']);
            if (isset($settings['configured_languages'][$src])) {
                if (!isset($settings['configured_languages'][$dst])) {
                    $det = $this->get_language_details($dst);
                    $settings['configured_languages'][$dst] = ['name' => $det ? $det['native_name'] . ' (' . $det['english_name'] . ')' : $dst, 'notifications' => []];
                }
                foreach ($settings['configured_languages'][$src]['notifications'] as $rule) {
                    $rule['id'] = $this->generate_unique_id();
                    $settings['configured_languages'][$dst]['notifications'][] = $rule;
                }
                $_POST['wawp_active_tab'] = '#tab-' . $dst;
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rules copied. Please translate and save.', WAWP_NOTIF_SLUG) . '</p></div>';
            }
        }
        if (!$action_taken) {
            
            if (!$this->is_multi_lang_active() && isset($_POST['configured_languages']) && count($_POST['configured_languages']) > 1) {
                $main_lang_code = sanitize_text_field($_POST['wawp_notif_main_language_code'] ?? array_key_first($_POST['configured_languages']));
                $main_lang_data = $_POST['configured_languages'][$main_lang_code] ?? null;

                if ($main_lang_data) {
                    $_POST['configured_languages'] = [$main_lang_code => $main_lang_data];
                    echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Multi-language feature is not active on your plan. Additional language configurations have been removed.', WAWP_NOTIF_SLUG) . '</p></div>';
                }
            }
            
            if (isset($_POST['wawp_notif_main_language_code'])) {
                $settings['main_language_code'] = sanitize_text_field($_POST['wawp_notif_main_language_code']);
            }

            if (!empty($_POST['configured_languages']) && is_array($_POST['configured_languages'])) {
                foreach ($_POST['configured_languages'] as $lang => $pdata) {
                    if (!isset($settings['configured_languages'][$lang])) continue;

                    $clean_rules = [];
                    
                    if ( isset( $pdata['notifications'] ) && is_array( $pdata['notifications'] ) ) {
            uasort(
                $pdata['notifications'],
                function ( $a, $b ) {
                    return intval( $a['sort_order'] ?? 0 ) <=> intval( $b['sort_order'] ?? 0 );
                }
            );
        }
                    if (!empty($pdata['notifications']) && is_array($pdata['notifications'])) {
                foreach ($pdata['notifications'] as $prule_idx => $prule) {

                    $original_rule = null;
                    if (isset($settings['configured_languages'][$lang]['notifications'][$prule_idx]) && $settings['configured_languages'][$lang]['notifications'][$prule_idx]['id'] === $prule['id']) {
               $original_rule = $settings['configured_languages'][$lang]['notifications'][$prule_idx];
                    }
                    $is_enabled = !empty($prule['enabled']);

                    if ($is_enabled) {
               if (!$is_enabled) {
                   $this->clear_crons_for_rule($prule['id']);
               }
               $sender_type = sanitize_text_field($prule['sender_type'] ?? 'user_whatsapp');
               $admin_ids_csv = implode(',', array_unique(array_map('intval', $prule['admin_user_ids'] ?? [])));
               $billing_countries_whitelist = implode(',', array_map('sanitize_text_field', $prule['billing_countries_whitelist'] ?? []));
               $billing_countries_blocklist = implode(',', array_map('sanitize_text_field', $prule['billing_countries_blocklist'] ?? []));
               $payment_gateways_csv = implode(',', array_map('sanitize_text_field', $prule['payment_gateways'] ?? []));
               $product_ids_whitelist = implode(',', array_map('intval', $prule['product_ids_whitelist'] ?? []));
               $product_ids_blocklist = implode(',', array_map('intval', $prule['product_ids_blocklist'] ?? []));

               $wh_user = in_array($sender_type, ['user_whatsapp', 'user_both', 'user_admin_both', 'user_admin_whatsapp'], true);
               $em_user = in_array($sender_type, ['user_email', 'user_both', 'user_admin_both', 'user_admin_email'], true);
               $wh_admin = in_array($sender_type, ['admin_whatsapp', 'admin_both', 'user_admin_both', 'user_admin_whatsapp'], true);
               $em_admin = in_array($sender_type, ['admin_email', 'admin_both', 'user_admin_both', 'user_admin_email'], true);

               $clean_rules[] = [
                   'id' => sanitize_text_field($prule['id']), 'enabled' => 1, 'trigger_key' => sanitize_text_field($prule['trigger_key'] ?? 'user_login'),
                   'sender_type' => $sender_type, 'whatsapp_enabled' => $wh_user ? 1 : 0, 'email_enabled' => $em_user ? 1 : 0,
                   'admin_whatsapp_enabled' => $wh_admin ? 1 : 0, 'admin_email_enabled' => $em_admin ? 1 : 0,
                   'country_filter_enabled' => !empty($prule['country_filter_enabled']) ? 1 : 0,
                   'product_filter_enabled' => !empty($prule['product_filter_enabled']) ? 1 : 0,
                   'payment_filter_enabled' => !empty($prule['payment_filter_enabled']) ? 1 : 0,
                   'whatsapp_message' => sanitize_textarea_field($prule['whatsapp_message'] ?? ''),
                   'whatsapp_media_url' => esc_url_raw($prule['whatsapp_media_url'] ?? ''),
                   'email_subject' => sanitize_text_field($prule['email_subject'] ?? ''), 'email_body' => wp_kses_post($prule['email_body'] ?? ''),
                   'admin_user_ids' => $admin_ids_csv, 'admin_whatsapp_message' => sanitize_textarea_field($prule['admin_whatsapp_message'] ?? ''),
                   'admin_whatsapp_media_url' => esc_url_raw($prule['admin_whatsapp_media_url'] ?? ''),
                   'admin_email_subject' => sanitize_text_field($prule['admin_email_subject'] ?? ''),
                   'admin_email_body' => wp_kses_post($prule['admin_email_body'] ?? ''),
                   'billing_countries_whitelist' => $billing_countries_whitelist, 'billing_countries_blocklist' => $billing_countries_blocklist,
                   'payment_gateways' => $payment_gateways_csv, 'product_ids_whitelist' => $product_ids_whitelist,
                   'product_ids_blocklist' => $product_ids_blocklist, 'send_product_image' => !empty($prule['send_product_image']) ? 1 : 0,
                   'send_timing' => sanitize_text_field($prule['send_timing'] ?? 'instant'),
                   'delay_value' => intval($prule['delay_value'] ?? 1), 'delay_unit' => sanitize_text_field($prule['delay_unit'] ?? 'minutes'),
               ];
                    } else {
               if ($original_rule) {
                   $preserved_rule = $original_rule; 
                   $preserved_rule['enabled'] = 0;   
                   $clean_rules[] = $preserved_rule;
               }
                    }
                }
                    }
                    $settings['configured_languages'][$lang]['notifications'] = $clean_rules;
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All settings and rules saved.', WAWP_NOTIF_SLUG) . '</p></div>';
        }
        
        $this->sync_rules_to_db($settings);
        
        global $wpdb;
        $current_langs_in_db = $wpdb->get_col("SELECT language_code FROM {$wpdb->prefix}awp_notif_languages");
        $current_langs_in_settings = array_keys($settings['configured_languages']);
        foreach ($settings['configured_languages'] as $code => $data) {
            $this->awp_db_manager->upsert_language($code, $data['name'], $code === $settings['main_language_code']);
        }
        $langs_to_delete = array_diff($current_langs_in_db, $current_langs_in_settings);
        if (!empty($langs_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($langs_to_delete), '%s'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}awp_notif_languages WHERE language_code IN ($placeholders)", $langs_to_delete));
        }
    }

    private function get_default_rule()
    {
        return [
            'id'                       => $this->generate_unique_id(),
            'enabled'                  => true,
            'trigger_key'              => 'user_login',
            'sender_type'              => 'user_whatsapp',

            'country_filter_enabled'   => 0,
            'product_filter_enabled'   => 0,
            'payment_filter_enabled'   => 0,

            'whatsapp_enabled'         => 1,
            'whatsapp_message'         => "Hello {display_name}, welcome to {site_title}!",
            'whatsapp_media_url'       => '',
            'email_enabled'            => 0,
            'email_subject'            => "Notification from {site_title}",
            'email_body'               => "Hello {display_name},\n\nThis is a notification regarding your account on {site_title}.",
            'admin_user_ids'           => '',
            'admin_whatsapp_enabled'   => 0,
            'admin_whatsapp_message'   => "Hello {admin_display_name},\n\nYou have a new {site_title} notification for user {display_name}.",
            'admin_whatsapp_media_url' => '',
            'admin_email_enabled'      => 0,
            'admin_email_subject'      => "Admin Copy: {site_title} Notification",
            'admin_email_body'         => "Hello {admin_display_name},<br><br>A new {site_title} notification has been triggered for user {display_name}.",
            'billing_countries_whitelist' => '',
            'billing_countries_blocklist' => '',
            'payment_gateways'         => '',
            'product_ids_whitelist'    => '',
            'product_ids_blocklist'    => '',
            'send_product_image'       => 0,
            'send_timing'              => 'instant',
            'delay_value'              => 1,
            'delay_unit'               => 'minutes',
        ];
    }

    private function get_settings()
    {

        if (empty($this->awp_db_manager)) {
            return get_option(
                WAWP_NOTIF_OPTION_SETTINGS,
                [
                    'selected_instance_ids' => '',
                    'main_language_code'    => get_option('WPLANG') ?: 'en_US',
                    'configured_languages'  => [],
                ]
            );
        }

        $global = $this->awp_db_manager->get_notif_global();

        $settings = [
            'selected_instance_ids' => $global['selected_instance_ids'] ?? '',
            'main_language_code'    => '',
            'configured_languages'  => [],
        ];

        $lang_rows = $this->awp_db_manager->get_languages();

        if (empty($lang_rows)) {
            $ml  = get_option('WPLANG') ?: 'en_US';
            $det = $this->get_language_details($ml);
            $nm  = $det ? $det['native_name'] . ' (' . $det['english_name'] . ')' : $ml;
            $this->awp_db_manager->upsert_language($ml, $nm, 1);
            $lang_rows = $this->awp_db_manager->get_languages();
        }

        foreach ($lang_rows as $row) {
            $settings['configured_languages'][$row['code']] = [
                'name'          => $row['name'],
                'notifications' => [],
            ];
            if ((int) $row['is_main'] === 1) {
                $settings['main_language_code'] = $row['code'];
            }
        }

        if (empty($settings['main_language_code'])) {
            $settings['main_language_code'] =
                array_key_first($settings['configured_languages']);
        }

        global $wpdb;
        $tbl_rules = $wpdb->prefix . WAWP_NOTIF_RULES_TABLE_NAME;
        $rules     = $wpdb->get_results("SELECT * FROM {$tbl_rules}", ARRAY_A);

        foreach ($rules as $r) {
            if (isset($settings['configured_languages'][$r['language_code']])) {
                $settings['configured_languages'][$r['language_code']]['notifications'][] = $r;
            }
        }

        foreach ($settings['configured_languages'] as $lang_code => &$lang_data) {

            if (empty($lang_data['notifications'])) {
                $lang_data['notifications'] = [];
            }

            foreach ($lang_data['notifications'] as &$rule) {

                if (empty($rule['id'])) {
                    $rule['id'] = $this->generate_unique_id();
                }

                if (!isset($rule['enabled'])) {
                    $rule['enabled'] = 1;
                }

                if (empty($rule['sender_type'])) {
                    $rule['sender_type'] = 'user_whatsapp';
                }

                $rule['whatsapp_enabled'] =
                    in_array($rule['sender_type'], ['user_whatsapp', 'user_both', 'user_admin_both'], true) ? 1 : 0;
                $rule['email_enabled'] =
                    in_array($rule['sender_type'], ['user_email', 'user_both', 'user_admin_both'], true) ? 1 : 0;

                if (!isset($rule['admin_user_ids'])) {
                    $rule['admin_user_ids'] = '';
                }

                $rule['admin_whatsapp_enabled'] =
                    in_array($rule['sender_type'], ['admin_whatsapp', 'admin_both', 'user_admin_both'], true) ? 1 : 0;
                $rule['admin_email_enabled'] =
                    in_array($rule['sender_type'], ['admin_email', 'admin_both', 'user_admin_both'], true) ? 1 : 0;

                foreach (['billing_countries', 'payment_gateways'] as $f) {
                    if (!isset($rule[$f])) {
                $rule[$f] = '';
                    }
                }

                $rule['send_timing'] = $rule['send_timing'] ?? 'instant';
                $rule['delay_value'] = $rule['delay_value'] ?? 1;
                $rule['delay_unit']  = $rule['delay_unit']  ?? 'minutes';

                foreach ([
                    'whatsapp_message', 'whatsapp_media_url',
                    'email_subject', 'email_body',
                    'admin_whatsapp_message', 'admin_whatsapp_media_url',
                    'admin_email_subject', 'admin_email_body'
                ] as $msgF) {
                    if (!isset($rule[$msgF])) {
                $rule[$msgF] = '';
                    }
                }

                foreach (['country_filter_enabled', 'product_filter_enabled', 'payment_filter_enabled'] as $flag) {
                    if (!isset($rule[$flag])) {
                $rule[$flag] = 0;
                    }
                }

                if (!isset($rule['send_product_image'])) {
                    $rule['send_product_image'] = 0;
                }
            }
            unset($rule);
        }
        unset($lang_data);

        return $settings;
    }

    private function sync_rules_to_db($all_settings)
    {
        global $wpdb;
        $table_name   = $wpdb->prefix . WAWP_NOTIF_RULES_TABLE_NAME;
        $existing_ids = $wpdb->get_col("SELECT rule_internal_id FROM {$table_name}");

        $current_ids  = [];

        if (!empty($all_settings['configured_languages']) && is_array($all_settings['configured_languages'])) {
            foreach ($all_settings['configured_languages'] as $lang_code => $lang_data) {
                if (!empty($lang_data['notifications']) && is_array($lang_data['notifications'])) {
                    foreach ($lang_data['notifications'] as $rule) {
                if (empty($rule['id'])) {
                    continue;
                }
                $current_ids[] = $rule['id'];
                $data = [
                    'language_code'          => $lang_code,
                    'trigger_key'            => $rule['trigger_key'],
                    'enabled'                => intval($rule['enabled']),
                    'whatsapp_enabled'       => intval($rule['whatsapp_enabled']),

                    'sender_type'    => $rule['sender_type'] ?? 'user_whatsapp',
                    'whatsapp_message'       => $rule['whatsapp_message'] ?? '',
                    'whatsapp_media_url'     => $rule['whatsapp_media_url'] ?? '',
                    'email_enabled'          => intval($rule['email_enabled']),
                    'email_subject'          => $rule['email_subject'] ?? '',
                    'email_body'             => $rule['email_body'] ?? '',

                    'admin_whatsapp_enabled' => intval($rule['admin_whatsapp_enabled']),
                    'admin_whatsapp_message' => $rule['admin_whatsapp_message'] ?? '',
                    'admin_whatsapp_media_url' => $rule['admin_whatsapp_media_url'] ?? '',
                    'admin_email_enabled'    => intval($rule['admin_email_enabled']),
                    'admin_email_subject'    => $rule['admin_email_subject'] ?? '',
                    'admin_email_body'       => $rule['admin_email_body'] ?? '',

                    'admin_user_ids'         => sanitize_text_field($rule['admin_user_ids'] ?? ''),


                    'country_filter_enabled' => intval($rule['country_filter_enabled'] ?? 0),
                    'product_filter_enabled' => intval($rule['product_filter_enabled'] ?? 0),
                    'payment_filter_enabled' => intval($rule['payment_filter_enabled'] ?? 0),

                    'billing_countries'         => sanitize_text_field($rule['billing_countries'] ?? ''),
                    'payment_gateways'       => sanitize_text_field($rule['payment_gateways'] ?? ''),
                    'billing_countries_whitelist' => sanitize_text_field($rule['billing_countries_whitelist'] ?? ''),
                    'billing_countries_blocklist' => sanitize_text_field($rule['billing_countries_blocklist'] ?? ''),
                    'product_ids_whitelist'       => sanitize_text_field($rule['product_ids_whitelist'] ?? ''),
                    'product_ids_blocklist'       => sanitize_text_field($rule['product_ids_blocklist'] ?? ''),
                    'send_product_image'       => intval($rule['send_product_image'] ?? 0),

                    'send_timing'            => $rule['send_timing'] ?? 'instant',
                    'delay_value'            => intval($rule['delay_value'] ?? 1),
                    'delay_unit'             => $rule['delay_unit'] ?? 'minutes',
                    'last_updated'           => current_time('mysql'),
                ];

                $where  = ['rule_internal_id' => $rule['id']];
                $exists = $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE rule_internal_id = %s", $rule['id'])
                );

                if ($exists) {
                    $wpdb->update($table_name, $data, $where);
                } else {
                    $data['rule_internal_id'] = $rule['id'];
                    $wpdb->insert($table_name, $data);
                }
                    }
                }
            }
        }
        $to_delete = array_diff($existing_ids, $current_ids);
        if (!empty($to_delete)) {
            foreach ($to_delete as $rid) {
                $wpdb->delete($table_name, ['rule_internal_id' => $rid]);
            }
        }
    }
    
    private function is_subscription_notifs_active() {
    $user_data = get_transient('siteB_user_data');
 
    if (empty($user_data)) {
        return false;
    }
    
    return !empty($user_data['subscription_notifications']);
}

    public function get_available_triggers()
    {
        $triggers = [
            'user_login'  => [
                'label'     => __('User Login', WAWP_NOTIF_SLUG),
                'icon_file' => 'wordpress.svg',
            ],
            'user_signup' => [
                'label'     => __('User Signup', WAWP_NOTIF_SLUG),
                'icon_file' => 'wordpress.svg',
            ],
        ];

        if (class_exists('WooCommerce')) {
            foreach (wc_get_order_statuses() as $slug => $status_label) {
                $key = 'wc_status_' . str_replace('wc-', '', $slug);
                $triggers[$key] = [
                    'label'     => sprintf(
                __('Order Status: %s', WAWP_NOTIF_SLUG),
                $status_label
                    ),
                    'icon_file' => 'woocommerce.svg',
                ];
            }
            $triggers['wc_order_note_added'] = [
                'label'     => __('Order: Note to Customer Added', WAWP_NOTIF_SLUG),
                'icon_file' => 'woocommerce.svg',
            ];

    
            if (class_exists('WC_Subscriptions') && $this->is_subscription_notifs_active()) {
                if (function_exists('wcs_get_subscription_statuses')) {
                    foreach (wcs_get_subscription_statuses() as $slug => $status_label) {
                $key = 'wc_sub_status_' . str_replace('wc-', '', $slug);
                $triggers[$key] = [
                    'label'     => sprintf(
               __('Subscription Status: %s', WAWP_NOTIF_SLUG),
               $status_label
                    ),
                    'icon_file' => 'Woo-Subscriptions.svg',
                ];
                    }
                }

                $triggers['wc_sub_renewal_payment_complete'] = [
                    'label'     => __('Subscription Renewal Payment Complete', WAWP_NOTIF_SLUG),
                    'icon_file' => 'Woo-Subscriptions.svg',
                ];

                $triggers['wc_sub_note_added'] = [
                    'label'     => __('Subscription: Note to Customer Added', WAWP_NOTIF_SLUG),
                    'icon_file' => 'Woo-Subscriptions.svg',
                ];
            }
        }

        return apply_filters('wawp_notif_available_triggers', $triggers);
    }

    private function get_language_details($lang_code)
    {
        if (!function_exists('wp_get_available_translations')) {
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        }
        $trans = wp_get_available_translations();
        if (isset($trans[$lang_code])) {
            return $trans[$lang_code];
        }
        if ($lang_code === 'en_US') {
            return ['language' => 'en_US', 'native_name' => 'English', 'english_name' => 'English (US)'];
        }
        return null;
    }

    private function generate_unique_id($prefix = 'wawp_rule_')
    {
        return uniqid($prefix);
    }

    private function clear_crons_for_rule($rule_id)
    {
        $deleted = 0;
        $crons = _get_cron_array();
        if (empty($crons)) return;
        foreach ($crons as $timestamp => $hooks) {
            if (isset($hooks[WAWP_NOTIF_CRON_HOOK])) {
                foreach ($hooks[WAWP_NOTIF_CRON_HOOK] as $key => $details) {
                    if (!empty($details['args'][0]['rule_internal_id']) && $details['args'][0]['rule_internal_id'] === $rule_id) {
                wp_unschedule_event($timestamp, WAWP_NOTIF_CRON_HOOK, $details['args']);
                $deleted++;
                    }
                }
            }
        }
        if ($deleted > 0) {
            error_log("WAWP NOTIF: Cleared {$deleted} cron job(s) for rule '{$rule_id}'.");
        }
    }

    public function handle_user_login_trigger($user_login, $user)
    {
        if (empty($this->awp_db_manager)) return;
        $settings = $this->get_settings();
        $locale   = get_user_locale($user->ID);
        $rules    = $this->get_matching_rules($settings, $locale, 'user_login');
        if (empty($rules)) return;
        $info = $this->awp_db_manager->get_user_info($user->ID);
        $phone = !empty($info->phone) ? $info->phone : get_user_meta($user->ID, 'billing_phone', true);

        $replacements_user = [
        '{{user_name}}'         => $user->user_login,
        '{{display_name}}'      => $user->display_name,
        '{{user_email}}'        => $user->user_email,
        '{{site_title}}'        => get_bloginfo('name'),
    ];

        $recipient_user = [
        'user_id'      => $user->ID,
        'email'        => $user->user_email,
        'phone'        => $phone,
        'display_name' => $user->display_name,
    ];

        foreach ($rules as $rule) {
            $this->schedule_or_send($rule, $replacements_user, $recipient_user, 'User Login', $user->ID, 'user_login');
        }
    }

    public function handle_user_signup_trigger($user_id)
    {
        
        
        if (empty($this->awp_db_manager)) return;
        $user = get_userdata($user_id);
        if (!$user) return;
        $settings = $this->get_settings();
        $locale   = get_user_locale($user_id);
        $rules    = $this->get_matching_rules($settings, $locale, 'user_signup');
        if (empty($rules)) return;

        $info = $this->awp_db_manager->get_user_info($user_id);
        $phone = !empty($info->phone) ? $info->phone : get_user_meta($user_id, 'billing_phone', true);

        $replacements_user = [
            '{username}'     => $user->user_login,
            '{display_name}' => $user->display_name,
            '{user_email}'   => $user->user_email,
            '{site_title}'   => get_bloginfo('name'),
        ];

        $recipient_user = [
            'user_id'      => $user_id,
            'email'        => $user->user_email,
            'phone'        => $phone,
            'display_name' => $user->display_name,
        ];

        foreach ($rules as $rule) {
            $this->schedule_or_send($rule, $replacements_user, $recipient_user, 'User Signup', $user_id, 'user_signup');
        }
    }

    public function handle_new_customer_note_trigger($note_data)
    {
        if (empty($note_data['order_id']) || empty($note_data['customer_note'])) {
            return;
        }

        $object_id     = $note_data['order_id'];
        $note_content  = $note_data['customer_note'];
        $object        = null;
        $trigger_key   = '';
        $is_subscription = false;
        if (function_exists('wcs_is_subscription') && wcs_is_subscription($object_id)) {
            $is_subscription = true;
            $trigger_key     = 'wc_sub_note_added';
            $object          = wcs_get_subscription($object_id);
        } else {
            $is_subscription = false;
            $trigger_key     = 'wc_order_note_added';
            $object          = wc_get_order($object_id);
        }

        if (!$object) {
            return;
        }

        $settings = $this->get_settings();
        $user_id  = $object->get_customer_id();
        $locale   = $user_id ? get_user_locale($user_id) : ($settings['main_language_code'] ?: 'en_US');
        $rules    = $this->get_matching_rules($settings, $locale, $trigger_key);

        if (empty($rules)) {
            return;
        }

        $first_name = $object->get_billing_first_name();
        $last_name  = $object->get_billing_last_name();
        $customer_name = trim($first_name . ' ' . $last_name);

        if (empty($customer_name) && $user_id) {
            $user_info = get_userdata($user_id);
            if ($user_info) {
                $customer_name = $user_info->display_name;
            }
        }
        $customer_email = $object->get_billing_email();
        $customer_phone = $object->get_billing_phone();
        $replacements = [
            '{customer_name}'   => $customer_name,
            '{user_email}'      => $customer_email,
            '{site_title}'      => get_bloginfo('name'),
            '{customer_note}'   => wp_strip_all_tags($note_content),
        ];

        $log_base = '';
        if ($is_subscription) {
            $replacements['{subscription_id}']    = $object->get_id();
            $replacements['{subscription_total}'] = $object->get_formatted_order_total();
            $log_base = sprintf('Subscription Note Added (#%s)', $object->get_id());
        } else {
            $replacements['{order_id}']    = $object->get_id();
            $replacements['{order_total}'] = $object->get_formatted_order_total();
            $log_base = sprintf('Order Note Added (#%s)', $object->get_id());
        }

        $recipient_user = [
            'user_id'      => $user_id,
            'email'        => $customer_email,
            'phone'        => $customer_phone,
            'display_name' => $customer_name,
        ];
        foreach ($rules as $rule) {
            $this->schedule_or_send($rule, $replacements, $recipient_user, $object_id, $trigger_key);
        }
    }

    public function handle_order_status_change($order_id, $old_status, $new_status, $order)
    {

        if (empty($this->awp_db_manager)) {
            return;
        }

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
        }

        $settings    = $this->get_settings();
        $user_id     = $order->get_customer_id();
        $locale      = $user_id
            ? get_user_locale($user_id)
            : ($settings['main_language_code'] ?: get_option('WPLANG') ?: 'en_US');

        $trigger_key = 'wc_status_' . $new_status;
        $rules       = $this->get_matching_rules($settings, $locale, $trigger_key);
        if (empty($rules)) {
            return;
        }

        $billing_country = $order->get_billing_country();
        $payment_method  = $order->get_payment_method();
        $billing_email   = $order->get_billing_email();
        $billing_phone   = $order->get_billing_phone();

        if (empty($billing_phone) && $user_id) {
            $info          = $this->awp_db_manager->get_user_info($user_id);
            $billing_phone = !empty($info->phone) ? $info->phone : '';
        }

        $all_statuses  = wc_get_order_statuses();
        $new_label     = $all_statuses['wc-' . $new_status] ?? $new_status;
        $old_label     = $all_statuses['wc-' . $old_status] ?? $old_status;

        $customer_name = $order->get_formatted_billing_full_name()
            ?: ($user_id ? get_userdata($user_id)->display_name : __('Customer', WAWP_NOTIF_SLUG));

        $replacements_user = [
            '{order_id}'               => $order_id,
            '{customer_name}'          => $customer_name,
            '{billing_first_name}'     => $order->get_billing_first_name(),
            '{billing_last_name}'      => $order->get_billing_last_name(),
            '{order_total}'            => $order->get_formatted_order_total(),
            '{site_title}'             => get_bloginfo('name'),
            '{user_email}'             => $billing_email,
            '{order_status_label}'     => $new_label,
            '{old_order_status_label}' => $old_label,
        ];

        $recipient_user = [
            'user_id'      => $user_id,
            'email'        => $billing_email,
            'phone'        => $billing_phone,
            'display_name' => $customer_name,
        ];

        $log_base = sprintf('Order Status %s - ', $new_label);

        $order_product_ids = [];
        foreach ($order->get_items() as $item) {
            $pid = (int) $item->get_product_id();
            if ($pid) {
                $order_product_ids[] = $pid;
            }
        }
        $order_product_ids = array_unique($order_product_ids);
        foreach ($rules as $rule) {

            if (!empty($rule['send_product_image']) && !empty($order_product_ids)) {
                $first_product_id = $order_product_ids[0];
                $image_id = get_post_thumbnail_id($first_product_id);
                if ($image_id) {
                    $image_url = wp_get_attachment_url($image_id);
                    if ($image_url) {
                $rule['whatsapp_media_url'] = $image_url;
                $rule['admin_whatsapp_media_url'] = $image_url;
                    }
                }
            }


            $sender_type    = $rule['sender_type'] ?? 'user_whatsapp';

            $user_part_present  = str_contains($sender_type, 'user');   
            $admin_part_present = str_contains($sender_type, 'admin');

            $filters_pass = true;

            if ($user_part_present && !empty($rule['country_filter_enabled'])) {
                $allow = array_filter(explode(',', $rule['billing_countries_whitelist'] ?? ''));
                $block = array_filter(explode(',', $rule['billing_countries_blocklist'] ?? ''));
                if (($allow && !in_array($billing_country, $allow, true)) ||
                    ($block &&   in_array($billing_country, $block, true))
                ) {
                    $filters_pass = false;
                }
            }

            if ($filters_pass && $user_part_present && !empty($rule['product_filter_enabled'])) {
                $allowP = array_filter(array_map(
                    'intval',
                    explode(',', $rule['product_ids_whitelist'] ?? '')
                ));
                $blockP = array_filter(array_map(
                    'intval',
                    explode(',', $rule['product_ids_blocklist'] ?? '')
                ));
                if ($allowP && !array_intersect($order_product_ids, $allowP)) {
                    $filters_pass = false;
                }
                if ($blockP && array_intersect($order_product_ids, $blockP)) {
                    $filters_pass = false;
                }
            }

            if ($filters_pass && $user_part_present && !empty($rule['payment_filter_enabled'])) {
                $gateways = array_filter(explode(',', $rule['payment_gateways'] ?? ''));
                if ($gateways && !in_array($payment_method, $gateways, true)) {
                    $filters_pass = false;
                }
            }

            if (!$filters_pass) {
                if (!$admin_part_present) {
                    continue;
                }


                $rule = $rule;
                if ($sender_type === 'user_admin_whatsapp') {
                    $rule['sender_type'] = 'admin_whatsapp';
                } elseif ($sender_type === 'user_admin_email') {
                    $rule['sender_type'] = 'admin_email';
                } else {
                    $rule['sender_type'] = 'admin_both';
                }
            }

            $this->schedule_or_send(
                $rule,
                $replacements_user,
                $recipient_user,
                $log_base,
                $order_id,
                $trigger_key
            );
        }
    }

    public function ajax_search_products() {

    $q = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';

    $out = [];

    if ( $q !== '' ) {
        $products = wc_get_products( [
            'status'  => 'publish',   
            'limit'   => 20,          
            'search'  => $q,         
            'return'  => 'ids',
        ] );

        foreach ( $products as $pid ) {
            if ( $title = get_the_title( $pid ) ) {
                $out[] = [
                    'id'   => $pid,
                    'text' => sprintf( '%s (ID:%d)', $title, $pid ),
                ];
            }
        }
    }

    wp_send_json( [ 'results' => $out ] );
}

    public function handle_subscription_status_updated($subscription, $new_status, $old_status)
    {
        if (empty($this->awp_db_manager) || !class_exists('WC_Subscription') || !($subscription instanceof WC_Subscription)) return;

        $settings   = $this->get_settings();
        $user_id    = $subscription->get_customer_id();
        $locale     = $user_id
            ? get_user_locale($user_id)
            : ($settings['main_language_code'] ?: get_option('WPLANG') ?: 'en_US');
        $trigger_key = 'wc_sub_status_' . $new_status;
        $rules     = $this->get_matching_rules($settings, $locale, $trigger_key);
        if (empty($rules)) return;

        $billing_country = $subscription->get_billing_country();
        $billing_phone   = $subscription->get_billing_phone();
        if (empty($billing_phone) && $user_id) {
            $info = $this->awp_db_manager->get_user_info($user_id);
            $billing_phone = !empty($info->phone) ? $info->phone : '';
        }
        $customer_email  = $subscription->get_billing_email();
        $all_statuses    = function_exists('wcs_get_subscription_statuses') ? wcs_get_subscription_statuses() : [];
        $new_label       = $all_statuses['wc-' . $new_status] ?? $new_status;
        $old_label       = $all_statuses['wc-' . $old_status] ?? $old_status;
        $customer_name   = $subscription->get_formatted_billing_full_name();
        if (empty($customer_name) && $user_id) {
            $u = get_userdata($user_id);
            $customer_name = $u ? $u->display_name : __('Customer', WAWP_NOTIF_SLUG);
        } elseif (empty($customer_name)) {
            $customer_name = __('Customer', WAWP_NOTIF_SLUG);
        }

        $replacements_user = [
            '{subscription_id}'               => $subscription->get_id(),
            '{customer_id}'                   => $user_id,
            '{customer_name}'                 => $customer_name,
            '{customer_email}'                => $customer_email,
            '{user_email}'                    => $customer_email,
            '{subscription_status_label}'     => $new_label,
            '{old_subscription_status_label}' => $old_label,
            '{subscription_total}'            => $subscription->get_formatted_order_total(),
            '{next_payment_date}'             => $subscription->get_date_to_display('next_payment'),
            '{site_title}'                    => get_bloginfo('name'),
        ];

        $recipient_user = [
            'user_id'      => $user_id,
            'email'        => $customer_email,
            'phone'        => $billing_phone,
            'display_name' => $customer_name,
        ];

        $log_base = sprintf('Subscription #%s Status %s - ', $subscription->get_id(), $new_label);

        foreach ($rules as $rule) {
            $this->schedule_or_send($rule, $replacements_user, $recipient_user, $log_base, $subscription->get_id(), $trigger_key);
        }
    }

    public function handle_subscription_renewal_complete($subscription, $last_order)
    {
        if (empty($this->awp_db_manager) || !class_exists('WC_Subscription') || !($subscription instanceof WC_Subscription) || !($last_order instanceof WC_Order)) return;

        $settings    = $this->get_settings();
        $user_id     = $subscription->get_customer_id();
        $locale      = $user_id
            ? get_user_locale($user_id)
            : ($settings['main_language_code'] ?: get_option('WPLANG') ?: 'en_US');
        $trigger_key = 'wc_sub_renewal_payment_complete';
        $rules       = $this->get_matching_rules($settings, $locale, $trigger_key);
        if (empty($rules)) return;

        $billing_phone   = $subscription->get_billing_phone();
        if (empty($billing_phone) && $user_id) {
            $info = $this->awp_db_manager->get_user_info($user_id);
            $billing_phone = !empty($info->phone) ? $info->phone : '';
        }
        $customer_name   = $subscription->get_formatted_billing_full_name();
        if (empty($customer_name) && $user_id) {
            $u = get_userdata($user_id);
            $customer_name = $u ? $u->display_name : __('Customer', WAWP_NOTIF_SLUG);
        } elseif (empty($customer_name)) {
            $customer_name = __('Customer', WAWP_NOTIF_SLUG);
        }
        $customer_email = $subscription->get_billing_email();

        $replacements_user = [
            '{subscription_id}'       => $subscription->get_id(),
            '{renewal_order_id}'      => $last_order->get_id(),
            '{customer_id}'           => $user_id,
            '{customer_name}'         => $customer_name,
            '{customer_email}'        => $customer_email,
            '{user_email}'            => $customer_email,
            '{renewal_amount}'        => $last_order->get_formatted_order_total(),
            '{subscription_total}'    => $subscription->get_formatted_order_total(),
            '{next_payment_date}'     => $subscription->get_date_to_display('next_payment'),
            '{site_title}'            => get_bloginfo('name'),
        ];

        $recipient_user = [
            'user_id'      => $user_id,
            'email'        => $customer_email,
            'phone'        => $billing_phone,
            'display_name' => $customer_name,
        ];

        $log_base = sprintf('Subscription #%s Renewal (Order #%s)', $subscription->get_id(), $last_order->get_id());

        foreach ($rules as $rule) {
            $this->schedule_or_send($rule, $replacements_user, $recipient_user, $log_base, $last_order->get_id(), $trigger_key);
        }
    }

    private function get_matching_rules($settings, $user_locale, $trigger_key)
    {
        $main_lang = $settings['main_language_code'] ?? 'en_US';
        $to_check  = [];
        if (isset($settings['configured_languages'][$user_locale])) {
            $to_check[] = $user_locale;
        }
        if ($user_locale !== $main_lang && isset($settings['configured_languages'][$main_lang])) {
            $to_check[] = $main_lang;
        }
        if (empty($to_check) && isset($settings['configured_languages'][$main_lang])) {
            $to_check[] = $main_lang;
        }

        $matches = [];
        $seen_ids  = [];
        foreach ($to_check as $lang) {
            if (!empty($settings['configured_languages'][$lang]['notifications'])) {
                foreach ($settings['configured_languages'][$lang]['notifications'] as $rule) {
                    if (!$rule['enabled']) {
                continue;
                    }
                    if (isset($rule['trigger_key']) && $rule['trigger_key'] === $trigger_key) {
                $st = $rule['sender_type'] ?? 'none';
                if (in_array($st, [
                'user_whatsapp', 'admin_whatsapp',
                'user_email', 'admin_email',
                'user_both', 'admin_both',
                'user_admin_whatsapp',
                'user_admin_email',  
                'user_admin_both'
                    ])) {
                    if (isset($rule['id']) && isset($seen_ids[$rule['id']])) {
               continue;    
                    }
                    $seen_ids[$rule['id']] = true;
                    $matches[] = $rule;
                }
                    }
                }
            }
            if (!empty($matches) && $lang === $user_locale) {
                break;
            }
        }
        return $matches;
    }

private function schedule_or_send($rule, $replacements_user, $recipient_user, $log_base, $related_id = null, $trigger_key = '')
{

    static $phones_sent = [];
    static $emails_sent = [];

    $opted_out = apply_filters('wawp_notif_before_send', true, $rule, $recipient_user) === false;

    if ($opted_out) {

        static $suppressed_logged = [];              

        $log_key = $recipient_user['user_id'] . '|'
            . ($rule['trigger_key'] ?? 'unknown'); 

        if (empty($suppressed_logged[$log_key])) {

            if (!empty($this->awp_log_manager) && !empty($recipient_user['user_id'])) {

                $this->awp_log_manager->log_notification([
                    'user_id'         => $recipient_user['user_id'],
                    'order_id'        => $related_id,
                    'customer_name'   => $recipient_user['display_name'],
                    'sent_at'         => current_time('mysql'),
                    'whatsapp_number' => $recipient_user['phone'],
                    'message'         => '',
                    'image_attachment' => '',
                    'message_type'    => 'Suppressed (user opt-out)',
                    'wawp_status'     => ['status' => 'info', 'message' => 'disabled by user'],
                    'resend_id'       => null,
                ]);
            }

            $suppressed_logged[$log_key] = true;  
        }

        switch ($rule['sender_type'] ?? 'user_whatsapp') {
            case 'user_whatsapp':
            case 'user_email':
            case 'user_both':
                return;

            case 'user_admin_whatsapp':
                $rule['sender_type'] = 'admin_whatsapp';
                break;

            case 'user_admin_email':
                $rule['sender_type'] = 'admin_email';
                break;

            case 'user_admin_both':
            default:
                $rule['sender_type'] = 'admin_both';
        }
    }

    if (($rule['send_timing'] ?? 'instant') === 'delayed') {

        $seconds = (int) ($rule['delay_value'] ?? 1) *
            (
                $rule['delay_unit'] === 'hours'
                ? HOUR_IN_SECONDS
                : (
                    $rule['delay_unit'] === 'days'
                    ? DAY_IN_SECONDS
                    : MINUTE_IN_SECONDS
                )
            );

        // build stable args
        $___cron_payload = array_merge(
            $rule,
            [
                'related_object_id'     => $related_id,
                'recipient_user'        => [
                    'user_id'      => $recipient_user['user_id'] ?? 0,
                    'email'        => $recipient_user['email'] ?? '',
                    'phone'        => $recipient_user['phone'] ?? '',
                    'display_name' => $recipient_user['display_name'] ?? '',
                ],
                'replacements_user'     => $replacements_user,
                'log_message_type_base' => $log_base,
            ]
        );
        $___cron_args = [ $___cron_payload ];
        $___ts        = time() + $seconds;

        // CHANGED: short window dedupe only (60s bucket)
        $___trigger = $trigger_key ?: ($rule['trigger_key'] ?? 'unknown');
        $___bucket  = (int) floor(time() / 60); // 1-minute bucket
        $___sched_key = 'wawp_notif_sched_' . md5(serialize($___cron_args) . '|' . $___trigger . '|' . $___bucket);

        if (wp_next_scheduled(WAWP_NOTIF_CRON_HOOK, $___cron_args)) {
            return;
        }
        if (get_transient($___sched_key)) {
            return;
        }
        set_transient($___sched_key, 1, 90); // CHANGED: 90s, not delay+15min

        wp_schedule_single_event(
            time() + $seconds,
            WAWP_NOTIF_CRON_HOOK,
            [
                array_merge(
                    $rule,
                    [
                        'related_object_id'     => $related_id,
                        'recipient_user'        => $recipient_user,
                        'replacements_user'     => $replacements_user,
                        'log_message_type_base' => $log_base,
                    ]
                ),
            ]
        );

        // keep latest only (best-effort)
        $___ts_old = wp_next_scheduled(WAWP_NOTIF_CRON_HOOK, $___cron_args);
        while ($___ts_old && $___ts_old < $___ts) {
            wp_unschedule_event($___ts_old, WAWP_NOTIF_CRON_HOOK, $___cron_args);
            $___ts_old = wp_next_scheduled(WAWP_NOTIF_CRON_HOOK, $___cron_args);
        }

        return;
    }

    $st = $rule['sender_type'] ?? 'user_whatsapp';
    $user_wh_active  = in_array($st, ['user_whatsapp', 'user_both', 'user_admin_both', 'user_admin_whatsapp'], true);
    $user_em_active  = in_array($st, ['user_email', 'user_both', 'user_admin_both', 'user_admin_email'], true);
    $admin_wh_active = in_array($st, ['admin_whatsapp', 'admin_both', 'user_admin_both', 'user_admin_whatsapp'], true);
    $admin_em_active = in_array($st, ['admin_email', 'admin_both', 'user_admin_both', 'user_admin_email'], true);

    // ---------- User WhatsApp ----------
    if (
        $user_wh_active &&
        !empty($rule['whatsapp_message']) &&
        !empty($recipient_user['phone']) &&
        !isset($phones_sent[$recipient_user['phone']])
    ) {

        $msg = $this->parse_template(
            $rule['whatsapp_message'],
            $replacements_user,   
            $related_id,    
            $recipient_user['user_id'] ?? null
        );

        // CHANGED: lock is only for ~1 minute and includes a time bucket and trigger
        $___trigger = $trigger_key ?: ($rule['trigger_key'] ?? 'unknown');
        $___bucket  = (int) floor(time() / 60);
        $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
            (string)($rule['rule_internal_id'] ?? $rule['id'] ?? 'noid'),
            'user_whatsapp',
            (string)($recipient_user['phone'] ?? ''),
            (string)($related_id ?? ''),
            $___trigger,
            $___bucket
        ]));
        if (!get_transient($___lock_key)) {
            set_transient($___lock_key, 1, 90); // CHANGED: 90s

            $this->send_whatsapp(
                $recipient_user['user_id'],
                $recipient_user['display_name'],
                $recipient_user['phone'],
                $msg,
                $rule['whatsapp_media_url'],
                $log_base . ' (User)',
                $related_id
            );

            $phones_sent[$recipient_user['phone']] = true;
        }
    }

    // ---------- User Email ----------
    if (
        $user_em_active &&
        !empty($rule['email_subject']) &&
        !empty($rule['email_body']) &&
        !empty($recipient_user['email']) &&
        !isset($emails_sent[$recipient_user['email']])
    ) {

        $subj = $this->parse_template(
            $rule['email_subject'],
            $replacements_user,
            $related_id,
            $recipient_user['user_id'] ?? null
        );
        $body = $this->parse_template(
            $rule['email_body'],
            $replacements_user,
            $related_id,
            $recipient_user['user_id'] ?? null
        );

        $___trigger = $trigger_key ?: ($rule['trigger_key'] ?? 'unknown');
        $___bucket  = (int) floor(time() / 60);
        $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
            (string)($rule['rule_internal_id'] ?? $rule['id'] ?? 'noid'),
            'user_email',
            (string)($recipient_user['email'] ?? ''),
            (string)($related_id ?? ''),
            $___trigger,
            $___bucket
        ]));
        if (!get_transient($___lock_key)) {
            set_transient($___lock_key, 1, 90); // CHANGED

            $this->send_email(
                $recipient_user['user_id'] ?? 0,    
                $recipient_user['email'],              
                $subj,               
                $body,                      
                $log_base . '',          
                $related_id                 
            );
            $emails_sent[$recipient_user['email']] = true;
        }
    }

    // ---------- Admins ----------
    if (($admin_wh_active || $admin_em_active) && !empty($rule['admin_user_ids'])) {

        $admin_ids = array_unique(
            array_diff(
                array_filter(array_map('intval', explode(',', $rule['admin_user_ids']))),
                [$recipient_user['user_id']]    
            )
        );

        foreach ($admin_ids as $admin_uid) {
            $admin = get_user_by('ID', $admin_uid);
            if (!$admin) {
                continue;
            }

            $admin_info  = $this->awp_db_manager->get_user_info($admin->ID);
            $admin_phone = $admin_info && $admin_info->phone
                ? $admin_info->phone
                : get_user_meta($admin->ID, 'billing_phone', true);
            $admin_email = $admin->user_email;
            $admin_name  = $admin->display_name;

            $rep_admin = array_merge($replacements_user, ['{admin_display_name}' => $admin_name]);

            // Admin WhatsApp
            if (
                $admin_wh_active &&
                !empty($rule['admin_whatsapp_message']) &&
                $admin_phone &&
                !isset($phones_sent[$admin_phone])
            ) {

                $msg_admin = $this->parse_template(
                    $rule['admin_whatsapp_message'],
                    $rep_admin,
                    $related_id,
                    $recipient_user['user_id'] ?? null
                );

                $___trigger = $trigger_key ?: ($rule['trigger_key'] ?? 'unknown');
                $___bucket  = (int) floor(time() / 60);
                $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
                    (string)($rule['rule_internal_id'] ?? $rule['id'] ?? 'noid'),
                    'admin_whatsapp',
                    (string)$admin_phone,
                    (string)($related_id ?? ''),
                    $___trigger,
                    $___bucket
                ]));
                if (!get_transient($___lock_key)) {
                    set_transient($___lock_key, 1, 90); // CHANGED

                    $this->send_whatsapp(
                        $admin->ID,
                        $admin_name,
                        $admin_phone,
                        $msg_admin,
                        $rule['admin_whatsapp_media_url'],
                        $log_base . ' (Admin)',
                        $related_id
                    );
                    $phones_sent[$admin_phone] = true;
                }
            }

            // Admin Email
            if (
                $admin_em_active &&
                !empty($rule['admin_email_subject']) &&
                !empty($rule['admin_email_body']) &&
                $admin_email &&
                !isset($emails_sent[$admin_email])
            ) {

                $subj_admin = $this->parse_template(
                    $rule['admin_email_subject'],
                    $rep_admin,
                    $related_id,
                    $recipient_user['user_id'] ?? null
                );
                $body_admin = $this->parse_template(
                    $rule['admin_email_body'],
                    $rep_admin,
                    $related_id,
                    $recipient_user['user_id'] ?? null
                );
                $admin_user = $admin;

                $___trigger = $trigger_key ?: ($rule['trigger_key'] ?? 'unknown');
                $___bucket  = (int) floor(time() / 60);
                $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
                    (string)($rule['rule_internal_id'] ?? $rule['id'] ?? 'noid'),
                    'admin_email',
                    (string)$admin_email,
                    (string)($related_id ?? ''),
                    $___trigger,
                    $___bucket
                ]));
                if (!get_transient($___lock_key)) {
                    set_transient($___lock_key, 1, 90); // CHANGED

                    $this->send_email(
                        $admin_user->ID,          
                        $admin_email,            
                        $subj_admin,              
                        $body_admin,          
                        $log_base . ' (Admin Email)',
                        $related_id
                    );
                    $emails_sent[$admin_email] = true;
                }
            }
        }
    }
}



public function process_scheduled_notification( $args = [] )
{
    if (empty($args) || !is_array($args)) {
        error_log('WAWP NOTIF Cron: Invalid args');
        return;
    }

    // remove matching scheduled event (covers Run now)
    try {
        $___hook = WAWP_NOTIF_CRON_HOOK;
        $___args = [ $args ];
        $___ts = wp_next_scheduled($___hook, $___args);
        while ($___ts) {
            wp_unschedule_event($___ts, $___hook, $___args);
            $___ts = wp_next_scheduled($___hook, $___args);
        }
    } catch (\Throwable $e) {}

    $rid                  = $args['rule_internal_id']      ?? '';
    $roid                 = $args['related_object_id']     ?? null;     
    $recipient_user       = $args['recipient_user']        ?? [];
    $replacements_user    = $args['replacements_user']     ?? [];   
    $log_base             = $args['log_message_type_base'] ?? 'Scheduled Notification';
    $sender_type          = $args['sender_type']           ?? 'user_whatsapp';

    // CHANGED: short-lived global run lock (90s) + bucket
    $___bucket = (int) floor(time() / 60);
    $___global_lock = 'wawp_notif_proc_' . md5(implode('|', [
        (string)$rid,
        (string)($recipient_user['user_id'] ?? 0),
        (string)($recipient_user['phone'] ?? ''),
        (string)($recipient_user['email'] ?? ''),
        (string)($roid ?? ''),
        $___bucket
    ]));
    if (get_transient($___global_lock)) {
        return;
    }
    set_transient($___global_lock, 1, 90); // CHANGED

    $admin_user_ids_csv = $args['admin_user_ids']       ?? '';
    $admin_wh_msg_raw   = $args['admin_whatsapp_message'] ?? '';
    $admin_wh_media     = $args['admin_whatsapp_media']  ?? '';
    $admin_em_subj_raw  = $args['admin_email_subject']   ?? '';
    $admin_em_body_raw  = $args['admin_email_body']      ?? '';

    if (!$rid || empty($recipient_user) || empty($replacements_user)) {
        error_log("WAWP NOTIF Cron: Missing data for rule {$rid}");
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . WAWP_NOTIF_RULES_TABLE_NAME;
    $rule  = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE rule_internal_id = %s", $rid),
        ARRAY_A
    );
    if (!$rule) {
        error_log("WAWP NOTIF Cron: Rule {$rid} not found");
        return;
    }

    $base = $replacements_user;
    $base['{site_title}'] = get_bloginfo('name');

    if ($roid && is_numeric($roid) && class_exists('WooCommerce')) {
        if ($order = wc_get_order($roid)) {
            $base = [
                '{order_id}'        => $order->get_id(),
                '{order_total}'     => $order->get_formatted_order_total(),
                '{order_key}'       => $order->get_order_key(),
                '{billing_first}'   => $order->get_billing_first_name(),
                '{billing_last}'    => $order->get_billing_last_name(),
                '{customer_note}'   => $order->get_customer_note(),
            ];
            $base = array_merge($replacements_user, $base);
            $base['{site_title}'] = get_bloginfo('name');
        }
    }

    static $phones_sent = [];
    static $emails_sent = [];
    $user_wh_active  = in_array($sender_type, ['user_whatsapp', 'user_both', 'user_admin_both', 'user_admin_whatsapp'], true);
    $user_em_active  = in_array($sender_type, ['user_email', 'user_both', 'user_admin_both', 'user_admin_email'], true);
    $admin_wh_active = in_array($sender_type, ['admin_whatsapp', 'admin_both', 'user_admin_both', 'user_admin_whatsapp'], true);
    $admin_em_active = in_array($sender_type, ['admin_email', 'admin_both', 'user_admin_both', 'user_admin_email'], true);

    // allow channels even if *_enabled=0 when forced by sender_type
    if ($user_wh_active && isset($rule['whatsapp_enabled']) && (int)$rule['whatsapp_enabled'] === 0) {
        $rule['whatsapp_enabled'] = 1;
    }
    if ($user_em_active && isset($rule['email_enabled']) && (int)$rule['email_enabled'] === 0) {
        $rule['email_enabled'] = 1;
    }
    if ($admin_wh_active && isset($rule['admin_whatsapp_enabled']) && (int)$rule['admin_whatsapp_enabled'] === 0) {
        $rule['admin_whatsapp_enabled'] = 1;
    }
    if ($admin_em_active && isset($rule['admin_email_enabled']) && (int)$rule['admin_email_enabled'] === 0) {
        $rule['admin_email_enabled'] = 1;
    }

    // ---------- User WhatsApp ----------
    if (
        $user_wh_active &&
        !empty($rule['whatsapp_enabled']) &&
        !empty($rule['whatsapp_message']) &&
        !empty($recipient_user['phone']) &&
        !isset($phones_sent[$recipient_user['phone']])
    ) {
        $message_user = $this->parse_template(
            $rule['whatsapp_message'],     
            $base,                
            $roid,                
            $recipient_user['user_id'] ?? null
        );

        $___bucket = (int) floor(time() / 60);
        $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
            (string)$rid, 'user_whatsapp',
            (string)($recipient_user['phone'] ?? ''), (string)($roid ?? ''),
            $___bucket
        ]));
        if (!get_transient($___lock_key)) {
            set_transient($___lock_key, 1, 90); // CHANGED

            $this->send_whatsapp(
                $recipient_user['user_id'],
                $recipient_user['display_name'],
                $recipient_user['phone'],
                $message_user,
                $rule['whatsapp_media_url'],
                $log_base . ' (User Scheduled)',
                $roid
            );
            $phones_sent[$recipient_user['phone']] = true;
        }
    }

    // ---------- User Email ----------
    if (
        $user_em_active &&
        !empty($rule['email_enabled']) &&
        !empty($rule['email_subject']) &&
        !empty($rule['email_body']) &&
        !empty($recipient_user['email']) &&
        !isset($emails_sent[$recipient_user['email']])
    ) {
        $subject_user = $this->parse_template(
            $rule['email_subject'],
            $base,
            $roid,
            $recipient_user['user_id'] ?? null
        );
        $body_user = $this->parse_template(
            $rule['email_body'],
            $base,
            $roid,
            $recipient_user['user_id'] ?? null
        );

        $___bucket = (int) floor(time() / 60);
        $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
            (string)$rid, 'user_email',
            (string)($recipient_user['email'] ?? ''), (string)($roid ?? ''),
            $___bucket
        ]));
        if (!get_transient($___lock_key)) {
            set_transient($___lock_key, 1, 90); // CHANGED

            $this->send_email(
                $recipient_user['user_id'] ?? 0,     
                $recipient_user['email'],              
                $subject_user,  
                $body_user,
                $log_base . ' (User Scheduled Email)',         
                $roid                  
            );
            $emails_sent[$recipient_user['email']] = true;
        }
    }

    // ---------- Admins ----------
    if (($admin_wh_active || $admin_em_active) && $admin_user_ids_csv) {

        $admin_ids = array_unique(
            array_diff(
                array_filter(array_map('intval', explode(',', $admin_user_ids_csv))),
                [$recipient_user['user_id']]
            )
        );

        foreach ($admin_ids as $admin_user_id) {

            $admin_user = get_user_by('ID', $admin_user_id);
            if (!$admin_user) {
                continue;
            }

            $admin_info  = $this->awp_db_manager->get_user_info($admin_user->ID);
            $admin_phone = $admin_info && $admin_info->phone
                ? $admin_info->phone
                : get_user_meta($admin_user->ID, 'billing_phone', true);

            $admin_email = $admin_user->user_email;
            $admin_name  = $admin_user->display_name;
            $base_admin  = array_merge($base, ['{admin_display_name}' => $admin_name]);

            // Admin WhatsApp
            if (
                $admin_wh_active &&
                !empty($rule['admin_whatsapp_enabled']) &&
                $admin_wh_msg_raw &&
                $admin_phone &&
                !isset($phones_sent[$admin_phone])
            ) {
                $msg_admin = $this->parse_template(
                    $admin_wh_msg_raw,
                    $base_admin,
                    $roid,
                    $recipient_user['user_id'] ?? null
                );

                $___bucket = (int) floor(time() / 60);
                $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
                    (string)$rid, 'admin_whatsapp',
                    (string)$admin_phone, (string)($roid ?? ''),
                    $___bucket
                ]));
                if (!get_transient($___lock_key)) {
                    set_transient($___lock_key, 1, 90); // CHANGED

                    $this->send_whatsapp(
                        $admin_user->ID,
                        $admin_name,
                        $admin_phone,
                        $msg_admin,
                        $admin_wh_media,
                        $log_base . ' (Admin Scheduled)',
                        $roid
                    );
                    $phones_sent[$admin_phone] = true;
                }
            }

            // Admin Email
            if (
                $admin_em_active &&
                !empty($rule['admin_email_enabled']) &&
                $admin_em_subj_raw &&
                $admin_em_body_raw &&
                $admin_email &&
                !isset($emails_sent[$admin_email])
            ) {
                $subj_admin = $this->parse_template(
                    $admin_em_subj_raw,
                    $base_admin,
                    $roid,
                    $recipient_user['user_id'] ?? null
                );

                $body_admin = $this->parse_template(
                    $admin_em_body_raw,
                    $base_admin,
                    $roid,
                    $recipient_user['user_id'] ?? null
                );

                $___bucket = (int) floor(time() / 60);
                $___lock_key = 'wawp_notif_lock_' . md5(implode('|', [
                    (string)$rid, 'admin_email',
                    (string)$admin_email, (string)($roid ?? ''),
                    $___bucket
                ]));
                if (!get_transient($___lock_key)) {
                    set_transient($___lock_key, 1, 90); // CHANGED

                    $this->send_email(
                        $admin_user->ID,               
                        $admin_email,                 
                        $subj_admin,                   
                        $body_admin,                   
                        $log_base . ' (Admin Scheduled Email)',
                        $roid
                    );
                    $emails_sent[$admin_email] = true;
                }
            }
        }
    }

    // final safety — ensure no duplicate future entries remain for these args
    try {
        $___hook = WAWP_NOTIF_CRON_HOOK;
        $___args = [ $args ];
        $___ts = wp_next_scheduled($___hook, $___args);
        while ($___ts) {
            wp_unschedule_event($___ts, $___hook, $___args);
            $___ts = wp_next_scheduled($___hook, $___args);
        }
    } catch (\Throwable $e) {}
}




    private function parse_template($raw, array $base_replace, $order_id = null, $user_id = null)
    {
        return AWP_Message_Parser::parse_message_placeholders(
            $raw,
            $base_replace,
            $order_id,
            $user_id
        );
    }

    private function get_random_online_instance(): ?object
    {
        static $cache = null;           
        if ($cache !== null) {
            return $cache;
        }

        $ids = explode(',', $this->get_settings()['selected_instance_ids'] ?? '');
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) {
            return null;
        }

        global $wpdb;
        $place = implode(',', array_fill(0, count($ids), '%d'));
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, instance_id, access_token
                FROM {$wpdb->prefix}awp_instance_data
                WHERE status='online' AND id IN ( $place )",
                $ids
            )
        );
        if (!$rows) {
            return null;
        }

        $cache = $rows[array_rand($rows)];
        return $cache;
    }

private function send_whatsapp(
    $user_id,
    $name,
    $phone,
    $message,
    $media_url,
    $log_type,
    $related_id = null
) {

    if (empty($phone) || empty($this->awp_log_manager)) {
        return;
    }
    $log_failure = function ($reason, $instance_id = null, $access_token = null) use (
        $user_id,
        $name,
        $phone,
        $message,
        $media_url,
        $log_type,
        $related_id
    ) {
        $this->awp_log_manager->log_notification([
            'user_id'          => $user_id,
            'order_id'         => (is_numeric($related_id) ? $related_id : null),
            'customer_name'    => $name,
            'sent_at'          => current_time('mysql'),
            'whatsapp_number'  => $phone,
            'message'          => $message,
            'image_attachment' => $media_url,
            'message_type'     => $log_type . ' (FAILED)',
            'wawp_status'      => ['status' => 'error', 'message' => $reason],
            'resend_id'        => null,
            'instance_id'      => $instance_id,
            'access_token'     => $access_token,
        ]);
    };

    $ids_csv = $this->get_settings()['selected_instance_ids'];
    $ids     = array_filter(array_map('intval', explode(',', $ids_csv)));

    if (!$ids) {
        $log_failure(__('No WhatsApp instance selected.', 'awp'));
        return;
    }

    global $wpdb;
    $place = implode(',', array_fill(0, count($ids), '%d'));
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, instance_id, access_token
            FROM {$wpdb->prefix}awp_instance_data
            WHERE status = 'online' AND id IN ( $place )",
            $ids
        )
    );

    if (!$rows) {
        $log_failure(__('None of the selected instances are online.', 'awp'));
        return;
    }

    $chosen = $rows[array_rand($rows)];
    if ( ! empty( $media_url ) ) {
        $resp = Wawp_Api_Url::send_image(
            $chosen->instance_id,
            $chosen->access_token,
            $phone,
            $media_url,
            $message
        );
    } else {
        $resp = Wawp_Api_Url::send_message(
            $chosen->instance_id,
            $chosen->access_token,
            $phone,
            $message
        );
    }


    $this->awp_log_manager->log_notification([
        'user_id'          => $user_id,
        'order_id'         => (is_numeric($related_id) ? $related_id : null),
        'customer_name'    => $name,
        'sent_at'          => current_time('mysql'),
        'whatsapp_number'  => $phone,
        'message'          => $message,
        'image_attachment' => $media_url,
        'message_type'     => $log_type,
        'wawp_status'      => $resp,
        'resend_id'        => null,
        'instance_id'      => $chosen->instance_id,
        'access_token'     => $chosen->access_token,
    ]);
}
    
    
    

    private function send_email(
     $user_id,           
     $to,
$subject,
     $body,
     $type,            
     $related_id = null
 )
    {
        if (empty($to) || !is_email($to)) {
            error_log("WAWP NOTIF: Invalid email for {$log_type}");
            return false;
        }
        if (empty($subject) && empty($body)) {
            error_log("WAWP NOTIF: Empty email content for {$log_type}");
            return false;
        }

        $headers = [
      'Content-Type: text/html; charset=UTF-8',
      'Disposition-Notification-To: ' . get_option( 'admin_email' ),
      'Return-Receipt-To: '          . get_option( 'admin_email' ),
  ];

  $log_id = $this->log_email_notification(
      $user_id,
      $to,
      $subject,
      $body,
      'pending',           
      'queued for sending', 
      $type,
      $related_id
  );

  $pixel = '<img src="' . esc_url( home_url( '/?wawp_email_open=' . $log_id ) ) .
           '" width="1" height="1" alt="" style="display:none;" />';
  if ( stripos( $body, '</body>' ) !== false ) {
      $body = str_ireplace( '</body>', $pixel . '</body>', $body );
  } else {
      $body .= $pixel;
  }


      $body_processed   = wpautop( wp_kses_post( $body ) );
    $sent_successfully = wp_mail( $to, $subject, $body_processed, $headers );

            global $wpdb;
   $wpdb->update(
        $wpdb->prefix . 'wawp_email_log',
        [
            'status'   => $sent_successfully ? 'sent' : 'error',
            'response' => $sent_successfully ? 'OK' : 'wp_mail() returned false',
            'sent_at'  => current_time( 'mysql' ),
        ],
        [ 'id' => $log_id ],
        [ '%s', '%s', '%s' ],
        [ '%d' ]
    );


        return $sent_successfully;
    }

    private function log_email_notification($user_id, $email_address, $subject, $message_body, $status, $response_msg, $type, $related_id = null)
    {
        global $wpdb;
        $email_log_table = $wpdb->prefix . 'wawp_email_log';

        $wpdb->insert(
            $email_log_table,
            [
                'campaign_id'     => 0, 
                'user_id'         => $user_id,
                'email_address'   => $email_address,
                'subject'         => $subject,
                'message_body'    => $message_body,
                'status'          => $status,
                'sent_at'         => current_time('mysql'),
                'response'        => $response_msg,
                'first_opened_at' => null,
                'open_count'      => 0,
                'created_at'      => current_time('mysql'),
                'type'            => $type,
            ],
            [
                '%d', 
                '%d',
                '%s', 
                '%s',
                '%s',
                '%s', 
                '%s', 
                '%s', 
                '%s', 
                '%d', 
                '%s',
                '%s',
            ]
        );
        return $wpdb->insert_id;
    }

    public function ajax_search_users()
    {
        
                if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied', 'awp' ), 403 );
        }
        check_ajax_referer('wawp_search_users_nonce', 'nonce');

        $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $results = [];

        if (!empty($q)) {
            $args = [
                'number'         => 20,
                'fields'         => ['ID', 'display_name', 'user_email'],
                'orderby'        => 'display_name',
                'order'          => 'ASC',
                'search' => '*' . esc_attr($q) . '*',
                'search_columns' => ['user_login', 'user_email', 'display_name'],
            ];
            $users = get_users($args);
            foreach ($users as $u) {
                $results[] = [
                    'id'   => $u->ID,
                    'text' => "{$u->display_name} ({$u->user_email} – ID:{$u->ID})"
                ];
            }
        }

        wp_send_json(['results' => $results]);
    }

    private function is_pro_user() {
        $user_data = get_transient('siteB_user_data');
        if (empty($user_data)) {
            return false;
        }

        if (!empty($user_data['is_lifetime'])) {
            return true;
        }

        if (!empty($user_data['subscriptions']) && is_array($user_data['subscriptions'])) {
            foreach ($user_data['subscriptions'] as $sub) {
                if (isset($sub['status']) && $sub['status'] === 'active') {
                    return true; 
                }
            }
        }

        return false;
    }

    private function is_sso_logged_in() {
        return !empty(get_option('mysso_token'));
    }

    private function is_multi_lang_active() {
        $user_data = get_transient('siteB_user_data');
      
        if (empty($user_data)) {
            return false;
        }
       
        return !empty($user_data['multi_lang_active']);
    }

}
    add_action( 'init', function () {
        if ( empty( $_GET['wawp_email_open'] ) ) {
            return;
        }
        $log_id = absint( $_GET['wawp_email_open'] );
        if ( ! $log_id ) {
            status_header( 400 );
            exit;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wawp_email_log';
    
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT open_count, first_opened_at FROM {$table} WHERE id = %d", $log_id )
        );
    
        if ( $row ) {
            $update = [ 'open_count' => (int) $row->open_count + 1 ];
            if ( empty( $row->first_opened_at ) || $row->first_opened_at === '0000-00-00 00:00:00' ) {
                $update['first_opened_at'] = current_time( 'mysql' );
            }
            $wpdb->update( $table, $update, [ 'id' => $log_id ] );
        }
    
        header( 'Content-Type: image/gif' );
        echo base64_decode( 'R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
        exit;
    } );
    
    
    new Wawp_df_Notifications();