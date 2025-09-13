<?php

if (!defined('ABSPATH')) {
    exit;
}

class AWP_System_Info {

    private $db_manager;
    public $import_all_html = '';
    public $sync_users_html = '';
    private $requirements = [];
    private $wpdb;

    private $cron_hooks = [];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = new AWP_Database_Manager();

        $this->define_requirements();
        $this->define_cron_hooks();

        add_action('admin_init', [$this, 'check_wc_order_storage_settings']);
        add_action('admin_init', [$this, 'add_system_checker_actions']);
        add_action('admin_notices', [$this, 'render_system_checker_admin_notice']);
        add_action('wp_ajax_awp_truncate_table', [$this, 'handle_ajax_truncate_table']);
        add_action('wp_ajax_awp_create_table',   [$this, 'handle_ajax_create_table']);
        add_action('wp_ajax_awp_drop_table',     [$this, 'handle_ajax_drop_table']);
    }

    public function add_system_checker_actions(): void {
        add_action('admin_action_awp_repair_database', [$this, 'handle_repair_action']);
        add_action('admin_action_awp_schedule_cron', [$this, 'handle_schedule_cron_action']);
        add_action('admin_action_awp_repair_all', [$this, 'handle_repair_all_action']);
    }

    private function get_toggle($option, $default = 1) {
        return (bool) get_option($option, $default);
    }

    private function define_requirements(): void {
        $prefix_awp  = $this->wpdb->prefix . 'awp_';
        $prefix_wawp = $this->wpdb->prefix . 'wawp_';

        $this->requirements = [];
        $this->requirements[$prefix_awp . 'instance_data'] = ['id','name','instance_id','access_token','status','message','created_at'];
        $this->requirements[$prefix_awp . 'notifications_log'] = ['id','user_id','order_id','customer_name','sent_at','whatsapp_number','message','image_attachment','message_type','wawp_status','resend_id'];
        $this->requirements[$prefix_awp . 'user_info'] = ['id','user_id','first_name','last_name','email','phone','password','otp_verification_email','otp_verification_whatsapp','whatsapp_verified','created_at','updated_at'];
        $this->requirements[$prefix_wawp . 'email_log'] = ['id','campaign_id','user_id','email_address','subject','message_body','status','sent_at','response','first_opened_at','open_count','created_at','type'];
        
        $this->requirements[$prefix_awp . 'blocked_numbers'] = ['id', 'phone', 'created_at'];

        if ($this->get_toggle('awp_wawp_otp_enabled')) {
            if ($this->get_toggle('awp_signup_enabled')) {
                $this->requirements[$prefix_awp . 'signup_settings'] = ['id','selected_instance','enable_otp','otp_method','otp_message','otp_message_email','field_order','signup_redirect_url','signup_logo','signup_title','signup_description','signup_button_style','signup_custom_css','button_background_color','button_text_color','button_hover_background_color','button_hover_text_color','enable_strong_password','enable_password_reset','auto_login','first_name_enabled','first_name_required','last_name_enabled','last_name_required','email_enabled','email_required','phone_enabled','phone_required','password_enabled','password_required','custom_fields'];
            }
        }
        if ($this->get_toggle('awp_notifications_enabled')) {
            $this->requirements[$prefix_awp . 'notif_notification_rules'] = ['id','rule_internal_id','enabled','language_code','trigger_key','sender_type','whatsapp_enabled','whatsapp_message','whatsapp_media_url','email_enabled','email_subject','email_body','admin_user_ids','admin_whatsapp_enabled','admin_whatsapp_message','admin_whatsapp_media_url','admin_email_enabled','admin_email_subject','admin_email_body','country_filter_enabled','product_filter_enabled','payment_filter_enabled','billing_countries_whitelist','billing_countries_blocklist','billing_countries','payment_gateways','product_ids_whitelist','product_ids_blocklist','send_product_image','send_timing','delay_value','delay_unit','last_updated'];
            $this->requirements[$prefix_awp . 'notif_languages'] = ['language_code','name','is_main','created_at'];
            $this->requirements[$prefix_awp . 'notif_global'] = ['id','selected_instance_ids','created_at','updated_at'];
        }
        if ($this->get_toggle('awp_campaigns_enabled', 0)) {
            $this->requirements[$prefix_wawp . 'campaigns'] = ['id','name','instances','role_ids','user_ids','external_numbers','external_emails','message','media_url','min_whatsapp_interval','max_whatsapp_interval','min_email_interval','max_email_interval','start_datetime','repeat_type','repeat_days','post_id','product_id','append_post','append_product','send_type','total_count','processed_count','status','paused','next_run','woo_spent_over','woo_orders_over','only_verified_phone','created_at','post_include_title','post_include_excerpt','post_include_link','post_include_image','product_include_title','product_include_excerpt','product_include_link','product_include_image','product_include_price','woo_ordered_products','woo_order_statuses','max_per_day','max_wa_per_day','max_email_per_day','billing_countries','wp_profile_languages','send_whatsapp','send_email','email_subject','email_message'];
            $this->requirements[$prefix_wawp . 'campaigns_queue'] = ['id','campaign_id','user_id','phone','unique_code','security_code','status','sent_at','created_at'];
        }
    }

    private function define_cron_hooks(): void {
        $this->cron_hooks = [
            'awp_cron_check_delivery_status',
            'awp_cron_recheck_delivery_status',
            'awp_cron_auto_resend',
            'awp_cron_refresh_system_info',
            'awp_cron_auto_clear_logs',
            'awp_cron_hourly_self_repair',
        ];

        if ($this->get_toggle('awp_campaigns_enabled', 0)) {
            $this->cron_hooks[] = 'wp_campaigns_cron_send_advanced';
        }
    }

    private function auto_assign_missing_senders( array $online_instances ): bool {
        if ( empty( $online_instances ) ) {
            return false;
        }

        $first_instance_row_id = (int) $online_instances[0]->id;
        $first_instance_id     = $online_instances[0]->instance_id;
        $changed               = false;

        if ( ! class_exists( 'AWP_Database_Manager' ) ) {
            require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        }
        $dbm = new AWP_Database_Manager();

        /* ───── OTP Login ───── */
        if ( $this->get_toggle( 'awp_wawp_otp_enabled' ) && $this->get_toggle( 'awp_otp_login_enabled' ) ) {
            $otp = get_option( 'awp_otp_settings', [] );
            if ( empty( $otp['instance'] ) ) {
                $otp['instance'] = $first_instance_id;
                update_option( 'awp_otp_settings', $otp );
                $changed = true;
            }
        }

        /* ───── OTP Signup ───── */
        if ( $this->get_toggle( 'awp_wawp_otp_enabled' ) && $this->get_toggle( 'awp_signup_enabled' ) ) {
            $signup = $dbm->get_signup_settings();
            if ( empty( $signup['selected_instance'] ) || (string) $signup['selected_instance'] === '0' ) {
                $dbm->update_signup_settings( [ 'selected_instance' => (string) $first_instance_row_id ] );
                $changed = true;
            }
        }

        /* ───── Checkout OTP ───── */
        if ( $this->get_toggle( 'awp_wawp_otp_enabled' ) && $this->get_toggle( 'awp_checkout_otp_enabled' ) ) {
            if ( ! get_option( 'awp_selected_instance' ) ) {
                update_option( 'awp_selected_instance', $first_instance_id );
                $changed = true;
            }
        }

        /* ───── Resend-failed sender ───── */
        if ( ! get_option( 'awp_selected_log_manager_instance' ) ) {
            update_option( 'awp_selected_log_manager_instance', $first_instance_id );
            $changed = true;
        }

        /* ───── WooCommerce Notifications ───── */
        if ( class_exists( 'WooCommerce' ) && $this->get_toggle('awp_notifications_enabled') ) {
            $current_ids = AWP_Instances::get_notif_sender_instance_ids();
            if ( empty( $current_ids ) ) {
                $dbm->set_notif_global( (string) $first_instance_row_id );
                $changed = true;
            }
        }

        return $changed;
    }

    public function run_all_repairs(): array {
        $results = ['db' => false, 'cron' => false, 'senders' => false, 'log' => []];
        $db_manager_path = AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        if (file_exists($db_manager_path)) {
            require_once $db_manager_path;
            if (class_exists('AWP_Database_Manager')) {
                $db_manager = new AWP_Database_Manager();
                $db_manager->create_all_tables();
                $results['db'] = true;
                $results['log'][] = __('Database repair process completed.', 'awp');
            } else {
                $results['log'][] = __('Database repair failed: AWP_Database_Manager class not found.', 'awp');
            }
        } else {
            $results['log'][] = __('Database repair failed: class-database-manager.php not found.', 'awp');
        }

        if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) {
            $this->define_cron_hooks();
            foreach ($this->cron_hooks as $hook) {
                if ($hook === 'awp_cron_refresh_system_info') {
                    $interval = 'twelve_hours';
                } elseif ($hook === 'awp_cron_auto_clear_logs') {
                    $interval = 'daily';
                } elseif ($hook === 'awp_cron_hourly_self_repair') {
                    $interval = 'hourly';
                } elseif ($hook === 'awp_cron_recheck_delivery_status') {
                    $interval = 'six_hours';
                }
                else {
                    $interval = 'five_minutes';
                }
                if (!wp_next_scheduled($hook)) {
                    wp_schedule_event(time(), $interval, $hook);
                    $results['log'][] = sprintf(__('Scheduled missing cron hook: %s.', 'awp'), $hook);
                }
            }
            $results['cron'] = true;
        } else {
            $results['log'][] = __('Cron repair skipped: DISABLE_WP_CRON is set to true in wp-config.php.', 'awp');
        }
        
        if (!class_exists('AWP_Instances')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-instances.php';
        }
        $awp_instances = new AWP_Instances();
        $online_instances = $awp_instances->get_online_instances(); 
        $senders_assigned = $this->auto_assign_missing_senders($online_instances);
        $results['senders'] = $senders_assigned;
        if($senders_assigned) {
            $results['log'][] = __('Successfully auto-assigned missing sender settings.', 'awp');
        } else {
            $results['log'][] = __('No missing sender settings to assign or no online instances were available.', 'awp');
        }
        
        $this->run_checks_and_set_transient();
        $results['log'][] = __('System info cache has been refreshed.', 'awp');
        
        return $results;
    }

    public function handle_repair_all_action() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'awp_repair_all_nonce')) {
            wp_die(__('Invalid nonce.', 'awp'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'awp'));
        }

        $results = $this->run_all_repairs();
        $senders_assigned = $results['senders'];

        $redirect_url = admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info&repaired_all=success');
        if ($senders_assigned) {
            $redirect_url = add_query_arg('senders_assigned', 'true', $redirect_url);
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_repair_action() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'awp_repair_db_nonce')) {
            wp_die(__('Invalid nonce.', 'awp'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'awp'));
        }

        $db_manager_path = AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        if (!file_exists($db_manager_path)) {
            wp_safe_redirect(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info&repaired=failed_not_found'));
            exit;
        }
        require_once $db_manager_path;

        if (!class_exists('AWP_Database_Manager')) {
            wp_safe_redirect(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info&repaired=failed_class_missing'));
            exit;
        }

        $db_manager = new AWP_Database_Manager();
        $db_manager->create_all_tables();

        $this->run_checks_and_set_transient();

        wp_safe_redirect(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info&repaired=success'));
        exit;
    }

    public function handle_schedule_cron_action() {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'awp_schedule_cron_nonce')) {
            wp_die(__('Invalid nonce.', 'awp'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'awp'));
        }

        $hook_to_schedule = isset($_GET['hook']) ? sanitize_key($_GET['hook']) : '';
        
        $this->define_cron_hooks();

        if (in_array($hook_to_schedule, $this->cron_hooks) && !wp_next_scheduled($hook_to_schedule)) {
            if ($hook_to_schedule === 'awp_cron_refresh_system_info') {
                $interval = 'twelve_hours';
            } elseif ($hook_to_schedule === 'awp_cron_auto_clear_logs') {
                $interval = 'daily';
            } elseif ($hook_to_schedule === 'awp_cron_hourly_self_repair') {
                $interval = 'hourly';
            } elseif ($hook_to_schedule === 'awp_cron_recheck_delivery_status') {
                $interval = 'six_hours';
            }
            else {
                $interval = 'five_minutes';
            }
            wp_schedule_event(time(), $interval, $hook_to_schedule);
            $this->run_checks_and_set_transient();
            wp_safe_redirect(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info&scheduled=success'));
        } else {
            wp_safe_redirect(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info&scheduled=failed'));
        }
        exit;
    }

    private function table_exists(string $table): bool {
        return (bool)$this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

    private function get_columns(string $table): array {
        return $this->table_exists($table)
            ? array_map('strtolower', $this->wpdb->get_col("DESCRIBE `$table`", 0))
            : [];
    }

    private function run_db_checks(): array {
        $report     = [];
        $has_issues = false;
        foreach ( $this->requirements as $table => $expected ) {
            $exists  = $this->table_exists( $table );
            $missing = $exists ? array_diff( $expected, $this->get_columns( $table ) ) : [];

            if ( ! $exists || ! empty( $missing ) ) {
                $has_issues = true;
            }

            $is_internal = ( substr( $table, -13 ) === 'instance_data' );

            $report[] = [
                'table'    => $table,
                'exists'     => $exists,
                'missing'    => $missing,
                'internal'   => $is_internal,
            ];
        }

        return [ 'report' => $report, 'has_issues' => $has_issues ];
    }

    private function run_server_env_checks(): array {
        $report = [];

        $is_ssl = is_ssl();
        $report['https'] = [
            'label'   => __('HTTPS Status', 'awp'),
            'status'  => $is_ssl ? __('OK', 'awp') : __('Warning', 'awp'),
            'message' => $is_ssl ? __('Site is using a secure (HTTPS) connection.', 'awp') : __('Site is not using HTTPS. A secure connection is highly recommended.', 'awp'),
        ];

        $is_debug = defined('WP_DEBUG') && WP_DEBUG;
        $report['wp_debug'] = [
            'label'   => __('WordPress Debug Mode', 'awp'),
            'status'  => $is_debug ? __('Enabled', 'awp') : __('OK', 'awp'),
            'message' => $is_debug ? __('<code>WP_DEBUG</code> is enabled. This should be disabled on a live production site.', 'awp') : __('Debug mode is disabled.', 'awp'),
        ];

        $is_debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $report['wp_debug_log'] = [
            'label'   => __('Debug Log', 'awp'),
            'status'  => $is_debug_log ? __('Enabled', 'awp') : __('OK', 'awp'),
            'message' => $is_debug_log ? __('<code>WP_DEBUG_LOG</code> is enabled. Error logging to a file is active.', 'awp') : __('Debug logging is disabled.', 'awp'),
        ];

        if (defined('DISABLE_WP_CRON')) {
            if (DISABLE_WP_CRON) {
                $report['disable_wp_cron'] = [
                    'label'   => __('WP-Cron Constant', 'awp'),
                    'status'  => __('Error', 'awp'),
                    'message' => __('The constant <code>DISABLE_WP_CRON</code> is set to <code>true</code>. This prevents all WordPress scheduled tasks from running automatically. **This line must be removed from wp-config.php.**', 'awp'),
                ];
            } else {
                $report['disable_wp_cron'] = [
                    'label'   => __('WP-Cron Constant', 'awp'),
                    'status'  => __('OK', 'awp'),
                    'message' => __('The constant <code>DISABLE_WP_CRON</code> is explicitly set to <code>false</code>. This has no negative effect.', 'awp'),
                ];
            }
        } else {
            $report['disable_wp_cron'] = [
                'label'   => __('WP-Cron Constant', 'awp'),
                'status'  => __('OK', 'awp'),
                'message' => __('The constant <code>DISABLE_WP_CRON</code> is not defined.', 'awp'),
            ];
        }

        return $report;
    }

    private function detect_cdn_proxy(): array {
        $h = array_change_key_case($_SERVER, CASE_UPPER);
        $map = [
            'Cloudflare' => ['HTTP_CF_RAY', 'HTTP_CF_CONNECTING_IP'],
            'Amazon CloudFront' => ['HTTP_CLOUDFRONT_VIEWER_COUNTRY'],
            'Sucuri' => ['HTTP_X_SUCURI_CLIENTIP'],
            'Imperva Incapsula' => ['HTTP_INCAP_CLIENT_IP'],
            'StackPath' => ['HTTP_X_SPE_CLIENT'],
            'Fastly' => ['HTTP_FASTLY_CLIENT_IP'],
            'Akamai' => ['HTTP_TRUE_CLIENT_IP'],
        ];
        foreach ($map as $vendor => $keys) {
            foreach ($keys as $key) {
                if (isset($h[$key])) return ['vendor' => $vendor, 'proxy' => true];
            }
        }
        if (!empty($h['HTTP_X_FORWARDED_FOR']) && preg_match('#^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)#', $h['REMOTE_ADDR'] ?? '')) {
            return ['vendor' => __('Generic Proxy/CDN', 'awp'), 'proxy' => true];
        }
        return ['vendor' => __('—', 'awp'), 'proxy' => false];
    }

    private function run_cron_checks(): array {
        $cron_is_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $events = [];
        $has_issues = false;

        if (!$cron_is_disabled) {
            foreach ($this->cron_hooks as $hook) {
                $event = wp_get_scheduled_event($hook);
                if (!$event) {
                    $has_issues = true;
                }
                $events[] = [
                    'hook' => $hook,
                    'scheduled' => (bool)$event,
                    'next_run' => $event ? $event->timestamp : 0,
                    'schedule' => $event ? ($event->schedule ?: __('single', 'awp')) : '',
                ];
            }
        }
        return ['enabled' => !$cron_is_disabled, 'events' => $events, 'has_issues' => $has_issues];
    }

    private function check_online_instances_exist(): array {
        $online_instances = [];
        if (!class_exists('AWP_Instances')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-instances.php';
        }
        if (class_exists('AWP_Instances')) {
            $awp_instances = new AWP_Instances();
            $online_instances = $awp_instances->get_online_instances();
        }

        $status = empty($online_instances) ? __('Error', 'awp') : __('OK', 'awp');
        $message = empty($online_instances) ?
            __('No **online WhatsApp instances** found. Please connect at least one number in Sender Settings.', 'awp') :
            __('At least one online WhatsApp instance is connected.', 'awp');

        return [
            'label' => __('Online WhatsApp Instances', 'awp'),
            'status' => $status,
            'message' => $message,
            'count' => count($online_instances)
        ];
    }

    private function check_selected_instances_valid(): array {
        $checks        = [];
        $has_issues    = false;
        $online_inst_ids = [];

        if ( ! class_exists( 'AWP_Instances' ) ) {
            require_once AWP_PLUGIN_DIR . 'includes/class-instances.php';
        }
        $awp_instances    = new AWP_Instances();
        $online_instances = $awp_instances->get_online_instances();

        foreach ( $online_instances as $inst ) {
            $online_inst_ids[] = $inst->instance_id;
        }

        if ( ! class_exists( 'AWP_Database_Manager' ) ) {
            require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        }
        $dbm = new AWP_Database_Manager();

        /* -------- OTP Login -------- */
        $login_status  = __('Disabled', 'awp');
        $login_message = __('Feature is disabled.', 'awp');
        if ( $this->get_toggle( 'awp_wawp_otp_enabled' ) && $this->get_toggle( 'awp_otp_login_enabled' ) ) {
            $otp_settings = get_option( 'awp_otp_settings', [] );
            $login_instance = $otp_settings['instance'] ?? '';
            if ( empty( $login_instance ) ) {
                $login_status  = __('Warning', 'awp');
                $login_message = __('OTP Login is enabled but **no instance is selected** in Sender Settings.', 'awp');
                $has_issues    = true;
            } elseif ( ! in_array( $login_instance, $online_inst_ids, true ) ) {
                $login_status  = __('Error', 'awp');
                $login_message = sprintf(__('OTP Login instance **%s is not online or does not exist**. Please check Sender Settings.', 'awp'), esc_html( $login_instance ));
                $has_issues = true;
            } else {
                $login_status = __('OK', 'awp');
                $login_message = __('OTP Login instance is configured and online.', 'awp');
            }
        }
        $checks['otp_login_instance'] = ['label' => __('OTP Login Sender', 'awp'), 'status' => $login_status, 'message' => $login_message];

        /* -------- OTP Signup -------- */
        $signup_status  = __('Disabled', 'awp');
        $signup_message = __('Feature is disabled.', 'awp');
        if ( $this->get_toggle( 'awp_wawp_otp_enabled' ) && $this->get_toggle( 'awp_signup_enabled' ) ) {
            $signup_row = $dbm->get_signup_settings();
            $signup_instanceId = $signup_row['selected_instance'] ?? '';
            if ( empty( $signup_instanceId ) || $signup_instanceId === '0' ) {
                $signup_status  = __('Warning', 'awp');
                $signup_message = __('WhatsApp OTP Signup is enabled but **no instance is selected** in Sender Settings.', 'awp');
                $has_issues     = true;
            } else {
                $found_online = false;
                foreach ( $awp_instances->get_all_instances() as $inst ) {
                    if ( (int) $inst->id === (int) $signup_instanceId && $inst->status === 'online' ) {
                        $found_online = true;
                        break;
                    }
                }
                if ( ! $found_online ) {
                    $signup_status  = __('Error', 'awp');
                    $signup_message = sprintf(__('WhatsApp OTP Signup instance (ID#%s) is **not online or does not exist**. Please check Sender Settings.', 'awp'), esc_html( $signup_instanceId ));
                    $has_issues = true;
                } else {
                    $signup_status = __('OK', 'awp');
                    $signup_message = __('WhatsApp OTP Signup instance is configured and online.', 'awp');
                }
            }
        }
        $checks['otp_signup_instance'] = ['label' => __('WhatsApp OTP Signup Sender', 'awp'), 'status' => $signup_status, 'message' => $signup_message];

        /* -------- Checkout OTP -------- */
        $checkout_status  = __('Disabled', 'awp');
        $checkout_message = __('Feature is disabled.', 'awp');
        if ( $this->get_toggle( 'awp_wawp_otp_enabled' ) && $this->get_toggle( 'awp_checkout_otp_enabled' ) ) {
            $checkout_instance = get_option( 'awp_selected_instance', '' );
            if ( empty( $checkout_instance ) ) {
                $checkout_status  = __('Warning', 'awp');
                $checkout_message = __('Checkout OTP Verification is enabled but **no instance is selected** in Sender Settings.', 'awp');
                $has_issues       = true;
            } elseif ( ! in_array( $checkout_instance, $online_inst_ids, true ) ) {
                $checkout_status  = __('Error', 'awp');
                $checkout_message = sprintf(__('Checkout OTP Verification instance **%s is not online or does not exist**. Please check Sender Settings.', 'awp'), esc_html( $checkout_instance ));
                $has_issues = true;
            } else {
                $checkout_status = __('OK', 'awp');
                $checkout_message = __('Checkout OTP Verification instance is configured and online.', 'awp');
            }
        }
        $checks['checkout_otp_instance'] = ['label' => __('Checkout OTP Verification Sender', 'awp'), 'status' => $checkout_status, 'message' => $checkout_message];

        /* -------- Resend-failed sender -------- */
        $resend_instance = get_option( 'awp_selected_log_manager_instance', '' );
        $resend_status  = __('OK', 'awp');
        $resend_message = __('Resend Failed Messages is enabled but no instance is selected.', 'awp');
        if ( empty( $resend_instance ) ) {
            $resend_status  = __('Warning', 'awp');
            $resend_message = __('Resend Failed Messages is enabled but **no instance is selected** in Sender Settings.', 'awp');
            $has_issues     = true;
        } elseif ( ! in_array( $resend_instance, $online_inst_ids, true ) ) {
            $resend_status  = __('Error', 'awp');
            $resend_message = sprintf(__('Resend Failed Messages instance **%s is not online or does not exist**. Please check Sender Settings.', 'awp'), esc_html( $resend_instance ));
            $has_issues = true;
        } else {
            $resend_message = __('Resend Failed Messages instance is configured and online.', 'awp');
        }
        $checks['resend_failed_instance'] = ['label' => __('Resend Failed Messages Sender', 'awp'), 'status' => $resend_status, 'message' => $resend_message];

        /* -------- Woo-commerce notifications -------- */
        $notif_status  = __('Disabled', 'awp');
        $notif_message = __('Feature is disabled.', 'awp');
        if ($this->get_toggle('awp_notifications_enabled')) {
            $selected_notif_ids = AWP_Instances::get_notif_sender_instance_ids();
            if ( empty( $selected_notif_ids ) ) {
                $notif_status  = __('Warning', 'awp');
                $notif_message = __('WooCommerce Notifications are enabled but **no instances are selected** in Sender Settings. Messages won’t be sent.', 'awp');
                $has_issues    = true;
            } else {
                $offline_ids = [];
                foreach ( $selected_notif_ids as $row_id ) {
                    $found = false;
                    foreach ( $awp_instances->get_all_instances() as $inst ) {
                        if ( (int) $inst->id === (int) $row_id && $inst->status === 'online' ) {
                            $found = true;
                            break;
                        }
                    }
                    if ( ! $found ) {
                        $offline_ids[] = $row_id;
                    }
                }
                if ( ! empty( $offline_ids ) ) {
                    $notif_status  = __('Error', 'awp');
                    $notif_message = sprintf(__('Some WooCommerce Notification instances (IDs: %s) are **not online or do not exist**. Please check Sender Settings.', 'awp'), implode( ', ', $offline_ids ));
                    $has_issues = true;
                } else {
                    $notif_status = __('OK', 'awp');
                    $notif_message = __('WooCommerce Notification instances are configured and online.', 'awp');
                }
            }
        }
        $checks['woo_notifications_instance'] = ['label' => __('WooCommerce Notifications Sender', 'awp'), 'status' => $notif_status, 'message' => $notif_message];

        return [ 'report' => $checks, 'has_issues' => $has_issues ];
    }

    private function check_smtp_settings(): array {
        $smtp_opts = get_option('awp_smtp_settings', []);
        $smtp_enabled = !empty($smtp_opts['enabled']);

        if ($smtp_enabled) {
            $status = __('OK', 'awp');
            $message = __('Custom SMTP is **enabled** for sending emails.', 'awp');

            if (!empty($smtp_opts['auth'])) {
                if (empty($smtp_opts['host']) || empty($smtp_opts['user']) || empty($smtp_opts['pass'])) {
                    $status = __('Empty Auth', 'awp');
                    $message .= __(' However, SMTP is enabled but **host, username, or password** might be missing. Run a connection test.', 'awp');
                } else {
                    $message .= sprintf(__(' Host: %1$s, Port: %2$s, Encryption: %3$s.', 'awp'), esc_html($smtp_opts['host']), esc_html($smtp_opts['port']), esc_html($smtp_opts['encryption']));
                }
            } else {
                if (empty($smtp_opts['host'])) {
                    $status = __('Other', 'awp');
                    $message .= __(' However, SMTP is enabled but **host** might be missing. Run a connection test.', 'awp');
                } else {
                    $message .= sprintf(__(' Host: %1$s, Port: %2$s, Encryption: %3$s. (Authentication disabled)', 'awp'), esc_html($smtp_opts['host']), esc_html($smtp_opts['port']), esc_html($smtp_opts['encryption']));
                }
            }

        } else {
            $status = __('disabled', 'awp');
            $message = __('Custom SMTP is **disabled**. The plugin relies on WordPress\'s default mailer (which may use PHP mail() or your host\'s configuration).', 'awp');
        }

        return ['label' => __('SMTP Configuration', 'awp'), 'status' => $status, 'message' => $message, 'enabled' => $smtp_enabled];
    }
    
    public function get_cached_issue_count(): int {
        $cached = get_transient( 'awp_system_info_report_cached' );

        if ( ! $cached ) {
            $cached = $this->gather_all_checks();
            $this->run_checks_and_set_transient();
        }

        return $this->count_total_issues( $cached );
    }

    public function gather_all_checks(): array {
        if (!class_exists('AWP_Instances')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-instances.php';
        }
        if (!class_exists('AWP_Database_Manager')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        }
        $this->define_requirements();
        $this->define_cron_hooks();

        return [
            'db' => $this->run_db_checks(),
            'server_env_security' => $this->run_server_env_checks(),
            'cdn' => $this->detect_cdn_proxy(),
            'cron' => $this->run_cron_checks(),
            'wawp_instances' => $this->check_online_instances_exist(),
            'wawp_sender_configs' => $this->check_selected_instances_valid(),
            'wawp_smtp' => $this->check_smtp_settings()
        ];
    }

    public function run_checks_and_set_transient(int $expiration = 12 * HOUR_IN_SECONDS): void {
        set_transient('awp_system_info_report_cached', $this->gather_all_checks(), $expiration);
    }

    public function render_system_checker_admin_notice(): void {
        if (!current_user_can('manage_options')) return;
        
        $data = get_transient('awp_db_checker_report'); 
        if (!$data) return;
        delete_transient('awp_db_checker_report');

        $db_issue_found = false;
        if (!empty($data['db']['report'])) {
            foreach ($data['db']['report'] as $r) {
                if (!$r['exists'] || !empty($r['missing'])) {
                    $db_issue_found = true;
                    break;
                }
            }
        }

        if ($db_issue_found) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'AWP DB Checker:', 'awp' ) . '</strong> ' . esc_html__( 'Found one or more issues with the database tables.', 'awp' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info' ) ) . '">' . esc_html__( 'Click here to view the report and repair the database.', 'awp' ) . '</a></p></div>';
        }
    }


public function count_total_issues( array $data ): int {
    $issues = 0;

    // 1. Database tables
    if ( ! empty( $data['db']['has_issues'] ) ) {
        $issues++;
    }

    // 2. Core / security checks
    foreach ( $data['server_env_security'] as $check ) {
        if ( in_array( $check['status'], [ __('Error', 'awp'), __('Warning', 'awp') ], true ) ) {
            $issues++;
        }
    }

    // 3. Cron
    if ( ! $data['cron']['enabled'] || $data['cron']['has_issues'] ) {
        $issues++;
    }

    // 4. WhatsApp instances
    if ( $data['wawp_instances']['status'] !== __('OK', 'awp') ) {
        $issues++;
    }

    // 5. Sender‑selection problems
    foreach ( $data['wawp_sender_configs']['report'] as $sc ) {
        if ( in_array( $sc['status'], [ __('Error', 'awp'), __('Warning', 'awp') ], true ) ) {
            $issues++;
        }
    }

    return $issues;
}

    /**
     * New: Get all wawp/awp database tables.
     * @return array
     */
    private function get_awp_tables(): array {
        $prefix = $this->wpdb->prefix;
        $awp_tables = $this->wpdb->get_col($this->wpdb->prepare("SHOW TABLES LIKE %s", $prefix . 'awp_%'));
        $wawp_tables = $this->wpdb->get_col($this->wpdb->prepare("SHOW TABLES LIKE %s", $prefix . 'wawp_%'));
        return array_merge($awp_tables, $wawp_tables);
    }

    /**
     * New: Handle AJAX request to truncate a database table.
     */
    public function handle_ajax_truncate_table() {
        check_ajax_referer('awp_truncate_table_nonce', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'awp')]);
        }

        $table_name = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        if (!class_exists('AWP_Database_Manager')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        }
        $db_manager = new AWP_Database_Manager();
        $allowed_tables = $db_manager->tables; 

        if (empty($table_name) || !in_array($table_name, $allowed_tables, true)) {
            wp_send_json_error(['message' => __('Invalid or disallowed table name.', 'awp')]);
        }

        $result = $this->wpdb->query("TRUNCATE TABLE `{$table_name}`");

        if ($result !== false) {
            wp_send_json_success(['message' => sprintf(__('Table %s has been emptied.', 'awp'), $table_name)]);
        } else {
            wp_send_json_error(['message' => sprintf(__('Failed to empty table %s.', 'awp'), $table_name)]);
        }
    }

    public function awp_admin_page_content() {
        $banned_msg  = get_transient('siteB_banned_msg');
        $token       = get_option('mysso_token');
        $user_data   = get_transient('siteB_user_data');
        $current_dom = parse_url(get_site_url(), PHP_URL_HOST);

        if ($banned_msg) {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('System Status', 'awp') . '</h1><p style="color:red;">' . esc_html(Wawp_Global_Messages::get('blocked_generic')) . '</p></div>';
            return;
        }
        if (!$token) {
            echo '<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> ' . esc_html__('System Status', 'awp') . '</h1><p>' . esc_html(Wawp_Global_Messages::get('need_login')) . '</p></div>';
            return;
        }
        if ($user_data && isset($user_data['sites'][$current_dom]) && $user_data['sites'][$current_dom] !== 'active') {
            echo '<div class="wrap"><h1><i class="ri-lock-line"></i> ' . esc_html__('System Status', 'awp') . '</h1><p style="color:red;">' . esc_html(Wawp_Global_Messages::get('not_active_site')) . '</p></div>';
            return;
        }

        $system_checker_data = $this->gather_all_checks();
        $this->run_checks_and_set_transient();
        $system_info = $this->awp_get_system_info();
        $this->handle_sync_users();

        $show_repair_all_button = $this->count_total_issues($system_checker_data) > 0;
        ?>
        <style>
            .awp-loading-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.9);display:flex;justify-content:center;align-items:center;z-index:99999;flex-direction:column;font-size:1.2em;color:#555;text-align:center;}
            .awp-spinner{border:4px solid rgba(0,0,0,0.1);border-left-color:#007cba;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin-bottom:15px;}
            @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
            body.wp-admin #wpwrap{opacity:0;transition:opacity .3s ease-in-out;}
            body.wp-admin.awp-loaded #wpwrap{opacity:1;}
            .awp-status-disabled { color: #888; font-weight: bold; }
            .button.button-danger { background: #d63638; border-color: #b62d2f; color: #fff; }
            .button.button-danger:hover, .button.button-danger:focus { background: #e04345; border-color: #d63638; }
        </style>

        <div id="awp-loading-overlay" class="awp-loading-overlay"><div class="awp-spinner"></div><p><?php echo esc_html__('Loading system health data...', 'awp'); ?></p></div>
        
        <div>
            <div class="page-header_row">
                <div>
                    <h2 class="page-title"><?php echo esc_html__('Wawp System Status', 'awp'); ?></h2>
                    <p><?php echo esc_html__('Review your site\'s configuration and Wawp feature status. You can repair issues directly from this page.', 'awp'); ?></p>
                    <?php
                    $total_issues = $this->count_total_issues( $system_checker_data );
                    if ($total_issues > 0) {
                        echo '<p style="margin:4px 0 1em;color:#c00;font-weight:600;">' .
                            sprintf(esc_html__( 'Total issues that need fixing: %d', 'awp' ), $total_issues) .
                            '</p>';
                    } else {
                        echo '<div class="notice notice-success inline" style="margin-bottom:1em;"><p style="margin:0;">' . esc_html__('No issues found. Your system is configured correctly for all enabled features.', 'awp') . '</p></div>';
                    }
                    ?>
                </div>
                <div style="display:flex;align-items:stretch;gap:12px;">
                    <?php if ($show_repair_all_button) : ?>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=awp_repair_all'), 'awp_repair_all_nonce')); ?>" class="hint-btn awp-btn primary" style="align-self:center;"><i class="ri-tools-fill"></i> <?php echo esc_html__('Repair All Issues', 'awp'); ?></a>
                    <?php endif; ?>
                    <form method="post" target="wawp-sync-frame" style="display:inline-block;">
                        <?php wp_nonce_field('awp_sync_users_action', 'awp_sync_users_nonce'); ?>
                        <button type="submit" name="awp_sync_users" class="hint-btn awp-btn secondary"><i class="ri-user-received-line"></i> <?php echo esc_html__('Sync Users', 'awp'); ?></button>
                    </form>
                </div>
            </div>

            <?php
            if (isset($_GET['repaired_all']) && $_GET['repaired_all'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('All issues repaired successfully.', 'awp') . '</strong>' . (isset($_GET['senders_assigned']) && $_GET['senders_assigned'] === 'true' ? ' ' . esc_html__('Missing sender settings were auto-assigned with the first online instance.', 'awp') : '') . '</p></div>';
            }
            if (isset($_GET['repaired'])) {
                if ($_GET['repaired'] === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('Database tables repaired successfully.', 'awp') . '</strong></p></div>';
                } elseif ($_GET['repaired'] === 'failed_not_found') {
                    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Repair failed:', 'awp') . '</strong> ' . esc_html__('Could not locate', 'awp') . ' <code>/awp/includes/class-database-manager.php</code>. ' . esc_html__('Please ensure the main Wawp plugin is installed and activated.', 'awp') . '</p></div>';
                } elseif ($_GET['repaired'] === 'failed_class_missing') {
                    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Repair failed:', 'awp') . '</strong> ' . esc_html__('Found the file, but the', 'awp') . ' <code>AWP_Database_Manager</code> ' . esc_html__('class is missing. The main Wawp plugin might be corrupted.', 'awp') . '</p></div>';
                }
            }
            if (isset($_GET['scheduled'])) {
                if ($_GET['scheduled'] === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('Cron job scheduled successfully.', 'awp') . '</strong> ' . esc_html__('It will run on the next available opportunity.', 'awp') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Failed to schedule cron job.', 'awp') . '</strong> ' . esc_html__('It might already be scheduled or an error occurred.', 'awp') . '</p></div>';
                }
            }
            ?>

            <iframe id="wawp-sync-frame" name="wawp-sync-frame" style="width:100%;height:200px;border:1px solid #ccc;display:none;"></iframe>
            <?php if ($this->sync_users_html) { echo $this->sync_users_html; } ?>

            <div class="sysinfo-cards" style="align-items:start;">

            <div class="awp-cards" style="align-items:start;">

                <div class="awp-card">
                    <div class="table-card-header" style="flex-direction:column;align-items:start;">
                        <h4 class="card-title"><i class="ri-earth-line" style="padding-right:5px;"></i><?php echo esc_html__('WordPress Environment', 'awp'); ?></h4>
                        <p><?php echo esc_html__('General information about your WordPress setup.', 'awp'); ?></p>
                    </div>
                    <table class="awp-box">
                        <thead><tr><th class="info-td"><?php echo esc_html__('Setting', 'awp'); ?></th><th class="info-td"><?php echo esc_html__('Value', 'awp'); ?></th></tr></thead>
                        <tbody>
                            <tr><td><?php echo esc_html__('Home URL:', 'awp'); ?></td><td><?php echo esc_url($system_info['home_url']); ?></td></tr>
                            <tr><td><?php echo esc_html__('Site URL:', 'awp'); ?></td><td><?php echo esc_url($system_info['site_url']); ?></td></tr>
                            <tr><td><?php echo esc_html__('WP Version:', 'awp'); ?></td><td class="<?php echo $system_info['wp_version_class']; ?>"><?php echo esc_html($system_info['wp_version']); ?></td></tr>
                            <tr><td><?php echo esc_html__('WP Multisite:', 'awp'); ?></td><td><?php echo esc_html($system_info['wp_multisite']); ?></td></tr>
                            <tr><td><?php echo esc_html__('System Language:', 'awp'); ?></td><td><?php echo esc_html($system_info['system_language']); ?>, <?php echo esc_html__('direction:', 'awp'); ?> <?php echo ($system_info['rtl'] ? 'RTL' : 'LTR'); ?></td></tr>
                            <tr><td><?php echo esc_html__('Your Language:', 'awp'); ?></td><td><?php echo esc_html($system_info['user_language']); ?></td></tr>
                            <tr><td><?php echo esc_html__('WooCommerce:', 'awp'); ?></td><td><?php echo $system_info['woocommerce'] ? esc_html__('Enabled', 'awp') : esc_html__('Disabled', 'awp'); ?></td></tr>
                            <tr><td><?php echo esc_html__('Uploads folder writable:', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['uploads_writable'], 'Writable'); ?>"><?php echo esc_html($system_info['uploads_writable']); ?></td></tr>
                            <tr><td><?php echo esc_html__('.htaccess File Access:', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['htaccess'], 'Found'); ?>"><?php echo esc_html($system_info['htaccess']); ?></td></tr>
                            <tr><td><?php echo esc_html__('Wawp Plugin Version:', 'awp'); ?></td><td><?php echo esc_html($system_info['plugin_version']); ?></td></tr>
                            <tr><td><?php echo esc_html__('Last Update Date:', 'awp'); ?></td><td><?php echo esc_html($system_info['last_update_date']); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="awp-card">
                    <div class="table-card-header" style="flex-direction:column;align-items:start;">
                        <h4 class="card-title"><i class="ri-settings-line" style="padding-right:5px;"></i><?php echo esc_html__('Wawp System Health Checks', 'awp'); ?></h4>
                        <p><?php echo esc_html__('Detailed checks for Wawp plugin specific configurations and dependencies.', 'awp'); ?></p>
                    </div>
                    <table class="awp-box">
                        <thead><tr><th><?php echo esc_html__('Check', 'awp'); ?></th><th><?php echo esc_html__('Status', 'awp'); ?></th><th><?php echo esc_html__('Details', 'awp'); ?></th></tr></thead>
                        <tbody>
                            <?php
                            echo '<tr><td>' . esc_html__('CDN Provider', 'awp') . '</td><td>' . esc_html($system_checker_data['cdn']['vendor']) . '</td><td>' . ($system_checker_data['cdn']['proxy'] ? '<span style="color:#e67e22;font-weight:bold;">' . esc_html__('Proxy Detected', 'awp') . '</span>' : '<span style="color:#2ecc71;font-weight:bold;">' . esc_html__('No Proxy', 'awp') . '</span>') . '</td></tr>';
                            echo '<tr><td>' . esc_html($system_checker_data['wawp_instances']['label']) . '</td><td><span style="font-weight:bold;color:' . ($system_checker_data['wawp_instances']['status'] === __('OK', 'awp') ? '#2ecc71' : '#c00') . ';">' . esc_html($system_checker_data['wawp_instances']['status']) . '</span></td><td>' . wp_kses_post($system_checker_data['wawp_instances']['message']) . '</td></tr>';
                            
                            foreach ($system_checker_data['wawp_sender_configs']['report'] as $config_check) {
                                $status_color = '#888';
                                if ($config_check['status'] !== __('Disabled', 'awp')) {
                                    $status_color = ($config_check['status'] === __('OK', 'awp') ? '#2ecc71' : ($config_check['status'] === __('Warning', 'awp') ? '#e67e22' : '#c00'));
                                }
                                echo '<tr><td>' . esc_html($config_check['label']) . '</td><td><span style="font-weight:bold;color:' . $status_color . ';">' . esc_html($config_check['status']) . '</span></td><td>' . wp_kses_post($config_check['message']) . '</td></tr>';
                            }
                            
                            echo '<tr><td>' . esc_html($system_checker_data['wawp_smtp']['label']) . '</td><td><span style="font-weight:bold;color:' . ($system_checker_data['wawp_smtp']['status'] === __('OK', 'awp') ? '#2ecc71' : ($system_checker_data['wawp_smtp']['status'] === __('Warning', 'awp') ? '#e67e22' : '#c00')) . ';">' . esc_html($system_checker_data['wawp_smtp']['status']) . '</span></td><td>' . wp_kses_post($system_checker_data['wawp_smtp']['message']) . '</td></tr>';
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="awp-card">
                    <div class="table-card-header" style="flex-direction:column;align-items:start;">
                        <h4 class="card-title"><i class="ri-settings-2-line" style="padding-right:5px;"></i><?php echo esc_html__('Server Environment', 'awp'); ?></h4>
                        <p><?php echo esc_html__('Meet system requirements for optimal Wawp plugin performance.', 'awp'); ?></p>
                    </div>
                    <table class="awp-box">
                        <thead><tr><th><?php echo esc_html__('Requirement', 'awp'); ?></th><th><?php echo esc_html__('Your System', 'awp'); ?></th></tr></thead>
                        <tbody>
                            <tr><td><?php echo esc_html__('MySQL Version (5.6+):', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['mysql_version'], '5.6'); ?>"><?php echo esc_html($system_info['mysql_version']); ?></td></tr>
                            <tr><td><?php echo esc_html__('PHP Version (7.4+):', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['php_version'], '7.4'); ?>"><?php echo esc_html($system_info['php_version']); ?></td></tr>
                            <tr><td><?php echo esc_html__('PHP Post Max Size (2M+):', 'awp'); ?></td><td class="<?php echo (isset($system_info['post_max_size_bytes']) && $system_info['post_max_size_bytes'] >= 2097152 ? 'status-true' : 'status-false'); ?>"><?php echo esc_html($system_info['post_max_size']); ?></td></tr>
                            <tr><td><?php echo esc_html__('PHP Memory Limit (1024M+):', 'awp'); ?></td><td class="<?php echo (isset($system_info['php_memory_limit_bytes']) && $system_info['php_memory_limit_bytes'] >= 1073741824 ? 'status-true' : 'status-false'); ?>"><?php echo esc_html($system_info['php_memory_limit']); ?></td></tr>
                            <tr><td><?php echo esc_html__('PHP Time Limit (300+):', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['php_time_limit'], '300'); ?>"><?php echo esc_html($system_info['php_time_limit']); ?></td></tr>
                            <tr><td><?php echo esc_html__('PHP Max Input Vars (2500+):', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['php_max_input_vars'], '2500'); ?>"><?php echo esc_html($system_info['php_max_input_vars']); ?></td></tr>
                            <tr><td><?php echo esc_html__('Max Upload Size (2M+):', 'awp'); ?></td><td class="<?php echo (isset($system_info['wp_max_upload_size_bytes']) && $system_info['wp_max_upload_size_bytes'] >= 2097152 ? 'status-true' : 'status-false'); ?>"><?php echo esc_html($system_info['wp_max_upload_size']); ?></td></tr>
                            <tr><td><?php echo esc_html__('ZipArchive:', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['ziparchive'], 'Enabled'); ?>"><?php echo esc_html($system_info['ziparchive']); ?></td></tr>
                            <tr><td><?php echo esc_html__('WP Remote Get:', 'awp'); ?></td><td class="<?php echo $this->awp_status_class($system_info['wp_remote_get'], 'Enabled'); ?>"><?php echo esc_html($system_info['wp_remote_get']); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="awp-card">
                    <div class="table-card-header" style="flex-direction:column;align-items:start;">
                        <h4 class="card-title"><i class="ri-time-line" style="padding-right:5px;"></i><?php echo esc_html__('WP-Cron Status', 'awp'); ?></h4>
                        <p><?php echo esc_html__('Checks if scheduled tasks for enabled features are running correctly.', 'awp'); ?></p>
                    </div>
                    <?php if ($system_checker_data['cron']['enabled']) : ?>
                        <table class="awp-box">
                            <thead><tr><th><?php echo esc_html__('Hook', 'awp'); ?></th><th><?php echo esc_html__('Status', 'awp'); ?></th><th><?php echo esc_html__('Next Run (UTC)', 'awp'); ?></th><th><?php echo esc_html__('Action', 'awp'); ?></th></tr></thead>
                            <tbody>
                                <?php
                                $all_possible_crons = [
                                    'awp_cron_auto_resend', 'awp_cron_refresh_system_info',
                                    'awp_cron_auto_clear_logs', 'awp_cron_hourly_self_repair', 
                                    'wp_campaigns_cron_send_advanced',
                                    'awp_cron_check_delivery_status', 'awp_cron_recheck_delivery_status'
                                ];
                                $cron_report_map = [];
                                foreach ($system_checker_data['cron']['events'] as $event) {
                                    $cron_report_map[$event['hook']] = $event;
                                }

                                foreach ($all_possible_crons as $hook) {
                                    if (array_key_exists($hook, $cron_report_map)) {
                                        $event = $cron_report_map[$hook];
                                        $missing = !$event['scheduled'];
                                        $next_run = $missing ? '—' : gmdate('Y-m-d H:i:s', $event['next_run']) . ' (' . human_time_diff($event['next_run'], time()) . ')';
                                        $status_html = $missing ? '<span style="color:#c00;font-weight:bold;">' . esc_html__('Not Scheduled', 'awp') . '</span>' : '<span style="color:#2ecc71;font-weight:bold;">' . esc_html__('Scheduled', 'awp') . '</span>';
                                        $action_html = $missing ? '<a href="' . esc_url(wp_nonce_url(admin_url('admin.php?action=awp_schedule_cron&hook=' . $event['hook']), 'awp_schedule_cron_nonce')) . '" class="button button-secondary">' . esc_html__('Schedule Now', 'awp') . '</a>' : '—';
                                    } else {
                                        $status_html = '<span class="awp-status-disabled">' . esc_html__('Disabled', 'awp') . '</span>';
                                        $next_run = '—';
                                        $action_html = '—';
                                    }
                                    echo '<tr><td><code>' . esc_html($hook) . '</code></td><td>' . $status_html . '</td><td>' . esc_html($next_run) . '</td><td>' . $action_html . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="notice notice-error inline"><p><strong><?php echo esc_html__('WP-Cron is disabled.', 'awp'); ?></strong> <?php echo esc_html__('Scheduled tasks will not run. Remove (or set to false) the DISABLE_WP_CRON constant in wp-config.php, or configure a real server cron job that hits wp-cron.php.', 'awp'); ?></p></div>
                    <?php endif; ?>
                </div>

                <div class="awp-card">
                    <div class="table-card-header" style="flex-direction:column;align-items:start;">
                        <h4 class="card-title"><i class="ri-shield-check-line" style="padding-right:5px;"></i><?php echo esc_html__('Security & Core Configuration', 'awp'); ?></h4>
                        <p><?php echo esc_html__('Important settings for site security and WordPress core functionality.', 'awp'); ?></p>
                    </div>
                    <table class="awp-box">
                        <thead><tr><th><?php echo esc_html__('Check', 'awp'); ?></th><th><?php echo esc_html__('Status', 'awp'); ?></th><th><?php echo esc_html__('Details', 'awp'); ?></th></tr></thead>
                        <tbody>
                            <?php
                            foreach ($system_checker_data['server_env_security'] as $check_data) {
                                $status_color = ($check_data['status'] === __('OK', 'awp') ? '#2ecc71' : ($check_data['status'] === __('Warning', 'awp') ? '#e67e22' : '#c00'));
                                echo '<tr><td>' . esc_html($check_data['label']) . '</td><td><span style="font-weight:bold;color:' . $status_color . ';">' . esc_html($check_data['status']) . '</span></td><td>' . wp_kses_post($check_data['message']) . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Dangerous Zone -->
                <div class="awp-card" style="border-color: #d63638;">
                    <div class="table-card-header" style="flex-direction:column;align-items:start;">
                        <h4 class="card-title" style="color: #d63638;"><i class="ri-alert-line" style="padding-right:5px;"></i><?php echo esc_html__('Dangerous Zone', 'awp'); ?></h4>
                        <p><?php echo esc_html__('These actions are destructive and will permanently delete data. This cannot be undone. Proceed with extreme caution.', 'awp'); ?></p>
                    </div>
                    <table class="awp-box">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Table Name', 'awp'); ?></th>
                                <th><?php echo esc_html__('Status', 'awp'); ?></th>
                                <th><?php echo esc_html__('Missing Columns', 'awp'); ?></th>
                                <th style="text-align: right;"><?php echo esc_html__('Action', 'awp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!class_exists('AWP_Database_Manager')) {
                                require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
                            }
                            $db_manager = new AWP_Database_Manager();
                            $all_awp_tables = $db_manager->tables;
                            
                            $db_report_map = [];
                            foreach ($system_checker_data['db']['report'] as $report_item) {
                                $db_report_map[$report_item['table']] = $report_item;
                            }

                            if (!empty($all_awp_tables)) {
                                foreach ($all_awp_tables as $table) {
                                    $table_exists = $this->table_exists($table);
                                    $status_html = '';
                                    $missing_html = '—';

                                    if (array_key_exists($table, $db_report_map)) {
                                        $row = $db_report_map[$table];
                                        $status_html = (!$row['exists']) 
                                            ? '<span style="color:#c00;font-weight:bold;">' . esc_html__('Not Found', 'awp') . '</span>' 
                                            : (!empty($row['missing']) 
                                                ? '<span style="color:#e67e22;font-weight:bold;">' . esc_html__('Incomplete', 'awp') . '</span>' 
                                                : '<span style="color:#2ecc71;font-weight:bold;">' . esc_html__('OK', 'awp') . '</span>');
                                        $missing_html = !empty($row['missing']) ? '<code>' . esc_html(implode(', ', $row['missing'])) . '</code>' : '—';
                                    } else {
                                        $status_html = $table_exists 
                                            ? '<span style="color:#2ecc71;font-weight:bold;">' . esc_html__('OK', 'awp') . '</span>'
                                            : '<span class="awp-status-disabled">' . esc_html__('Not Found', 'awp') . '</span>';
                                    }

                                    echo '<tr>';
                                    echo '<td><code>' . esc_html($table) . '</code></td>';
                                    echo '<td>' . $status_html . '</td>';
                                    echo '<td>' . $missing_html . '</td>';
                                    echo '<td style="text-align: right;">';

                                    if ($table_exists) {
                                        echo '<button class="button awp-drop-table-btn" data-table="' . esc_attr($table) . '">' . esc_html__('Delete', 'awp') . '</button> ';
                                        echo '<button class="button awp-truncate-table-btn" data-table="' . esc_attr($table) . '">' . esc_html__('Empty', 'awp') . '</button>';
                                    } else {
                                        echo '<button class="button button-primary awp-create-table-btn" data-table="' . esc_attr($table) . '">' . esc_html__('Create Table', 'awp') . '</button> ';
                                    }
                                    
                                    echo '<span class="awp-truncate-status" style="margin-left:10px;font-weight:bold;"></span>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="4">' . esc_html__('No Wawp tables found.', 'awp') . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php wp_nonce_field('awp_truncate_table_nonce', 'awp_truncate_table_nonce_field'); ?>
                <!-- End Dangerous Zone -->

            </div>  
        </div>  
        </div>

        <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            var loadingOverlay = document.getElementById("awp-loading-overlay");
            if (loadingOverlay) {
                loadingOverlay.style.display = "none";
                document.body.classList.add("awp-loaded");
            }
        });

        function handleToggle(checkbox) {
            const optionName = checkbox.dataset.option;
            const optionValue = checkbox.checked ? 1 : 0;
            const nonce = document.getElementById('awp_live_toggle_nonce').value;

            const formData = new FormData();
            formData.append('action', 'awp_save_toggle');
            formData.append('option_name', optionName);
            formData.append('option_value', optionValue);
            formData.append('_wpnonce', nonce);

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(optionName + ' saved to ' + optionValue);
                    location.reload(); 
                } else {
                    console.error('Failed to save ' + optionName);
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function handleOtpParentToggle(checkbox) {
            const is_checked = checkbox.checked;
            const sub_toggles = document.querySelectorAll('.awp-sub-toggle input[type="checkbox"]');
            sub_toggles.forEach(toggle => {
                toggle.disabled = !is_checked;
                if (!is_checked) {
                    toggle.checked = false;
                }
            });
            handleToggle(checkbox);
        }

        // JS for Dangerous Zone
        document.addEventListener('click', function (e) {
            const isCreate = e.target.matches('.awp-create-table-btn');
            const isDrop   = e.target.matches('.awp-drop-table-btn');
            const isEmpty  = e.target.matches('.awp-truncate-table-btn');

            if (!isCreate && !isDrop && !isEmpty) {
                // Reset any buttons awaiting second-click confirmation
                document.querySelectorAll('.awp-create-table-btn.confirming, .awp-drop-table-btn.confirming, .awp-truncate-table-btn.confirming').forEach(btn => {
                    btn.classList.remove('confirming', 'button-danger');
                    btn.textContent  = btn.dataset.originalText || btn.textContent;
                    const status = btn.parentElement.querySelector('.awp-truncate-status');
                    if (status) status.textContent = '';
                });
                return;
            }

            e.preventDefault();
            const button = e.target;
            const table  = button.dataset.table;
            const status = button.parentElement.querySelector('.awp-truncate-status');
            const action = isCreate ? 'awp_create_table' : isDrop ? 'awp_drop_table' : 'awp_truncate_table';
            const defaultLabel = isCreate ? '<?php echo esc_js(__('Create Table',  'awp')); ?>'
                               : isDrop   ? '<?php echo esc_js(__('Delete',  'awp')); ?>'
                               :           '<?php echo esc_js(__('Empty',   'awp')); ?>';
            const workingLabel  = isCreate ? '<?php echo esc_js(__('Creating...', 'awp')); ?>'
                               : isDrop   ? '<?php echo esc_js(__('Deleting...', 'awp')); ?>'
                               :           '<?php echo esc_js(__('Emptying...',  'awp')); ?>';
            const doneLabel     = isCreate ? '<?php echo esc_js(__('Created', 'awp')); ?>'
                               : isDrop   ? '<?php echo esc_js(__('Deleted', 'awp')); ?>'
                               :           '<?php echo esc_js(__('Emptied', 'awp')); ?>';

            if (!button.classList.contains('confirming')) {
                // first click – ask for confirmation
                button.dataset.originalText = defaultLabel;
                button.classList.add('confirming', 'button-danger');
                button.textContent = '<?php echo esc_js(__('Confirm?', 'awp')); ?>';
                return;
            }

            // second click – perform AJAX
            button.disabled  = true;
            button.textContent = workingLabel;
            if (status) status.textContent = '';

            const formData = new FormData();
            formData.append('action',   action);
            formData.append('_wpnonce', document.getElementById('awp_truncate_table_nonce_field').value);
            formData.append('table',    table);

            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    button.classList.remove('confirming', 'button-danger');
                    if (data.success) {
                        button.textContent = doneLabel;
                        button.classList.add('button-primary');
                        if (status) { status.textContent = '<?php echo esc_js(__('Success!', 'awp')); ?>'; status.style.color = '#28a745'; }
                         // Reload the page to reflect the change (e.g., button state)
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        button.textContent = '<?php echo esc_js(__('Error!', 'awp')); ?>';
                        button.disabled = false;
                        if (status) { status.textContent = data.data.message || '<?php echo esc_js(__('An error occurred.', 'awp')); ?>'; status.style.color = '#d63638'; }
                    }
                })
                .catch(() => {
                    button.textContent = '<?php echo esc_js(__('Error!', 'awp')); ?>';
                    button.disabled = false;
                    button.classList.remove('confirming', 'button-danger');
                    if (status) { status.textContent = '<?php echo esc_js(__('Request failed.', 'awp')); ?>'; status.style.color = '#d63638'; }
                });
        });

        </script>
        <?php
    }


public function handle_ajax_create_table() {
    check_ajax_referer('awp_truncate_table_nonce', '_wpnonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied.', 'awp')]);
    }

    $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';

    if (!class_exists('AWP_Database_Manager')) {
        require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
    }
    $db  = new AWP_Database_Manager();

    // Only allow our own tables
    if (!in_array($table, array_values($db->tables), true)) {
        wp_send_json_error(['message' => __('Invalid or disallowed table name for creation.', 'awp')]);
    }

    // (Re)create schema
    $db->create_all_tables();
    // Ensure any missing columns are added (uses SHOW/ALTER internally)
    $db->ensure_all_columns();

    // Verify the table now exists
    $exists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        wp_send_json_error([
            'message' => sprintf(__('Failed to create table %s.', 'awp'), $table),
            'mysql'   => $this->wpdb->last_error ?: __('Unknown MySQL error', 'awp'),
        ]);
    }

    // Extra safety: ensure critical columns are present
    $cols = array_map('strtolower', $this->wpdb->get_col("DESCRIBE `$table`", 0));
    $must_have = ['id','sent_at','whatsapp_number','message','message_type','wawp_status','delivery_ack','delivery_check_count','next_check_at'];
    $missing = array_diff($must_have, $cols);

    if (!empty($missing)) {
        wp_send_json_error([
            'message' => sprintf(__('Table %s created but missing columns: %s', 'awp'), $table, implode(', ', $missing)),
            'hint'    => __('Run “Repair All Issues” or re-click Create.', 'awp'),
        ]);
    }

    wp_send_json_success([
        'message' => sprintf(__('Table %s is ready.', 'awp'), $table)
    ]);
}

    
    public function handle_ajax_drop_table() {
        check_ajax_referer('awp_truncate_table_nonce', '_wpnonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'awp')]);
        }
    
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        if (!class_exists('AWP_Database_Manager')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-database-manager.php';
        }
        $db = new AWP_Database_Manager();
        if (!in_array($table, array_values($db->tables), true)) {
            wp_send_json_error(['message' => __('Invalid or disallowed table name for deletion.', 'awp')]);
        }
    
        $dropped = $this->wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        if ($dropped !== false) {
            wp_send_json_success(['message' => sprintf(__('Table %s has been deleted.', 'awp'), $table)]);
        }
        wp_send_json_error(['message' => sprintf(__('Failed to delete table %s.', 'awp'), $table)]);
    }



    public function awp_get_system_info() {
        $plugin_data = get_plugin_data(AWP_PLUGIN_DIR . 'class-awp.php');
        $plugin_version = $plugin_data['Version'] ?? esc_html__('Unknown','awp');
        $plugin_file = AWP_PLUGIN_DIR . 'class-awp.php';
        $last_update_date = file_exists($plugin_file) ? esc_html(gmdate('F d Y, H:i:s', filemtime($plugin_file))) : esc_html__('File not found','awp');

        global $wpdb;
        $mysql_version = esc_html($wpdb->db_version());
        $woocommerce_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true);
        
        $php_memory_limit_bytes = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $post_max_size_bytes = wp_convert_hr_to_bytes(ini_get('post_max_size'));
        $wp_max_upload_size_bytes = wp_max_upload_size();

        $wp_version = get_bloginfo('version');
        $latest_wp_version = get_transient('wp_latest_version');
        if (false === $latest_wp_version) {
            $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
            if (!is_wp_error($response) && $response['response']['code'] === 200) {
                $body = json_decode($response['body']);
                $latest_wp_version = $body->offers[0]->current;
                set_transient('wp_latest_version', $latest_wp_version, 12 * HOUR_IN_SECONDS);
            }
        }
        
        $remote_status_cached = get_transient( 'awp_remote_status_ok' );

        if ( false === $remote_status_cached ) {
            $response = wp_remote_head(
                home_url(),
                [
                    'timeout'     => 1,
                    'redirection' => 0,
                    'blocking'    => true,
                ]
            );
        
            $remote_status_cached = ( ! is_wp_error( $response ) &&
                                      wp_remote_retrieve_response_code( $response ) < 400 )
                ? __( 'Enabled', 'awp' )
                : __( 'Disabled', 'awp' );
        
            set_transient( 'awp_remote_status_ok', $remote_status_cached, 12 * HOUR_IN_SECONDS );
        }
        
        
        $wp_version_class = 'status-false';
        if ($latest_wp_version && version_compare($wp_version, $latest_wp_version, '>=')) {
            $wp_version_class = 'status-true';
        }


        return [
            'php_version'        => esc_html(phpversion()),
            'php_memory_limit'   => esc_html(ini_get('memory_limit')),
            'php_memory_limit_bytes' => $php_memory_limit_bytes,
            'php_time_limit'     => esc_html(ini_get('max_execution_time')),
            'php_max_input_vars' => esc_html(ini_get('max_input_vars')),
            'ziparchive'         => class_exists('ZipArchive') ? esc_html__('Enabled','awp') : esc_html__('Disabled','awp'),
            'uploads_writable'   => is_writable(wp_upload_dir()['basedir']) ? esc_html__('Writable','awp') : esc_html__('Not Writable','awp'),
            'htaccess'           => file_exists(ABSPATH . '.htaccess') ? esc_html__('Found','awp') : esc_html__('Not Found','awp'),
            'home_url'           => esc_url(home_url()),
            'site_url'           => esc_url(site_url()),
            'wp_version'         => $wp_version,
            'wp_version_class'   => $wp_version_class,
            'wp_max_upload_size' => esc_html(size_format($wp_max_upload_size_bytes)),
            'wp_max_upload_size_bytes' => $wp_max_upload_size_bytes,
            'post_max_size'      => esc_html(ini_get('post_max_size')),
            'post_max_size_bytes' => $post_max_size_bytes,
            'wp_multisite'       => is_multisite() ? esc_html__('Enabled','awp') : esc_html__('Disabled','awp'),
            'system_language'    => esc_html(get_option('WPLANG') ?: 'en_US'),
            'user_language'      => esc_html(get_user_locale()),
            'rtl'                => is_rtl(),
            'mysql_version'      => $mysql_version,
            'wp_remote_get'      => esc_html( $remote_status_cached ),
            'woocommerce'        => $woocommerce_active,
            'plugin_version'     => $plugin_version,
            'last_update_date'   => $last_update_date,
        ];
    }

    public function awp_status_class($value, $required) {
        if (!is_numeric(substr($value, 0, 1)) && !is_numeric(substr($required, 0, 1))) {
            $required_translated = $required === 'Enabled' ? __('Enabled', 'awp') : $required;
            $required_translated = $required === 'Writable' ? __('Writable', 'awp') : $required_translated;
            $required_translated = $required === 'Found' ? __('Found', 'awp') : $required_translated;
            return $value === $required_translated ? 'status-true' : 'status-false';
        }

        if (is_numeric($value) && is_numeric($required)) {
            return (int)$value >= (int)$required ? 'status-true' : 'status-false';
        }
        
        return version_compare($value, $required, '>=') ? 'status-true' : 'status-false';
    }

    public function check_wc_order_storage_settings() {
        if (isset($_GET['awp_dismiss_notice']) && $_GET['awp_dismiss_notice'] === '1') {
            update_option('awp_wc_order_storage_notice_dismissed', 'yes');
            wp_redirect(remove_query_arg('awp_dismiss_notice'));
            exit;
        }
        $hp_order_storage   = get_option('woocommerce_high_performance_order_storage', 'no');
        $compatibility_mode = get_option('woocommerce_enable_compatibility_mode', 'no');
        if (
            ($hp_order_storage !== 'yes' || $compatibility_mode !== 'yes') &&
            get_option('awp_wc_order_storage_notice_dismissed', 'no') !== 'yes'
        ) {
            add_action('admin_notices', [$this, 'display_wc_order_storage_notice']);
        }
    }

    public function display_wc_order_storage_notice() {
        if (get_option('awp_wc_order_storage_notice_dismissed','no') === 'yes') {
            return;
        }

        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $dismiss_url  = admin_url('admin.php?page=' . $current_page . '&awp_dismiss_notice=1');
        
        echo '<div class="notice notice-warning is-dismissible"><p>'
             . esc_html__('Wawp recommends enabling "High-performance order storage" and "Enable compatibility mode" in WooCommerce for better performance.', 'awp')
             . '</p><p><a href="'
             . esc_url(admin_url('admin.php?page=wc-settings&tab=advanced&section=features'))
             . '" class="button" style="background-color:#28a745;border-color:#28a745;">'
             . esc_html__('Enable these options', 'awp')
             . '</a> <a href="'
             . esc_url($dismiss_url)
             . '" class="button button-primary">'
             . esc_html__('I have already activated the setting', 'awp')
             . '</a></p></div>';
    }
    
    public function handle_sync_users() {
        if (!isset($_POST['awp_sync_users'])) return;
        check_admin_referer('awp_sync_users_action','awp_sync_users_nonce');

        ob_start();
        list($success, $logs) = $this->sync_users_info();
        echo '<div class="awp-sync-users" style="border:1px solid #ccc; background:#f9f9f9; padding:10px; margin-bottom:1rem;">';
        if ($success) {
            echo '<p style="color:#2E8540; margin:0;">';
            echo '<span class="dashicons dashicons-yes" style="margin-right:5px;"></span>';
            echo '<strong>' . esc_html__('Users synced successfully!', 'awp') . '</strong></p>';
        } else {
            echo '<p style="color:#D60000; margin:0;">';
            echo '<span class="dashicons dashicons-no-alt" style="margin-right:5px;"></span>';
            echo '<strong>' . esc_html__('Some errors occurred during user sync.', 'awp') . '</strong></p>';
        }
        if (!empty($logs)) {
            echo '<div style="margin:8px 0 0 14px;">';
            echo '<strong>' . esc_html__('User Sync Logs', 'awp') . '</strong>:';
            echo '<ul style="margin:6px 0; padding:0 0 0 16px;">';
            foreach ($logs as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }
        echo '</div>';
        $this->sync_users_html = ob_get_clean();
    }

    public function sync_users_info() {
        global $wpdb;
        $table_name = $this->db_manager->tables['user_info'];
        $logs       = [];
        $has_error  = false;
        $wp_users   = get_users(['fields' => ['ID','user_email','user_pass']]);
        $has_woo    = class_exists('WooCommerce');

        foreach ($wp_users as $user) {
            $wp_user_id = $user->ID;
            $wp_email   = strtolower(trim($user->user_email));
            if (empty($wp_email)) {
                $logs[] = sprintf(__('Skipped WP user ID #%d (no email).', 'awp'), $wp_user_id);
                continue;
            }
            $wp_pass  = $user->user_pass;
            $wp_fname = get_user_meta($wp_user_id, 'first_name', true);
            $wp_lname = get_user_meta($wp_user_id, 'last_name', true);
            $wp_phone = get_user_meta($wp_user_id, 'phone', true);

            $wc_billing_first = $has_woo ? get_user_meta($wp_user_id, 'billing_first_name', true) : '';
            $wc_billing_last  = $has_woo ? get_user_meta($wp_user_id, 'billing_last_name', true) : '';
            $wc_billing_phone = $has_woo ? get_user_meta($wp_user_id, 'billing_phone', true) : '';

            $final_first = !empty($wc_billing_first) ? $wc_billing_first : $wp_fname;
            $final_last  = !empty($wc_billing_last)  ? $wc_billing_last  : $wp_lname;
            $final_phone = !empty($wc_billing_phone) ? $wc_billing_phone : $wp_phone;

            if (empty($final_first)) $final_first = __('N/A', 'awp');
            if (empty($final_last))  $final_last  = __('N/A', 'awp');
            if (empty($final_phone)) $final_phone = __('N/A', 'awp');

            if ($final_phone !== __('N/A', 'awp')) {
                $final_phone = $this->normalize_phone_number($final_phone);
            }
            if (!empty($wc_billing_phone)) {
                $normalized_billing = $this->normalize_phone_number($wc_billing_phone);
                update_user_meta($wp_user_id, 'billing_phone', $normalized_billing);
            }

            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE email=%s LIMIT 1",
                $wp_email
            ));

            $whatsapp_verified_value   = __('Not Verified', 'awp');
            $otp_verification_whatsapp = 0;

            if ($existing_id) {
                $existing_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT whatsapp_verified FROM $table_name WHERE id=%d",
                    $existing_id
                ));
                if ($existing_status === __('Verified', 'awp')) {
                    $whatsapp_verified_value   = __('Verified', 'awp');
                    $otp_verification_whatsapp = 1;
                }
            }

            $data = [
                'user_id'                   => $wp_user_id,
                'first_name'                => $final_first,
                'last_name'                 => $final_last,
                'email'                     => $wp_email,
                'phone'                     => $final_phone,
                'password'                  => $wp_pass,
                'otp_verification_email'    => 0,
                'otp_verification_whatsapp' => $otp_verification_whatsapp,
                'whatsapp_verified'         => $whatsapp_verified_value,
            ];

            if ($existing_id) {
                $res = $wpdb->update($table_name, $data, ['id' => $existing_id]);
                if ($res === false) {
                    $has_error = true;
                    $logs[] = sprintf(__('Error updating user %1$s: %2$s', 'awp'), $wp_email, $wpdb->last_error);
                }
            } else {
                $res = $wpdb->insert($table_name, $data);
                if ($res === false) {
                    $has_error = true;
                    $logs[] = sprintf(__('Error inserting user %1$s: %2$s', 'awp'), $wp_email, $wpdb->last_error);
                }
            }
        }

        return [!$has_error, $logs];
    }

    private function normalize_phone_number($phone) {
        $phone = str_replace('-', '', $phone);
        $phone = preg_replace('/^\+/', '', $phone);
        $phone = preg_replace('/^00/', '', $phone);
        
        return $phone;
    }
    
    private function remove_leading_plus_00($phone) {
        $phone = preg_replace('/^\+/', '', $phone);
        $phone = preg_replace('/^00/', '', $phone);
        
        return $phone;
    }
    
}
