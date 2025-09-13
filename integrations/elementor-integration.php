<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register WAWP shortcodes as Elementor widgets if Elementor is active.
 */
add_action( 'plugins_loaded', function() {

    // Only run if Elementor has finished loading.
    if ( did_action( 'elementor/loaded' ) ) {

        // Use the newer 'elementor/widgets/register' hook if available,
        // otherwise fall back to the older 'elementor/widgets/widgets_registered'.
        $hook = has_action( 'elementor/widgets/register' )
            ? 'elementor/widgets/register'
            : 'elementor/widgets/widgets_registered';

        add_action( $hook, function( $widgets_manager ) {

            // 1. Define a class for the WAWP Login Widget.
            class WAWP_Login_Widget extends \Elementor\Widget_Base {
                public function get_name() {
                    return 'wawp_login_widget';
                }
                public function get_title() {
                    return __( 'WAWP Login', 'awp' );
                }
                public function get_icon() {
                    return 'eicon-lock-user';
                }
                public function get_categories() {
                    return [ 'general' ];
                }
                protected function render() {
                    echo do_shortcode( '[wawp_otp_login]' );
                }
            }

            // 2. Define a class for the WAWP Signup Widget.
            class WAWP_Signup_Widget extends \Elementor\Widget_Base {
                public function get_name() {
                    return 'wawp_signup_widget';
                }
                public function get_title() {
                    return __( 'WAWP Signup', 'awp' );
                }
                public function get_icon() {
                    return 'eicon-user-circle-o';
                }
                public function get_categories() {
                    return [ 'general' ];
                }
                protected function render() {
                    echo do_shortcode( '[wawp_signup_form]' );
                }
            }

            // 3. Define a class for the WAWP Fast Login Widget.
            class WAWP_Fast_Login_Widget extends \Elementor\Widget_Base {
                public function get_name() {
                    return 'wawp_fast_login_widget';
                }
                public function get_title() {
                    return __( 'WAWP Fast Login', 'awp' );
                }
                public function get_icon() {
                    return 'eicon-lock';
                }
                public function get_categories() {
                    return [ 'general' ];
                }
                protected function render() {
                    echo do_shortcode( '[wawp-fast-login]' );
                }
            }

            // Finally, register our widgets with Elementor.
            $widgets_manager->register_widget_type( new WAWP_Login_Widget() );
            $widgets_manager->register_widget_type( new WAWP_Signup_Widget() );
            $widgets_manager->register_widget_type( new WAWP_Fast_Login_Widget() );
        });
    }
});
