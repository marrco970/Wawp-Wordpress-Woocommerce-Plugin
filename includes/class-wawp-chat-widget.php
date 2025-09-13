<?php
if (!defined('ABSPATH')) {
    exit;
}
class WAWP_Chat_Widget {
    
    private $option_site_visits = 'awp_site_visits';
    private $option_chat_clicks = 'awp_chat_button_clicks';
    private $option_open_whatsapp = 'awp_open_whatsapp_clicks';
    private $option_contact_stats = 'awp_contact_stats';
    private $option_page_stats = 'awp_page_stats';
    private $transient_sessions = 'awp_user_sessions';
    private $session_expire_seconds = 300;
    
    private $remix_icons_extended = [
        'ri-facebook-circle-fill'=>['label'=>'Facebook','color'=>'#4267B2'],
        'ri-twitter-fill'=>['label'=>'Twitter (X)','color'=>'#1DA1F2'],
        'ri-instagram-fill'=>['label'=>'Instagram','color'=>'#C13584'],
        'ri-tiktok-fill'=>['label'=>'TikTok','color'=>'#010101'],
        'ri-linkedin-box-fill'=>['label'=>'LinkedIn','color'=>'#0A66C2'],
        'ri-youtube-fill'=>['label'=>'YouTube','color'=>'#FF0000'],
        'ri-telegram-fill'=>['label'=>'Telegram','color'=>'#229ED9'],
        'ri-github-fill'=>['label'=>'GitHub','color'=>'#171515'],
        'ri-mail-fill'=>['label'=>'Email','color'=>'#D44638'],
        'ri-phone-fill'=>['label'=>'Phone','color'=>'#4D4D4D'],
        'ri-global-fill'=>['label'=>'Website','color'=>'#555555'],
    ];
    
    private $chat_whatsapp_icons = [
        'ri-whatsapp-line'=>'WhatsApp (ri-whatsapp-line)',
        'ri-chat-2-line'=>'Chat (ri-chat-2-line)',
        'ri-chat-3-line'=>'Chat (ri-chat-3-line)',
        'ri-chat-4-line'=>'Chat Dots (ri-chat-4-line)',
        'ri-discuss-line'=>'Chat (ri-discuss-line)',
        'ri-chat-smile-2-line'=>'Chat (ri-chat-smile-2-line)',
        'ri-send-plane-fill'=>'Chat Dots (ri-send-plane-fill)',
        'ri-customer-service-2-line'=>'Chat Dots (ri-customer-service-2-line)',
        'ri-customer-service-line'=>'Chat Dots (ri-customer-service-line)',
    ];
    
    private $preset_svgs = [
        'whatsapp-line'=>'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><path d="M7.25361 18.4944L7.97834 18.917C9.18909 19.623 10.5651 20 12.001 20C16.4193 20 20.001 16.4183 20.001 12C20.001 7.58172 16.4193 4 12.001 4C7.5827 4 4.00098 7.58172 4.00098 12C4.00098 13.4363 4.37821 14.8128 5.08466 16.0238L5.50704 16.7478L4.85355 19.1494L7.25361 18.4944ZM2.00516 22L3.35712 17.0315C2.49494 15.5536 2.00098 13.8345 2.00098 12C2.00098 6.47715 6.47813 2 12.001 2C17.5238 2 22.001 6.47715 22.001 12C22.001 17.5228 17.5238 22 12.001 22C10.1671 22 8.44851 21.5064 6.97086 20.6447L2.00516 22ZM8.39232 7.30833C8.5262 7.29892 8.66053 7.29748 8.79459 7.30402C8.84875 7.30758 8.90265 7.31384 8.95659 7.32007C9.11585 7.33846 9.29098 7.43545 9.34986 7.56894C9.64818 8.24536 9.93764 8.92565 10.2182 9.60963C10.2801 9.76062 10.2428 9.95633 10.125 10.1457C10.0652 10.2428 9.97128 10.379 9.86248 10.5183C9.74939 10.663 9.50599 10.9291 9.50599 10.9291C9.50599 10.9291 9.40738 11.0473 9.44455 11.1944C9.45903 11.25 9.50521 11.331 9.54708 11.3991C9.57027 11.4368 9.5918 11.4705 9.60577 11.4938C9.86169 11.9211 10.2057 12.3543 10.6259 12.7616C10.7463 12.8783 10.8631 12.9974 10.9887 13.108C11.457 13.5209 11.9868 13.8583 12.559 14.1082L12.5641 14.1105C12.6486 14.1469 12.692 14.1668 12.8157 14.2193C12.8781 14.2457 12.9419 14.2685 13.0074 14.2858C13.0311 14.292 13.0554 14.2955 13.0798 14.2972C13.2415 14.3069 13.335 14.2032 13.3749 14.1555C14.0984 13.279 14.1646 13.2218 14.1696 13.2222V13.2238C14.2647 13.1236 14.4142 13.0888 14.5476 13.097C14.6085 13.1007 14.6691 13.1124 14.7245 13.1377C15.2563 13.3803 16.1258 13.7587 16.1258 13.7587L16.7073 14.0201C16.8047 14.0671 16.8936 14.1778 16.8979 14.2854C16.9005 14.3523 16.9077 14.4603 16.8838 14.6579C16.8525 14.9166 16.7738 15.2281 16.6956 15.3913C16.6406 15.5058 16.5694 15.6074 16.4866 15.6934C16.3743 15.81 16.2909 15.8808 16.1559 15.9814C16.0737 16.0426 16.0311 16.0714 16.0311 16.0714C15.8922 16.159 15.8139 16.2028 15.6484 16.2909C15.391 16.428 15.1066 16.5068 14.8153 16.5218C14.6296 16.5313 14.4444 16.5447 14.2589 16.5347C14.2507 16.5342 13.6907 16.4482 13.6907 16.4482C12.2688 16.0742 10.9538 15.3736 9.85034 14.402C9.62473 14.2034 9.4155 13.9885 9.20194 13.7759C8.31288 12.8908 7.63982 11.9364 7.23169 11.0336C7.03043 10.5884 6.90299 10.1116 6.90098 9.62098C6.89729 9.01405 7.09599 8.4232 7.46569 7.94186C7.53857 7.84697 7.60774 7.74855 7.72709 7.63586C7.85348 7.51651 7.93392 7.45244 8.02057 7.40811C8.13607 7.34902 8.26293 7.31742 8.39232 7.30833Z"></path></svg>',
        'custom-svg-smiley'=>'<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="8" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/><path d="M12 17c2.21 0 4-1.79 4-4H8c0 2.21 1.79 4 4 4z"/></svg>'
    ];
    
