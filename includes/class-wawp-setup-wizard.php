<?php
if (!defined('ABSPATH')) exit;

class Wawp_Setup_Wizard {
    private $total_issues = null;

    public function __construct() {
        if (!class_exists('AWP_Database_Manager')) {
            $db_manager_path = AWP_PLUGIN_DIR . 'includes/class-awp-database-manager.php';
            if (file_exists($db_manager_path)) {
                require_once $db_manager_path;
            }
        }
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_footer', [$this, 'maybe_render_wizard_stuff']);
        add_action('admin_init', [$this, 'maybe_handle_sync_actions']);
        add_action('wp_ajax_wawp_wizard_save_sender_settings', [$this, 'ajax_save_sender_settings']);
        add_action('wp_ajax_wawp_wizard_save_country', [$this, 'ajax_save_country']);
        add_action('wp_ajax_wawp_wizard_get_final_checks', [$this, 'ajax_get_final_checks']);
    }

    private function get_total_issues() {
        if ($this->total_issues === null) {
            if (!class_exists('AWP_System_Info')) {
                if (file_exists(AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php')) {
                    require_once AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php';
                    $system_info_instance = new AWP_System_Info();
                    $system_checker_data = $system_info_instance->gather_all_checks();
                    $this->total_issues = (int) $system_info_instance->count_total_issues($system_checker_data);
                } else {
                    $this->total_issues = 0;
                }
            }
        }
        return $this->total_issues;
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wawp') {
            return;
        }
        $is_sso   = $this->check_if_sso();
        $url_step = isset($_GET['wawp_popup_step']) ? absint($_GET['wawp_popup_step']) : 1;
        if ($is_sso && $url_step < 2) {
            $url_step = 2;
        } elseif (!$is_sso && $url_step > 1) {
            $url_step = 1;
        }
        if ($url_step > 6) {
            $url_step = 6;
        }
        wp_enqueue_style('wawp_wizard_css', AWP_PLUGIN_URL . 'assets/css/wawp-wizard.css', [], AWP_PLUGIN_VERSION);
        wp_enqueue_script('wawp_wizard_js', AWP_PLUGIN_URL . 'assets/js/wawp-wizard.js', ['jquery'], AWP_PLUGIN_VERSION, true);
        wp_enqueue_script('select2', AWP_PLUGIN_URL . 'assets/js/resources/select2.min.js', ['jquery'], '4.0.13', true);
        wp_enqueue_style('select2', AWP_PLUGIN_URL . 'assets/css/resources/select2.min.css');

        if (is_rtl()) {
            wp_enqueue_style('awp-admin-rtl-css', AWP_PLUGIN_URL . 'assets/css/wawp-admin-rtl-style.css', [], filemtime(AWP_PLUGIN_DIR . 'assets/css/wawp-admin-rtl-style.css'));
        }

        $user_data = get_transient('siteB_user_data');
        $is_qr_enabled = (!isset($user_data['external_qr']) || $user_data['external_qr'] !== 'Off');

        wp_localize_script('wawp_wizard_js', 'wawpWizardData', [
            'initialStep' => $url_step,
            'totalIssues' => $this->get_total_issues(),
            'isQrEnabled' => $is_qr_enabled
        ]);

        wp_localize_script('wawp_wizard_js', 'wawpWizardAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awp_nonce'),
            'save_sender_nonce' => wp_create_nonce('wawp_wizard_save_senders_action'),
            'apiAccessToken' => get_option('wawp_access_token', false),
            'debugMode' => defined('WP_DEBUG') && WP_DEBUG,
            'unexpectedError' => __('An unexpected error occurred.', 'awp'),
            'creating'  => __( 'Creating instance…', 'awp' ),
            'fetching'  => __( 'Fetching QR code…', 'awp' ),
            'scanHint'  => __( 'Scan this code with WhatsApp on your phone.', 'awp' ),
            'connected' => __( 'Connected ✔ – saving…', 'awp' ),
            'saved'     => __( 'Number saved – continuing to next step…', 'awp' ),
            'error'     => __( 'Something went wrong, please try again.', 'awp' ),
            'settingsSaved' => __('Settings saved successfully!', 'awp'),
            'countrySaved' => __('Default country saved.', 'awp'),
        ]);
    }

    public function maybe_render_wizard_stuff() {
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_' . AWP_MAIN_MENU_SLUG) {
            return;
        }
        $this->render_wizard_modal();
    }

    public function render_wizard_modal() {
        $total_issues = $this->get_total_issues();
        $system_info_page_url = admin_url('admin.php?page=wawp&awp_section=system_info');

        $token      = get_option('mysso_token');
        $user_data  = get_transient('siteB_user_data');
        $is_sso     = (!empty($token) && !empty($user_data) && isset($user_data['user_email']));
        $sso_email  = $is_sso ? $user_data['user_email'] : '';
        $is_qr_enabled = (!isset($user_data['external_qr']) || $user_data['external_qr'] !== 'Off');

        $current_page    = $this->get_current_admin_url();
        $redirect_back   = add_query_arg('wawp_popup_step', '2', $current_page);
        $login_url       = add_query_arg(['action' => 'siteA_sso', 'redirect_to' => urlencode($redirect_back)], 'https://wawp.net/');
        $disconnect_link = add_query_arg(['mysso_logout' => '1', 'redirect_to' => urlencode($current_page)], admin_url());

        $progress_steps = '<div class="wawp-progress-container">
                                <div class="wawp-progress-step step-1"></div>
                                <div class="wawp-progress-step step-2"></div>
                                <div class="wawp-progress-step step-3" ' . ($total_issues === 0 ? 'style="display: none;"' : '') . '></div>
                                <div class="wawp-progress-step step-4" ' . (!$is_qr_enabled ? 'style="display: none;"' : '') . '></div>
                                <div class="wawp-progress-step step-5"></div>
                            </div>';
        ?>
        <div id="wawp-wizard-modal">
            <script>
                (function() {
                    const params = new URLSearchParams(window.location.search);
                    const step = params.get('wawp_popup_step');
                    const isQrDisabled = <?php echo json_encode(!$is_qr_enabled); ?>;
                    if (step === '4' && isQrDisabled) {
                        params.set('wawp_popup_step', '5');
                        window.history.replaceState({}, '', `${window.location.pathname}?${params}`);
                    }
                })();
            </script>
            <div id="wawp-wizard-content">
                <button id="wawp-wizard-close"><i class="ri-close-line"></i></button>
                
                <!-- Step 1: Welcome -->
                <div class="wawp-step" id="wawp-step-1">
                    <div style="margin: auto; text-align: center;">
                        <img src="<?php echo esc_url(AWP_PLUGIN_URL . 'assets/img/wawp-logo.png'); ?>" alt="<?php esc_attr_e('Wawp Logo', 'awp'); ?>" style="margin-bottom: 24px;" />
                        <h1><?php esc_html_e('Welcome to Wawp!', 'awp'); ?></h1>
                        <p><?php esc_html_e('Our quick setup wizard makes getting started easy. It takes just seconds to configure basic settings. This guide is optional - let\'s begin!', 'awp'); ?></p>
                        <br>
                        <br>
                        <?php if (class_exists('Wawp_Connector')): ?>
                            <?php if (!$is_sso): ?>
                                <div style="margin: 24px 0; display: flex; align-items: stretch; gap: .625rem;">
                                    <a href="<?php echo esc_url($login_url); ?>" style="justify-content: center; width:65%;font-style: normal;font-size: 16px;font-weight: 600;padding: 16px 24px;background: #002626;color: #fff;border-radius: 8px;" class="awp-save"><?php esc_html_e('Create Free Account', 'awp'); ?></a>
                                    <a href="<?php echo esc_url($login_url); ?>" class="hint-btn" style="justify-content: center;font-size: 16px;font-weight: 600;padding: 16px 24px;border-radius: 8px;background-color: var(--wawp-green);color: #fff;width: 35%;"><?php esc_html_e('Login', 'awp'); ?></a>
                                </div>
                                <small style="color:#999;"><?php esc_html_e('By continuing, you agree to the', 'awp'); ?> <a href="https://wawp.net/terms-of-services/" target="_blank"><?php esc_html_e('Terms of Service', 'awp'); ?></a> <?php esc_html_e('and', 'awp'); ?> <a href="https://wawp.net/privacy-policy/" target="_blank"><?php esc_html_e('Privacy Policy', 'awp'); ?></a>.</small>
                            <?php else: ?>
                                <a href="#" class="wawp-btn-next wawp-next-btn awp-save" style="justify-content: center;font-size: 16px;font-weight: 600;padding: 16px 24px;border-radius: 8px;background-color: var(--wawp-green);color: #fff;width: 35%;"><?php esc_html_e("Let's do it", 'awp'); ?> <i class="ri-arrow-right-line"></i></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="padding:10px; background:#f8d7da; color:#721c24; border-radius:4px;"><?php esc_html_e('Wawp Connector plugin not active. Please install/activate it first.', 'awp'); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Connect -->
                <div class="wawp-step" id="wawp-step-2">
                    <div class="step-content">
                        <div>
                            <p class="step-txt"><?php esc_html_e('Step 2', 'awp'); ?></p>
                            <h1><?php esc_html_e("Let's connect your site.", 'awp'); ?></h1>
                            <p><?php esc_html_e("Once that's done, you'll have access to all the features of Wawp.", 'awp'); ?></p>
                        </div>
                        <?php if ($is_sso): ?>
                            <div class="flex align-center">
                                <img src="<?php echo esc_url(AWP_PLUGIN_URL . 'assets/img/Wawp.webp'); ?>" alt="<?php esc_attr_e('Wawp Logo', 'awp'); ?>" />
                                <div class="flex align-center" style="position: relative; width: 200px; justify-content: center;">
                                    <i class="ri-checkbox-circle-line" style="font-size: 28px !important; color: var(--wawp-green); background: #fff;"></i>
                                    <hr style="border-top: 2px dashed #b3b3b3; position: absolute; width: 100%; z-index: -1;">
                                </div>
                                <img src="<?php echo esc_url(AWP_PLUGIN_URL . 'assets/img/user.webp'); ?>" alt="<?php esc_attr_e('User avatar', 'awp'); ?>" style="border-radius: 999px;" />
                            </div>
                            <p><?php esc_html_e("You'll connect your", 'awp'); ?> <b style="color: var(--heading);"><?php echo esc_html($sso_email); ?></b> <?php esc_html_e('account on Wawp to this site.', 'awp'); ?></p>
                            <p style="font-size: 14px !important;"><?php esc_html_e('Not You?', 'awp'); ?> <a href="<?php echo esc_url($disconnect_link); ?>"><?php esc_html_e('Use a different account.', 'awp'); ?></a></p>
                        <?php else: ?>
                            <p style="color:red;"><?php esc_html_e('Not logged in. Please go back and log in first.', 'awp'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wawp-step-footer">
                        <a href="#" class="wawp-btn-back wawp-prev-btn"><?php esc_html_e('Back', 'awp'); ?></a>
                        <?php echo $progress_steps; ?>
                        <a href="#" class="wawp-btn-next wawp-next-btn"><?php esc_html_e('Continue', 'awp'); ?> <i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>

                <!-- Step 3: System Health -->
                <div class="wawp-step" id="wawp-step-3">
                    <div class="step-content">
                        <div>
                            <p class="step-txt"><?php esc_html_e('Step 3', 'awp'); ?></p>
                            <h1><?php esc_html_e('Syncing & System Health', 'awp'); ?></h1>
                            <p><?php esc_html_e('Sync your data and check for any system issues to get the most out of Wawp.', 'awp'); ?></p>
                        </div>
                        <?php if ($is_sso): ?>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <div class="sync-card">
                                    <p style="font-size: 14px !important; text-align: start; max-width: 48ch;margin: 0 !important;"><?php esc_html_e('Sync your WordPress website with the Wawp plugin to enable users login and checkout via WhatsApp OTP and more.', 'awp'); ?></p>
                                    <form method="post" target="wawp-sync-frame" style="display:inline-block;">
                                        <?php wp_nonce_field('awp_sync_users_action', 'awp_sync_users_nonce'); ?>
                                        <button type="submit" name="awp_sync_users" class="awp-save awp-btn secondary"><i class="ri-user-received-line"></i> <?php esc_html_e('Sync Users', 'awp'); ?></button>
                                    </form>
                                </div>
                                <div class="sync-card">
                                    <div style="text-align: start; width: 100%;">
                                        <h4 style="margin: 0 0 4px; color: #333;"><?php esc_html_e('System Health Check', 'awp'); ?></h4>
                                        <p style="font-size: 14px !important; margin: 0 !important; color: <?php echo $total_issues > 0 ? '#c00' : '#28a745'; ?>; font-weight: bold;"><?php printf(esc_html__('Total issues that need fixing: %d', 'awp'), (int) $total_issues); ?></p>
                                    </div>
                                    <div style="display: flex; gap: 12px;">
                                        <form method="post" target="wawp-sync-frame" style="display:inline-block;">
                                            <?php wp_nonce_field('awp_repair_all_wizard_action', 'awp_repair_all_wizard_nonce'); ?>
                                            <button type="submit" name="awp_repair_all_wizard" class="awp-save awp-btn primary"><i class="ri-tools-fill"></i> <?php esc_html_e('Repair All Issues', 'awp'); ?></button>
                                        </form>
                                        <a href="<?php echo esc_url($system_info_page_url); ?>" target="_blank" class="awp-save awp-btn secondary"><i class="ri-information-line"></i> <?php esc_html_e('Check System Info', 'awp'); ?></a>
                                    </div>
                                </div>
                                <div id="wawp-sync-logs" style="max-width:660px; width: 660px;"></div>
                                <iframe id="wawp-sync-frame" name="wawp-sync-frame" style="display:none;"></iframe>
                            </div>
                        <?php else: ?>
                            <p style="color:red;"><?php esc_html_e('Not logged in. Go back and log in first.', 'awp'); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wawp-step-footer">
                        <a href="#" class="wawp-btn-back wawp-prev-btn"><?php esc_html_e('Back', 'awp'); ?></a>
                        <?php echo $progress_steps; ?>
                        <a href="#" class="wawp-btn-next wawp-next-btn"><?php esc_html_e('Continue', 'awp'); ?> <i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>

                <!-- Step 4: Scan QR -->
                <div class="wawp-step" id="wawp-step-4">
                    <div class="step-content" style="text-align:center;">
                        <p class="step-txt"><?php esc_html_e('Step 4', 'awp'); ?></p>
                        <h1><?php esc_html_e('Scan QR Code to connect WhatsApp', 'awp'); ?></h1>
                        <p><?php esc_html_e('Use your phone to scan the code below. Once the number is online it will be saved automatically.', 'awp'); ?></p>
                        <div id="awp-qr-wizard-status" style="margin:20px 0;">
                            <div class="awp-spinner large"></div>
                        </div>
                        <img id="awp-qr-wizard-img" alt="<?php esc_attr_e('WhatsApp QR Code', 'awp'); ?>" style="display:none;max-width:260px;border:1px solid #ddd;" />
                        <p id="awp-qr-wizard-msg" style="font-size:14px;margin-top:8px;"></p>
                    </div>
                    <div class="wawp-step-footer">
                        <a href="#" class="wawp-btn-back wawp-prev-btn"><?php esc_html_e('Back', 'awp'); ?></a>
                        <?php echo $progress_steps; ?>
                        <a href="#" class="wawp-btn-next wawp-next-btn"><?php esc_html_e('Continue', 'awp'); ?> <i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
                
                <!-- Step 5: Configure Senders -->
                <div class="wawp-step" id="wawp-step-5">
                    <div class="step-content">
                        <div>
                            <p class="step-txt"><?php esc_html_e('Step 5', 'awp'); ?></p>
                            <h1><?php esc_html_e('Configure Senders', 'awp'); ?></h1>
                            <p><?php esc_html_e('Assign your connected numbers to different features.', 'awp'); ?></p>
                            <p class="wawp-wizard-sub-link" style="font-size: 14px; margin-top: -10px; margin-bottom: 20px;"><?php 
                                printf(
                                    __('To add or manage your connected WhatsApp & Email numbers, please visit the %sSender Settings%s page.', 'awp'),
                                    '<a href="' . esc_url(admin_url('admin.php?page=wawp&awp_section=instances')) . '" target="_blank">',
                                    '</a>'
                                ); 
                            ?></p>
                        </div>
                        
                        <?php $this->render_country_code_form_wizard(); ?>
                        <?php $this->render_otp_senders_form_wizard(); ?>

                    </div>
                    <div class="wawp-step-footer">
                        <a href="#" class="wawp-btn-back wawp-prev-btn"><?php esc_html_e('Back', 'awp'); ?></a>
                        <?php echo $progress_steps; ?>
                        <a href="#" class="wawp-btn-next wawp-next-btn"><?php esc_html_e('Continue', 'awp'); ?> <i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
                
                <!-- Step 6: Final Checks -->
                <div class="wawp-step" id="wawp-step-6">
                    <div class="step-content" style="text-align:center;">
                        <div id="wawp-final-checks">
                            <h1><?php esc_html_e('Finalizing Setup...', 'awp'); ?></h1>
                            <p><?php esc_html_e('Running final checks on your system configuration.', 'awp'); ?></p>
                            <div class="wawp-progress-bar-container">
                                <div class="wawp-progress-bar"></div>
                            </div>
                            <ul class="wawp-checklist">
                                <li id="check-db"><span class="icon loading"></span> <?php esc_html_e('Database Tables Status', 'awp'); ?></li>
                                <li id="check-cron-status"><span class="icon loading"></span> <?php esc_html_e('WP-Cron Status', 'awp'); ?></li>
                                <li id="check-cron-const"><span class="icon loading"></span> <?php esc_html_e('WP-Cron Constant', 'awp'); ?></li>
                                <li id="check-instances"><span class="icon loading"></span> <?php esc_html_e('Online WhatsApp Instances', 'awp'); ?></li>
                                <li id="check-issues"><span class="icon loading"></span> <?php esc_html_e('System Issues', 'awp'); ?></li>
                            </ul>
                            <div id="wawp-final-issues-notice" style="display:none;">
                                <p style="color:red;"><?php esc_html_e('Your setup has issues that need attention.', 'awp'); ?></p>
                                <div class="buttons-sys-error">
                                <a href="<?php echo esc_url($system_info_page_url); ?>" class="awp-btn primary" target="_blank"><?php esc_html_e('Fix Issues Now', 'awp'); ?></a>
                                <button id="wawp-rerun-checks-btn" class="awp-btn secondary"><i class="ri-refresh-line"></i> <?php esc_html_e('Re-run Checks', 'awp'); ?></button>
                                </div>
                            </div>
                        </div>
                        <div id="wawp-finish-screen" style="display:none;">
                            <h1>🎉 <?php esc_html_e('Welcome aboard!', 'awp'); ?></h1>
                            <p><?php esc_html_e('Your Wawp setup is complete. What would you like to do next?', 'awp'); ?></p>
                            <div class="wawp-finish-actions">
                                <h4><?php esc_html_e('What\'s Next?', 'awp'); ?></h4>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wawp&awp_section=notifications')); ?>" class="awp-btn"><?php esc_html_e('Setup notification for Login and orders', 'awp'); ?></a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wawp&awp_section=otp_messages')); ?>" class="awp-btn"><?php esc_html_e('Setup OTP for Login/Signup/Checkout', 'awp'); ?></a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wawp&awp_section=chat_widget')); ?>" class="awp-btn"><?php esc_html_e('Setup Whatsapp button on Front end', 'awp'); ?></a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wawp&awp_section=dashboard')); ?>" class="awp-btn"><?php esc_html_e('Enable/disable feature As you need', 'awp'); ?></a>
                                <button id="wawp-recheck-status-btn" class="awp-btn"><?php esc_html_e('Re-check system status', 'awp'); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="wawp-step-footer">
                        <a href="#" class="wawp-btn-back wawp-prev-btn"><?php esc_html_e('Back', 'awp'); ?></a>
                        <?php echo $progress_steps; ?>
                        <a href="#" id="wawp-finish-button" class="wawp-next-btn"><?php esc_html_e('Finish', 'awp'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function maybe_handle_sync_actions() {
        if (isset($_POST['awp_sync_users']) && check_admin_referer('awp_sync_users_action','awp_sync_users_nonce')) {
            if (!current_user_can('manage_options')) return;
            require_once AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php';
            $system_info = new AWP_System_Info();
            $system_info->handle_sync_users();
            $logs_html = $system_info->sync_users_html;
            if (!$logs_html) {
                $logs_html = '<div style=\"background:#fff;padding:10px;border:1px solid #ddd;\">Users sync completed (no logs).</div>';
            }
            list($successCount, $errorCount) = $this->countSuccessErrors($logs_html);
            $headingText = ($errorCount === 0) ? 'User Sync Logs: Users synced successfully!' : 'User Sync Logs: Some errors occurred…';
            $headingText .= ' ('.$successCount.' success, '.$errorCount.' errors)';
            $logs_html = $this->wrap_in_accordion($logs_html, $headingText);
            $this->iframe_response($logs_html);
        }
    }

    private function iframe_response($logs_html) {
        @header('Content-Type: text/html; charset=UTF-8');
        ?>
        <!DOCTYPE html>
        <html><head><meta charset="utf-8"><title>Wawp Sync Response</title></head>
        <body>
        <?php $logs_html_safed = wp_kses_post($logs_html); ?>
        <script>
        if(window.parent && typeof window.parent.updateWawpLogs === 'function'){
           window.parent.updateWawpLogs(`<?php echo str_replace('`','\\`',$logs_html_safed); ?>`);
        }
        </script>
        </body></html>
        <?php
        exit;
    }

    private function wrap_in_accordion($content, $heading) {
        return '
          <div class="wawp-accordion">
            <div class="wawp-accordion-header">
              <span>'.esc_html($heading).'</span>
              <button class="wawp-log-toggle">Show logs</button>
            </div>
            <div class="wawp-accordion-content">
              '.$content.'
            </div>
          </div>';
    }

    private function countSuccessErrors($html) {
        $lc = strtolower($html);
        $errorCount = substr_count($lc, 'error');
        $successCount = substr_count($lc, 'success');
        return [$successCount, $errorCount];
    }
    
    private function get_online_instances() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'awp_instance_data';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status=%s", 'online'
        ));
    }

    private function get_toggle($option) {
        $val = get_option($option, '');
        if ($val === '') {
            return true;
        }
        return (bool)$val;
    }

    private function render_column($col, $instances) {
        ob_start();
        echo '<div class="instance-select">';
        echo '<label for="' . esc_attr($col['name']) . '">' . esc_html($col['label']) . '</label>';
        if (!empty($instances)) {
            echo '<select id="' . esc_attr($col['name']) . '" name="' . esc_attr($col['name']) . '">';
            echo '<option value="">' . esc_html__('-- Select an online instance --', 'awp') . '</option>';
            foreach ($instances as $inst) {
                if ($col['type'] === 'numeric_id') {
                    $val = (int)$inst->id;
                    $sel = selected($col['value'], $val, false);
                    echo '<option value="' . esc_attr($val) . '" ' . $sel . '>' . esc_html($inst->name . ' (ID#' . $inst->id . ')') . '</option>';
                } else {
                    $iid = $inst->instance_id;
                    $sel = selected($col['value'], $iid, false);
                    echo '<option value="' . esc_attr($iid) . '" ' . $sel . '>' . esc_html($inst->name . ' (' . $inst->instance_id . ')') . '</option>';
                }
            }
            echo '</select>';
        } else {
            echo '<p style="color:#a00;margin:0;">' . esc_html__('No online instances found. ', 'awp') . '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public function render_otp_senders_form_wizard() {
        if (!current_user_can('manage_options')) return;

        $otp_global = $this->get_toggle('awp_wawp_otp_enabled');
        $otp_login = $this->get_toggle('awp_otp_login_enabled');
        $otp_signup = $this->get_toggle('awp_signup_enabled');
        $otp_checkout = $this->get_toggle('awp_checkout_otp_enabled');
        $notifications_enabled = $this->get_toggle('awp_notifications_enabled');

        $otp_settings = get_option('awp_otp_settings', []);
        $curr_login_val = $otp_settings['instance'] ?? '';

        $curr_signup_val = '';
        if (class_exists('AWP_Database_Manager')) {
            $dbm = new AWP_Database_Manager();
            $signup_row = $dbm->get_signup_settings();
            if (!empty($signup_row['selected_instance'])) {
                $curr_signup_val = (string)$signup_row['selected_instance'];
            }
        }

        $curr_checkout_val = get_option('awp_selected_instance', '');
        $curr_resend_val = get_option('awp_selected_log_manager_instance', '');

        $selected_mult = [];
        if (class_exists('AWP_Database_Manager')) {
            $dbm = $dbm ?? new AWP_Database_Manager();
            $notif_settings = $dbm->get_notif_global();
            $selected_csv = $notif_settings['selected_instance_ids'] ?? '';
            $selected_mult = array_filter(array_map('intval', explode(',', $selected_csv)));
        }

        $online_instances = $this->get_online_instances();

        $otp_cols = [];
        if ($otp_global && $otp_login) $otp_cols[] = ['type' => 'instance_id', 'label' => __('OTP Login', 'awp'), 'name' => 'awp_login_instance', 'value' => $curr_login_val];
        if ($otp_global && $otp_signup) $otp_cols[] = ['type' => 'numeric_id', 'label' => __('WhatsApp OTP Signup', 'awp'), 'name' => 'awp_signup_instance', 'value' => $curr_signup_val];
        if ($otp_global && $otp_checkout) $otp_cols[] = ['type' => 'instance_id', 'label' => __('Checkout OTP Verification', 'awp'), 'name' => 'awp_checkout_instance', 'value' => $curr_checkout_val];
        $resend_col = ['type' => 'instance_id', 'label' => __('Resend Failed Messages', 'awp'), 'name' => 'awp_resend_instance', 'value' => $curr_resend_val];

        ?>
        <form id="wawp-wizard-sender-form">
            <?php wp_nonce_field('wawp_wizard_save_senders_action', 'wawp_wizard_save_senders_nonce'); ?>
            <div class="awp-card" style="margin:20px 0;">
                <div class="card-header_row">
                    <div class="card-header">
                        <h4 class="card-title"><i class="ri-send-plane-fill"></i> <?php esc_html_e('Choose WhatsApp Sender', 'awp'); ?></h4>
                        <p><?php esc_html_e('Select the WhatsApp number that will be used for each feature.', 'awp'); ?></p>
                    </div>
                </div>
                <div class="instances-setup">
                    <?php
                    if (!empty($otp_cols)) {
                        foreach ($otp_cols as $col) {
                            echo $this->render_column($col, $online_instances);
                        }
                    }
                    echo $this->render_column($resend_col, $online_instances);
                    if ($notifications_enabled): ?>
                    <div class="instance-select" style="min-width:100%;">
                        <label for="wawp_notif_selected_instance"><?php esc_html_e('WooCommerce Notifications', 'awp'); ?></label>
                        <?php if (!empty($online_instances)): ?>
                            <select name="wawp_notif_selected_instance[]" id="wawp_notif_selected_instance" multiple="multiple" class="wawp-instance-multi" style="width:100%;">
                                <?php foreach ($online_instances as $inst): ?>
                                    <option value="<?php echo (int)$inst->id; ?>" <?php selected(in_array((int)$inst->id, $selected_mult, true), true); ?>>
                                        <?php echo esc_html("{$inst->name} ({$inst->instance_id})"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Number(s) that will send WooCommerce event notifications.', 'awp'); ?></p>
                        <?php else: ?>
                            <p style="color:#a00;margin:0;"><?php esc_html_e('No online instances found.', 'awp'); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <p class="submit awp_save" style="text-align: right; margin-top: 1rem;">
                        <button type="submit" class="awp-btn primary"><?php esc_html_e( 'Save Changes', 'awp' ); ?></button>
                    </p>
                </div>
            </div>
        </form>
        </div>
        <?php
    }
    
    private function get_all_countries() {
        if (function_exists('AWP\\awp_get_all_countries')) return \AWP\awp_get_all_countries();
        $countries_file = AWP_PLUGIN_DIR . 'includes/country-code-list.php';
        if (file_exists($countries_file)) {
            return require $countries_file;
        }
        return [['iso2'=>'us','name'=>__('United States','awp'),'region'=>__('Americas','awp')]];
    }

    private function iso2_to_flag($iso) {
        $iso = strtoupper($iso);
        $flag='';
        for($i=0;$i<mb_strlen($iso);$i++){
            $code=127397+ord($iso[$i]);
            $flag.=mb_convert_encoding('&#'.$code.';','UTF-8','HTML-ENTITIES');
        }
        return $flag;
    }

    public function render_country_code_form_wizard() {
        $options = get_option('woo_intl_tel_options', []);
        $default = isset($options['default_country_code']) ? $options['default_country_code'] : 'us';
        $all_countries = $this->get_all_countries();
        ?>
        <div class="step5-style" style="display: ruby;">
        <form id="wawp-wizard-country-form">
            <div class="awp-card" style="margin: 20px 0;">
                <div class="card-header_row">
                    <div class="card-header">
                        <h4 class="card-title"><i class="ri-earth-line"></i> <?php esc_html_e('Default Country Code', 'awp'); ?></h4>
                        <p><?php esc_html_e('Set the default country for phone number fields across your site.', 'awp'); ?></p>
                    </div>
                </div>
                <div class="awp-setting-end" style="display: grid;padding: 1rem;">
                    <select id="awp-default-country-code" name="awp_default_country">
                        <?php
                        foreach ($all_countries as $c) {
                            $iso2 = $c['iso2'];
                            $name = $c['name'];
                            $flag = $this->iso2_to_flag($iso2);
                            printf(
                                '<option value="%s" %s>%s %s</option>',
                                esc_attr($iso2),
                                selected($default, $iso2, false),
                                $flag,
                                esc_html($name)
                            );
                        }
                        ?>
                    </select>
                    <p class="submit awp_save" style="text-align: right; margin-top: 1rem; margin-bottom: 0;">
                        <button type="submit" class="awp-btn secondary"><?php esc_html_e( 'Save Country', 'awp' ); ?></button>
                    </p>
                </div>
            </div>
        </form>
        <?php
    }

    public function ajax_save_country() {
        check_ajax_referer('awp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
        $cc = isset($_POST['awp_default_country']) ? sanitize_text_field($_POST['awp_default_country']) : 'us';
        $options = get_option('woo_intl_tel_options', []);
        $options['default_country_code'] = $cc;
        update_option('woo_intl_tel_options', $options);
        wp_send_json_success(['message' => __('Default country saved.', 'awp')]);
    }

    public function ajax_save_sender_settings() {
        check_ajax_referer('wawp_wizard_save_senders_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $login_val    = sanitize_text_field( $_POST['awp_login_instance'] ?? '' );
        $signup_val   = sanitize_text_field( $_POST['awp_signup_instance'] ?? '' );
        $checkout_val = sanitize_text_field( $_POST['awp_checkout_instance'] ?? '' );
        $resend_val   = sanitize_text_field( $_POST['awp_resend_instance'] ?? '' );

        $otp_settings = get_option( 'awp_otp_settings', [] );
        $otp_settings['instance'] = $login_val;
        update_option( 'awp_otp_settings', $otp_settings );

        if ( class_exists( 'AWP_Database_Manager' ) ) {
            $dbm = new AWP_Database_Manager();
            $dbm->update_signup_settings( [ 'selected_instance' => (int) $signup_val ] );
        }

        update_option( 'awp_selected_instance', $checkout_val );
        update_option( 'awp_selected_log_manager_instance', $resend_val );

        if ( class_exists( 'AWP_Database_Manager' ) ) {
            $dbm  = $dbm ?? new AWP_Database_Manager();
            $sel  = isset( $_POST['wawp_notif_selected_instance'] )
                ? array_unique( array_map( 'intval', (array) $_POST['wawp_notif_selected_instance'] ) )
                : [];
            $dbm->set_notif_global( implode( ',', $sel ) );
        }
        
        wp_send_json_success(['message' => __('Settings saved successfully!', 'awp')]);
    }
    
    public function ajax_get_final_checks() {
        check_ajax_referer('awp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        if (!class_exists('AWP_System_Info')) {
            require_once AWP_PLUGIN_DIR . 'includes/class-awp-system-info.php';
        }
        $system_info = new AWP_System_Info();
        $checks = $system_info->gather_all_checks();
        $total_issues = $system_info->count_total_issues($checks);

        $response = [
            'db_ok' => !$checks['db']['has_issues'],
            'cron_status_ok' => !$checks['cron']['has_issues'],
            'cron_const_ok' => ! (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
            'instances_ok' => $checks['wawp_instances']['status'] === 'OK',
            'total_issues' => $total_issues
        ];

        wp_send_json_success($response);
    }
    
    private function check_if_sso() {
        $token = get_option('mysso_token');
        $data  = get_transient('siteB_user_data');
        return (!empty($token) && !empty($data) && isset($data['user_email']));
    }

    private function get_current_admin_url() {
        $scheme = is_ssl() ? 'https' : 'http';
        $base   = admin_url($GLOBALS['pagenow'], $scheme);
        $qs     = $_SERVER['QUERY_STRING'] ?? '';
        $filtered_url = add_query_arg([], $base . '?' . $qs);
        $filtered_url = remove_query_arg(['token', 'mysso_logout', 'redirect_to', 'wawp_popup_step'], $filtered_url);
        return $filtered_url;
    }
}

new Wawp_Setup_Wizard();
