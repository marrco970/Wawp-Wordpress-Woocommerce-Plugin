<?php
if (!defined('ABSPATH')) exit;

class AWP_Database_Manager {
    public $tables = [];
    private $wpdb, $table_prefix;
    
    
       private function schema_map() : array {
        return [
            'instance_data' => [
                'name' => "VARCHAR(255) NOT NULL",
                'instance_id' => "VARCHAR(255) NOT NULL",
                'access_token' => "VARCHAR(255) NOT NULL",
                'status' => "VARCHAR(50) DEFAULT 'Unknown'",
                'message' => "TEXT DEFAULT NULL",
                'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            ],
            'notifications_log' => [
                'user_id' => "BIGINT(20) UNSIGNED DEFAULT NULL",
                'order_id' => "BIGINT(20) UNSIGNED DEFAULT NULL",
                'customer_name' => "VARCHAR(255) NOT NULL",
                'sent_at' => "DATETIME NOT NULL",
                'whatsapp_number' => "VARCHAR(20) NOT NULL",
                'message' => "TEXT NOT NULL",
                'image_attachment' => "VARCHAR(255) DEFAULT NULL",
                'message_type' => "VARCHAR(100) NOT NULL",
                'wawp_status' => "TEXT NOT NULL",
                'resend_id' => "BIGINT(20) UNSIGNED DEFAULT NULL",
                'instance_id' => "VARCHAR(255) DEFAULT NULL",
                'access_token' => "TEXT DEFAULT NULL",
                'delivery_status' => "TEXT DEFAULT NULL",
                'delivery_ack' => "INT DEFAULT NULL",
                'delivery_check_count' => "INT NOT NULL DEFAULT 0",
                'next_check_at' => "DATETIME DEFAULT NULL",
            ],
            'signup_settings' => [
                'selected_instance' => "INT DEFAULT 0",
                'enable_otp' => "TINYINT(1) DEFAULT 1",
                'otp_method' => "VARCHAR(50) DEFAULT 'whatsapp'",
                'otp_message' => "TEXT",
                'otp_message_email' => "TEXT",
                'field_order' => "VARCHAR(255) DEFAULT 'first_name,last_name,email,phone,password'",
                'signup_redirect_url' => "VARCHAR(255) DEFAULT ''",
                'signup_logo' => "VARCHAR(255) DEFAULT ''",
                'signup_title' => "VARCHAR(255) DEFAULT ''",
                'signup_description' => "TEXT DEFAULT ''",
                'signup_button_style' => "TEXT DEFAULT ''",
                'signup_custom_css' => "TEXT DEFAULT ''",
                'button_background_color' => "VARCHAR(7) DEFAULT '#0073aa'",
                'button_text_color' => "VARCHAR(7) DEFAULT '#ffffff'",
                'button_hover_background_color' => "VARCHAR(7) DEFAULT '#005177'",
                'button_hover_text_color' => "VARCHAR(7) DEFAULT '#ffffff'",
                'enable_strong_password' => "TINYINT(1) DEFAULT 0",
                'enable_password_reset' => "TINYINT(1) DEFAULT 1",
                'auto_login' => "TINYINT(1) DEFAULT 1",
                'first_name_enabled' => "TINYINT(1) DEFAULT 1",
                'first_name_required' => "TINYINT(1) DEFAULT 1",
                'last_name_enabled' => "TINYINT(1) DEFAULT 1",
                'last_name_required' => "TINYINT(1) DEFAULT 1",
                'email_enabled' => "TINYINT(1) DEFAULT 1",
                'email_required' => "TINYINT(1) DEFAULT 1",
                'phone_enabled' => "TINYINT(1) DEFAULT 1",
                'phone_required' => "TINYINT(1) DEFAULT 1",
                'password_enabled' => "TINYINT(1) DEFAULT 1",
                'password_required' => "TINYINT(1) DEFAULT 1",
                'custom_fields' => "LONGTEXT DEFAULT '[]'"
            ],
            'notification_rules' => [
                'rule_internal_id' => "VARCHAR(255) NOT NULL",
                'enabled' => "TINYINT(1) NOT NULL DEFAULT 1",
                'language_code' => "VARCHAR(20) NOT NULL",
                'trigger_key' => "VARCHAR(100) NOT NULL",
                'whatsapp_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'sender_type' => "VARCHAR(30) NOT NULL DEFAULT 'user_whatsapp'",
                'whatsapp_message' => "TEXT NOT NULL",
                'whatsapp_media_url' => "VARCHAR(255) NOT NULL",
                'email_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'email_subject' => "VARCHAR(255) NOT NULL",
                'email_body' => "LONGTEXT NOT NULL",
                'admin_user_ids' => "TEXT NOT NULL DEFAULT ''",
                'admin_whatsapp_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'admin_whatsapp_message' => "TEXT NOT NULL",
                'admin_whatsapp_media_url' => "VARCHAR(255) NOT NULL",
                'admin_email_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'admin_email_subject' => "VARCHAR(255) NOT NULL",
                'admin_email_body' => "LONGTEXT NOT NULL",
                'country_filter_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'product_filter_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'payment_filter_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
                'billing_countries_whitelist' => "TEXT NOT NULL DEFAULT ''",
                'billing_countries_blocklist' => "TEXT NOT NULL DEFAULT ''",
                'billing_countries' => "TEXT NOT NULL DEFAULT ''",
                'payment_gateways' => "TEXT NOT NULL DEFAULT ''",
                'product_ids_whitelist' => "TEXT NOT NULL DEFAULT ''",
                'product_ids_blocklist' => "TEXT NOT NULL DEFAULT ''",
                'send_product_image' => "TINYINT(1) NOT NULL DEFAULT 0",
                'send_timing' => "VARCHAR(20) NOT NULL DEFAULT 'instant'",
                'delay_value' => "INT(11) NOT NULL DEFAULT 0",
                'delay_unit' => "VARCHAR(10) NOT NULL DEFAULT 'minutes'",
                'last_updated' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
            ],
            'user_info' => [
                'user_id' => "BIGINT(20) UNSIGNED NOT NULL",
                'first_name' => "VARCHAR(255) NOT NULL",
                'last_name' => "VARCHAR(255) NOT NULL",
                'email' => "VARCHAR(255) NOT NULL",
                'phone' => "VARCHAR(20) NOT NULL",
                'password' => "VARCHAR(255) NOT NULL",
                'otp_verification_email'    => "BOOLEAN DEFAULT FALSE",
                'otp_verification_whatsapp' => "BOOLEAN DEFAULT FALSE",
                'whatsapp_verified'         => "ENUM('Verified','Not Verified') DEFAULT 'Not Verified'",
                'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
                'updated_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
            ],
            'campaigns' => [
                'name' => "VARCHAR(255) NOT NULL",
                'instances' => "TEXT",
                'role_ids' => "TEXT",
                'user_ids' => "TEXT",
                'external_numbers' => "TEXT",
                'external_emails' => "TEXT",
                'message' => "TEXT",
                'media_url' => "VARCHAR(255) DEFAULT ''",
                'min_whatsapp_interval' => "INT DEFAULT 60",
                'max_whatsapp_interval' => "INT DEFAULT 75",
                'min_email_interval' => "INT DEFAULT 30",
                'max_email_interval' => "INT DEFAULT 60",
                'start_datetime' => "DATETIME NULL",
                'repeat_type' => "VARCHAR(20) DEFAULT 'no'",
                'repeat_days' => "INT DEFAULT 0",
                'post_id' => "BIGINT UNSIGNED DEFAULT 0",
                'product_id' => "BIGINT UNSIGNED DEFAULT 0",
                'append_post' => "TINYINT(1) DEFAULT 0",
                'append_product' => "TINYINT(1) DEFAULT 0",
                'send_type' => "VARCHAR(20) DEFAULT 'text'",
                'total_count' => "INT DEFAULT 0",
                'processed_count' => "INT DEFAULT 0",
                'status' => "VARCHAR(20) DEFAULT 'saved'",
                'paused' => "TINYINT(1) DEFAULT 0",
                'next_run' => "DATETIME NULL",
                'woo_spent_over' => "DECIMAL(10,2) DEFAULT 0",
                'woo_orders_over' => "INT DEFAULT 0",
                'only_verified_phone' => "TINYINT(1) DEFAULT 0",
                'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
                'post_include_title' => "TINYINT(1) DEFAULT 0",
                'post_include_excerpt' => "TINYINT(1) DEFAULT 0",
                'post_include_link' => "TINYINT(1) DEFAULT 0",
                'post_include_image' => "TINYINT(1) DEFAULT 0",
                'product_include_title' => "TINYINT(1) DEFAULT 0",
                'product_include_excerpt' => "TINYINT(1) DEFAULT 0",
                'product_include_link' => "TINYINT(1) DEFAULT 0",
                'product_include_image' => "TINYINT(1) DEFAULT 0",
                'product_include_price' => "TINYINT(1) DEFAULT 0",
                'woo_ordered_products' => "TEXT",
                'woo_order_statuses' => "TEXT",
                'max_per_day' => "INT DEFAULT 0",
                'max_wa_per_day' => "INT DEFAULT 0",
                'max_email_per_day' => "INT DEFAULT 0",
                'billing_countries' => "TEXT",
                'wp_profile_languages' => "TEXT",
                'send_whatsapp' => "TINYINT(1) DEFAULT 1",
                'send_email' => "TINYINT(1) DEFAULT 0",
                'email_subject' => "VARCHAR(255) DEFAULT ''",
                'email_message' => "LONGTEXT",
            ],
            'campaigns_queue' => [
                'campaign_id' => "BIGINT UNSIGNED NOT NULL",
                'user_id' => "BIGINT UNSIGNED NOT NULL",
                'phone' => "VARCHAR(50) NOT NULL",
                'unique_code' => "VARCHAR(50) NOT NULL",
                'security_code' => "VARCHAR(50) NOT NULL",
                'status' => "VARCHAR(20) DEFAULT 'pending'",
                'sent_at' => "DATETIME NULL",
                'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            ],
            'email_log' => [
                'campaign_id' => "BIGINT UNSIGNED NOT NULL",
                'user_id' => "BIGINT UNSIGNED NOT NULL",
                'email_address' => "VARCHAR(255) NOT NULL",
                'subject' => "VARCHAR(255) NOT NULL",
                'message_body' => "LONGTEXT NOT NULL",
                'status' => "VARCHAR(20) DEFAULT 'pending'",
                'sent_at' => "DATETIME NULL",
                'response' => "TEXT",
                'first_opened_at' => "DATETIME NULL",
                'open_count' => "INT DEFAULT 0",
                'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
                'type' => "VARCHAR(50) NOT NULL DEFAULT 'campaign'",
            ],
            'blocked_numbers' => [
                'phone' => "VARCHAR(20) NOT NULL",
                'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            ]
        ];
    }
    
    
    public function ensure_all_columns() {
        foreach ( $this->schema_map() as $tbl_key => $columns ) {
            $table_name = isset( $this->tables[ $tbl_key ] )
                ? $this->tables[ $tbl_key ]
                : $tbl_key;
    
            foreach ( $columns as $col => $def ) {
                $this->maybe_add_column( $table_name, $col, $def );
    
                // ❏ OPTIONAL – back‑fill a default value after creation
                if ( 'signup_settings' === $tbl_key && 'custom_fields' === $col ) {
                    $this->wpdb->query(
                        $this->wpdb->prepare(
                            "UPDATE `$table_name` SET `$col` = %s WHERE `$col` IS NULL",
                            '[]'
                        )
                    );
                }
            }
        }
    }

    

    public function __construct() {
        global $wpdb;
        $this->wpdb         = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'awp_';
        $this->tables       = [
            'instance_data'         => $this->table_prefix . 'instance_data',
            'notifications_log'     => $this->table_prefix . 'notifications_log',
            'signup_settings'       => $this->table_prefix . 'signup_settings',
            'notification_rules'=> $this->table_prefix . 'notif_notification_rules',
            'user_info'             => $this->table_prefix . 'user_info',
            'campaigns'             => $wpdb->prefix . 'wawp_campaigns',
            'campaigns_queue'       => $wpdb->prefix . 'wawp_campaigns_queue',
            'email_log'   => $wpdb->prefix . 'wawp_email_log',
            'blocked_numbers' => $this->table_prefix . 'blocked_numbers',
        ];
    }

    public function get_log_table_name()    { return $this->tables['notifications_log']; }
    
    public function get_signup_settings_table_name()   { return $this->tables['signup_settings']; }

    public function get_user_info_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'awp_user_info';
    }

    public function create_all_tables() {
        $this->create_instance_table();
        $this->create_notif_languages_table();   
		$this->create_notif_global_table();     
        $this->create_notifications_log_table();
        $this->create_signup_settings_table();
        $this->create_user_info_table();
        $this->create_notification_rules_table(); 
        $this->create_campaigns_table();
        $this->create_campaigns_queue_table();
        $this->create_email_log_table();
        $this->create_blocked_numbers_table(); 
    }

    private function create_user_info_table() {
        $this->run_dbDelta($this->tables['user_info'], "(
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            password VARCHAR(255) NOT NULL,
            otp_verification_email BOOLEAN DEFAULT FALSE,
            otp_verification_whatsapp BOOLEAN DEFAULT FALSE,
            whatsapp_verified ENUM('Verified','Not Verified') DEFAULT 'Not Verified',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id)
        )");
    }
    
    private function create_notif_languages_table() {
    $table = $this->table_prefix . 'notif_languages';
    $this->run_dbDelta(
        $table,
        "(
            language_code   VARCHAR(20)  NOT NULL PRIMARY KEY,
            name            VARCHAR(255) NOT NULL,
            is_main         TINYINT(1)   NOT NULL DEFAULT 0,
            created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $row_count = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM $table" );

        if ( $row_count === 0 ) {
            $this->wpdb->insert(
                $table,
                [
                    'language_code' => 'en_US',
                    'name'          => 'English (English (US))',
                    'is_main'       => 1,
                ],
                [ '%s', '%s', '%d' ]
            );
        }
    }

	private function create_notif_global_table() {
		$this->run_dbDelta(
			$this->table_prefix . 'notif_global',
			"(
				id                       TINYINT(1)   NOT NULL PRIMARY KEY,   -- always “1”
				selected_instance_ids    TEXT         NOT NULL,
				created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
					                               ON UPDATE CURRENT_TIMESTAMP
			)"
		);
	}
    
    private function create_instance_table() {
        $this->run_dbDelta($this->tables['instance_data'], "(
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            instance_id VARCHAR(255) NOT NULL,
            access_token VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'Unknown',
            message TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

           private function create_notifications_log_table() {
    $tbl = $this->tables['notifications_log'];

    // NOTE: dbDelta() dislikes FOREIGN KEY lines inside CREATE.
    // We'll add the FK (optional) in a quiet follow-up step.
    $this->run_dbDelta($tbl, "(
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        order_id BIGINT(20) UNSIGNED DEFAULT NULL,
        customer_name VARCHAR(255) NOT NULL,
        sent_at DATETIME NOT NULL,
        whatsapp_number VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        image_attachment VARCHAR(255) DEFAULT NULL,
        message_type VARCHAR(100) NOT NULL,
        wawp_status TEXT NOT NULL,
        resend_id BIGINT(20) UNSIGNED DEFAULT NULL,
        instance_id VARCHAR(255) DEFAULT NULL,
        access_token TEXT DEFAULT NULL,
        delivery_status TEXT DEFAULT NULL,
        delivery_ack TINYINT(1) DEFAULT 0,
        delivery_check_count INT NOT NULL DEFAULT 0,
        next_check_at DATETIME DEFAULT NULL,

        PRIMARY KEY  (id),
        KEY idx_delivery_ack (delivery_ack),
        KEY idx_next_check_at (next_check_at),
        KEY idx_user_id (user_id),
        KEY idx_order_id (order_id),
        KEY idx_sent_at (sent_at),
        KEY idx_message_type (message_type),
        KEY idx_resend_id (resend_id)
    )");

    // OPTIONAL: try to add a self-referencing FK after creation.
    // Many hosts disable FKs or are on MyISAM, so ignore errors.
    $this->wpdb->suppress_errors(true);
    $this->wpdb->query("
        ALTER TABLE `$tbl`
        ADD CONSTRAINT `fk_{$this->wpdb->prefix}awp_notifications_log_resend`
        FOREIGN KEY (`resend_id`) REFERENCES `$tbl`(`id`) ON DELETE SET NULL
    ");
    $this->wpdb->suppress_errors(false);
}



    private function create_signup_settings_table() {
    $this->run_dbDelta($this->tables['signup_settings'], "(
        id INT AUTO_INCREMENT PRIMARY KEY,
        selected_instance INT DEFAULT 0,
        enable_otp TINYINT(1) DEFAULT 1,
        otp_method VARCHAR(50) DEFAULT 'whatsapp',
        otp_message TEXT,
        otp_message_email TEXT,
        field_order VARCHAR(255) DEFAULT 'first_name,last_name,email,phone,password',
        signup_redirect_url VARCHAR(255) DEFAULT '" . esc_sql(home_url()) . "',
        signup_logo VARCHAR(255) DEFAULT '',
        signup_title VARCHAR(255) DEFAULT '',
        signup_description TEXT DEFAULT '',
        signup_button_style TEXT DEFAULT '',
        signup_custom_css TEXT DEFAULT '',
        button_background_color VARCHAR(7) DEFAULT '#0073aa',
        button_text_color VARCHAR(7) DEFAULT '#ffffff',
        button_hover_background_color VARCHAR(7) DEFAULT '#005177',
        button_hover_text_color VARCHAR(7) DEFAULT '#ffffff',
        enable_strong_password TINYINT(1) DEFAULT 0,
        enable_password_reset TINYINT(1) DEFAULT 1,
        auto_login TINYINT(1) DEFAULT 1,
        first_name_enabled TINYINT(1) DEFAULT 1,
        first_name_required TINYINT(1) DEFAULT 1,
        last_name_enabled TINYINT(1) DEFAULT 1,
        last_name_required TINYINT(1) DEFAULT 1,
        email_enabled TINYINT(1) DEFAULT 1,
        email_required TINYINT(1) DEFAULT 1,
        phone_enabled TINYINT(1) DEFAULT 1,
        phone_required TINYINT(1) DEFAULT 1,
        password_enabled TINYINT(1) DEFAULT 1,
        password_required TINYINT(1) DEFAULT 1,
        custom_fields LONGTEXT DEFAULT '[]'
    )");
}

    private function run_dbDelta($table_name, $columns) {
        global $wpdb;
        $charset_collate = $this->wpdb->get_charset_collate();
        $sql             = "CREATE TABLE $table_name $columns $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_notification_rules_table() {
    $tbl = $this->tables['notification_rules'];
    $this->run_dbDelta($tbl, "(
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        rule_internal_id VARCHAR(255) NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        language_code VARCHAR(20) NOT NULL,
        trigger_key VARCHAR(100) NOT NULL,
        whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0,
        sender_type VARCHAR(30) NOT NULL DEFAULT 'user_whatsapp',
        whatsapp_message TEXT NOT NULL,
        whatsapp_media_url VARCHAR(255) NOT NULL,
        email_enabled TINYINT(1) NOT NULL DEFAULT 0,
        email_subject VARCHAR(255) NOT NULL,
        email_body LONGTEXT NOT NULL,
        admin_user_ids TEXT NOT NULL DEFAULT '',
        admin_whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0,
        admin_whatsapp_message TEXT NOT NULL,
        admin_whatsapp_media_url VARCHAR(255) NOT NULL,
        admin_email_enabled TINYINT(1) NOT NULL DEFAULT 0,
        admin_email_subject VARCHAR(255) NOT NULL,
        admin_email_body LONGTEXT NOT NULL,

        /* NEW flags so the INSERT works */
        country_filter_enabled  TINYINT(1) NOT NULL DEFAULT 0,
        product_filter_enabled  TINYINT(1) NOT NULL DEFAULT 0,
        payment_filter_enabled  TINYINT(1) NOT NULL DEFAULT 0,

        billing_countries_whitelist TEXT NOT NULL DEFAULT '',
        billing_countries_blocklist TEXT NOT NULL DEFAULT '',
        billing_countries TEXT NOT NULL DEFAULT '',
        payment_gateways TEXT NOT NULL DEFAULT '',
        product_ids_whitelist TEXT NOT NULL DEFAULT '',
        product_ids_blocklist TEXT NOT NULL DEFAULT '',
        send_product_image TINYINT(1) NOT NULL DEFAULT 0,
        send_timing VARCHAR(20) NOT NULL DEFAULT 'instant',
        delay_value INT(11) NOT NULL DEFAULT 0,
        delay_unit VARCHAR(10) NOT NULL DEFAULT 'minutes',
        last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        PRIMARY KEY (id),
        UNIQUE KEY rule_internal_id (rule_internal_id)
    )");
}

    private function create_blocked_numbers_table() {
        $this->run_dbDelta( $this->tables['blocked_numbers'], "(
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY phone (phone)
        )" );
    }
    
    public function get_signup_settings() {
        $table = $this->get_signup_settings_table_name();
        $row   = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", 1), ARRAY_A);
        if (!$row) {
            $default_settings = [
                'selected_instance'             => 0,
                'enable_otp'                    => 1,
                'otp_method'                    => 'whatsapp',
                'otp_message'                   => 'Your OTP code is: {{otp}}',
                'otp_message_email'             => 'Your OTP code is: {{otp}}',
                'field_order'                   => 'first_name,last_name,email,phone,password',
                'signup_redirect_url'           => home_url(),
                'signup_logo'                   => '',
                'signup_title'                  => 'Lets Get Started',
                'signup_description'            => 'Enter your details below to create your account.',
                'signup_button_style'           => '',
                'button_background_color'       => '#22c55e',
                'button_text_color'             => '#ffffff',
                'button_hover_background_color' => '#00c447',
                'button_hover_text_color'       => '#ffffff',
                'enable_strong_password'        => 0,
                'enable_password_reset'         => 1,
                'auto_login'                    => 1,
                'first_name_enabled'            => 1,
                'first_name_required'           => 1,
                'last_name_enabled'             => 1,
                'last_name_required'            => 1,
                'email_enabled'                 => 1,
                'email_required'                => 1,
                'phone_enabled'                 => 1,
                'phone_required'                => 1,
                'password_enabled'              => 1,
                'password_required'             => 0
            ];
            $this->wpdb->insert($table, $default_settings);
            $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", 1), ARRAY_A);
        }
        return $row;
    }
    
    private function create_campaigns_table() {
    $this->run_dbDelta( $this->tables['campaigns'], "(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          name VARCHAR(255) NOT NULL,
        
          instances TEXT,
          role_ids TEXT,
          user_ids TEXT,
          external_numbers TEXT,
          external_emails TEXT,
        
          message TEXT,
          media_url VARCHAR(255) DEFAULT '',
        
          min_whatsapp_interval INT DEFAULT 60,
          max_whatsapp_interval INT DEFAULT 75,
          min_email_interval INT DEFAULT 30,
          max_email_interval INT DEFAULT 60,
        
          start_datetime DATETIME NULL,
          repeat_type VARCHAR(20) DEFAULT 'no',
          repeat_days INT DEFAULT 0,
        
          post_id BIGINT UNSIGNED DEFAULT 0,
          product_id BIGINT UNSIGNED DEFAULT 0,
          append_post TINYINT(1) DEFAULT 0,
          append_product TINYINT(1) DEFAULT 0,
          send_type VARCHAR(20) DEFAULT 'text',
        
          total_count INT DEFAULT 0,
          processed_count INT DEFAULT 0,
          status VARCHAR(20) DEFAULT 'saved',
          paused TINYINT(1) DEFAULT 0,
          next_run DATETIME NULL,
        
          woo_spent_over DECIMAL(10,2) DEFAULT 0,
          woo_orders_over INT DEFAULT 0,
          only_verified_phone TINYINT(1) DEFAULT 0,
        
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        
          post_include_title   TINYINT(1) DEFAULT 0,
          post_include_excerpt TINYINT(1) DEFAULT 0,
          post_include_link    TINYINT(1) DEFAULT 0,
          post_include_image   TINYINT(1) DEFAULT 0,
        
          product_include_title   TINYINT(1) DEFAULT 0,
          product_include_excerpt TINYINT(1) DEFAULT 0,
          product_include_link    TINYINT(1) DEFAULT 0,
          product_include_image   TINYINT(1) DEFAULT 0,
          product_include_price   TINYINT(1) DEFAULT 0,
        
          woo_ordered_products TEXT,
          woo_order_statuses   TEXT,
        
          max_per_day INT DEFAULT 0,
          max_wa_per_day INT DEFAULT 0,
          max_email_per_day INT DEFAULT 0,
        
          billing_countries TEXT,
          wp_profile_languages TEXT,
        
          send_whatsapp TINYINT(1) DEFAULT 1,
          send_email    TINYINT(1) DEFAULT 0,
          email_subject VARCHAR(255) DEFAULT '',
          email_message LONGTEXT,
        
          PRIMARY KEY (id),
          KEY status (status),
          KEY next_run (next_run)
    )" );
}

    private function create_campaigns_queue_table() {
        $this->run_dbDelta( $this->tables['campaigns_queue'], "(
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            phone VARCHAR(50) NOT NULL,
            unique_code VARCHAR(50) NOT NULL,
            security_code VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            sent_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY (campaign_id), KEY (status)
        )" );
    }

    private function create_email_log_table() {
    $this->run_dbDelta( $this->tables['email_log'], "(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        email_address VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message_body LONGTEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        sent_at DATETIME NULL,
        response TEXT,
        first_opened_at DATETIME NULL,
        open_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        `type` VARCHAR(50) NOT NULL DEFAULT 'campaign',
        PRIMARY KEY (id), KEY (campaign_id), KEY (user_id), KEY (status), KEY(first_opened_at)
    )" );
}
    
    public function update_signup_settings($data) {
        if ( isset( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
        $data['custom_fields'] = wp_json_encode( $data['custom_fields'] );
    }
        $table = $this->get_signup_settings_table_name();
        $this->wpdb->update($table, $data, ['id' => 1]);
    }

    public function get_instance_by_id($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['instance_data']} WHERE id = %d AND status = 'online'",
                $id
            )
        );
    }

    public function insert_user_info($user_id, $first_name, $last_name, $email, $phone, $password) {
        $table = $this->get_user_info_table_name();
        $data  = [
            'user_id'                   => $user_id,
            'first_name'                => $first_name,
            'last_name'                 => $last_name,
            'email'                     => $email,
            'phone'                     => $phone,
            'password'                  => password_hash($password, PASSWORD_BCRYPT),
            'otp_verification_email'    => 0,
            'otp_verification_whatsapp' => 0,
            'whatsapp_verified'         => 'Not Verified',
        ];
        if (false === $this->wpdb->insert($table, $data)) {
            error_log('Failed to insert user info for user_id: ' . $user_id);
        }
    }

    public function update_user_verification($user_id, $method, $status) {
        global $wpdb;
        $table  = $this->get_user_info_table_name();
        $column = ($method === 'whatsapp') ? 'otp_verification_whatsapp' : 'otp_verification_email';
        $wpdb->update($table, [$column => $status ? 1 : 0], ['user_id' => $user_id], ['%d'], ['%d']);
        if ($method === 'whatsapp') {
            $wpdb->update($table, ['whatsapp_verified' => $status ? 'Verified' : 'Not Verified'], ['user_id' => $user_id], ['%s'], ['%d']);
        }
    }

    public function get_user_info($user_id) {
        $table = $this->get_user_info_table_name();
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d",
                $user_id
            )
        );
    }
    