    public function __construct() {
        add_action('wp_enqueue_scripts',[$this,'enqueue_frontend_scripts']);
        add_action('wp_footer',[$this,'render_whatsapp_button']);
        add_action('admin_init',[$this,'register_settings']);
        add_action('wp_ajax_awp_increment_chat_clicks',[$this,'ajax_increment_chat_clicks']);
        add_action('wp_ajax_nopriv_awp_increment_chat_clicks',[$this,'ajax_increment_chat_clicks']);
        add_action('wp_ajax_awp_contact_click',[$this,'ajax_contact_click']);
        add_action('wp_ajax_nopriv_awp_contact_click',[$this,'ajax_contact_click']);
        add_action('wp_ajax_awp_open_whatsapp_click',[$this,'ajax_open_whatsapp_click']);
        add_action('wp_ajax_nopriv_awp_open_whatsapp_click',[$this,'ajax_open_whatsapp_click']);
        add_action('wp_ajax_awp_clear_stats',[$this,'ajax_clear_stats']);

    }

    public function ajax_increment_chat_clicks() {
        $c = get_option($this->option_chat_clicks,0);
        $c++;
        update_option($this->option_chat_clicks,$c);
        $this->record_page_stat('chat_clicks');
        wp_send_json_success(['clicks'=>$c]);
    }
    
    private function get_chat_clicks() {
        return (int)get_option($this->option_chat_clicks,0);
    }
    
    private function increment_open_whatsapp() {
        $c = get_option($this->option_open_whatsapp,0);
        $c++;
        update_option($this->option_open_whatsapp,$c);
        return $c;
    }
    
    private function get_open_whatsapp_total() {
        return (int)get_option($this->option_open_whatsapp,0);
    }
    
    public function ajax_contact_click() {
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : esc_html__('Unknown','awp');
        if (!$phone) {
            wp_send_json_error(['msg'=>esc_html__('No phone','awp')]);
        }
        $stats = get_option($this->option_contact_stats,[]);
        if (!isset($stats[$phone])) {
            $stats[$phone] = [
                'name'=>$name,
                'chat_clicks'=>0,
                'open_whatsapp'=>0
            ];
        }
        $stats[$phone]['chat_clicks']++;
        update_option($this->option_contact_stats,$stats);
        $this->record_page_stat('chat_clicks');
        wp_send_json_success(['msg'=>esc_html__('Contact click recorded','awp')]);
    }
    
    public function ajax_open_whatsapp_click() {
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        if (!$phone) {
            wp_send_json_error(['msg'=>esc_html__('No phone','awp')]);
        }
        $newGlobal = $this->increment_open_whatsapp();
        $stats = get_option($this->option_contact_stats,[]);
        if (!isset($stats[$phone])) {
            $stats[$phone] = [
                'name'=>esc_html__('Unknown','awp'),
                'chat_clicks'=>0,
                'open_whatsapp'=>0
            ];
        }
        $stats[$phone]['open_whatsapp']++;
        update_option($this->option_contact_stats,$stats);
        $this->record_page_stat('open_whatsapp');
        wp_send_json_success(['total_open'=>$newGlobal]);
    }
    
    private function record_page_stat($field) {
        $pid = 0;
        if (isset($_POST['page_id'])) {
            $pid = absint($_POST['page_id']);
        }
        if (!$pid) {
            global $post;
            if (!empty($post) && isset($post->ID)) {
                $pid = $post->ID;
            }
            if (!$pid) {
                $pid = get_queried_object_id();
            }
        }
        if (!$pid) {
            $pid = 0;
        }
        $stats = get_option($this->option_page_stats,[]);
        if (!isset($stats[$pid])) {
            $stats[$pid] = [
                'chat_clicks'=>0,
                'open_whatsapp'=>0
            ];
        }
        $stats[$pid][$field]++;
        update_option($this->option_page_stats,$stats);
    }
    
    public function ajax_clear_stats() {
        update_option($this->option_site_visits,0);
        update_option($this->option_chat_clicks,0);
        update_option($this->option_open_whatsapp,0);
        update_option($this->option_contact_stats,[]);
        update_option($this->option_page_stats,[]);
        delete_transient($this->transient_sessions);
        wp_send_json_success(['message'=>esc_html__('Stats cleared.','awp')]);
    }
    
    public function enqueue_frontend_scripts() {
        global $post;
        $pid = 0;
        if (!empty($post) && isset($post->ID)) {
            $pid = $post->ID;
        }
        if (!$pid) {
            $pid = get_queried_object_id();
        }
        $style_path = AWP_PLUGIN_DIR.'assets/css/style.css';
        $style_ver = file_exists($style_path)?filemtime($style_path):'1.0';
        wp_enqueue_style('floating-whatsapp-button-style',AWP_PLUGIN_URL.'assets/css/style.css',[],$style_ver);
        wp_enqueue_script('qrcodejs',AWP_PLUGIN_URL . 'assets/js/resources/qrcode.min.js',['jquery'],'1.0.1',true);
        $script_path = AWP_PLUGIN_DIR.'assets/js/script.js';
        $script_ver = file_exists($script_path)?filemtime($script_path):'1.0';
        wp_enqueue_script('floating-whatsapp-button-script',AWP_PLUGIN_URL.'assets/js/script.js',['jquery','qrcodejs'],$script_ver,true);
        wp_localize_script('floating-whatsapp-button-script','awp_ajax_obj',[
            'ajax_url'=>admin_url('admin-ajax.php'),
            'current_pid'=>$pid
        ]);
        wp_enqueue_style('remix-icon',AWP_PLUGIN_URL . 'assets/css/resources/remixicon.css',[],'4.6.0');
    }
    
