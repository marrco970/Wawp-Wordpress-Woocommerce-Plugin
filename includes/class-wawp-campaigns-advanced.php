<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-wawp-campaigns-admin.php';

class WP_Wawp_Campaigns_Advanced
{
    const CAMP_TABLE = 'wawp_campaigns';
    const QUEUE_TABLE = 'wawp_campaigns_queue';
    const EMAIL_LOG_TABLE = 'wawp_email_log';
    const LOG_TABLE = 'awp_notifications_log';
    private $camp_table;
    private $queue_table;
    private $email_log_table;
    private $log_table;
    private $cron_hook = 'wp_campaigns_cron_send_advanced';
    private $admin;
    private $email_log_list_table;

    public function __construct()
    {
        global $wpdb;
        $this->camp_table = $wpdb->prefix . self::CAMP_TABLE;
        $this->queue_table = $wpdb->prefix . self::QUEUE_TABLE;
        $this->email_log_table = $wpdb->prefix . self::EMAIL_LOG_TABLE;
        $this->log_table = $wpdb->prefix . self::LOG_TABLE;
        $this->admin = new WP_Wawp_Campaigns_Admin();
        add_filter('cron_schedules', [$this, 'cron_schedules']);
        add_action($this->cron_hook, [$this, 'cron_send']);
        add_action('wp_ajax_camp_ext_preview', [$this, 'ajax_preview']);
        add_action('wp_ajax_camp_ext_calc_recipients', [$this, 'ajax_calc_recipients']);
        add_action('admin_post_campaign_create', [$this, 'create']);
        add_action('admin_post_campaign_update', [$this, 'update']);
        add_action('admin_post_campaign_pause', [$this, 'pause']);
        add_action('admin_post_campaign_delete', [$this, 'delete']);
        add_action('admin_post_campaign_action', [$this, 'action']);
        add_action('wp_ajax_run_risky_campaign', [$this, 'ajax_run_risky']);

        add_action('wp_ajax_wawp_track_email_open', [$this, 'handle_email_open_tracking']);
        add_action('wp_ajax_nopriv_wawp_track_email_open', [$this, 'handle_email_open_tracking']);
    }

    private function is_segmentation_enabled(): bool
    {
        $user_data = get_transient('siteB_user_data');
        return (bool)($user_data['segmentation_notifications'] ?? false);
    }

    private function render_feature_locked_message() {
        echo '<div class="wrap">';
        echo '<h1 class="cex_h1"><i class="bi bi-megaphone-fill"></i> ' . esc_html__('Wawp Campaigns', 'awp') . '</h1>';
        echo '<div class="awp-card" style="padding: 1em 2em; margin-top: 20px; text-align: center;">';
        echo '<p style="font-size: 1.2em; font-weight: bold; color: #d63638;">' . esc_html__('You must purchase the multi-personalized group marketing feature to access this section.', 'awp') . '</p>';
        echo '<a href="https://wawp.net/pricing/" target="_blank" class="awp-btn primary" style="margin-top: 15px;">' . esc_html__('Learn More', 'awp') . '</a>';
        echo '</div>';
        echo '</div>';
    }

    public function cron_schedules($schedules) {
        if (!isset($schedules['minute'])) {
            $schedules['minute'] = ['interval' => 60, 'display' => 'Every 1 minute'];
        }
        return $schedules;
    }

    public function page_email_log() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1><i class="bi bi-envelope-paper-fill"></i> Wawp Email Log</h1>';
        