    public function get_languages() {
	return $this->wpdb->get_results(
		"SELECT language_code AS code, name, is_main
		   FROM {$this->table_prefix}notif_languages
		 ORDER BY name ASC",
		ARRAY_A
	);
}

    public function upsert_language( $code, $name, $is_main = 0 ) {
	$this->wpdb->replace(
		$this->table_prefix . 'notif_languages',
		[
			'language_code' => $code,
			'name'          => $name,
			'is_main'       => $is_main ? 1 : 0,
		],
		[ '%s', '%s', '%d' ]
	);
}

    public function get_notif_global() {
	$row = $this->wpdb->get_row(
		"SELECT selected_instance_ids FROM {$this->table_prefix}notif_global WHERE id = 1",
		ARRAY_A
	);
	return $row ?: [ 'selected_instance_ids' => '' ];
}

    public function set_notif_global( $ids_csv ) {
	$this->wpdb->replace(
		$this->table_prefix . 'notif_global',
		[ 'id' => 1, 'selected_instance_ids' => $ids_csv ],
		[ '%d', '%s' ]
	);
}

    public function get_all_db_instances() {
        global $wpdb;
        $table = $this->tables['instance_data'];
        return $wpdb->get_results("SELECT * FROM $table");
    }

    public function update_user_phone($user_id, $phone) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'awp_user_info';
        $exists     = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", $user_id));
        if ($exists) {
            $wpdb->update($table_name, ['phone' => $phone], ['user_id' => $user_id], ['%s'], ['%d']);
        } else {
            $wpdb->insert($table_name, ['user_id' => $user_id, 'phone' => $phone], ['%d', '%s']);
        }
    }
    