    public function register_settings() {
        $settings = [
            'awp_whatsapp_numbers'=>['default'=>[],'sanitize_callback'=>[$this,'sanitize_array']],
            'awp_whatsapp_messages'=>['default'=>[],'sanitize_callback'=>[$this,'sanitize_array']],
            'awp_user_names'=>['default'=>[],'sanitize_callback'=>[$this,'sanitize_array']],
            'awp_user_roles'=>['default'=>[],'sanitize_callback'=>[$this,'sanitize_array']],
            'awp_user_avatars'=>['default'=>[],'sanitize_callback'=>[$this,'sanitize_array']],
            'awp_social_button_style'=>['default'=>'round','sanitize_callback'=>'sanitize_text_field'],
            'awp_social_intro_text'=>['default'=>__('Reach us on:','awp'),'sanitize_callback'=>'sanitize_text_field'],
            'awp_social_icons'=>['default'=>[],'sanitize_callback'=>[$this,'sanitize_social_icons']],
            'awp_reply_time_text'=>['default'=>__('Typically replies within minutes','awp'),'sanitize_callback'=>'sanitize_text_field'],
            'awp_welcome_message'=>['default'=>__('How may we help you?','awp'),'sanitize_callback'=>'sanitize_text_field'],
            'awp_whatsapp_header'=>['default'=>__('Let\'s chat','awp'),'sanitize_callback'=>'sanitize_text_field'],
            'awp_button_size'=>['default'=>'60','sanitize_callback'=>'intval'],
            'awp_corner_radius'=>['default'=>'50','sanitize_callback'=>'intval'],
            'awp_button_position'=>['default'=>'right','sanitize_callback'=>'sanitize_text_field'],
            'awp_display_desktop'=>['default'=>'yes','sanitize_callback'=>'sanitize_text_field'],
            'awp_display_mobile'=>['default'=>'yes','sanitize_callback'=>'sanitize_text_field'],
            'awp_enable_button'=>['default'=>'yes','sanitize_callback'=>'sanitize_text_field'],
            'awp_disable_powered_by'=>['default'=>'no','sanitize_callback'=>'sanitize_text_field'],
            'awp_icon_url'=>['default'=>plugin_dir_url(__FILE__).'WhatsApp_icon.png','sanitize_callback'=>'esc_url_raw'],
            
            'awp_avatar_url'=>['default'=>plugin_dir_url(__FILE__).'WhatsApp_avatar.png','sanitize_callback'=>'esc_url_raw'],
            'awp_avatar_name'=>['default'=>basename(plugin_dir_url(__FILE__).'WhatsApp_avatar.png'),'sanitize_callback'=>'sanitize_text_field'],
            'awp_whatsapp_icon_class'=>['default'=>'ri-chat-3-line','sanitize_callback'=>'sanitize_text_field'],
            'awp_button_bg_color'=>['default'=>'linear-gradient(135deg, #2ee168, #00c66b)','sanitize_callback'=>'sanitize_text_field'],
            'awp_icon_color'=>['default'=>'#fff','sanitize_callback'=>'sanitize_text_field']
        ];
        foreach ($settings as $key=>$args) {
            register_setting('awp_options_group',$key,[
                'type'=>'string',
                'sanitize_callback'=>$args['sanitize_callback'],
                'default'=>$args['default']
            ]);
        }
        register_setting('awp_options_group','awp_trigger_on_all_pages',[
            'type'=>'string',
            'sanitize_callback'=>'sanitize_text_field',
            'default'=>'yes'
        ]);
        register_setting('awp_options_group','awp_page_conditions',[
            'type'=>'array',
            'sanitize_callback'=>[$this,'sanitize_page_conditions_exact'],
            'default'=>[]
        ]);
    }
    
    public function sanitize_array($input){
        if(is_array($input)) {
            return array_map('sanitize_text_field',$input);
        }
        return [];
    }
    
    public function sanitize_social_icons($input){
    if(!is_array($input)) return [];
    $clean=[];
    foreach($input as $item){
        $link = isset($item['link']) ? esc_url_raw($item['link']) : '';
        $icon = isset($item['icon']) ? sanitize_text_field($item['icon']) : 'ri-global-fill';

        if(!isset($this->remix_icons_extended[$icon])) {
            $icon = 'ri-global-fill';
        }

        if ($icon === 'ri-mail-fill' && $link && stripos($link, 'mailto:') !== 0) {
            $link = 'mailto:' . $link;
        }

        if ($icon === 'ri-phone-fill' && $link && stripos($link, 'tel:') !== 0) {
            $link = 'tel:' . $link;
        }

        if ($link) {
            $clean[] = [
                'link' => $link,
                'icon' => $icon
            ];
        }
    }
    return $clean;
}

    public function sanitize_page_conditions_exact($input) {
        $clean=[];
        if(is_array($input)) {
            foreach($input as $item) {
                $id=absint($item);
                if($id>0) $clean[]=$id;
            }
        }
        return $clean;
    }
    
