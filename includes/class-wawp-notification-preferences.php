<?php
    if ( ! defined( 'ABSPATH' ) ) { exit; }
    
    class AWP_Notification_Preferences {
    
    	const META_LOGIN = 'awp_disable_login_notifications';
    	const META_WC    = 'awp_disable_wc_notifications';
    
    	public function __construct() {
    		add_shortcode( 'awp_notification_prefs', [ $this, 'render_shortcode' ] );
    		add_action( 'wp_enqueue_scripts',        [ $this, 'assets' ] );
    		add_action( 'wp_ajax_awp_toggle_prefs',  [ $this, 'ajax_toggle' ] );
    	}
    
        public function assets() {
        if ( ! is_user_logged_in() ) {
            return;
        }
    
        wp_register_script(
            'awp-prefs',
            AWP_PLUGIN_URL . 'assets/js/awp-prefs.js',
            [ 'jquery' ],
            AWP_PLUGIN_VERSION,
            true
        );
        wp_localize_script(
            'awp-prefs',
            'awpPrefs',
            [
                'ajax'  => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'awp_toggle_prefs' ),
                'i18n'  => [
                    'saving' => __( 'Saving…', 'awp' ),
                    'saved'  => __( 'Saved!',  'awp' ),
                    'error'  => __( 'Error',   'awp' ),
                ],
            ]
        );
    
        wp_register_style(
            'awp-prefs',
            AWP_PLUGIN_URL . 'assets/css/awp-prefs.css',
            [],
            AWP_PLUGIN_VERSION
        );
    
        wp_enqueue_script( 'awp-prefs' );
        wp_enqueue_style ( 'awp-prefs' );
    }
    
        public function render_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'You need to log in to manage your notifications.', WAWP_NOTIF_SLUG ) . '</p>';
        }
    
        $uid       = get_current_user_id();
        $login_off = get_user_meta( $uid, self::META_LOGIN, true );
        $wc_off    = get_user_meta( $uid, self::META_WC,    true );
    
        ob_start(); ?>
        <div id="awp-prefs-wrap" class="awp-card">
    
            <h3 class="awp-card-title"><?php _e( 'Notification preferences', WAWP_NOTIF_SLUG ); ?></h3>
    
            <!-- login switch -->
            <div class="awp-row">
                <label class="awp-switch">
                    <input type="checkbox"
                           id="awp-login-pref"
                           <?php checked( $login_off, '' ); ?>>
                    <span class="awp-slider"></span>
                </label>
                <span class="awp-switch-label">
                    <?php _e( 'Receive login notifications', WAWP_NOTIF_SLUG ); ?>
                </span>
            </div>
    
            <!-- WooCommerce switch -->
            <div class="awp-row">
                <label class="awp-switch">
                    <input type="checkbox"
                           id="awp-wc-pref"
                           <?php checked( $wc_off, '' ); ?>>
                    <span class="awp-slider"></span>
                </label>
                <span class="awp-switch-label">
                    <?php _e( 'Receive Orders notifications', WAWP_NOTIF_SLUG ); ?>
                </span>
            </div>
    
            <button id="awp-save-prefs" class="awp-btn awp-btn-primary">
                <?php _e( 'Save preferences', WAWP_NOTIF_SLUG ); ?>
            </button>
    
            <!-- toast / status -->
            <span id="awp-prefs-msg" class="awp-toast" aria-live="polite"></span>
        </div>
        <?php
        return ob_get_clean();
    }
    
    	public function ajax_toggle() {
        check_ajax_referer( 'awp_toggle_prefs', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'Not logged in', WAWP_NOTIF_SLUG ) );
        }
        $uid = get_current_user_id();
    
        $login_pref = filter_var( $_POST['login'] ?? true, FILTER_VALIDATE_BOOLEAN );
        $wc_pref    = filter_var( $_POST['wc']    ?? true, FILTER_VALIDATE_BOOLEAN );
    
        update_user_meta( $uid, self::META_LOGIN, $login_pref ? '' : '1' );
        update_user_meta( $uid, self::META_WC,    $wc_pref    ? '' : '1' );
    
        if ( ! $login_pref || ! $wc_pref ) {
    
            $number = '';
            if ( class_exists( 'AWP_Database_Manager' ) ) {
                $info = ( new AWP_Database_Manager() )->get_user_info( $uid );
                $number = $info && ! empty( $info->phone ) ? $info->phone : '';
            }
            if ( ! $number ) {
                $number = get_user_meta( $uid, 'billing_phone', true );
            }
        }
    
        wp_send_json_success();
    }

    	public static function user_opted_out( $user_id, $type ) {
    		if ( ! $user_id ) { return false; }
    		switch ( $type ) {
    			case 'login':
    				return (bool) get_user_meta( $user_id, self::META_LOGIN, true );
    			case 'wc':
    				return (bool) get_user_meta( $user_id, self::META_WC, true );
    			default:
    				return false;
    		}
    	}
    	
    }
    new AWP_Notification_Preferences();
    
    add_filter(
    	'wawp_notif_before_send', 
    	function ( $should_send, $rule, $recipient ) {
    
    		if ( empty( $recipient['user_id'] ) ) {
    			return $should_send;
    		}
    
    		$uid = $recipient['user_id'];
    
    		if ( $rule['trigger_key'] === 'user_login' &&
    		     AWP_Notification_Preferences::user_opted_out( $uid, 'login' ) ) {
    			return false;
    		}
    
    		if ( str_starts_with( $rule['trigger_key'], 'wc_' ) &&
    		     AWP_Notification_Preferences::user_opted_out( $uid, 'wc' ) ) {
    			return false;
    		}
    
    		return $should_send;
    	},
    	10,
    	3
    );
