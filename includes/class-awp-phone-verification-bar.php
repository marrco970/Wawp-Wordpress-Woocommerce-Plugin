<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AWP_Phone_Verification_Bar {

    private $db;

    public function __construct() {
        $this->db = new AWP_Database_Manager();

        if ( get_option( 'awp_show_phone_bar_in_account', 1 ) ) {
            add_action( 'woocommerce_before_my_account',  [ $this, 'render_bar' ] );
        }

        add_shortcode( 'wawp_phone_verification_bar', [ $this, 'render_bar' ] );
        add_action( 'wp_enqueue_scripts',             [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_awp_send_phone_otp',     [ $this, 'send_phone_otp' ] );
        add_action( 'wp_ajax_awp_verify_phone_otp',   [ $this, 'verify_phone_otp' ] );
        add_action( 'wp_ajax_awp_update_phone_number',[ $this, 'update_phone_number' ] );
    }

    public function render_bar() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $info    = $this->db->get_user_info( get_current_user_id() );
        if ( $info && $info->whatsapp_verified === 'Verified' ) {
            return;
        }

        $is_new  = ( ! $info || empty( $info->phone ) );
        $phone   = $is_new ? '' : esc_html( $info->phone );

        wp_nonce_field( 'awp_phone_verify', 'awp_phone_verify_nonce' );
        ?>
        <div id="awp-phone-bar" style="position:relative;width:100%;padding:14px 22px;margin-bottom:20px;margin-top:20px;background:#fff8d2;border:1px solid #ffe58f;border-left:4px solid #ffcf00;display:flex;flex-wrap:wrap;align-items:center;gap:8px;border-radius:6px;">
            <style>
                #awp-phone-bar .button{border-radius:5px;}
                #awp-send-otp,#awp-save-phone{background:#000;color:#fff;margin-inline-start:auto;}
                #awp-confirm-otp{background:#22c55e;color:#fff;}
                #awp-otp-input,#awp-new-phone{border-radius:5px;width:100%!important;}
                #awp-edit-phone{cursor:pointer;color:#555;margin-left:6px;}
                #awp-bar-close{position:absolute;top:4px;right:8px;background:transparent;border:none;font-size:20px;line-height:1;cursor:pointer;color:#666;}
            </style>

            <span id="awp-add-text" style="<?php echo $is_new ? '' : 'display:none;'; ?>">
                <?php _e( 'Add your number to access all features and login via WhatsApp OTP.', 'awp' ); ?>
            </span>

            <span id="awp-bar-text" style="<?php echo $is_new ? 'display:none;' : ''; ?>">
                <?php _e( 'Confirm your number', 'awp' ); ?>
                <strong id="awp-phone-display"><?php echo $phone; ?></strong>
                <i id="awp-edit-phone" class="ri-edit-line"<?php echo $is_new ? ' style="display:none;"' : ''; ?>></i>
                <?php _e( 'to get full access and login via WhatsApp OTP.', 'awp' ); ?>
            </span>

            <input id="awp-new-phone" type="tel" value="<?php echo $phone; ?>" style="width:180px;<?php echo $is_new ? '' : 'display:none;'; ?>">

            <button id="awp-save-phone" class="button"<?php echo $is_new ? '' : ' style="display:none;"'; ?>>
                <?php _e( 'Save', 'awp' ); ?>
            </button>

            <input id="awp-otp-input" type="text" placeholder="<?php echo esc_attr__( '123456', 'awp' ); ?>" style="display:none;width:110px">

            <button id="awp-send-otp" class="button"<?php echo $is_new ? ' style="display:none;"' : ''; ?>>
                <?php _e( 'Send OTP', 'awp' ); ?>
            </button>

            <button id="awp-confirm-otp" class="button" style="display:none">
                <?php _e( 'Confirm', 'awp' ); ?>
            </button>
        </div>

        <script>
        (function($){
            let cooldown=5,timer=null,isNew=<?php echo $is_new ? 'true' : 'false'; ?>;
            function ajax(action,data){
                return $.post(awpPhoneVerify.ajax,$.extend({security:awpPhoneVerify.nonce,action:action},data||{}));
            }
            function startCooldown(btn){
                btn.prop('disabled',true).addClass('disabled');
                function tick(){
                    btn.text('<?php echo esc_js(__('Resend OTP','awp')); ?> ('+cooldown+'s)');
                    if(--cooldown<0){
                        clearInterval(timer);
                        btn.prop('disabled',false).removeClass('disabled').text('<?php echo esc_js(__('Resend OTP','awp')); ?>');
                        cooldown=cooldown<=0?10:cooldown*2;
                    }
                }
                tick();
                timer=setInterval(tick,1000);
            }
            function hideIntl(){
                $('#awp-new-phone').closest('.iti').hide();
                $('#awp-new-phone').closest('.iti').next('.intl-tel-status').hide();
            }
            function showIntl(){
                $('#awp-new-phone').closest('.iti').show();
                $('#awp-new-phone').closest('.iti').next('.intl-tel-status').show();
            }
            $(function(){if(!isNew)hideIntl();});
            $('#awp-save-phone').on('click',function(){
                ajax('awp_update_phone_number',{phone:$('#awp-new-phone').val()}).done(function(r){
                    if(r.success){
                        $('#awp-phone-display').text(r.data.phone).show();
                        $('#awp-new-phone,#awp-save-phone').hide();
                        $('#awp-bar-text,#awp-edit-phone,#awp-send-otp').show();
                        hideIntl();
                        isNew=false;
                    }else alert(r.data);
                });
            });
            $('#awp-edit-phone').on('click',function(){
                $('#awp-phone-display,#awp-edit-phone').hide();
                $('#awp-new-phone,#awp-save-phone').show();
                $('#awp-send-otp').hide();
                    $('#awp-send-otp').hide();          // “Send / Resend OTP”
    $('#awp-otp-input,#awp-confirm-otp').hide();   // input + “Confirm”  ← add this line
                showIntl();
                $('#awp-new-phone').focus();
            });
            $('#awp-send-otp').on('click',function(){
                const b=$(this);
                ajax('awp_send_phone_otp').done(r=>{
                    if(r.success){
                        $('#awp-otp-input,#awp-confirm-otp').show();
                        if(!b.data('sent')){
                            b.data('sent',1).text('<?php echo esc_js(__('Resend OTP','awp')); ?>');
                        }
                        startCooldown(b);
                    }else alert(r.data);
                });
            });
            $('#awp-confirm-otp').on('click',function(){
                ajax('awp_verify_phone_otp',{code:$('#awp-otp-input').val()}).done(function(r){
                    if(r.success){
                        $('#awp-phone-bar').css({background:'#e6ffed',borderColor:'#22c55e',position:'relative'}).html('<button id="awp-bar-close">&times;</button><span style="display:flex;align-items:center;gap:6px;font-weight:600;color:#059669"><i class="ri-shield-check-line"></i> <?php echo esc_js(__('Thank you, your number is verified now','awp')); ?></span>');
                        $('#awp-bar-close').on('click',function(){$('#awp-phone-bar').slideUp(300);});
                        setTimeout(function(){$('#awp-phone-bar').slideUp(300);},10000);
                        window.location.reload();
                    }else alert(r.data);
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public function update_phone_number() {
        check_ajax_referer( 'awp_phone_verify','security' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'Login required', 'awp' ) );
        }
        $raw = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
        if ( ! preg_match( '/^\+?[0-9]{7,15}$/', $raw ) ) {
            wp_send_json_error( __( 'Invalid phone format', 'awp' ) );
        }
        $dup = get_users([
            'meta_key'   => 'awp-user-phone',
            'meta_value' => $raw,
            'exclude'    => [ get_current_user_id() ],
            'fields'     => 'ids',
            'number'     => 1
        ]);
        if ( $dup ) {
            wp_send_json_error( __( 'Phone already used.', 'awp' ) );
        }
        update_user_meta( get_current_user_id(), 'awp-user-phone', $raw );
        update_user_meta( get_current_user_id(), 'billing_phone', $raw );
        $info = $this->db->get_user_info( get_current_user_id() );
        if ( ! $info ) {
            $u = wp_get_current_user();
            $this->db->insert_user_info(
                $u->ID,
                get_user_meta( $u->ID, 'first_name', true ),
                get_user_meta( $u->ID, 'last_name', true ),
                $u->user_email,
                $raw,
                ''
            );
        } else {
            $this->db->update_user_phone( get_current_user_id(), $raw );
            $this->db->update_user_verification( get_current_user_id(), 'whatsapp', false );
        }
        wp_send_json_success( [ 'phone' => esc_html( $raw ) ] );
    }

    public function send_phone_otp() {
    check_ajax_referer( 'awp_phone_verify','security' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( __( 'Login required', 'awp' ) );
    }
    $info = $this->db->get_user_info( get_current_user_id() );
    if ( ! $info || empty( $info->phone ) ) {
        wp_send_json_error( __( 'No phone found', 'awp' ) );
    }
    $otp = random_int( 100000, 999999 );
    update_user_meta( get_current_user_id(), 'awp_phone_otp_hash', password_hash( $otp, PASSWORD_BCRYPT ) );
    update_user_meta( get_current_user_id(), 'awp_phone_otp_exp', time() + 600 );
    $inst = $this->instance();
    if ( ! $inst ) {
        wp_send_json_error( __( 'No online instance', 'awp' ) );
    }
    $msg = str_replace( '{{otp}}', $otp, __( 'Your OTP code is: {{otp}}', 'awp' ) );
    $res = Wawp_Api_Url::send_message( $inst->instance_id, $inst->access_token, $info->phone, $msg );
    $logger = new AWP_Log_Manager();
    $user   = wp_get_current_user();

    // Unwrap the response if it's nested
    $response_to_log = $res;
    if (isset($res['full_response'])) {
        $response_to_log = $res['full_response'];
    }

    $logger->log_notification([
        'user_id'          => get_current_user_id(),
        'order_id'         => null,
        'customer_name'    => trim( "{$user->first_name} {$user->last_name}" ) ?: $user->display_name,
        'sent_at'          => current_time( 'mysql' ),
        'whatsapp_number'  => $info->phone,
        'message'          => $msg,
        'image_attachment' => null,
        'message_type'     => __( 'Verify Bar', 'awp' ),
        'wawp_status'      => $response_to_log,
        'resend_id'        => null,
        'instance_id'      => $inst->instance_id,
        'access_token'     => $inst->access_token,
    ]);

    if ( $res['status'] === 'success' ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( $res['message'] );
    }
}

    public function verify_phone_otp() {
        check_ajax_referer( 'awp_phone_verify','security' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }
        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
        if ( ! preg_match( '/^\d{6}$/', $code ) ) {
            wp_send_json_error( __( 'Invalid OTP', 'awp' ) );
        }
        $hash = get_user_meta( get_current_user_id(), 'awp_phone_otp_hash', true );
        $exp  = (int) get_user_meta( get_current_user_id(), 'awp_phone_otp_exp', true );
        if ( ! $hash || time() > $exp ) {
            wp_send_json_error( __( 'OTP expired', 'awp' ) );
        }
        if ( ! password_verify( $code, $hash ) ) {
            wp_send_json_error( __( 'Incorrect OTP', 'awp' ) );
        }
        delete_user_meta( get_current_user_id(), 'awp_phone_otp_hash' );
        delete_user_meta( get_current_user_id(), 'awp_phone_otp_exp' );
        $this->db->update_user_verification( get_current_user_id(), 'whatsapp', true );
        $info = $this->db->get_user_info( get_current_user_id() );
        if ( $info && ! empty( $info->phone ) ) {
            update_user_meta( get_current_user_id(), 'billing_phone', $info->phone );
        }
        $logger = new AWP_Log_Manager();
        $user   = wp_get_current_user();
        $status = [
            'status'        => 'success',
            'message'       => __( 'OTP verified by user', 'awp' ),
            'full_response' => [ 'verified_at' => current_time( 'mysql' ) ]
        ];
        $logger->log_notification([
            'user_id'          => get_current_user_id(),
            'order_id'         => null,
            'customer_name'    => trim( "{$user->first_name} {$user->last_name}" ) ?: $user->display_name,
            'sent_at'          => current_time( 'mysql' ),
            'whatsapp_number'  => $info->phone,
            'message'          => __( 'OTP verification request', 'awp' ),
            'image_attachment' => null,
            'message_type'     => __( 'Verified Request', 'awp' ),
            'wawp_status'      => $status,
            'resend_id'        => null,
        ]);
        wp_send_json_success();
    }

    private function instance() {
        global $wpdb;
        return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}awp_instance_data WHERE status='online' LIMIT 1" );
    }

    public function enqueue_assets() {


	wp_localize_script(
		'jquery',
		'awpPhoneVerify',
		[
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'awp_phone_verify' ),
		]
	);
}

}

new AWP_Phone_Verification_Bar();