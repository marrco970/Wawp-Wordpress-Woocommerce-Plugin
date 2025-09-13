<?php
    if (!defined('ABSPATH')) {
        exit;
    }
    
    class AWP_Enqueue_Scripts {
        public static function enqueue_admin_styles_scripts() {
                $screen = get_current_screen();
                if (!$screen) {
                    return;
                }
                if (strpos($screen->id, 'wawp') === false) {
                    return;
                }
                $section = isset($_GET['awp_section']) ? sanitize_key($_GET['awp_section']) : 'dashboard';
                wp_enqueue_style('wawp-style-css', AWP_PLUGIN_URL . 'assets/css/wawp-style.css', [], filemtime(AWP_PLUGIN_DIR . 'assets/css/wawp-style.css'));
                //wp_enqueue_style('awp-tour-css', AWP_PLUGIN_URL . 'assets/css/wawp-tour.css', [], filemtime(AWP_PLUGIN_DIR . 'assets/css/wawp-tour.css'));
                wp_enqueue_style('remix-icon', AWP_PLUGIN_URL . 'assets/css/resources/remixicon.css', [], '4.6.0');
                wp_enqueue_script('awp-responsive-js', AWP_PLUGIN_URL . 'assets/js/admin/awp-responsive.js', [], AWP_PLUGIN_VERSION, true);
                wp_enqueue_script('mrsb-auto-check', AWP_PLUGIN_URL . 'assets/js/admin/mrsb-auto-check.js', ['jquery'], '3.9', true);
                wp_localize_script('mrsb-auto-check', 'MRSBAutoCheck', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('mrsb_auto_check_nonce')
                ]);
        
                if (is_rtl()) {
                    wp_enqueue_style('awp-admin-rtl-css', AWP_PLUGIN_URL . 'assets/css/wawp-admin-rtl-style.css', [], filemtime(AWP_PLUGIN_DIR . 'assets/css/wawp-admin-rtl-style.css'));
                    
                    //wp_enqueue_style('awp-tour-rtl-css', AWP_PLUGIN_URL . 'assets/css/wawp-tour-rtl.css', [], filemtime(AWP_PLUGIN_DIR . 'assets/css/wawp-tour-rtl.css'));
                    
                }
        
                 if ($section === 'dashboard') {
                    wp_enqueue_style('awp-dashboard-css', AWP_PLUGIN_URL . 'assets/css/awp-dashboard.css', [], AWP_PLUGIN_VERSION);
                    wp_enqueue_script('awp-dashboard-js', AWP_PLUGIN_URL . 'assets/js/admin/awp-dashboard.js', ['jquery'], AWP_PLUGIN_VERSION, true);
                    //wp_enqueue_script('awp-tour-js', AWP_PLUGIN_URL . 'assets/js/tour/dashboard-tour.js', ['jquery'], AWP_PLUGIN_VERSION, true);
                    wp_localize_script('awp-dashboard-js', 'awpPopupText', [
                    'successMessage' => __('Option updated successfully.', 'awp'),
                    'errorPrefix'    => __('Error: ', 'awp'),
                    'requestFailed'  => __('Request failed: ', 'awp'),
                    'enabledLabel'   => __('Enabled', 'awp'),
                    'disabledLabel'  => __('Disabled', 'awp'),
                    'statusLabel'    => __('Status:', 'awp'),
                    'successGif'     => AWP_PLUGIN_URL . 'assets/img/success.gif',
                    'errorGif'       => AWP_PLUGIN_URL . 'assets/img/error.gif',
                ]);


                    
                    wp_localize_script('awp-tour-js', 'awpTourData', [
                        'steps' => [
                            [
                                'title' => __('Welcome to Wawp Plugin!', 'awp'),
                                'message' => __('Hi there! Thank you for using the Wawp plugin. We\'ll guide you through its key features to help you get started.', 'awp'),
                                'position' => 'center',
                            ],
                            [
                                'title' => __('Dashboard Overview', 'awp'),
                                'selector' => '.awp-dashboard-content',
                                'message' => __('This is your **main dashboard**. Here, you can quickly view key statistics and essential information about your plugin\'s performance.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Core Controls', 'awp'),
                                'selector' => '.awp-menu:nth-of-type(1)',
                                'message' => __('This section provides core controls. You can quickly verify that everything is running smoothly and as expected.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Feature Management', 'awp'),
                                'selector' => '.awp-menu:nth-of-type(2)',
                                'message' => __('Here, you can manage specific features. Easily enable or disable functionalities to tailor the plugin to your needs.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Activity Logs', 'awp'),
                                'selector' => '.awp-menu:nth-of-type(3)',
                                'message' => __('Monitor all outgoing communications from your site in this section. Check the status of messages (sent, read, failed) and resend if necessary.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Total WordPress Users', 'awp'),
                                'selector' => '.awp-card.total-users',
                                'message' => __('Gain insights into your user base. See the total number of WordPress users and how many of them have associated phone numbers.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('WhatsApp Chat Button', 'awp'),
                                'selector' => '.awp-card.whatsapp-chat-button',
                                'message' => __('Control the entire WhatsApp chat button functionality. Enable or disable it for both frontend and backend interactions.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Advanced Phone Field', 'awp'),
                                'selector' => '.awp-card.advanced-phone-field',
                                'message' => __('This feature, built on International Telephone Input, automatically adds country codes to your forms. Disabling it will prevent auto-correction of user phone numbers.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Append Unique/Message IDs', 'awp'),
                                'selector' => '.awp-card.message-ids',
                                'message' => __('Enhance message security by appending unique and message IDs to your outgoing communications. Disable this if you don\'t require this extra layer of detail.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Notifications', 'awp'),
                                'selector' => '.awp-card.notifications',
                                'message' => __('Set up automated notifications for various triggers, such as order completion, user logins, and more, keeping your users informed.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('OTP Verifications', 'awp'),
                                'selector' => '.awp-card.otp-verifications',
                                'message' => __('Manage the user experience for login, signup, and checkout processes using One-Time Password (OTP) verifications. Enable or disable specific OTP flows as needed.', 'awp'),
                                'position' => 'right',
                            ],
                            [
                                'title' => __('Bulk Campaigns', 'awp'),
                                'selector' => '.awp-card.bulk-campaigns',
                                'message' => __('Unleash the full potential of your site\'s user data. Create and manage bulk campaigns to re-engage existing customers or target external numbers, all from one centralized place.', 'awp'),
                                'position' => 'right',
                            ],
                        ],
                        'backText' => __('Back', 'awp'),
                        'nextText' => __('Next', 'awp'),
                        'finishTourText' => __('Finish Tour', 'awp'),
                        'isRTL' => is_rtl(), // Pass RTL status to JS
                    ]);
                }
                
                if ($section === 'system_info') {
                    wp_enqueue_style('awp-dashboard-css', AWP_PLUGIN_URL . 'assets/css/awp-dashboard.css', [], AWP_PLUGIN_VERSION);
                    
                }
                
                if ($section === 'settings') {
                    wp_enqueue_style('awp-admin-styles', AWP_PLUGIN_URL . 'assets/css/wadmin-styles.css', [], AWP_PLUGIN_VERSION);
                    wp_enqueue_script('awp-admin-scripts', AWP_PLUGIN_URL . 'assets/js/admin/awp-admin-countrycode.js', ['jquery'], AWP_PLUGIN_VERSION, true);
                    
                    wp_localize_script('awp-admin-scripts','awpAdminStrings',[
                        'enabledLabel'  => __('Enabled','awp'),
                        'disabledLabel' => __('Disabled','awp'),
                    ]);
                }
                
                if ( $section === 'notifications' ) {
                 wp_enqueue_style('emojionearea-css', AWP_PLUGIN_URL . 'assets/css/resources/emojionearea.min.css', [], '3.4.2');
                    wp_enqueue_script('emojionearea-js', AWP_PLUGIN_URL . 'assets/js/resources/emojionearea.min.js', ['jquery'], '3.4.2', true);
                    wp_enqueue_style('wawp-notif-admin-styles', AWP_PLUGIN_URL . 'assets/css/wawp-notif-admin-styles.css', [], AWP_PLUGIN_VERSION);
                    wp_enqueue_script('wawp-notif-admin-scripts', AWP_PLUGIN_URL . 'assets/js/admin/wawp-notif-admin-scripts.js', [ 'jquery', 'jquery-ui-sortable','wp-util' ], AWP_PLUGIN_VERSION, true);
                    $icon_base = AWP_PLUGIN_URL . 'assets/icons/';
                    wp_localize_script( 'wawp-notif-admin-scripts', 'wawpNotifData', [
                        'iconBaseUrl'               => esc_url( $icon_base ),
                        'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
                        'textSendUserWhatsApp'      => __( 'User WhatsApp', 'awp' ),
                        'textSendAdminWhatsApp'     => __( 'Admin WhatsApp', 'awp' ),
                        'textSendUserEmail'         => __( 'User Email', 'awp' ),
                        'textSendAdminEmail'        => __( 'Admin Email', 'awp' ),
                        'textWhen'                  => __( 'When', 'awp' ),
                        'textRule'                  => __( 'Rule', 'awp' ),
                        'nonceSearchUsers'          => wp_create_nonce( 'wawp_search_users_nonce' ),
                        'textWhatsAppTemplateSet'   => __( 'WhatsApp Template Set', 'awp' ),
                        'textWhatsAppNotSet'        => __( 'WhatsApp Not Set', 'awp' ),
                        'textEmailTemplateSet'      => __( 'Email Template Set', 'awp' ),
                        'textEmailNotSet'           => __( 'Email Not Set', 'awp' ),
                        'textAdminWhatsAppTemplateSet' => __( 'Admin WhatsApp Set', 'awp' ),
                        'textAdminWhatsAppNotSet'      => __( 'Admin WhatsApp Not Set', 'awp' ),
                        'textAdminEmailTemplateSet'    => __( 'Admin Email Set', 'awp' ),
                        'textAdminEmailNotSet'         => __( 'Admin Email Not Set', 'awp' ),
                         'textSendNow'   => __( 'send now',   'awp' ),
                        'textSendAfter' => __( 'send after', 'awp' ),
                        'delayUnits'    => [
                            'minutes' => __( 'minutes', 'awp' ),
                            'hours'   => __( 'hours',   'awp' ),
                            'days'    => __( 'days',    'awp' ),
                        ],
                        'lblWhatsApp'   => __( 'WhatsApp', 'awp' ),
                    'lblEmail'      => __( 'E‑mail',   'awp' ),
                       
                    ] );
        }
        
                if ($section === 'otp_messages') {
                    $settings = AWP_Signup::get_instance()->get_settings();
                    wp_enqueue_script('awp-otp-admin-script', AWP_PLUGIN_URL . 'assets/js/admin/awp-otp-admin.js', ['jquery', 'wp-color-picker', 'jquery-ui-sortable', 'lucide-icons'], AWP_PLUGIN_VERSION, true); 
                    wp_enqueue_script('lucide-icons', 'https://unpkg.com/lucide@0.514.0/dist/umd/lucide.min.js', [], null, true);
                    wp_enqueue_style('emojionearea-css', AWP_PLUGIN_URL . 'assets/css/resources/emojionearea.min.css', [], '3.4.2');
                    wp_enqueue_script('emojionearea-js', AWP_PLUGIN_URL . 'assets/js/resources/emojionearea.min.js', ['jquery'], '3.4.2', true);
                    wp_enqueue_style('codemirror-css', AWP_PLUGIN_URL . 'assets/css/resources/codemirror.min.css', [], '6.65.7');
                    wp_enqueue_script('codemirror-js', AWP_PLUGIN_URL . 'assets/js/resources/codemirror.min.js', ['jquery'], '6.65.7', true);
                    wp_enqueue_script('codemirror-css-mode', AWP_PLUGIN_URL . 'assets/js/resources/css.min.js', ['jquery'], '6.65.7', true);
                    wp_enqueue_style('bootstrap-icons', AWP_PLUGIN_URL . 'assets/css/resources/bootstrap-icons.css', [], '1.11.3');
                    wp_enqueue_style('bootstrap-select', AWP_PLUGIN_URL . 'assets/css/resources/bootstrap-select.min.css', [], '1.13.18');
                    wp_enqueue_script('bootstrap-select', AWP_PLUGIN_URL . 'assets/js/resources/bootstrap-select.min.js', ['jquery'], '1.13.18', true);
                    wp_enqueue_style('bootstrap-core', AWP_PLUGIN_URL . 'assets/css/resources/bootstrap.min.css', [], '5.2.3');
                    wp_enqueue_script('bootstrap-core', AWP_PLUGIN_URL . 'assets/js/resources/bootstrap4.bundle.min.js', ['jquery'], '4.6.2', true);
                    wp_enqueue_script('select2-js', AWP_PLUGIN_URL . 'assets/js/resources/select2.min.js', ['jquery'], '4.0.13', true);
                    wp_enqueue_style('select2', AWP_PLUGIN_URL . 'assets/css/resources/select2.min.css');
                    wp_enqueue_script('lucide-icons', 'https://unpkg.com/lucide@0.514.0/dist/umd/lucide.min.js', [], null, true);
                    wp_add_inline_script( 'select2-js', "jQuery(document).ready(function($){ $('.awp-page-selector').select2(); });" );
                    wp_enqueue_style('wp-color-picker');
                    wp_enqueue_script('wp-color-picker');
                    wp_enqueue_script('jquery-ui-sortable');
                    wp_enqueue_media();
                    wp_localize_script('awp-otp-admin-script', 'awpOtpAdminAjax', [
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('awp_admin_nonce'),
                        'nonce'    => wp_create_nonce('awp_otp_nonce'),
                        'default_logo_url' => AWP_PLUGIN_URL . 'assets/images/default-logo.png',
                        'all_roles' => wp_roles()->roles,
                        'custom_fields_config' => $settings['custom_fields'],
                        'strings' => [
                            'fieldRequired' => __('This field is required.', 'awp'),
                            'invalidMetaKey' => __('Meta key must be lowercase, contain only letters, numbers, and underscores.', 'awp'),
                            'duplicateMetaKey' => __('This meta key is already in use for a custom field.', 'awp'),
                            'metaKeyConflictStandard' => __('This meta key conflicts with a standard field (e.g., first_name, email). Please choose a different key.', 'awp'),
                            'optionsRequired' => __('At least one option is required for this field type.', 'awp'),
                            'confirmDeleteField' => __('Are you sure you want to delete this custom field? User data associated with this field will NOT be deleted.', 'awp'),
                            'edit' => __('Edit', 'awp'),
                            'delete' => __('Delete', 'awp'),
                            'enter_redirect_url' => __('Enter redirect URL', 'awp'),
                            'all_roles' => __('All Roles', 'awp'),
                            'failedToLoadLogo' => __('Failed to load logo image. Please check the URL or try uploading again.', 'awp'),
                            'primaryKey' => __("Primary Key Can't edit or deleted", 'awp'),
                            'all_roles'           => 'All Roles',
                            'remove'              => 'Remove',
                            'enter_redirect_url'  => 'Enter redirect URL',
                            'add_redirect_rule'   => 'Add Redirection Rule',
                            'upload_logo'         => 'Upload Logo',
                            'remove_logo'         => 'Remove Logo'
                        ],
                    ]);

                       
                }
        
                $campaign_sections = [ 'campaigns', 'campaigns_new', 'email_log' ];
                $is_campaign_edit  = ( $section === 'campaigns' && isset( $_GET['edit_id'] ) );
                if ( in_array( $section, $campaign_sections, true ) || $is_campaign_edit ) {
                    wp_enqueue_media();
                    wp_enqueue_editor();
                     wp_enqueue_style('emojionearea-css', AWP_PLUGIN_URL . 'assets/css/resources/emojionearea.min.css', [], '3.4.2');
                    wp_enqueue_script('emojionearea-js', AWP_PLUGIN_URL . 'assets/js/resources/emojionearea.min.js', ['jquery'], '3.4.2', true);
                    wp_enqueue_style('select2-css', AWP_PLUGIN_URL . 'assets/css/resources/select2.min.css', [], '4.0.13');
                    wp_enqueue_script( 'select2-js',
                        'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                        [ 'jquery' ], '4.1.0-rc.0', true );
                    wp_enqueue_style('bootstrap-icons', AWP_PLUGIN_URL . 'assets/css/resources/bootstrap-icons.css', [], '1.11.3');
                    wp_enqueue_script(
                        'wawp-campaigns-admin-js',
                        AWP_PLUGIN_URL . 'assets/js/admin/wawp-campaigns-admin.js',
                        [ 'jquery', 'select2-js', 'editor', 'quicktags', 'wp-tinymce' ],
                        AWP_PLUGIN_VERSION,
                        true
                    );
                    wp_localize_script(
                        'wawp-campaigns-admin-js',
                        'campExtData',
                        [
                            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                            'noncePreview'  => wp_create_nonce( 'camp_preview_nonce' ),
                            'nonceCalc'     => wp_create_nonce( 'camp_calc_recip_nonce' ),
                            'nonceRunRisky' => wp_create_nonce( 'camp_run_risky_nonce' ),
                            'isWooActive'   => class_exists( 'WooCommerce' ),
                        ]
                    );
                    wp_enqueue_style(
                        'wawp-campaigns-admin-css',
                        AWP_PLUGIN_URL . 'assets/css/wawp-campaigns-admin.css',
                        [],
                        AWP_PLUGIN_VERSION
                    );
                }
        
                if ($section === 'chat_widget') {
                    wp_enqueue_style('wp-color-picker');
                    wp_enqueue_script('wp-color-picker');
                    wp_enqueue_script('wp-color-picker-alpha', AWP_PLUGIN_URL . 'assets/js/resources/evol-colorpicker.min.js', ['wp-color-picker'], '3.4.4', true);
                    wp_enqueue_style('intlTelInput-css', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css');
                    wp_enqueue_script('intlTelInput-js', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js', ['jquery'], null, true);
                    wp_enqueue_media();
                    wp_enqueue_script('floating-whatsapp-button-wawp-chat-widget-admint', AWP_PLUGIN_URL.'assets/js/admin/wawp-chat-widget-admin.js', ['jquery','wp-color-picker','media-upload','thickbox','wp-color-picker-alpha'], AWP_PLUGIN_VERSION, true);
                    wp_enqueue_script('select2', AWP_PLUGIN_URL . 'assets/js/resources/select2.min.js', ['jquery'], '4.0.13', true);
                    wp_enqueue_style('select2', AWP_PLUGIN_URL . 'assets/css/resources/select2.min.css');
                       wp_localize_script('floating-whatsapp-button-wawp-chat-widget-admint','awp_ajax_obj',[
                    'ajax_url'=>admin_url('admin-ajax.php'),
                    'hide_powered_by' => (get_option('awp_disable_powered_by', 'no') === 'yes')
                ]);
                    
                    
                }

                if ( $section === 'instances' ) {
                
                
                    wp_enqueue_script(
                        'awp-admin-script',
                        AWP_PLUGIN_URL . 'assets/js/awp-scripts.js',
                        [ 'jquery' ],
                        AWP_PLUGIN_VERSION,
                        true
                    );
                    wp_enqueue_script(
                        'awp-block-tagify-init',
                        AWP_PLUGIN_URL . 'assets/js/admin/block-tagify-init.js',
                        [ 'jquery' ],
                        AWP_PLUGIN_VERSION,
                        true
                    );
                    wp_enqueue_style(
                        'tagify-css',
                        'https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css',
                        [],
                        '4.9.10'
                    );
                    wp_enqueue_script(
                        'tagify-js',
                        'https://cdn.jsdelivr.net/npm/@yaireo/tagify',
                        [],
                        '4.9.10',
                        true
                    );
                    wp_enqueue_style(
                        'intlTelInput-css',
                        'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css'
                    );
                    wp_enqueue_script(
                        'intlTelInput-js',
                        'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js',
                        [ 'jquery' ],
                        null,
                        true
                    );
                    wp_enqueue_script(
                        'intl-tel-input-utils',
                        'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.8/build/js/utils.js',
                        [],
                        '17.0.8',
                        true
                    );
                
                    wp_localize_script(
                        'awp-admin-script',
                        'awpBlockAjax',
                        [
                            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                            'nonce'   => wp_create_nonce( 'awp_block_list_nonce' ),
                        ]
                    );
                
                    wp_localize_script(
                        'awp-admin-script',
                        'awpAjax',
                        [
                            'ajax_url'        => admin_url( 'admin-ajax.php' ),
                            'nonce'           => wp_create_nonce( 'awp_nonce' ),
                
                            'unexpectedError' => __( 'Something went wrong. Please try again.', 'awp' ),
                            'noInstancesFound'=> __( 'No instances found.', 'awp' ),
                            'emptyFields'     => __( 'All fields are required.', 'awp' ),
                            'importedRows'    => __( 'Imported rows', 'awp' ),
                            'importFailed'    => __( 'Import failed:', 'awp' ),
                            'uploadError'     => __( 'Upload error.', 'awp' ),
                            'confirmDelete'   => __( 'Are you sure you want to delete this instance?', 'awp' ),
                
                            'edit'            => __( 'Edit',              'awp' ),
                            'delete'          => __( 'Delete',            'awp' ),
                            'sendTestMessage' => __( 'Send Test Message', 'awp' ),
                            'checkStatus'     => __( 'Check Connection',  'awp' ),
                
                            'statusUpdated'   => __( 'Status Updated', 'awp' ),
                            'online'          => __( 'Online',   'awp' ),
                            'offline'         => __( 'Offline',  'awp' ),
                            'checking'        => __( 'Checking', 'awp' ),
                            'unknown'         => __( 'Unknown',  'awp' ),
                            
                            'activeInstances' => __( 'Active Instances', 'awp' ),
                            
                
                            'rawResponseText'     => __( 'Raw API Response Preview:', 'awp' ),
                            'apiResponseDataText' => __( 'API Response Data:',        'awp' ),
                            'instanceIdText'      => __( 'Instance ID',               'awp' ),
                
                            'addManuallyButtonText' => __( 'Add New Manually', 'awp' ),
                
                            'qrCreatingInstance'     => __( 'Creating instance, please wait...', 'awp' ),
                            'qrFetchingNew'          => __( 'Fetching new QR code...',            'awp' ),
                            'qrScanInstruction'      => __( 'Scan this QR code with your WhatsApp app.', 'awp' ),
                            'qrFetchFailed'          => __( 'Failed to fetch QR code. Please try again or check API token.', 'awp' ),
                            'qrCreateFailed'         => __( 'Failed to create a new instance for QR scanning.', 'awp' ),
                            'qrPollingStart'         => __( 'Waiting for QR scan... Checking status periodically.', 'awp' ),
                            'qrPollingOnlineCheck'   => __( 'Instance connected! Finalizing setup...', 'awp' ),
                            'qrPollingOfflineCheck'  => __( 'Instance still offline. Will try fetching a new QR code shortly.', 'awp' ),
                            'qrPollingChecking'      => __( 'Checking instance connection status...', 'awp' ),
                            'qrPollingError'         => __( 'Error checking instance status. Retrying...', 'awp' ),
                            'qrInstanceOnline'       => __( 'WhatsApp successfully connected!', 'awp' ),
                            'qrInstanceOnlineNotice' => __( 'New WhatsApp number connected and saved.', 'awp' ),
                            'qrAddFailed'            => __( 'Failed to save the new instance locally.', 'awp' ),
                            'qrPageRefresh'          => __( 'Page will refresh shortly.', 'awp' ),
                            'qrNotAllowed'           => __( 'Cannot connect by QR due to an existing issue or limit.', 'awp' ),
                            'noApiAccessToken'       => __( 'API Access Token is missing. Cannot connect by QR. Please check WAWP Connector settings.', 'awp' ),
                            'qrMaxAttempts'          => __( 'Max polling attempts reached. Fetching a new QR code...', 'awp' ),
                            'qrAttemptingReconnect'  => __( 'Instance may already be active. Attempting to verify connection...', 'awp' ),
                
                            'apiAccessToken' => get_option( 'wawp_access_token', false ),
                        ]
                    );
                
                    wp_localize_script(
                        'awp-admin-script',
                        'awpCSVImport',
                        [
                            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                            'nonce'   => wp_create_nonce( 'import_csv_nonce' ),
                        ]
                    );
                }

        
                if ($section === 'activity_logs') {
                    wp_enqueue_style('datatables-css', AWP_PLUGIN_URL . 'assets/css/resources/dataTables.dataTables.min.css', [], '2.2.0');
                    wp_enqueue_script('datatables-js', AWP_PLUGIN_URL . 'assets/js/resources/dataTables.min.js', ['jquery'], '2.2.0', true);
                    wp_enqueue_script('awp-log-js', AWP_PLUGIN_URL . 'assets/js/admin/awp-log.js', ['jquery','datatables-js'], AWP_PLUGIN_VERSION, true);
                }
        
            }
            
        public static function enqueue_frontend_styles_scripts() {
                // Enqueue a single CSS file for all frontend pages
                wp_enqueue_style('wawp-frontend-css', AWP_PLUGIN_URL . 'assets/css/wawp-frontend.css', [], filemtime(AWP_PLUGIN_DIR . 'assets/css/wawp-frontend.css'));
            }
    
    }
    
    add_action('admin_enqueue_scripts', ['AWP_Enqueue_Scripts', 'enqueue_admin_styles_scripts']);
    add_action('wp_enqueue_scripts', ['AWP_Enqueue_Scripts', 'enqueue_frontend_styles_scripts']);


