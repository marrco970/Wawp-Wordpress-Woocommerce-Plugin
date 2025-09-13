<?php

    if (!defined('ABSPATH')) {
        exit;
    }
    
    add_action( 'phpmailer_init', function ( PHPMailer\PHPMailer\PHPMailer $phpmailer ) {

    	$opts = get_option( 'awp_smtp_settings', [] );
    	if ( empty( $opts['enabled'] ) ) {
    		return;                        
    	}
    
    	$phpmailer->isSMTP();
    	$phpmailer->Host       = $opts['host']        ?? '';
    	$phpmailer->Port       = (int) ( $opts['port'] ?? 587 );
    	$encryption            = $opts['encryption']  ?? 'tls';  
    	$phpmailer->SMTPSecure = ( $encryption === 'none' ? '' : $encryption );
    	$phpmailer->SMTPAuth   = ! empty( $opts['auth'] );
    	if ( $phpmailer->SMTPAuth ) {
    		$phpmailer->Username = $opts['user'] ?? '';
    		$phpmailer->Password = $opts['pass'] ?? '';
    	}
    	$phpmailer->setFrom(
    		$opts['from_email'] ?? get_bloginfo( 'admin_email' ),
    		$opts['from_name']  ?? get_bloginfo( 'name' )
    	);
    }, 20 );

    class AWP_Instances {
        private $database_manager;
        private $wawp_domain = 'https://wawp.net';
        private $wawp_app_domain = 'https://wawp.net';
    
        public function __construct() {
            $this->database_manager = new AWP_Database_Manager();
        }
    
        public function init() {
            add_action('wp_ajax_awp_add_instance', [$this, 'add_instance']);
            add_action('wp_ajax_awp_delete_instance', [$this, 'delete_instance']);
            add_action('wp_ajax_awp_edit_instance', [$this, 'edit_instance']);
            add_action('wp_ajax_awp_update_status', [$this, 'update_status']);
            add_action('wp_ajax_awp_send_test_message', [$this, 'send_test_message']);
            add_action('admin_notices', [$this, 'admin_notices']);
            add_action('rest_api_init', [$this, 'register_instance_count_endpoint']);
            add_action('wp_ajax_awp_export_instances', [$this, 'export_instances']);
            add_action('wp_ajax_awp_import_csv_via_ajax', [$this, 'import_csv_via_ajax']);
            add_action('wp_ajax_awp_background_fetch_limits', [$this, 'background_fetch_limits']);
            add_action('wp_ajax_awp_get_all_instances', [$this, 'awp_get_all_instances_ajax']);
            add_action('wp_ajax_awp_auto_check_all_instance_status', [$this, 'auto_check_all_instance_status']);
            add_action( 'wp_ajax_awp_qr_poll_instance_status', [$this,'ajax_qr_poll_status'] );
            add_action( 'wp_ajax_nopriv_awp_qr_poll_instance_status', [$this,'ajax_qr_poll_status'] ); 
            add_action( 'wp_ajax_awp_smtp_send_test_email', [ $this, 'smtp_send_test_email' ] );
            add_action( 'wp_ajax_awp_smtp_test_connection', [ $this, 'smtp_test_connection' ] );
            add_action( 'wp_ajax_awp_get_auto_instances', [$this, 'get_auto_instances'] );
            add_action( 'wp_ajax_awp_add_auto_instance',  [$this, 'add_auto_instance'] );
            add_action('wp_ajax_awp_qr_create_new_instance_action', [$this, 'qr_create_new_instance_action']);
            add_action('wp_ajax_awp_qr_get_code_action', [$this, 'qr_get_code_action']);
            add_action('wp_ajax_awp_qr_check_connection_status_action', [$this, 'qr_check_connection_status_action']);
            add_action('wp_ajax_awp_qr_save_online_instance_action', [$this, 'qr_save_online_instance_action']);
            
            add_action( 'wp_ajax_awp_save_block_list', function () {
    
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'unauthorized', 403 );
            }
            check_ajax_referer( 'awp_block_list_nonce', 'nonce' );
        
            $raw  = wp_unslash( $_POST['list'] ?? '' );
            $list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        
            ( new AWP_Database_Manager() )->upsert_blocked_numbers( $list );
        
            wp_send_json_success( [ 'saved' => count( $list ) ] );
        } );
    
    
        }
    
        public function render_admin_page() {
        
        	if ( ! current_user_can( 'manage_options' ) ) {
        		return;
        	}
        
        	$banned_msg = get_transient( 'siteB_banned_msg' );
        	$token      = get_option( 'mysso_token' );
        
        	if ( $banned_msg ) : ?>
        		<div class="wrap awp-wrapper-instances">
        			<div class="awp-admin-notice-global"></div>
        			<h1><i class="ri-forbid-line"></i> <?php _e( 'Manage Wawp Instances', 'awp' ); ?></h1>
        			<p style="color:red;"><?php echo Wawp_Global_Messages::get( 'blocked_generic' ); ?></p>
        		</div>
        		<?php return;
        	endif;
        
        	if ( ! $token ) : ?>
        		<div class="wrap awp-wrapper-instances">
        			<div class="awp-admin-notice-global"></div>
        			<h1><i class="ri-lock-line"></i> <?php _e( 'Manage Wawp Instances', 'awp' ); ?></h1>
        			<p><?php echo Wawp_Global_Messages::get( 'need_login' ); ?></p>
        		</div>
        		<?php return;
        	endif;
        
        	$mc_initial_html            = '<span class="awp-data-loading">...</span>';
        	$val_initial_html           = '<span class="awp-data-loading">...</span>';
        	$dis_initial_add_button_css = 'display:none;';
        	$dis_initial_qr_button_css  = 'display:none;';
        	$over_limit_msg_css         = 'display:none;';
        	$api_access_token           = get_option( 'wawp_access_token' );
        	$user_data                  = get_transient( 'siteB_user_data' );
        	?>
        
        	<div class="wrap">
        
        		<div class="page-header_row">
        			<div class="page-header">
        				<h2 class="page-title"><?php _e( 'Sender Settings', 'awp' ); ?></h2>
        				<p>
        					<?php _e( 'Configure email and WhatsApp sending preferences and control blocked numbers.', 'awp' ); ?>
        				</p>
        		        <div class="awp-admin-notice-global"></div>
        			</div>
        		</div>
        
        		<div class="nav-tab-wrapper" id="awp-sender-tabs" style="margin-bottom: 1.25rem;">
        			<a href="#tab-email" class="nav-tab nav-tab-active"><?php _e( 'Email Smtp', 'awp' ); ?></a>
        			<a href="#tab-wa"    class="nav-tab"><?php _e( 'WhatsApp Web', 'awp' ); ?></a>
        			<a href="#tab-block" class="nav-tab"><?php _e( 'Block Manager', 'awp' ); ?></a>
        		</div>
        
        		<div class="awp-tab-content">
        
        			<div id="tab-email" class="awp-tab-pane active">
        				<?php
        					$this->maybe_save_smtp_settings();
        					$this->render_smtp_settings_form();
        				?>
    
                </div></div>
        			<div id="tab-wa" class="awp-tab-pane">
            				<div id="awp-add-modal" class="awp-modal">
        					<div class="awp-modal-content">
        						<h4 class="card-title"><i class="ri-whatsapp-line"></i> <?php _e( 'Add New Instance Manually', 'awp' ); ?></h4>
        
        						<form id="awp-add-instance-form">
        							<div class="awp-form-group">
        								<label><?php _e( 'Name', 'awp' ); ?></label>
        								<input type="text" id="awp-name" placeholder="<?php esc_attr_e( 'e.g., Main Business Line', 'awp' ); ?>">
        							</div>
        							<div class="awp-form-group">
        								<label><?php _e( 'Instance ID', 'awp' ); ?></label>
        								<input type="text" id="awp-instance-id">
        							</div>
        							<div class="awp-form-group">
        								<label><?php _e( 'Access Token', 'awp' ); ?></label>
        								<input type="text" id="awp-access-token">
        							</div>
        
        							<div class="btn-group" style="margin-top:12px;">
        								<button type="button" class="awp-btn" id="awp-close-add-modal" style="flex:1;"><?php _e( 'Cancel', 'awp' ); ?></button>
        								<button type="button" class="awp-btn primary" id="awp-save-add-btn" style="flex:1;"><?php _e( 'Save', 'awp' ); ?></button>
        							</div>
        						</form>
        					</div>
        				</div>
        
        				<div id="awp-edit-modal" class="awp-modal">
        					<div class="awp-modal-content">
        						<h4 class="card-title"><i class="ri-edit-line"></i> <?php _e( 'Edit Instance', 'awp' ); ?></h4>
        
        						<form id="awp-edit-instance-form">
        							<input type="hidden" id="edit-id">
        
        							<div class="awp-form-group">
        								<label><?php _e( 'Name', 'awp' ); ?></label>
        								<input type="text" id="edit-name">
        							</div>
        							<div class="awp-form-group">
        								<label><?php _e( 'Instance ID', 'awp' ); ?></label>
        								<input type="text" id="edit-instance-id">
        							</div>
        							<div class="awp-form-group">
        								<label><?php _e( 'Access Token', 'awp' ); ?></label>
        								<input type="text" id="edit-access-token">
        							</div>
        
        							<div class="btn-group" style="margin-top:12px;">
        								<button type="button" class="awp-btn" id="awp-close-edit-modal" style="flex:1;"><?php _e( 'Cancel', 'awp' ); ?></button>
        								<button type="button" class="awp-btn awp-save primary" id="awp-save-edit-btn" style="flex:1;"><?php _e( 'Save Changes', 'awp' ); ?></button>
        							</div>
        						</form>
        					</div>
        				</div>
        
            				<div id="awp-qr-modal" class="awp-modal">
        					<div class="awp-modal-content">
        						<h4 class="card-title"><i class="ri-qr-scan-2-line"></i> <?php _e( 'Scan QR Code to Connect WhatsApp', 'awp' ); ?></h4>
        
        						<div id="awp-qr-instance-id-display" style="text-align:center;margin-bottom:10px;font-size:1em;color:#333;padding:5px;background:#e9f5ff;border-radius:4px;margin-top:10px;"></div>
        
        						<div id="awp-qr-code-container" style="text-align:center;margin-bottom:10px;min-height:250px;display:flex;align-items:center;justify-content:center;border:1px solid #eee;background:#f9f9f9;padding:10px;box-shadow:0 0 10px rgba(0,0,0,.05);">
        							<p id="awp-qr-status-message" style="display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:1.1em;"><?php _e( 'Initializingâ€¦', 'awp' ); ?></p>
        							<img id="awp-qr-code-img" src="" alt="<?php esc_attr_e( 'QR Code', 'awp' ); ?>" style="display:none;max-width:250px;max-height:250px;height:auto;border:1px solid #ddd;">
        						</div>
        
        						<p id="awp-qr-polling-message" style="text-align:center;font-style:italic;font-size:.9em;color:#555;min-height:1.2em;margin-top:5px;"></p>
        
        						<div class="btn-group" style="margin-top:12px;">
        							<button type="button" class="awp-btn" id="awp-close-qr-modal" style="flex:1;"><?php _e( 'Close', 'awp' ); ?></button>
        						</div>
        					</div>
        				</div>
        
            				<div id="awp-auto-modal" class="awp-modal">
        					<div class="awp-modal-content" style="min-width:720px">
        						<h4 class="card-title" style="margin-bottom: 1.25rem;"><i class="ri-download-line"></i> <?php _e( 'Import instance from your Wawp account.', 'awp' ); ?></h4>
        
        						<style>
        							.awp-modal-content { position:relative; }
        							.awp-modal-loader{
        								position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,.9);
        								z-index:10;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:8px;}
        							.awp-modal-loader .awp-spinner{
        								border:5px solid #f3f3f3;border-top:5px solid #3498db;border-radius:50%;width:50px;height:50px;
        								animation:awp-spin 1.2s linear infinite;}
        							@keyframes awp-spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        						</style>
        
        						<div class="awp-modal-loader" style="display:none;">
        							<div class="awp-spinner"></div>
        							<p><?php _e( 'Processing, please waitâ€¦', 'awp' ); ?></p>
        						</div>
        
        						<table id="awp-auto-table" class="awp-table" style="margin-bottom:1rem">
        							<thead><tr>
        								<th><?php _e( 'WhatsApp Number', 'awp' ); ?></th>
        								<th><?php _e( 'Instance ID',      'awp' ); ?></th>
        								<th><?php _e( 'Access Token',     'awp' ); ?></th>
        								<th><?php _e( 'Action',           'awp' ); ?></th>
        							</tr></thead>
        							<tbody></tbody>
        						</table>
        
        						<div class="btn-group">
        							<button type="button" class="awp-btn" id="awp-close-auto-modal" style="flex:1;"><?php _e( 'Close', 'awp' ); ?></button>
        						</div>
        					</div>
        				</div>
        				<div class="awp-card" style="margin-bottom: 1.25rem;">
                            <div class="card-header_row">
                                <div class="card-header">
                                    <h4 class="card-title"><i class="ri-qr-scan-2-line"></i><?php _e( 'Connect your WhatsApp', 'awp' ); ?></h4>
                                    <p><?php
                                // translators: 1: opening <a> tag to WAWP Connect, 2: closing </a> tag.
                                printf(
                                    esc_html__( 'Scan the QR code at %1$sWawp.net%2$s, then import or manually add the access token and instance ID.', 'awp' ),
                                    '<a href="' . esc_url( 'https://wawp.net/account/connect' ) . '" target="_blank" rel="noopener noreferrer">',
                                    '</a>'); ?>
                                    </p>
                                </div>
                                <p id="awp-over-limit-msg" style="color:#f00;<?php echo esc_attr( $over_limit_msg_css ); ?>;width:100%;">
    									<?php echo Wawp_Global_Messages::get( 'overloaded_limit_Instances' ); ?>
    							</p>
                                <div class="btn-group">
            						<?php if ( isset( $user_data['auto_instances'] ) && $user_data['auto_instances'] === 'On' ) : ?>
            							<div class="btn-group">
            								<button type="button" class="awp-btn secondary" id="awp-open-auto-modal"
            								        style="<?php echo esc_attr( $dis_initial_add_button_css ); ?>">
            									<i class="ri-download-line"></i> <?php _e( 'Import', 'awp' ); ?>
            								</button>
            							</div>
            						<?php endif; ?>
    
    
    								<button type="button" class="awp-btn secondary" id="awp-open-add-modal"
    								        style="<?php echo esc_attr( $dis_initial_add_button_css ); ?>">
    									<i class="ri-add-line"></i> <?php _e( 'Add Manually', 'awp' ); ?>
    								</button>
    							</div>
    						</div>
                        </div>
        				<div class="awp-table-container-wrapper">
        					<div style="position:relative;">
        						<div class="awp-table-container awp-card" style="padding:0;">
        
        							<div class="card-header_row" style="padding:1.25rem 1.25rem 0;">
        								<div class="card-header">
        									<h4 class="card-title"><i class="ri-links-line"></i> <?php _e( 'Connected Numbers', 'awp' ); ?></h4>
        
        									<p>
        										<span id="awp-instance-count-wrapper"><?php echo $mc_initial_html; ?></span>
        										<?php
        											printf(
        												__( 'of %s numbers are available with your %sSubscription%s.', 'awp' ),
        												'<span id="awp-instance-limit-wrapper">'.$val_initial_html.'</span>',
        												'<a href="https://wawp.net/account/connect/" target="_blank">',
        												'</a>'
        											);
        										?>
        									</p>
        								</div>
        							</div>

        							<table class="awp-table">
        								<thead><tr>
        									<th><?php _e( 'Name',             'awp' ); ?></th>
        									<th><?php _e( 'Instance ID',      'awp' ); ?></th>
        									<th><?php _e( 'Access Token',     'awp' ); ?></th>
        									<th><?php _e( 'Connection Status','awp' ); ?></th>
        									<th><?php _e( 'Actions',          'awp' ); ?></th>
        								</tr></thead>
        								<tbody id="awp-table-body"></tbody>
        							</table>
        
        							<div id="awp-loading-spinner" class="awp-loading-spinner" style="display:flex;">
        								<i class="ri-loader-4-line"></i>
        							</div>
        						</div>
        					</div>
        				</div>
        
        				<?php $this->render_otp_senders_form(); ?>
        
        			</div>
            			<?php
        				$this->maybe_save_block_list();
        				$dbm        = new AWP_Database_Manager();
                        $block_text = implode( "\n", $dbm->get_blocked_numbers() );
        			?>
        			<div id="tab-block" class="awp-tab-pane">
        				<div class="awp-card">
        
        					<div class="card-header_row">
        						<div class="card-header">
        							<h4 class="card-title"><i class="ri-spam-3-line"></i><?php _e( 'Blocked Numbers', 'awp' ); ?></h4>
        							<p><?php _e( 'Blocked numbers will no longer be able to log in, sign up, purchase, or receive messages from your website.', 'awp' ); ?></p>
        						</div>
        					</div>
        
        					<div class="awp-intl-wrap" style="display: flex;align-items: center;gap: .5rem;">
        						<input id="awp_block_intl" type="tel" placeholder="<?php esc_attr_e( 'Type number', 'awp' ); ?>">
        						<button type="button" id="awp_block_add_btn" class="awp-btn secondary"><?php _e( 'Add', 'awp' ); ?></button>
        					</div>
        
        					<form method="post" style="gap: 0;">
        						<?php wp_nonce_field( 'awp_block_list_nonce', 'awp_block_list_nonce_field' ); ?>
        
        						<textarea class="awp-block-area" name="awp_block_list" rows="5" style="display:none;"><?php echo esc_textarea( $block_text ); ?></textarea>
        
        						<input id="awp_block_tagify" type="text" style="width:100%;" placeholder="<?php esc_attr_e( 'Enter phone numbers', 'awp' ); ?>">
        						<p class="submit awp_save">
        							<button type="submit" name="awp_block_list_submit" class="awp-btn primary"><?php _e( 'Save Block List', 'awp' ); ?></button>
        						</p>
        					</form>
        
        				</div>
        			</div>
        
        		</div>
        	</div>
        
            	<script>
        		jQuery(function ($){
        			function activatePane(hash,skip){
        				const $tabs=$('#awp-sender-tabs a'),$panes=$('.awp-tab-pane');let $t=$(hash);
        				if(!$t.length){$t=$panes.first();hash='#'+$t.attr('id');}
        				$tabs.removeClass('nav-tab-active').filter('[href="'+hash+'"]').addClass('nav-tab-active');
        				$panes.hide().removeClass('active');$t.show().addClass('active');
        				if(!skip){history.replaceState(null,'',hash);}
        			}
        			activatePane(window.location.hash||'#tab-email',true);
        			$('#awp-sender-tabs').on('click','a',e=>{e.preventDefault();activatePane($(e.currentTarget).attr('href'));});
        			$(window).on('hashchange',()=>activatePane(window.location.hash,true));
        		});
        	</script>
        <?php
        }
            
        private function maybe_save_block_list() {
        
            if ( ! isset( $_POST['awp_block_list_submit'] ) ) {
                return;
            }
            check_admin_referer( 'awp_block_list_nonce', 'awp_block_list_nonce_field' );
        
            $raw  = wp_unslash( $_POST['awp_block_list'] ?? '' );
            $list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
        
            ( new AWP_Database_Manager() )->upsert_blocked_numbers( $list );
        
            echo '<div class="notice notice-success is-dismissible"><p>'
               . esc_html__( 'Block list updated successfully.', 'awp' )
               . '</p></div>';
        }
        
        private function get_smtp_settings() : array {
        	return array_merge( [
        		'enabled'     => 0,
        		'host'        => '',
        		'port'        => 587,
        		'encryption'  => 'tls',  
        		'auth'        => 0,
        		'user'        => '',
        		'pass'        => '',
        		'from_email'  => get_bloginfo( 'admin_email' ),
        		'from_name'   => get_bloginfo( 'name' ),
        	], (array) get_option( 'awp_smtp_settings', [] ) );
        }
        
        public function smtp_send_test_email() {
        
        	$this->check_permissions_and_nonce();
        
        	$to = sanitize_email( $_POST['to'] ?? '' );
        	if ( ! is_email( $to ) ) {
        		wp_send_json_error( __( 'Please enter a valid email address.', 'awp' ) );
        	}
        	$sent = wp_mail(
        		$to,
        		'Wawp SMTP test' . wp_specialchars_decode( get_bloginfo( 'name' ) ),
        		"Wawp SMTP is working! \n\nTime: " . current_time( 'mysql' )
        	);
        
        	if ( $sent ) {
        		wp_send_json_success( __( 'Test message sent â€“ please check the inbox.', 'awp' ) );
        	}
        	wp_send_json_error( __( 'wp_mail() returned false. Check SMTP settings or server logs.', 'awp' ) );
        }
        
        public function smtp_test_connection() {

    $this->check_permissions_and_nonce();


    if ( ! class_exists( '\PHPMailer\PHPMailer\PHPMailer', false ) ) {

        // WP 5.x and early 6.x   →  wp-includes/PHPMailer/
        if ( file_exists( ABSPATH . WPINC . '/PHPMailer/PHPMailer.php' ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

        // WP 4.x fallback       →  wp-includes/class-phpmailer.php
        } elseif ( file_exists( ABSPATH . WPINC . '/class-phpmailer.php' ) ) {
            require_once ABSPATH . WPINC . '/class-phpmailer.php';
            require_once ABSPATH . WPINC . '/class-smtp.php';
        }
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer( true );
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->SMTPAuth   = $auth;
        $mail->SMTPAutoTLS = false;
        if ( $enc !== 'none' ) {
            $mail->SMTPSecure = $enc;
        }
        if ( $auth ) {
            $mail->Username = $user;
            $mail->Password = $pass;
        }

        $ok = $mail->smtpConnect();
        $mail->smtpClose();

        if ( $ok ) {
            wp_send_json_success( __( 'Connection successful! 🎉', 'awp' ) );
        }
        wp_send_json_error( __( 'Could not connect – server rejected the handshake.', 'awp' ) );

    } catch ( \Throwable $e ) {   // catch *everything*, even fatals on PHP 8
        wp_send_json_error(
            sprintf( __( 'Connection failed: %s', 'awp' ), $e->getMessage() )
        );
    }
}

        
        public function render_smtp_settings_form() {
        
        	$opts = $this->get_smtp_settings();
        	$chk  = function ( $v ) { return $v ? 'checked' : ''; };
        
        	?>
        	<form method="post" class="awp-smtp-form">
        		<?php wp_nonce_field( 'awp_save_smtp', 'awp_save_smtp_nonce' ); ?>
        
        		<div class="awp-card awp-accordion" id="awp-card-smtp">
        			<div class="card-header_row awp-accordion-header">
        				
        				<div class="card-header" style="flex:1;">
        					<h4 class="card-title">
        						<i class="ri-mail-settings-line"></i>
        						<?php esc_html_e( 'SMTP Settings', 'awp' ); ?>
        					</h4>
        					<p><?php esc_html_e(
        						'If enabled, all wp_mail() calls will be routed through this SMTP server.',
        						'awp'
        					); ?></p>
        					
        				</div>
        			</div>
        
        			<div class="awp-accordion-content">
        				<table class="form-table"><tbody>
        					<tr>
        						<th scope="row"><?php esc_html_e( 'Route emails via SMTP', 'awp' ); ?></th>
        						<td>
        							<label class="awp-switch">
        								<input type="checkbox" id="awp-smtp-enabled"
        									   name="awp_smtp[enabled]" value="1" <?php echo $chk( $opts['enabled'] ); ?> >
        								<span class="custom-control-label"></span>
        							</label>
        						</td>
        					</tr>
        				</tbody></table>
            				<div id="awp-smtp-settings-fields">
        
        					<table class="form-table"><tbody>
            						<tr>
        							<th><?php esc_html_e( 'SMTP Host', 'awp' ); ?></th>
        							<td>
        								<input type="text"   name="awp_smtp[host]"
        									   value="<?php echo esc_attr( $opts['host'] ); ?>"
        									   placeholder="smtp.example.com">
        							</td>
        						</tr>
        						<tr>
        							<th><?php esc_html_e( 'Port', 'awp' ); ?></th>
        							<td>
        								<input type="number" name="awp_smtp[port]"
        									   value="<?php echo (int) $opts['port']; ?>">
        							</td>
        						</tr>
        
        						<tr>
        							<th><?php esc_html_e( 'Encryption', 'awp' ); ?></th>
        							<td>
        								<select name="awp_smtp[encryption]">
        									<?php
        									$encs = [
        										'none' => __( 'None', 'awp' ),
        										'ssl'  => 'SSL',
        										'tls'  => 'TLS'
        									];
        									foreach ( $encs as $val => $label ) {
        										printf(
        											'<option value="%s"%s>%s</option>',
        											esc_attr( $val ),
        											selected( $opts['encryption'], $val, false ),
        											esc_html( $label )
        										);
        									}
        									?>
        								</select>
        							</td>
        						</tr>
            						<tr>
        							<th><?php esc_html_e( 'From Email', 'awp' ); ?></th>
        							<td>
        								<input type="email" name="awp_smtp[from_email]"
        									   value="<?php echo esc_attr( $opts['from_email'] ); ?>">
        							</td>
        						</tr>
        
        						<tr>
        							<th><?php esc_html_e( 'Name', 'awp' ); ?></th>
        							<td>
        								<input type="text"  name="awp_smtp[from_name]"
        									   value="<?php echo esc_attr( $opts['from_name'] ); ?>">
        							</td>
        						</tr>
            						<tr>
        							<th><?php esc_html_e( 'SMTP Authentication', 'awp' ); ?></th>
        							<td>
        								<label class="awp-switch">
        									<input type="checkbox" id="awp-smtp-auth"
        										   name="awp_smtp[auth]" value="1" <?php echo $chk( $opts['auth'] ); ?> >
        									<span class="custom-control-label"></span>
        								</label>
        							</td>
        						</tr>
            						<tr class="awp-smtp-auth-fields">
        							<th><?php esc_html_e( 'Username', 'awp' ); ?></th>
        							<td>
        								<input type="text"     name="awp_smtp[user]"
        									   value="<?php echo esc_attr( $opts['user'] ); ?>">
        							</td>
        						</tr>
        						
        						<tr class="awp-smtp-auth-fields">
        							<th><?php esc_html_e( 'Password', 'awp' ); ?></th>
        							<td>
        								<input type="password" name="awp_smtp[pass]"
        									   value="<?php echo esc_attr( $opts['pass'] ); ?>"
        									   autocomplete="new-password">
        							</td>
        						</tr>
        
        					</tbody></table>
        
        				</div>
        					<p class="submit awp_save">
        						<button type="submit" name="awp_save_smtp" class="awp-btn primary">
        							<?php esc_html_e( 'Save SMTP Settings', 'awp' ); ?>
        						</button>
        					</p>
        			</div>
        		</div>
        		<div class="awp-card">
        		    <div class="card-header_row">
            		    <div class="card-header">
                			<h4 class="card-title">
                				<i class="ri-mail-send-line"></i>
                				<?php esc_html_e( 'Send Test Email', 'awp' ); ?>
                			</h4>
                			<p><?php esc_html_e( 'The email address to which you want to send the test.', 'awp' ); ?></p>
            		    </div>
            			<div class="card-header">
            				<button class="awp-btn" id="awp-smtp-test-conn">
            				    <i class="ri-refresh-line"></i>
            					<?php esc_html_e( 'Check Connection', 'awp' ); ?>
            				</button>
            				
            				<span id="awp-smtp-test-status"></span>
            			</div>
        		    </div>
        			<p class="btn-group">
        				<input type="email" id="awp-smtp-test-to" placeholder="you@example.com">
        				<button class="awp-btn secondary" id="awp-smtp-test-btn">
        					<?php esc_html_e( 'Send Test', 'awp' ); ?>
        				</button>
        			</p>
        		</div>
        
        	</form>
        	<?php
        }
        
        private function maybe_save_smtp_settings() {
        	if (
        		isset( $_POST['awp_save_smtp'] )
        		&& check_admin_referer( 'awp_save_smtp', 'awp_save_smtp_nonce' )
        	) {
        		$raw = $_POST['awp_smtp'] ?? [];
        		$clean = [
        			'enabled'    => empty( $raw['enabled'] ) ? 0 : 1,
        			'host'       => sanitize_text_field( $raw['host'] ?? '' ),
        			'port'       => (int) ( $raw['port'] ?? 587 ),
        			'encryption' => in_array( $raw['encryption'] ?? 'tls', [ 'none','ssl','tls' ], true ) ? $raw['encryption'] : 'tls',
        			'auth'       => empty( $raw['auth'] ) ? 0 : 1,
        			'user'       => sanitize_text_field( $raw['user'] ?? '' ),
        			'pass'       => sanitize_text_field( $raw['pass'] ?? '' ),
        			'from_email' => sanitize_email( $raw['from_email'] ?? '' ),
        			'from_name'  => sanitize_text_field( $raw['from_name'] ?? '' ),
        		];
        		update_option( 'awp_smtp_settings', $clean );
        		echo '<div class="notice notice-success is-dismissible"><p>'
        		     . esc_html__( 'SMTP settings saved.', 'awp' ) . '</p></div>';
        	}
        }
        
        public function get_auto_instances() {
            $this->check_permissions_and_nonce();
        
            $token = get_option( 'mysso_token' );
            if ( ! $token ) {
                wp_send_json_error( [ 'message' => __( 'No SSO token found.', 'awp' ) ] );
            }
        
            $resp = $this->call_wawp( '/wp-json/my-sub-list/v1/internal-instances', 'GET', [ 'token' => $token ] );
        
            if ( ! $resp || $resp['code'] !== 200 ) {
                wp_send_json_error( [ 'message' => __( 'Failed to fetch internal instances.', 'awp' ) ] );
            }
        
            $data = json_decode( $resp['body'], true );
            if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
                wp_send_json_error( [ 'message' => __( 'Invalid response from Site A.', 'awp' ) ] );
            }
        
            wp_send_json_success( $data ); 
        }
        
        public function add_auto_instance() {
    $this->check_permissions_and_nonce();

    $whatsapp_number = sanitize_text_field( $_POST['whatsapp_number'] ?? '' );
    $instance_id     = sanitize_text_field( $_POST['instance_id'] ?? '' );
    $access_token    = sanitize_text_field( $_POST['access_token'] ?? '' );

    if ( ! $whatsapp_number || ! $instance_id || ! $access_token ) {
        wp_send_json_error( [ 'message' => __( 'Missing required data to add instance.', 'awp' ) ] );
    }

    global $wpdb;
    $table_name = $this->database_manager->tables['instance_data'];

    if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE instance_id = %s", $instance_id ) ) ) {
        wp_send_json_error( [ 'message' => __( 'This instance already exists in your list.', 'awp' ) ] );
    }

    $response = $this->call_wawp_app_api('/api/reconnect', 'GET', [
        'instance_id'  => $instance_id,
        'access_token' => $access_token
    ]);
    
    $response_data = json_decode( $response['body'] ?? '', true );
    $api_status = $response_data['status'] ?? 'error';
    $connected_name = $response_data['data']['name'] ?? null;

    if ( strtolower($api_status) === 'success' && $connected_name ) {
        
        // **FIX:** Use the name from the API ($connected_name) instead of the phone number.
        $result = $wpdb->insert( $table_name, [
            'name'           => $connected_name, 
            'instance_id'    => $instance_id,
            'access_token'   => $access_token,
            'status'         => 'online',
            'message'        => __('Connected as', 'awp') . ' ' . sanitize_text_field($connected_name),
        ]);

        if ($result === false) {
            wp_send_json_error( [ 'message' => __('Failed to save the instance to the database.', 'awp') ] );
        }

        $this->push_instance_update_to_wawp();
        wp_send_json_success( [ 'message' => __('Instance added successfully.', 'awp') ] );

    } else {
        $error_message = $response_data['message'] ?? __('Instance is not online or accessible. Cannot add.', 'awp');
        wp_send_json_error( [ 'message' => $error_message ] );
    }
}
    
        private function call_wawp_app_api($endpoint, $method = 'POST', $url_params = [], $post_body_data = null) {
            $url = $this->wawp_app_domain . $endpoint;
            if (!empty($url_params)) {
                $url = add_query_arg($url_params, $url);
            }
    
            $req_args = [
                'timeout' => 25,
                'method'  => strtoupper($method),
                // 'sslverify' => false, // KEEP THIS COMMENTED OUT for production, enable only for local HTTPS dev issues
            ];
    
            if (strtoupper($method) === 'POST') {
                if ($post_body_data !== null) {
    
                     if ( in_array($endpoint, ['/api/send', '/api/send_group', '/api/create_instance'], true) ) {
                        $req_args['headers'] = ['Content-Type' => 'application/json'];
                        $req_args['body'] = wp_json_encode($post_body_data);
                     } else { 
                
                        $req_args['body'] = $post_body_data;
                     }
                } else {
                   
                    $req_args['body'] = ''; 
                }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[WAWP API Call] Requesting URL: " . $url . " with method: " . $method);
                if (strtoupper($method) === 'POST' && !empty($req_args['body'])) {
                     error_log("[WAWP API Call] POST Body: " . print_r($req_args['body'], true));
                }
            }
            
            $response = wp_remote_request($url, $req_args);
    
            if (is_wp_error($response)) {
                error_log("[WAWP API Call WP_Error] Endpoint: $url, Method: $method, Error: " . $response->get_error_message() . " Data: " . print_r($response->get_error_data(), true));
                return ['error' => $response->get_error_message(), 'wp_error_data' => $response->get_error_data(), 'code' => 'WP_REQUEST_ERROR'];
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
    
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[WAWP API Call Response] Endpoint: $url, Method: $method, Code: " . $response_code . ", Body Preview: " . substr($response_body, 0, 500));
            }
    
            return [
                'code' => $response_code,
                'body' => $response_body
            ];
        }
    
        public function qr_create_new_instance_action() {
            $this->check_permissions_and_nonce();
            $api_access_token = get_option('wawp_access_token');
            if (!$api_access_token) {
                wp_send_json_error(['message' => __('API Access Token not found. Please ensure you are connected to Wawp.', 'awp')]);
                return;
            }
          $slug = preg_replace('/[^a-z0-9]/i', '', $this->get_site_domain()); 
          $session_name = substr($slug, 0, 20) . wp_rand(100, 999);          

          $response = $this->call_wawp_app_api(
              '/api/create_instance',
              'POST',
              [],                          
              [
                  'access_token' => $api_access_token,
                  'name'         => $session_name,
              ]
          );
            if (isset($response['error'])) {
                wp_send_json_error(['message' => __('Error creating instance (WP_Error):', 'awp') . ' ' . $response['error'], 'raw_response_code' => $response['code'] ?? null, 'raw_response_body_preview' => 'WP_Error']);
                return;
            }
            $response_data = json_decode($response['body'], true);
            $json_error_code = json_last_error();
            if ($response['code'] !== 200 || $json_error_code !== JSON_ERROR_NONE || !isset($response_data['status'])) {
                 $debug_info = [
                    'message' => __('Invalid response from create instance API.', 'awp'),
                    'raw_response_code' => $response['code'],
                    'raw_response_body_preview' => substr($response['body'], 0, 500) . (strlen($response['body']) > 500 ? '...' : ''),
                    'json_decode_error' => json_last_error_msg() . " (Code: $json_error_code)"
                ];
                error_log('[WAWP QR Debug] Invalid response from create_instance: ' . print_r($debug_info, true));
                wp_send_json_error($debug_info);
                return;
            }
 if ( strtolower($response_data['status']) === 'success'
      && !empty($response_data['data']['metadata']['instance_id']) ) {

     $iid = sanitize_text_field($response_data['data']['metadata']['instance_id']);
    wp_send_json_success([
         'instance_id'  => $iid,
          'access_token' => sanitize_text_field($api_access_token),
          'message'      => $response_data['message'] ?? __('Instance created successfully.', 'awp')
      ]);
            } else {
                $error_detail = [
                    'message' => $response_data['message'] ?? __('Failed to create instance (API error).', 'awp'),
                    'raw_response_code' => $response['code'],
                    'api_response_data' => $response_data
                ];
                error_log('[WAWP QR Debug] create_instance API returned non-success status: ' . print_r($error_detail, true));
                wp_send_json_error($error_detail);
            }
        }
    
        public function qr_get_code_action() {
    $this->check_permissions_and_nonce();
    $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');
    $access_token = sanitize_text_field($_POST['access_token'] ?? '');

    if (empty($instance_id) || empty($access_token)) {
        wp_send_json_error(['message' => __('Instance ID and Access Token are required for QR.', 'awp')]);
        return;
    }

    // Call /reconnect instead, as it provides the QR code if the instance is in the 'SCAN_QR_CODE' state.
    $response = $this->call_wawp_app_api('/api/reconnect', 'GET', [
        'instance_id' => $instance_id,
        'access_token' => $access_token
    ]);

    if (isset($response['error'])) {
        wp_send_json_error(['message' => __('Error getting QR code (WP_Error):', 'awp') . ' ' . $response['error']]);
        return;
    }

    $response_data = json_decode($response['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['status'])) {
        wp_send_json_error(['message' => __('Invalid response from QR code API.', 'awp')]);
        return;
    }

    // Check if the API returned a QR code in the 'data' field
    if (strtolower($response_data['status']) === 'success' && !empty($response_data['data']['qrcode'])) {
        wp_send_json_success([
            'qr_code_base64' => $response_data['data']['qrcode'],
            'message' => $response_data['message'] ?? __('QR Code fetched successfully.', 'awp')
        ]);
    } else {
        // Handle other statuses, like if it's already connected
        wp_send_json_error([
            'message' => $response_data['message'] ?? __('Failed to get QR code. The instance may already be connected or in an error state.', 'awp'),
            'status_from_api' => $response_data['status'] ?? 'unknown'
        ]);
    }
}
        
        public function qr_check_connection_status_action() {
            $this->check_permissions_and_nonce();
            $instance_id  = sanitize_text_field($_POST['instance_id'] ?? '');
            $access_token = sanitize_text_field($_POST['access_token'] ?? '');
    
            if (empty($instance_id) || empty($access_token)) {
                wp_send_json_error(['message' => __('Instance ID and Access Token are required for status check.', 'awp')]);
                return;
            }
            
            $response = $this->call_wawp_app_api('/api/reconnect', 'GET',  [
                'instance_id' => $instance_id,
                'access_token' => $access_token
            ]);
    
            if (isset($response['error'])) {
                wp_send_json_error(['status_api' => 'error', 'message_api' => $response['error'], 'raw_response_code' => $response['code'] ?? null, 'raw_response_body_preview' => 'WP_Error']);
                return;
            }
            
            $response_data = json_decode($response['body'], true);
            $json_error_code = json_last_error();
    
            if ($json_error_code !== JSON_ERROR_NONE || !isset($response_data['status'])) {
                $debug_info = [
                    'status_api' => 'error',
                    'message_api' => __('Invalid or unexpected API response for status check.', 'awp'),
                    'raw_response_code' => $response['code'],
                    'raw_response_body_preview' => substr($response['body'], 0, 500) . (strlen($response['body']) > 500 ? '...' : ''),
                    'json_decode_error' => json_last_error_msg() . " (Code: $json_error_code)"
                ];
                 error_log('[WAWP QR Debug] Invalid response from reconnect: ' . print_r($debug_info, true));
                wp_send_json_error($debug_info);
                return;
            }
            
            wp_send_json_success([
                'status_api' => strtolower($response_data['status']),
                'message_api' => $response_data['message'] ?? '',
                'data_api' => $response_data['data'] ?? null
            ]);
        }
    
        public function qr_save_online_instance_action() {
            $this->check_permissions_and_nonce();
            $instance_id = sanitize_text_field($_POST['instance_id'] ?? '');
            $access_token = sanitize_text_field($_POST['access_token'] ?? '');
            $instance_name = sanitize_text_field($_POST['instance_name'] ?? '');
    
            if (empty($instance_id) || empty($access_token) || empty($instance_name)) {
                wp_send_json_error(['message' => __('Instance details (ID, Token, Name) are required to save.', 'awp')]);
                return;
            }
    
            global $wpdb;
            $table_name = $this->database_manager->tables['instance_data'];
            
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE instance_id = %s", $instance_id));
            if ($existing) {
                 wp_send_json_error(['message' => __('Instance with this ID already exists locally.', 'awp')]);
                 return;
            }
    
            $result = $wpdb->insert($table_name, [
                'name' => $instance_name,
                'instance_id' => $instance_id,
                'access_token' => $access_token,
                'status' => 'online', 
                'message' => __('Connected via QR Scan.', 'awp')
            ]);
    
            if ($result === false) {
                wp_send_json_error(['message' => __('Failed to save the new instance to the database.', 'awp')]);
                return;
            }
            
            $this->push_instance_update_to_wawp(); 
            wp_send_json_success(['message' => __('New instance connected and saved successfully.', 'awp')]);
        }
    
        public function background_fetch_limits() {
    check_ajax_referer('awp_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => __('Unauthorized user.', 'awp')]);
    }

    // Left number = how many ONLINE right now (local DB)
    $instance_count = $this->get_online_instance_count();

    // Right number = allowed numbers (stored locally)
    $limit = $this->get_local_allowed_numbers_limit();

    // Over-limit logic stays the same
    $is_over = (is_numeric($limit) && (int)$limit > 0) ? ($instance_count >= (int)$limit) : false;

    wp_send_json_success([
        'instance_count'         => $instance_count,
        'limit'                  => $limit,   // will be 1 (Free) or 10 (Pro/Lifetime)
        'is_over'                => $is_over,

        // keep these flags for the existing JS so no UI changes are needed
        'not_logged_in_locally'  => false,
        'sso_login_required'     => false,
        'site_not_active_on_sso' => false,
        'is_banned'              => false,
        'api_error_message'      => '',
    ]);
}

    
        public function auto_check_all_instance_status() {
            check_ajax_referer('awp_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Unauthorized user.', 'awp'));
            }
    
            $instances = $this->get_all_instances();
            if (!$instances) {
                wp_send_json_error(__('No instances found.', 'awp'));
            }
    
            $updated = 0;
            foreach ($instances as $inst) {
                $result = $this->check_and_update_instance_status($inst->instance_id, $inst->access_token);
                if ($result) {
                    $updated++;
                }
            }
    
            wp_send_json_success([
                'updated_count' => $updated,
                'message'       => sprintf(__('%d instances checked & updated.', 'awp'), $updated),
            ]);
        }
    
        public function import_csv_via_ajax() {
            check_ajax_referer('import_csv_nonce','security');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Unauthorized user.', 'awp'));
            }
            if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(__('No file or upload error.', 'awp'));
            }
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            if (!$handle) {
                wp_send_json_error(__('Cannot open file.', 'awp'));
            }
            global $wpdb;
            $table = $wpdb->prefix.'awp_instance_data';
            fgetcsv($handle);
            $imported = 0;
            while(($row = fgetcsv($handle)) !== false) {
                $id           = intval($row[0]);
                $name         = sanitize_text_field($row[1]);
                $instance_id  = sanitize_text_field($row[2]);
                $access_token = sanitize_text_field($row[3]);
                $status       = sanitize_text_field($row[4]);
                $message      = sanitize_text_field($row[5]);
                $exists       = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE instance_id=%s",
                    $instance_id
                ));
                if ($exists) {
                    $wpdb->update(
                        $table,
                        [
                            'name'         => $name,
                            'instance_id'  => $instance_id,
                            'access_token' => $access_token,
                            'status'       => $status,
                            'message'      => $message
                        ],
                        ['id'=>$exists]
                    );
                } else {
                    $wpdb->insert(
                        $table,
                        [
                            'name'         => $name,
                            'instance_id'  => $instance_id,
                            'access_token' => $access_token,
                            'status'       => $status,
                            'message'      => $message
                        ]
                    );
                }
                $imported++;
            }
            fclose($handle);
            wp_send_json_success(['imported_count'=>$imported]);
        }
    
        public function register_instance_count_endpoint() {
            register_rest_route('awp-instances/v1','/online-data',[
                'methods'  => 'GET',
                'callback' => [$this, 'handle_get_online_data'],
                'permission_callback'=>'__return_true',
            ]);
        }
    
        public function handle_get_online_data($r) {
            $online = $this->get_online_instances();
            $ids    = [];
            if ($online) {
                foreach($online as $i){
                    $ids[] = $i->instance_id;
                }
            }
            return [
                'count'        => count(array_unique($ids)),
                'instance_ids' => array_values(array_unique($ids))
            ];
        }
    
        public function export_instances() {
            if(!current_user_can('manage_options')){
                wp_die(__('Unauthorized user.','awp'));
            }
            if(empty($_GET['nonce'])|| !wp_verify_nonce($_GET['nonce'], 'awp_export_nonce')){
                wp_die(__('Invalid nonce.','awp'));
            }
            $instances = $this->get_all_instances();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="wawp-instances.csv"');
            $output = fopen('php://output','w');
            fputcsv($output, ['id','name','instance_id','access_token','status','message']);
            if($instances){
                foreach($instances as $inst){
                    fputcsv($output, [
                        $inst->id,
                        $inst->name,
                        $inst->instance_id,
                        $inst->access_token,
                        $inst->status,
                        $inst->message
                    ]);
                }
            }
            fclose($output);
            exit;
        }
    
        public function awp_get_all_instances_ajax() {
            check_ajax_referer('awp_nonce','nonce');
            if(!current_user_can('manage_options')){
                wp_send_json_error(__('Unauthorized user.','awp'));
            }
            $page = isset($_POST['page'])? absint($_POST['page']) : 1;
            $page_size = 20;
            $offset = ($page-1)*$page_size;
            global $wpdb;
            $table = $wpdb->prefix.'awp_instance_data';
            $rows  = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table LIMIT %d,%d",
                $offset, $page_size
            ));
            wp_send_json_success($rows);
        }
    
        public function get_all_instances() {
            global $wpdb;
            return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}awp_instance_data");
        }
    
        public function get_online_instances() {
            global $wpdb;
            $tn = "{$wpdb->prefix}awp_instance_data";
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $tn WHERE status=%s",'online'
            ));
        }
        
        public function ajax_qr_poll_status() {
            $this->check_permissions_and_nonce(); 
            $instance_id  = sanitize_text_field($_POST['instance_id'] ?? '');
            $access_token = sanitize_text_field($_POST['access_token'] ?? '');
    
            if (empty($instance_id) || empty($access_token)) {
                wp_send_json_error([
                    'message' => __('Instance ID and Access Token are required for polling status.', 'awp'),
                    'status_api' => 'error_client_data', 
                    'message_api' => __('Client-side data missing for polling.', 'awp')
                ]);
                return;
            }
            
            $response = $this->call_wawp_app_api(
                '/api/reconnect', 
                'GET',
                [ 
                    'instance_id' => $instance_id,
                    'access_token' => $access_token
                ]
            );
    
            if (isset($response['error'])) { 
                wp_send_json_error([
                    'message' => __('API Call Error (Network/cURL) for /reconnect:', 'awp') . ' ' . $response['error'],
                    'status_api' => 'error_network_reconnect',
                    'message_api' => $response['error'],
                    'raw_response_code' => $response['code'] ?? 'N/A',
                    'raw_response_body_preview' => 'WP_Error during API call to /reconnect. Details: ' . esc_html($response['error']),
                    'wp_error_data' => isset($response['wp_error_data']) ? $response['wp_error_data'] : null
                ]);
                return;
            }
            
            $response_data = json_decode($response['body'], true);
            $json_error_code = json_last_error();
    
            if ($response['code'] !== 200 || $json_error_code !== JSON_ERROR_NONE || !isset($response_data['status'])) {
                $debug_info = [
                    'message' => __('Invalid or unexpected API response format from /reconnect.', 'awp'),
                    'status_api' => 'error_format_reconnect', 
                    'message_api' => __('API response from /reconnect was not valid JSON or missed expected fields.', 'awp'),
                    'raw_response_code' => $response['code'],
                    'raw_response_body_preview' => substr($response['body'], 0, 1000) . (strlen($response['body']) > 1000 ? '...' : ''), 
                    'json_decode_error' => json_last_error_msg() . " (Code: $json_error_code)"
                ];
                 error_log('[WAWP QR Debug] Invalid response from /reconnect: ' . print_r($debug_info, true));
                wp_send_json_error($debug_info); 
                return;
            }
            
            wp_send_json_success([
                'status_api' => strtolower($response_data['status']), 
                'message_api' => $response_data['message'] ?? '',     
                'data_api' => $response_data['data'] ?? null,         
                'raw_response_body_preview' => $response['body']    
            ]);
        }
    
        public function add_instance() {
    $this->check_permissions_and_nonce();
    global $wpdb;

    $name         = sanitize_text_field( $_POST['name']         ?? '' );
    $instance_id  = sanitize_text_field( $_POST['instance_id']  ?? '' );
    $access_token = sanitize_text_field( $_POST['access_token'] ?? '' );

    if ( ! $name || ! $instance_id || ! $access_token ) {
        wp_send_json_error( [ 'message' => __( 'All fields are required.', 'awp' ) ] );
    }

    // 1) hit your new status endpoint
    $api_url = sprintf(
        '%s/wp-json/awp/v1/status?instance_id=%s&access_token=%s',
        $this->wawp_domain,
        rawurlencode( $instance_id ),
        rawurlencode( $access_token )
    );
    $resp = wp_remote_get( $api_url, ['timeout'=>15] );
    if ( is_wp_error( $resp ) ) {
        wp_send_json_error([
            'message' => __( 'Network error checking status:', 'awp' ) . ' ' . $resp->get_error_message()
        ]);
    }

    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    $data = json_decode( $body, true );

    if ( $code !== 200 || empty( $data['status'] ) ) {
        wp_send_json_error([
            'message' => __( 'Invalid status response from server.', 'awp' )
        ]);
    }

    // 2) only allow “Online”
    if ( strcasecmp( $data['status'], 'Online' ) !== 0 ) {
        wp_send_json_error([
            'message' => __( 'Instance is not online. Cannot add.', 'awp' )
        ]);
    }

    // 3) now insert
    $inserted = $wpdb->insert(
        "{$wpdb->prefix}awp_instance_data",
        [
            'name'         => $name,
            'instance_id'  => $instance_id,
            'access_token' => $access_token,
            'status'       => 'online',
            'message'      => __( 'Instance is online.', 'awp' ),
        ]
    );
    if ( false === $inserted ) {
        wp_send_json_error([ 'message' => __( 'Failed to save the instance.', 'awp' ) ]);
    }

    // 4) respond success
    wp_send_json_success([ 'message' => __( 'Instance added successfully.', 'awp' ) ]);
}
    
        public function delete_instance(){
            $this->check_permissions_and_nonce();
            global $wpdb;
            $id=intval($_POST['id']??0);
            if($id<=0){
                wp_send_json_error(['message' => __('Invalid instance ID.','awp')]);
                return;
            }
            $result=$wpdb->delete("{$wpdb->prefix}awp_instance_data", ['id'=>$id]);
            if($result===false){
                wp_send_json_error(['message' => __('Failed to delete instance.','awp')]);
                return;
            }
            $this->push_instance_update_to_wawp();
            wp_send_json_success(['message' => __('Instance deleted successfully.','awp')]);
        }
    
        public function edit_instance(){
            $this->check_permissions_and_nonce();
            global $wpdb;
            $id=intval($_POST['id']??0);
            $name=sanitize_text_field($_POST['name']??'');
            $instance_id=sanitize_text_field($_POST['instance_id']??'');
            $access_token=sanitize_text_field($_POST['access_token']??'');
            if($id<=0||!$name||!$instance_id||!$access_token){
                wp_send_json_error(['message' => __('All fields are required.','awp')]);
                return;
            }
            $result = $wpdb->update("{$wpdb->prefix}awp_instance_data",[
                'name'         => $name,
                'instance_id'  => $instance_id,
                'access_token' => $access_token,
                'status'       => 'checking',
                'message'      => ''
            ], ['id'=>$id]);
            if($result===false){
                wp_send_json_error(['message' => __('Failed to update instance.','awp')]);
                return;
            }
            $this->check_and_update_instance_status($instance_id,$access_token);
            $st = $this->get_instance_status($instance_id);
            $msg= $this->get_instance_message($instance_id);
            if($st==='online'){
                $this->push_instance_update_to_wawp();
            }
            wp_send_json_success(['status'=>$st,'message'=>$msg,'text'=>__('Instance updated successfully.','awp')]);
        }
    
        public function update_status() {
    $this->check_permissions_and_nonce();

    $instance_id  = sanitize_text_field( $_POST['instance_id']  ?? '' );
    $access_token = sanitize_text_field( $_POST['access_token'] ?? '' );

    if ( ! $instance_id || ! $access_token ) {
        wp_send_json_error([ 'message' => __( 'Instance ID & Access Token are required.', 'awp' ) ]);
    }

    $api_url = sprintf(
        '%s/wp-json/awp/v1/status?instance_id=%s&access_token=%s',
        $this->wawp_domain,
        rawurlencode( $instance_id ),
        rawurlencode( $access_token )
    );
    $resp = wp_remote_get( $api_url, ['timeout'=>15] );
    if ( is_wp_error( $resp ) ) {
        wp_send_json_error([
            'message' => __( 'Network error checking status:', 'awp' ) . ' ' . $resp->get_error_message()
        ]);
    }

    $code = wp_remote_retrieve_response_code( $resp );
    $body = wp_remote_retrieve_body( $resp );
    $data = json_decode( $body, true );

    if ( $code !== 200 || empty( $data['status'] ) ) {
        wp_send_json_error([ 'message' => __( 'Invalid status response from server.', 'awp' ) ]);
    }

    $new_status  = ( strcasecmp( $data['status'], 'Online' ) === 0 ) ? 'online' : 'offline';
    $new_message = $data['status'];

    global $wpdb;
    $updated = $wpdb->update(
        "{$wpdb->prefix}awp_instance_data",
        [ 'status' => $new_status, 'message' => $new_message ],
        [ 'instance_id' => $instance_id ]
    );

    if ( false === $updated ) {
        wp_send_json_error([ 'message' => __( 'Failed to update status in database.', 'awp' ) ]);
    }

    wp_send_json_success([
        'status'  => $new_status,
        'message' => $new_message,
    ]);
}

        public function send_test_message() {
    $this->check_permissions_and_nonce();

    $instance_id  = sanitize_text_field( $_POST['instance_id']  ?? '' );
    $access_token = sanitize_text_field( $_POST['access_token'] ?? '' );

    if ( ! $instance_id || ! $access_token ) {
        wp_send_json_error( [ 'message' => __( 'Instance ID and Access Token are required.', 'awp' ) ] );
    }

    $phone_number = '447441429009'; // your test number
    $message      = __( 'Wawp Notification work', 'awp' );

    $result = Wawp_Api_Url::send_message(
        $instance_id,
        $access_token,
        $phone_number,
        $message
    );
    
    // Start Logging
    $user = wp_get_current_user();
    $customer_name = $user ? ($user->display_name ?: 'Admin Test') : 'Admin Test';

    // Unwrap the response if it's nested
    $response_to_log = $result;
    if (isset($result['full_response'])) {
        $response_to_log = $result['full_response'];
    }

    $log_manager = new AWP_Log_Manager();
    $log_manager->log_notification([
        'user_id'          => $user->ID ?? 0,
        'order_id'         => null,
        'customer_name'    => $customer_name,
        'sent_at'          => current_time( 'mysql' ),
        'whatsapp_number'  => $phone_number,
        'message'          => $message,
        'image_attachment' => null,
        'message_type'     => 'Admin Test Message',
        'wawp_status'      => $response_to_log,
        'resend_id'        => null,
        'instance_id'      => $instance_id,
        'access_token'     => $access_token,
    ]);
    // End Logging

    if ( $result['status'] === 'success' ) {
        wp_send_json_success( [
            'message' => __( 'Test message sent successfully!', 'awp' )
        ] );
    }

    $err = $result['message'] ?? __( 'Unknown error', 'awp' );
    wp_send_json_error( [
        'message' => __( 'Failed to send test message: ', 'awp' ) . $err
    ] );
}