    public function options_page() {
        $banned_msg = get_transient('siteB_banned_msg');
        $token      = get_option('mysso_token');
        $user_data  = get_transient('siteB_user_data');
        if ($banned_msg) {
            echo '<div class="wrap">
                    <h1><i class="ri-lock-line"></i> '.esc_html__('Whatsapp Chat Widget','awp').'</h1>
                    <p style="color:red;">'.esc_html(Wawp_Global_Messages::get('blocked_generic')).'</p>
                  </div>';
            return;
        }
        if (!$token) {
            echo '<div class="wrap">
                    <h1><i class="dashicons dashicons-lock"></i> '.esc_html__('Whatsapp Chat Widget','awp').'</h1>
                    <p>'.esc_html(Wawp_Global_Messages::get('need_login')).'</p>
                  </div>';
            return;
        }
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        if ($user_data && isset($user_data['sites'][$current_domain]) && $user_data['sites'][$current_domain] !== 'active') {
            echo '<div class="wrap">
                    <h1><i class="ri-lock-line"></i> '.esc_html__('Whatsapp Chat Widget','awp').'</h1>
                    <p style="color:red;">'.esc_html(Wawp_Global_Messages::get('not_active_site')).'</p>
                  </div>';
            return;
        }
        $chat_clicks = (int)get_option($this->option_chat_clicks,0);
        $open_total = (int)get_option($this->option_open_whatsapp,0);
        $contactStats = get_option($this->option_contact_stats,[]);
        $pageStats = get_option($this->option_page_stats,[]);
        if (is_array($pageStats)) {
            uasort($pageStats,function($a,$b){
                $sumA = $a['chat_clicks']+$a['open_whatsapp'];
                $sumB = $b['chat_clicks']+$b['open_whatsapp'];
                return $sumB<=>$sumA;
            });
        }
        $topTen = array_slice($pageStats,0,10,true);
        $numbers = get_option('awp_whatsapp_numbers',[]);
        $messages = get_option('awp_whatsapp_messages',[]);
        $names = get_option('awp_user_names',[]);
        $roles = get_option('awp_user_roles',[]);
        $avatars = get_option('awp_user_avatars',[]);
        $social_style = get_option('awp_social_button_style','round');
        $social_intro = get_option('awp_social_intro_text',__('Reach us on:','awp'));
        $social_icons = get_option('awp_social_icons',[]);
        $main_icon_class = get_option('awp_whatsapp_icon_class','ri-chat-3-line');
        $button_bg = get_option('awp_button_bg_color','linear-gradient(135deg, #2ee168, #00c66b)');
        $icon_col = get_option('awp_icon_color','#fff');
        $trigger_on_all_pages = get_option('awp_trigger_on_all_pages','yes');
        $page_conditions = get_option('awp_page_conditions',[]);
        $all_pages = get_pages([
            'post_type'=>'page',
            'post_status'=>'publish',
            'numberposts'=>-1,
            'sort_column'=>'post_title',
            'sort_order'=>'asc'
        ]);
        ?>
        <div class="wrap">
            <div class="page-header_row">
                    <div class="page-header">
                        <h2 class="page-title"><?php esc_html_e('WhatsApp Chat Button','awp'); ?></h2> 
                        <p><?php esc_html_e('Add direct WhatsApp chat, social media links, and more with stats.','awp'); ?>
                        <a href="https://wawp.net/get-started/add-whatsapp-chat-button-to-wordpress/" target="_blank"><?php esc_html_e('Learn more','awp'); ?></a>
                        </p>
                    </div>
            </div>
            <div class="awp-settings-wrap">
              <form method="post" action="options.php">
                <?php
                settings_fields('awp_options_group');
                do_settings_sections('awp_options_group');
                ?>
                <div class="nav-tab-wrapper awp-tabs-nav">
                    <a href="#" class="nav-tab" data-tab="awp-tab-contacts"><?php echo esc_html__('WhatsApp', 'awp'); ?></a>
                    <a href="#" class="nav-tab" data-tab="awp-tab-links"><?php echo esc_html__('Social Links', 'awp'); ?></a>
                    <a href="#" class="nav-tab" data-tab="awp-tab-analytics"><?php echo esc_html__('Analytics', 'awp'); ?></a>
                    <a href="#" class="nav-tab" data-tab="awp-tab-settings"><?php echo esc_html__('Settings', 'awp'); ?></a>
                </div>
                  <!-- FIRST TAB: Set number (moved code) -->
                <div id="awp-tab-contacts" class="awp-tab" style="display:none;">
                    <div class="awp-card">
                        <div class="awp-settings-group">
                            <div class="card-header">
                                <label class="card-title" for="awp_enable_button"><?php echo esc_html__('Enable Chat Button','awp'); ?></label>
                                <p><?php echo esc_html__('Turn on to make the WhatsApp chat button visible on your website.','awp'); ?></p>
                            </div>
                          <div class="custom-control custom-switch">
                            <input type="checkbox" id="awp_enable_button" name="awp_enable_button" value="yes" <?php checked(get_option('awp_enable_button','yes'),'yes'); ?> class="custom-control-input" />
                            <label class="custom-control-label" for="awp_enable_button"></label>
                          </div>
                        </div>
                    </div>

                    <div class="awp-card">
                        <div class="card-header_row">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo esc_html__('WhatsApp Numbers','awp'); ?></h4>
                                <p><?php echo esc_html__('Add and manage multiple WhatsApp numbers and show in your support chat.','awp'); ?></p>
                            </div>
                        </div>
                        <div id="awp-repeatable-fields">
                        <?php
                            $numbers  = get_option('awp_whatsapp_numbers', []);
                            $messages = get_option('awp_whatsapp_messages', []);
                            $names    = get_option('awp_user_names', []);
                            $roles    = get_option('awp_user_roles', []);
                            $avatars  = get_option('awp_user_avatars', []);
                            
                            if (!empty($numbers)) {
                                foreach ($numbers as $i => $num) {
                                    $msg  = isset($messages[$i]) ? $messages[$i] : '';
                                    $name = isset($names[$i])    ? $names[$i]    : '';
                                    $role = isset($roles[$i])    ? $roles[$i]    : '';
                                    $avat = isset($avatars[$i])  ? $avatars[$i]  : '';
                        ?>
                        <div class="awp-repeatable-group">
                            
<div class="awp-contact-collapsed">
    <img src="<?php echo esc_url( $avat ?: $def_avatar ); ?>"
         class="awp-summary-avatar" />

    <div class="awp-summary-info">
        <span class="awp-summary-name">
            <i class="ri-user-3-line"></i> 
            <?php echo esc_html( 'Name: ' . $name ?: __( 'Empty', 'awp' ) ); ?>
        </span>
        <span class="awp-summary-number">
            <i class="ri-whatsapp-line"></i> 
            <?php echo esc_html( 'Number: ' . $num ); ?>
        </span>
        <span class="awp-summary-role">
            <i class="ri-briefcase-line"></i> 
            <?php echo esc_html( 'Role: ' . $role ?: __( 'Empty', 'awp' ) ); ?>
        </span>
    </div>

    <div class="awp-summary-actions">
        <button type="button" class="awp-btn edit-plain awp-edit-contact">
            <i class="ri-pencil-line"></i> <?php esc_html_e( 'Edit', 'awp' ); ?>
        </button>
        <button type="button" class="awp-btn delete-plain">
            <i class="ri-delete-bin-line"></i> <?php esc_html_e( 'Delete', 'awp' ); ?>
        </button>
    </div>
</div>



  <div class="awp-contact-expanded" style="display:none;">
                         
                          <div class="awp-field">
                            <label><i class="ri-whatsapp-line"></i> WhatsApp Number</label>
                            <input type="text" name="awp_whatsapp_numbers[]" 
                                   value="<?php echo esc_attr($num); ?>" 
                                   class="awp-whatsapp-number"/>
                          </div>
                          <div class="awp-field">
                            <label><i class="ri-user-3-line"></i> Name</label>
                            <input type="text" name="awp_user_names[]" 
                                   value="<?php echo esc_attr($name); ?>"/>
                          </div>
                          <div class="awp-field">
                            <label><i class="ri-chat-2-line"></i> Default Message</label>
                            <input type="text" name="awp_whatsapp_messages[]" 
                                   value="<?php echo esc_attr($msg); ?>"/>
                          </div>
                          <div class="awp-field">
                            <label><i class="ri-briefcase-line"></i> Role/Support Text</label>
                            <input type="text" name="awp_user_roles[]" 
                                   value="<?php echo esc_attr($role); ?>"/>
                          </div>
                          <div class="awp-field">
                            <label><i class="ri-image-line"></i> Avatar</label>
                            <div class="awp-upload-wrapper" style="background: #fff;">
                                <div class="btn-group" style="align-items: center;">
                                    <img src="<?php echo esc_url($avat); ?>">
                                    <span><?php echo esc_html($avat); ?></span>
                                </div>
                                <button type="button" 
                                    class="awp-btn awp-select-avatar-button">
                                    <?php esc_html_e('Upload','awp'); ?>
                                </button>
                            </div>
                            <input type="hidden" name="awp_user_avatars[]" 
                                   value="<?php echo esc_attr($avat); ?>" />
                          </div>
                          <div class="btn-group" style="justify-content: end;">
                            <button type="button" class="delete-plain">
                              <i class="ri-delete-bin-line"></i> Delete
                            </button>
                             <button type="button" class="awp-btn secondary awp-close-contact">
                                <i class="ri-check-line"></i> <?php esc_html_e('Done','awp'); ?>
                             </button>
      </div>
  </div>
         
                        </div>
                        <?php
                    }
                }
                ?>
                
                        </div>
                        <div class="awp-card-btn">
                            <button type="button" class="awp-add-plus awp-btn secondary">
                                <i class="ri-add-line"></i> <?php echo esc_html__('Add New Number','awp'); ?>
                            </button>
                        </div>
                    </div>
                                                       <div class="awp-card">
                        <div class="awp-settings-group">
                            <div class="card-header">
                                <label class="card-title" for="awp_disable_powered_by"><?php echo esc_html__('Disable "Powered by" link','awp'); ?></label>
                                <p><?php echo esc_html__('Turn on to hide the “Powered by” branding from your chat widget.','awp'); ?></p>
                            </div>
                            
                            <div class="custom-control custom-switch">
                                <input type="checkbox" id="awp_disable_powered_by" name="awp_disable_powered_by" value="yes" <?php checked(get_option('awp_disable_powered_by','no'),'yes'); ?> class="custom-control-input" />
                                <label class="custom-control-label" for="awp_disable_powered_by"></label>
                            </div>
                        </div>
                    </div>
                    <div class="awp-card awp-contact-card">
                        <div class="card-header_row">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo esc_html__('Main Card','awp'); ?></h4>
                                <p><?php echo esc_html__('Customize how your chat widget appears to visitors.','awp'); ?></p>
                            </div>
                        </div>
                        <hr class="h-divider">
                        <div class="awp-settings-group">
                          <label><i class="ri-edit-line"></i> <?php echo esc_html__('Headline','awp'); ?></label>
                          <input type="text" name="awp_whatsapp_header" value="<?php echo esc_attr(get_option('awp_whatsapp_header',__('Let\'s chat','awp'))); ?>" />
                        </div>
                        <div class="awp-settings-group">
                          <label><i class="ri-chat-1-line"></i> <?php echo esc_html__('Welcome Message','awp'); ?></label>
                          <input type="text" name="awp_welcome_message" value="<?php echo esc_attr(get_option('awp_welcome_message',__('How may we help you?','awp'))); ?>" />
                        </div>
                        <div class="awp-settings-group">
                          <label><i class="ri-time-line"></i> <?php echo esc_html__('Typical Reply Time','awp'); ?></label>
                          <input type="text" name="awp_reply_time_text" value="<?php echo esc_attr(get_option('awp_reply_time_text',__('Typically replies within minutes','awp'))); ?>" />
                        </div>
                        <div class="awp-settings-group">
                          <label><i class="ri-user-3-line"></i> <?php echo esc_html__('Default Profile Picture','awp'); ?></label>
                          <div class="awp-upload-wrapper">
                            <div class="btn-group" style="align-items: center;">
                                <img id="awp_avatar_preview" src="<?php echo esc_url(get_option('awp_avatar_url')); ?>" alt="Default Profile Picture">
                                <span><?php $avatar_url = esc_url(get_option('awp_avatar_url')); $avatar_name = basename($avatar_url); echo esc_html($avatar_name); ?></span>
                            </div>
                            <button type="button" class="awp-btn" id="awp_select_avatar_button"><?php echo esc_html__('Upload','awp'); ?></button>
                          </div>
                          <input type="hidden" id="awp_avatar_url" name="awp_avatar_url" value="<?php echo esc_attr(get_option('awp_avatar_url')); ?>" />
                        </div>
                      </div>
                      
                </div>
                <div id="awp-tab-links" class="awp-tab" style="display:none;">
                    <div class="awp-card">
                        <div class="card-header_row">
                            <div class="card-header">
                                <h4 class="card-title"><?php echo esc_html__('Add Your Social Media','awp'); ?></h4>
                                <p><?php echo esc_html__(' Add links to your social media profiles and display their icons at the bottom of the chat widget card.','awp'); ?></p>
                            </div>
                        </div>
                        <hr class="h-divider">
                        <div class="awp-settings-group">
                          <div class="awp-field">
                            <label for="awp_social_intro_text"><?php echo esc_html__('Intro Text:','awp'); ?></label>
                            <input type="text" id="awp_social_intro_text" name="awp_social_intro_text" value="<?php echo esc_attr($social_intro); ?>" style="width:100%;" />
                          </div>
                        </div>
                        <div id="awp-social-fields">
                          <?php
                          $social_icons = is_array($social_icons)?$social_icons:[];
                          $i=0;
                          foreach($social_icons as $s) {
                            $link = isset($s['link'])?$s['link']:'';
                            $icon = isset($s['icon'])?$s['icon']:'ri-global-fill';
                            ?>
                            <div class="awp-settings-group awp-social-group">
                              <div class="awp-field">
                                <label><?php echo esc_html__('Icon','awp'); ?></label>
                                <select name="awp_social_icons[<?php echo $i; ?>][icon]" class="awp-social-icon-select">
                                  <?php
                                  foreach($this->remix_icons_extended as $icon_class=>$icon_info){
                                    $sel2 = selected($icon_class,$icon,false);
                                    echo '<option value="'.esc_attr($icon_class).'" '.$sel2.'>'.esc_html($icon_info['label']).'</option>';
                                  }
                                  ?>
                                </select>
                            
                              </div>
                              <div class="awp-field">
                                <label><?php echo esc_html__('Link','awp'); ?></label>
                                <input type="url" name="awp_social_icons[<?php echo $i; ?>][link]" value="<?php echo esc_attr($link); ?>" style="max-width: 100%;"/>
                              </div>
                              <div class="awp-field" style="max-width: fit-content">
                                <button type="button" class="awp-remove-social delete-plain"><i class="ri-delete-bin-line"></i>Delete</button>
                              </div>
                            </div>
                            <?php
                            $i++;
                          }
                          ?>
                        </div>
                        <div class="awp-card-btn">
                            <button type="button" class="awp-btn secondary" id="awp-add-social-btn"><i class="ri-add-line"></i><?php echo esc_html__('Add New Link','awp'); ?></button>
                        </div>
                    </div>
                </div>
                <div id="awp-tab-analytics" class="awp-tab" style="display:none;">
                  <div class="awp-card">
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Clicks Tracking','awp'); ?></h4>  
                            <p><?php echo esc_html__('View all click for each contact below.','awp'); ?></p>
                        </div>
                        <button id="awp-clear-stats" class="awp-btn delete"><?php echo esc_html__('Clear Data','awp'); ?></button>
                    </div>
                    <div class="awp-cards" style="flex-direction: row;">
                        <div class="awp-card">
                            <div class="card-header">
                                <span class="card-label"><?php echo esc_html__('Chat Button Clicks','awp'); ?></span>
                                <span class="stats"><?php echo esc_html($chat_clicks); ?></span>    
                            </div>
                        </div>
                        <div class="awp-card">
                            <div class="card-header">
                                <span class="card-label"><?php echo esc_html__('WhatsApp Opens','awp'); ?></span>
                                <span class="stats"><?php echo esc_html($open_total); ?></span>    
                            </div>
                        </div>
                    </div>
                                    <table class="widefat striped">
                      <thead>
                        <tr>
                          <th><?php echo esc_html__('WhatsApp Number','awp'); ?></th>
                          <th><?php echo esc_html__('Name','awp'); ?></th>
                          <th><?php echo esc_html__('Chat Button Clicks','awp'); ?></th>
                          <th><?php echo esc_html__('WhatsApp Opens','awp'); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php
                      if(!empty($contactStats)) {
                        foreach($contactStats as $phone=>$data) {
                          $uname = isset($data['name'])?$data['name']:esc_html__('Unknown','awp');
                          $cC = isset($data['chat_clicks'])?$data['chat_clicks']:0;
                          $oW = isset($data['open_whatsapp'])?$data['open_whatsapp']:0;
                          ?>
                          <tr>
                            <td><?php echo esc_html($phone); ?></td>
                            <td><?php echo esc_html($uname); ?></td>
                            <td><?php echo esc_html($cC); ?></td>
                            <td><?php echo esc_html($oW); ?></td>
                          </tr>
                          <?php
                        }
                      } else {
                        echo '<tr><td colspan="4">'.esc_html__('No data yet','awp').'</td></tr>';
                      }
                      ?>
                      </tbody>
                    </table>
    
                  </div>
                  <div class="awp-card">
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Top Clicks by Page','awp'); ?></h4>
                            <p><?php echo esc_html__('Displays the top 10 pages with chat button clicks and WhatsApp opens.','awp'); ?></p>
                        </div>
                    </div>
                    <table class="widefat striped">
                      <thead>
                        <tr>
                          <th><?php echo esc_html__('Page','awp'); ?></th>
                          <th><?php echo esc_html__('Chat Button Clicks','awp'); ?></th>
                          <th><?php echo esc_html__('WhatsApp Opens','awp'); ?></th>
                          <th><?php echo esc_html__('Total','awp'); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php
                      if(!empty($topTen)) {
                        foreach($topTen as $pid=>$data) {
                          $cC = isset($data['chat_clicks'])?$data['chat_clicks']:0;
                          $oW = isset($data['open_whatsapp'])?$data['open_whatsapp']:0;
                          $ttl = $cC+$oW;
                          if($pid==0) {
                            $pageTitle = esc_html__('(No post/page context)','awp');
                            $pageLink = '#';
                          } else {
                            $pObj = get_post($pid);
                            if ($pObj) {
                              $pageTitle = $pObj->post_title;
                              $pageLink = get_permalink($pid);
                            } else {
                              $pageTitle = esc_html__('(Unknown Page)','awp');
                              $pageLink = '#';
                            }
                          }
                          ?>
                          <tr>
                            <td><a href="<?php echo esc_url($pageLink); ?>" target="_blank"><?php echo esc_html($pageTitle); ?></a></td>
                            <td><?php echo esc_html($cC); ?></td>
                            <td><?php echo esc_html($oW); ?></td>
                            <td><?php echo esc_html($ttl); ?></td>
                          </tr>
                          <?php
                        }
                      } else {
                        echo '<tr><td colspan="4">'.esc_html__('No page data yet','awp').'</td></tr>';
                      }
                      ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div id="awp-tab-settings" class="awp-tab" style="display:none;">
                    <div class="awp-card">
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Display Settings','awp'); ?></h4>
                        </div>
                    </div>
                    <hr class="h-divider">
                    <div class="awp-settings-group">
                      <label for="awp_display_desktop"><i class="ri-computer-line"></i><?php echo esc_html__('Show on Desktop','awp'); ?></label>
                      <div class="custom-control custom-switch">
                        <input type="checkbox" id="awp_display_desktop" name="awp_display_desktop" value="yes" <?php checked(get_option('awp_display_desktop','yes'),'yes'); ?> class="custom-control-input" />
                        <label class="custom-control-label" for="awp_display_desktop"></label>
                      </div>
                    </div>
                    <div class="awp-settings-group">
                      <label for="awp_display_mobile"><i class="ri-smartphone-line"></i> <?php echo esc_html__('Show on Mobile','awp'); ?></label>
                      <div class="custom-control custom-switch">
                        <input type="checkbox" id="awp_display_mobile" name="awp_display_mobile" value="yes" <?php checked(get_option('awp_display_mobile','yes'),'yes'); ?> class="custom-control-input" />
                        <label class="custom-control-label" for="awp_display_mobile"></label>
                      </div>
                    </div>
                    </div>
                  <div class="awp-card">
                    <div class="card-header_row">
                        <div class="card-header">
                            <h4 class="card-title"><?php echo esc_html__('Where to Show','awp'); ?></h4>
                        </div>
                    </div>
                    <hr class="h-divider">
                    <div class="awp-settings-group">
                      <label for="awp_trigger_on_all_pages"><?php echo esc_html__('Show on all pages','awp'); ?></label>
                      <div class="custom-control custom-switch">
                        <input type="checkbox" id="awp_trigger_on_all_pages" name="awp_trigger_on_all_pages" value="yes" <?php checked($trigger_on_all_pages,'yes'); ?> class="custom-control-input" />
                        <label class="custom-control-label" for="awp_trigger_on_all_pages"></label>
                      </div>
                    </div>
                    <hr class="h-divider">
                    <div id="awp-conditions-wrapper" style="<?php echo ($trigger_on_all_pages==='yes')?'opacity:0.4; pointer-events:none;':''; ?>">
                      <label style="margin-bottom: 6px;"><?php echo esc_html__("Exclude Pages:",'awp'); ?></label>
                      <div id="awp-condition-fields">
                        <?php
                        if(!empty($page_conditions)) {
                          foreach($page_conditions as $index=>$pid) {
                            ?>
                            <div class="awp-settings-group awp-condition-group">
                              <select name="awp_page_conditions[<?php echo $index; ?>]">
                                <option value="0">-- <?php echo esc_html__('Select page','awp'); ?> --</option>
                                <?php
                                foreach($all_pages as $pg) {
                                  echo '<option value="'.$pg->ID.'" '.selected($pg->ID,$pid,false).'>'.esc_html($pg->post_title).'</option>';
                                }
                                ?>
                              </select>
                              <button type="button" class="awp-remove-condition delete-plain"><i class="ri-delete-bin-line"></i>Delete</button>
                            </div>
                            <?php
                          }
                        }
                        ?>
                      </div>
                      <div id="awp-condition-template" style="display:none;">
                        <div class="awp-settings-group awp-condition-group">
                          <select name="">
                            <option value="0">-- <?php echo esc_html__('Select page','awp'); ?> --</option>
                            <?php
                            foreach($all_pages as $pg) {
                              echo '<option value="'.$pg->ID.'">'.esc_html($pg->post_title).'</option>';
                            }
                            ?>
                          </select>
                          <button type="button" class="awp-remove-condition"><i class="ri-delete-bin-line"></i></button>
                        </div>
                      </div>
                      <div class="awp-card-btn">
                         <button type="button" class="awp-btn secondary" id="awp-add-condition"><?php echo esc_html__('Add New Condition','awp'); ?></button>
                      </div>
                    </div>
                  </div>

                    <div class="awp-card">
                    <div class="awp-settings-group">
                      <label class="card-title" for="awp_button_position"><?php echo esc_html__('Button Position','awp'); ?></label>
                      <div class="radios">
                        <div class="awp-radio">
                            <input type="radio" id="awp_position_left" name="awp_button_position" value="left" <?php checked(get_option('awp_button_position','left'),'left'); ?>>
                            <label for="awp_position_left"><?php echo esc_html__('Left','awp'); ?></label>
                        </div>
                        <div class="awp-radio">
                            <input type="radio" id="awp_position_right" name="awp_button_position" value="right" <?php checked(get_option('awp_button_position','right'),'right'); ?>>
                            <label for="awp_position_right"><?php echo esc_html__('Right','awp'); ?></label>
                        </div>
                      </div>
                    </div>
                  </div>
                    <div class="awp-card">
                      <h4 class="card-title"><?php echo esc_html__('Customize Style','awp'); ?></h4>
                      <hr class="h-divider">
                      <div class="awp-settings-group" style="flex-direction: column;align-items: start;gap: .5rem;">
                        <label for="awp_whatsapp_icon_class"><?php echo esc_html__('Chat Icon','awp'); ?></label>
                        <select name="awp_whatsapp_icon_class" id="awp_whatsapp_icon_class" class="awp-social-icon-select">
                          <?php
                          foreach($this->chat_whatsapp_icons as $iconClass=>$labelText){
                              $selected = selected($iconClass,$main_icon_class,false);
                              echo '<option value="'.esc_attr($iconClass).'" '.$selected.'</option>';
                          }
                          ?>
                        </select>
                      </div>
                      <hr class="h-divider">
                      <div class="awp-settings-group">
                        <label for="awp_button_size"><?php echo esc_html__('Icon Size','awp'); ?></label>
                        <div class="btn-group">
                            <input type="range" id="awp_button_size" name="awp_button_size" min="30" max="100" value="<?php echo esc_attr(get_option('awp_button_size','60')); ?>" />
                            <span id="awp_button_size_value"><?php echo esc_html(get_option('awp_button_size','60')); ?>px</span>
                        </div>
                      </div>
                      <div class="awp-settings-group">
                        <label for="awp_corner_radius"><?php echo esc_html__('Corner Radius','awp'); ?></label>
                        <div class="btn-group">
                            <input type="range" id="awp_corner_radius" name="awp_corner_radius" min="0" max="50" value="<?php echo esc_attr(get_option('awp_corner_radius','50')); ?>" />
                            <span id="awp_corner_radius_value"><?php echo esc_html(get_option('awp_corner_radius','50')); ?>%</span>
                        </div>
                      </div>
                  </div>
                    <div class="awp-card">
                      <h4 class="card-title"><?php echo esc_html__('Change Colors','awp'); ?></h4>
                      <hr class="h-divider">
                      <div class="awp-color-group">
                          <div class="awp-settings-group" >
                            <label for="awp_button_bg_color"><?php echo esc_html__('Icon Background','awp'); ?></label>
                            <input type="text" id="awp_button_bg_color" name="awp_button_bg_color" value="<?php echo esc_attr($button_bg); ?>" style="width:100%;"/>
                          </div>
                          <div class="awp-settings-group">
                            <label for="awp_icon_color"><?php echo esc_html__('Icon Color','awp'); ?></label>
                            <input type="text" id="awp_icon_color" name="awp_icon_color" value="<?php echo esc_attr($icon_col); ?>" />
                          </div>
                      </div>
                  </div>
                  
                </div>
                <?php submit_button(); ?>
              </form>
            </div>
        </div>
        <?php
    }
    
