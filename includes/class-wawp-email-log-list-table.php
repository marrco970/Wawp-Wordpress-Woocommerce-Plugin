<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Wawp_Email_Log_List_Table extends WP_List_Table {
    
    private $stats_data = [];

    public function __construct() {
        parent::__construct([
            'singular' => __('Email Log Entry', 'awp'),
            'plural'   => __('Email Log Entries', 'awp'),
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        $columns = [
            'cb'          => '<input type="checkbox" />',
            'campaign_id' => __('Campaign', 'awp'),
            'email_address' => __('Email Recipient', 'awp'),
            'type'        => __('Type', 'awp'), 
            'subject'     => __('Subject', 'awp'), 
            'status'      => __('Status', 'awp'),
            'open_count'  => __('Opened', 'awp'), 
            'first_opened_at' => __('First Open', 'awp'),
            'sent_at'     => __('Date Sent', 'awp'),
        ];
        return $columns;
    }

    public function get_sortable_columns() {
        $sortable_columns = [
            'campaign_id'   => ['campaign_id', false],
            'email_address' => ['email_address', false],
            'type'          => ['type', false], 
            'status'        => ['status', false],
            'first_opened_at' => ['first_opened_at', false],
            'open_count'    => ['open_count', false],
            'sent_at'       => ['sent_at', true], 
        ];
        return $sortable_columns;
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="log_id[]" value="%s" />', esc_attr($item['id']));
    }
    
    protected function column_campaign_id($item) {
        global $wpdb;
        $campaign_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}wawp_campaigns WHERE id = %d", $item['campaign_id']));
        if ($campaign_name) {
            $edit_link = admin_url(
                'admin.php?page=wawp&awp_section=campaigns&edit_id=' . $item['campaign_id']
            );
            return sprintf(
                '<strong><a href="%s" title="%s">%s (#%d)</a></strong>',
                esc_url($edit_link),
                esc_attr($campaign_name),
                esc_html(mb_strimwidth($campaign_name, 0, 25, "...")),
                esc_html($item['campaign_id'])
            );
        }
        return esc_html($item['campaign_id']);
    }

    protected function column_type($item) {
        $type_display = ucfirst(esc_html($item['type']));
        $class = 'cex_status_info'; 
        switch ($item['type']) {
            case 'campaign':
                $class = 'cex_status_completed'; 
                break;
            case 'manual':
                $class = 'cex_status_risky';     
                break;
            case 'abandoned_cart': 
                $class = 'cex_status_paused'; 
                break;
        }
        return '<span class="cex_status_badge ' . esc_attr($class) . '">' . $type_display . '</span>';
    }

    protected function column_user_id($item) {
        if ($item['user_id'] > 0) {
            $user = get_userdata($item['user_id']);
            if ($user) {
                return sprintf('<a href="%s">%s (#%d)</a>', esc_url(get_edit_user_link($item['user_id'])), esc_html($user->display_name), esc_html($item['user_id']));
            }
            return esc_html($item['user_id']);
        }
        return __('Guest/Manual', 'awp');
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'email_address':
                $output = '<a href="mailto:'.esc_attr($item[$column_name]).'">'.esc_html($item[$column_name]).'</a>';
                if($item['user_id'] > 0){
                    $user = get_userdata($item['user_id']);
                    if($user){
                       $output .= '<br><small><a href="'.esc_url(get_edit_user_link($item['user_id'])).'" title="'.esc_attr($user->display_name).'">'.sprintf(__('User: %s (#%d)', 'awp'), esc_html(mb_strimwidth($user->user_login,0,20,'...')), $item['user_id']).'</a></small>';
                    }
                }
                return $output;
            case 'subject':
                $subject_text = !empty($item[$column_name]) ? esc_html(mb_strimwidth($item[$column_name], 0, 40, "...")) : __('(No Subject)', 'awp');
                return '<strong>' . $subject_text . '</strong>';
            case 'status':
                $status_class = 'cex_status_unknown';
                $status_icon = 'dashicons-marker';
                
                if ($item['first_opened_at'] && $item['first_opened_at'] !== '0000-00-00 00:00:00' && $item['open_count'] > 0) {
                    $status_class = 'cex_status_opened';
                    $status_icon = 'ri-eye-line';
                    return '<div class="cex_status_badge ' . esc_attr($status_class) . '"><i class="' . $status_icon . '"></i> ' . __('Opened', 'awp') . '</div>';
                } elseif ($item[$column_name] === 'sent') { 
                    $status_class = 'cex_status_completed'; 
                    $status_icon = 'ri-check-line'; 
                } elseif ($item[$column_name] === 'error') { 
                    $status_class = 'cex_status_risky'; 
                    $status_icon = 'dashicons-warning';
                } elseif ($item[$column_name] === 'pending') { 
                    $status_class = 'cex_status_paused'; 
                    $status_icon = 'ri-history-line';
                }
                return '<div class="cex_status_badge ' . esc_attr($status_class) . '"><i class="' . $status_icon . '"></i> ' . esc_html(ucfirst($item[$column_name])) . '</div>';
            case 'response':
                return '<span title="'.esc_attr($item[$column_name]).'">' . esc_html(mb_strimwidth($item[$column_name], 0, 50, "...")) . '</span>';
            case 'sent_at':
            case 'first_opened_at':
                return $item[$column_name] && $item[$column_name] !== '0000-00-00 00:00:00' ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $item[$column_name]) : __('N/A', 'awp');
            case 'open_count':
                return $item[$column_name] > 0 ? '<span class="dashicons dashicons-chart-bar"></span> '.intval($item[$column_name]) : '0';
            default:
                return esc_html($item[$column_name]);
        }
    }
    
    private function get_query_where_clauses() {
        global $wpdb;
        $where_clauses = [];
        $search_term = '';

        if (!empty($_REQUEST['s'])) {
            $search_term = sanitize_text_field($_REQUEST['s']);
            if ($search_term) {
                $where_clauses[] = $wpdb->prepare("(el.email_address LIKE %s OR el.subject LIKE %s OR el.response LIKE %s OR c.name LIKE %s)", 
                    '%' . $wpdb->esc_like($search_term) . '%', 
                    '%' . $wpdb->esc_like($search_term) . '%', 
                    '%' . $wpdb->esc_like($search_term) . '%',
                    '%' . $wpdb->esc_like($search_term) . '%'
                );
            }
        }
        
        if (!empty($_REQUEST['campaign_filter_id'])) {
            $campaign_filter_id = intval($_REQUEST['campaign_filter_id']);
            if ($campaign_filter_id > 0) {
                $where_clauses[] = $wpdb->prepare("el.campaign_id = %d", $campaign_filter_id);
            }
        }
        
        if (!empty($_REQUEST['status_filter'])) {
            $status_filter = sanitize_text_field($_REQUEST['status_filter']);
            if ($status_filter === 'readed') {
                $where_clauses[] = "el.first_opened_at IS NOT NULL AND el.first_opened_at != '0000-00-00 00:00:00' AND el.open_count > 0";
            } elseif (in_array($status_filter, ['pending', 'sent', 'error'])) {
                $where_clauses[] = $wpdb->prepare("el.status = %s", $status_filter);
            }
        }
        
        if (!empty($_REQUEST['type_filter'])) {
            $type_filter = sanitize_text_field($_REQUEST['type_filter']);
            if ($type_filter !== '') { 
                $where_clauses[] = $wpdb->prepare("el.type = %s", $type_filter);
            }
        }
        return $where_clauses;
    }

    public function prepare_items() {
        global $wpdb;
        $table_name_log = $wpdb->prefix . 'wawp_email_log' . " el"; 
        $table_name_campaigns = $wpdb->prefix . "wawp_campaigns c"; 

        $per_page = $this->get_items_per_page('wawp_emails_per_page', 20);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->process_bulk_action();

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $orderby = (!empty($_REQUEST['orderby']) && array_key_exists($_REQUEST['orderby'], $this->get_sortable_columns())) ? $_REQUEST['orderby'] : 'id';
        $orderby_sql = 'el.' . $orderby;
        
        $order = (!empty($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), ['ASC', 'DESC'])) ? $_REQUEST['order'] : 'DESC';

        $where_clauses = $this->get_query_where_clauses();
        
        $join_clause = "";
        if (!empty($_REQUEST['s']) || !empty($_REQUEST['campaign_filter_id']) || !empty($_REQUEST['type_filter'])) { 
            $join_clause = " LEFT JOIN {$table_name_campaigns} ON el.campaign_id = c.id";
        }
        
        $sql_where = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

        $this->stats_data['total_logged'] = (int) $wpdb->get_var("SELECT COUNT(el.id) FROM {$table_name_log}{$join_clause}" . $sql_where);
        
        $sent_where_sql = ($sql_where ? $sql_where . " AND el.status = 'sent'" : " WHERE el.status = 'sent'");
        $this->stats_data['total_sent'] = (int) $wpdb->get_var("SELECT COUNT(el.id) FROM {$table_name_log}{$join_clause}" . $sent_where_sql);
        
        $pending_where_sql = ($sql_where ? $sql_where . " AND el.status = 'pending'" : " WHERE el.status = 'pending'");
        $this->stats_data['total_pending'] = (int) $wpdb->get_var("SELECT COUNT(el.id) FROM {$table_name_log}{$join_clause}" . $pending_where_sql);

        $error_where_sql = ($sql_where ? $sql_where . " AND el.status = 'error'" : " WHERE el.status = 'error'");
        $this->stats_data['total_errors'] = (int) $wpdb->get_var("SELECT COUNT(el.id) FROM {$table_name_log}{$join_clause}" . $error_where_sql);
        
        $opened_where_sql = ($sql_where ? $sql_where . " AND el.first_opened_at IS NOT NULL AND el.first_opened_at != '0000-00-00 00:00:00' AND el.open_count > 0" : " WHERE el.first_opened_at IS NOT NULL AND el.first_opened_at != '0000-00-00 00:00:00' AND el.open_count > 0");
        $this->stats_data['total_opened'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT el.id) FROM {$table_name_log}{$join_clause}" . $opened_where_sql); 
        
        $this->stats_data['open_rate'] = ($this->stats_data['total_sent'] > 0) ? round(($this->stats_data['total_opened'] / $this->stats_data['total_sent']) * 100, 2) : 0;

        $this->stats_data['active_campaigns'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT el.campaign_id) FROM {$table_name_log}{$join_clause}" . $sql_where);
        $this->stats_data['unique_recipients_reached'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT el.email_address) FROM {$table_name_log}{$join_clause}" . $sent_where_sql);

        $query = "SELECT el.* FROM {$table_name_log}{$join_clause} {$sql_where}";
        $query .= $wpdb->prepare(" ORDER BY {$orderby_sql} {$order} LIMIT %d OFFSET %d", $per_page, $offset);
        
        $this->items = $wpdb->get_results($query, ARRAY_A);
        $total_items = $this->stats_data['total_logged'];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    protected function extra_tablenav($which) {
        if ($which == "top") {
            global $wpdb;
            $campaigns = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}wawp_campaigns ORDER BY name ASC", ARRAY_A);
            $current_campaign_filter = !empty($_REQUEST['campaign_filter_id']) ? intval($_REQUEST['campaign_filter_id']) : '';
            $current_status_filter = !empty($_REQUEST['status_filter']) ? sanitize_text_field($_REQUEST['status_filter']) : '';
            $current_type_filter = !empty($_REQUEST['type_filter']) ? sanitize_text_field($_REQUEST['type_filter']) : ''; 
            ?>
            <div class="alignleft actions">
                <select name="campaign_filter_id" id="campaign_filter_id_filter">
                    <option value=""><?php _e('All Campaigns', 'awp'); ?></option>
                    <?php foreach ($campaigns as $campaign) : ?>
                        <option value="<?php echo esc_attr($campaign['id']); ?>" <?php selected($current_campaign_filter, $campaign['id']); ?>>
                            <?php echo esc_html(mb_strimwidth($campaign['name'], 0, 30, "...")); ?> (ID: <?php echo esc_html($campaign['id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status_filter" id="status_filter_id">
                    <option value=""><?php _e('All Statuses', 'awp'); ?></option>
                    <option value="pending" <?php selected($current_status_filter, 'pending'); ?>><?php _e('Pending', 'awp'); ?></option>
                    <option value="sent" <?php selected($current_status_filter, 'sent'); ?>><?php _e('Sent', 'awp'); ?></option>
                    <option value="error" <?php selected($current_status_filter, 'error'); ?>><?php _e('Error', 'awp'); ?></option>
                    <option value="readed" <?php selected($current_status_filter, 'readed'); ?>><?php _e('Readed', 'awp'); ?></option> <!-- MODIFIED: Added 'Readed' option -->
                </select>
                <select name="type_filter" id="type_filter_id">
                    <option value=""><?php _e('All Types', 'awp'); ?></option>
                    <option value="campaign" <?php selected($current_type_filter, 'campaign'); ?>><?php _e('Campaign', 'awp'); ?></option>
                    <option value="manual" <?php selected($current_type_filter, 'manual'); ?>><?php _e('Manual', 'awp'); ?></option>
                    <option value="abandoned_cart" <?php selected($current_type_filter, 'abandoned_cart'); ?>><?php _e('Abandoned Cart', 'awp'); ?></option>
                </select>
                <?php submit_button(__('Filter'), 'button', 'filter_action', false, ['id' => "post-query-submit"]); ?>
                <?php if ($current_campaign_filter || $current_status_filter || $current_type_filter || !empty($_REQUEST['s'])): ?>
                         <a href="<?php echo esc_url(
                         admin_url('admin.php?page=wawp&awp_section=email_log')
                     ); ?>" class="button" style="margin-left:5px;"><?php _e('Clear Filters', 'awp'); ?></a>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    protected function get_bulk_actions() {
        $actions = ['bulk_delete_email_log' => __('Delete Selected Logs', 'awp')];
        return $actions;
    }

    public function process_bulk_action() {
        if ('bulk_delete_email_log' === $this->current_action() && check_admin_referer('bulk-' . $this->_args['plural'])) {
            if (isset($_REQUEST['log_id']) && is_array($_REQUEST['log_id'])) {
                $log_ids = array_map('intval', $_REQUEST['log_id']);
                if (!empty($log_ids)) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'wawp_email_log'; 
                    $ids_string = implode(',', $log_ids);
                    $deleted_count = $wpdb->query("DELETE FROM {$table_name} WHERE id IN ($ids_string)");
                    if($deleted_count !== false){
                         add_settings_error('wawp_email_log_notices', 'bulk_delete_success', sprintf(_n('%d log entry deleted.', '%d log entries deleted.', $deleted_count, 'awp'), $deleted_count), 'updated');
                    } else {
                         add_settings_error('wawp_email_log_notices', 'bulk_delete_error', __('Error deleting log entries.', 'awp'), 'error');
                    }
                }
            }
        }
    }

    public function display_stats_cards() {
        if (empty($this->stats_data)) {
            return;
        }
        ?>
        <style>
        .awp-cards.mail-logs {
            flex-direction: row;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 2.5rem;
        }
        
        .awp-cards.mail-logs > * {
            max-width: calc((100% - 1rem)/3);
            flex: auto;
            gap: .5rem;
        }
        
        .awp-cards.mail-logs .card_number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            color: #044;
        }
        
        .tablenav {
            height: auto;
            margin: .5rem 0 .5rem;
            padding: 0;
        }
        
        /* Specific badge colors - consistent with stat cards */
        .cex_status_completed { 
            background-color: #affebf; 
            color: #014b40;
        } 
        .cex_status_risky { 
            background-color: #fed1d7; 
            color: #8e0b21;
        }
        .cex_status_paused { 
            background-color: #ffeb78; 
            color: #4f4700; 
        } 
        .cex_status_unknown, .cex_status_info { 
            background-color: #d5ebff;
            color: #003a5a;
        } 
        .cex_status_opened { 
            background-color: #affebf; 
            color: #014b40;
        }

        </style>

        <div class="awp-cards mail-logs">
            <div class="awp-card">
                <div class="card-header">
                    <h4 class="card-title"><i class="ri-archive-line"></i><?php _e('Total Logged', 'awp'); ?></h4>
                    <p><?php _e('All entries in current view.', 'awp'); ?></p>
                </div>
                <div class="card_number"><?php echo number_format_i18n($this->stats_data['total_logged']); ?></div>
            </div>
            <div class="awp-card status-sent">
                <div class="card-header">
                    <h4 class="card-title"><i class="ri-mail-line"></i><?php _e('Emails Sent', 'awp'); ?></h4>
                    <p><?php _e('Successfully processed & sent.', 'awp'); ?></p>
                </div>
                <div class="card_number"><?php echo number_format_i18n($this->stats_data['total_sent']); ?></div>
            </div>
               <div class="awp-card status-opened">
                <div class="card-header">
                    <h4 class="card-title"><i class="ri-eye-line"></i><?php _e('Unique Reads', 'awp'); ?></h4>
                    <p><?php printf(__('%.2f%% Read Rate (of sent)', 'awp'), $this->stats_data['open_rate']); ?></p>
                </div>
                <div class="card_number"><?php echo number_format_i18n($this->stats_data['total_opened']); ?></div>
            </div>
            <div class="awp-card status-pending">
                <div class="card-header">
                    <h4 class="card-title"><i class="ri-history-line"></i><?php _e('Emails Pending', 'awp'); ?></h4>
                    <p><?php _e('Queued for sending.', 'awp'); ?></p>
                </div>
                <div class="card_number"><?php echo number_format_i18n($this->stats_data['total_pending']); ?></div>
            </div>
            <div class="awp-card status-error">
                <div class="card-header">
                    <h4 class="card-title"><i class="ri-error-warning-line"></i><?php _e('Send Errors', 'awp'); ?></h4>
                    <p><?php _e('Failed to send.', 'awp'); ?></p>
                </div>
                <div class="card_number"><?php echo number_format_i18n($this->stats_data['total_errors']); ?></div>
            </div>
            <div class="awp-card status-users">
                <div class="card-header">
                    <h4 class="card-title"><i class="ri-user-line"></i><?php _e('Unique Recipients', 'awp'); ?></h4>
                    <p><?php _e('Unique emails successfully sent to.', 'awp'); ?></p>
                </div>
                <div class="card_number"><?php echo number_format_i18n($this->stats_data['unique_recipients_reached']); ?></div>
            </div>
        </div>

        <?php
    }

    public function display() {
        echo '<div id="wawp-email-log-top-notices">';
        settings_errors('wawp_email_log_notices');
        echo '</div>';
        $this->display_stats_cards(); 
        echo '<div class="clear"></div>'; 
        parent::display(); 
    }
}
