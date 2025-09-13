<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wawp_others_register_block_patterns() {

    // First, register a custom block pattern category.
    if ( function_exists( 'register_block_pattern_category' ) ) {
        register_block_pattern_category(
            'awp',
            [ 'label' => __( 'WAWP', 'awp' ) ]
        );
    }

    // Then, register each pattern in that category.
    if ( function_exists( 'register_block_pattern' ) ) {

        register_block_pattern(
            'awp/wawp-fast-login-form',
            [
                'title'       => __( 'WAWP Login/Signup Form', 'awp' ),
                'description' => __( 'A combined login and signup form for WAWP.', 'awp' ),
                'content'     => '<!-- wp:shortcode -->[wawp-fast-login]<!-- /wp:shortcode -->',
                'categories'  => [ 'awp' ],
                'keywords'    => [ 'wawp', 'login', 'signup' ],
            ]
        );

        register_block_pattern(
            'awp/wawp-login-form',
            [
                'title'       => __( 'WAWP Login Form', 'awp' ),
                'description' => __( 'A dedicated login form for WAWP.', 'awp' ),
                'content'     => '<!-- wp:shortcode -->[wawp_otp_login]<!-- /wp:shortcode -->',
                'categories'  => [ 'awp' ],
                'keywords'    => [ 'wawp', 'login', 'form' ],
            ]
        );

        register_block_pattern(
            'awp/wawp-signup-form',
            [
                'title'       => __( 'WAWP Signup Form', 'awp' ),
                'description' => __( 'A dedicated signup form for WAWP.', 'awp' ),
                'content'     => '<!-- wp:shortcode -->[wawp_signup_form]<!-- /wp:shortcode -->',
                'categories'  => [ 'awp' ],
                'keywords'    => [ 'wawp', 'signup', 'form' ],
            ]
        );
    }
}

add_action( 'init', 'wawp_others_register_block_patterns' );