private function maybe_add_column( $table, $column, $definition ) {
    global $wpdb;

    if ( preg_match('/\b(LONGTEXT|TEXT)\b/i', $definition) ) {
        $definition = preg_replace('/\s+DEFAULT\s+[^,\)]+/i', '', $definition);
    }

    if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) ) ) {
        $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `$column` $definition" );
    }
}


    public function get_user_verification_status($user_id, $method = 'whatsapp') {
        global $wpdb;
        $table  = $this->get_user_info_table_name();
        $column = ($method === 'whatsapp') ? 'otp_verification_whatsapp' : 'otp_verification_email';
        $sql    = $wpdb->prepare("SELECT $column FROM $table WHERE user_id = %d LIMIT 1", $user_id);
        $result = $wpdb->get_var($sql);
        return (bool) $result;
    }
    
    public function get_blocked_numbers() : array {
    return $this->wpdb->get_col(
        "SELECT phone FROM {$this->tables['blocked_numbers']}"
    );
}

    public function upsert_blocked_numbers( array $numbers ) {
        $tbl = $this->tables['blocked_numbers'];
        $this->wpdb->query( "TRUNCATE TABLE $tbl" );
        foreach ( array_unique( $numbers ) as $p ) {
            $this->wpdb->insert( $tbl, [ 'phone' => preg_replace('/\D/','', $p) ] );
        }
    }
    
    public function is_phone_blocked( string $phone ) : bool {
        $tbl  = $this->tables['blocked_numbers'];
        $num  = preg_replace( '/\D/', '', $phone );
        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare( "SELECT 1 FROM $tbl WHERE phone = %s", $num )
        );
    }
}
