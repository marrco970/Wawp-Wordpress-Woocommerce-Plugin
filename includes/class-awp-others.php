<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once AWP_PLUGIN_DIR . 'integrations/elementor-integration.php';
require_once AWP_PLUGIN_DIR . 'integrations/block-patterns.php';
require_once AWP_PLUGIN_DIR . 'includes/class-wawp-connector.php';

class AWP_Others {
    
    private $db_manager;

    public function __construct() {
        $this->db_manager = new AWP_Database_Manager();

        add_shortcode('wawp-fast-login', [$this, 'render_fast_login_shortcode']);

        add_filter('manage_users_columns', [$this, 'add_whatsapp_verified_column']);
        add_filter('manage_users_custom_column', [$this, 'populate_whatsapp_verified_column'], 10, 3);
        add_filter('manage_users_columns', [$this, 'add_phone_number_column'], 20);
        add_filter('manage_users_custom_column', [$this, 'populate_phone_number_column'], 20, 3);
        add_filter('manage_users_columns', [$this, 'add_send_message_column'], 30);
        add_filter('manage_users_custom_column', [$this, 'populate_send_message_column'], 30, 3);

        add_action('show_user_profile', [$this, 'show_user_phone_field']);
        add_action('edit_user_profile', [$this, 'show_user_phone_field']);
        add_action('personal_options_update', [$this, 'save_user_phone_field']);
        add_action('edit_user_profile_update', [$this, 'save_user_phone_field']);
        
        add_action('woocommerce_edit_account_form', [$this, 'awp_show_custom_phone_fields']);
        add_action('woocommerce_save_account_details', [ $this, 'awp_save_custom_phone_fields' ]);

        $this->setup_user_deletion_hook();
        $this->setup_phone_sync_hooks();
        $this->enable_number_search_bar();

        add_action('admin_enqueue_scripts', [$this, 'enqueue_send_message_assets']);
        add_action('wp_ajax_awp_send_user_custom_message', [$this, 'handle_send_user_custom_message']);
        add_action('profile_update', [$this, 'sync_phone_fields']);
        add_filter('bulk_actions-users', [$this, 'awp_add_bulk_verification_actions']);
        add_filter('handle_bulk_actions-users', [$this, 'awp_handle_bulk_verification_actions'], 10, 3);
        add_action('admin_notices', [$this, 'awp_verification_bulk_admin_notice']);
    }
    
    public function awp_show_custom_phone_fields() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $user_info = $this->db_manager->get_user_info($user_id);
    $awp_phone = !empty($user_info->phone) ? $user_info->phone : '---';
    $woo_phone = get_user_meta($user_id, 'billing_phone', true);
    $verified = $this->db_manager->get_user_verification_status($user_id, 'whatsapp');
    ?>
<br><hr>

    <h3><?php esc_html_e('My Phone Details', 'awp'); ?></h3>

    <!-- AWP/Login Phone (left column) -->
    <p class="form-row form-row-first">
        <label for="awp_user_phone"><?php esc_html_e('Login Phone Number', 'awp'); ?></label>
        <input 
            type="text" 
            name="awp_user_phone" 
            id="awp_user_phone" 
            value="<?php echo esc_attr($awp_phone); ?>"
        />

        <?php if ($verified) : ?>
            <span class="awp-phone-badge verified" style="margin-left: 10px;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="rgba(34,197,94,1)">
                    <path d="M10.007 2.10377C8.60544 1.65006 7.08181 2.28116 6.41156 3.59306L5.60578 5.17023C5.51004 5.35763 5.35763 5.51004 5.17023 5.60578L3.59306 6.41156C2.28116 7.08181 1.65006 8.60544 2.10377 10.007L2.64923 11.692C2.71404 11.8922 2.71404 12.1078 2.64923 12.308L2.10377 13.993C1.65006 15.3946 2.28116 16.9182 3.59306 17.5885L5.17023 18.3942C5.35763 18.49 5.51004 18.6424 5.60578 18.8298L6.41156 20.407C7.08181 21.7189 8.60544 22.35 10.007 21.8963L11.692 21.3508C11.8922 21.286 12.1078 21.286 12.308 21.3508L13.993 21.8963C15.3946 22.35 16.9182 21.7189 17.5885 20.407L18.3942 18.8298C18.49 18.6424 18.6424 18.49 18.8298 18.3942L20.407 17.5885C21.7189 16.9182 22.35 15.3946 21.8963 13.993L21.3508 12.308C21.286 12.1078 21.286 11.8922 21.3508 11.692L21.8963 10.007C22.35 8.60544 21.7189 7.08181 20.407 6.41156L18.8298 5.60578C18.6424 5.51004 18.49 5.35763 18.3942 5.17023L17.5885 3.59306C16.9182 2.28116 15.3946 1.65006 13.993 2.10377L12.308 2.64923C12.1078 2.71403 11.8922 2.71404 11.692 2.64923L10.007 2.10377ZM6.75977 11.7573L8.17399 10.343L11.0024 13.1715L16.6593 7.51465L18.0735 8.92886L11.0024 15.9999L6.75977 11.7573Z"></path>
                </svg>
                <?php esc_html_e('Verified', 'awp'); ?>
            </span>
        <?php else : ?>
            <span class="awp-phone-badge not-verified" style="margin-left: 10px; color: #ED3737;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="rgba(237,55,55,1)">
                    <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"/>
                </svg>
                <?php esc_html_e('Not Verified', 'awp'); ?>
            </span>
        <?php endif; ?>
    </p>

