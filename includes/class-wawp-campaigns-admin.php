<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Wawp_Campaigns_Admin
{
    public function render_html($type, $data)
    {
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
        if (!AWP_Admin_Notices::require_online_instance(null)) {
            return;
        }

        if ($type === 'campaigns_list') {
            $rows = isset($data['rows']) ? $data['rows'] : [];
            $search_term = isset($_GET['cex_search']) ? sanitize_text_field($_GET['cex_search']) : '';

            if (isset($_GET['message'])) {
                $message_text = '';
                switch (sanitize_text_field($_GET['message'])) {
                    case 'created':
                        $message_text = __('Campaign created successfully.', 'awp');
                        break;
                    case 'updated':
                        $message_text = __('Campaign updated successfully.', 'awp');
                        break;
                    case 'deleted':
                        $message_text = __('Campaign deleted successfully.', 'awp');
                        break;
                    case 'saved_for_later':
                        $message_text = __('Campaign saved for later.', 'awp');
                        break;
                }
                if ($message_text) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message_text) . '</p></div>';
                }
            }

            echo '<div class="page-header_row">';
            echo '<div class="page-header">';
            echo '<h1 class="page-title"><i class="bi bi-megaphone-fill"></i> ' . esc_html__('Wawp Campaigns', 'awp') . '</h1>';
            echo '<p>' . esc_html__('Manage your existing campaigns, pause/resume, check progress, or edit them.', 'awp') . '</p>';
            echo '</div>';
            echo '</div>';
  

            $filtered_rows = [];
            if ($search_term) {
                foreach ($rows as $r) {
                    if (stripos($r->name, $search_term) !== false) {
                        $filtered_rows[] = $r;
                    }
                }
            } else {
                $filtered_rows = $rows;
            }

            if (empty($filtered_rows)) {
                echo '<div><p>' . wp_kses_post(sprintf(__('No campaigns found. <a href="%s">Add a new campaign?</a>', 'awp'), esc_url(admin_url('admin.php?page=wawp&awp_section=campaigns_new')))) . '</p></div>';
                return;
            }

            echo '<table class="widefat striped cex_table">
                        <thead>
                            <tr>
                                <th>' . esc_html__('ID', 'awp') . '</th>
                                <th>' . esc_html__('Name', 'awp') . '</th>
                                <th>' . esc_html__('Progress', 'awp') . '</th>
                                <th>' . esc_html__('Status', 'awp') . '</th>
                                <th>' . esc_html__('Channels', 'awp') . '</th>
                                <th>' . esc_html__('Next Run', 'awp') . '</th>
                                <th>' . esc_html__('Actions', 'awp') . '</th>
                            </tr>
                        </thead>
                        <tbody>';

            global $wpdb;
            $queue_table     = $wpdb->prefix . 'wawp_campaigns_queue';
            $email_log_table = $wpdb->prefix . 'wawp_email_log';

            foreach ($filtered_rows as $r) {
                $progress_text_parts = [];

                if (property_exists($r, 'send_whatsapp') && $r->send_whatsapp) {
                    $total_wa = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE campaign_id = %d", $r->id));
                    $sent_count_whatsapp = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE campaign_id = %d AND status = 'sent'", $r->id));
                    $error_count_whatsapp = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $queue_table WHERE campaign_id = %d AND status = 'error'", $r->id));
                    $pending_wa = max(0, $total_wa - $sent_count_whatsapp - $error_count_whatsapp);
                    $sent_percent_whatsapp  = $total_wa > 0 ? ($sent_count_whatsapp / $total_wa) * 100 : 0;
                    $error_percent_whatsapp = $total_wa > 0 ? ($error_count_whatsapp / $total_wa) * 100 : 0;
                    
                    $progress_bar_title_wa = sprintf(esc_attr__('WhatsApp – Sent: %1$d, Errors: %2$d, Pending: %3$d', 'awp'), $sent_count_whatsapp, $error_count_whatsapp, $pending_wa);
                    $progress_bar_html_wa = '<div class="cex_progress_bar_container" title="' . $progress_bar_title_wa . '">';
                    if ($sent_count_whatsapp > 0) {
                        $progress_bar_html_wa .= '<div class="cex_progress_bar_segment cex_progress_sent" style="width:' . esc_attr(round($sent_percent_whatsapp, 2)) . '%;"></div>';
                    }
                    if ($error_count_whatsapp > 0) {
                        $progress_bar_html_wa .= '<div class="cex_progress_bar_segment cex_progress_error" style="width:' . esc_attr(round($error_percent_whatsapp, 2)) . '%;"></div>';
                    }
                    $progress_bar_html_wa .= '</div>';
                    
                    $processed_wa = $sent_count_whatsapp + $error_count_whatsapp;
                    
                    $wa_details_string = sprintf(
                        esc_html__( '(S: %1$s, E: %2$s)', 'awp' ),
                        esc_html( $sent_count_whatsapp ),
                        esc_html( $error_count_whatsapp )
                    );
                    $progress_text_parts[] = 'WA: '
                        . $progress_bar_html_wa
                        . esc_html($processed_wa) . '/' . esc_html($total_wa)
                        . ' <span class="cex_progress_details_inline">' . $wa_details_string . '</span>';
                }

                if (property_exists($r, 'send_email') && $r->send_email) {
                    $total_email = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $email_log_table WHERE campaign_id = %d", $r->id));
                    $sent_count_email = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $email_log_table WHERE campaign_id = %d AND status = 'sent'", $r->id));
                    $error_count_email = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $email_log_table WHERE campaign_id = %d AND status = 'error'", $r->id));
                    $pending_email = max(0, $total_email - $sent_count_email - $error_count_email);
                    $sent_percent_email  = $total_email > 0 ? ($sent_count_email / $total_email) * 100 : 0;
                    $error_percent_email = $total_email > 0 ? ($error_count_email / $total_email) * 100 : 0;

                    $progress_bar_title_email = sprintf(esc_attr__('Email – Sent: %1$d, Errors: %2$d, Pending: %3$d', 'awp'), $sent_count_email, $error_count_email, $pending_email);
                    $progress_bar_html_email = '<div class="cex_progress_bar_container" title="' . $progress_bar_title_email . '">';
                    if ($sent_count_email > 0) {
                        $progress_bar_html_email .= '<div class="cex_progress_bar_segment cex_progress_sent" style="width:' . esc_attr(round($sent_percent_email, 2)) . '%;"></div>';
                    }
                    if ($error_count_email > 0) {
                        $progress_bar_html_email .= '<div class="cex_progress_bar_segment cex_progress_error" style="width:' . esc_attr(round($error_percent_email, 2)) . '%;"></div>';
                    }
                    $progress_bar_html_email .= '</div>';
                    
                    $processed_email = $sent_count_email + $error_count_email;

                    $email_details_string = sprintf(
                        esc_html__( '(S: %1$s, E: %2$s)', 'awp' ),
                        esc_html( $sent_count_email ),
                        esc_html( $error_count_email )
                    );
                    $progress_text_parts[] = 'Email: '
                        . $progress_bar_html_email
                        . esc_html($processed_email) . '/' . esc_html($total_email)
                        . ' <span class="cex_progress_details_inline">' . $email_details_string . '</span>';
                }

                $progress_display = !empty($progress_text_parts) ? implode('<br style="margin-bottom:3px;">', $progress_text_parts) : esc_html__('N/A', 'awp');

                echo '<tr>
                        <td>' . esc_html($r->id) . '</td>
                        <td>' . esc_html($r->name) . '</td>
                        <td>' . $progress_display . '</td>';

                $status_html  = '';
                $status_class = '';
                if (!empty($r->paused)) {
                    $status_html  = '<i class="bi bi-pause-circle-fill"></i> ' . esc_html__('Paused', 'awp');
                    $status_class = 'cex_status_paused';
                } else {
                    switch ($r->status) {
                        case 'completed':
                            $status_html  = '<i class="bi bi-check-circle-fill"></i> ' . esc_html__('Completed', 'awp');
                            $status_class = 'cex_status_completed';
                            if (property_exists($r, 'repeat_type') && $r->repeat_type !== 'no' && $r->repeat_type !== '') {
                                $status_html  .= ' ' . esc_html__('(Will Repeat)', 'awp');
                                $status_class = 'cex_status_completed_repeats';
                            }
                            break;
                        case 'risky':
                            $status_html  = '<i class="bi bi-exclamation-triangle-fill"></i> ' . esc_html__('Risky', 'awp');
                            $status_class = 'cex_status_risky';
                            break;
                        case 'saved':
                            $status_html  = '<i class="bi bi-save-fill"></i> ' . esc_html__('Saved', 'awp');
                            $status_class = 'cex_status_saved';
                            break;
                        case 'active':
                            $status_html  = '<i class="bi bi-play-circle-fill"></i> ' . esc_html__('Active', 'awp');
                            $status_class = 'cex_status_active';
                            break;
                        default:
                            $status_html  = esc_html(ucfirst($r->status));
                            $status_class = 'cex_status_unknown';
                    }
                }
                echo '<td><span class="cex_status_badge ' . esc_attr($status_class) . '">' . $status_html . '</span></td>';

                $channels = [];
                if (property_exists($r, 'send_whatsapp') && $r->send_whatsapp) {
                    $channels[] = '<i class="bi bi-whatsapp" title="' . esc_attr__('WhatsApp Enabled', 'awp') . '"></i>';
                }
                if (property_exists($r, 'send_email') && $r->send_email) {
                    $channels[] = '<i class="bi bi-envelope-fill" title="' . esc_attr__('Email Enabled', 'awp') . '"></i>';
                }
                echo '<td>' . (!empty($channels) ? implode(' ', $channels) : '<small>' . esc_html__('N/A', 'awp') . '</small>') . '</td>';

                $next_run_display = $r->next_run ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($r->next_run)) : '-';
                echo '<td>' . esc_html($next_run_display) . '</td>';

                echo '<td><div class="cex_actions_group">';
                if ($r->status === 'completed') {
                    if (property_exists($r, 'repeat_type') && ($r->repeat_type === 'no' || $r->repeat_type === '')) {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                            . wp_nonce_field('camp_action_' . $r->id, '_camp_action_nonce', true, false)
                            . '<input type="hidden" name="action" value="campaign_action">'
                            . '<input type="hidden" name="act" value="start_resend">'
                            . '<input type="hidden" name="camp_id" value="' . esc_attr($r->id) . '">'
                            . '<button type="submit" class="awp-btn"><i class="bi bi-arrow-clockwise"></i> ' . esc_html__('Re-run', 'awp') . '</button>'
                            . '</form>';
                    } else {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                            . wp_nonce_field('camp_action_' . $r->id, '_camp_action_nonce', true, false)
                            . '<input type="hidden" name="action" value="campaign_action">'
                            . '<input type="hidden" name="act" value="stop_repeat">'
                            . '<input type="hidden" name="camp_id" value="' . esc_attr($r->id) . '">'
                            . '<button type="submit" class="awp-btn cex_button"><i class="bi bi-stop-fill"></i> ' . esc_html__('Stop Repeat', 'awp') . '</button>'
                            . '</form>';
                    }
                } elseif ($r->status === 'saved') {
                    echo '<a class="awp-btn secondary" href="' . esc_url(admin_url('admin.php?page=wawp&awp_section=campaigns&edit_id=' . $r->id)) . '"><i class="bi bi-pencil-square"></i> ' . esc_html__('Edit & Run', 'awp') . '</a>';
                } elseif ($r->status === 'risky') {
                    echo '<button class="awp-btn cex_button cex_risky_btn" data-id="' . esc_attr($r->id) . '"><i class="bi bi-exclamation-triangle"></i> ' . esc_html__('Approve Run', 'awp') . '</button>';
                    echo '<a class="awp-btn cex_button" href="' . esc_url(admin_url('admin.php?page=wawp&awp_section=campaigns&edit_id=' . $r->id)) . '"><i class="bi bi-pencil-square"></i> ' . esc_html__('Edit', 'awp') . '</a>';
                } else {
                    if (!empty($r->paused)) {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                            . wp_nonce_field('campaign_pause_' . $r->id, '_camp_pause_nonce', true, false)
                            . '<input type="hidden" name="action" value="campaign_pause">'
                            . '<input type="hidden" name="camp_id" value="' . esc_attr($r->id) . '">'
                            . '<input type="hidden" name="pause_val" value="0">'
                            . '<button type="submit" class="awp-btn primary"><i class="bi bi-play-fill"></i> ' . esc_html__('Resume', 'awp') . '</button>'
                            . '</form>';
                    } else {
                        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                            . wp_nonce_field('campaign_pause_' . $r->id, '_camp_pause_nonce', true, false)
                            . '<input type="hidden" name="action" value="campaign_pause">'
                            . '<input type="hidden" name="camp_id" value="' . esc_attr($r->id) . '">'
                            . '<input type="hidden" name="pause_val" value="1">'
                            . '<button type="submit" class="awp-btn"><i class="bi bi-pause-fill"></i> ' . esc_html__('Pause', 'awp') . '</button>'
                            . '</form>';
                    }
                    echo '<a class="awp-btn secondary" href="' . esc_url(admin_url('admin.php?page=wawp&awp_section=campaigns&edit_id=' . $r->id)) . '"><i class="bi bi-pencil-square"></i> ' . esc_html__('Edit', 'awp') . '</a>';
                }

                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                    . wp_nonce_field('campaign_delete_' . $r->id, '_camp_delete_nonce', true, false)
                    . '<input type="hidden" name="action" value="campaign_delete">'
                    . '<input type="hidden" name="camp_id" value="' . esc_attr($r->id) . '">'
                    . '<button type="submit" class="awp-btn delete-plain" onclick="return confirm(\'' . esc_attr__('Are you sure you want to delete this campaign and its queue/logs?', 'awp') . '\');"><i class="bi bi-trash-fill"></i> ' . esc_html__('Delete', 'awp') . '</button>'
                    . '</form>';

                echo '</div></td></tr>';
            }

            echo '</tbody></table></div>';

            echo '<div id="cex_risky_modal" class="cex_modal" style="display:none;">
                    <div class="cex_modal_content">
                        <span class="cex_modal_close">&times;</span>
                        <p style="color:red;font-weight:bold;font-size:1.1em;"><i class="bi bi-exclamation-triangle-fill"></i> ' . esc_html__('High Volume Warning', 'awp') . '</p>
                        <p>' . esc_html__('This campaign targets a large number of recipients or has a high daily sending limit. Sending too many messages too quickly can lead to your WhatsApp number being blocked.', 'awp') . '</p>
                        <p>' . wp_kses_post(__('It is strongly recommended to send <strong>500 messages per day or less</strong> per number to ensure its safety.', 'awp')) . '</p>
                        <p>' . esc_html__('Do you understand the risk and wish to proceed?', 'awp') . '</p>
                        <button class="button button-primary" id="cex_risky_agree">' . esc_html__('Yes, Run Campaign', 'awp') . '</button>
                        <button class="button" id="cex_risky_cancel_modal" style="margin-left:10px;">' . esc_html__('Cancel', 'awp') . '</button>
                        <input type="hidden" id="cex_risky_cid" value="">
                    </div>
                </div>';
        }

        if ($type === 'new_campaign_multi') {
            $is_woocommerce_active = class_exists('WooCommerce');
            echo '<div class="wrap awp-card">
                <h1 class="cex_h1" id="add_new_campaign_heading"><i class="bi bi-plus-square"></i> ' . esc_html__('Add New Campaign', 'awp') . '</h1>
                <div id="cex_form_error_summary" class="notice notice-error is-dismissible" style="display:none;"><p></p></div>
                <div class="cex_recipients_info">
                    <div id="whatsapp_recipients_info" style="display:none;">
                        <strong>' . esc_html__('WhatsApp Estimated Recipients:', 'awp') . '</strong>
                        <span id="whatsapp_recipients_count">0</span><br>
                    </div>
                    <div id="email_recipients_info" style="display:none;">
                        <strong>' . esc_html__('Email Estimated Recipients:', 'awp') . '</strong>
                        <span id="email_recipients_count">0</span>
                    </div>
                    <div id="estimate_finish" class="cex_estimate_details"></div>
                </div>

                <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" id="cex_multi_form">
                    <input type="hidden" name="action" value="campaign_create">
                    ' . wp_nonce_field('campaign_create_nonce', '_camp_nonce') . '
                    ' . wp_referer_field(false) . '

                    <div id="cex_step_1" class="cex_step">
                        <h2><i class="bi bi-people-fill"></i> ' . esc_html__('Step 1: Audience, Channels & Name', 'awp') . '</h2>
                        <div class="cex_step_error_summary notice notice-error" style="display:none;"><p></p></div>';
            $this->fields_step_1(null);
            echo '<p class="cex_step_nav">
                <button type="submit" class="awp-btn cex_button" name="save_for_later" value="1">
                    <i class="bi bi-save"></i> ' . esc_html__('Save for Later', 'awp') . '
                </button>
                <button type="button" class="awp-btn primary cex_button cex_next_btn" data-step="1" data-next="2" style="float:right;">
                    <i class="bi bi-arrow-right-circle"></i> ' . esc_html__('Next: Additional Filters', 'awp') . '
                </button>
            </p>
        </div>

        <div id="cex_step_2" class="cex_step" style="display:none;">
            <h2><i class="bi bi-funnel-fill"></i> ' . esc_html__('Step 2: Additional Customer Filters', 'awp') . '</h2>
            <div class="cex_step_error_summary notice notice-error" style="display:none;"><p></p></div>';
            $this->fields_step_2_additional_filters(null);
            echo '<p class="cex_step_nav">
                <button type="submit" class="awp-btn cex_button" name="save_for_later" value="1">
                    <i class="bi bi-save"></i> ' . esc_html__('Save for Later', 'awp') . '
                </button>
                <button type="button" class="awp-btn secondary cex_button cex_prev_btn" data-prev="1">
                    <i class="bi bi-arrow-left-circle"></i> ' . esc_html__('Back', 'awp') . '
                </button>
                <button type="button" class="awp-btn primary cex_button cex_next_btn" data-step="2" data-next="3" style="float:right;">
                    <i class="bi bi-arrow-right-circle"></i> ' . esc_html__('Next: WooCommerce Filters', 'awp') . '
                </button>
            </p>
        </div>

        <div id="cex_step_3" class="cex_step" style="display:none;">
            <h2><i class="bi bi-cart-check-fill"></i> ' . esc_html__('Step 3: WooCommerce Customer Filters', 'awp') . '</h2>
            <div class="cex_step_error_summary notice notice-error" style="display:none;"><p></p></div>';
            if ($is_woocommerce_active) {
                $this->fields_step_3_woo_filters(null);
            } else {
                echo '<p class="notice notice-warning">' . esc_html__('WooCommerce is not active. This step is disabled.', 'awp') . '</p>';
            }
            echo '<p class="cex_step_nav">
                <button type="submit" class="awp-btn cex_button" name="save_for_later" value="1">
                    <i class="bi bi-save"></i> ' . esc_html__('Save for Later', 'awp') . '
                </button>
                <button type="button" class="awp-btn secondary cex_button cex_prev_btn" data-prev="2">
                    <i class="bi bi-arrow-left-circle"></i> ' . esc_html__('Back', 'awp') . '
                </button>
                <button type="button" class="awp-btn primary cex_button cex_next_btn" data-step="3" data-next="4" style="float:right;">
                    <i class="bi bi-arrow-right-circle"></i> ' . esc_html__('Next: Message Content', 'awp') . '
                </button>
            </p>
        </div>

        <div id="cex_step_4" class="cex_step" style="display:none;">
            <h2><i class="bi bi-envelope-open-fill"></i> ' . esc_html__('Step 4: Message Content', 'awp') . '</h2>
            <div class="cex_step_error_summary notice notice-error" style="display:none;"><p></p></div>';
            $this->fields_step_4_message_content(null);
            echo '<p class="cex_step_nav">
                <button type="submit" class="awp-btn cex_button" name="save_for_later" value="1">
                    <i class="bi bi-save"></i> ' . esc_html__('Save for Later', 'awp') . '
                </button>
                <button type="button" class="awp-btn secondary cex_button cex_prev_btn" data-prev="3">
                    <i class="bi bi-arrow-left-circle"></i> ' . esc_html__('Back', 'awp') . '
                </button>
                <button type="button" class="awp-btn primary cex_button cex_next_btn" data-step="4" data-next="5" style="float:right;">
                    <i class="bi bi-arrow-right-circle"></i> ' . esc_html__('Next: Scheduling', 'awp') . '
                </button>
            </p>
        </div>

        <div id="cex_step_5" class="cex_step" style="display:none;">
            <h2><i class="bi bi-clock-history"></i> ' . esc_html__('Step 5: Scheduling & Timing', 'awp') . '</h2>
            <div class="cex_step_error_summary notice notice-error" style="display:none;"><p></p></div>';
            $this->fields_step_5_scheduling_timing(null);
            echo '<p class="cex_step_nav">
                <button type="submit" class="awp-btn cex_button" name="save_for_later" value="1">
                    <i class="bi bi-save"></i> ' . esc_html__('Save for Later', 'awp') . '
                </button>
                <button type="button" class="awp-btn secondary cex_button cex_prev_btn" data-prev="4">
                    <i class="bi bi-arrow-left-circle"></i> ' . esc_html__('Back', 'awp') . '
                </button>
                <button type="button" class="awp-btn primary cex_button cex_next_btn" data-step="5" data-next="6" style="float:right;">
                    <i class="bi bi-arrow-right-circle"></i> ' . esc_html__('Next: Confirm', 'awp') . '
                </button>
            </p>
        </div>

        <div id="cex_step_6" class="cex_step" style="display:none;">
            <h2><i class="bi bi-check2-circle"></i> ' . esc_html__('Step 6: Confirmation', 'awp') . '</h2>
            <div id="cex_confirm_box" class="cex_confirm_summary">' . esc_html__('Review your campaign settings below.', 'awp') . '</div>
            <div id="cex_extra_info" class="cex_estimate_details" style="margin-top:15px;"></div>
            <p class="cex_step_nav">
                <button type="submit" class="awp-btn cex_button" name="save_for_later" value="1">
                    <i class="bi bi-save"></i> ' . esc_html__('Save for Later', 'awp') . '
                </button>
                <button type="button" class="awp-btn secondary cex_button cex_prev_btn" data-prev="5">
                    <i class="bi bi-arrow-left-circle"></i> ' . esc_html__('Back', 'awp') . '
                </button>
                <button type="submit" class="awp-btn primary cex_button" style="float:right;">
                    <i class="bi bi-check-circle"></i> ' . esc_html__('Create Campaign', 'awp') . '
                </button>
            </p>
        </div>

    </form>
</div>';
        }

        if ($type === 'edit_campaign') {
            $edit_id = isset($data['edit_id']) ? intval($data['edit_id']) : 0;
            global $wpdb;
            $camp_table = $wpdb->prefix . 'wawp_campaigns';
            $camp = $wpdb->get_row($wpdb->prepare("SELECT * FROM $camp_table WHERE id=%d", $edit_id));
            if (!$camp) {
                echo '<p class="notice notice-error">' . esc_html__('Invalid campaign ID.', 'awp') . '</p>';
                return;
            }

            echo '<div class="wrap cex_wrap_form">
                            <h1 class="cex_h1"><i class="bi bi-pencil-square"></i> ' . sprintf(esc_html__('Edit Campaign: %1$s (#%2$d)', 'awp'), esc_html($camp->name), intval($camp->id)) . '</h1>
                            <div id="cex_form_error_summary" class="notice notice-error is-dismissible" style="display:none;"><p></p></div>
                            <div class="cex_recipients_info">
                                <div id="whatsapp_recipients_info" style="display:none;"><strong>' . esc_html__('WhatsApp Estimated Recipients:', 'awp') . '</strong> <span id="whatsapp_recipients_count">0</span><br></div>
                                <div id="email_recipients_info" style="display:none;"><strong>' . esc_html__('Email Estimated Recipients:', 'awp') . '</strong> <span id="email_recipients_count">0</span></div>
                                <div id="estimate_finish" class="cex_estimate_details"></div>
                            </div>
                            <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" id="cex_edit_form">
                            <input type="hidden" name="action" value="campaign_update">
                            <input type="hidden" name="camp_id" value="' . esc_attr($camp->id) . '">'
                . wp_nonce_field('campaign_update_' . $camp->id, '_camp_edit_nonce', true, false);

            $this->form_fields($camp);

            echo '<p style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;"><button type="submit" class="awp-btn primary cex_button_large"><i class="bi bi-save"></i> ' . esc_html__('Update Campaign', 'awp') . '</button></p>
                            </form>
                            </div>';
        }
    }

    private function campaigns_url($section = 'campaigns', $args = [])
    {
        return add_query_arg(
            array_merge(
                [
                    'page'        => 'wawp',
                    'awp_section' => $section,
                ],
                $args
            ),
            admin_url('admin.php')
        );
    }


    private function render_field_error_placeholder($field_id)
    {
        return '<span class="cex_field_error" id="error_for_' . esc_attr($field_id) . '" style="display:none;"></span>';
    }

    private function render_select_field($label_icon, $label_text, $id, $name, $options_data, $selected_values = [], $placeholder = 'Select options...', $is_required = false, $description = '', $is_simple_select = false, $additional_attrs = [])
    {
        $tr_class = isset($additional_attrs['tr_class']) ? 'class="' . esc_attr($additional_attrs['tr_class']) . '"' : '';
        $tr_style = isset($additional_attrs['tr_style']) ? 'style="' . esc_attr($additional_attrs['tr_style']) . '"' : '';

        echo "<tr {$tr_class} {$tr_style}><th><label for='" . esc_attr($id) . "'><i class='bi " . esc_attr($label_icon) . "'></i> " . wp_kses_post($label_text) . ($is_required ? " <span class='cex_required'>*</span>" : "") . "</label></th><td>";
        if (!$is_simple_select && is_array($options_data) && count($options_data) > 1) {
            echo '<div class="cex_btn_group">';
            echo '<button class="awp-btn cex_select_all_btn" type="button" data-target="#' . esc_attr($id) . '">' . esc_html__('All', 'awp') . '</button>';
            echo '<button class="awp-btn cex_deselect_all_btn" type="button" data-target="#' . esc_attr($id) . '" style="margin-left:5px;">' . esc_html__('None', 'awp') . '</button>';
            echo '</div>';
        }
        echo '<select ' . ($is_simple_select ? '' : 'multiple="multiple"') . ' id="' . esc_attr($id) . '" name="' . esc_attr($name) . ($is_simple_select ? '' : '[]') . '" class="cex_select2_field" data-placeholder="' . esc_attr($placeholder) . '" style="width: 100%; max-width: 450px;">';

        if ($is_simple_select || (!$is_simple_select && empty($options_data) && strpos(strtolower($placeholder), 'all') !== false)) {
            echo '<option value="">' . esc_html($placeholder) . '</option>';
        }

        if (is_array($options_data)) {
            foreach ($options_data as $value => $text_or_obj) {
                $opt_val = '';
                $opt_text = '';
                if (is_object($text_or_obj) && isset($text_or_obj->id) && isset($text_or_obj->label)) {
                    $opt_val = $text_or_obj->id;
                    $opt_text = $text_or_obj->label;
                    if (isset($text_or_obj->instance_api_id)) $opt_val = $text_or_obj->id . '|' . $text_or_obj->instance_api_id;
                } elseif (is_array($text_or_obj) && isset($text_or_obj['id']) && isset($text_or_obj['label'])) {
                    $opt_val = $text_or_obj['id'];
                    $opt_text = $text_or_obj['label'];
                } else {
                    $opt_val = $value;
                    $opt_text = $text_or_obj;
                }
                $is_selected = '';
                if ($is_simple_select) {
                    $is_selected = selected((string)$opt_val, (string)$selected_values, false);
                } else {
                    $is_selected = in_array((string)$opt_val, array_map('strval', (array)$selected_values), true) ? 'selected="selected"' : '';
                }
                echo '<option value="' . esc_attr($opt_val) . '" ' . $is_selected . '>' . esc_html($opt_text) . '</option>';
            }
        }
        echo '</select>' . $this->render_field_error_placeholder($id);
        if ($description) {
            echo '<p class="description">' . wp_kses_post($description) . '</p>';
        }
        echo '</td></tr>';
    }

    public function fields_step_1($camp = null)
    {
        global $wpdb, $wp_roles;
        $is_edit = $camp && isset($camp->id);

        $send_whatsapp_checked_val = $is_edit ? ($camp->send_whatsapp ?? 1) : 1;
        $send_email_checked_val = $is_edit ? ($camp->send_email ?? 0) : 0;
        $send_whatsapp_checked = checked($send_whatsapp_checked_val, 1, false);
        $send_email_checked = checked($send_email_checked_val, 1, false);

        $instances_raw = $wpdb->get_results("SELECT id, instance_id, name FROM {$wpdb->prefix}awp_instance_data WHERE status='online'");
        $instances_options = [];
        if ($instances_raw) {
            foreach ($instances_raw as $inst) {
                $instances_options[] = (object)['id' => $inst->id, 'instance_api_id' => $inst->instance_id, 'label' => sprintf(esc_html__('%s (ID: %d)', 'awp'), $inst->name, $inst->id)];
            }
        }
        $selected_instances = $is_edit && isset($camp->instances) ? (array)maybe_unserialize($camp->instances) : [];

        $roles_options = [];
        if ($wp_roles && isset($wp_roles->roles)) {
            foreach ($wp_roles->roles as $key => $role_details) {
                $roles_options[$key] = $role_details['name'];
            }
        }
        asort($roles_options);
        $selected_roles = $is_edit && isset($camp->role_ids) ? (array)maybe_unserialize($camp->role_ids) : [];

        $users_raw = get_users(['orderby' => 'display_name', 'order' => 'ASC', 'fields' => ['ID', 'display_name', 'user_email', 'user_login']]);
        $users_options = [];
        foreach ($users_raw as $u) {
            $fn = get_user_meta($u->ID, 'first_name', true);
            $ln = get_user_meta($u->ID, 'last_name', true);
            $label = trim("$fn $ln");
            if (!$label) $label = $u->display_name ?: $u->user_login;
            $users_options[] = ['id' => $u->ID, 'label' => sprintf(esc_html__('%1$s (%2$s - ID: %3$d)', 'awp'), $label, $u->user_email, $u->ID)];
        }
        usort($users_options, function ($a, $b) {
            return strcmp(strtolower($a['label']), strtolower($b['label']));
        });

        $selected_users = $is_edit && isset($camp->user_ids) ? (array)maybe_unserialize($camp->user_ids) : [];
        $name_val = $is_edit ? esc_attr($camp->name) : '';
        $ext_numbers_val = $is_edit && isset($camp->external_numbers) ? esc_textarea($camp->external_numbers) : '';
        $ext_emails_val = $is_edit && property_exists($camp, 'external_emails') ? esc_textarea($camp->external_emails) : '';
        $vf_checked = $is_edit && isset($camp->only_verified_phone) ? checked($camp->only_verified_phone, 1, false) : '';

        $whatsapp_specific_style_attr = $send_whatsapp_checked_val ? '' : 'display:none;';
        $email_specific_style_attr = $send_email_checked_val ? '' : 'display:none;';

        echo '<table class="form-table cex_form_table"><tbody>';
        echo '<tr><th><label for="campaign_name"><i class="bi bi-card-text"></i> ' . esc_html__('Campaign Name', 'awp') . ' <span class="cex_required">*</span></label></th><td><input type="text" id="campaign_name" name="name" value="' . $name_val . '" class="cex_input_long">' . $this->render_field_error_placeholder('campaign_name') . '</td></tr>';

        echo '<tr><th><i class="bi bi-broadcast-pin"></i> ' . esc_html__('Channels', 'awp') . ' <span class="cex_required">*</span></th><td>';
        echo '<label class="switch-container" for="send_whatsapp_switch" style="margin-right: 20px;">
                <input type="checkbox" id="send_whatsapp_switch" name="send_whatsapp" value="1" ' . $send_whatsapp_checked . '>
                <span class="switch"><span class="slider"></span></span> ' . esc_html__('Send WhatsApp', 'awp') . '
              </label>';
        echo '<label class="switch-container" for="send_email_switch">
                <input type="checkbox" id="send_email_switch" name="send_email" value="1" ' . $send_email_checked . '>
                <span class="switch"><span class="slider"></span></span> ' . esc_html__('Send Email', 'awp') . '
              </label>';
        echo $this->render_field_error_placeholder('channels_selection');
        echo '<p class="description">' . esc_html__('At least one channel (WhatsApp or Email) must be enabled.', 'awp') . '</p>';
        echo '</td></tr>';

        $this->render_select_field(
            'bi-sd-card',
            __('Instances (for WhatsApp)', 'awp'),
            'instances_input',
            'instances',
            $instances_options,
            $selected_instances,
            __('Select sending instances...', 'awp'),
            false,
            __('Required if "Send WhatsApp" channel is enabled.', 'awp'),
            false,
            ['tr_class' => 'whatsapp-specific-field-step1', 'tr_style' => $whatsapp_specific_style_attr]
        );

        $this->render_select_field('bi-people', __('Target User Roles', 'awp'), 'roles_input', 'role_ids', $roles_options, $selected_roles, __('Select user roles...', 'awp'));
        $this->render_select_field('bi-person-lines-fill', __('Target Specific Users', 'awp'), 'users_input', 'user_ids', $users_options, $selected_users, __('Select specific users...', 'awp'));

        echo '<tr class="whatsapp-specific-field-step1" style="' . $whatsapp_specific_style_attr . '"><th><label for="external_numbers"><i class="bi bi-telephone"></i> ' . esc_html__('External Numbers (WhatsApp)', 'awp') . '</label></th><td>
                  <textarea name="external_numbers" rows="3" id="external_numbers" class="cex_input_long" placeholder="' . esc_attr__('One phone number per line (e.g., 1xxxxxxxxxx)', 'awp') . '">' . $ext_numbers_val . '</textarea>
                  ' . $this->render_field_error_placeholder('external_numbers_combined_audience') . '
                  <p class="description">' . esc_html__('Enter numbers if you want to target users not in your WordPress database (for WhatsApp only).', 'awp') . '</p></td></tr>';

        echo '<tr class="email-specific-field-step1" style="' . $email_specific_style_attr . '"><th><label for="external_emails"><i class="bi bi-envelope-plus"></i> ' . esc_html__('External Emails', 'awp') . '</label></th><td>
              <textarea name="external_emails" rows="3" id="external_emails" class="cex_input_long" placeholder="' . esc_attr__('One email address per line (e.g., user@example.com)', 'awp') . '">' . $ext_emails_val . '</textarea>
              <p class="description">' . esc_html__('Enter email addresses if you want to target recipients not in your WordPress database (for Email only).', 'awp') . '</p></td></tr>';

        echo '<tr class="whatsapp-specific-field-step1" style="' . $whatsapp_specific_style_attr . '"><th><i class="bi bi-check-circle-fill"></i> ' . esc_html__('Only Verified Phones? (WhatsApp)', 'awp') . '</th><td>
                  <label class="switch-container"><input type="checkbox" name="only_verified_phone" value="1" ' . $vf_checked . '> <span class="switch"><span class="slider"></span></span> </label>
                  <span class="description">' . esc_html__('If checked, only send WhatsApp to users whose phone numbers are marked as verified by WAWP core. This filter applies only to WhatsApp recipients from WordPress users.', 'awp') . '</span></td></tr>';
        echo '</tbody></table>';
    }

    public function fields_step_2_additional_filters($camp = null)
    {
        $is_edit = $camp && isset($camp->id);
        $countries = [];
        if (class_exists('WooCommerce')) {
            $wc_countries = WC()->countries;
            if ($wc_countries) {
                $countries = $wc_countries->get_allowed_countries();
                if (empty($countries)) {
                    $countries = $wc_countries->get_countries();
                }
            }
        }
        if (empty($countries)) {
            $countries = ['US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'BR' => 'Brazil'];
        }
        asort($countries);

        $installed_translations = wp_get_installed_translations('core');
        $profile_lang_options = [];
        $available_locales = get_available_languages();

        if (!empty($available_locales)) {
            foreach ($available_locales as $locale) {
                if (isset($installed_translations[$locale])) {
                    $profile_lang_options[$locale] = $installed_translations[$locale]['native_name'] . ' (' . $locale . ')';
                } else {
                    $name_parts = explode('_', $locale);
                    $lang_name = ucfirst(str_replace('-', ' ', $name_parts[0]));
                    if (isset($name_parts[1])) {
                        $lang_name .= ' (' . strtoupper($name_parts[1]) . ')';
                    }
                    $profile_lang_options[$locale] = ($locale === 'en_US' && !isset($profile_lang_options['en_US'])) ? 'English (United States) (en_US)' : $lang_name . ' (' . $locale . ')';
                }
            }
        }
        if (empty($profile_lang_options['en_US'])) {
            $profile_lang_options['en_US'] = 'English (United States) (en_US)';
        }
        asort($profile_lang_options);

        $selected_billing_countries = $is_edit && isset($camp->billing_countries) ? (array)maybe_unserialize($camp->billing_countries) : [];
        $selected_wp_profile_languages = $is_edit && isset($camp->wp_profile_languages) ? (array)maybe_unserialize($camp->wp_profile_languages) : [];

        echo '<table class="form-table cex_form_table"><tbody>';
        echo '<tr><td colspan="2"><p class="description">' . esc_html__('These filters apply to the users selected in Step 1. They help narrow down your audience based on their profile data.', 'awp') . '</p></td></tr>';
        $this->render_select_field('bi-globe', __('Billing Country', 'awp'), 'billing_countries_input', 'billing_countries', $countries, $selected_billing_countries, __('All Countries (No Filter)', 'awp'), false, __('Filter by user billing country. If "All Countries" is selected (or none), it applies to every country.', 'awp'));
        $this->render_select_field('bi-translate', __('WordPress Profile Language', 'awp'), 'wp_profile_languages_input', 'wp_profile_languages', $profile_lang_options, $selected_wp_profile_languages, __('All Languages (No Filter)', 'awp'), false, __('Filter by user\'s WordPress profile language. If "All Languages" is selected (or none), it applies to every profile language.', 'awp'));
        echo '</tbody></table>';
    }

    public function fields_step_3_woo_filters($camp = null)
    {
        $is_edit = $camp && isset($camp->id);
        $woo_spent_val = $is_edit && isset($camp->woo_spent_over) ? esc_attr($camp->woo_spent_over) : '';
        $woo_orders_val = $is_edit && isset($camp->woo_orders_over) && $camp->woo_orders_over > 0 ? intval($camp->woo_orders_over) : '';
        $products_options = [];
        $statuses_options = [];
        if (class_exists('WooCommerce')) {
            $products_raw = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
            foreach ($products_raw as $pr) {
                $products_options[$pr->ID] = sprintf(esc_html__('%s (ID: %d)', 'awp'), $pr->post_title, $pr->ID);
            }
            asort($products_options);
            $statuses_options = wc_get_order_statuses();
        }
        $selected_woo_prods = $is_edit && isset($camp->woo_ordered_products) ? (array)maybe_unserialize($camp->woo_ordered_products) : [];
        $selected_woo_statuses = $is_edit && isset($camp->woo_order_statuses) ? (array)maybe_unserialize($camp->woo_order_statuses) : [];

        echo '<table class="form-table cex_form_table"><tbody>';
        if (class_exists('WooCommerce')) {
            echo '<tr><td colspan="2"><p class="description">' . esc_html__('These filters apply to the users selected in Step 1. They help narrow down your audience based on their WooCommerce purchase history.', 'awp') . '</p></td></tr>';
            echo '<tr><th><label for="woo_spent_over"><i class="bi bi-currency-dollar"></i> ' . esc_html__('Min. Total Spent', 'awp') . '</label></th><td><input type="number" id="woo_spent_over" step="0.01" name="woo_spent_over" value="' . $woo_spent_val . '" class="cex_input_short" placeholder="' . esc_attr__('e.g., 100.00', 'awp') . '" min="0"> <span class="description">' . esc_html__('Only include customers who spent at least this amount.', 'awp') . '</span>' . $this->render_field_error_placeholder('woo_spent_over') . '</td></tr>';
            echo '<tr><th><label for="woo_orders_over"><i class="bi bi-cart-check"></i> ' . esc_html__('Min. Order Count', 'awp') . '</label></th><td><input type="number" id="woo_orders_over" name="woo_orders_over" value="' . $woo_orders_val . '" class="cex_input_short" placeholder="' . esc_attr__('e.g., 2', 'awp') . '" min="0"> <span class="description">' . esc_html__('Only include customers with at least this many orders.', 'awp') . '</span>' . $this->render_field_error_placeholder('woo_orders_over') . '</td></tr>';
            $this->render_select_field('bi-box2-heart', __('Purchased Specific Products', 'awp'), 'products_input', 'woo_products', $products_options, $selected_woo_prods, __('Filter by products purchased...', 'awp'));
            $this->render_select_field('bi-archive', __('Order Statuses (for Product Filter)', 'awp'), 'statuses_input', 'woo_statuses', $statuses_options, $selected_woo_statuses, __('Consider these order statuses for product purchase filter...', 'awp'));
        } else {
            echo '<tr><td colspan="2"><p class="notice notice-info">' . esc_html__('WooCommerce is not active. No customer filters available.', 'awp') . '</p></td></tr>';
        }
        echo '</tbody></table>';
    }

    public function fields_step_4_message_content($camp = null)
    {
        $is_edit = $camp && isset($camp->id);
        $send_type_val = $is_edit && isset($camp->send_type) ? $camp->send_type : 'text';
        $message_val = $is_edit && isset($camp->message) ? esc_textarea($camp->message) : '';
        $media_url_val = $is_edit && isset($camp->media_url) ? esc_attr($camp->media_url) : '';

        $send_whatsapp_checked_val = $is_edit ? ($camp->send_whatsapp ?? 1) : (isset($_POST['send_whatsapp']) ? intval($_POST['send_whatsapp']) : 1);
        $send_email_checked_val = $is_edit ? ($camp->send_email ?? 0) : (isset($_POST['send_email']) ? intval($_POST['send_email']) : 0);

        $email_subject_val = $is_edit && isset($camp->email_subject) ? esc_attr($camp->email_subject) : '';
        $email_message_val = $is_edit && isset($camp->email_message) ? $camp->email_message : '';
        $post_id_val = $is_edit && isset($camp->post_id) ? intval($camp->post_id) : 0;
        $append_post_checked = $is_edit && isset($camp->append_post) ? checked($camp->append_post, 1, false) : '';
        $post_title_checked = $is_edit && isset($camp->post_include_title) ? checked($camp->post_include_title, 1, false) : 'checked="checked"';
        $post_excerpt_checked = $is_edit && isset($camp->post_include_excerpt) ? checked($camp->post_include_excerpt, 1, false) : 'checked="checked"';
        $post_link_checked = $is_edit && isset($camp->post_include_link) ? checked($camp->post_include_link, 1, false) : 'checked="checked"';
        $post_image_checked = $is_edit && isset($camp->post_include_image) ? checked($camp->post_include_image, 1, false) : '';
        $product_id_val = $is_edit && isset($camp->product_id) ? intval($camp->product_id) : 0;
        $append_prod_checked = $is_edit && isset($camp->append_product) ? checked($camp->append_product, 1, false) : '';
        $prod_title_checked = $is_edit && isset($camp->product_include_title) ? checked($camp->product_include_title, 1, false) : 'checked="checked"';
        $prod_excerpt_checked = $is_edit && isset($camp->product_include_excerpt) ? checked($camp->product_include_excerpt, 1, false) : '';
        $prod_price_checked = $is_edit && isset($camp->product_include_price) ? checked($camp->product_include_price, 1, false) : 'checked="checked"';
        $prod_link_checked = $is_edit && isset($camp->product_include_link) ? checked($camp->product_include_link, 1, false) : 'checked="checked"';
        $prod_image_checked = $is_edit && isset($camp->product_include_image) ? checked($camp->product_include_image, 1, false) : '';
        $all_posts = get_posts(['post_type' => ['post', 'page'], 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true]);
        $all_products = [];
        if (class_exists('WooCommerce')) {
            $all_products = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true]);
        }

        echo '<table class="form-table cex_form_table"><tbody>';

        echo '<tr><th><label for="send_type"><i class="bi bi-chat-right-dots"></i> ' . esc_html__('Message Type', 'awp') . '</label></th><td><select name="send_type" id="send_type" class="cex_input_medium">';
        $send_type_opts = ['text' => __('Text Only', 'awp'), 'media' => __('Text with Media (WhatsApp)', 'awp'), 'post' => __('Share WordPress Post/Page', 'awp'), 'product' => __('Share WooCommerce Product', 'awp')];
        foreach ($send_type_opts as $k => $v) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($send_type_val, $k, false) . '>' . esc_html($v) . '</option>';
        }
        echo '</select><p class="description">' . esc_html__('"Text with Media" is specific to WhatsApp. For Email, media is embedded in the HTML body. Post/Product sharing adds content to enabled channels.', 'awp') . '</p></td></tr>';

        echo '<tr class="cex_field_group_header channel-content-group" id="whatsapp_content_header" ' . ($send_whatsapp_checked_val ? '' : 'style="display:none;"') . '>
                <td colspan="2"><h4><i class="bi bi-whatsapp"></i> ' . esc_html__('WhatsApp Content', 'awp') . '</h4><hr class="cex_hr_tight"></td>
            </tr>';
        echo '<tr class="cex_field_group channel-content-group" id="whatsapp_text_group" ' . ($send_whatsapp_checked_val ? '' : 'style="display:none;"') . '><th><label for="message_input"><i class="bi bi-fonts"></i> ' . esc_html__('WhatsApp Text', 'awp') . ' <span class="cex_required" id="whatsapp_text_required_star" style="display:none;">*</span></label></th><td>
                <textarea name="message" id="message_input" rows="5" class="cex_input_long" placeholder="' . esc_attr__('Enter your WhatsApp message here...', 'awp') . '">' . $message_val . '</textarea>
                ' . $this->render_field_error_placeholder('message_input') . '
                <p class="description" id="whatsapp_text_description">' . esc_html__('This text will be used unless a Post/Product share replaces it (if "Append" is not checked below).', 'awp') . '</p>
            </td></tr>';
        echo '<tr class="cex_field_group channel-content-group" id="whatsapp_media_group" style="display:none;"><th><label for="media_url_input"><i class="bi bi-image"></i> ' . esc_html__('WhatsApp Media', 'awp') . ' <span class="cex_required" id="whatsapp_media_required_star" style="display:none;">*</span></label></th><td>
                <div class="cex_media_uploader">
                    <input type="text" name="media_url" id="media_url_input" value="' . $media_url_val . '" class="cex_input_long" placeholder="' . esc_attr__('Enter media URL (image, video, doc)', 'awp') . '">
                    <input type="file" name="media_file_upload" id="media_file_upload_input" accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="display:none;">
                    <button type="button" class="button" id="upload_media_button" style="margin-left:5px;">' . esc_html__('Upload/Select Media', 'awp') . '</button>
                    ' . $this->render_field_error_placeholder('media_url_input') . '
                    <div id="media_preview_container" style="margin-top:10px;">';
        if ($media_url_val && preg_match('/\.(jpeg|jpg|gif|png|webp)$/i', $media_url_val)) {
            echo '<img src="' . esc_url($media_url_val) . '" class="cex_media_preview_img" alt="' . esc_attr__('Media Preview', 'awp') . '"/>';
        } elseif ($media_url_val) {
            echo '<a href="' . esc_url($media_url_val) . '" target="_blank" class="cex_media_preview_link"><i class="bi bi-file-earmark-check"></i> ' . esc_html__('View Linked Media', 'awp') . '</a>';
        }
        echo '</div></div><p class="description">' . esc_html__('Required if Message Type is "Text with Media" and WhatsApp is enabled.', 'awp') . '</p></td></tr>';

        echo '<tr class="cex_field_group_header channel-content-group" id="email_content_header" ' . ($send_email_checked_val ? '' : 'style="display:none;"') . '>
                <td colspan="2"><h4><i class="bi bi-envelope"></i> ' . esc_html__('Email Content', 'awp') . '</h4><hr class="cex_hr_tight"></td>
            </tr>';
        echo '<tr class="cex_field_group channel-content-group" id="email_subject_group" ' . ($send_email_checked_val ? '' : 'style="display:none;"') . '><th><label for="email_subject_input">' . esc_html__('Email Subject', 'awp') . ' <span class="cex_required" id="email_subject_required_star" style="display:none;">*</span></label></th><td>
                <input type="text" name="email_subject" id="email_subject_input" value="' . $email_subject_val . '" class="cex_input_long" placeholder="' . esc_attr__('Your Email Subject', 'awp') . '">
                ' . $this->render_field_error_placeholder('email_subject_input') . '
            </td></tr>';
        echo '<tr class="cex_field_group channel-content-group" id="email_body_group" ' . ($send_email_checked_val ? '' : 'style="display:none;"') . '><th><label for="email_message_editor">' . esc_html__('Email Body', 'awp') . ' <span class="cex_required" id="email_body_required_star" style="display:none;">*</span></label></th><td>';
        wp_editor($email_message_val, 'email_message_editor', ['textarea_name' => 'email_message', 'textarea_rows' => 10, 'media_buttons' => true, 'tinymce' => true, 'quicktags' => true]);
        echo $this->render_field_error_placeholder('email_message_editor');
        echo '<p class="description">' . esc_html__('Compose your email message. HTML is supported. Placeholders like {first_name}, {last_name} can be used.', 'awp') . '</p>';
        echo '</td></tr>';

        echo '<tr class="cex_field_group shared-content-group" id="field-group-post" style="' . ($send_type_val === 'post' ? '' : 'display:none;') . '"><th><label for="post_id_select"><i class="bi bi-file-earmark-text"></i> ' . esc_html__('Select Post/Page', 'awp') . ' <span class="cex_required" id="post_id_required_star" style="display:none;">*</span></label></th><td>
                <select id="post_id_select" name="post_id" class="cex_select2_field cex_input_long_select" data-placeholder="' . esc_attr__('Choose a post or page...', 'awp') . '">
                <option value="">' . esc_html__('(None)', 'awp') . '</option>';
        foreach ($all_posts as $p) {
            echo '<option value="' . esc_attr($p->ID) . '" ' . selected($post_id_val, $p->ID, false) . '>' . esc_html(wp_trim_words($p->post_title, 15, '...')) . ' (' . ucfirst($p->post_type) . ')</option>';
        }
        echo '</select>' . $this->render_field_error_placeholder('post_id_select') . '<div id="post_preview_area" class="cex_preview_box"></div>
                <div class="cex_append_options">
                    <label><input type="checkbox" name="append_post" value="1" ' . $append_post_checked . '> ' . esc_html__('Append post info to message text (if custom text is provided)?', 'awp') . '</label><br>
                    <strong>' . esc_html__('Include in shared post info:', 'awp') . '</strong><br>
                    <label><input type="checkbox" name="post_include_title" value="1" ' . $post_title_checked . '> ' . esc_html__('Title', 'awp') . '</label>
                    <label style="margin-left:10px;"><input type="checkbox" name="post_include_excerpt" value="1" ' . $post_excerpt_checked . '> ' . esc_html__('Excerpt', 'awp') . '</label>
                    <label style="margin-left:10px;"><input type="checkbox" name="post_include_link" value="1" ' . $post_link_checked . '> ' . esc_html__('Link', 'awp') . '</label>
                    <label style="margin-left:10px;"><input type="checkbox" name="post_include_image" value="1" ' . $post_image_checked . '> ' . esc_html__('Featured Image', 'awp') . '</label>
                    <p class="description"><small>' . esc_html__('If "Append" is not checked, the Post/Page content will replace the main message text for the respective channel(s). Featured Image is primarily for WhatsApp media or email HTML.', 'awp') . '</small></p>
                </div></td></tr>';
        if (class_exists('WooCommerce')) {
            echo '<tr class="cex_field_group shared-content-group" id="field-group-product" style="' . ($send_type_val === 'product' ? '' : 'display:none;') . '"><th><label for="product_id_select"><i class="bi bi-bag"></i> ' . esc_html__('Select Product', 'awp') . ' <span class="cex_required" id="product_id_required_star" style="display:none;">*</span></label></th><td>
                        <select id="product_id_select" name="product_id" class="cex_select2_field cex_input_long_select" data-placeholder="' . esc_attr__('Choose a product...', 'awp') . '">
                        <option value="">' . esc_html__('(None)', 'awp') . '</option>';
            foreach ($all_products as $pr) {
                echo '<option value="' . esc_attr($pr->ID) . '" ' . selected($product_id_val, $pr->ID, false) . '>' . esc_html(wp_trim_words($pr->post_title, 15, '...')) . '</option>';
            }
            echo '</select>' . $this->render_field_error_placeholder('product_id_select') . '<div id="product_preview_area" class="cex_preview_box"></div>
                        <div class="cex_append_options">
                            <label><input type="checkbox" name="append_product" value="1" ' . $append_prod_checked . '> ' . esc_html__('Append product info to message text (if custom text is provided)?', 'awp') . '</label><br>
                            <strong>' . esc_html__('Include in shared product info:', 'awp') . '</strong><br>
                            <label><input type="checkbox" name="product_include_title" value="1" ' . $prod_title_checked . '> ' . esc_html__('Title', 'awp') . '</label>
                            <label style="margin-left:10px;"><input type="checkbox" name="product_include_price" value="1" ' . $prod_price_checked . '> ' . esc_html__('Price', 'awp') . '</label>
                            <label style="margin-left:10px;"><input type="checkbox" name="product_include_excerpt" value="1" ' . $prod_excerpt_checked . '> ' . esc_html__('Short Description', 'awp') . '</label>
                            <label style="margin-left:10px;"><input type="checkbox" name="product_include_link" value="1" ' . $prod_link_checked . '> ' . esc_html__('Link', 'awp') . '</label>
                            <label style="margin-left:10px;"><input type="checkbox" name="product_include_image" value="1" ' . $prod_image_checked . '> ' . esc_html__('Main Image', 'awp') . '</label>
                            <p class="description"><small>' . esc_html__('If "Append" is not checked, the Product content will replace the main message text for the respective channel(s). Main Image is primarily for WhatsApp media or email HTML.', 'awp') . '</small></p>
                        </div></td></tr>';
        }
        echo '</tbody></table>';
    }

    public function fields_step_5_scheduling_timing($camp = null)
    {
        $is_edit = $camp && isset($camp->id);

        $min_wa_interval_val = $is_edit && isset($camp->min_whatsapp_interval) ? intval($camp->min_whatsapp_interval) : 60;
        $max_wa_interval_val = $is_edit && isset($camp->max_whatsapp_interval) ? intval($camp->max_whatsapp_interval) : 75;
        $min_email_interval_val = $is_edit && isset($camp->min_email_interval) ? intval($camp->min_email_interval) : 30;
        $max_email_interval_val = $is_edit && isset($camp->max_email_interval) ? intval($camp->max_email_interval) : 60;
        $max_wa_per_day_val = $is_edit && property_exists($camp, 'max_wa_per_day') ? intval($camp->max_wa_per_day) : 0;
        $max_email_per_day_val = $is_edit && property_exists($camp, 'max_email_per_day') ? intval($camp->max_email_per_day) : 0;
        $start_dt_val = $is_edit && isset($camp->start_datetime) && $camp->start_datetime ? date('Y-m-d\TH:i', strtotime($camp->start_datetime)) : '';
        $repeat_type_val = $is_edit && isset($camp->repeat_type) ? $camp->repeat_type : 'no';
        $repeat_days_val = $is_edit && isset($camp->repeat_days) ? intval($camp->repeat_days) : 0;
        $send_whatsapp_enabled_initial = $is_edit ? ($camp->send_whatsapp ?? 1) : (isset($_POST['send_whatsapp']) ? intval($_POST['send_whatsapp']) : 1);
        $send_email_enabled_initial = $is_edit ? ($camp->send_email ?? 0) : (isset($_POST['send_email']) ? intval($_POST['send_email']) : 0);

        echo '<table class="form-table cex_form_table"><tbody>';

        echo '<tr class="cex_field_group_header channel-schedule-group" id="whatsapp_schedule_fields_header" ' . ($send_whatsapp_enabled_initial ? '' : 'style="display:none;"') . '>
                <td colspan="2"><h4><i class="bi bi-whatsapp"></i> ' . esc_html__('WhatsApp Scheduling', 'awp') . '</h4><hr class="cex_hr_tight"></td>
            </tr>';
        echo '<tr class="cex_field_group channel-schedule-group" id="whatsapp_min_interval_row" ' . ($send_whatsapp_enabled_initial ? '' : 'style="display:none;"') . '><th><label for="min_whatsapp_interval">' . esc_html__('Min Interval (sec)', 'awp') . '</label></th><td><input type="number" id="min_whatsapp_interval" name="min_whatsapp_interval" value="' . $min_wa_interval_val . '" class="cex_input_short" min="1">' . $this->render_field_error_placeholder('min_whatsapp_interval') . ' <span class="description">' . esc_html__('Minimum seconds between WhatsApp messages.', 'awp') . '</span></td></tr>';
        echo '<tr class="cex_field_group channel-schedule-group" id="whatsapp_max_interval_row" ' . ($send_whatsapp_enabled_initial ? '' : 'style="display:none;"') . '><th><label for="max_whatsapp_interval">' . esc_html__('Max Interval (sec)', 'awp') . '</label></th><td><input type="number" id="max_whatsapp_interval" name="max_whatsapp_interval" value="' . $max_wa_interval_val . '" class="cex_input_short" min="1">' . $this->render_field_error_placeholder('max_whatsapp_interval') . ' <span class="description">' . esc_html__('Maximum seconds between WhatsApp messages.', 'awp') . '</span></td></tr>';
        echo '<tr class="cex_field_group channel-schedule-group" id="whatsapp_daily_limit_row" ' . ($send_whatsapp_enabled_initial ? '' : 'style="display:none;"') . '><th><label for="max_wa_per_day"><i class="bi bi-calendar3"></i> ' . esc_html__('WhatsApp Daily Send Limit', 'awp') . '</label></th><td><input type="number" id="max_wa_per_day" name="max_wa_per_day" value="' . $max_wa_per_day_val . '" class="cex_input_short" min="0">
                <p class="description">' . esc_html__('Max WhatsApp messages per day. 0 for no limit. Recommended: 500 or less.', 'awp') . '</p></td></tr>';

        echo '<tr class="cex_field_group_header channel-schedule-group" id="email_schedule_fields_header" ' . ($send_email_enabled_initial ? '' : 'style="display:none;"') . '>
                <td colspan="2"><h4><i class="bi bi-envelope"></i> ' . esc_html__('Email Scheduling', 'awp') . '</h4><hr class="cex_hr_tight"></td>
            </tr>';
        echo '<tr class="cex_field_group channel-schedule-group" id="email_min_interval_row" ' . ($send_email_enabled_initial ? '' : 'style="display:none;"') . '><th><label for="min_email_interval">' . esc_html__('Min Interval (sec)', 'awp') . '</label></th><td><input type="number" id="min_email_interval" name="min_email_interval" value="' . $min_email_interval_val . '" class="cex_input_short" min="1">' . $this->render_field_error_placeholder('min_email_interval') . ' <span class="description">' . esc_html__('Minimum seconds between Email messages.', 'awp') . '</span></td></tr>';
        echo '<tr class="cex_field_group channel-schedule-group" id="email_max_interval_row" ' . ($send_email_enabled_initial ? '' : 'style="display:none;"') . '><th><label for="max_email_interval">' . esc_html__('Max Interval (sec)', 'awp') . '</label></th><td><input type="number" id="max_email_interval" name="max_email_interval" value="' . $max_email_interval_val . '" class="cex_input_short" min="1">' . $this->render_field_error_placeholder('max_email_interval') . ' <span class="description">' . esc_html__('Maximum seconds between Email messages.', 'awp') . '</span></td></tr>';
        echo '<tr class="cex_field_group channel-schedule-group" id="email_daily_limit_row" ' . ($send_email_enabled_initial ? '' : 'style="display:none;"') . '><th><label for="max_email_per_day"><i class="bi bi-calendar3"></i> ' . esc_html__('Email Daily Send Limit', 'awp') . '</label></th><td><input type="number" id="max_email_per_day" name="max_email_per_day" value="' . $max_email_per_day_val . '" class="cex_input_short" min="0">
                <p class="description">' . esc_html__('Max Email messages per day. 0 for no limit.', 'awp') . '</p></td></tr>';

        echo '<tr><td colspan="2"><h4><i class="bi bi-calendar-week"></i> ' . esc_html__('Common Scheduling (Overall)', 'awp') . '</h4><hr class="cex_hr_tight"></td></tr>';
        echo '<tr><th><label for="start_datetime"><i class="bi bi-alarm"></i> ' . esc_html__('Schedule Start', 'awp') . '</label></th><td><input type="datetime-local" name="start_datetime" id="start_datetime" value="' . $start_dt_val . '" class="cex_input_medium">
                <button type="button" class="button" id="btn-now" style="margin-left:5px;">' . esc_html__('Set to Now', 'awp') . '</button> <p class="description">' . esc_html__('Leave blank to start ASAP after creation/activation.', 'awp') . '</p></td></tr>';
        echo '<tr><th><label for="repeat_type"><i class="bi bi-arrow-repeat"></i> ' . esc_html__('Repeat Campaign', 'awp') . '</label></th><td><select name="repeat_type" id="repeat_type" class="cex_input_medium">';
        $repeat_opts = ['no' => __('No Repeat', 'awp'), 'daily' => __('Daily', 'awp'), 'monthly' => __('Monthly', 'awp'), 'annual' => __('Annual', 'awp'), 'custom' => __('Custom Interval', 'awp')];
        foreach ($repeat_opts as $k => $v) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($repeat_type_val, $k, false) . '>' . esc_html($v) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr id="repeat_days_row" style="' . ($repeat_type_val === 'custom' ? '' : 'display:none;') . '"><th><label for="repeat_days"><i class="bi bi-calendar-event"></i> ' . esc_html__('Custom Repeat Days', 'awp') . '</label></th><td><input type="number" id="repeat_days" name="repeat_days" value="' . $repeat_days_val . '" class="cex_input_short" min="0"> <span class="description">' . esc_html__('Number of days after completion to repeat.', 'awp') . '</span></td></tr>';
        echo '</tbody></table>';
    }

    public function form_fields($camp)
    {
        echo '<h3><i class="bi bi-people-fill"></i> ' . esc_html__('Audience, Channels & Name', 'awp') . '</h3>';
        $this->fields_step_1($camp);
        echo '<hr class="cex_hr">';

        echo '<h3><i class="bi bi-funnel-fill"></i> ' . esc_html__('Additional Customer Filters', 'awp') . '</h3>';
        $this->fields_step_2_additional_filters($camp);
        echo '<hr class="cex_hr">';

        echo '<h3><i class="bi bi-cart-check-fill"></i> ' . esc_html__('WooCommerce Customer Filters', 'awp') . '</h3>';
        if (class_exists('WooCommerce')) {
            $this->fields_step_3_woo_filters($camp);
        } else {
            echo '<p class="notice notice-info">' . esc_html__('WooCommerce is not active. WooCommerce specific filters are disabled.', 'awp') . '</p>';
        }
        echo '<hr class="cex_hr">';

        echo '<h3><i class="bi bi-envelope-open-fill"></i> ' . esc_html__('Message Content', 'awp') . '</h3>';
        $this->fields_step_4_message_content($camp);
        echo '<hr class="cex_hr">';

        echo '<h3><i class="bi bi-clock-history"></i> ' . esc_html__('Scheduling & Timing', 'awp') . '</h3>';
        $this->fields_step_5_scheduling_timing($camp);
    }
}