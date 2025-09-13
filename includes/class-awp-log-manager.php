<?php
if (!defined('ABSPATH')) exit;

class AWP_Log_Manager {

    private $database_manager;
    private $log_table_name;

    // Tunables
    private $MAX_NEW_CHECKS            = 250;    // per cron run
    private $MAX_RECHECKS              = 500;    // per cron run
    private $MAX_DELIVERY_CHECKS       = 5;      // per message before we give up
    private $FRESH_SKIP_SECONDS        = 60;     // don’t poll messages < 60s old
    private $STALE_HORIZON_SECONDS     = 604800; // stop after 7 days
    private $CAP_PER_INSTANCE_PER_RUN  = 100;    // per-instance throttle per run

    public function __construct() {
        $this->database_manager = new AWP_Database_Manager();
        $this->log_table_name   = $this->database_manager->get_log_table_name();
        $this->ensure_schema();        // make sure next_check_at + indexes exist
        $this->init_ajax_handlers();
    }

    /** Ensure we have required column(s) and indexes without crashing older MySQL */
    private function ensure_schema() {
        global $wpdb;
        $table = $this->log_table_name;

        // Ensure next_check_at column exists
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'next_check_at'));
        if (!$col) {
            $wpdb->query("ALTER TABLE `$table` ADD COLUMN `next_check_at` DATETIME DEFAULT NULL");
        }

        // Add helpful indexes if missing
        $this->maybe_add_index($table, 'idx_delivery_ack', 'delivery_ack');
        $this->maybe_add_index($table, 'idx_next_check_at', 'next_check_at');
        // Mixed index is handy for due filtering by state
        $this->maybe_add_index($table, 'idx_ack_next', 'delivery_ack,next_check_at');
    }

    private function maybe_add_index($table, $index_name, $columns_csv) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM `$table` WHERE Key_name = %s",
            $index_name
        ));
        if (!$exists) {
            // Safe create
            $wpdb->query("CREATE INDEX `$index_name` ON `$table` ($columns_csv)");
        }
    }

    public function init_ajax_handlers() {
        add_action('wp_ajax_awp_resend_notification', [$this, 'handle_resend_notification']);
        add_action('wp_ajax_awp_delete_logs',        [$this, 'handle_delete_logs']);
    }

    public function handle_delete_logs() {
        if (!current_user_can('manage_options')) wp_send_json_error(__('No permission.', 'awp'));
        check_ajax_referer('awp_resend_notification_nonce', 'nonce');

        $ids = isset($_POST['log_ids']) ? array_map('intval', (array)$_POST['log_ids']) : [];
        if (empty($ids)) wp_send_json_error(__('No IDs provided.', 'awp'));

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$this->log_table_name} WHERE id IN ($placeholders)", $ids));
        wp_send_json_success(__('Deleted.', 'awp'));
    }

    private function is_successful_status($json) : bool {
        $d = is_array($json) ? $json : json_decode($json, true);
        if (!is_array($d)) return false;

        // Format 1
        $format1_success = isset($d['sent'], $d['upstream_status'])
                           && $d['sent'] === true
                           && (int) $d['upstream_status'] === 201;

        // Format 2
        $format2_success = isset($d['id']) && ($d['_data']['Info']['IsFromMe'] ?? null) === true;

        // Format 3
        $format3_success = ($d['_data']['id']['fromMe'] ?? null) === true;

        // Format 4
        $format4_success = !empty($d['verified_at']);

        return $format1_success || $format2_success || $format3_success || $format4_success;
    }

    private function get_delivery_status_text($ack) {
        if ($ack === null) return '-';

        $single_tick_svg = '<span class="wawp-tick"><svg viewBox="0 0 16 15" height="18" width="18"><path fill="#667781" d="M10.91 3.316l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.273-8.05a.365.365 0 0 0-.063-.51z"></path></svg></span>';
        $gray_tick_svg   = '<span class="wawp-tick"><svg viewBox="0 0 18 18" height="18" width="18" preserveAspectRatio="xMidYMid meet" version="1.1" x="0px" y="0px" enable-background="new 0 0 18 18"><path fill="#667781" d="M17.394,5.035l-0.57-0.444c-0.188-0.147-0.462-0.113-0.609,0.074l-6.5,8.358L6.44,10.057 c-0.162-0.178-0.432-0.203-0.61-0.041l-0.53,0.482c-0.178,0.162-0.203,0.432-0.041,0.61l3.52,3.882 c0.163,0.178,0.433,0.202,0.61,0.041l7.44-9.562C17.511,5.498,17.582,5.228,17.394,5.035z M12.51,5.035l-0.57-0.444 c-0.188-0.147-0.462-0.113-0.609,0.074l-6.5,8.358L1.556,10.057c-0.162-0.178-0.432-0.203-0.61-0.041l-0.53,0.482 c-0.178,0.162-0.203,0.432-0.041,0.61l3.52,3.882c0.163,0.178,0.433,0.202,0.61,0.041l7.44-9.562 C12.627,5.498,12.698,5.228,12.51,5.035z"></path></svg></span>';
        $blue_tick_svg   = '<span class="wawp-tick"><svg viewBox="0 0 18 18" height="18" width="18" preserveAspectRatio="xMidYMid meet" version="1.1" x="0px" y="0px" enable-background="new 0 0 18 18"><path fill="#53bdeb" d="M17.394,5.035l-0.57-0.444c-0.188-0.147-0.462-0.113-0.609,0.074l-6.5,8.358L6.44,10.057 c-0.162-0.178-0.432-0.203-0.61-0.041l-0.53,0.482c-0.178,0.162-0.203,0.432-0.041,0.61l3.52,3.882 c0.163,0.178,0.433,0.202,0.61,0.041l7.44-9.562C17.511,5.498,17.582,5.228,17.394,5.035z M12.51,5.035l-0.57-0.444 c-0.188-0.147-0.462-0.113-0.609,0.074l-6.5,8.358L1.556,10.057c-0.162-0.178-0.432-0.203-0.61-0.041l-0.53,0.482 c-0.178,0.162-0.203,0.432-0.041,0.61l3.52,3.882c0.163,0.178,0.433,0.202,0.61,0.041l7.44-9.562 C12.627,5.498,12.698,5.228,12.51,5.035z"></path></svg></span>';

        $ack = (int) $ack;
        switch ($ack) {
            case -1: return __('ERROR', 'awp');
            case  0: return __('PENDING', 'awp');
            case  1: return $single_tick_svg . __('Sent', 'awp');
            case  2: return $gray_tick_svg   . __('Delivered', 'awp');
            case  3: return $blue_tick_svg   . __('READ', 'awp');
            default: return __('Unknown', 'awp');
        }
    }

    public function render_logs_page() {
        echo '<style>.wawp-tick{display:inline-flex;vertical-align:middle;margin-right:4px}</style>';

        $banned_msg = get_transient('siteB_banned_msg');
        $token      = get_option('mysso_token');
        $user_data  = get_transient('siteB_user_data');

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
        if (!AWP_Admin_Notices::require_online_instance(null)) return;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['awp_log_settings_nonce']) && wp_verify_nonce($_POST['awp_log_settings_nonce'], 'awp_log_settings_action')) {
            if (isset($_POST['awp_instance_id'])) {
                update_option('awp_selected_log_manager_instance', sanitize_text_field($_POST['awp_instance_id']));
            }
            if (isset($_POST['awp_auto_resend_limit'])) {
                update_option('awp_auto_resend_limit', (int) $_POST['awp_auto_resend_limit']);
            }
            $auto_resend_enabled = isset($_POST['awp_enable_auto_resend']) ? 'on' : 'off';
            update_option('awp_enable_auto_resend', $auto_resend_enabled);

            if (isset($_POST['awp_auto_clear_log_interval'])) {
                update_option('awp_auto_clear_log_interval', sanitize_text_field($_POST['awp_auto_clear_log_interval']));
            }

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved', 'awp') . '</p></div>';
        }

        global $wpdb;
        $online_instances    = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}awp_instance_data WHERE status='online' ORDER BY id DESC");
        $selected_instance   = get_option('awp_selected_log_manager_instance', '');
        $auto_resend_limit   = get_option('awp_auto_resend_limit', 3);
        $auto_resend_enabled = get_option('awp_enable_auto_resend', 'on');
        $auto_clear_interval = get_option('awp_auto_clear_log_interval', 'never');

        $logs = $wpdb->get_results("SELECT * FROM {$this->log_table_name} ORDER BY sent_at DESC");

        // Stats
        $total_logs        = $wpdb->get_var("SELECT COUNT(id) FROM {$this->log_table_name}");
        $sent_logs         = $wpdb->get_var("SELECT COUNT(id) FROM {$this->log_table_name} WHERE wawp_status LIKE '%\"IsFromMe\":true%' OR wawp_status LIKE '%\"sent\":true%'");
        $read_logs         = $wpdb->get_var("SELECT COUNT(id) FROM {$this->log_table_name} WHERE delivery_ack = 3");
        $pending_logs      = $wpdb->get_var("SELECT COUNT(id) FROM {$this->log_table_name} WHERE delivery_ack = 0");
        $error_logs        = $wpdb->get_var("SELECT COUNT(id) FROM {$this->log_table_name} WHERE wawp_status LIKE '%\"status\":\"error\"%' OR wawp_status LIKE '%\"status\":\"failure\"%'");
        $unique_recipients = $wpdb->get_var("SELECT COUNT(DISTINCT whatsapp_number) FROM {$this->log_table_name} WHERE wawp_status LIKE '%\"IsFromMe\":true%' OR wawp_status LIKE '%\"sent\":true%'");
        $read_rate         = ($sent_logs > 0) ? number_format(($read_logs / $sent_logs) * 100, 2) : 0;

        wp_localize_script('awp-log-js', 'awpLog', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('awp_resend_notification_nonce'),
            'message_types' => $this->get_all_message_types()
        ]);

        echo '<div><div class="page-header_row"><div class="page-header">
                <h2 class="page-title">' . esc_html__('WhatsApp Messages History', 'awp') . '</h2>
                <p>' . esc_html__('Track all messages and quickly access info via live search, filters, and logs. Also see status for any errors.', 'awp') . '</p>
              </div></div>';

        echo '<div class="awp-cards" style="flex-direction: row;margin-bottom: 1.25rem;gap: .5rem;">';
        echo '<form method="post" class="awp-log-settings-form" style="min-width: calc((100% / 4));">';
        wp_nonce_field('awp_log_settings_action', 'awp_log_settings_nonce');

        echo '<div class="awp-card awp-log-options-bar">
                <div class="awp-field switch-field">
                  <label for="awp_enable_auto_resend">' . esc_html__('Enable Auto Resend', 'awp') . '</label>
                  <label class="switch">
                    <input type="checkbox" id="awp_enable_auto_resend" name="awp_enable_auto_resend" ' . checked($auto_resend_enabled, 'on', false) . '>
                    <span class="slider round"></span>
                  </label>
                </div>';

        $clear_intervals = [
            'never' => __('Never', 'awp'),
            '1'     => __('Every 1 Day', 'awp'),
            '15'    => __('Every 15 Days', 'awp'),
            '30'    => __('Every 30 Days', 'awp'),
            '60'    => __('Every 60 Days', 'awp'),
            '90'    => __('Every 90 Days', 'awp'),
        ];
        echo '<div class="awp-field">
                <label for="awp_auto_clear_log_interval">' . esc_html__('Auto Clear Logs', 'awp') . '</label>
                <select name="awp_auto_clear_log_interval" id="awp_auto_clear_log_interval">';
        foreach ($clear_intervals as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($auto_clear_interval, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '  </select>
              </div>';

        echo '<div class="awp-field">
                <label for="awp_auto_resend_limit">' . esc_html__('Auto Resend Limit:', 'awp') . '</label>
                <input type="number" id="awp_auto_resend_limit" name="awp_auto_resend_limit" value="' . esc_attr($auto_resend_limit) . '" />
              </div>';

        echo '<label style="display:none;">' . esc_html__('Choose Instance:', 'awp') . ' <select name="awp_instance_id">';
        echo '<option value="">-- ' . esc_html__('Select', 'awp') . ' --</option>';
        if ($online_instances) {
            foreach ($online_instances as $inst) {
                $isSel = selected($selected_instance, $inst->instance_id, false);
                echo '<option value="' . esc_attr($inst->instance_id) . '" ' . $isSel . '>' . esc_html($inst->name . ' - ' . $inst->instance_id) . '</option>';
            }
        }
        echo '</select></label>';

        echo '<button type="submit" class="awp-btn primary">' . esc_html__('Save Settings', 'awp') . '</button>
              </div></form>';

        // Stats
        echo '<div class="whatsapp-logs">';
        echo '  <div class="awp-card"><div class="card-header"><h4 class="card-title"><i class="ri-archive-line"></i>Total Logged</h4><p>All entries in current view.</p></div><div class="card_number">' . esc_html($total_logs) . '</div></div>';
        echo '  <div class="awp-card status-sent"><div class="card-header"><h4 class="card-title"><i class="ri-whatsapp-line"></i>Notifactions Sent</h4><p>Successfully processed & sent, not include otp.</p></div><div class="card_number">' . esc_html($sent_logs) . '</div></div>';
        echo '  <div class="awp-card status-opened"><div class="card-header"><h4 class="card-title"><i class="ri-eye-line"></i>Reads</h4><p>' . esc_html($read_rate) . '% Read Rate (of sent)</p></div><div class="card_number">' . esc_html($read_logs) . '</div></div>';
        echo '  <div class="awp-card status-pending"><div class="card-header"><h4 class="card-title"><i class="ri-history-line"></i>WhatsApp Pending</h4><p>Queued for sending.</p></div><div class="card_number">' . esc_html($pending_logs) . '</div></div>';
        echo '  <div class="awp-card status-error"><div class="card-header"><h4 class="card-title"><i class="ri-error-warning-line"></i>Send Errors</h4><p>Failed to send.</p></div><div class="card_number">' . esc_html($error_logs) . '</div></div>';
        echo '  <div class="awp-card status-users"><div class="card-header"><h4 class="card-title"><i class="ri-user-line"></i>Unique Recipients</h4><p>Unique whatsapp successfully sent to.</p></div><div class="card_number">' . esc_html($unique_recipients) . '</div></div>';
        echo '</div></div>';

        // Icons
        $arrow_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="rgba(7,41,41,1)"><path d="M11.9999 13.1714L16.9497 8.22168L18.3639 9.63589L11.9999 15.9999L5.63599 9.63589L7.0502 8.22168L11.9999 13.1714Z"></path></svg>';
        $close_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="rgba(173,184,194,1)"><path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path></svg>';

        // Filters (unique arrow ids per pill)
        echo '<div class="wawp-log-filters">
                <div class="live-search-bar">
                    <input type="text" id="awp-live-search" class="search-icon" placeholder="' . esc_attr__('Live search...', 'awp') . '">
                    <button class="awp-btn delete awp-delete-selected">' . esc_html__('Delete Selected', 'awp') . '</button>
                </div>
                <div class="filter-bar">
                    <div id="awp-pill-items" class="filter-pill">
                        <button class="pill-close pill-items-close">'.$close_icon.'</button>
                        <span class="pill-label">'.esc_html__('Results Per Page','awp').'</span>
                        <span class="pill-sep">~</span>
                        <span id="awp-value-items" class="pill-value">20</span>
                        <button class="pill-arrow" id="awp-arrow-items">'.$arrow_icon.'</button>
                        <div id="awp-popup-items" class="filter-popup">
                            <select id="awp-items-select">
                                <option value="20">20</option>
                                <option value="40">40</option>
                                <option value="60">60</option>
                                <option value="80">80</option>
                                <option value="100">100</option>
                                <option value="500">500</option>
                                <option value="1000">1000</option>
                                <option value="-1">All</option>
                            </select>
                            <button class="popup-btn" id="awp-apply-items">'.esc_html__('Apply','awp').'</button>
                        </div>
                    </div>

                    <div id="awp-pill-date" class="filter-pill">
                        <button class="pill-close pill-date-close">'.$close_icon.'</button>
                        <span class="pill-label">'.esc_html__('Date Range','awp').'</span>
                        <span class="pill-sep">~</span>
                        <span id="awp-value-date" class="pill-value">'.esc_html__('Last 7 days','awp').'</span>
                        <button class="pill-arrow" id="awp-arrow-date">'.$arrow_icon.'</button>
                        <div id="awp-popup-date" class="filter-popup">
                            <select id="awp-date-operator">
                                <option value="last">'.esc_html__('is in the last','awp').'</option>
                                <option value="equal">'.esc_html__('is equal to','awp').'</option>
                                <option value="between">'.esc_html__('is between','awp').'</option>
                                <option value="onorafter">'.esc_html__('is on or after','awp').'</option>
                                <option value="beforeoron">'.esc_html__('is before or on','awp').'</option>
                            </select>
                            <input type="number" id="awp-date-value" value="7">
                            <select id="awp-date-unit">
                                <option value="hours">'.esc_html__('hours','awp').'</option>
                                <option value="days" selected>'.esc_html__('days','awp').'</option>
                                <option value="months">'.esc_html__('months','awp').'</option>
                            </select>
                            <input type="date" id="awp-date-value2" style="display:none;">
                            <button class="popup-btn" id="awp-apply-date">'.esc_html__('Apply','awp').'</button>
                        </div>
                    </div>

                    <div id="awp-pill-msgtype" class="filter-pill">
                        <button class="pill-close pill-msgtype-close">'.$close_icon.'</button>
                        <span class="pill-label">'.esc_html__('Message Type','awp').'</span>
                        <span class="pill-sep">~</span>
                        <span id="awp-value-msgtype" class="pill-value">'.esc_html__('All','awp').'</span>
                        <button class="pill-arrow" id="awp-arrow-msgtype">'.$arrow_icon.'</button>
                        <div id="awp-popup-msgtype" class="filter-popup">
                            <div id="awp-msgtype-boxes"></div>
                            <button class="popup-btn" id="awp-apply-msgtype">'.esc_html__('Apply','awp').'</button>
                        </div>
                    </div>

                    <div id="awp-pill-status" class="filter-pill">
                        <button class="pill-close pill-status-close">'.$close_icon.'</button>
                        <span class="pill-label">'.esc_html__('Status','awp').'</span>
                        <span class="pill-sep">~</span>
                        <span id="awp-value-status" class="pill-value">'.esc_html__('All','awp').'</span>
                        <button class="pill-arrow" id="awp-arrow-status">'.$arrow_icon.'</button>
                        <div id="awp-popup-status" class="filter-popup">
                            <div id="awp-status-boxes"></div>
                            <button class="popup-btn" id="awp-apply-status">'.esc_html__('Apply','awp').'</button>
                        </div>
                    </div>

                    <div id="awp-pill-columns" class="filter-pill">
                        <button class="pill-close pill-columns-close">'.$close_icon.'</button>
                        <span class="pill-label">'.esc_html__('Columns','awp').'</span>
                        <span class="pill-sep">~</span>
                        <span class="pill-value">'.esc_html__('All','awp').'</span>
                        <button class="pill-arrow" id="awp-arrow-columns">'.$arrow_icon.'</button>
                        <div class="filter-popup" id="awp-popup-columns">
                            <div id="awp-columns-boxes">
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="1" checked><span class="slider"></span></label><label>'.esc_html__('ID','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="2"><span class="slider"></span></label><label>'.esc_html__('User ID','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="3" checked><span class="slider"></span></label><label>'.esc_html__('Order','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="4" checked><span class="slider"></span></label><label>'.esc_html__('Name','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="5" checked><span class="slider"></span></label><label>'.esc_html__('Date','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="6" checked><span class="slider"></span></label><label>'.esc_html__('Number','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="7" checked><span class="slider"></span></label><label>'.esc_html__('Message','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="8" checked><span class="slider"></span></label><label>'.esc_html__('Attach','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="9" checked><span class="slider"></span></label><label>'.esc_html__('Type','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="10" checked><span class="slider"></span></label><label>'.esc_html__('Status','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="11" checked><span class="slider"></span></label><label>'.esc_html__('Delivery Status','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="12" checked><span class="slider"></span></label><label>'.esc_html__('Action','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="13"><span class="slider"></span></label><label>'.esc_html__('Info','awp').'</label></div>
                                <div class="switch-block"><label class="switch"><input type="checkbox" class="col-toggle" data-col="14"><span class="slider"></span></label><label>'.esc_html__('Ref ID','awp').'</label></div>
                            </div>
                            <button class="popup-btn" id="awp-apply-columns">'.esc_html__('Apply','awp').'</button>
                        </div>
                    </div>

                    <span class="clear-link" id="awp-clear-filters">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="rgba(67,73,96,1)"><path d="M22 12C22 17.5228 17.5229 22 12 22C6.4772 22 2 17.5228 2 12C2 6.47715 6.4772 2 12 2V4C7.5817 4 4 7.58172 4 12C4 16.4183 7.5817 20 12 20C16.4183 20 20 16.4183 20 12C20 9.25022 18.6127 6.82447 16.4998 5.38451L16.5 8H14.5V2L20.5 2V4L18.0008 3.99989C20.4293 5.82434 22 8.72873 22 12Z"></path></svg>
                        '.esc_html__('Clear Filters','awp').'
                    </span>
                </div>
            </div>';

        echo '<div id="awp-entries-menu" style="margin-left:auto;"></div>';

        echo '<table id="awp-logs-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="awp-select-all"></th>
                        <th>'.esc_html__('ID','awp').'</th>
                        <th>'.esc_html__('User ID','awp').'</th>
                        <th>'.esc_html__('Order','awp').'</th>
                        <th>'.esc_html__('Name','awp').'</th>
                        <th>'.esc_html__('Date','awp').'</th>
                        <th>'.esc_html__('Number','awp').'</th>
                        <th>'.esc_html__('Message','awp').'</th>
                        <th>'.esc_html__('Attach','awp').'</th>
                        <th>'.esc_html__('Type','awp').'</th>
                        <th>'.esc_html__('Status','awp').'</th>
                        <th>'.esc_html__('Delivery Status','awp').'</th>
                        <th>'.esc_html__('Action','awp').'</th>
                        <th>'.esc_html__('Info','awp').'</th>
                        <th>'.esc_html__('Ref ID','awp').'</th>
                    </tr>
                </thead>
                <tbody>';

        if ($logs) {
            foreach ($logs as $log) {
                $decoded         = json_decode($log->wawp_status, true);
                $status_display  = '';
                $info_display    = '-';

                if ($this->is_successful_status($decoded)) {
                    $status_display = sprintf(
                        '<span class="awp-status success" data-json="%s">%s</span>',
                        esc_attr($log->wawp_status),
                        esc_html__('Success', 'awp')
                    );

                    $info_parts = [];
                    if (isset($decoded['_data']['Info']['Sender'], $decoded['_data']['Info']['ID'])) {
                        list($sender_number) = explode(':', $decoded['_data']['Info']['Sender']);
                        $sender_formatted = $sender_number . '@c.us';
                        $message_id       = $decoded['_data']['Info']['ID'];
                        $info_parts[]     = 'Sender: ' . esc_html($sender_formatted);
                        $info_parts[]     = 'ID: ' . esc_html($message_id);
                    }
                    if (!empty($log->instance_id)) {
                        $info_parts[] = 'Instance: ' . esc_html($log->instance_id);
                    }
                    if (!empty($log->access_token)) {
                        $end = substr($log->access_token, -4);
                        $info_parts[] = 'Token: ••••' . esc_html($end);
                    }
                    $info_display = $info_parts ? implode('<br>', $info_parts) : '-';

                } elseif (isset($decoded['code']) && in_array($decoded['code'], ['invalid_instance', 'proxy_error'], true)) {
                    $status_display = sprintf(
                        '<span class="awp-status error" data-json="%s">%s</span>',
                        esc_attr($log->wawp_status),
                        esc_html__('Error', 'awp')
                    );
                } elseif (isset($decoded['status'])) {
                    $status_text  = '';
                    $status_class = '';

                    switch ($decoded['status']) {
                        case 'success':
                            if (isset($decoded['message']) && $decoded['message'] === 'The number of messages you have sent per month has exceeded the maximum limit') {
                                $status_text  = __('Need Upgrade', 'awp');
                                $status_class = 'need-upgrade';
                            } elseif (isset($decoded['message'])) {
                                $status_text  = __('Success', 'awp');
                                $status_class = 'success';
                            } else {
                                $log->wawp_status = wp_json_encode([
                                    'status'  => 'failure',
                                    'message' => __('This message seems to have failed. Please use Resend.', 'awp'),
                                ]);
                                $status_text  = __('Failed', 'awp');
                                $status_class = 'no-success';
                            }
                            break;
                        case 'blocked':
                            $status_text  = __('Blocked', 'awp');
                            $status_class = 'blocked';
                            break;
                        case 'info':
                            $status_text  = !empty($decoded['message']) ? $decoded['message'] : __('Info', 'awp');
                            $status_class = 'info';
                            break;
                        case 'error':
                            $status_text  = __('Error', 'awp');
                            $status_class = 'error';
                            break;
                        case 'failure':
                            $status_text  = __('Failure', 'awp');
                            $status_class = 'error';
                            break;
                        case 'unknown':
                            $status_text  = __('Unknown', 'awp');
                            $status_class = 'info';
                            break;
                        default:
                            $status_text  = ucfirst(esc_html($decoded['status']));
                            $status_class = 'info';
                            break;
                    }

                    $status_display = sprintf(
                        '<span class="awp-status %s" data-json="%s">%s</span>',
                        esc_attr($status_class),
                        esc_attr($log->wawp_status),
                        esc_html($status_text)
                    );
                } else {
                    $json = wp_json_encode([
                        'status'  => 'unknown',
                        'message' => $log->wawp_status,
                    ]);
                    $status_display = '<span class="awp-status info" data-json="' . esc_attr($json) . '">' . esc_html__('Unknown', 'awp') . '</span>';
                }

                $delivery_ack          = $log->delivery_ack ?? null;
                $delivery_status_html  = $this->get_delivery_status_text($delivery_ack);
                $delivery_check_count  = isset($log->delivery_check_count) ? (int) $log->delivery_check_count : 0;

                if ($delivery_check_count >= 5 && (int)$delivery_ack < 3) {
                    if ((int)$delivery_ack === 1) $delivery_status_html .= ' (device not online or user not had whatsapp)';
                    elseif ((int)$delivery_ack === 2) $delivery_status_html .= ' (not read yet or disabled by user)';
                }

                echo '<tr>
                        <td><input type="checkbox" class="awp-row-select" value="'.esc_attr($log->id).'"></td>
                        <td>'.esc_html($log->id).'</td>
                        <td>'.esc_html($log->user_id ?: '-').'</td>
                        <td>'.esc_html($log->order_id ?: '-').'</td>
                        <td>'.esc_html($log->customer_name).'</td>
                        <td>'.esc_html($log->sent_at).'</td>
                        <td>'.esc_html($log->whatsapp_number).'</td>
                        <td>'.esc_html($log->message).'</td>
                        <td>' . (
                            $log->image_attachment
                            ? '<a href="' . esc_url($log->image_attachment) . '" target="_blank"><img src="' . esc_url($log->image_attachment) . '" alt="Attachment" style="max-width:32px;height:auto;display:block;"></a>'
                            : 'No img sent with msg'
                        ) . '</td>
                        <td>'.esc_html($log->message_type).'</td>
                        <td>'.$status_display.'</td>
                        <td>'.$delivery_status_html.'</td>
                        <td><button class="awp-resend-button awp-btn" data-log-id="'.esc_attr($log->id).'">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(31,193,107,1)"><path d="M7.25361 18.4944L7.97834 18.917C9.18909 19.623 10.5651 20 12.001 20C16.4193 20 20.001 16.4183 20.001 12C20.001 7.58172 16.4193 4 12.001 4C7.5827 4 4.00098 7.58172 4.00098 12C4.00098 13.4363 4.37821 14.8128 5.08466 16.0238L5.50704 16.7478L4.85355 19.1494L7.25361 18.4944ZM2.00516 22L3.35712 17.0315C2.49494 15.5536 2.00098 13.8345 2.00098 12C2.00098 6.47715 6.47813 2 12.001 2C17.5238 2 22.001 6.47715 22.001 12C22.001 17.5228 17.5238 22 12.001 22C10.1671 22 8.44851 21.5064 6.97086 20.6447L2.00516 22ZM8.39232 7.30833C8.5262 7.29892 8.66053 7.29748 8.79459 7.30402C8.84875 7.30758 8.90265 7.31384 8.95659 7.32007C9.11585 7.33846 9.29098 7.43545 9.34986 7.56894C9.64818 8.24536 9.93764 8.92565 10.2182 9.60963C10.2801 9.76062 10.2428 9.95633 10.125 10.1457C10.0652 10.2428 9.97128 10.379 9.86248 10.5183C9.74939 10.663 9.50599 10.9291 9.50599 10.9291C9.50599 10.9291 9.40738 11.0473 9.44455 11.1944C9.45903 11.25 9.50521 11.331 9.54708 11.3991C9.57027 11.4368 9.5918 11.4705 9.60577 11.4938C9.86169 11.9211 10.2057 12.3543 10.6259 12.7616C10.7463 12.8783 10.8631 12.9974 10.9887 13.108C11.457 13.5209 11.9868 13.8583 12.559 14.1082L12.5641 14.1105C12.6486 14.1469 12.692 14.1668 12.8157 14.2193C12.8781 14.2457 12.9419 14.2685 13.0074 14.2858C13.0311 14.292 13.0554 14.2955 13.0798 14.2972C13.2415 14.3069 13.335 14.2032 13.3749 14.1555C14.0984 13.279 14.1646 13.2218 14.1696 13.2222V13.2238C14.2647 13.1236 14.4142 13.0888 14.5476 13.097C14.6085 13.1007 14.6691 13.1124 14.7245 13.1377C15.2563 13.3803 16.1258 13.7587 16.1258 13.7587L16.7073 14.0201C16.8047 14.0671 16.8936 14.1778 16.8979 14.2854C16.9005 14.3523 16.9077 14.4603 16.8838 14.6579C16.8525 14.9166 16.7738 15.2281 16.6956 15.3913C16.6406 15.5058 16.5694 15.6074 16.4866 15.6934C16.3743 15.81 16.2909 15.8808 16.1559 15.9814C16.0737 16.0426 16.0311 16.0714 16.0311 16.0714C15.8922 16.159 15.8139 16.2028 15.6484 16.2909C15.391 16.428 15.1066 16.5068 14.8153 16.5218C14.6296 16.5313 14.4444 16.5447 14.2589 16.5347C14.2507 16.5342 13.6907 16.4482 13.6907 16.4482C12.2688 16.0742 10.9538 15.3736 9.85034 14.402C9.62473 14.2034 9.4155 13.9885 9.20194 13.7759C8.31288 12.8908 7.63982 11.9364 7.23169 11.0336C7.03043 10.5884 6.90299 10.1116 6.90098 9.62098C6.89729 9.01405 7.09599 8.4232 7.46569 7.94186C7.53857 7.84697 7.60774 7.74855 7.72709 7.63586C7.85348 7.51651 7.93392 7.45244 8.02057 7.40811C8.13607 7.34902 8.26293 7.31742 8.39232 7.30833Z"></path></svg>
                            '.esc_html__('Resend','awp').'
                        </button></td>
                        <td>'.$info_display.'</td>
                        <td>'.esc_html($log->resend_id ?: '-').'</td>
                      </tr>';
            }
        }

        echo '</tbody></table>
              <div id="awp-modal">
                <div id="awp-modal-content-wrap">
                    <span id="awp-modal-close">&times;</span>
                    <div id="awp-modal-content"></div>
                </div>
              </div>';
        echo '</div>';
    }

    public function handle_resend_notification() {
        if (!current_user_can('manage_options')) wp_send_json_error(__('No permission.', 'awp'));
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'awp_resend_notification_nonce')) wp_send_json_error(__('Invalid nonce.', 'awp'));

        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
        if ($log_id <= 0) wp_send_json_error(__('Invalid log ID.', 'awp'));

        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->log_table_name} WHERE id = %d", $log_id));
        if (!$log) wp_send_json_error(__('Log not found.', 'awp'));

        $instance_id = get_option('awp_selected_log_manager_instance', '');
        if (!$instance_id) wp_send_json_error(__('No instance selected for resend.', 'awp'));

        $access_token = $this->get_selected_instance_token($instance_id);
        if (!$access_token) wp_send_json_error(__('Cannot find token for selected instance.', 'awp'));

        if (!empty($log->image_attachment)) {
            $response = Wawp_Api_Url::send_image(
                $instance_id,
                $access_token,
                $log->whatsapp_number,
                $log->image_attachment,
                $log->message
            );
        } else {
            $response = Wawp_Api_Url::send_message(
                $instance_id,
                $access_token,
                $log->whatsapp_number,
                $log->message
            );
        }

        $this->log_notification([
            'user_id'          => $log->user_id,
            'order_id'         => $log->order_id,
            'customer_name'    => $log->customer_name,
            'sent_at'          => current_time('mysql'),
            'whatsapp_number'  => $log->whatsapp_number,
            'message'          => $log->message,
            'image_attachment' => $log->image_attachment,
            'message_type'     => __('Message Re-sent (Original ID:', 'awp') . ' ' . $log->id . ')',
            'wawp_status'      => $response,
            'resend_id'        => $log->id,
            'instance_id'      => $instance_id,
            'access_token'     => $access_token
        ]);

        if (isset($response['status']) && $response['status'] === 'success') {
            // Park the old row so we don’t keep polling it
            $wpdb->update($this->log_table_name, [
                'next_check_at' => date('Y-m-d H:i:s', time() + 24*60*60),
            ], ['id' => $log->id]);
            wp_send_json_success(__('Notification resent successfully.', 'awp'));
        } else {
            $error_message = isset($response['message']) ? $response['message'] : __('Unknown error occurred.', 'awp');
            wp_send_json_error($error_message);
        }
    }

    public function log_notification($data) {
        global $wpdb;
        $json = isset($data['wawp_status']['full_response'])
            ? wp_json_encode($data['wawp_status']['full_response'])
            : wp_json_encode($data['wawp_status']);

        $wpdb->insert(
            $this->log_table_name,
            [
                'user_id'          => isset($data['user_id']) ? intval($data['user_id']) : null,
                'order_id'         => isset($data['order_id']) ? intval($data['order_id']) : null,
                'customer_name'    => sanitize_text_field($data['customer_name']),
                'sent_at'          => sanitize_text_field($data['sent_at']),
                'whatsapp_number'  => sanitize_text_field($data['whatsapp_number']),
                'message'          => sanitize_textarea_field($data['message']),
                'image_attachment' => !empty($data['image_attachment']) ? esc_url_raw($data['image_attachment']) : null,
                'message_type'     => sanitize_text_field($data['message_type']),
                'wawp_status'      => $json,
                'resend_id'        => isset($data['resend_id']) ? intval($data['resend_id']) : null,
                'instance_id'      => isset($data['instance_id']) ? sanitize_text_field($data['instance_id']) : null,
                'access_token'     => isset($data['access_token']) ? sanitize_text_field($data['access_token']) : null
            ],
            ['%d','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s']
        );
    }

    /** Compute when to re-check next, with exponential backoff + jitter */
    private function compute_next_check_at(?int $ack, string $sent_at, int $delivery_check_count): string {
        $base = 300; // 5m default
        if ($ack === 1) $base = 3600;      // Sent → 1h
        if ($ack === 2) $base = 21600;     // Delivered → 6h
        if ($ack === 3) $base = 86400;     // Read → 24h (we’ll stop anyway)

        // progressive backoff (1,2,4,8,16) capped
        $mult    = min(1 << max(0, $delivery_check_count - 1), 16);
        $seconds = $base * $mult;

        // jitter ±20%
        $jitter = (int) ($seconds * (mt_rand(-20, 20) / 100));
        return date('Y-m-d H:i:s', time() + $seconds + $jitter);
    }

    /** First-pass polling: only successful sends without ack yet, and due now */
    public function check_new_delivery_status() {
        global $wpdb;
        $log_table = $this->log_table_name;

        $logs_to_check = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$log_table}
                  WHERE delivery_ack IS NULL
                    AND (next_check_at IS NULL OR next_check_at <= NOW())
                    AND wawp_status LIKE '%%\"IsFromMe\":true%%'
                    AND instance_id IS NOT NULL
                    AND access_token IS NOT NULL
                  ORDER BY sent_at DESC
                  LIMIT %d",
                $this->MAX_NEW_CHECKS
            )
        );

        $this->process_delivery_status_check($logs_to_check);
    }

    /** Rechecks: messages not read yet (ack<3), due now, and under retry limit */
    public function recheck_delivery_status() {
        global $wpdb;
        $log_table = $this->log_table_name;

        $logs_to_check = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$log_table}
                  WHERE delivery_ack IS NOT NULL
                    AND delivery_ack < 3
                    AND delivery_check_count < %d
                    AND (next_check_at IS NULL OR next_check_at <= NOW())
                    AND wawp_status LIKE '%%\"IsFromMe\":true%%'
                    AND instance_id IS NOT NULL
                    AND access_token IS NOT NULL
                  ORDER BY sent_at DESC
                  LIMIT %d",
                $this->MAX_DELIVERY_CHECKS,
                $this->MAX_RECHECKS
            )
        );

        $this->process_delivery_status_check($logs_to_check);
    }

    /** The heavy lifter: throttling, horizons, API call, updates */
    private function process_delivery_status_check($logs_to_check) {
        if (empty($logs_to_check)) return;

        global $wpdb;
        $table      = $this->log_table_name;
        $seenPerIID = [];

        foreach ($logs_to_check as $log) {
            // Per-instance throttle
            $iid = $log->instance_id ?: 'none';
            $seenPerIID[$iid] = ($seenPerIID[$iid] ?? 0) + 1;
            if ($seenPerIID[$iid] > $this->CAP_PER_INSTANCE_PER_RUN) {
                $wpdb->update($table, [
                    'next_check_at' => date('Y-m-d H:i:s', time() + 15*60) // push +15m
                ], ['id' => $log->id]);
                continue;
            }

            // Age guard
            $age = time() - strtotime($log->sent_at);
            if ($log->delivery_ack === null && $age < $this->FRESH_SKIP_SECONDS) {
                // too fresh, defer small
                $wpdb->update($table, [
                    'next_check_at' => date('Y-m-d H:i:s', time() + 120)
                ], ['id' => $log->id]);
                continue;
            }

            // Horizon: stop after 7 days if not read
            if ($age > $this->STALE_HORIZON_SECONDS && (int)$log->delivery_ack < 3) {
                $wpdb->update($table, [
                    'delivery_check_count' => max($this->MAX_DELIVERY_CHECKS, (int)$log->delivery_check_count),
                    'next_check_at'        => null
                ], ['id' => $log->id]);
                continue;
            }

            // Extract chat + message id
            $wawp_status_data = json_decode($log->wawp_status, true);
            $chat_id    = $wawp_status_data['_data']['Info']['Sender'] ?? null;
            $message_id = $wawp_status_data['_data']['Info']['ID'] ?? null;

            if (!$chat_id || !$message_id || !$log->instance_id || !$log->access_token) {
                $cnt = (int)$log->delivery_check_count + 1;
                $wpdb->update($table, [
                    'delivery_check_count' => $cnt,
                    'next_check_at'        => $this->compute_next_check_at((int)$log->delivery_ack, $log->sent_at, $cnt),
                ], ['id' => $log->id]);
                continue;
            }

            list($sender_number) = explode(':', $chat_id);
            $chat_id_formatted   = $sender_number . '@c.us';

            $api_url = add_query_arg([
                'instance_id'   => $log->instance_id,
                'access_token'  => $log->access_token,
                'chatId'        => $chat_id_formatted,
                'messageId'     => $message_id,
                'downloadMedia' => 'false',
            ], 'https://wawp.net/wp-json/awp/v1/message');

            $response = wp_remote_get($api_url, ['timeout' => 15]);

            if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) >= 500) {
                // infra/network error → back off harder
                $cnt = (int)$log->delivery_check_count + 1;
                $hours = min(24, pow(2, min(5, $cnt))); // 1,2,4,8,16,24h
                $wpdb->update($table, [
                    'delivery_check_count' => $cnt,
                    'next_check_at'        => date('Y-m-d H:i:s', time() + 3600 * $hours),
                ], ['id' => $log->id]);
                continue;
            }

            $body        = wp_remote_retrieve_body($response);
            $data        = json_decode($body, true);
            $current_ack = isset($data['ack']) ? (int) $data['ack'] : null;

            if ($current_ack !== null) {
                // If read → stop polling
                if ($current_ack === 3) {
                    $wpdb->update($table, [
                        'delivery_status'      => $body,
                        'delivery_ack'         => 3,
                        'delivery_check_count' => 0,
                        'next_check_at'        => null
                    ], ['id' => $log->id]);
                    continue;
                }

                $same = ($current_ack === (int)$log->delivery_ack);
                $cnt  = $same ? ((int)$log->delivery_check_count + 1) : 1;

                $wpdb->update($table, [
                    'delivery_status'      => $body,
                    'delivery_ack'         => $current_ack,
                    'delivery_check_count' => $cnt,
                    'next_check_at'        => $this->compute_next_check_at($current_ack, $log->sent_at, $cnt),
                ], ['id' => $log->id]);
            } else {
                $cnt = (int)$log->delivery_check_count + 1;
                $wpdb->update($table, [
                    'delivery_check_count' => $cnt,
                    'next_check_at'        => $this->compute_next_check_at((int)$log->delivery_ack, $log->sent_at, $cnt),
                ], ['id' => $log->id]);
            }
        }
    }

    /* ========================= NEW HELPERS (anti-spam resend) ========================= */

    /** True if a later row (same number+message) looks successful within a short window */
    private function has_sibling_success($row, int $minutes = 10): bool {
        global $wpdb;
        $table = $this->log_table_name;

        $start = $row->sent_at;
        $end   = date('Y-m-d H:i:s', strtotime($row->sent_at) + ($minutes * 60));

        $sib = $wpdb->get_row($wpdb->prepare(
            "SELECT wawp_status
               FROM {$table}
              WHERE whatsapp_number = %s
                AND message = %s
                AND sent_at BETWEEN %s AND %s
                AND (
                       wawp_status LIKE '%%\"IsFromMe\":true%%'
                    OR wawp_status LIKE '%%\"sent\":true%%'
                    OR wawp_status LIKE '%%\"status\":\"success\"%%'
                    OR wawp_status LIKE '%%\"_data\":{\"id\":{\"fromMe\":true%%'
                )
              ORDER BY sent_at ASC
              LIMIT 1",
            $row->whatsapp_number, $row->message, $start, $end
        ));

        if (!$sib) return false;
        $d = json_decode($sib->wawp_status, true);
        return $this->is_successful_status($d) || (($d['status'] ?? null) === 'success');
    }

    /** Decide if we should actually resend now, adding a cool-down for flaky timeouts */
    private function should_resend_now($row, array $decoded): bool {
        // Never resend too fresh attempts (give upstream a chance)
        $ageSec = time() - strtotime($row->sent_at);
        if ($ageSec < 90) return false; // 1.5 min guard

        // Timeout / network-ish signals we should back off & try to reconcile
        $isTimeouty = (
            (isset($decoded['message']) && strpos($decoded['message'], 'cURL error 28') !== false)
            || (isset($decoded['http_code']) && (int)$decoded['http_code'] === 0)
            || (is_string($row->wawp_status) && (strpos($row->wawp_status, 'cURL error 28') !== false || strpos($row->wawp_status, '"http_code":0') !== false))
        );

        if ($isTimeouty) {
            // If a sibling success exists shortly after, don't resend this row
            if ($this->has_sibling_success($row)) return false;
            // Otherwise, cool down first.
            return false;
        }

        // For other hard errors we do allow resend (subject to limit)
        return true;
    }

    /* ================================================================================ */

    public function auto_resend_stuck_messages() {
        if (get_option('awp_enable_auto_resend', 'on') !== 'on') return;

        global $wpdb;
        $limit       = get_option('awp_auto_resend_limit', 3);
        $table       = $this->log_table_name;
        $instance_id = get_option('awp_selected_log_manager_instance', '');
        if (!$instance_id) return;

        $access_token = $this->get_selected_instance_token($instance_id);
        if (!$access_token) return;

        // Only consider rows that are DUE (avoid hammering the same rows repeatedly)
        $rows = $wpdb->get_results("
            SELECT *
            FROM {$table}
            WHERE (next_check_at IS NULL OR next_check_at <= NOW())
              AND (
                     wawp_status LIKE '%\"status\":\"error\"%'
                  OR wawp_status LIKE '%\"code\":\"invalid_instance\"%'
                  OR wawp_status LIKE '%\"code\":\"wawp_api_error\"%'
                  OR wawp_status LIKE '%\"code\":\"proxy_error\"%'
                  OR wawp_status LIKE '%This message didn\\\\\'t send%'
                  OR wawp_status LIKE '%Operation timed out%'
                  OR wawp_status LIKE '%cURL error 28%'
                  OR wawp_status LIKE '%\"http_code\":0%'
                  OR wawp_status='{\"status\":\"error\",\"message\":\"Instance ID Invalidated\"}'
                  OR wawp_status='{\"status\":\"error\",\"message\":\"Access token is required\"}'
                  OR wawp_status LIKE '%\"status\":\"failure\"%'
              )
        ");

        $firstOnline = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}awp_instance_data WHERE status='online' ORDER BY id ASC LIMIT 1");

        foreach ($rows as $row) {
            $decoded = json_decode($row->wawp_status, true);
            if ($this->is_successful_status($decoded)) continue;
            if (!$decoded) $decoded = [];

            $count = isset($decoded['auto_resend_count']) ? (int)$decoded['auto_resend_count'] : 0;
            if ($count >= $limit) continue;

            // Enforce cool-down & avoid resending immediately for timeout’y rows
            if (!$this->should_resend_now($row, $decoded)) {
                // push a short retry window so we won’t pick it up again immediately
                $cooldown = 180; // 3 minutes
                if ($count >= 1) {
                    // exponential-ish bump: 3m,6m,12m,24m, max 60m
                    $cooldown = min(3600, 180 * (1 << min(3, $count - 1)));
                }
                $wpdb->update($table, [
                    'next_check_at' => date('Y-m-d H:i:s', time() + $cooldown),
                ], ['id' => $row->id]);
                continue;
            }

            $useFirstOnline = false;
            if (isset($decoded['status']) && in_array($decoded['status'], ['error', 'unknown', 'failure'], true)) {
                if (!empty($decoded['message']) && in_array($decoded['message'], ['Instance ID Invalidated', 'Access token is required'], true)) {
                    $useFirstOnline = true;
                }
            }

            $inst_id_for_resend = $instance_id;
            $tok_for_resend     = $access_token;
            if ($useFirstOnline && $firstOnline) {
                $inst_id_for_resend = $firstOnline->instance_id;
                $tok_for_resend     = $firstOnline->access_token;
            }

            // Attempt resend
            if (!empty($row->image_attachment)) {
                $resp = Wawp_Api_Url::send_image(
                    $inst_id_for_resend,
                    $tok_for_resend,
                    $row->whatsapp_number,
                    $row->image_attachment,
                    $row->message
                );
            } else {
                $resp = Wawp_Api_Url::send_message(
                    $inst_id_for_resend,
                    $tok_for_resend,
                    $row->whatsapp_number,
                    $row->message
                );
            }

            if (isset($resp['status']) && $resp['status'] === 'success') {
                // Success → update and defer ack polling to later
                $wpdb->update($table, [
                    'wawp_status'   => wp_json_encode($resp),
                    'instance_id'   => $inst_id_for_resend,
                    'access_token'  => $tok_for_resend,
                    'next_check_at' => date('Y-m-d H:i:s', time() + 24*60*60),
                ], ['id' => $row->id]);
            } else {
                // If this was a timeout-y/network error, DO NOT immediately try again.
                $isTimeouty = (
                    (isset($resp['message']) && strpos($resp['message'], 'cURL error 28') !== false)
                    || ((int)($resp['http_code'] ?? 200) === 0)
                );

                $count++;
                $decoded['auto_resend_count'] = $count;
                $decoded['status']  = 'error';
                $decoded['message'] = $resp['message'] ?? 'Unknown error during auto-resend';

                $update = ['wawp_status' => wp_json_encode($decoded)];
                if ($isTimeouty) {
                    // back off so we don’t spam — give reconciliation/ack time to catch up
                    $cooldown = min(3600, 180 * (1 << min(3, $count - 1))); // 3m,6m,12m,24m, max 60m
                    $update['next_check_at'] = date('Y-m-d H:i:s', time() + $cooldown);
                } else {
                    // other errors → short backoff
                    $update['next_check_at'] = date('Y-m-d H:i:s', time() + 300); // 5m
                }

                $wpdb->update($table, $update, ['id' => $row->id]);
            }
        }
    }

    public function handle_auto_clear_logs() {
        global $wpdb;
        $interval = get_option('awp_auto_clear_log_interval', 'never');
        if ($interval === 'never' || !is_numeric($interval)) return;

        $days = (int) $interval;
        if ($days <= 0) return;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days", current_time('timestamp')));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->log_table_name} WHERE sent_at < %s",
            $cutoff_date
        ));
    }

    private function get_selected_instance_token($iid) {
        global $wpdb;
        if (!$iid) return null;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT access_token FROM {$wpdb->prefix}awp_instance_data WHERE instance_id=%s AND status='online'",
            $iid
        ));
        return $row ? $row->access_token : null;
    }

    private function get_all_message_types() {
        global $wpdb;
        $rows  = $wpdb->get_col("SELECT DISTINCT message_type FROM {$this->log_table_name} WHERE message_type <> ''");
        $clean = [];
        if ($rows) {
            foreach ($rows as $r) {
                if (strpos($r, 'Message Re-sent') === false) $clean[] = esc_html($r);
            }
        }
        return array_values(array_unique($clean));
    }
}