private function get_local_allowed_numbers_limit() : int {
    $limit = get_option('awp_allowed_numbers_limit', '');
    if ($limit === '' || $limit === null) {
        // Default from local tier if nothing stored yet
        $tier  = get_option('awp_plan_tier', 'free');
        $limit = ($tier === 'pro') ? 10 : 1;
        update_option('awp_allowed_numbers_limit', $limit, 'no');
    }
    return (int) $limit;
}


    
        private function insert_test_message_log($body,$status,$resp=''){
            global $wpdb;
            $table_log = $this->database_manager->get_log_table_name();
            $user_id   = get_current_user_id() ?: 0;
            $number    = $body['number']??'N/A';
            $msg       = $body['message']??'N/A';
            $wpdb->insert($table_log,[
                'user_id'        => $user_id,
                'order_id'       => 0,
                'customer_name'  => (!empty($body['instance_id'])?$body['instance_id']:'N/A'),
                'sent_at'        => current_time('mysql'),
                'whatsapp_number'=> $number,
                'message'        => $msg,
                'image_attachment'=>'',
                'message_type'   => 'test_message',
                'wawp_status'    => $resp,
                'resend_id'      => null
            ]);
        }
    
private function check_and_update_instance_status( $iid, $atk ) {

    if ( ! $iid || ! $atk ) {
        return false;
    }

    $url  = sprintf(
        '%s/wp-json/awp/v1/session/info?instance_id=%s&access_token=%s',
        $this->wawp_domain,
        rawurlencode( $iid ),
        rawurlencode( $atk )
    );
    $resp = wp_remote_get( $url, [ 'timeout' => 15 ] );

    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) {
        $msg = is_wp_error( $resp )
            ? $resp->get_error_message()
            : sprintf( 'HTTP%s returned', wp_remote_retrieve_response_code( $resp ) );
        return $this->set_instance_state( $iid, 'offline', $msg );
    }

    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return $this->set_instance_state(
            $iid, 'offline',
            'Invalid JSON from /session/info'
        );
    }

    $raw = strtoupper( $data['status'] ?? '' );
    $map = [ 'WORKING'=>'online', 'READY'=>'ready', 'PAUSED'=>'paused', 'OFFLINE'=>'offline' ];
    $new_status = $map[ $raw ] ?? 'checking';

    $display = $data['metadata']['display_name']
            ?? $data['display_name']
            ?? '';
    $new_msg = ( $new_status === 'online' && $display )
        ? sprintf( __( 'Connected as %s', 'awp' ), sanitize_text_field( $display ) )
        : $raw;

    return $this->set_instance_state( $iid, $new_status, $new_msg );
}