    <div class="clear"></div>
    <?php
}

    public function awp_save_custom_phone_fields( $user_id ) {
    if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['awp_user_phone'] ) ) {
        $awp_phone = sanitize_text_field( $_POST['awp_user_phone'] );

        if ( !empty($awp_phone) && ! $this->is_valid_phone($awp_phone) ) {
            wc_add_notice(__('Invalid phone number format. Use +1234567890', 'awp'), 'error');
            return;
        }

        $existing_users = get_users([
            'meta_key'   => 'awp-user-phone',
            'meta_value' => $awp_phone,
            'exclude'    => [ $user_id ],
            'fields'     => 'ids',
            'number'     => 1,
        ]);
        if (!empty($existing_users)) {
            wc_add_notice(__('That phone number is already used by another account.', 'awp'), 'error');
            return;
        }

        update_user_meta($user_id, 'awp-user-phone', $awp_phone);
        update_user_meta($user_id, 'billing_phone', $awp_phone);

        $this->db_manager->update_user_phone($user_id, $awp_phone);

        $previously_verified = $this->db_manager->get_user_verification_status($user_id, 'whatsapp');
        $previous_awp_phone  = get_user_meta($user_id, 'awp-user-phone', true);
        if ($previously_verified && $previous_awp_phone !== $awp_phone) {
            $this->db_manager->update_user_verification($user_id, 'whatsapp', false);
        }
    }
}

    public function awp_add_bulk_verification_actions($bulk_actions) {
        $bulk_actions['awp_mark_verified']   = __('Mark as Verified', 'awp');
        $bulk_actions['awp_mark_unverified'] = __('Mark as Not Verified', 'awp');
        return $bulk_actions;
    }
    
    public function awp_handle_bulk_verification_actions($redirect_to, $action, $user_ids) {
        if ($action === 'awp_mark_verified') {
            foreach ($user_ids as $user_id) {
                $this->db_manager->update_user_verification($user_id, 'whatsapp', true);
            }
            $redirect_to = add_query_arg('awp_marked_verified', count($user_ids), $redirect_to);
        }
    
        if ($action === 'awp_mark_unverified') {
            foreach ($user_ids as $user_id) {
                $this->db_manager->update_user_verification($user_id, 'whatsapp', false);
            }
            $redirect_to = add_query_arg('awp_marked_unverified', count($user_ids), $redirect_to);
        }
    
        return $redirect_to;
    }
    
    public function awp_verification_bulk_admin_notice() {
        if (isset($_GET['awp_marked_verified'])) {
            $count = intval($_GET['awp_marked_verified']);
            printf(
                '<div id="message" class="updated notice is-dismissible"><p>%s user(s) marked as Verified.</p></div>',
                $count
            );
        }
        if (isset($_GET['awp_marked_unverified'])) {
            $count = intval($_GET['awp_marked_unverified']);
            printf(
                '<div id="message" class="updated notice is-dismissible"><p>%s user(s) marked as Not Verified.</p></div>',
                $count
            );
        }
    }

    public function add_send_message_column($columns) {
        $columns['awp_send_message'] = __('Send Message', 'awp');
        return $columns;
    }

    public function populate_send_message_column($value, $column_name, $user_id) {
        if ($column_name === 'awp_send_message') {
            $awp_phone = get_user_meta($user_id, 'awp-user-phone', true);
            if (!$awp_phone) {
                $awp_phone = get_user_meta($user_id, 'billing_phone', true);
            }
            if (!$awp_phone) {
                $awp_phone = '';
            }
            $button_html = '<button class="awp-send-msg-btn" 
                data-user-id="'.esc_attr($user_id).'" 
                data-user-phone="'.esc_attr($awp_phone).'">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7.25361 18.4944L7.97834 18.917C9.18909 19.623 10.5651 20 12.001 20C16.4193 20 20.001 16.4183 20.001 12C20.001 7.58172 16.4193 4 12.001 4C7.5827 4 4.00098 7.58172 4.00098 12C4.00098 13.4363 4.37821 14.8128 5.08466 16.0238L5.50704 16.7478L4.85355 19.1494L7.25361 18.4944ZM2.00516 22L3.35712 17.0315C2.49494 15.5536 2.00098 13.8345 2.00098 12C2.00098 6.47715 6.47813 2 12.001 2C17.5238 2 22.001 6.47715 22.001 12C22.001 17.5228 17.5238 22 12.001 22C10.1671 22 8.44851 21.5064 6.97086 20.6447L2.00516 22ZM8.39232 7.30833C8.5262 7.29892 8.66053 7.29748 8.79459 7.30402C8.84875 7.30758 8.90265 7.31384 8.95659 7.32007C9.11585 7.33846 9.29098 7.43545 9.34986 7.56894C9.64818 8.24536 9.93764 8.92565 10.2182 9.60963C10.2801 9.76062 10.2428 9.95633 10.125 10.1457C10.0652 10.2428 9.97128 10.379 9.86248 10.5183C9.74939 10.663 9.50599 10.9291 9.50599 10.9291C9.50599 10.9291 9.40738 11.0473 9.44455 11.1944C9.45903 11.25 9.50521 11.331 9.54708 11.3991C9.57027 11.4368 9.5918 11.4705 9.60577 11.4938C9.86169 11.9211 10.2057 12.3543 10.6259 12.7616C10.7463 12.8783 10.8631 12.9974 10.9887 13.108C11.457 13.5209 11.9868 13.8583 12.559 14.1082L12.5641 14.1105C12.6486 14.1469 12.692 14.1668 12.8157 14.2193C12.8781 14.2457 12.9419 14.2685 13.0074 14.2858C13.0311 14.292 13.0554 14.2955 13.0798 14.2972C13.2415 14.3069 13.335 14.2032 13.3749 14.1555C14.0984 13.279 14.1646 13.2218 14.1696 13.2222V13.2238C14.2647 13.1236 14.4142 13.0888 14.5476 13.097C14.6085 13.1007 14.6691 13.1124 14.7245 13.1377C15.2563 13.3803 16.1258 13.7587 16.1258 13.7587L16.7073 14.0201C16.8047 14.0671 16.8936 14.1778 16.8979 14.2854C16.9005 14.3523 16.9077 14.4603 16.8838 14.6579C16.8525 14.9166 16.7738 15.2281 16.6956 15.3913C16.6406 15.5058 16.5694 15.6074 16.4866 15.6934C16.3743 15.81 16.2909 15.8808 16.1559 15.9814C16.0737 16.0426 16.0311 16.0714 16.0311 16.0714C15.8922 16.159 15.8139 16.2028 15.6484 16.2909C15.391 16.428 15.1066 16.5068 14.8153 16.5218C14.6296 16.5313 14.4444 16.5447 14.2589 16.5347C14.2507 16.5342 13.6907 16.4482 13.6907 16.4482C12.2688 16.0742 10.9538 15.3736 9.85034 14.402C9.62473 14.2034 9.4155 13.9885 9.20194 13.7759C8.31288 12.8908 7.63982 11.9364 7.23169 11.0336C7.03043 10.5884 6.90299 10.1116 6.90098 9.62098C6.89729 9.01405 7.09599 8.4232 7.46569 7.94186C7.53857 7.84697 7.60774 7.74855 7.72709 7.63586C7.85348 7.51651 7.93392 7.45244 8.02057 7.40811C8.13607 7.34902 8.26293 7.31742 8.39232 7.30833Z"></path></svg>
                ' . esc_html__('Send', 'awp') . '
            </button>';
            return $button_html;
        }
        return $value;
    }

    public function handle_send_user_custom_message() {

    check_ajax_referer( 'awp_send_msg_nonce', 'security' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized user.', 'awp' ) );
    }

    $user_id     = isset( $_POST['user_id'] )     ? absint( $_POST['user_id'] )     : 0;
    $instance_id = isset( $_POST['instance_id'] ) ? sanitize_text_field( $_POST['instance_id'] ) : '';
    $text        = isset( $_POST['text'] )        ? wp_unslash( $_POST['text'] )    : '';

    if ( $user_id <= 0 || empty( $instance_id ) || empty( $text ) ) {
        wp_send_json_error( __( 'Missing fields.', 'awp' ) );
    }

    $phone = get_user_meta( $user_id, 'awp-user-phone', true );
    if ( ! $phone ) {
        $phone = get_user_meta( $user_id, 'billing_phone', true );
    }
    if ( ! $phone ) {
        wp_send_json_error( __( 'No phone found for user.', 'awp' ) );
    }

    global $wpdb;
    $tn  = $wpdb->prefix . 'awp_instance_data';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$tn} WHERE instance_id = %s AND status = 'online'",
            $instance_id
        )
    );
    if ( ! $row ) {
        wp_send_json_error( __( 'No online instance found or invalid instance.', 'awp' ) );
    }

    $parsed_message = AWP_Message_Parser::parse_message_placeholders(
        $text,
        [],         
        0,         
        $user_id
    );

    $result = Wawp_Api_Url::send_message(
        $row->instance_id,
        $row->access_token,
        $phone,
        $parsed_message,
        /* $options */ [ 'type' => 'text' ]
    );

    if ( $result['status'] === 'success' ) {
        $this->log_admin_custom_message( $user_id, $phone, $parsed_message, $result, $row->instance_id, $row->access_token );
        wp_send_json_success( $result['message'] );
    }

    wp_send_json_error( __( 'API Error: ', 'awp' ) . $result['message'] );
}

    private function log_admin_custom_message($user_id, $phone, $parsed_message, $response_data, $instance_id = null, $access_token = null) {
    $user_info = get_user_by('id', $user_id);
    $customer_name = $user_info ? $user_info->display_name : 'User #'.$user_id;
    
    // Unwrap the response if it's nested
    $response_to_log = $response_data;
    if (isset($response_data['full_response'])) {
        $response_to_log = $response_data['full_response'];
    }

    $log_data = [
        'user_id'          => $user_id,
        'order_id'         => null,
        'customer_name'    => $customer_name,
        'sent_at'          => current_time('mysql'),
        'whatsapp_number'  => $phone,
        'message'          => $parsed_message,
        'image_attachment' => null,
        'message_type'     => 'Admin->User (Custom)',
        'wawp_status'      => $response_to_log, // Use the unwrapped response
        'resend_id'        => null,
        'instance_id'      => $instance_id,
        'access_token'     => $access_token
    ];
    $log_manager = new AWP_Log_Manager();
    $log_manager->log_notification($log_data);
}

    public function enqueue_send_message_assets($hook) {
    if ( ! in_array( $hook, array( 'users.php', 'profile.php' ), true ) ) {
        return;
    }

    wp_enqueue_style('emojionearea-css', AWP_PLUGIN_URL . 'assets/css/resources/emojionearea.min.css', [], '3.4.2');
    wp_enqueue_script('emojionearea-js', AWP_PLUGIN_URL . 'assets/js/resources/emojionearea.min.js', ['jquery'], '3.4.2', true);
    wp_enqueue_script('awp_send_msg_js', AWP_PLUGIN_URL . 'assets/js/awp-send-msg.js', ['jquery'], AWP_PLUGIN_VERSION, true);
    wp_enqueue_style('wawp-badges-css', AWP_PLUGIN_URL . 'assets/css/wawp-badges.css', [], AWP_PLUGIN_VERSION);

    $online_instances = $this->db_manager->get_all_db_instances();
    $online = [];
    if ($online_instances) {
        foreach ($online_instances as $inst) {
            if ($inst->status === 'online') {
                $online[] = [
                    'instance_id' => $inst->instance_id,
                    'name'        => $inst->name
                ];
            }
        }
    }

    wp_localize_script('awp_send_msg_js', 'awpSendMsgData', [
        'ajaxUrl'          => admin_url('admin-ajax.php'),
        'security'         => wp_create_nonce('awp_send_msg_nonce'),
        'onlineInstances'  => $online,
        'noOnlineInstance' => __('No online instances found.', 'awp')
    ]);
}

    public function enable_number_search_bar() {
        add_action('restrict_manage_users', [$this, 'render_number_search_field']);
        add_action('pre_user_query', [$this, 'filter_users_by_phone'], 999);
    }

    public function render_number_search_field($which) {
        if ('users.php' !== $GLOBALS['pagenow']) {
            return;
        }
        if ('top' !== $which) {
            return;
        }
        $current_value = isset($_GET['number_search']) ? esc_attr($_GET['number_search']) : '';
        echo '<input type="search" id="number_search_live" name="number_search" value="' . $current_value . '" placeholder="' . esc_attr__('Search Number...', 'awp') . '" style="margin-left:10px;" />';
        submit_button(__('Search Number', 'awp'), '', '', false);
    }

    public function filter_users_by_phone($user_search) {
        global $wpdb, $pagenow;
        if ('users.php' !== $pagenow) {
            return;
        }
        if (empty($_GET['number_search'])) {
            return;
        }
        $search_value   = trim($_GET['number_search']);
        $like_value     = '%' . $wpdb->esc_like($search_value) . '%';
        $awp_user_info_table = $this->db_manager->get_user_info_table_name();

        $user_search->query_from .= "
            LEFT JOIN $awp_user_info_table AS awp_info
                ON ( {$wpdb->users}.ID = awp_info.user_id )
            LEFT JOIN {$wpdb->usermeta} AS meta_billing
                ON ( {$wpdb->users}.ID = meta_billing.user_id 
                     AND meta_billing.meta_key = 'billing_phone' )
        ";
        $condition = $wpdb->prepare(
            "(awp_info.phone LIKE %s OR meta_billing.meta_value LIKE %s)",
            $like_value,
            $like_value
        );
        $user_search->query_where .= " AND $condition ";
        $user_search->query_where = str_replace(
            'ID =',
            "{$wpdb->users}.ID =",
            $user_search->query_where
        );
    }

    public function add_phone_number_column($columns) {
        if (!array_key_exists('whatsapp_verified', $columns)) {
            $columns['awp_phone_number'] = __('Phone Number', 'awp');
            return $columns;
        }
        $new_columns = [];
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'whatsapp_verified') {
                $new_columns['awp_phone_number'] = __('Phone Number', 'awp');
            }
        }
        return $new_columns;
    }

    public function populate_phone_number_column($value, $column_name, $user_id) {
        if ($column_name === 'awp_phone_number') {
            $user_info = $this->db_manager->get_user_info($user_id);
            $awp_phone = !empty($user_info->phone) ? $user_info->phone : '---';
            $woo_phone = get_user_meta($user_id, 'billing_phone', true)   ?: '---';
            $awp_phone_esc = esc_html($awp_phone);
            $woo_phone_esc = esc_html($woo_phone);

            $output  = '<div class="wawp-phone-admin" style="margin-bottom: 2px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 3.75C18 3.33579 17.6642 3 17.25 3H15.75C15.3358 3 15 3.33579 15 3.75V5.25C15 5.66421 15.3358 6 15.75 6H17.25C17.6642 6 18 5.66421 18 5.25V3.75Z" fill="#004349"/>
            <path d="M6 3.75C6 3.33579 6.33579 3 6.75 3H8.25C8.66421 3 9 3.33579 9 3.75V5.25C9 5.66421 8.66421 6 8.25 6H6.75C6.33579 6 6 5.66421 6 5.25V3.75Z" fill="#004349"/>
            <path d="M3 8.25C3 6.59315 4.34315 5.25 6 5.25H18C19.6569 5.25 21 6.59315 21 8.25V15.75C21 17.4069 19.6569 18.75 18 18.75H6C4.34315 18.75 3 17.4069 3 15.75V8.25Z" fill="#004349"/>
            <path d="M4.5 8.25C4.5 7.42157 5.17157 6.75 6 6.75H18C18.8284 6.75 19.5 7.42157 19.5 8.25V15.75C19.5 16.5784 18.8284 17.25 18 17.25H6C5.17157 17.25 4.5 16.5784 4.5 15.75V8.25Z" fill="#44FF87"/>
            <path d="M13.5 10.5C13.5 10.0858 13.8358 9.75 14.25 9.75H15.75C16.1642 9.75 16.5 10.0858 16.5 10.5V13.5C16.5 13.9142 16.1642 14.25 15.75 14.25H14.25C13.8358 14.25 13.5 13.9142 13.5 13.5V10.5Z" fill="#004349"/>
            <path d="M12 21C13.5 21 15.75 18.75 15.75 18.75H8.25C8.25 18.75 10.5 21 12 21Z" fill="#004349"/>
            <path d="M7.5 10.5C7.5 10.0858 7.83579 9.75 8.25 9.75H9.75C10.1642 9.75 10.5 10.0858 10.5 10.5V13.5C10.5 13.9142 10.1642 14.25 9.75 14.25H8.25C7.83579 14.25 7.5 13.9142 7.5 13.5V10.5Z" fill="#004349"/>
            </svg>
            ' . $awp_phone_esc . '</div>';

            $output .= '<div class="wawp-phone-admin">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_182_25)">
            <path d="M3.04193 5H20.9496C22.0828 5 23.0003 5.91738 23.0003 7.05073V13.8864C23.0003 15.0196 22.0829 15.9371 20.9496 15.9371H14.5277L15.4092 18.0957L11.5326 15.9371H3.05104C1.91778 15.9371 1.00031 15.0197 1.00031 13.8864V7.05073C0.991315 5.92641 1.90867 5 3.04193 5Z" fill="#7F54B3"/>
            <path d="M2.25274 6.86908C2.37794 6.69917 2.56575 6.60973 2.81615 6.59185C3.27225 6.55608 3.5316 6.77071 3.59421 7.23575C3.87144 9.10489 4.1755 10.6878 4.49749 11.9846L6.45601 8.25531C6.63487 7.91548 6.85845 7.73661 7.12674 7.71873C7.52024 7.6919 7.7617 7.9423 7.86007 8.46995C8.08365 9.65941 8.36983 10.67 8.70967 11.5285C8.94219 9.25688 9.33569 7.62037 9.89019 6.60975C10.0243 6.35934 10.2211 6.23414 10.4804 6.21625C10.6861 6.19836 10.8739 6.26097 11.0439 6.39511C11.2138 6.52926 11.3032 6.69918 11.3211 6.90487C11.33 7.06585 11.3032 7.19999 11.2317 7.33413C10.8829 7.97804 10.5967 9.06019 10.3642 10.5625C10.1406 12.0203 10.0601 13.1561 10.1138 13.9699C10.1317 14.1935 10.0959 14.3902 10.0065 14.5601C9.89916 14.7569 9.73818 14.8642 9.53249 14.8821C9.29997 14.9 9.05852 14.7926 8.82599 14.5512C7.99429 13.7016 7.33249 12.4317 6.8496 10.7414C6.26831 11.8861 5.83907 12.7447 5.56183 13.317C5.03419 14.3276 4.58704 14.8463 4.21141 14.8731C3.96994 14.891 3.76425 14.6853 3.58539 14.256C3.12929 13.0844 2.63741 10.8219 2.10976 7.46826C2.07398 7.23574 2.12764 7.03004 2.25284 6.86907L2.25274 6.86908ZM21.4718 8.27313C21.1499 7.70972 20.6759 7.36984 20.0409 7.23569C19.871 7.19992 19.71 7.18203 19.558 7.18203C18.6995 7.18203 18.0019 7.62919 17.4564 8.52352C16.9914 9.28368 16.7588 10.1244 16.7588 11.0454C16.7588 11.7341 16.9019 12.3243 17.1881 12.8162C17.5101 13.3796 17.9841 13.7195 18.619 13.8536C18.7889 13.8894 18.9499 13.9073 19.1019 13.9073C19.9694 13.9073 20.6669 13.4601 21.2035 12.5658C21.6685 11.7967 21.9011 10.956 21.9011 10.0349C21.91 9.33737 21.758 8.7561 21.4718 8.27313ZM20.345 10.7504C20.2198 11.3406 19.9962 11.7788 19.6653 12.074C19.406 12.3065 19.1645 12.4049 18.9409 12.3602C18.7263 12.3154 18.5474 12.1276 18.4133 11.7789C18.306 11.5016 18.2523 11.2244 18.2523 10.965C18.2523 10.7415 18.2702 10.5179 18.3149 10.3122C18.3954 9.94553 18.5474 9.5878 18.7889 9.24794C19.084 8.80973 19.397 8.63087 19.719 8.69348C19.9336 8.73819 20.1125 8.926 20.2466 9.27477C20.3539 9.55201 20.4076 9.82924 20.4076 10.0886C20.4076 10.3211 20.3897 10.5447 20.345 10.7504ZM15.8734 8.27313C15.5515 7.70972 15.0685 7.36984 14.4425 7.23569C14.2726 7.19992 14.1116 7.18203 13.9596 7.18203C13.1011 7.18203 12.4035 7.62919 11.858 8.52352C11.393 9.28368 11.1605 10.1244 11.1605 11.0454C11.1605 11.7341 11.3035 12.3243 11.5897 12.8162C11.9117 13.3796 12.3857 13.7195 13.0206 13.8536C13.1905 13.8894 13.3515 13.9073 13.5035 13.9073C14.371 13.9073 15.0685 13.4601 15.6051 12.5658C16.0701 11.7967 16.3027 10.956 16.3027 10.0349C16.3027 9.33737 16.1596 8.7561 15.8734 8.27313ZM14.7377 10.7504C14.6124 11.3406 14.3889 11.7788 14.058 12.074C13.7986 12.3065 13.5572 12.4049 13.3336 12.3602C13.119 12.3154 12.9401 12.1276 12.8059 11.7789C12.6986 11.5016 12.645 11.2244 12.645 10.965C12.645 10.7415 12.6629 10.5179 12.7076 10.3122C12.7881 9.94553 12.9401 9.5878 13.1816 9.24794C13.4767 8.80973 13.7897 8.63087 14.1117 8.69348C14.3263 8.73819 14.5052 8.926 14.6393 9.27477C14.7466 9.55201 14.8003 9.82924 14.8003 10.0886C14.8092 10.3211 14.7824 10.5447 14.7377 10.7504Z" fill="white"/>
            </g>
            <defs>
            <clipPath id="clip0_182_25">
            <rect width="22" height="13.1484" fill="white" transform="translate(1 5)"/>
            </clipPath>
            </defs>
            </svg>
            '  . $woo_phone_esc . '</div>';

            return $output;
        }
        return $value;
    }

    private function setup_phone_sync_hooks() {
        add_action('updated_user_meta', [$this, 'sync_awp_phone_to_woocommerce'], 10, 4);
        add_action('save_post_shop_order', [$this, 'sync_woocommerce_phone_to_awp'], 10, 3);
    }

    public function sync_awp_phone_to_woocommerce($meta_id, $user_id, $meta_key, $meta_value) {
        if ($meta_key === 'awp-user-phone') {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                update_user_meta($user_id, 'billing_phone', $meta_value);
            }
        }
    }

    public function sync_woocommerce_phone_to_awp($post_id, $post, $update) {
        if ($post->post_type === 'shop_order') {
            $order = wc_get_order($post_id);
            if ($order && is_a($order, 'WC_Order')) {
                $user_id = $order->get_user_id();
                $billing_phone = $order->get_billing_phone();
                if ($user_id && !empty($billing_phone)) {
                    update_user_meta($user_id, 'awp-user-phone', $billing_phone);
                }
            }
        }
    }

    public static function on_activate() {
        $page_title = __('Fast Login', 'awp');
        $page_check = get_page_by_title($page_title);
        $page_content = '[wawp-fast-login]';
        if (!$page_check) {
            wp_insert_post([
                'post_title'     => $page_title,
                'post_type'      => 'page',
                'post_content'   => $page_content,
                'post_status'    => 'publish',
                'comment_status' => 'closed',
            ]);
        }
    }

    public function setup_user_deletion_hook() {
        add_action('delete_user', [$this, 'delete_awp_user_info']);
    }

    public function delete_awp_user_info($user_id) {
        global $wpdb;
        $table = $this->db_manager->get_user_info_table_name();
        $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
    }

    public function render_fast_login_shortcode() {
        ob_start();
        ?>
        <div id="awp_otp_login" style="display: block;">
            <?php echo do_shortcode('[wawp_otp_login]'); ?>
            <p class="awp-switch-form">
                <?php echo esc_html__('You don’t have an account?', 'awp'); ?>
                <a href="#" id="signup_toggle"><?php echo esc_html__('Signup', 'awp'); ?></a>
            </p>
        </div>
        <div id="awp_signup_form" style="display: none;">
            <?php echo do_shortcode('[wawp_signup_form]'); ?>
            <p class="awp-switch-form">
                <?php echo esc_html__('Already have an account?', 'awp'); ?>
                <a href="#" id="login_toggle"><?php echo esc_html__('Login', 'awp'); ?></a>
            </p>
        </div>
        <script>
        (function($){
            $(document).ready(function(){
                $('#signup_toggle').on('click', function(e){
                    e.preventDefault();
                    $('#awp_otp_login').hide();
                    $('#awp_signup_form').show();
                });
                $('#login_toggle').on('click', function(e){
                    e.preventDefault();
                    $('#awp_signup_form').hide();
                    $('#awp_otp_login').show();
                });
            });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }

    public function add_whatsapp_verified_column($columns) {
        $columns['whatsapp_verified'] = __('WhatsApp Verified', 'awp');
        return $columns;
    }

    public function populate_whatsapp_verified_column($value, $column_name, $user_id) {
        if ($column_name === 'whatsapp_verified') {
            $whatsapp_verified = $this->db_manager->get_user_verification_status($user_id, 'whatsapp');
            if ($whatsapp_verified) {
                return '<div class="wawp-badge-admin verified">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(34,197,94,1)">
                <path d="M10.007 2.10377C8.60544 1.65006 7.08181 2.28116 6.41156 3.59306L5.60578 5.17023C5.51004 5.35763 5.35763 5.51004 5.17023 5.60578L3.59306 6.41156C2.28116 7.08181 1.65006 8.60544 2.10377 10.007L2.64923 11.692C2.71404 11.8922 2.71404 12.1078 2.64923 12.308L2.10377 13.993C1.65006 15.3946 2.28116 16.9182 3.59306 17.5885L5.17023 18.3942C5.35763 18.49 5.51004 18.6424 5.60578 18.8298L6.41156 20.407C7.08181 21.7189 8.60544 22.35 10.007 21.8963L11.692 21.3508C11.8922 21.286 12.1078 21.286 12.308 21.3508L13.993 21.8963C15.3946 22.35 16.9182 21.7189 17.5885 20.407L18.3942 18.8298C18.49 18.6424 18.6424 18.49 18.8298 18.3942L20.407 17.5885C21.7189 16.9182 22.35 15.3946 21.8963 13.993L21.3508 12.308C21.286 12.1078 21.286 11.8922 21.3508 11.692L21.8963 10.007C22.35 8.60544 21.7189 7.08181 20.407 6.41156L18.8298 5.60578C18.6424 5.51004 18.49 5.35763 18.3942 5.17023L17.5885 3.59306C16.9182 2.28116 15.3946 1.65006 13.993 2.10377L12.308 2.64923C12.1078 2.71403 11.8922 2.71404 11.692 2.64923L10.007 2.10377ZM6.75977 11.7573L8.17399 10.343L11.0024 13.1715L16.6593 7.51465L18.0735 8.92886L11.0024 15.9999L6.75977 11.7573Z"/>
                </svg>
                <span>'.esc_html__('Verified By Wawp', 'awp').'</span>
                </div>';
            } else {
                return '<div class="wawp-badge-admin not-verified">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(237,55,55,1)">
                <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"/>
                </svg>
                <span>'.esc_html__('Not Verified', 'awp').'</span>
                </div>';
            }
        }
        return $value;
    }

    public function show_user_phone_field($user) {
    if (!current_user_can('edit_user', $user->ID)) return;

    $user_info = $this->db_manager->get_user_info($user->ID);
    $user_phone = !empty($user_info->phone) ? $user_info->phone : '---';

    echo '<h3>' . esc_html__('Wawp User Information', 'awp') . '</h3>';
    echo '<table class="form-table">
            <tr>
              <th><label for="awp_user_phone">' . esc_html__('Phone', 'awp') . '</label></th>
              <td><input type="text" name="awp_user_phone" id="awp_user_phone" value="' . esc_attr($user_phone) . '" class="regular-text" /></td>
            </tr>
            <tr>
              <th>' . esc_html__('WhatsApp Verified', 'awp') . '</th>
              <td>';
    
    $whatsapp_verified = $this->db_manager->get_user_verification_status($user->ID, 'whatsapp');
    if ($whatsapp_verified) {
        echo '<div class="wawp-badge-admin verified">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(34,197,94,1)">
        <path d="M10.007 2.10377C8.60544 1.65006 7.08181 2.28116 6.41156 3.59306L5.60578 5.17023C5.51004 5.35763 5.35763 5.51004 5.17023 5.60578L3.59306 6.41156C2.28116 7.08181 1.65006 8.60544 2.10377 10.007L2.64923 11.692C2.71404 11.8922 2.71404 12.1078 2.64923 12.308L2.10377 13.993C1.65006 15.3946 2.28116 16.9182 3.59306 17.5885L5.17023 18.3942C5.35763 18.49 5.51004 18.6424 5.60578 18.8298L6.41156 20.407C7.08181 21.7189 8.60544 22.35 10.007 21.8963L11.692 21.3508C11.8922 21.286 12.1078 21.286 12.308 21.3508L13.993 21.8963C15.3946 22.35 16.9182 21.7189 17.5885 20.407L18.3942 18.8298C18.49 18.6424 18.6424 18.49 18.8298 18.3942L20.407 17.5885C21.7189 16.9182 22.35 15.3946 21.8963 13.993L21.3508 12.308C21.286 12.1078 21.286 11.8922 21.3508 11.692L21.8963 10.007C22.35 8.60544 21.7189 7.08181 20.407 6.41156L18.8298 5.60578C18.6424 5.51004 18.49 5.35763 18.3942 5.17023L17.5885 3.59306C16.9182 2.28116 15.3946 1.65006 13.993 2.10377L12.308 2.64923C12.1078 2.71403 11.8922 2.71404 11.692 2.64923L10.007 2.10377ZM6.75977 11.7573L8.17399 10.343L11.0024 13.1715L16.6593 7.51465L18.0735 8.92886L11.0024 15.9999L6.75977 11.7573Z"/>
        </svg>
        <span>' . esc_html__('Verified By Wawp', 'awp') . '</span>
        </div>';
    } else {
        echo '<div class="wawp-badge-admin not-verified">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="rgba(237,55,55,1)">
        <path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"/>
        </svg>
        <span>' . esc_html__('Not Verified', 'awp') . '</span>
        </div>';
    }
    echo '</td></tr></table>';
}

    public function sync_phone_fields($user_id) {
    $awp_phone = get_user_meta($user_id, 'awp-user-phone', true);
    $billing_phone = get_user_meta($user_id, 'billing_phone', true);

    if($awp_phone && !$billing_phone) {
        update_user_meta($user_id, 'billing_phone', $awp_phone);
    }
    if($billing_phone && !$awp_phone) {
        update_user_meta($user_id, 'awp-user-phone', $billing_phone);
    }

    $this->db_manager->update_user_phone($user_id, $awp_phone ?: $billing_phone);
}

    public function save_user_phone_field($user_id) {
    if (! current_user_can('edit_user', $user_id)) {
        return;
    }

    if ( isset($_POST['awp_user_phone']) ) {
        $phone = sanitize_text_field($_POST['awp_user_phone']);

        $previous_awp_phone  = get_user_meta($user_id, 'awp-user-phone', true);
        $previously_verified = $this->db_manager->get_user_verification_status($user_id, 'whatsapp');

        if ( !empty($phone) && ! $this->is_valid_phone($phone) ) {
            add_action('user_profile_update_errors', function($errors) {
                $errors->add('phone_error', __('Invalid phone number format. Use +1234567890', 'awp'));
            });
            return;
        }

        if (!empty($phone)) {
            $existing_users = get_users([
                'meta_key'   => 'awp-user-phone',
                'meta_value' => $phone,
                'exclude'    => [$user_id],
                'fields'     => 'ids',
                'number'     => 1,
            ]);
            if (!empty($existing_users)) {
                add_action('user_profile_update_errors', function($errors) {
                    $errors->add('phone_error', __('That phone number is already used by another account.', 'awp'));
                });
                return;
            }
        }

        update_user_meta($user_id, 'awp-user-phone', $phone);
        update_user_meta($user_id, 'billing_phone', $phone);

        $this->db_manager->update_user_phone($user_id, $phone);

        if ($previously_verified && $previous_awp_phone !== $phone) {
            $this->db_manager->update_user_verification($user_id, 'whatsapp', false);
        }
    }
}

    private function is_valid_phone($phone) {
        return preg_match('/^\+?[0-9]{7,15}$/', $phone);
    }
    
}