<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AWP_Admin_Notices {

	public static function require_online_instance( $awp_instances, string $title = 'No online instance (Whatsapp Number)' ) : bool {

		$online = [];

		if ( is_object( $awp_instances ) && method_exists( $awp_instances, 'get_online_instances' ) ) {
			$online = $awp_instances->get_online_instances();

		} elseif ( is_array( $awp_instances ) ) {
			$online = $awp_instances;

		} else {
			global $wpdb;
			$online = $wpdb->get_results(
				"SELECT id
				   FROM {$wpdb->prefix}awp_instance_data
				  WHERE status = 'online'"
			);
		}

		if ( ! empty( $online ) ) {
			return true;
		}

		self::render_no_instance_notice( $title );
		return false;
	}

	private static function render_no_instance_notice( string $title ) : void {

		echo '<div class="wrap">
				<h1><i class="ri-lock-line"></i> ' . esc_html( $title ) . '</h1>
				<p style="color:red;">' .
					esc_html__(
						'No instance is online! Please connect an instance before using these features.',
						'awp'
					) .
				'</p>
				<p>
					<a href="' . esc_url(
						admin_url( 'admin.php?page=wawp&awp_section=instances#tab-wa' )
					) . '" class="awp-btn awp-btn-green" id="awp-open-add-modal" style="width:fit-content;">
						<i class="ri-add-line"></i> ' . esc_html__( 'Add New Instance', 'awp' ) . '
					</a>
				</p>
			</div>';
	}
}