private function set_instance_state( $iid, $status, $message ) {
    global $wpdb;
    $table  = "{$wpdb->prefix}awp_instance_data";
    $before = $wpdb->get_var(
        $wpdb->prepare( "SELECT status FROM $table WHERE instance_id = %s", $iid )
    );
    $wpdb->update( $table,
        [ 'status'=>$status, 'message'=>$message ],
        [ 'instance_id'=>$iid ]
    );
    if ( $before !== 'online' && $status === 'online' ) {
        $this->push_instance_update_to_wawp();
    }
    return true;
}
private function maybe_autoselect_default_sender( array $online_instances,
    array &$otp_settings,
    string &$curr_login_val,
    string &$curr_signup_val,
    string &$curr_checkout_val,
    string &$curr_resend_val,
    array  &$selected_mult,         
    bool    $otp_global,
    bool    $otp_login,
    bool    $otp_signup,
    bool    $otp_checkout,
    bool    $notifications_enabled
) {
    if ( empty( $online_instances ) ) {
        return;                  
    }
    $first = $online_instances[0];    

    if ( $otp_global && $otp_login && $curr_login_val === '' ) {
        $curr_login_val = $first->instance_id;
        $otp_settings['instance'] = $curr_login_val;
        update_option( 'awp_otp_settings', $otp_settings );
    }

    if ( $otp_global && $otp_signup && $curr_signup_val === '' && class_exists( 'AWP_Database_Manager' ) ) {
        $dbm = new AWP_Database_Manager();
        $dbm->update_signup_settings( [ 'selected_instance' => (int) $first->id ] );
        $curr_signup_val = (string) $first->id;
    }

    if ( $otp_global && $otp_checkout && $curr_checkout_val === '' ) {
        $curr_checkout_val = $first->instance_id;
        update_option( 'awp_selected_instance', $curr_checkout_val );
    }

    if ( $curr_resend_val === '' ) {
        $curr_resend_val = $first->instance_id;
        update_option( 'awp_selected_log_manager_instance', $curr_resend_val );
    }

    if ( $notifications_enabled && empty( $selected_mult ) && class_exists( 'AWP_Database_Manager' ) ) {
        $dbm = $dbm ?? new AWP_Database_Manager();
        $dbm->set_notif_global( (string) $first->id );
        $selected_mult = [ (int) $first->id ];
    }
}

    
        private function get_instance_status($iid){
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}awp_instance_data WHERE instance_id=%s",$iid));
        }
    
        private function get_instance_message($iid){
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare("SELECT message FROM {$wpdb->prefix}awp_instance_data WHERE instance_id=%s",$iid));
        }
    
        public function admin_notices(){
            if(isset($_GET['awp_notice'])){
                $notice=sanitize_text_field($_GET['awp_notice']);
                $type=sanitize_text_field($_GET['awp_notice_type']??'success');
                echo'<div class="notice notice-'.esc_attr($type).' is-dismissible awp-admin-notice"><p>'.esc_html($notice).'</p></div>';
            }
        }
    
        private function check_permissions_and_nonce(){
            if(!current_user_can('manage_options')){
                wp_send_json_error(['message' => __('Unauthorized user.','awp')]);
            }
            check_ajax_referer('awp_nonce','nonce');
        }
    
        private function call_wawp($endpoint,$method='GET',$body=[],$hdrs=[]){
            $url=$this->wawp_domain.$endpoint;
            $args=['timeout'=>5,'headers'=>array_merge(['x-api-key'=>'some-secret-string'],$hdrs)];
            if($method==='POST'){
                $args['method']='POST';
                $args['body']=$body;
                $res=wp_remote_post($url,$args);
            } else {
                if(!empty($body)){
                    $url=add_query_arg($body,$url);
                }
                $res=wp_remote_get($url,$args);
            }
            if(is_wp_error($res)){
                return null;
            }
            $code=wp_remote_retrieve_response_code($res);
            $rb=wp_remote_retrieve_body($res);
            if(in_array($code,[200,201,401,403],true)){
                return['code'=>$code,'body'=>$rb];
            }
            return null;
        }
    
        private function push_instance_update_to_wawp(){
            $token=get_option('mysso_token');
            if(!$token){
                return;
            }
            $site_domain=$this->get_site_domain();
            $admin_email=get_option('admin_email');
            $this->force_fresh_data_from_wawp($token);
            $body=[
                'token'                 => $token,
                'mysso_site_domain'     => $site_domain,
                'mysso_site_status'     => 'active',
                'mysso_site_admin_email'=>$admin_email,
                'mysso_instance_count'=>$this->get_online_instance_count(),
                'mysso_instance_ids'  => $this->get_online_instance_ids()
            ];
            $this->call_wawp('/wp-json/my-sso/v1/validate','POST',$body);
        }
    
        private function force_fresh_data_from_wawp($token){
            $site_domain=$this->get_site_domain();
            $admin_email=get_option('admin_email');
            $body=[
                'token'                 => $token,
                'mysso_site_domain'     => $site_domain,
                'mysso_site_status'     => 'active',
                'mysso_site_admin_email'=>$admin_email,
                'mysso_instance_count'=>$this->get_online_instance_count(),
                'mysso_instance_ids'  => $this->get_online_instance_ids()
            ];
            return $this->call_wawp('/wp-json/my-sso/v1/validate','POST',$body);
        }
    
        private function fetch_active_instance_limit_from_wawp($token){
            $response=$this->call_wawp('/wp-json/my-sub-list/v1/get-limit','GET',['token'=>$token]);
            $ret=['limit'=>'0','note'=>''];
            if($response&&isset($response['body'])){
                $parsed=json_decode($response['body'],true);
                if(isset($parsed['limit'])){
                    $ret['limit']=$parsed['limit'];
                }
                if(isset($parsed['note'])){
                    $ret['note']=$parsed['note'];
                }
            }
            return $ret;
        }
    
        private function fetch_merged_instance_data_from_wawp($token){
            $response=$this->call_wawp('/wp-json/my-sub-list/v1/get-user-instances','GET',['token'=>$token]);
            if(!$response||!isset($response['body'])){
                return null;
            }
            $data=json_decode($response['body'],true);
            if(json_last_error()!==JSON_ERROR_NONE){
                return null;
            }
            return $data;
        }
    
        private function get_online_instance_count(){
            return count($this->get_online_instance_ids());
        }
    
        private function get_online_instance_ids(){
            $rows=$this->get_online_instances();
            $ids=[];
            if($rows){
                foreach($rows as $r){
                    $ids[]=$r->instance_id;
                }
            }
            return array_values(array_unique($ids));
        }
    
        public static function get_notif_sender_instance_ids() : array {
        
            if ( ! class_exists( 'AWP_Database_Manager' ) ) {
                return [];
            }
        
            $dbm = new AWP_Database_Manager();
            $row = $dbm->get_notif_global();    
            $csv = (string) ( $row['selected_instance_ids'] ?? '' );
        
            if ( $csv === '' ) {
                return [];
            }
        
            $ids = array_filter(
                array_map( 'intval', explode( ',', $csv ) )
            );
        
            return array_values( array_unique( $ids ) );
        }
    
        private function get_site_domain(){
            $domain=parse_url(get_site_url(),PHP_URL_HOST);
            return $domain?$domain:get_site_url();
        }
    
        public function render_otp_senders_form() {
        
        	if ( ! current_user_can( 'manage_options' ) ) {
        		return;
        	}
        
        	$otp_global   = $this->get_toggle( 'awp_wawp_otp_enabled' );
        	$otp_login    = $this->get_toggle( 'awp_otp_login_enabled' );
        	$otp_signup   = $this->get_toggle( 'awp_signup_enabled' );
        	$otp_checkout = $this->get_toggle( 'awp_checkout_otp_enabled' );
        	$notifications_enabled = $this->get_toggle( 'awp_notifications_enabled' );
        
        	if (
        		isset( $_POST['awp_choose_sender_submit'] ) &&
        		check_admin_referer( 'awp_choose_sender_save', 'awp_choose_sender_nonce' )
        	) {
        		$login_val    = sanitize_text_field( $_POST['awp_login_instance']    ?? '' );
        		$signup_val   = sanitize_text_field( $_POST['awp_signup_instance']   ?? '' );
        		$checkout_val = sanitize_text_field( $_POST['awp_checkout_instance'] ?? '' );
        		$resend_val   = sanitize_text_field( $_POST['awp_resend_instance']   ?? '' );
        
        		$otp_settings             = get_option( 'awp_otp_settings', [] );
        		$otp_settings['instance'] = $login_val;
        		update_option( 'awp_otp_settings', $otp_settings );
        
        		if ( class_exists( 'AWP_Database_Manager' ) ) {
        			$dbm = new AWP_Database_Manager();
        			$dbm->update_signup_settings( [ 'selected_instance' => (int) $signup_val ] );
        		}
        
        		update_option( 'awp_selected_instance',             $checkout_val );
        		update_option( 'awp_selected_log_manager_instance', $resend_val );
        
        		if ( $notifications_enabled && class_exists( 'AWP_Database_Manager' ) ) {
                $dbm  = $dbm ?? new AWP_Database_Manager();
                $sel  = isset( $_POST['wawp_notif_selected_instance'] )
                    ? array_unique( array_map( 'intval', (array) $_POST['wawp_notif_selected_instance'] ) )
                    : [];
                $dbm->set_notif_global( implode( ',', $sel ) );
            }
            
        		echo '<div class="notice notice-success is-dismissible"><p>';
        		esc_html_e( 'Settings saved successfully!', 'awp' );
        		echo '</p></div>';
        	}
        
        	$otp_settings       = get_option( 'awp_otp_settings', [] );
        	$curr_login_val     = $otp_settings['instance'] ?? '';
        
        	$curr_signup_val    = '';
        	if ( class_exists( 'AWP_Database_Manager' ) ) {
        		$dbm        = $dbm ?? new AWP_Database_Manager();
        		$signup_row = $dbm->get_signup_settings();
        		if ( ! empty( $signup_row['selected_instance'] ) ) {
        			$curr_signup_val = (string) $signup_row['selected_instance'];
        		}
        	}
        
        	$curr_checkout_val  = get_option( 'awp_selected_instance',             '' );
        	$curr_resend_val    = get_option( 'awp_selected_log_manager_instance', '' );
        
        	if ( class_exists( 'AWP_Database_Manager' ) ) {
        		$notif_settings = $dbm->get_notif_global();
        		$selected_csv   = $notif_settings['selected_instance_ids'] ?? '';
        		$selected_mult  = array_filter( array_map( 'intval', explode( ',', $selected_csv ) ) );
        	} else {
        		$selected_mult = [];
        	}
        
        	$online_instances = class_exists( 'AWP_Instances' ) ? $this->get_online_instances() : [];
        	
        	$this->maybe_autoselect_default_sender(
            $online_instances,
            $otp_settings,
            $curr_login_val,
            $curr_signup_val,
            $curr_checkout_val,
            $curr_resend_val,
            $selected_mult,
            $otp_global,
            $otp_login,
            $otp_signup,
            $otp_checkout,
            $notifications_enabled
        );
        
        	$otp_cols = [];
        	if ( $otp_global && $otp_login ) {
        		$otp_cols[] = [
        			'type'  => 'instance_id',
        			'label' => __( 'Login via WhatsApp', 'awp' ),
        			'name'  => 'awp_login_instance',
        			'value' => $curr_login_val,
        			'description' => 'Send OTP codes via WhatsApp to customers.',
        		];
        	}
        	if ( $otp_global && $otp_signup ) {
        		$otp_cols[] = [
        			'type'  => 'numeric_id',
        			'label' => __( 'Confirm New Users', 'awp' ),
        			'name'  => 'awp_signup_instance',
        			'value' => $curr_signup_val,
        			'description' => 'Send OTP codes via WhatsApp to verify customers before creating an account.',
        		];
        	}
        	if ( $otp_global && $otp_checkout ) {
        		$otp_cols[] = [
        			'type'  => 'instance_id',
        			'label' => __( 'Verify WooCommerce Orders', 'awp' ),
        			'name'  => 'awp_checkout_instance',
        			'value' => $curr_checkout_val,
        			'description' => 'Send OTP codes via WhatsApp to confirm customer phone number before completing checkout.',
        		];
        	}
        
        
        	$resend_col = [
        		'type'  => 'instance_id',
        		'label' => __( 'Resend Failed Messages', 'awp' ),
        		'name'  => 'awp_resend_instance',
        		'value' => $curr_resend_val,
        		'description' => 'Resends messages that failed to send from any of the other features.',
        	];
        
        	$any_otp = ! empty( $otp_cols );
        	$has_any = ( $any_otp || true );
        	?>
        	<form method="post">
        		<div class="awp-card awp-accordion" id="awp-card-sender" style="margin:20px 0;">
        
        			<div class="card-header_row awp-accordion-header">
        				<div class="card-header">
        					<h4 class="card-title"><i class="ri-whatsapp-line"></i> <?php esc_html_e( 'Choose WhatsApp Sender', 'awp' ); ?></h4>
        					<p><?php esc_html_e( 'Select the WhatsApp number that will be used to send messages for each feature.', 'awp' ); ?></p>
        				</div>
        			</div>
        
        			<div class="awp-accordion-content">
        				<?php wp_nonce_field( 'awp_choose_sender_save', 'awp_choose_sender_nonce' ); ?>
        
        				<div class="instances-setup">
        					<?php if ( ! $has_any ) : ?>
        
        						<p style="color:#a00;"><?php esc_html_e( 'All features are disabled, so there are no sender fields to configure.', 'awp' ); ?></p>
        
        					<?php else : ?>
        						<?php if ( $notifications_enabled ) : ?>
                                <div class="instance-select" style="min-width:100%;">
                                    <label for="wawp_notif_selected_instance">
                                        <?php esc_html_e( 'WhatsApp Notifications', 'awp' ); ?>
                                    </label>
                                    <?php if ( ! empty( $online_instances ) ) : ?>
                                        <p><?php esc_html_e( 'Choose one or more numbers to send notification events for users and admins.', 'awp' ); ?></p>
                                        <select name="wawp_notif_selected_instance[]" id="wawp_notif_selected_instance"
                                                multiple="multiple" class="wawp-instance-multi" style="width:100%;">
                                            <?php foreach ( $online_instances as $inst ) : ?>
                                                <option value="<?php echo (int) $inst->id; ?>"
                                                    <?php selected( in_array( (int) $inst->id, $selected_mult, true ), true ); ?>>
                                                    <?php echo esc_html( "{$inst->name} ({$inst->instance_id})" ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else : ?>
                                        <p style="color:#a00;margin:0;">
                                            <?php esc_html_e( 'No online instances found.', 'awp' ); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

        						<?php
        						
        						
        						if ( $any_otp ) {
        							foreach ( $otp_cols as $col ) {
        								echo $this->render_column( $col, $online_instances );
        							}
        						}
        
        						echo $this->render_column( $resend_col, $online_instances );
        						?>
        
        
        					<?php endif; ?>
        
        					<p class="submit awp_save">
        						<button type="submit" name="awp_choose_sender_submit" class="awp-btn primary"><?php esc_html_e( 'Save Changes', 'awp' ); ?></button>
        					</p>
        				</div>
        			</div>
        		</div>
        	</form>
        <?php
        }
    
        private function get_toggle($option){
            $val=get_option($option,'');
            if($val===''){
                return true;
            }
            return (bool)$val;
        }
    
        private function render_column($col,$instances){
            ob_start();
            echo'<div class="instance-select">';
            echo'<label for="'.esc_attr($col['name']).'">'.esc_html($col['label']).'</label>';
            echo'<p>'.esc_html($col['description']).'</p>';
            if(!empty($instances)){
                echo'<select id="'.esc_attr($col['name']).'" name="'.esc_attr($col['name']).'">';
                echo'<option value="">'.esc_html__('-- Select an online instance --','awp').'</option>';
                foreach($instances as $inst){
                    if($col['type']==='numeric_id'){
                        $val=(int)$inst->id;
                        $sel=selected($col['value'],$val,false);
                        echo'<option value="'.esc_attr($val).'" '.$sel.'>'.esc_html($inst->name.' (ID#'.$inst->id.')').'</option>';
                    } else {
                        $iid=$inst->instance_id;
                        $sel=selected($col['value'],$iid,false);
                        echo'<option value="'.esc_attr($iid).'" '.$sel.'>'.esc_html($inst->name.' ('.$inst->instance_id.')').'</option>';
                    }
                }
                echo'</select>';
            } else {
                echo'<p style="color:#a00;margin:0;">'.esc_html__('No online instances found. ','awp').'</p>';
            }
            echo'</div>';
            return ob_get_clean();
        }
        
    }
