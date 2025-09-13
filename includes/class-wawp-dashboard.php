<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wawp_Dashboard {

	public function render_page() {
		$current_domain       = wp_parse_url( get_site_url(), PHP_URL_HOST );
		$banned_msg           = get_transient( 'siteB_banned_msg' );
		$token                = get_option( 'mysso_token' );
		$user_data            = get_transient( 'siteB_user_data' );
		$segmentation_allowed = (bool) ( $user_data['segmentation_notifications'] ?? false );

		if ( $banned_msg ) {
			printf(
				'<div class="wrap"><h1><i class="ri-lock-line"></i> %s</h1><p style="color:red;">%s</p></div>',
				esc_html__( 'Wawp Dashboard', 'awp' ),
				esc_html( Wawp_Global_Messages::get( 'blocked_generic' ) )
			);
			return;
		}

		if ( ! $token ) {
			printf(
				'<div class="wrap"><h1><i class="dashicons dashicons-lock"></i> %s</h1><p>%s</p></div>',
				esc_html__( 'Wawp Dashboard', 'awp' ),
				esc_html( Wawp_Global_Messages::get( 'need_login' ) )
			);
			return;
		}

		if ( $user_data && ( $user_data['sites'][ $current_domain ] ?? '' ) !== 'active' ) {
			printf(
				'<div class="wrap"><h1><i class="ri-lock-line"></i> %s</h1><p style="color:red;">%s</p></div>',
				esc_html__( 'Wawp Dashboard', 'awp' ),
				esc_html( Wawp_Global_Messages::get( 'not_active_site' ) )
			);
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$dm              = new AWP_Database_Manager();
		$user_info_table = $dm->get_user_info_table_name();

		$total_wp_users = get_transient( 'wawp_total_users' );
		if ( false === $total_wp_users ) {
			$total_wp_users = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users}" );
			set_transient( 'wawp_total_users', $total_wp_users, HOUR_IN_SECONDS );
		}

		$info_stats = get_transient( 'wawp_userinfo_stats' );
		if ( false === $info_stats ) {
			$info_stats = $wpdb->get_row(
				"SELECT
					COUNT(*)                                             AS total_rows,
					SUM(phone <> '' AND phone <> 'N/A')                 AS users_with_phone,
					SUM(whatsapp_verified = 'Verified')                 AS verified,
					SUM(whatsapp_verified = 'Not Verified')             AS not_verified
				 FROM {$user_info_table}",
				ARRAY_A
			);
			set_transient( 'wawp_userinfo_stats', $info_stats, 5 * MINUTE_IN_SECONDS );
		}

		$users_with_phone   = (int) $info_stats['users_with_phone'];
		$total_verified     = (int) $info_stats['verified'];
		$total_not_verified = (int) $info_stats['not_verified'];

		$is_tracking_ids_enabled  = get_option( Wawp_Api_Url::OPT_TRACKING_IDS, Wawp_Api_Url::DEF_TRACKING_IDS );
		$is_chat_widget_enabled   = get_option( 'awp_chat_widget_enabled', 1 );
		$is_notifications_enabled = get_option( 'awp_notifications_enabled', 1 );
		$is_wawp_otp_enabled      = get_option( 'awp_wawp_otp_enabled', 1 );
		$is_otp_login_enabled     = get_option( 'awp_otp_login_enabled', 1 );
		$is_signup_enabled        = get_option( 'awp_signup_enabled', 1 );
		$is_checkout_otp_enabled  = get_option( 'awp_checkout_otp_enabled', 1 );

		if ( ! class_exists( 'WooCommerce' ) && $is_checkout_otp_enabled ) {
			update_option( 'awp_checkout_otp_enabled', 0 );
			$is_checkout_otp_enabled = 0;
		}

		$is_countrycode_enabled = get_option( 'awp_countrycode_enabled', 1 );
		$is_campaigns_enabled   = get_option( 'awp_campaigns_enabled', 0 );

		wp_localize_script(
			'awp-dashboard-js',
			'awpVars',
			[
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'verified'     => $total_verified,
				'not_verified' => $total_not_verified,
			]
		);
		wp_nonce_field( 'awp_live_toggle_nonce', 'awp_live_toggle_nonce' );
		?>
		<div class="wrap awp-dashboard-wrapper">
			<div class="awp-dashboard-content">
				<div class="page-header_row">
					<div class="page-header">
						<h2 class="page-title"><?php esc_html_e( 'Welcome to Wawp', 'awp' ); ?></h2>
						<p><?php esc_html_e( 'Thank you so much for using us!', 'awp' ); ?></p>
						<?php if ( ! class_exists( 'WooCommerce' ) ) :
							$woo_file              = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
							$woocommerce_installed = file_exists( $woo_file );
							?>
							<div style="margin-top:12px;padding:16px;border:1px solid #ccc;background:#fff;">
								<p style="font-size:14px;margin:0 0 8px;">
									<strong><?php esc_html_e( 'To enable Order Notifications, WooCommerce must be installed and active.', 'awp' ); ?></strong>
								</p>
								<?php
								if ( $woocommerce_installed ) {
									$activate_url = wp_nonce_url(
										'plugins.php?action=activate&plugin=woocommerce/woocommerce.php',
										'activate-plugin_woocommerce/woocommerce.php'
									);
									printf(
										'<a class="button button-primary" href="%s">%s</a>',
										esc_url( $activate_url ),
										esc_html__( 'Activate WooCommerce', 'awp' )
									);
								} else {
									$install_url = wp_nonce_url(
										self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
										'install-plugin_woocommerce'
									);
									printf(
										'<a class="button button-primary" href="%s">%s</a>',
										esc_url( $install_url ),
										esc_html__( 'Install WooCommerce', 'awp' )
									);
								}
								?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="awp-cards">

					<div class="awp-card">
						<div class="card-header_row">
							<div class="card-header">
								<span class="card-title"><?php esc_html_e( 'Total WordPress Users', 'awp' ); ?></span>
								<span class="stats"><i class="ri-group-line"></i><?php echo esc_html( $total_wp_users ); ?></span>
							</div>
							<a class="awp-btn secondary" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" target="_blank">
								<?php esc_html_e( 'View All Users', 'awp' ); ?>
							</a>
						</div>
						<div style="display:flex;gap:1rem;">
							<div class="stats-header">
								<span class="card-label"><?php esc_html_e( 'Verified', 'awp' ); ?></span>
								<span class="card-number green"><?php echo esc_html( $total_verified ); ?></span>
							</div>
							<div class="stats-header">
								<span class="card-label"><?php esc_html_e( 'Not Verified', 'awp' ); ?></span>
								<span class="card-number red"><?php echo esc_html( $total_not_verified ); ?></span>
							</div>
							<div class="stats-header">
								<span class="card-label"><?php esc_html_e( 'Users with Phone number', 'awp' ); ?></span>
								<span class="card-number"><?php echo esc_html( $users_with_phone ); ?></span>
							</div>
						</div>
					</div>

					<div class="awp-card" style="display:flex;flex-direction:column;gap:12px;">
						<div class="awp-toggle-group awp-toggle-main">
							<label class="awp-switch-label">
								<h4 class="card-title"><i class="ri-whatsapp-line"></i><?php esc_html_e( 'WhatsApp Chat Button', 'awp' ); ?></h4>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wawp&awp_section=chat_widget' ) ); ?>" class="awp-setting-icon" style="display:<?php echo $is_chat_widget_enabled ? 'flex' : 'none'; ?>;" target="_blank"><i class="ri-settings-3-line"></i></a>
							</label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_chat_widget_enabled" <?php checked( $is_chat_widget_enabled, 1 ); ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
					</div>

					<div class="awp-card" style="display:flex;flex-direction:column;gap:12px;">
						<div class="awp-toggle-group awp-toggle-main">
							<label class="awp-switch-label">
								<h4 class="card-title"><i class="ri-global-line"></i><?php esc_html_e( 'Advanced Phone Field', 'awp' ); ?></h4>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wawp&awp_section=settings' ) ); ?>" class="awp-setting-icon" style="display:<?php echo $is_countrycode_enabled ? 'flex' : 'none'; ?>;" target="_blank"><i class="ri-settings-3-line"></i></a>
							</label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_countrycode_enabled" <?php checked( $is_countrycode_enabled, 1 ); ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
					</div>

					<div class="awp-card" style="display:flex;flex-direction:column;gap:12px;">
						<div class="awp-toggle-group awp-toggle-main">
							<label class="awp-switch-label">
								<h4 class="card-title"><i class="ri-hashtag"></i><?php esc_html_e( 'Append Unique / Message IDs', 'awp' ); ?></h4>
							</label>
							<label class="awp-switch">
								<input type="checkbox" data-option="<?php echo esc_attr( Wawp_Api_Url::OPT_TRACKING_IDS ); ?>" <?php checked( $is_tracking_ids_enabled, 1 ); ?> onchange="handleToggle(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
					</div>

					<div class="awp-card" style="display:flex;flex-direction:column;gap:12px;">
						<div class="awp-toggle-group awp-toggle-main">
							<label class="awp-switch-label">
								<h4 class="card-title"><i class="ri-notification-3-line"></i><?php esc_html_e( 'Notifications', 'awp' ); ?></h4>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wawp&awp_section=notifications' ) ); ?>" class="awp-setting-icon" style="display:<?php echo $is_notifications_enabled ? 'flex' : 'none'; ?>;" target="_blank"><i class="ri-settings-3-line"></i></a>
							</label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_notifications_enabled" <?php checked( $is_notifications_enabled, 1 ); ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
					</div>

					<div class="awp-card" style="display:flex;flex-direction:column;gap:12px;">
						<div class="awp-toggle-group awp-toggle-main">
							<label class="awp-switch-label">
								<h4 class="card-title"><i class="ri-lock-password-line"></i><?php esc_html_e( 'OTP Verifications', 'awp' ); ?></h4>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wawp&awp_section=otp_messages' ) ); ?>" class="awp-setting-icon" style="display:<?php echo $is_wawp_otp_enabled ? 'flex' : 'none'; ?>;" target="_blank"><i class="ri-settings-3-line"></i></a>
							</label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_wawp_otp_enabled" <?php checked( $is_wawp_otp_enabled, 1 ); ?> onchange="handleOtpParentToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
						<div class="h-divider" style="margin:12px 0;"></div>
						<div class="awp-toggle-group awp-sub-toggle">
							<label class="awp-switch-label"><?php esc_html_e( 'Enable Login OTP', 'awp' ); ?></label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_otp_login_enabled" <?php checked( $is_otp_login_enabled, 1 ); ?> <?php echo $is_wawp_otp_enabled ? '' : 'disabled'; ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
						<div class="awp-toggle-group awp-sub-toggle">
							<label class="awp-switch-label"><?php esc_html_e( 'Enable Signup OTP', 'awp' ); ?></label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_signup_enabled" <?php checked( $is_signup_enabled, 1 ); ?> <?php echo $is_wawp_otp_enabled ? '' : 'disabled'; ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
						<div class="awp-toggle-group awp-sub-toggle">
							<label class="awp-switch-label"><?php esc_html_e( 'Enable Checkout OTP', 'awp' ); ?></label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_checkout_otp_enabled" <?php checked( $is_checkout_otp_enabled, 1 ); ?> <?php echo $is_wawp_otp_enabled ? '' : 'disabled'; ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
					</div>

					<div class="awp-card" style="display:flex;flex-direction:column;gap:12px;">
						<div class="awp-toggle-group awp-toggle-main">
							<label class="awp-switch-label">
								<h4 class="card-title"><i class="ri-funds-line"></i><?php esc_html_e( 'Bulk Campaigns', 'awp' ); ?></h4>
								<?php if ( ! $segmentation_allowed ) : ?>
									<a href="https://wawp.net/product/segmentation-notifications-via-whatsapp-and-email/" target="_blank"><span class="awp-badge" style="background:#ff9f00;color:#fff;margin-left:4px;"><?php esc_html_e( 'Get now Segmentation Notifications feature', 'awp' ); ?></span></a>
								<?php endif; ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wawp&awp_section=campaigns' ) ); ?>" class="awp-setting-icon" style="display:<?php echo ( $is_campaigns_enabled && $segmentation_allowed ) ? 'flex' : 'none'; ?>;" target="_blank"><i class="ri-settings-3-line"></i></a>
							</label>
							<label class="awp-switch">
								<input type="checkbox" data-option="awp_campaigns_enabled" <?php checked( $is_campaigns_enabled, 1 ); ?> <?php echo $segmentation_allowed ? '' : 'disabled'; ?> onchange="handleToggle(this); awpToggleIconVisibility(this);">
								<span class="awp-slider"></span>
							</label>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
	}
}

add_filter(
    'pre_count_users',
    function ( $override ) {
        // Screen check to ensure this only runs on the Wawp admin page.
        if ( ! function_exists( 'get_current_screen' ) ) {
            return $override;
        }
        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_wawp' !== $screen->id ) {
            return $override; // Let WordPress handle user counts on other pages.
        }

        if ( ! empty( $override ) ) {
            return $override;
        }
        $total = get_transient( 'wawp_total_users' );
        if ( false === $total ) {
            global $wpdb;
            $total = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users}" );
            set_transient( 'wawp_total_users', $total, HOUR_IN_SECONDS );
        }
        return [
            'total_users' => $total,
            'avail_roles' => [], 
        ];
    }
);

add_action(
	'wp_ajax_awp_save_toggle',
	function () {
		check_ajax_referer( 'awp_live_toggle_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		$option_name  = isset( $_POST['option_name'] ) ? sanitize_key( $_POST['option_name'] ) : '';
		$option_value = isset( $_POST['option_value'] ) ? (int) $_POST['option_value'] : 0;
		if ( $option_name ) {
			update_option( $option_name, $option_value );
			wp_send_json_success();
		}
		wp_send_json_error( 'Missing option name', 400 );
	}
);