        $this->email_log_list_table = new Wawp_Email_Log_List_Table();
        $this->email_log_list_table->prepare_items();
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page']) . '" />';
        $this->email_log_list_table->search_box('Search Emails/Subjects', 'email_log_search');
        $this->email_log_list_table->display();
        echo '</form>';
        echo '</div>';
    }

    public function page_campaigns() {
        if (!current_user_can('manage_options')) return;

        if (!$this->is_segmentation_enabled()) {
            $this->render_feature_locked_message();
            return; 
        }

        global $wpdb;
        $edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
        if ($edit_id) {
            $this->admin->render_html('edit_campaign', ['edit_id' => $edit_id]);
            return;
        }
        $rows = $wpdb->get_results("SELECT * FROM {$this->camp_table} ORDER BY id DESC");
        $this->admin->render_html('campaigns_list', ['rows' => $rows]);
    }

    public function page_new_campaign() {
        if (!current_user_can('manage_options')) return;

        if (!$this->is_segmentation_enabled()) {
            $this->render_feature_locked_message();
            return;
        }

        $this->admin->render_html('new_campaign_multi', []);
    }

    public function ajax_calc_recipients() {
        if (!current_user_can('manage_options')) wp_die('No perm');
        if (!check_ajax_referer('camp_calc_recip_nonce', '_camp_calc_nonce', false)) wp_die('Bad nonce');
        
        if (!$this->is_segmentation_enabled()) {
            wp_send_json_error(['message' => __('Segmentation feature not enabled. Cannot calculate recipients.', 'awp')]);
        }

        $roles = isset($_POST['roles']) ? array_map('sanitize_text_field', (array)$_POST['roles']) : [];
        $users = isset($_POST['users']) ? array_map('intval', (array)$_POST['users']) : [];
        $billing_countries = isset($_POST['billing_countries']) ? array_map('sanitize_text_field', (array)$_POST['billing_countries']) : [];
        $wp_profile_languages = isset($_POST['wp_profile_languages']) ? array_map('sanitize_text_field', (array)$_POST['wp_profile_languages']) : [];
        $woo_products = isset($_POST['woo_products']) ? array_map('intval', (array)$_POST['woo_products']) : [];
        $woo_statuses = isset($_POST['woo_statuses']) ? array_map('sanitize_text_field', (array)$_POST['woo_statuses']) : [];
        $woo_spent = isset($_POST['woo_spent']) ? floatval($_POST['woo_spent']) : 0;
        $woo_orders = isset($_POST['woo_orders']) ? intval($_POST['woo_orders']) : 0;
        $only_verified = isset($_POST['only_verified']) ? intval($_POST['only_verified']) : 0;

        $all_ids = $this->merge_roles_users($roles, $users);
        $all_ids = $this->filter_by_billing_countries($all_ids, $billing_countries);
        $all_ids = $this->filter_by_profile_languages($all_ids, $wp_profile_languages);
        $all_ids = $this->segment_filter($all_ids, $woo_spent, $woo_orders, $only_verified);
        if (class_exists('WooCommerce') && (!empty($woo_products) || !empty($woo_statuses))) {
            $all_ids = $this->filter_by_purchase_and_status($all_ids, $woo_products, $woo_statuses);
        }
        
        echo count($all_ids);
        wp_die();
    }

    public function ajax_preview() {
        if (!current_user_can('manage_options')) wp_die();
        if (!check_ajax_referer('camp_preview_nonce', '_camp_preview_nonce', false)) wp_die('Bad nonce');
        
        if (!$this->is_segmentation_enabled()) {
            wp_send_json_error(['message' => __('Segmentation feature not enabled. Cannot preview content.', 'awp')]);
        }

        $id = intval($_POST['post_id'] ?? 0);
        if (!$id) { echo ''; wp_die(); }
        $p = get_post($id);
        if (!$p) { echo 'Not found'; wp_die(); }
        $title = esc_html($p->post_title); $type = esc_html($p->post_type);
        $excerpt = $p->post_excerpt ? $p->post_excerpt : wp_trim_words($p->post_content, 40);
        $excerpt = esc_html(strip_tags($excerpt));
        echo '<strong>Type:</strong> ' . $type . '<br><strong>Title:</strong> ' . $title . '<br><strong>Excerpt:</strong> ' . $excerpt . '<br>';
        if (has_post_thumbnail($id)) {
            echo '<strong>Image:</strong><br><img src="' . esc_url(get_the_post_thumbnail_url($id, 'medium')) . '" class="camp_preview_img">';
        }
        wp_die();
    }

    public function create() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        
        if (!$this->is_segmentation_enabled()) {
            wp_die(__('Segmentation feature not enabled. Cannot create campaigns.', 'awp'));
        }

        $is_save_for_later = isset($_POST['save_for_later']) && $_POST['save_for_later'] == '1';
        $form_errors = [];

        if (empty($_POST['name'])) $form_errors['name'] = 'Campaign Name is required.';
        
        $send_whatsapp = isset($_POST['send_whatsapp']) ? 1 : 0;
        $send_email = isset($_POST['send_email']) ? 1 : 0;
        $instances_from_post = isset($_POST['instances']) && is_array($_POST['instances']) ? array_map('sanitize_text_field', $_POST['instances']) : [];
        $roles_from_post = isset($_POST['role_ids']) && is_array($_POST['role_ids']) ? array_map('sanitize_text_field', $_POST['role_ids']) : [];
        $users_from_post = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];
        $external_numbers_raw = isset($_POST['external_numbers']) ? trim(sanitize_textarea_field($_POST['external_numbers'])) : '';
        $external_emails_raw = isset($_POST['external_emails']) ? trim(sanitize_textarea_field($_POST['external_emails'])) : '';

        $audience_provided = !empty($roles_from_post) || !empty($users_from_post) || !empty($external_numbers_raw) || !empty($external_emails_raw);

        if (!$is_save_for_later) {
            if (!$send_whatsapp && !$send_email) $form_errors['channels'] = 'At least one channel (WhatsApp or Email) must be enabled.';
            if ($send_whatsapp && empty($instances_from_post)) $form_errors['instances'] = 'At least one Instance must be selected for WhatsApp.';
            if (!$audience_provided) $form_errors['audience'] = 'You must select Target Roles, Users, or provide External Numbers/Emails.';
            if ($send_email && empty(trim($_POST['email_subject']))) $form_errors['email_subject'] = 'Email Subject is required if Send Email is enabled.';
            if ($send_email && empty(trim($_POST['email_message']))) $form_errors['email_message'] = 'Email Body is required if Send Email is enabled.';
            if ($send_whatsapp && $_POST['send_type'] !== 'post' && $_POST['send_type'] !== 'product' && empty(trim($_POST['message']))) {
                if (! (isset($_POST['append_post']) && $_POST['send_type'] === 'post' && !empty($_POST['post_id'])) && ! (isset($_POST['append_product']) && $_POST['send_type'] === 'product' && !empty($_POST['product_id'])) ) {
                    $form_errors['message'] = 'WhatsApp Message Text is required unless Post/Product content replaces it.';
                }
            }
        }

        if (!empty($form_errors)) {
            if ($is_save_for_later && isset($form_errors['name'])) {
                wp_die($form_errors['name'] . '<br><small>(Save for Later - Name Check)</small>');
            } elseif (!$is_save_for_later) {
                wp_die(implode('<br>', array_values($form_errors)) . '<br><small>(Create - Full Validation)</small>');
            }
        }
        
        global $wpdb;
        $name = sanitize_text_field($_POST['name']);
        $media_url_val = '';
        if (!empty($_POST['media_url'])) {
            $media_url_val = sanitize_text_field($_POST['media_url']);
        } elseif (!empty($_FILES['media_file_upload']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $uploaded_file = wp_handle_upload($_FILES['media_file_upload'], ['test_form' => false]);
            if ($uploaded_file && !isset($uploaded_file['error'])) {
                $file_path = $uploaded_file['file'];
                $file_type = wp_check_filetype(basename($file_path), null);
                $attachment_data = ['post_mime_type' => $file_type['type'], 'post_title' => sanitize_file_name(basename($file_path)), 'post_content' => '', 'post_status' => 'inherit'];
                $attach_id = wp_insert_attachment($attachment_data, $file_path);
                if (!is_wp_error($attach_id)) {
                    $attach_meta = wp_generate_attachment_metadata($attach_id, $file_path);
                    wp_update_attachment_metadata($attach_id, $attach_meta);
                    $media_url_val = wp_get_attachment_url($attach_id);
                }
            }
        }

        $vf = isset($_POST['only_verified_phone']) ? 1 : 0;
        $min_wa_interval_val = intval($_POST['min_whatsapp_interval'] ?? 60);
        $max_wa_interval_val = intval($_POST['max_whatsapp_interval'] ?? 75);
        if ($max_wa_interval_val < $min_wa_interval_val) $max_wa_interval_val = $min_wa_interval_val + 15;
        $min_email_interval_val = intval($_POST['min_email_interval'] ?? 30);
        $max_email_interval_val = intval($_POST['max_email_interval'] ?? 60);
        if ($max_email_interval_val < $min_email_interval_val) $max_email_interval_val = $min_email_interval_val + 15;

        $max_wa_per_day_val = intval($_POST['max_wa_per_day'] ?? 0);
        $max_email_per_day_val = intval($_POST['max_email_per_day'] ?? 0);

        $start_datetime_raw = sanitize_text_field($_POST['start_datetime'] ?? '');
        $start_datetime_val = current_time('Y-m-d H:i:s');
        if ($start_datetime_raw && strtotime($start_datetime_raw) !== false) {
            if (strtotime($start_datetime_raw) < current_time('timestamp') && !$is_save_for_later) {
                $start_datetime_val = current_time('Y-m-d H:i:s');
            } else {
                $start_datetime_val = date('Y-m-d H:i:s', strtotime($start_datetime_raw));
            }
        }

        $all_wp_user_ids = [];  
        if (!empty($roles_from_post) || !empty($users_from_post)) {
            $all_wp_user_ids = $this->merge_roles_users($roles_from_post, $users_from_post);
            $all_wp_user_ids = array_unique($all_wp_user_ids);  
        }
        
        $whatsapp_recipients_from_wp = [];
        $email_recipients_from_wp = [];

        $billing_countries_data = isset($_POST['billing_countries']) ? array_map('sanitize_text_field', (array)$_POST['billing_countries']) : [];
        $wp_profile_languages_data = isset($_POST['wp_profile_languages']) ? array_map('sanitize_text_field', (array)$_POST['wp_profile_languages']) : [];
        $woo_products_data = isset($_POST['woo_products']) ? array_map('intval', (array)$_POST['woo_products']) : [];
        $woo_statuses_data = isset($_POST['woo_statuses']) ? array_map('sanitize_text_field', (array)$_POST['woo_statuses']) : [];
        $woo_spent_data = floatval($_POST['woo_spent_over'] ?? 0);
        $woo_orders_data = intval($_POST['woo_orders_over'] ?? 0);

        if ($send_whatsapp) {
            $whatsapp_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
            $whatsapp_recipients_from_wp = $this->filter_by_profile_languages($whatsapp_recipients_from_wp, $wp_profile_languages_data);
            $whatsapp_recipients_from_wp = $this->segment_filter($whatsapp_recipients_from_wp, $woo_spent_data, $woo_orders_data, $vf); 
            if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                $whatsapp_recipients_from_wp = $this->filter_by_purchase_and_status($whatsapp_recipients_from_wp, $woo_products_data, $woo_statuses_data);
            }
        }

        if ($send_email) {
            $email_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
            $email_recipients_from_wp = $this->filter_by_profile_languages($email_recipients_from_wp, $wp_profile_languages_data);
            $email_recipients_from_wp = $this->segment_filter($email_recipients_from_wp, $woo_spent_data, $woo_orders_data, 0); 
            if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                $email_recipients_from_wp = $this->filter_by_purchase_and_status($email_recipients_from_wp, $woo_products_data, $woo_statuses_data);
            }
        }

        $external_numbers_for_queue = [];
        if ($external_numbers_raw) {
            $lines = preg_split('/[\r\n]+/', $external_numbers_raw);
            foreach ($lines as $ln) { $ln = trim($ln); if ($ln) $external_numbers_for_queue[] = preg_replace('/\D+/', '', $ln); }
        }
        $external_numbers_for_queue = array_unique($external_numbers_for_queue);

        $external_emails_for_log = [];
        if ($external_emails_raw) {
            $lines = preg_split('/[\r\n]+/', $external_emails_raw);
            foreach ($lines as $ln) { if (is_email(trim($ln))) $external_emails_for_log[] = trim($ln); }
        }
        $external_emails_for_log = array_unique($external_emails_for_log);
        
        $total_recipients_whatsapp = count($whatsapp_recipients_from_wp) + count($external_numbers_for_queue);
        $total_recipients_email = count($email_recipients_from_wp) + count($external_emails_for_log);

        $total_recipients = max($total_recipients_whatsapp, $total_recipients_email);  


        if ($total_recipients === 0 && ($send_whatsapp || $send_email) && !$is_save_for_later) {
            wp_die('No recipients found after applying all filters. Please adjust your audience selection or filters.');
        }

        $status_to_set = 'saved';  
        $campaign_is_runnable = ($total_recipients_whatsapp > 0 || $total_recipients_email > 0) && ($send_whatsapp || $send_email);
        
        if (!$is_save_for_later && ($total_recipients_whatsapp > 0 || $total_recipients_email > 0) && ($send_whatsapp || $send_email)) {
            $is_risky_whatsapp = ($max_wa_per_day_val > 0 && $max_wa_per_day_val < $total_recipients_whatsapp && $max_wa_per_day_val > 500) || ($max_wa_per_day_val == 0 && $total_recipients_whatsapp > 500 && $send_whatsapp);
            $is_risky_email = ($max_email_per_day_val > 0 && $max_email_per_day_val < $total_recipients_email && $max_email_per_day_val > 500) || ($max_email_per_day_val == 0 && $total_recipients_email > 500 && $send_email);
            if ($is_risky_whatsapp || $is_risky_email) {
                $status_to_set = 'risky';
            } else {
                $status_to_set = 'active';
            }
        } elseif (!$is_save_for_later && ($total_recipients === 0 || (!$send_whatsapp && !$send_email)) && $audience_provided) {
            $status_to_set = 'saved';  
        }
        
        $db_data = [
            'name' => $name, 'instances' => maybe_serialize($instances_from_post), 'role_ids' => maybe_serialize($roles_from_post),
            'user_ids' => maybe_serialize($users_from_post), 'external_numbers' => $external_numbers_raw,  
            'external_emails' => $external_emails_raw,
            'message' => wp_kses_post($_POST['message'] ?? ''),
            'media_url' => $media_url_val, 'min_whatsapp_interval' => $min_wa_interval_val, 'max_whatsapp_interval' => $max_wa_interval_val,
            'min_email_interval' => $min_email_interval_val, 'max_email_interval' => $max_email_interval_val,
            'start_datetime' => ($status_to_set !== 'saved') ? $start_datetime_val : null, 'repeat_type' => sanitize_text_field($_POST['repeat_type'] ?? 'no'),
            'repeat_days' => intval($_POST['repeat_days'] ?? 0), 'post_id' => intval($_POST['post_id'] ?? 0), 'product_id' => intval($_POST['product_id'] ?? 0),
            'append_post' => isset($_POST['append_post']) ? 1 : 0, 'append_product' => isset($_POST['append_product']) ? 1 : 0,
            'send_type' => sanitize_text_field($_POST['send_type'] ?? 'text'), 'total_count' => $total_recipients, 'processed_count' => 0,
            'status' => $status_to_set, 'paused' => 0, 'next_run' => ($status_to_set === 'active') ? $start_datetime_val : null,
            'woo_spent_over' => floatval($_POST['woo_spent_over'] ?? 0), 'woo_orders_over' => intval($_POST['woo_orders_over'] ?? 0),
            'only_verified_phone' => $vf, 'post_include_title' => isset($_POST['post_include_title']) ? 1 : 0,
            'post_include_excerpt' => isset($_POST['post_include_excerpt']) ? 1 : 0, 'post_include_link' => isset($_POST['post_include_link']) ? 1 : 0,
            'post_include_image' => isset($_POST['post_include_image']) ? 1 : 0, 'product_include_title' => isset($_POST['product_include_title']) ? 1 : 0,
            'product_include_excerpt' => isset($_POST['product_include_excerpt']) ? 1 : 0, 'product_include_price' => isset($_POST['product_include_price']) ? 1 : 0,
            'product_include_link' => isset($_POST['product_include_link']) ? 1 : 0, 'product_include_image' => isset($_POST['product_include_image']) ? 1 : 0,
            'woo_ordered_products' => maybe_serialize(isset($_POST['woo_products']) ? array_map('intval', (array)$_POST['woo_products']) : []),
            'woo_order_statuses' => maybe_serialize(isset($_POST['woo_statuses']) ? array_map('sanitize_text_field', (array)$_POST['woo_statuses']) : []),
            'max_per_day' => 0,
            'max_wa_per_day' => $max_wa_per_day_val,
            'max_email_per_day' => $max_email_per_day_val,
            'billing_countries' => maybe_serialize(isset($_POST['billing_countries']) ? array_map('sanitize_text_field', (array)$_POST['billing_countries']) : []),
            'wp_profile_languages' => maybe_serialize(isset($_POST['wp_profile_languages']) ? array_map('sanitize_text_field', (array)$_POST['wp_profile_languages']) : []),
            'send_whatsapp' => $send_whatsapp, 'send_email' => $send_email,
            'email_subject' => sanitize_text_field($_POST['email_subject'] ?? ''), 'email_message' => wp_kses_post($_POST['email_message'] ?? ''),
        ];
        
        $wpdb->insert($this->camp_table, $db_data);
        $cid = $wpdb->insert_id;

        if (!$is_save_for_later && ($send_whatsapp || $send_email)) {
            if ($send_whatsapp) {
                foreach ($whatsapp_recipients_from_wp as $uid) {
                    $ph = $this->find_phone($uid);  
                    if (!$ph) continue;
                    $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => $uid, 'phone' => $ph, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]);
                }
                foreach ($external_numbers_for_queue as $en) {
                    $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => 0, 'phone' => $en, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]);
                }
            }
            
            if ($send_email) {
                $email_subject_template = sanitize_text_field($_POST['email_subject'] ?? '');
                $email_message_template = wp_kses_post($_POST['email_message'] ?? '');

                foreach ($email_recipients_from_wp as $uid) {
                    $user_email = $this->find_email($uid);  
                    if (!$user_email) continue;
                    $parsed_subject = $this->parse_placeholders($email_subject_template, $uid);
                    $parsed_message = $this->parse_placeholders($email_message_template, $uid);

                    $wpdb->insert($this->email_log_table, [
                        'campaign_id' => $cid, 'user_id' => $uid, 'email_address' => $user_email,
                        'subject' => $parsed_subject,  
                        'message_body' => $parsed_message,
                        'status' => 'pending'
                    ]);
                }
                foreach ($external_emails_for_log as $ee) {
                    $wpdb->insert($this->email_log_table, [
                        'campaign_id' => $cid, 'user_id' => 0, 'email_address' => $ee,
                        'subject' => $email_subject_template,  
                        'message_body' => $email_message_template,
                        'status' => 'pending'
                    ]);
                }
            }
        }
        $redirect_message = $is_save_for_later ? 'saved_for_later' : 'created';
        wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => $redirect_message,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
    
    public function update() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.');
        
        if (!$this->is_segmentation_enabled()) {
            wp_die(__('Segmentation feature not enabled. Cannot update campaigns.', 'awp'));
        }

        $cid = intval($_POST['camp_id'] ?? 0);
        if (!$cid) { wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'error',
                ],
                admin_url( 'admin.php' )
            )
        ); exit; }
        if (!check_admin_referer('campaign_update_' . $cid, '_camp_edit_nonce')) wp_die('Nonce verification failed.');
        
        global $wpdb;
        $old_campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->camp_table} WHERE id=%d", $cid));
        if (!$old_campaign) { wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'error',
                ],
                admin_url( 'admin.php' )
            )
        ); exit; }

        $form_errors = [];
        if (empty($_POST['name'])) $form_errors['name'] = 'Campaign Name is required.';
        $send_whatsapp = isset($_POST['send_whatsapp']) ? 1 : 0;
        $send_email = isset($_POST['send_email']) ? 1 : 0;
        if (!$send_whatsapp && !$send_email) $form_errors['channels'] = 'At least one channel (WhatsApp or Email) must be enabled.';
        if ($send_whatsapp && ( !isset($_POST['instances']) || empty($_POST['instances']) ) ) $form_errors['instances'] = 'At least one Instance must be selected for WhatsApp.';
        
        $roles_from_post = isset($_POST['role_ids']) && is_array($_POST['role_ids']) ? array_map('sanitize_text_field', $_POST['role_ids']) : [];
        $users_from_post = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];
        $external_numbers_raw = isset($_POST['external_numbers']) ? trim(sanitize_textarea_field($_POST['external_numbers'])) : '';
        $external_emails_raw = isset($_POST['external_emails']) ? trim(sanitize_textarea_field($_POST['external_emails'])) : '';
        $audience_provided = !empty($roles_from_post) || !empty($users_from_post) || !empty($external_numbers_raw) || !empty($external_emails_raw);

        if (!$audience_provided) $form_errors['audience'] = 'Audience selection is required.';
        if ($send_email && empty(trim($_POST['email_subject']))) $form_errors['email_subject'] = 'Email Subject is required if Send Email is enabled.';
        if ($send_email && empty(trim($_POST['email_message']))) $form_errors['email_message'] = 'Email Body is required if Send Email is enabled.';
        if ($send_whatsapp && $_POST['send_type'] !== 'post' && $_POST['send_type'] !== 'product' && empty(trim($_POST['message']))) {
            if (! (isset($_POST['append_post']) && $_POST['send_type'] === 'post' && !empty($_POST['post_id'])) && ! (isset($_POST['append_product']) && $_POST['send_type'] === 'product' && !empty($_POST['product_id'])) ) {
                $form_errors['message'] = 'WhatsApp Message Text is required unless Post/Product content replaces it.';
            }
        }
        if (!empty($form_errors)) wp_die(implode('<br>', array_values($form_errors)));

        $name = sanitize_text_field($_POST['name']);
        $inst_data = isset($_POST['instances']) && is_array($_POST['instances']) ? array_map('sanitize_text_field', $_POST['instances']) : [];
        $media_url_val = sanitize_text_field($_POST['media_url'] ?? $old_campaign->media_url);
        if (!empty($_FILES['media_file_upload']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
            $uploaded_file = wp_handle_upload($_FILES['media_file_upload'], ['test_form' => false]);
            if ($uploaded_file && !isset($uploaded_file['error'])) {
                $media_url_val = wp_get_attachment_url(wp_insert_attachment(['post_mime_type' => wp_check_filetype(basename($uploaded_file['file']), null)['type'], 'post_title' => sanitize_file_name(basename($uploaded_file['file'])), 'post_content' => '', 'post_status' => 'inherit'], $uploaded_file['file']));
            }
        }
        
        $vf = isset($_POST['only_verified_phone']) ? 1 : 0;
        $min_wa_interval_val = intval($_POST['min_whatsapp_interval'] ?? ($old_campaign->min_whatsapp_interval ?? 60));
        $max_wa_interval_val = intval($_POST['max_whatsapp_interval'] ?? ($old_campaign->max_whatsapp_interval ?? 75));
        if ($max_wa_interval_val < $min_wa_interval_val) $max_wa_interval_val = $min_wa_interval_val + 15;
        $min_email_interval_val = intval($_POST['min_email_interval'] ?? ($old_campaign->min_email_interval ?? 30));
        $max_email_interval_val = intval($_POST['max_email_interval'] ?? ($old_campaign->max_email_interval ?? 60));
        if ($max_email_interval_val < $min_email_interval_val) $max_email_interval_val = $min_email_interval_val + 15;
        
        $max_wa_per_day_val = intval($_POST['max_wa_per_day'] ?? ($old_campaign->max_wa_per_day ?? 0));
        $max_email_per_day_val = intval($_POST['max_email_per_day'] ?? ($old_campaign->max_email_per_day ?? 0));

        $start_datetime_raw = sanitize_text_field($_POST['start_datetime'] ?? '');
        $start_datetime_val = $old_campaign->start_datetime;
        if ($start_datetime_raw && strtotime($start_datetime_raw) !== false) {
            $new_start_timestamp = strtotime($start_datetime_raw);
            if ($new_start_timestamp < current_time('timestamp') && $old_campaign->processed_count == 0 && $old_campaign->status !== 'saved' ) {
                $start_datetime_val = current_time('Y-m-d H:i:s');
            } else {
                $start_datetime_val = date('Y-m-d H:i:s', $new_start_timestamp);
            }
        }

        $recalculate_audience = false;
        $old_billing_countries = $old_campaign->billing_countries ?? '';
        $old_wp_profile_languages = $old_campaign->wp_profile_languages ?? '';

        if (maybe_serialize($inst_data) !== $old_campaign->instances ||
            maybe_serialize($roles_from_post) !== $old_campaign->role_ids ||
            maybe_serialize($users_from_post) !== $old_campaign->user_ids ||
            $external_numbers_raw !== $old_campaign->external_numbers ||
            $external_emails_raw !== ($old_campaign->external_emails ?? '') ||  
            (isset($_POST['billing_countries']) && maybe_serialize(array_map('sanitize_text_field', (array)$_POST['billing_countries'])) !== $old_billing_countries) ||
            (isset($_POST['wp_profile_languages']) && maybe_serialize(array_map('sanitize_text_field', (array)$_POST['wp_profile_languages'])) !== $old_wp_profile_languages) ||
            (isset($_POST['woo_products']) && maybe_serialize(array_map('intval', (array)$_POST['woo_products'])) !== $old_campaign->woo_ordered_products) ||
            (isset($_POST['woo_statuses']) && maybe_serialize(array_map('sanitize_text_field', (array)$_POST['woo_statuses'])) !== $old_campaign->woo_order_statuses) ||
            (isset($_POST['woo_spent_over']) && floatval($_POST['woo_spent_over']) != floatval($old_campaign->woo_spent_over)) ||
            (isset($_POST['woo_orders_over']) && intval($_POST['woo_orders_over']) != intval($old_campaign->woo_orders_over)) ||
            (isset($_POST['only_verified_phone']) ? 1:0) != $old_campaign->only_verified_phone ||
            $send_whatsapp != ($old_campaign->send_whatsapp ?? 1) ||
            $send_email != ($old_campaign->send_email ?? 0)
            ) {
            $recalculate_audience = true;
        }

        $total_recipients_whatsapp = 0;
        $total_recipients_email = 0;

        if ($recalculate_audience && $audience_provided) {
            $billing_countries_data = isset($_POST['billing_countries']) ? array_map('sanitize_text_field', (array)$_POST['billing_countries']) : [];
            $wp_profile_languages_data = isset($_POST['wp_profile_languages']) ? array_map('sanitize_text_field', (array)$_POST['wp_profile_languages']) : [];
            $woo_products_data = isset($_POST['woo_products']) ? array_map('intval', (array)$_POST['woo_products']) : [];
            $woo_statuses_data = isset($_POST['woo_statuses']) ? array_map('sanitize_text_field', (array)$_POST['woo_statuses']) : [];
            $woo_spent_data = floatval($_POST['woo_spent_over'] ?? 0);
            $woo_orders_data = intval($_POST['woo_orders_over'] ?? 0);

            $all_wp_user_ids = $this->merge_roles_users($roles_from_post, $users_from_post);
            $all_wp_user_ids = array_unique($all_wp_user_ids);

            $whatsapp_recipients_from_wp = [];
            $email_recipients_from_wp = [];

            if ($send_whatsapp) {
                $whatsapp_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
                $whatsapp_recipients_from_wp = $this->filter_by_profile_languages($whatsapp_recipients_from_wp, $wp_profile_languages_data);
                $whatsapp_recipients_from_wp = $this->segment_filter($whatsapp_recipients_from_wp, $woo_spent_data, $woo_orders_data, $vf);
                if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                    $whatsapp_recipients_from_wp = $this->filter_by_purchase_and_status($whatsapp_recipients_from_wp, $woo_products_data, $woo_statuses_data);
                }
            }

            if ($send_email) {
                $email_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
                $email_recipients_from_wp = $this->filter_by_profile_languages($email_recipients_from_wp, $wp_profile_languages_data);
                $email_recipients_from_wp = $this->segment_filter($email_recipients_from_wp, $woo_spent_data, $woo_orders_data, 0);
                if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                    $email_recipients_from_wp = $this->filter_by_purchase_and_status($email_recipients_from_wp, $woo_products_data, $woo_statuses_data);
                }
            }

            $external_numbers_for_queue = [];
            if ($external_numbers_raw) { $lines = preg_split('/[\r\n]+/', $external_numbers_raw); foreach ($lines as $ln) { $ln = trim($ln); if ($ln) $external_numbers_for_queue[] = preg_replace('/\D+/', '', $ln); }}
            $external_numbers_for_queue = array_unique($external_numbers_for_queue);

            $external_emails_for_log = [];
            if ($external_emails_raw) { $lines = preg_split('/[\r\n]+/', $external_emails_raw); foreach ($lines as $ln) { if (is_email(trim($ln))) $external_emails_for_log[] = trim($ln); }}
            $external_emails_for_log = array_unique($external_emails_for_log);

            $total_recipients_whatsapp = count($whatsapp_recipients_from_wp) + count($external_numbers_for_queue);
            $total_recipients_email = count($email_recipients_from_wp) + count($external_emails_for_log);
            $total_recipients = max($total_recipients_whatsapp, $total_recipients_email);
            
            $wpdb->delete($this->queue_table, ['campaign_id' => $cid]);
            $wpdb->delete($this->email_log_table, ['campaign_id' => $cid]);

            if ($send_whatsapp) {
                foreach ($whatsapp_recipients_from_wp as $uid) { $ph = $this->find_phone($uid); if (!$ph) continue; $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => $uid, 'phone' => $ph, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]);}
                foreach ($external_numbers_for_queue as $en) {$wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => 0, 'phone' => $en, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]);}
            }
            if ($send_email) {
                $email_subject_template = sanitize_text_field($_POST['email_subject'] ?? $old_campaign->email_subject);
                $email_message_template = wp_kses_post($_POST['email_message'] ?? $old_campaign->email_message);
                
                foreach ($email_recipients_from_wp as $uid) {
                    $user_email = $this->find_email($uid); if (!$user_email) continue;
                    $parsed_subject = $this->parse_placeholders($email_subject_template, $uid);
                    $parsed_message = $this->parse_placeholders($email_message_template, $uid);
                    $wpdb->insert($this->email_log_table, [
                        'campaign_id' => $cid, 'user_id' => $uid, 'email_address' => $user_email,
                        'subject' => $parsed_subject,  
                        'message_body' => $parsed_message,
                        'status' => 'pending'
                    ]);
                }
                foreach ($external_emails_for_log as $ee) {
                    $wpdb->insert($this->email_log_table, [
                        'campaign_id' => $cid, 'user_id' => 0, 'email_address' => $ee,
                        'subject' => $email_subject_template,  
                        'message_body' => $email_message_template,
                        'status' => 'pending'
                    ]);
                }
            }
            $wpdb->update($this->camp_table, ['processed_count' => 0], ['id' => $cid]);

        } else {

            $total_recipients_whatsapp = $old_campaign->total_count_whatsapp ?? $old_campaign->total_count;
            $total_recipients_email = $old_campaign->total_count_email ?? 0; 
            $total_recipients = $old_campaign->total_count;
        }

        $current_status = $old_campaign->status;
        if ($old_campaign->status === 'saved' || $old_campaign->status === 'risky') {
            if (($total_recipients_whatsapp > 0 || $total_recipients_email > 0) && ($send_whatsapp || $send_email)) {
                $is_risky_whatsapp = ($max_wa_per_day_val > 0 && $max_wa_per_day_val < $total_recipients_whatsapp && $max_wa_per_day_val > 500) || ($max_wa_per_day_val == 0 && $total_recipients_whatsapp > 500 && $send_whatsapp);
                $is_risky_email = ($max_email_per_day_val > 0 && $max_email_per_day_val < $total_recipients_email && $max_email_per_day_val > 500) || ($max_email_per_day_val == 0 && $total_recipients_email > 500 && $send_email);
                if ($is_risky_whatsapp || $is_risky_email) {
                    $current_status = 'risky';
                } else {
                    $current_status = 'active';  
                }
            } else {
                $current_status = 'saved';
            }
        } elseif ($old_campaign->status === 'active') {
            if (($total_recipients_whatsapp > 0 || $total_recipients_email > 0) && ($send_whatsapp || $send_email)) {
                $is_risky_whatsapp = ($max_wa_per_day_val > 0 && $max_wa_per_day_val < $total_recipients_whatsapp && $max_wa_per_day_val > 500) || ($max_wa_per_day_val == 0 && $total_recipients_whatsapp > 500 && $send_whatsapp);
                $is_risky_email = ($max_email_per_day_val > 0 && $max_email_per_day_val < $total_recipients_email && $max_email_per_day_val > 500) || ($max_email_per_day_val == 0 && $total_recipients_email > 500 && $send_email);
                if ($is_risky_whatsapp || $is_risky_email) {
                    $current_status = 'risky';
                } else {
                    $current_status = 'active';  
                }
            } else {
                $current_status = 'saved'; 
            }
        }
        $db_data_update = [
            'name' => $name, 'instances' => maybe_serialize($inst_data),
            'role_ids' => maybe_serialize($roles_from_post),
            'user_ids' => maybe_serialize($users_from_post),
            'external_numbers' => $external_numbers_raw,
            'external_emails' => $external_emails_raw,  
            'message' => wp_kses_post($_POST['message'] ?? $old_campaign->message), 'media_url' => $media_url_val,
            'min_whatsapp_interval' => $min_wa_interval_val, 'max_whatsapp_interval' => $max_wa_interval_val,
            'min_email_interval' => $min_email_interval_val, 'max_email_interval' => $max_email_interval_val,
            'start_datetime' => $start_datetime_val, 'repeat_type' => sanitize_text_field($_POST['repeat_type'] ?? $old_campaign->repeat_type),
            'repeat_days' => intval($_POST['repeat_days'] ?? $old_campaign->repeat_days),
            'post_id' => intval($_POST['post_id'] ?? $old_campaign->post_id), 'product_id' => intval($_POST['product_id'] ?? $old_campaign->product_id),
            'append_post' => isset($_POST['append_post']) ? 1 : 0, 'append_product' => isset($_POST['append_product']) ? 1 : 0,
            'send_type' => sanitize_text_field($_POST['send_type'] ?? $old_campaign->send_type), 'total_count' => $total_recipients,
            'status' => $current_status, 'paused' => $old_campaign->paused,
            'next_run' => ($current_status === 'active' && !$old_campaign->paused) ? ($start_datetime_val ? $start_datetime_val : current_time('mysql')) : null,
            'woo_spent_over' => floatval($_POST['woo_spent_over'] ?? $old_campaign->woo_spent_over),
            'woo_orders_over' => intval($_POST['woo_orders_over'] ?? $old_campaign->woo_orders_over), 'only_verified_phone' => $vf,
            'post_include_title' => isset($_POST['post_include_title']) ? 1 : 0, 'post_include_excerpt' => isset($_POST['post_include_excerpt']) ? 1 : 0,
            'post_include_link' => isset($_POST['post_include_link']) ? 1 : 0, 'post_include_image' => isset($_POST['post_include_image']) ? 1 : 0,
            'product_include_title' => isset($_POST['product_include_title']) ? 1 : 0, 'product_include_excerpt' => isset($_POST['product_include_excerpt']) ? 1 : 0,
            'product_include_price' => isset($_POST['product_include_price']) ? 1 : 0, 'product_include_link' => isset($_POST['product_include_link']) ? 1 : 0,
            'product_include_image' => isset($_POST['product_include_image']) ? 1 : 0,
            'woo_ordered_products' => maybe_serialize(isset($_POST['woo_products']) ? array_map('intval', (array)$_POST['woo_products']) : maybe_unserialize($old_campaign->woo_ordered_products)),
            'woo_order_statuses' => maybe_serialize(isset($_POST['woo_statuses']) ? array_map('sanitize_text_field', (array)$_POST['woo_statuses']) : maybe_unserialize($old_campaign->woo_order_statuses)),
            'max_per_day' => 0,
            'max_wa_per_day' => $max_wa_per_day_val,
            'max_email_per_day' => $max_email_per_day_val,
            'billing_countries' => maybe_serialize(isset($_POST['billing_countries']) ? array_map('sanitize_text_field', (array)$_POST['billing_countries']) : maybe_unserialize($old_campaign->billing_countries)),
            'wp_profile_languages' => maybe_serialize(isset($_POST['wp_profile_languages']) ? array_map('sanitize_text_field', (array)$_POST['wp_profile_languages']) : maybe_unserialize($old_campaign->wp_profile_languages)),
            'send_whatsapp' => $send_whatsapp, 'send_email' => $send_email,
            'email_subject' => sanitize_text_field($_POST['email_subject'] ?? $old_campaign->email_subject),
            'email_message' => wp_kses_post($_POST['email_message'] ?? $old_campaign->email_message),
        ];

        if ($db_data_update['status'] === 'saved' || $db_data_update['status'] === 'completed' || $db_data_update['paused']) {
            $db_data_update['next_run'] = null;
        }

        $wpdb->update($this->camp_table, $db_data_update, ['id' => $cid]);
        wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'updated',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function pause() {
        if (!current_user_can('manage_options')) wp_die('No');
        
        if (!$this->is_segmentation_enabled()) {
            wp_die(__('Segmentation feature not enabled. Cannot pause campaigns.', 'awp'));
        }

        $cid = intval($_POST['camp_id'] ?? 0);
        if (!check_admin_referer('campaign_pause_' . $cid, '_camp_pause_nonce')) wp_die('Bad nonce');
        global $wpdb;
        $pv = intval($_POST['pause_val'] ?? 0);
        $update_values = ['paused' => $pv];
        if ($pv == 0) {
            $campaign = $wpdb->get_row($wpdb->prepare("SELECT start_datetime, status, next_run FROM {$this->camp_table} WHERE id=%d", $cid));
            if ($campaign && $campaign->status === 'active') {
                if (empty($campaign->next_run) || strtotime($campaign->next_run) < current_time('timestamp')) {
                    $update_values['next_run'] = (strtotime($campaign->start_datetime) > current_time('timestamp')) ? $campaign->start_datetime : current_time('mysql');
                }
            } elseif ($campaign && $campaign->status === 'risky') {
                $update_values['next_run'] = null; 
            } elseif ($campaign && $campaign->status === 'saved') {
                $update_values['status'] = 'active';
                $update_values['next_run'] = (strtotime($campaign->start_datetime) < current_time('timestamp')) ? current_time('mysql') : $campaign->start_datetime;
            }
        } else { $update_values['next_run'] = null; }
        $wpdb->update($this->camp_table, $update_values, ['id' => $cid]);
        wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'updated',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function delete() {
        if (!current_user_can('manage_options')) wp_die('No');

        if (!$this->is_segmentation_enabled()) {
            wp_die(__('Segmentation feature not enabled. Cannot delete campaigns.', 'awp'));
        }

        $cid = intval($_POST['camp_id'] ?? 0);
        if (!$cid) { wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'error',
                ],
                admin_url( 'admin.php' )
            )
        );exit; }
        if (!check_admin_referer('campaign_delete_' . $cid, '_camp_delete_nonce')) wp_die('Bad nonce');
        global $wpdb;
        $wpdb->delete($this->queue_table, ['campaign_id' => $cid]);
        $wpdb->delete($this->email_log_table, ['campaign_id' => $cid]);
        $wpdb->delete($this->camp_table, ['id' => $cid]);
        wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'deleted',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function action() {
        if (!current_user_can('manage_options')) wp_die('No');
        
        if (!$this->is_segmentation_enabled()) {
            wp_die(__('Segmentation feature not enabled. Cannot perform campaign actions.', 'awp'));
        }

        $cid = intval($_POST['camp_id'] ?? 0);
        if (!$cid) wp_die('Missing campaign ID.');
        if (!check_admin_referer('camp_action_' . $cid, '_camp_action_nonce')) wp_die('Bad nonce');
        global $wpdb;
        $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->camp_table} WHERE id=%d", $cid));
        if (!$camp) wp_die('Campaign not found.');
        $act = sanitize_text_field($_POST['act'] ?? '');
        if ($act === 'start_resend') {
            $roles_data = maybe_unserialize($camp->role_ids);
            $users_data = maybe_unserialize($camp->user_ids);
            $external_numbers_raw = $camp->external_numbers;
            $external_emails_raw = $camp->external_emails;
            $billing_countries_data = maybe_unserialize($camp->billing_countries);
            $wp_profile_languages_data = maybe_unserialize($camp->wp_profile_languages);
            $woo_products_data = maybe_unserialize($camp->woo_ordered_products);
            $woo_statuses_data = maybe_unserialize($camp->woo_order_statuses);
            $woo_spent_data = floatval($camp->woo_spent_over);
            $woo_orders_data = intval($camp->woo_orders_over);
            $only_verified = intval($camp->only_verified_phone);

            $all_wp_user_ids = $this->merge_roles_users($roles_data, $users_data);
            $all_wp_user_ids = array_unique($all_wp_user_ids);

            $whatsapp_recipients_from_wp = [];
            $email_recipients_from_wp = [];

            if ($camp->send_whatsapp) {
                $whatsapp_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
                $whatsapp_recipients_from_wp = $this->filter_by_profile_languages($whatsapp_recipients_from_wp, $wp_profile_languages_data);
                $whatsapp_recipients_from_wp = $this->segment_filter($whatsapp_recipients_from_wp, $woo_spent_data, $woo_orders_data, $only_verified);
                if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                    $whatsapp_recipients_from_wp = $this->filter_by_purchase_and_status($whatsapp_recipients_from_wp, $woo_products_data, $woo_statuses_data);
                }
            }

            $external_numbers_for_queue = [];
            if ($external_numbers_raw) {
                $lines = preg_split('/[\r\n]+/', $external_numbers_raw);
                foreach ($lines as $ln) { $ln = trim($ln); if ($ln) $external_numbers_for_queue[] = preg_replace('/\D+/', '', $ln); }
            }
            $external_numbers_for_queue = array_unique($external_numbers_for_queue);

            if ($camp->send_email) {
                $email_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
                $email_recipients_from_wp = $this->filter_by_profile_languages($email_recipients_from_wp, $wp_profile_languages_data);
                $email_recipients_from_wp = $this->segment_filter($email_recipients_from_wp, $woo_spent_data, $woo_orders_data, 0);
                if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                    $email_recipients_from_wp = $this->filter_by_purchase_and_status($email_recipients_from_wp, $woo_products_data, $woo_statuses_data);
                }
            }

            $external_emails_for_log = [];
            if ($external_emails_raw) {
                $lines = preg_split('/[\r\n]+/', $external_emails_raw);
                foreach ($lines as $ln) { if (is_email(trim($ln))) $external_emails_for_log[] = trim($ln); }
            }
            $external_emails_for_log = array_unique($external_emails_for_log);
            
            $wpdb->query($wpdb->prepare("DELETE FROM {$this->queue_table} WHERE campaign_id=%d", $cid));
            $wpdb->query($wpdb->prepare("DELETE FROM {$this->email_log_table} WHERE campaign_id=%d", $cid));

            if ($camp->send_whatsapp) {
                foreach ($whatsapp_recipients_from_wp as $uid) {
                    $ph = $this->find_phone($uid); if (!$ph) continue;
                    $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => $uid, 'phone' => $ph, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]);
                }
                foreach ($external_numbers_for_queue as $en) {
                    $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => 0, 'phone' => $en, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]);
                }
            }

            if ($camp->send_email) {
                $email_subject_template = $camp->email_subject;
                $email_message_template = $camp->email_message;
                foreach ($email_recipients_from_wp as $uid) {
                    $user_email = $this->find_email($uid); if (!$user_email) continue;
                    $parsed_subject = $this->parse_placeholders($email_subject_template, $uid);
                    $parsed_message = $this->parse_placeholders($email_message_template, $uid);
                    $wpdb->insert($this->email_log_table, [
                        'campaign_id' => $cid, 'user_id' => $uid, 'email_address' => $user_email,
                        'subject' => $parsed_subject,  
                        'message_body' => $parsed_message,
                        'status' => 'pending'
                    ]);
                }
                foreach ($external_emails_for_log as $ee) {
                    $wpdb->insert($this->email_log_table, [
                        'campaign_id' => $cid, 'user_id' => 0, 'email_address' => $ee,
                        'subject' => $email_subject_template,  
                        'message_body' => $email_message_template,
                        'status' => 'pending'
                    ]);
                }
            }
            $total_recipients_whatsapp = count($whatsapp_recipients_from_wp) + count($external_numbers_for_queue);
            $total_recipients_email = count($email_recipients_from_wp) + count($external_emails_for_log);
            $new_total_count_for_camp = max($total_recipients_whatsapp, $total_recipients_email);

            $next_run_time = (strtotime($camp->start_datetime) < current_time('timestamp')) ? current_time('mysql') : $camp->start_datetime;
            $wpdb->update($this->camp_table, ['status' => 'active', 'processed_count' => 0, 'paused' => 0, 'next_run' => $next_run_time, 'total_count' => $new_total_count_for_camp], ['id' => $cid]);
        } elseif ($act === 'stop_repeat') {
            $wpdb->update($this->camp_table, ['repeat_type' => 'no', 'next_run' => null], ['id' => $cid]);
        }
        wp_redirect(
            add_query_arg(
                [
                    'page'        => 'wawp',
                    'awp_section' => 'campaigns',
                    'message'     => 'action_completed',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function ajax_run_risky() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        if (!check_ajax_referer('camp_run_risky_nonce', '_ajax_nonce', false)) wp_send_json_error(['message' => 'Nonce verification failed.']);
        
        if (!$this->is_segmentation_enabled()) {
            wp_send_json_error(['message' => __('Segmentation feature not enabled. Cannot run risky campaigns.', 'awp')]);
        }

        $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        if(!$cid) wp_send_json_error(['message' => 'Campaign ID missing.']);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT status, start_datetime FROM {$this->camp_table} WHERE id=%d", $cid));
        if ($row && $row->status === 'risky') {
            $next_run_time = (strtotime($row->start_datetime) < current_time('timestamp')) ? current_time('mysql') : $row->start_datetime;
            $wpdb->update($this->camp_table, ['status' => 'active', 'paused' => 0, 'next_run' => $next_run_time], ['id' => $cid]);
            wp_send_json_success(['message' => 'Campaign set to active.']);
        } else {
            wp_send_json_error(['message' => 'Campaign not found or not in risky state.']);
        }
    }
    
    public function cron_send() {
    
        if (!$this->is_segmentation_enabled()) {
            return;
        }

        global $wpdb;
        $now_timestamp = current_time('timestamp');
        $now_mysql = current_time('mysql');
       
        $campaigns_to_run = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->camp_table} WHERE status='active' AND paused=0 AND next_run IS NOT NULL AND next_run <= %s", $now_mysql ) );
        
        if (empty($campaigns_to_run)) return;

        foreach ($campaigns_to_run as $camp) {
           
            $max_wa_per_day = intval($camp->max_wa_per_day ?? 0);  
            $max_email_per_day = intval($camp->max_email_per_day ?? 0);

            $continue_whatsapp_sending = true;
            if ($camp->send_whatsapp && $max_wa_per_day > 0) {
                $today_start_gmt = gmdate('Y-m-d 00:00:00');
                $sent_wa_today = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$this->queue_table} WHERE campaign_id = %d AND status IN ('sent', 'error') AND sent_at >= %s", $camp->id, $today_start_gmt ));
                if ($sent_wa_today >= $max_wa_per_day) {
                    $continue_whatsapp_sending = false;
                }
            }

            $continue_email_sending = true;
            if ($camp->send_email && $max_email_per_day > 0) {
                $today_start_gmt = gmdate('Y-m-d 00:00:00');
                $sent_email_today = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$this->email_log_table} WHERE campaign_id = %d AND status IN ('sent', 'error') AND sent_at >= %s", $camp->id, $today_start_gmt ));
                if ($sent_email_today >= $max_email_per_day) {
                    $continue_email_sending = false;
                }
            }
            
       
            if (!$continue_whatsapp_sending && !$continue_email_sending && $camp->send_whatsapp && $camp->send_email) {
                $current_next_run_time = strtotime($camp->next_run);
                $time_of_day = date('H:i:s', $current_next_run_time > 0 ? $current_next_run_time : current_time('timestamp'));
                $new_next_run_datetime = date('Y-m-d', strtotime('+1 day', $now_timestamp)) . ' ' . $time_of_day;
                $wpdb->update($this->camp_table, ['next_run' => $new_next_run_datetime], ['id' => $camp->id]);
                continue; 
            }
         
            if (($camp->send_whatsapp && !$continue_whatsapp_sending && !$camp->send_email) || ($camp->send_email && !$continue_email_sending && !$camp->send_whatsapp)) {
                $current_next_run_time = strtotime($camp->next_run);
                $time_of_day = date('H:i:s', $current_next_run_time > 0 ? $current_next_run_time : current_time('timestamp'));
                $new_next_run_datetime = date('Y-m-d', strtotime('+1 day', $now_timestamp)) . ' ' . $time_of_day;
                $wpdb->update($this->camp_table, ['next_run' => $new_next_run_datetime], ['id' => $camp->id]);
                continue; 
            }


            $message_sent_this_cycle = false;
            $interval_to_use = 60; 

            if ($camp->send_whatsapp && $continue_whatsapp_sending) {
                $inst_array = maybe_unserialize($camp->instances);
                if (!empty($inst_array) && is_array($inst_array)) {
                    $wa_queue_item = $wpdb->get_row($wpdb->prepare( "SELECT * FROM {$this->queue_table} WHERE campaign_id = %d AND status = 'pending' ORDER BY id ASC LIMIT 1", $camp->id ));
                    if ($wa_queue_item) {
                        $this->attempt_send_with_failover($camp, $wa_queue_item, $inst_array, $now_mysql);
                        $message_sent_this_cycle = true;
                     
                        $wpdb->update($this->camp_table, ['processed_count' => $wpdb->get_var("SELECT processed_count FROM {$this->camp_table} WHERE id = {$camp->id}") + 1 ], ['id' => $camp->id]);
                        $interval_to_use = rand(intval($camp->min_whatsapp_interval ?? 60), intval($camp->max_whatsapp_interval ?? 75));
                    }
                }
            }

           
            if (!$message_sent_this_cycle && $camp->send_email && $continue_email_sending) {
                $email_log_item = $wpdb->get_row($wpdb->prepare( "SELECT * FROM {$this->email_log_table} WHERE campaign_id = %d AND status = 'pending' ORDER BY id ASC LIMIT 1", $camp->id ));
                if ($email_log_item) {
                    $email_log_row_for_send = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->email_log_table} WHERE id = %d", $email_log_item->id));  
                    if($email_log_row_for_send){
                        $this->attempt_send_email($camp, $email_log_row_for_send, $now_mysql);
                        $message_sent_this_cycle = true;
                        $wpdb->update($this->camp_table, ['processed_count' => $wpdb->get_var("SELECT processed_count FROM {$this->camp_table} WHERE id = {$camp->id}") + 1 ], ['id' => $camp->id]);
                        $interval_to_use = rand(intval($camp->min_email_interval ?? 30), intval($camp->max_email_interval ?? 60));
                    }
                }
            }
            
            $remaining_wa_in_queue = $camp->send_whatsapp ? $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$this->queue_table} WHERE campaign_id = %d AND status = 'pending'", $camp->id )) : 0;
            $remaining_email_in_log = $camp->send_email ? $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$this->email_log_table} WHERE campaign_id = %d AND status = 'pending'", $camp->id )) : 0;

            if ($remaining_wa_in_queue > 0 || $remaining_email_in_log > 0) {
                if ($message_sent_this_cycle) {
                    if ($interval_to_use < 1) $interval_to_use = 1;
                    $new_next_run_timestamp = $now_timestamp + $interval_to_use;
                    $wpdb->update($this->camp_table, ['next_run' => date('Y-m-d H:i:s', $new_next_run_timestamp)], ['id' => $camp->id]);
                } else {
                  
                    $all_enabled_channels_at_limit = true;
                    if ($camp->send_whatsapp && $continue_whatsapp_sending) $all_enabled_channels_at_limit = false; 
                    if ($camp->send_email && $continue_email_sending) $all_enabled_channels_at_limit = false;

                    if ($all_enabled_channels_at_limit) {
                        $current_next_run_time = strtotime($camp->next_run);
                        $time_of_day = date('H:i:s', $current_next_run_time > 0 ? $current_next_run_time : current_time('timestamp'));
                        $new_next_run_datetime = date('Y-m-d', strtotime('+1 day', $now_timestamp)) . ' ' . $time_of_day;
                        $wpdb->update($this->camp_table, ['next_run' => $new_next_run_datetime], ['id' => $camp->id]);
                    } else {
                        $wpdb->update($this->camp_table, ['next_run' => date('Y-m-d H:i:s', $now_timestamp + 60)], ['id' => $camp->id]);
                    }
                }
            } else {
                $this->finish($camp->id);
            }
        }
    }

    public function handle_email_open_tracking() {
        global $wpdb;
        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
        
        if ($log_id > 0) {
            $log_entry = $wpdb->get_row($wpdb->prepare("SELECT id, first_opened_at, open_count FROM {$this->email_log_table} WHERE id = %d", $log_id));
            if ($log_entry) {
                $update_data = ['open_count' => (intval($log_entry->open_count) ?? 0) + 1];
                if (is_null($log_entry->first_opened_at) || $log_entry->first_opened_at === '0000-00-00 00:00:00') {
                    $update_data['first_opened_at'] = current_time('mysql', 1);
                }
                $wpdb->update(
                    $this->email_log_table,
                    $update_data,
                    ['id' => $log_id]
                );
            }
        }
        
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
        wp_die();
    }

    private function attempt_send_with_failover($camp, $queue_row, $inst_array, $now) {
        global $wpdb;
        if (!class_exists('Wawp_Api_Url')) {
            $wpdb->update($this->queue_table, ['status' => 'error', 'sent_at' => $now], ['id' => $queue_row->id]);
            return;
        }
        
        $final_msg = $this->parse_placeholders($camp->message, $queue_row->user_id);
        $chosen_img = '';
        if (!empty($camp->media_url)) { $chosen_img = $camp->media_url; }
        else {  
            if ($camp->append_post && $camp->post_id && $camp->post_include_image) {
                $p = get_post($camp->post_id);
                if ($p && has_post_thumbnail($p->ID)) { $chosen_img = get_the_post_thumbnail_url($p->ID, 'full'); }
            }
            if (!$chosen_img && $camp->append_product && $camp->product_id && $camp->product_include_image) {
                $prodp = get_post($camp->product_id);
                if ($prodp && has_post_thumbnail($prodp->ID)) { $chosen_img = get_the_post_thumbnail_url($prodp->ID, 'full'); }
            }
        }
        $final_msg .= Wawp_Api_Url::make_tracking_ids();
        
        $current_processed_for_index = $wpdb->get_var($wpdb->prepare("SELECT processed_count FROM {$this->camp_table} WHERE id = %d", $camp->id));
        $index = count($inst_array) > 0 ? ($current_processed_for_index % count($inst_array)) : 0;

        $attempted = false;
        for ($i = 0; $i < count($inst_array); $i++) {
            $pos = ($index + $i) % count($inst_array);
            $parts= explode('|', $inst_array[$pos]);
            if (count($parts) < 2) continue;  
            $instance_db_id = $parts[0]; $instance_api_id = $parts[1];
            $instance_row = $wpdb->get_row($wpdb->prepare("SELECT instance_id,access_token,status FROM {$wpdb->prefix}awp_instance_data WHERE id=%d AND instance_id=%s", $instance_db_id, $instance_api_id));
            if (!$instance_row || strtolower($instance_row->status) !== 'online') { continue; }
            
            if ( ! empty( $chosen_img ) ) {
            $resp = Wawp_Api_Url::send_image(
                $instance_row->instance_id,
                $instance_row->access_token,
                $queue_row->phone,
                $chosen_img,
                $final_msg
            );
        } else {
            // plain‑text campaign message
            $resp = Wawp_Api_Url::send_message(
                $instance_row->instance_id,
                $instance_row->access_token,
                $queue_row->phone,
                $final_msg
            );
        }
            $this->log_awp($queue_row->user_id, $queue_row->phone, $final_msg, $chosen_img, "Campaign Name: ({$camp->name})", $resp, $instance_row->instance_id, $instance_row->access_token);
            if (isset($resp['status']) && $resp['status'] === 'success') {
                $wpdb->update($this->queue_table, ['status' => 'sent', 'sent_at' => $now], ['id' => $queue_row->id]);
            } else {
                $wpdb->update($this->queue_table, ['status' => 'error', 'sent_at' => $now], ['id' => $queue_row->id]);
            }
            $attempted = true;
            break;  
        }
        if (!$attempted) {  
            $wpdb->update($this->queue_table, ['status' => 'error', 'sent_at' => $now], ['id' => $queue_row->id]);
        }
    }

    private function attempt_send_email($camp, $email_log_row_full, $now_mysql) {
        global $wpdb;
        if (empty($email_log_row_full->email_address)) {
            $this->log_campaign_email($camp->id, $email_log_row_full->user_id, $email_log_row_full->id, '', 'Error: No email address', '', 'error', $now_mysql);
            return false;
        }

       
        $subject = $email_log_row_full->subject;  
        $body = $email_log_row_full->message_body;
        if ($camp->send_type === 'post' && $camp->post_id) {
            $post_text_for_email = $this->build_post_text($camp, true);
            if ($camp->append_post) $body .= $post_text_for_email; 
            else $body = $post_text_for_email; 
        } elseif ($camp->send_type === 'product' && $camp->product_id) {
            $product_text_for_email = $this->build_product_text($camp, true);
            if ($camp->append_product) $body .= $product_text_for_email;
            else $body = $product_text_for_email; 
        }
      


        $tracking_pixel_url = add_query_arg(['action' => 'wawp_track_email_open', 'log_id' => $email_log_row_full->id ], admin_url('admin-ajax.php'));
        $tracking_pixel_img = '<img src="' . esc_url($tracking_pixel_url) . '" alt="" width="1" height="1" border="0" style="height:1px !important;width:1px !important;border-width:0 !important;margin-top:0 !important;margin-bottom:0 !important;margin-right:0 !important;margin-left:0 !important;padding-top:0 !important;padding-bottom:0 !important;padding-right:0 !important;padding-left:0 !important;"/>';
        $body .= $tracking_pixel_img;
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($email_log_row_full->email_address, $subject, $body, $headers);

        $status_to_log = $sent ? 'sent' : 'error';
        $response_text = $sent ? 'Successfully sent.' : 'Failed to send email.';
        if (!$sent && isset($GLOBALS['phpmailer']) && $GLOBALS['phpmailer'] instanceof \PHPMailer\PHPMailer\PHPMailer) {
            $response_text .= ' Mailer Error: ' . $GLOBALS['phpmailer']->ErrorInfo;
        }
        $wpdb->update(
            $this->email_log_table,
            [
                'status' => $status_to_log,
                'sent_at' => $now_mysql,
                'response' => $response_text,
            ],
            ['id' => $email_log_row_full->id]
        );
        return $sent;
    }
    
    private function finish($cid) {
        global $wpdb;
        $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->camp_table} WHERE id=%d", $cid));
        if (!$camp) return;

        $pending_wa_count = $camp->send_whatsapp ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->queue_table} WHERE campaign_id = %d AND status = 'pending'", $cid)) : 0;
        $pending_email_count = $camp->send_email ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->email_log_table} WHERE campaign_id = %d AND status = 'pending'", $cid)) : 0;

        if (($pending_wa_count > 0 || $pending_email_count > 0) && ($camp->repeat_type === 'no' || $camp->repeat_type === '')) {
            return;
        }

        if ($camp->repeat_type === 'no' || $camp->repeat_type === '') {
            $wpdb->update($this->camp_table, ['status' => 'completed', 'next_run' => null], ['id' => $cid]);
            return;
        }

        $original_start_datetime = strtotime($camp->start_datetime);
        $time_component = date('H:i:s', $original_start_datetime > 0 ? $original_start_datetime : current_time('timestamp'));
        $next_run_base_timestamp = current_time('timestamp');  
        $next_run_timestamp = 0;
        switch ($camp->repeat_type) {
            case 'daily': $next_run_timestamp = strtotime('+1 day', $next_run_base_timestamp); break;
            case 'monthly': $next_run_timestamp = strtotime('+1 month', $next_run_base_timestamp); break;
            case 'annual': $next_run_timestamp = strtotime('+1 year', $next_run_base_timestamp); break;
            case 'custom': $days = max(1, intval($camp->repeat_days)); $next_run_timestamp = strtotime("+{$days} days", $next_run_base_timestamp); break;
        }
        if (!$next_run_timestamp) {
            $wpdb->update($this->camp_table, ['status' => 'completed', 'next_run' => null], ['id' => $cid]);
            return;
        }
        $nx = date('Y-m-d', $next_run_timestamp) . ' ' . $time_component;
        
        $roles_data = maybe_unserialize($camp->role_ids);
        $users_data = maybe_unserialize($camp->user_ids);
        $external_numbers_raw = $camp->external_numbers;
        $external_emails_raw = $camp->external_emails;
        $billing_countries_data = maybe_unserialize($camp->billing_countries);
        $wp_profile_languages_data = maybe_unserialize($camp->wp_profile_languages);
        $woo_products_data = maybe_unserialize($camp->woo_ordered_products);
        $woo_statuses_data = maybe_unserialize($camp->woo_order_statuses);
        $woo_spent_data = floatval($camp->woo_spent_over);
        $woo_orders_data = intval($camp->woo_orders_over);
        $only_verified = intval($camp->only_verified_phone);

        $all_wp_user_ids = $this->merge_roles_users($roles_data, $users_data);
        $all_wp_user_ids = array_unique($all_wp_user_ids);

        $whatsapp_recipients_from_wp = [];
        $email_recipients_from_wp = [];

        if ($camp->send_whatsapp) {
            $whatsapp_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
            $whatsapp_recipients_from_wp = $this->filter_by_profile_languages($whatsapp_recipients_from_wp, $wp_profile_languages_data);
            $whatsapp_recipients_from_wp = $this->segment_filter($whatsapp_recipients_from_wp, $woo_spent_data, $woo_orders_data, $only_verified);
            if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                $whatsapp_recipients_from_wp = $this->filter_by_purchase_and_status($whatsapp_recipients_from_wp, $woo_products_data, $woo_statuses_data);
            }
        }

        $external_numbers_for_queue = [];
        if ($external_numbers_raw) {
            $lines = preg_split('/[\r\n]+/', $external_numbers_raw);
            foreach ($lines as $ln) { $ln = trim($ln); if ($ln) $external_numbers_for_queue[] = preg_replace('/\D+/', '', $ln); }
        }
        $external_numbers_for_queue = array_unique($external_numbers_for_queue);

        if ($camp->send_email) {
            $email_recipients_from_wp = $this->filter_by_billing_countries($all_wp_user_ids, $billing_countries_data);
            $email_recipients_from_wp = $this->filter_by_profile_languages($email_recipients_from_wp, $wp_profile_languages_data);
            $email_recipients_from_wp = $this->segment_filter($email_recipients_from_wp, $woo_spent_data, $woo_orders_data, 0);
            if (class_exists('WooCommerce') && (!empty($woo_products_data) || !empty($woo_statuses_data))) {
                $email_recipients_from_wp = $this->filter_by_purchase_and_status($email_recipients_from_wp, $woo_products_data, $woo_statuses_data);
            }
        }

        $external_emails_for_log = [];
        if ($external_emails_raw) {
            $lines = preg_split('/[\r\n]+/', $external_emails_raw);
            foreach ($lines as $ln) { if (is_email(trim($ln))) $external_emails_for_log[] = trim($ln); }
        }
        $external_emails_for_log = array_unique($external_emails_for_log);

        $total_recipients_whatsapp = count($whatsapp_recipients_from_wp) + count($external_numbers_for_queue);
        $total_recipients_email = count($email_recipients_from_wp) + count($external_emails_for_log);
        $new_total_count_for_camp = max($total_recipients_whatsapp, $total_recipients_email);

        $wpdb->query($wpdb->prepare("DELETE FROM {$this->queue_table} WHERE campaign_id = %d", $cid));
        $wpdb->query($wpdb->prepare("DELETE FROM {$this->email_log_table} WHERE campaign_id = %d", $cid));

        if ($camp->send_whatsapp) {
            foreach ($whatsapp_recipients_from_wp as $uid) { $ph = $this->find_phone($uid); if (!$ph) continue; $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => $uid, 'phone' => $ph, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]); }
            foreach ($external_numbers_for_queue as $en) { $wpdb->insert($this->queue_table, ['campaign_id' => $cid, 'user_id' => 0, 'phone' => $en, 'unique_code' => $this->rand_code(12), 'security_code' => $this->rand_code(12)]); }
        }
        if ($camp->send_email) {
            $email_subject_template = $camp->email_subject;
            $email_message_template = $camp->email_message;
            foreach ($email_recipients_from_wp as $uid) {
                $user_email = $this->find_email($uid); if (!$user_email) continue;
                $parsed_subject = $this->parse_placeholders($email_subject_template, $uid);
                $parsed_message = $this->parse_placeholders($email_message_template, $uid);
                $wpdb->insert($this->email_log_table, [
                    'campaign_id' => $cid, 'user_id' => $uid, 'email_address' => $user_email,
                    'subject' => $parsed_subject,  
                    'message_body' => $parsed_message,
                    'status' => 'pending'
                ]);
            }
            foreach ($external_emails_for_log as $ee) {
                $wpdb->insert($this->email_log_table, [
                    'campaign_id' => $cid, 'user_id' => 0, 'email_address' => $ee,
                    'subject' => $email_subject_template,  
                    'message_body' => $email_message_template,
                    'status' => 'pending'
                ]);
            }
        }
        $wpdb->update($this->camp_table, ['status' => 'active','processed_count'=>0, 'total_count' => $new_total_count_for_camp, 'next_run'=>$nx], ['id' => $cid]);
    }
    
    private function build_appended_message($camp, $base) {  
        $c = $base;
        if ($camp->send_type === 'post' && $camp->post_id && $camp->append_post) { $c .= $this->build_post_text($camp, false); }
        if ($camp->send_type === 'product' && $camp->product_id && $camp->append_product) { $c .= $this->build_product_text($camp, false); }
        return $c;
    }
    
    private function build_post_text($camp, $for_email = false) {  
        $p = get_post($camp->post_id); if (!$p) return '';  
        $out = $for_email ? "<br><br><hr style='border-top-color:#eee;'>" : "\n\n";  
        if ($camp->post_include_title) {  
            $title = esc_html($p->post_title);
            $out .= $for_email ? "<h4>" . $title . "</h4>" : "*" . $title . "*\n";
        }
        if ($for_email && $camp->post_include_image && has_post_thumbnail($p->ID)) {
            $out .= '<p><img src="'.esc_url(get_the_post_thumbnail_url($p->ID, 'medium')).'" alt="'.esc_attr($p->post_title).'" style="max-width:100%;height:auto;margin-bottom:10px;"></p>';
        }
        if ($camp->post_include_excerpt) {
            $ex_raw = $p->post_excerpt ? $p->post_excerpt : wp_trim_words(strip_shortcodes($p->post_content), ($for_email ? 100 : 40) );
            $ex = $for_email ? wpautop(wp_kses_post($ex_raw)) : esc_html(strip_tags($ex_raw));
            if($ex) $out .= $ex . ($for_email ? "" : "\n");
        }
        if ($camp->post_include_link) {  
            $link = esc_url(get_permalink($p->ID));
            $out .= ($for_email ? '<p><a href="'.$link.'">Read More</a></p>' : $link . "\n");
        }
        return $out;
    }
    
    private function build_product_text($camp, $for_email = false) {  
        if (!class_exists('WooCommerce')) return '';  
        $p = get_post($camp->product_id); if (!$p) return '';  
        $out = $for_email ? "<br><br><hr style='border-top-color:#eee;'>" : "\n\n";
        $wc_product = wc_get_product($p->ID); if (!$wc_product) return '';
        if ($camp->product_include_title) {  
            $title = esc_html($p->post_title);
            $out .= $for_email ? "<h4>" . $title . "</h4>" : "*" . $title . "*\n";
        }
        if ($for_email && $camp->product_include_image && has_post_thumbnail($p->ID)) {
            $out .= '<p><img src="'.esc_url(get_the_post_thumbnail_url($p->ID, 'medium')).'" alt="'.esc_attr($p->post_title).'" style="max-width:300px;height:auto;margin-bottom:10px;"></p>';
        }
        if ($camp->product_include_excerpt) {
            $ex_raw = $p->post_excerpt ? $p->post_excerpt : wp_trim_words(strip_shortcodes(strip_tags($p->post_content)), ($for_email ? 50 : 25));
            $ex = $for_email ? wpautop(wp_kses_post($ex_raw)) : esc_html(strip_tags($ex_raw));
            if($ex) $out .= $ex . ($for_email ? "" : "\n");
        }
        if ($camp->product_include_price) {
            $price_html_raw = $wc_product->get_price_html();
            $price_display = $for_email ? $price_html_raw : strip_tags($price_html_raw);
            if (empty($price_display) && $wc_product->get_price()) {
                $price_display = wc_price($wc_product->get_price());
                if (!$for_email) $price_display = strip_tags($price_display);
            }
            if ($price_display) { $out .= ($for_email ? "<p><strong>Price:</strong> " : "Price: ") . $price_display . ($for_email ? "</p>" : "\n"); }
        }
        if ($camp->product_include_link) {  
            $link = esc_url(get_permalink($p->ID));
            $out .= ($for_email ? '<p><a href="'.$link.'">View Product</a></p>' : $link . "\n");
        }
        return $out;
    }
    
    private function parse_placeholders($message, $user_id) {
        if (empty($message) || !is_string($message)) {
            return '';
        }
        if (empty($user_id) || !is_numeric($user_id) || $user_id <= 0) {
            return $message;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return $message;
        }

        if (preg_match_all('/{{(.*?)}}/', $message, $matches)) {
            $placeholders = array_unique($matches[1]); 

            foreach ($placeholders as $key) {
                $replacement = '';
                $full_placeholder = '{{' . $key . '}}';

                switch ($key) {
                    case 'wp-first-name':
                        $replacement = $user->first_name;
                        break;
                    case 'wp-last-name':
                        $replacement = $user->last_name;
                        break;
                    case 'wp-username':
                        $replacement = $user->user_login;
                        break;
                    case 'wp-nickname':
                        $replacement = get_user_meta($user_id, 'nickname', true);
                        break;
                    case 'wp-display-name':
                        $replacement = $user->display_name;
                        break;
                    case 'wp-email':
                        $replacement = $user->user_email;
                        break;
                    case 'wp-user-website':
                        $replacement = $user->user_url;
                        break;
                    case 'wp-user-bio':
                        $replacement = $user->description;
                        break;
                    default:
                        $replacement = get_user_meta($user_id, $key, true);
                        if (is_array($replacement) || is_object($replacement)) {
                            $replacement = ''; 
                        }
                        break;
                }
                
                $message = str_replace($full_placeholder, (string)$replacement, $message);
            }
        }

        return $message;
    }
    
    private function segment_filter($user_ids, $min_spent, $min_orders, $only_verified) {
        if (empty($user_ids)) { return []; } $out = [];  
        foreach ($user_ids as $uid) {
            $uid = intval($uid);  
            if ($only_verified && !$this->is_phone_verified($uid)) { continue; }
            if (class_exists('WooCommerce')) {
                if ($min_spent > 0 && !$this->user_spent_over($uid, $min_spent)) { continue; }
                if ($min_orders > 0 && !$this->user_orders_over($uid, $min_orders)) { continue; }
            }
            $out[] = $uid;
        }
        return $out;
    }

    private function filter_by_billing_countries($user_ids, $target_countries) {
        if (empty($target_countries) || in_array("", $target_countries, true) || in_array("All Countries", $target_countries, true) || empty($user_ids)) {
            return $user_ids;
        }

        $filtered_ids = [];
        foreach ($user_ids as $user_id) {
            $user_country = get_user_meta($user_id, 'billing_country', true);  
            if (empty($user_country) && class_exists('WC_Customer')) {  
                $customer = new WC_Customer($user_id);
                $user_country = $customer->get_billing_country();
            }

            if (!empty($user_country) && in_array($user_country, $target_countries)) {
                $filtered_ids[] = $user_id;
            }
        }
        return $filtered_ids;
    }

    private function filter_by_profile_languages($user_ids, $target_languages) {
        if (empty($target_languages) || in_array("", $target_languages, true) || in_array("All Languages", $target_languages, true) || empty($user_ids)) {
            return $user_ids;
        }

        $filtered_ids = [];
        foreach ($user_ids as $user_id) {
            $user_language = get_user_meta($user_id, 'locale', true);  
            if (empty($user_language)) {  
                $user_language = get_locale();
            }
            if (!empty($user_language) && in_array($user_language, $target_languages)) {
                $filtered_ids[] = $user_id;
            }
        }
        return $filtered_ids;
    }

    private function is_phone_verified($uid) {
        global $wpdb; $tbl = $wpdb->prefix . 'awp_user_info';
        if ($wpdb->get_var("SHOW TABLES LIKE '$tbl'") !== $tbl) { return true; }  
        $row = $wpdb->get_row($wpdb->prepare("SELECT whatsapp_verified FROM $tbl WHERE user_id=%d", $uid));
        if (!$row) { return false; }  
        return ($row->whatsapp_verified === 'Verified');
    }
    
    private function user_spent_over($uid, $amt) {
        if (!class_exists('WooCommerce')) { return false; } $customer = new WC_Customer($uid);
        if(!$customer->get_id()) return false; return (floatval($customer->get_total_spent()) >= $amt);
    }
    
    private function user_orders_over($uid, $count) {
        if (!class_exists('WooCommerce')) { return false; } $customer = new WC_Customer($uid);
        if(!$customer->get_id()) return false; return (intval($customer->get_order_count()) >= $count);
    }
    
    private function merge_roles_users($roles, $users) {
        $all_user_ids = [];
        if(!empty($roles)){
            foreach ($roles as $role_key) {
                $role_users = get_users(['role' => sanitize_key($role_key), 'fields' => 'ID']);
                $all_user_ids = array_merge($all_user_ids, $role_users);
            }
        }
        if(!empty($users)){ $all_user_ids = array_merge($all_user_ids, array_map('intval', $users)); }
        return array_unique(array_map('intval', $all_user_ids));
    }
    
    private function find_phone($uid) {
        $phone_number = '';
        if (class_exists('WooCommerce')) {
            $phone_number = get_user_meta($uid, 'billing_phone', true);
            if ($phone_number) { return preg_replace('/\D+/', '', $phone_number); }
        }
        global $wpdb; $awp_user_info_table = $wpdb->prefix . 'awp_user_info';
        if ($wpdb->get_var("SHOW TABLES LIKE '$awp_user_info_table'") === $awp_user_info_table) {
            $phone_number = $wpdb->get_var($wpdb->prepare("SELECT phone FROM $awp_user_info_table WHERE user_id = %d", $uid));
            if ($phone_number) { return preg_replace('/\D+/', '', $phone_number); }
        }
        $alt_phone = get_user_meta($uid, '_awp_user_phone', true);  
        if ($alt_phone) { return preg_replace('/\D+/', '', $alt_phone); }
        return '';
    }

    private function find_email($uid) {
        $user_data = get_userdata($uid);
        if ($user_data && !empty($user_data->user_email)) {
            return $user_data->user_email;
        }
        return '';
    }

    private function rand_code($len = 12) { return wp_generate_password($len, false, false); }
    
    private function log_awp($user_id, $phone, $msg, $img, $type, $resp, $instance_id = null, $access_token = null) {
    if (!class_exists('AWP_Log_Manager')) {
        return;
    }
    $lm = new AWP_Log_Manager();

    // Unwrap the response if it's nested inside 'full_response'
    $response_to_log = $resp;
    if (isset($resp['full_response'])) {
        $response_to_log = $resp['full_response'];
    }

    $log_data = [
        'user_id'          => $user_id,
        'order_id'         => 0,
        'customer_name'    => '',
        'sent_at'          => current_time('mysql'),
        'whatsapp_number'  => $phone,
        'message'          => $msg,
        'image_attachment' => $img,
        'message_type'     => $type,
        'wawp_status'      => $response_to_log,
        'resend_id'        => null,
        'instance_id'      => $instance_id,
        'access_token'     => $access_token,
    ];

    if (method_exists($lm, 'log_notification')) {
        $lm->log_notification($log_data);
    } elseif (method_exists($lm, 'add_log')) {
        // Fallback for older versions, though it won't log the new fields
        $simple_status = 'See Details';
        if (is_array($resp) && isset($resp['status'])) {
            $simple_status = ucfirst($resp['status']);
            if (isset($resp['message'])) $simple_status .= ': ' . $resp['message'];
        } else if (is_string($resp)) $simple_status = $resp;
        if (strlen($simple_status) > 255) $simple_status = substr($simple_status, 0, 252) . '...';
        $lm->add_log('Campaign Message Send', $simple_status, $log_data);
    }
}

    private function log_campaign_email($campaign_id, $user_id, $email_log_id, $email_address, $subject, $body, $status, $sent_at_mysql, $response_text = '') {
        global $wpdb;
        $wpdb->update(
            $this->email_log_table,
            [
                'status' => $status,
                'sent_at' => $sent_at_mysql,
                'response' => $response_text,
            ],
            ['id' => $email_log_id]
        );
    }
    
    private function filter_by_purchase_and_status($user_ids, $product_ids, $statuses) {
        if (!class_exists('WooCommerce') || (empty($product_ids) && empty($statuses))) { return $user_ids; }
        if (empty($user_ids)) return [];
        $product_ids = array_map('intval', (array)$product_ids);
        $statuses = array_map('wc_clean', (array)$statuses);  
        $filtered_user_ids = [];
        foreach ($user_ids as $user_id) {
            $query_args = [ 'customer_id' => intval($user_id), 'limit' => -1, 'paginate' => false, ];
            if (!empty($statuses)) { $query_args['status'] = $statuses; }
            $orders = wc_get_orders($query_args); if (empty($orders)) continue;
            $user_matched = false;
            foreach ($orders as $order) {
                if (empty($product_ids)) { $user_matched = true; break; }  
                foreach ($order->get_items() as $item) {
                    $p_id = $item->get_product_id(); $v_id = $item->get_variation_id();
                    if (in_array($p_id, $product_ids) || ($v_id && in_array($v_id, $product_ids))) {
                        $user_matched = true; break 2;  
                    }
                }
            }
            if ($user_matched) { $filtered_user_ids[] = $user_id; }
        }
        return $filtered_user_ids;
    }
}  

new WP_Wawp_Campaigns_Advanced();
