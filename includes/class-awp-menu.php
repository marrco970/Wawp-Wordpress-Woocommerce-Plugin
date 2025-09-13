<?php
if (!defined('ABSPATH')) exit;
if (!defined('AWP_MAIN_MENU_SLUG')) define('AWP_MAIN_MENU_SLUG', 'wawp');

class AWP_Menu {
    
    private $awp_instances;
    private $awp_log_manager;
    private $awp_countrycode;
    private $awp_chat_widget;
    private $wawp_connector;
    private $wawp_dashboard;
    private $awp_notifications;
    private $awp_campaigns;
    private $awp_system_info;

    public function __construct(
        $awp_instances,
        $awp_log_manager,
        $awp_countrycode,
        $awp_chat_widget,
        $awp_notifications,
        
        $wawp_connector
        
    ) {
        $this->awp_instances = $awp_instances;
        $this->awp_log_manager = $awp_log_manager;
        $this->awp_countrycode = $awp_countrycode;
        $this->awp_chat_widget = $awp_chat_widget;
        $this->wawp_connector = $wawp_connector;
        $this->awp_notifications = $awp_notifications;
        $this->wawp_dashboard = new Wawp_Dashboard();
        $this->awp_system_info = new AWP_System_Info();
        if ( class_exists( 'WP_Wawp_Campaigns_Advanced' ) ) {
            $this->awp_campaigns = wp_list_filter( $GLOBALS, [ 'WP_Wawp_Campaigns_Advanced' ], 'instanceof' );
            $this->awp_campaigns = $this->awp_campaigns ? reset( $this->awp_campaigns ) : new WP_Wawp_Campaigns_Advanced();
        }
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_main_menu']);
        add_action('wp_ajax_awp_check_menu_status', [$this, 'check_menu_status']);
        add_action('admin_post_awp_process_form', [$this, 'process_form']);
        add_action('admin_enqueue_scripts', ['AWP_Enqueue_Scripts', 'enqueue_admin_styles_scripts']);
        add_action( 'admin_bar_menu', [ $this, 'add_toolbar_node' ], 90 ); 
        add_action( 'admin_head',     [ $this, 'print_favicon' ] );       
    }

    private function is_segmentation_enabled(): bool
    {
        $user_data = get_transient('siteB_user_data');
        return (bool)($user_data['segmentation_notifications'] ?? false);
    }