    public function render_whatsapp_button() {
        static $rendered = false;
        
        if ($rendered) {
            return; 
        }
        $rendered = true;
        
        if (get_option('awp_enable_button','yes')!=='yes') {
            return;
        }
          $numbers = get_option('awp_whatsapp_numbers', []);

    if (empty($numbers)) {
        return;
    }
        $desktop = get_option('awp_display_desktop','yes');
        $mobile = get_option('awp_display_mobile','yes');
        if (!(($desktop==='yes'&&!wp_is_mobile())||($mobile==='yes'&&wp_is_mobile()))) {
            return;
        }

        $trigger_on_all = get_option('awp_trigger_on_all_pages','yes');
        if ($trigger_on_all!=='yes') {
            $conditions = get_option('awp_page_conditions',[]);
            global $post;
            $current_id = $post?$post->ID:0;
            if (in_array($current_id,$conditions)) {
                return;
            }
        }
        $numbers = get_option('awp_whatsapp_numbers',[]);
        $messages = get_option('awp_whatsapp_messages',[]);
        $names = get_option('awp_user_names',[]);
        $roles = get_option('awp_user_roles',[]);
        $avatars = get_option('awp_user_avatars',[]);
        $social_style = get_option('awp_social_button_style','round');
        $social_intro = get_option('awp_social_intro_text',__('Reach us on:','awp'));
        $social_icons = get_option('awp_social_icons',[]);
        $header = esc_html(get_option('awp_whatsapp_header',__('Let\'s chat','awp')));
        $welcome = esc_html(get_option('awp_welcome_message',__('How may we help you?','awp')));
        $reply_time = esc_html(get_option('awp_reply_time_text',__('Typically replies within minutes','awp')));
        $btn_size = (int)get_option('awp_button_size',60);
        $corner = (int)get_option('awp_corner_radius',50);
        $pos = get_option('awp_button_position','right');
        $btn_bg = get_option('awp_button_bg_color','linear-gradient(135deg, #2ee168, #00c66b)');
        $mainIconClass = get_option('awp_whatsapp_icon_class','ri-chat-3-line');
        $icon_url = get_option('awp_icon_url',plugin_dir_url(__FILE__).'WhatsApp_icon.png');
        $def_avatar = get_option('awp_avatar_url',plugin_dir_url(__FILE__).'WhatsApp_avatar.png');
        $icon_color = get_option('awp_icon_color','#fff');
        $pos_css = ($pos==='left')?'left:16px;':'right:16px;';
        $pos_window_css = ($pos==='left')?'left:0;':'right:0;';

        $dir = (is_rtl()) ? 'right' : 'left';
        $pos_dir = ($dir === 'left') ? 'right:24px;' : 'left:24px;';
        $btn_back = ($dir === 'left') 
        ? '<i class="ri-arrow-left-line awp-qr-back" id="awp-qr-back"></i>' 
        : '<i class="ri-arrow-right-line awp-qr-back" id="awp-qr-back"></i>';
        
        $transformOrigin = ($pos === 'left') ? 'transform-origin:bottom left;' : 'transform-origin:bottom right;';

        ?>
        <div id="awp-chat-wrapper" style="<?php echo $pos_css; ?>">
        <div id="awp-whatsapp-button" class="awp-whatsapp-button"
             style="width:<?php echo $btn_size; ?>px; height:<?php echo $btn_size; ?>px;
                    border-radius:<?php echo $corner; ?>%;
                    background:<?php echo esc_attr($btn_bg); ?>; display:flex;">
            <?php
            $iconChoice = $mainIconClass;
            $iconSize = floor($btn_size*0.6);
            if (isset($this->preset_svgs[$iconChoice])) {
                $svgCode = $this->preset_svgs[$iconChoice];
                echo '<div class="awp-custom-svg" style="width:'.$iconSize.'px; height:auto; color:'.esc_attr($icon_color).';">'.$svgCode.'</div>';
            } elseif (!empty($iconChoice)) {
                echo '<i class="'.esc_attr($iconChoice).'" style="font-size:'.$iconSize.'px; color:'.esc_attr($icon_color).';"></i>';
            } else {
                ?>
                <img src="<?php echo esc_url($icon_url); ?>" alt="WhatsApp"
                     style="width:100%; height:auto; border-radius:inherit;">
                <?php
            }
            ?>
        </div>
        <div id="awp-chat-window" class="awp-chat-window" style="background:linear-gradient(135deg, #044 50%, #00c66b); <?php echo $transformOrigin; ?><?php echo $pos_window_css; ?>">
            <div class="awp-chat-header">
                <img src="<?php echo esc_url($def_avatar); ?>" alt="<?php echo esc_attr__('Avatar','awp'); ?>" class="awp-header-avatar" />
                <div>
                    <div class="awp-widget-title"><?php echo $header; ?></div>
                    <div class="awp-widget-message"><?php echo $welcome; ?></div>
                </div>
                <i class="ri-subtract-fill awp-minimize-icon" id="awp-minimize-icon" style="<?php echo $pos_dir; ?>"></i>
            </div>
            <div class="awp-chat-data">
                <div class="awp-reply-time-container">
                    <div class="awp-reply-time-bubble"><i class="ri-time-line"></i><?php echo $reply_time; ?></div>
                </div>
                <div class="awp-chat-content" id="awp-contact-list">
                    <?php
                    foreach($numbers as $i=>$num):
                        $avt = isset($avatars[$i])?$avatars[$i]:$def_avatar;
                        $nm = isset($names[$i])?$names[$i]:esc_html__('John Doe','awp');
                        $rl = isset($roles[$i])?$roles[$i]:esc_html__('Support','awp');
                        $msg = isset($messages[$i])?$messages[$i]:esc_html__('Hello!','awp');
                        $current_url = get_permalink();
                        
                        $msg_with_link = $msg . ' | ' . __('I am on this page: ','awp') . $current_url;
                        
                        $link = 'https://api.whatsapp.com/send?phone='.esc_attr($num).'&text='.urlencode($msg_with_link);

                        ?>
                        <div class="awp-contact-item">
                            <div class="awp-avatar-wrapper">
                                <img src="<?php echo esc_url($avt); ?>" alt="<?php echo esc_attr__('Avatar','awp'); ?>">
                                <span class="awp-online-dot"></span>
                            </div>
                            <div class="awp-contact-info">
                                <span class="awp-contact-name"><?php echo esc_html($nm); ?></span>
                                <span class="awp-contact-role"><?php echo esc_html($rl); ?></span>
                            </div>
                            <div class="btns-group">
                                <a href="<?php echo esc_url($link); ?>" target="_blank"><i class="ri-whatsapp-line awp-wa-chat-icon"></i></a>
                                <i class="ri-qr-scan-2-line awp-qr-chat-icon" data-awp-link="<?php echo esc_url($link); ?>"></i>
                            </div>
                        </div>
                        <hr style="width: -moz-available;margin: 0;border: 0;border-top: 1px solid #ededed;">
                    <?php endforeach; ?>
                </div>
                <div class="awp-qr-card" id="awp-qr-card" style="display:none;">
                    <div class="awp-qr-header">
                        <?php echo $btn_back; ?>
                        <div class="awp-widget-title" style="width: 100%;text-align: center;"><?php echo esc_html__('Scan QR code','awp'); ?></div>
                    </div>
                    <div class="awp-qr-body">
                        <div id="awp-dynamic-qr"></div>
                        <a href="#" target="_blank" class="awp-open-whatsapp" id="awp-open-whatsapp"><?php echo esc_html__('Open WhatsApp','awp'); ?></a>
                    </div>
                </div>
                <?php if(!empty($social_icons)): ?>
                <div class="awp-social-container">
                    <span><?php echo esc_html($social_intro); ?></span>
                    <div class="awp-social-icons-list awp-style-<?php echo esc_attr($social_style); ?>">
                        <?php
                        foreach($social_icons as $sicon):
                            $url = isset($sicon['link'])?$sicon['link']:'';
                            $icon = isset($sicon['icon'])?$sicon['icon']:'ri-global-fill';
                            if(!$url) continue;
                            $brandColor = isset($this->remix_icons_extended[$icon])?$this->remix_icons_extended[$icon]['color']:'#666';
                            $inlineStyle = '';
                            if($social_style==='colored'){
                                $inlineStyle = 'color:'.$brandColor.';';
                            }
                            ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="awp-social-link">
                                <i class="<?php echo esc_attr($icon); ?>" style="font-size:24px; <?php echo esc_attr($inlineStyle); ?>"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
              <div class="awp-powered_by">
  <?php if (get_option('awp_disable_powered_by', 'no') !== 'yes'): ?>
          
                <?php echo esc_html__('Powered by','awp'); ?>
                <a href="https://wawp.net" target="_blank"><img src="<?php echo esc_url(AWP_PLUGIN_URL . 'assets/img/wawp-logo.png'); ?>"
                 alt="<?php echo esc_attr__('Wawp logo', 'awp'); ?>"></a>
           
            <?php endif; ?>
             </div>
        </div>
        </div>
        <?php
    }
    
}
new WAWP_Chat_Widget();
