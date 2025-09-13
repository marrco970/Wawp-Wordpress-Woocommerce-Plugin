<?php
/**
 * bricks-elements.php
 * Defines three custom Bricks elements for WAWP shortcodes.
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}



/**
 * 1) WAWP Login Element -> [awp_otp_login]
 */
class My_WAWP_Login_Element extends \Bricks\Element {
  public $name         = 'my-wawp-login';
  public $category     = 'general';
  public $icon         = 'ti-lock';
  public $css_selector = '.my-wawp-login-wrapper';

  public function get_label() {
    return esc_html__( 'WAWP Login', 'awp' );
  }

  public function set_control_groups() {}
  public function set_controls() {}

  public function render() {
    $this->set_attribute( '_root', 'class', 'my-wawp-login-wrapper' );
    echo '<div ' . $this->render_attributes( '_root' ) . '>';
      echo do_shortcode( '[wawp_otp_login]' );
    echo '</div>';
  }
}

/**
 * 2) WAWP Signup Element -> [awp_signup_form]
 */
class My_WAWP_Signup_Element extends \Bricks\Element {
  public $name         = 'my-wawp-signup';
  public $category     = 'general';
  public $icon         = 'ti-user';
  public $css_selector = '.my-wawp-signup-wrapper';

  public function get_label() {
    return esc_html__( 'WAWP Signup', 'awp' );
  }
  public function set_control_groups() {}
  public function set_controls() {}

  public function render() {
    $this->set_attribute( '_root', 'class', 'my-wawp-signup-wrapper' );
    echo '<div ' . $this->render_attributes( '_root' ) . '>';
      echo do_shortcode( '[wawp_signup_form]' );
    echo '</div>';
  }
}

/**
 * 3) WAWP Fast Login Element -> [wawp-fast-login]
 */
class My_WAWP_Fast_Login_Element extends \Bricks\Element {
  public $name         = 'my-wawp-fast-login';
  public $category     = 'general';
  public $icon         = 'ti-bolt-alt';
  public $css_selector = '.my-wawp-fast-login-wrapper';

  public function get_label() {
    return esc_html__( 'WAWP Fast Login', 'awp' );
  }
  public function set_control_groups() {}
  public function set_controls() {}

  public function render() {
    $this->set_attribute( '_root', 'class', 'my-wawp-fast-login-wrapper' );
    echo '<div ' . $this->render_attributes( '_root' ) . '>';
      echo do_shortcode( '[wawp-fast-login]' );
    echo '</div>';
  }
}