    public function check_menu_status() {
    check_ajax_referer( 'awp_check_menu_status_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Unauthorized access.', 'awp' ) ], 401 );
    }

    $banned_msg      = get_transient( 'siteB_banned_msg' );
    $not_logged_in   = get_transient( 'siteB_not_logged_in_msg' );
    $token           = get_option( 'mysso_token' );
    $user_data       = get_transient( 'siteB_user_data' );
    $current_domain  = wp_parse_url( get_site_url(), PHP_URL_HOST );

    $site_active = true;
    if ( $user_data && isset( $user_data['sites'][ $current_domain ] ) && 'active' !== $user_data['sites'][ $current_domain ] ) {
        $site_active = false;
    }

    wp_send_json_success( [
        'banned'        => (bool) $banned_msg,
        'not_logged_in' => (bool) $not_logged_in,
        'hasToken'      => ! empty( $token ),
        'siteActive'    => $site_active,
    ] );
}

    public function load_section_dependencies() {
    $section = isset( $_GET['awp_section'] ) ? sanitize_key( $_GET['awp_section'] ) : 'dashboard';

    switch ( $section ) {
        case 'activity_logs':
            require_once AWP_PLUGIN_DIR . 'includes/class-awp-log-manager.php';
            break;
        case 'otp_messages':
            require_once AWP_PLUGIN_DIR . 'includes/class-wawp-otp.php';
            break;
    }
}

    private function get_wawp_svg_icon() {
        return '
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36" fill="none">
            <rect width="36" height="36" rx="12" fill="#44FF87"/>
            <path d="M26 7.5C26 6.94772 25.5523 6.5 25 6.5H23C22.4477 6.5 22 6.94772 22 7.5V9.5C22 10.0523 22.4477 10.5 23 10.5H25C25.5523 10.5 26 10.0523 26 9.5V7.5Z" fill="#004349"/>
            <path d="M10 7.5C10 6.94772 10.4477 6.5 11 6.5H13C13.5523 6.5 14 6.94772 14 7.5V9.5C14 10.0523 13.5523 10.5 13 10.5H11C10.4477 10.5 10 10.0523 10 9.5V7.5Z" fill="#004349"/>
            <path d="M6 13.5C6 11.2909 7.79086 9.5 10 9.5H26C28.2091 9.5 30 11.2909 30 13.5V23.5C30 25.7091 28.2091 27.5 26 27.5H10C7.79086 27.5 6 25.7091 6 23.5V13.5Z" fill="#004349"/>
            <rect x="8" y="11.5" width="20" height="14" rx="2" fill="white"/>
            <rect x="20" y="15.5" width="4" height="6" rx="1" fill="#004349"/>
            <path d="M18 30.5C20 30.5 23 27.5 23 27.5H13C13 27.5 16 30.5 18 30.5Z" fill="#004349"/>
            <rect x="12" y="15.5" width="4" height="6" rx="1" fill="#004349"/>
        </svg>';
    }

    public function register_main_menu() {
        $menu_label = esc_html__('Wawp', 'awp');
        $svg_icon   = $this->get_wawp_svg_icon();  
        $icon_data  = 'data:image/svg+xml;base64,' . base64_encode($svg_icon);
        $banned_msg      = get_transient('siteB_banned_msg');
        $not_logged_in_msg = get_transient('siteB_not_logged_in_msg');
        $token           = get_option('mysso_token');
        $user_data       = get_transient('siteB_user_data');
        $current_domain  = wp_parse_url(get_site_url(), PHP_URL_HOST);
        $site_active     = true;

        if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
            $site_active = false;
        }

        $show_connector = false;
        if ($banned_msg || $not_logged_in_msg || !$token || !$site_active) {
            $show_connector = true;
        }

        $hook_suffix = add_menu_page(
            $menu_label,
            $menu_label,
            'manage_options',
            AWP_MAIN_MENU_SLUG,
            [$this, 'render_main_page'],
            $icon_data,
            50
        );

        add_action('admin_head', function () use ($hook_suffix, $svg_icon) {
            $base64 = 'data:image/svg+xml;base64,' . base64_encode($svg_icon);
            ?>
            <style>
                #adminmenu #toplevel_page_wawp .wp-menu-image:before {
                    content: "";
                    display: inline-block;
                    background: url("<?php echo esc_attr($base64); ?>") no-repeat center center !important;
                    background-size: 20px 20px !important;
                    width: 20px;
                    height: 20px;
                }
                #adminmenu #toplevel_page_wawp .wp-menu-image.dashicons-before:before {
                    font-family: initial !important;
                    content: none !important;
                }
                .folded #adminmenu div.wp-menu-image {
                    width: 30px !important;
                    height: 33px !important;
                    position: absolute;
                    z-index: 25;
                }
            </style>
            <?php
        });
    }

    public function render_main_page() {

    $banned_msg      = get_transient( 'siteB_banned_msg' );
    $not_logged_in   = get_transient( 'siteB_not_logged_in_msg' );
    $token           = get_option( 'mysso_token' );
    $user_data       = get_transient( 'siteB_user_data' );
    $current_domain  = wp_parse_url( get_site_url(), PHP_URL_HOST );
    $site_active     = ( $user_data && isset( $user_data['sites'][ $current_domain ] ) && 'active' !== $user_data['sites'][ $current_domain ] ) ? false : true;

    $section = isset( $_GET['awp_section'] ) ? sanitize_key( wp_unslash( $_GET['awp_section'] ) ) : 'dashboard';

    if ( $banned_msg || $not_logged_in || ! $token || ! $site_active ) {
        $section = 'connector';
    }

    echo '<div class="awp-wrapper"><div class="awp-main-container" style="display:contents;">';

 
    if ( $banned_msg || $not_logged_in || ! $token || ! $site_active ) {
        $this->render_lite_sidebar( $section );
    } else {
        $this->render_sidebar( $section );
    }

    echo '<div class="awp_main-container">';
    echo '<div class="awp_top-bar">
        ' . esc_html__('VERSION ', 'awp') . AWP_PLUGIN_VERSION . '
        <div style="display: flex;gap: .5rem;">
            <a href="https://wawp.net/support" target="_blank" class="awp-btn">
                <i class="ri-customer-service-2-line"></i>
                ' . esc_html__('Support', 'awp') . '
            </a>
            <a href="https://wawp.net/affiliate-program" target="_blank" class="awp-btn primary">
                <i class="ri-gift-line"></i>
                ' . esc_html__('Refer & Earn', 'awp') . '
            </a>';
            
        echo '</div>
    </div>';
    /* ----------  PERSISTENT “update available” bar  ---------- */
$remote_version = '';
$user_data      = get_transient( 'siteB_user_data' );
if ( $user_data && ! empty( $user_data['wawp_version'] ) ) {
    $remote_version = $user_data['wawp_version'];
}

if ( $remote_version && version_compare( AWP_PLUGIN_VERSION, $remote_version, '<' ) ) {

    $update_url = admin_url( 'update-core.php' );

    echo '<div class="awp_update-bar" style="
            background:#fffbe5;
            border-left:4px solid #ffb900;
            margin:0 0 10px;
            padding:12px 18px;
            font-size:14px;
            line-height:1.5;
            display:flex;
            align-items:center;
            gap:15px;
        ">
            <span style="font-weight:600;">
                ' . sprintf(
                    /* translators: 1: current version, 2: latest version */
                    esc_html__(
                        '🚀Wawp%2$s is ready! (You’re on %1$s) – update now to enjoy the latest features and fixes✨',
                        'awp'
                    ),
                    esc_html( AWP_PLUGIN_VERSION ),
                    esc_html( $remote_version )
                ) . '
            </span>

            <a href="' . esc_url( $update_url ) . '" class="awp-btn primary">
                ' . esc_html__( '🔄 Update now', 'awp' ) . '
            </a>
        </div>';
}
/* ----------  end persistent bar  ---------- */

    echo '<div class="awp-content">';
    $this->render_section_content( $section );
    echo '</div></div></div></div><div id="wawp-get-started-container"></div>';
}

    private function render_section_content( $section ) {
    switch ( $section ) {
        case 'instances':
            $this->render_instances_page();
            break;
        case 'otp_messages':
            $this->render_wawp_otp_page();
            break;
        case 'activity_logs':
            $this->render_notification_logs_page();
            break;
        case 'chat_widget':
            if ( $this->awp_chat_widget ) {
                $this->awp_chat_widget->options_page();
            }
            break;
            
        case 'notifications':
            if ( $this->awp_notifications ) {
                $this->awp_notifications->render_settings_page();
            } else {
                $this->error_notice( __( 'Notifications module not initialised.', 'awp' ) );
            }
            break;
            
            case 'campaigns':
            $this->render_campaigns_page();
            break;
        case 'campaigns_new':
            $this->render_campaigns_new_page();
            break;
        case 'email_log':
            $this->render_email_log_page();
            break;
            
            
        case 'settings':
    if ( $this->awp_countrycode ) {
        $this->render_country_code_page();
    }
    break;
        case 'connector':
            $this->render_connector_page();
            break;
        case 'system_info':
            $this->render_system_info_page();
            break;
        default:
            $this->render_dashboard_page();
            break;
    }
}

    private function render_lite_sidebar($section) {
        echo '<div class="awp-sidebar">';
        echo '<div class="wawp-logo"><img src="' . esc_url(AWP_PLUGIN_URL . 'assets/img/Wawp-logo.svg') . '" alt="' . esc_attr__('Wawp logo', 'awp') . '">';
        echo '<a href="https://wawp.net" target="_blank" class="wawp-link"><i class="ri-external-link-line"></i></a>';
        echo '</div>';
        echo '<ul class="awp-menu">';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=connector')) . '" class="' . esc_attr($section === 'connector' ? 'active' : '') . '"><i class="ri-rocket-2-line"></i>' . esc_html__('Get Started', 'awp') . '</a></li>';
        echo '
            <li>
                <a href="https://wawp.net/get-started/welcome-to-wawp/" target="_blank">
                    <i class="ri-book-line"></i> ' . esc_html__('Documentation', 'awp') . '
                    <i class="ri-arrow-right-up-line awp-external"></i>
                </a>
            </li>
            <li>
                <a href="https://www.facebook.com/groups/wawpcommunity" target="_blank">
                    <i class="ri-facebook-circle-fill"></i> ' . esc_html__('Wawp Community', 'awp') . '
                    <i class="ri-arrow-right-up-line awp-external"></i>
                </a>
            </li>
        ';
        echo '</ul>';
        echo '<div class="awp-menu_wrapper">';
        echo '<ul class="awp-menu">';
        
        echo '<li class="label">' . esc_html__('GENERAL', 'awp') . '</li>';
        echo '<li><a><i class="ri-layout-grid-line"></i>' . esc_html__('Dashboard', 'awp') . '</a></li>';
        echo '<li><a><i class="ri-send-plane-2-line"></i>' . esc_html__('Sender Settings', 'awp') . '</a></li>';
        echo '<li><a><i class="ri-global-line"></i>' . esc_html__('Advanced Phone Field', 'awp') . '</a></li>';
        echo '<li><a><i class="ri-whatsapp-line"></i>' . esc_html__('WhatsApp Chat Button', 'awp') . '</a></li>';
        echo '<li><a><i class="ri-lock-password-line"></i>' . esc_html__('Forms & OTP Verification', 'awp') . '</a></li>';
        echo '<li><a><i class="ri-chat-history-line"></i>' . esc_html__('Messages History', 'awp') . '</a></li>';
        echo '</ul>';
        echo '<div class="awp-unlock">
                <h4>' . esc_html__('Login to Unlock All Wawp Features for FREE', 'awp') . '</h4>
              </div>';
        echo '</div>';
        echo '</div>';

    }
    
    private function render_campaigns_page() {
        if ( ! $this->awp_campaigns ) {
            $this->error_notice( __( 'Campaign module not initialised.', 'awp' ) );
            return;
        }
        $this->awp_campaigns->page_campaigns();
    }
    
    private function render_campaigns_new_page() {
        if ( ! $this->awp_campaigns ) { $this->error_notice( __( 'Campaign module not initialised.', 'awp' ) ); return; }
        $this->awp_campaigns->page_new_campaign();
    }
    
    private function render_email_log_page() {

    if ( ! class_exists( 'Wawp_Email_Log_List_Table' ) ) {
        require_once AWP_PLUGIN_DIR . 'includes/class-wawp-email-log-list-table.php';
    }

    if ( ! class_exists( 'Wawp_Email_Log_List_Table' ) ) {
        $this->error_notice( __( 'Email-log component not found.', 'awp' ) );
        return;
    }

    $table = new Wawp_Email_Log_List_Table();
    $table->prepare_items();

    echo '<div>
            <div class="page-header_row">
                <div class="page-header">
                    <h2 class="page-title">' .esc_html__( 'Email History', 'awp' ) . '</h2>
                    <p>' .esc_html__( 'Track all email logs. Also see read status and errors.', 'awp' ) . '</p>
                </div>
            </div>';

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="wawp">';
    echo '<input type="hidden" name="awp_section" value="email_log">';
    $table->display();
    echo '</form></div>';
}

    private function render_sidebar($section) {
        $otp_enabled = get_option('awp_wawp_otp_enabled', 1);
        $is_countrycode_enabled = get_option( 'awp_countrycode_enabled', 1 );
        $campaigns_enabled      = get_option( 'awp_campaigns_enabled', 0 );

        $issues_count = 0;
        if ( $this->awp_system_info instanceof AWP_System_Info ) {
        	$issues_count = $this->awp_system_info->get_cached_issue_count();
        }
        
        $issues_badge = $issues_count
        	? sprintf(
        		'<span class="awp-badge error" style="margin-left:6px;">%d&nbsp;%s</span>',
        		$issues_count,
        		_n( 'issue', 'issues', $issues_count, 'awp' )
        	  )
        	: '';
        
        
        
        $user_data   = get_transient('siteB_user_data');
        $user_email = '';
        if ($user_data && !empty($user_data['user_email'])) {
            $user_email = $user_data['user_email'];
        }

        $plan_color_class = 'awp-free-status';
        $dot_css          = 'background-color: gray;';
        $upgrade_btn      = '<a href="https://wawp.net/pricing" target="_blank" class="upgrade-btn" style="text-decoration:none;">
                                 <i class="ri-flashlight-fill" style="font-size:16px !important;"></i>'
                                 . esc_html__('Get Wawp Pro', 'awp') .
                               '</a>';
        $plan_text = '<span class="awp-badge disabled">' . esc_html__('Free', 'awp') . '</span>';

        $lifetime = (!empty($user_data['is_lifetime']));

        if ($lifetime) {
            $plan_color_class = 'awp-pro-status plan-dot';
            $dot_css          = 'background-color: green;';
            $plan_text = '<span class="awp-badge pro">' . esc_html__('Pro Lifetime', 'awp') . '</span>';
            $upgrade_btn      = '<a class="upgrade-btn" style="display:none;"></a>';
        } else {
            if ($user_data && !empty($user_data['subscriptions']) && is_array($user_data['subscriptions'])) {
                foreach ($user_data['subscriptions'] as $sub) {
                    if (!empty($sub['status']) && $sub['status'] === 'active') {
                        $plan_color_class = 'awp-pro-status plan-dot';
                        $dot_css          = 'background-color: green;';
                        $plan_text = '<span class="awp-badge pro">' . esc_html__('Pro', 'awp') . '</span>';
                        $upgrade_btn      = '<a class="upgrade-btn" style="display:none;"></a>';

                        if (!empty($sub['next'])) {
                            $next_ts = strtotime($sub['next']);
                            if ($next_ts) {
                                $diff      = $next_ts - time();
                                $days_left = ceil($diff / DAY_IN_SECONDS);

                                if ($days_left > 7 && $days_left <= 30) {
                                    $plan_text .= '<span class="awp-badge warning">' . sprintf(esc_html__('%d days left', 'awp'), $days_left) . '</span>';
                                } elseif ($days_left > 0 && $days_left <= 7) {
                                    $dot_css   = 'background-color: red;';
                                    $plan_text = '<span class="awp-badge error"> - ' . esc_html__('Expiring soon', 'awp') . '</span>';
                                } elseif ($days_left === 0) {
                                    $dot_css   = 'background-color: red;';
                                    $plan_text = '<span class="awp-badge error"> - ' . esc_html__('Expire today', 'awp') . '</span>';
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
        ?>
        <div class="awp-sidebar">
            <div class="wawp-logo">
                <img src="<?php echo esc_url(AWP_PLUGIN_URL . 'assets/img/Wawp-logo.svg'); ?>" alt="">
                <a href="https://wawp.net" target="_blank" class="wawp-link">
                    <i class="ri-external-link-line"></i>
                </a>
            </div>
 <div id="wawp-floating-launch" class="awp-btn"><i class="ri-magic-fill"></i>  <?php esc_html_e('Setup Wizard', 'awp'); ?> </div>

                    <ul class="awp-menu">
                        <li class="label"><?php esc_html_e('GENERAL', 'awp'); ?></li>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=dashboard')); ?>"
                               class="<?php echo esc_attr($section === 'dashboard' ? 'active' : ''); ?>">
                               <i class="ri-layout-grid-line"></i> <?php esc_html_e('Dashboard', 'awp'); ?>
                            </a>
                        </li>

                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=instances')); ?>"
                               class="<?php echo esc_attr($section === 'instances' ? 'active' : ''); ?>">
                               <i class="ri-send-plane-2-line"></i> <?php esc_html_e('Sender Settings', 'awp'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=connector')); ?>"
                               class="<?php echo esc_attr($section === 'connector' ? 'active' : ''); ?>">
                               <i class="ri-vip-crown-line"></i> <?php esc_html_e('Subscription', 'awp'); ?>
                               <?php echo $plan_text; ?>
                            </a>
                        </li>
                        
                        
                                            
                    <li>
                    	<a href="<?php echo esc_url(
                    		admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=system_info' )
                    	); ?>"
                    	   class="<?php echo esc_attr( $section === 'system_info' ? 'active' : '' ); ?>">
                    	   <i class="ri-settings-line"></i>
                    	   <?php esc_html_e( 'System Info', 'awp' ); ?>
                    	   <?php echo $issues_badge; ?>
                    	</a>
                    </li>
                        
                    </ul>


                     <ul class="awp-menu">
                        <li class="label"><?php esc_html_e('FEATURES', 'awp'); ?></li>
                       
                     <?php if ( $this->awp_countrycode ) : ?>
                        <li>
                            <a href="<?php echo esc_url(
                                admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=settings' )
                            ); ?>"
                               class="<?php echo esc_attr( $section === 'settings' ? 'active' : '' ); ?>">
                               <i class="ri-global-line"></i>
                               <?php esc_html_e( 'Advanced Phone Field', 'awp' ); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                        
                        <?php if (get_option('awp_chat_widget_enabled', 1)) : ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=chat_widget')); ?>"
                                   class="<?php echo esc_attr($section === 'chat_widget' ? 'active' : ''); ?>">
                                   <i class="ri-whatsapp-line"></i> <?php esc_html_e('WhatsApp Chat Button', 'awp'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ( $this->awp_notifications ) : ?>
                            <li>
                                <a href="<?php echo esc_url(
                                    admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=notifications' )
                                ); ?>"
                                   class="<?php echo esc_attr( $section === 'notifications' ? 'active' : '' ); ?>">
                                   <i class="ri-notification-3-line"></i>
                                   <?php esc_html_e( 'Notifications Builder', 'awp' ); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                                        
                        <?php if ($otp_enabled) : ?>
                            <li>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=otp_messages')); ?>"
                                   class="<?php echo esc_attr($section === 'otp_messages' ? 'active' : ''); ?>">
                                   <i class="ri-lock-password-line"></i> <?php esc_html_e('Forms & OTP Verification', 'awp'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="awp-menu">
                        <li class="label"><?php esc_html_e( 'MESSAGES LOG', 'awp' ); ?></li>

                        <li>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=activity_logs')); ?>"
                               class="<?php echo esc_attr($section === 'activity_logs' ? 'active' : ''); ?>">
                               <i class="ri-chat-history-line"></i> <?php esc_html_e('Whatsapp Web History', 'awp'); ?>
                            </a>
                        </li>

                        <li>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=email_log' ) ); ?>"
                               class="<?php echo esc_attr( $section === 'email_log' ? 'active' : '' ); ?>">
                               <i class="ri-mail-line"></i> <?php esc_html_e( 'Email History', 'awp' ); ?>
                            </a>
                        </li>
                    </ul>
                    
                    
                 <?php   if ( $campaigns_enabled ) : ?>
                    <ul class="awp-menu">
                    <?php if ($this->is_segmentation_enabled()) : ?>
                        <li class="label"><?php esc_html_e( 'CAMPAIGN', 'awp' ); ?></li>

                        <li>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=campaigns' ) ); ?>"
                               class="<?php echo esc_attr( $section === 'campaigns' ? 'active' : '' ); ?>">
                               <i class="ri-funds-line"></i> <?php esc_html_e( 'Bulk Campaigns', 'awp' ); ?>
                            </a>
                        </li>

                        <li>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG . '&awp_section=campaigns_new' ) ); ?>"
                               class="<?php echo esc_attr( $section === 'campaigns_new' ? 'active' : '' ); ?>">
                               <i class="ri-add-circle-line"></i> <?php esc_html_e( 'Create Campaign', 'awp' ); ?>
                            </a>
                        </li>
                    <?php else :?>
                        <li class="label"><?php esc_html_e( 'CAMPAIGN', 'awp' ); ?></li>
                        <li>
                            <a href="https://wawp.net/product/segmentation-notifications-via-whatsapp-and-email/" target="_blank">
                                <i class="ri-funds-line"></i> <?php esc_html_e( 'Bulk Campaigns', 'awp' ); ?>
                                <span class="awp-badge" style="background-color: #0073AA; color: #fff; margin-left: 5px; padding: 3px 8px; border-radius: 4px; font-size: 0.7em; vertical-align: middle;">ONLY FOR 199€</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                    

                    <ul class="awp-menu">
                        <li class="label"><?php esc_html_e('RESOURCES', 'awp'); ?></li>
                        <li>
                            <a href="https://wawp.net/get-started/welcome-to-wawp/" target="_blank">
                                <i class="ri-book-line"></i> <?php esc_html_e('Documentation', 'awp'); ?>
                                <i class="ri-arrow-right-up-line awp-external"></i>
                            </a>
                        </li>
                        <li>
                            <a href="https://wawp.net/whatsapp-text-formatter/" target="_blank">
                                <i class="ri-braces-line"></i> <?php esc_html_e('Placeholders List', 'awp'); ?>
                                <i class="ri-arrow-right-up-line awp-external"></i>
                            </a>
                        </li>
                        <li>
                            <a href="https://www.facebook.com/groups/wawpcommunity" target="_blank">
                                <i class="ri-facebook-circle-fill"></i> <?php esc_html_e('Wawp Community', 'awp'); ?>
                                <i class="ri-arrow-right-up-line awp-external"></i>
                            </a>
                        </li>
                    </ul>
        </div>
        <?php
    }

    private function render_dashboard_page() {
        if ($this->wawp_dashboard) {
            $this->wawp_dashboard->render_page();
        } else {
            $this->error_notice(esc_html__('Dashboard is not initialized.', 'awp'));
        }
    }

    public function render_instances_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'awp'));
        }
        if ($this->awp_instances) {
            $this->awp_instances->render_admin_page();
        } else {
            $this->error_notice(esc_html__('Instances are not initialized.', 'awp'));
        }
    }

    public function render_wawp_otp_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'awp'));
        }
        $dm = new AWP_Database_Manager();
        $inst = new AWP_Instances();
        if (!class_exists('Wawp_Otp')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('OTP integration is disabled.', 'awp') . '</p></div>';
            return;
        }
        $otp_ui = new Wawp_Otp();
        $otp_ui->init($dm, $inst);
        $otp_ui->render_tabs_page();
    }

    public function render_notification_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'awp'));
        }
        if ($this->awp_log_manager) {
            $this->awp_log_manager->render_logs_page();
        } else {
            $this->error_notice(esc_html__('Log Manager is not initialized.', 'awp'));
        }
    }

    public function render_country_code_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'awp'));
        }
        if ($this->awp_countrycode) {
            $this->awp_countrycode->settings_page();
        } else {
            $this->error_notice(esc_html__('Country Code Manager is not initialized.', 'awp'));
        }
    }

    private function error_notice($message) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function render_connector_page() {
        if (!$this->wawp_connector) {
            echo '<p>' . esc_html__('Wawp connector is not initialized.', 'awp') . '</p>';
            return;
        }
        $this->wawp_connector->render_admin_page();
    }

    private function render_system_info_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'awp'));
        }
        if ($this->awp_system_info) {
            $this->awp_system_info->awp_admin_page_content();
        } else {
            $this->error_notice(esc_html__('System Info is not initialized.', 'awp'));
        }
    }

    public function process_form() {
        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }
        check_admin_referer('awp_form_action', 'awp_form_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'awp'));
        }
        wp_redirect(add_query_arg('awp_message', 'form_processed', admin_url('admin.php?page=' . AWP_MAIN_MENU_SLUG)));
        exit;
    }
    
    public function add_toolbar_node( WP_Admin_Bar $wp_admin_bar ) {

    if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $svg      = $this->get_wawp_svg_icon();
    $icon_url = 'data:image/svg+xml;base64,' . base64_encode( $svg );

    $wp_admin_bar->add_node( [
        'id'    => 'awp_toolbar_node',
        'parent'=> false,                               
        'title' => sprintf(
            '<img src="%1$s" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;" alt="Wawp" />%2$s',
            esc_attr( $icon_url ),
            esc_html__( 'Wawp', 'awp' )
        ),
        'href'  => admin_url( 'admin.php?page=' . AWP_MAIN_MENU_SLUG ),
        'meta'  => [ 'class' => 'awp-toolbar-node' ],
    ] );
}

    public function print_favicon() {
    
        if ( ! is_admin() || empty( $_GET['page'] ) || $_GET['page'] !== AWP_MAIN_MENU_SLUG ) {
            return;
        }
    
        $svg       = $this->get_wawp_svg_icon();
        $icon_data = 'data:image/svg+xml;base64,' . base64_encode( $svg );
    
        printf(
            '<link rel="icon" type="image/svg+xml" href="%s" />' . PHP_EOL,
            esc_attr( $icon_data )
        );
    }
    
}