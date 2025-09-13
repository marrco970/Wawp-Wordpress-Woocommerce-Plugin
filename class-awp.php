<?php
/**
 * Plugin Name: Wawp OTP Verification, Order Notifications, and Country Code Selector for WooCommerce
 * Plugin URI:  https://wawp.net
 * Description: Wawp is the best way to send & receive order updates, recover abandoned carts, drive repeat sales, and secure your store using OTP – all via WhatsApp.
 * Version:     4.0.3.13
 * Author:      wawp.net
 * Author URI:  https://wawp.net
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: awp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('AWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AWP_PLUGIN_VERSION', '4.0.3.13');
define('AWP_PLUGIN_FILE', __FILE__); // Define the main plugin file path
define('AWP_MAIN_MENU_SLUG', 'wawp');

require_once AWP_PLUGIN_DIR . 'includes/class-wawp-connector.php';
require_once AWP_PLUGIN_DIR . 'views/wawp-enqueue-scripts.php';
require_once AWP_PLUGIN_DIR . 'includes/autoload.php';
require_once AWP_PLUGIN_DIR . 'includes/helpers/class-awp-admin-notices.php';
require_once AWP_PLUGIN_DIR . 'includes/helpers/class-awp-custom-pages-settings.php';
$awp_custom_login_pages_instance = new AWP_Custom_Login_Pages();

register_activation_hook(__FILE__, [ $awp_custom_login_pages_instance, 'activate_plugin' ]);
register_deactivation_hook(__FILE__, [ $awp_custom_login_pages_instance, 'deactivate_plugin' ]);


add_action('init', function() {
    load_plugin_textdomain('awp', false, dirname(plugin_basename(__FILE__)) . '/languages');
});


register_activation_hook(__FILE__, 'awp_copy_language_files_on_activation');
function awp_copy_language_files_on_activation() {
    $source = AWP_PLUGIN_DIR . 'languages/';
    $destination = WP_LANG_DIR . '/plugins/';
    if (!file_exists($destination)) {
        wp_mkdir_p($destination);
    }
    if (defined('GLOB_BRACE')) {
        $lang_files = glob($source . 'awp-*.{mo,po}', GLOB_BRACE);
    } else {
        $files_mo = glob($source . 'awp-*.mo');
        $files_po = glob($source . 'awp-*.po');
        $lang_files = array_merge($files_mo ?: [], $files_po ?: []);
    }
    if (!empty($lang_files)) {
        foreach ($lang_files as $file) {
            $filename = basename($file);
            @copy($file, $destination . $filename);
        }
    }

    require_once AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php';
    if (class_exists('AWP_System_Info')) {
        $system_info_instance = new AWP_System_Info();
        $system_info_instance->run_checks_and_set_transient();
    }
}

$is_chat_widget_enabled       = get_option('awp_chat_widget_enabled', 1);
$is_wawp_otp_enabled          = get_option('awp_wawp_otp_enabled', 1);
$is_otp_login_enabled         = get_option('awp_otp_login_enabled', 1);
$is_signup_enabled            = get_option('awp_signup_enabled', 1);
$is_checkout_otp_enabled      = get_option('awp_checkout_otp_enabled', 1);
$is_countrycode_enabled = get_option( 'awp_countrycode_enabled', 1 );
$is_campaigns_enabled = get_option( 'awp_campaigns_enabled', 0 ); 

$files_to_include = [
    'class-wawp-setup-wizard.php',
    'class-awp-log-manager.php',
    'class-wawp-dashboard.php',
    'class-instances.php',
    'class-database-manager.php',
    'class-awp-menu.php',
    'class-awp-message-parser.php',
    'class-awp-system-info.php',
    'class-wawp-api-url.php',
    'class-awp-phone-verification-bar.php',
    'class-wawp-email-log-list-table.php',
    'class-awp-others.php'
];

if ( $is_countrycode_enabled ) {
    $files_to_include[] = 'class-awp-countrycode.php';
}

$is_notifications_enabled = get_option( 'awp_notifications_enabled', 1 );
if ( $is_notifications_enabled ) {
    $files_to_include[] = 'class-wawp-flow-builder.php';
    $files_to_include[] = 'class-wawp-notification-preferences.php';
}

if ($is_wawp_otp_enabled) {
    if ($is_otp_login_enabled) {
        $files_to_include[] = 'class-awp-otp-login.php';
    }
    if ($is_signup_enabled) {
        $files_to_include[] = 'class-awp-signup.php';
    }
    if ($is_checkout_otp_enabled) {
        $files_to_include[] = 'class-awp-checkout-otp.php';
    }
    $files_to_include[] = 'class-wawp-otp.php';
}

if ( $is_campaigns_enabled ) {
    $files_to_include[] = 'class-wawp-campaigns-advanced.php';
    $files_to_include[] = 'class-wawp-campaigns-admin.php';
}

if ($is_chat_widget_enabled) {
    $files_to_include[] = 'class-wawp-chat-widget.php';
}

foreach ($files_to_include as $file) {
    require_once AWP_PLUGIN_DIR . 'includes/' . $file;
}

add_action( 'plugins_loaded', function () use ( $is_notifications_enabled, $is_countrycode_enabled,$is_campaigns_enabled) {
    $wawp_connector = new Wawp_Connector();
    $dm = new AWP_Database_Manager();
    $inst = new AWP_Instances();
    $awp_others = new AWP_Others();
    $inst->init();
    $log = new AWP_Log_Manager();
    
    $notifications = null;
    if ( $is_notifications_enabled && class_exists( 'Wawp_df_Notifications' ) ) {
        $notifications = new Wawp_df_Notifications();
    }

    $countrycode = null;                            
    if ( $is_countrycode_enabled && class_exists( '\AWP\Wawp_Countrycode' ) ) {
        $countrycode = new AWP\Wawp_Countrycode();
        $countrycode->init();
    }
        $menu_chat_widget = null;
    if (get_option('awp_chat_widget_enabled', 1) && class_exists('WAWP_Chat_Widget')) {
        $menu_chat_widget = new WAWP_Chat_Widget();
    }
    
       $awp_campaigns = null;
    if ( $is_campaigns_enabled && class_exists( 'WP_Wawp_Campaigns_Advanced' ) ) {
        $awp_campaigns = new WP_Wawp_Campaigns_Advanced();
    }

    $menu = new AWP_Menu(
        $inst,
        $log,
        $countrycode,
        $menu_chat_widget,
        $notifications,
        $wawp_connector
    );
    $menu->init();
    if (class_exists('AWP_Otp_Login')) {
        $otp = new AWP_Otp_Login($dm, $inst);
        $otp->init();
    }
    if (class_exists('Wawp_Otp')) {
        $wawp_otp = new Wawp_Otp();
        $wawp_otp->init($dm, $inst);
    }
    if ( class_exists( 'Wawp_Flow_Builder' ) ) {
        ( new Wawp_Flow_Builder() )->init();
    }
    
    
});

register_activation_hook(__FILE__, function () {
    $dm = new AWP_Database_Manager();
    $dm->create_all_tables();
    $dm->ensure_all_columns();
    $connector = new Wawp_Connector();
    $connector->on_activate();
    $old_list = (array) get_option( 'awp_block_list', [] );
    if ( $old_list ) {
        $dm->upsert_blocked_numbers( $old_list );
        delete_option( 'awp_block_list' );
    }
    

    // Cron Job for NEW logs' Delivery Status (every minute)
    if (! wp_next_scheduled('awp_cron_check_delivery_status')) {
        wp_schedule_event(time(), 'five_minutes', 'awp_cron_check_delivery_status');
    }
    // Cron Job for OLD logs' Delivery Status (every 6 hours)
    if (! wp_next_scheduled('awp_cron_recheck_delivery_status')) {
        wp_schedule_event(time(), 'six_hours', 'awp_cron_recheck_delivery_status');
    }
    
    if (! wp_next_scheduled('awp_cron_auto_resend')) {
        wp_schedule_event(time(), 'five_minutes', 'awp_cron_auto_resend');
    }
    if ( ! wp_next_scheduled( 'wp_campaigns_cron_send_advanced' ) ) {
        wp_schedule_event( time(), 'five_minutes', 'wp_campaigns_cron_send_advanced' );
    }   
    if (!wp_next_scheduled('awp_cron_refresh_system_info')) {
        wp_schedule_event(time(), 'twelve_hours', 'awp_cron_refresh_system_info');
    }
    if (! wp_next_scheduled('awp_cron_auto_clear_logs')) {
        wp_schedule_event(time(), 'daily', 'awp_cron_auto_clear_logs');
    }
    if (!wp_next_scheduled('awp_cron_hourly_self_repair')) {
        wp_schedule_event(time(), 'hourly', 'awp_cron_hourly_self_repair');
    }
    if ( false === get_option( 'awp_campaigns_enabled' ) ) {
    add_option( 'awp_campaigns_enabled', 0 );  
    }
    if ( false === get_option( Wawp_Api_Url::OPT_TRACKING_IDS ) ) {
		add_option( Wawp_Api_Url::OPT_TRACKING_IDS, Wawp_Api_Url::DEF_TRACKING_IDS );
	}

    if (false === get_option('awp_otp_settings')) {
        add_option('awp_otp_settings', [
            'instance'               => 0,
            'otp_message_whatsapp'   => __('Your OTP code is: {{otp}}', 'awp'),
            'otp_message_email'      => __('Your OTP code is: {{otp}}', 'awp'),
            'login_method'           => 'whatsapp_otp',
            'enable_whatsapp'        => 1,
            'enable_email'           => 1,
            'enable_email_password'  => 1,
            'redirect_rules'         => [],
            'signup_logo'            => [
                'default'           => AWP_PLUGIN_DIR . 'login-WhatsApp_icon.png',
                'sanitize_callback' => 'esc_url_raw'
            ],
            'title'                  => __('Welcome back', 'awp'),
            'description'            => __('Choose a sign-in method to continue', 'awp'),
            'request_otp_button_color' => '#22c55e',
            'verify_otp_button_color'  => '#22c55e',
            'resend_otp_button_color'  => '#22c55e',
            'login_button_color'     => '#22c55e',
            'custom_shortcode'       => '',
            'custom_css'             => '',
        ]);
    }
    $wcc_opts = get_option('woo_intl_tel_options', []);
    $wcc_opts['enable_ip_detection'] = 1;
    if (empty($wcc_opts['phone_fields']) || !is_array($wcc_opts['phone_fields'])) {
        $wcc_opts['phone_fields'] = [
            ['id'=>'#billing-phone', 'name'=>'billing_phone', 'enabled'=>'1'],
            ['id'=>'#billing_phone', 'name'=>'billing_phone', 'enabled'=>'1'],
            ['id'=>'#awp_whatsapp',  'name'=>'awp_whatsapp',  'enabled'=>'1'],
            ['id'=>'#awp_phone',     'name'=>'awp_phone',     'enabled'=>'1'],
        ];
    } else {
        foreach ($wcc_opts['phone_fields'] as &$pf) {
            $pf['enabled'] = '1';
        }
    }
    if (function_exists('\AWP\awp_get_all_countries')) {
        $all_countries = \AWP\awp_get_all_countries();
        $iso2_list = array_map(function($c) {
            return $c['iso2'];
        }, $all_countries);
        $wcc_opts['enabled_countries'] = $iso2_list;
    }
    update_option('woo_intl_tel_options', $wcc_opts);
    if (class_exists('AWP_Others')) {
        AWP_Others::on_activate();
    }
    set_transient('_awp_activation_redirect', true, 30);
});

register_deactivation_hook(__FILE__, function () {
    $connector = new Wawp_Connector();
    $connector->on_deactivate();
    wp_clear_scheduled_hook('awp_cron_check_status');
    wp_clear_scheduled_hook('awp_cron_check_delivery_status');
    wp_clear_scheduled_hook('awp_cron_recheck_delivery_status');
    wp_clear_scheduled_hook('awp_cron_auto_resend');
    wp_clear_scheduled_hook('wawp_notif_send_scheduled_notification_action');
    wp_clear_scheduled_hook('awp_cron_refresh_system_info');
    wp_clear_scheduled_hook('awp_cron_auto_clear_logs');
    wp_clear_scheduled_hook('awp_cron_hourly_self_repair');
});

add_filter('cron_schedules', function ($schedules) {
    $schedules['one_minute'] = [
        'interval' => 60,
        'display'  => 'Every 1 minute',
    ];
    
       $schedules['five_minutes'] = [
        'interval' => 300,        
        'display'  => 'Every 5 Minutes',
    ];

    $schedules['six_hours'] = [
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => 'Every 6 Hours',
    ];

    $schedules['hourly'] = [
        'interval' => HOUR_IN_SECONDS,
        'display'  => 'Every 1 Hour',
    ];
    
    $schedules['twelve_hours'] = [
        'interval' => 12 * HOUR_IN_SECONDS,
        'display'  => 'Every 12 Hours',
    ];
    return $schedules;
});

add_action('awp_cron_refresh_system_info', 'awp_cron_refresh_system_info_callback');
function awp_cron_refresh_system_info_callback() {
    require_once AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php';
    if (class_exists('AWP_System_Info')) {
        $system_info_instance = new AWP_System_Info();
        $system_info_instance->run_checks_and_set_transient();
    }
}

add_action( 'plugins_loaded', function () {
if ( ! is_admin() ) {
    return;  
}
$dm = new AWP_Database_Manager();
$dm->ensure_all_columns();
} );

add_action('awp_cron_auto_clear_logs', 'awp_cron_auto_clear_logs_callback');
function awp_cron_auto_clear_logs_callback() {
    require_once AWP_PLUGIN_DIR . 'includes/class-awp-log-manager.php';
    if (class_exists('AWP_Log_Manager')) {
        $log_manager = new AWP_Log_Manager();
        $log_manager->handle_auto_clear_logs();
    }
}

add_action('awp_cron_hourly_self_repair', 'awp_cron_hourly_self_repair_callback');
function awp_cron_hourly_self_repair_callback() {
    require_once AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php';
    if (class_exists('AWP_System_Info')) {
        $system_info_instance = new AWP_System_Info();
        $system_info_instance->run_all_repairs();
    }
}
add_action('admin_init', function() {
    if (!get_transient('_awp_activation_redirect')) {
        return;
    }
    delete_transient('_awp_activation_redirect');
    if (is_network_admin() || isset($_GET['activate-multi'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    wp_safe_redirect(admin_url('admin.php?page=wawp&awp_section=connector&wawp_popup_step=1'));
    return;
});



add_action('init', 'my_wawp_plugin_register_bricks_elements', 11);
function my_wawp_plugin_register_bricks_elements() {
    if (!class_exists('\Bricks\Elements')) return;
    $element_file = AWP_PLUGIN_DIR . 'integrations/bricks-elements.php';
    \Bricks\Elements::register_element($element_file, 'my-wawp-login', 'My_WAWP_Login_Element');
    \Bricks\Elements::register_element($element_file, 'my-wawp-signup', 'My_WAWP_Signup_Element');
    \Bricks\Elements::register_element($element_file, 'my-wawp-fast-login', 'My_WAWP_Fast_Login_Element');
}

add_action('awp_cron_auto_resend', 'awp_cron_auto_resend_callback');
function awp_cron_auto_resend_callback() {
    $log_manager = new AWP_Log_Manager();
    $log_manager->auto_resend_stuck_messages();
}

// Callback for the new logs cron job (1 minute)
add_action('awp_cron_check_delivery_status', 'awp_cron_check_delivery_status_callback');
function awp_cron_check_delivery_status_callback() {
    // Ensure all necessary classes are loaded for the cron job
    require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
    require_once AWP_PLUGIN_DIR . 'includes/class-awp-log-manager.php';
    if (class_exists('AWP_Log_Manager')) {
        $log_manager = new AWP_Log_Manager();
        $log_manager->check_new_delivery_status();
    }
}

// Callback for the old logs re-check cron job (6 hours)
add_action('awp_cron_recheck_delivery_status', 'awp_cron_recheck_delivery_status_callback');
function awp_cron_recheck_delivery_status_callback() {
    // Ensure all necessary classes are loaded for the cron job
    require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
    require_once AWP_PLUGIN_DIR . 'includes/class-awp-log-manager.php';
    if (class_exists('AWP_Log_Manager')) {
        $log_manager = new AWP_Log_Manager();
        $log_manager->recheck_delivery_status();
    }
}
