<?php
namespace AWP;
if (!defined('ABSPATH')) exit;
require_once AWP_PLUGIN_DIR . 'includes/country-code-list.php';

class Wawp_CountryCode {
    
    public function __construct() {}
    
    public function init() {
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_scripts']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function enqueue_frontend_scripts() {
    $options = get_option('woo_intl_tel_options', []);
    $allowed_countries = isset($options['enabled_countries']) && is_array($options['enabled_countries'])
        ? $options['enabled_countries']
        : [];

    if ( empty($allowed_countries) ) {
        return;
    }
    
    wp_enqueue_style(
        'intl-tel-input-css',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/css/intlTelInput.min.css',
        [],
        '17.0.19'
    );
    wp_enqueue_script(
        'intl-tel-input-js',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/intlTelInput.min.js',
        [],
        '17.0.19',
        true
    );
    wp_enqueue_script(
        'intl-tel-input-utils-js',
        'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/utils.js',
        ['intl-tel-input-js'],
        '17.0.19',
        true
    );
    
    $default = !empty($options['default_country_code']) ? $options['default_country_code'] : 'us';
    $ip_detect = !empty($options['enable_ip_detection']);
    
    $phone_fields = [];
    if (!empty($options['phone_fields']) && is_array($options['phone_fields'])) {
        foreach ($options['phone_fields'] as $f) {
            if (isset($f['enabled']) && $f['enabled'] === '1' && !empty($f['id'])) {
                $phone_fields[] = [
                    'id'   => $f['id'],
                    'name' => isset($f['name']) ? $f['name'] : '',
                ];
            }
        }
    }
    
    wp_enqueue_script(
        'wawp-country-code-script',
        AWP_PLUGIN_URL . 'assets/js/block-checkout-intl-tel.js',
        ['intl-tel-input-js', 'intl-tel-input-utils-js', 'jquery'],
        '1.6',
        true
    );
    
    
    wp_localize_script('wawp-country-code-script', 'wooIntlTelSettings', [
        'defaultCountry'       => $default,
        'enableIpDetection'    => $ip_detect,
        'phoneFields'          => $phone_fields,
        'allowedCountries'     => $allowed_countries,
        'countryCodeAlignment' => isset($options['country_code_alignment']) ? $options['country_code_alignment'] : 'auto',
        'isRTL'                => is_rtl()
    ]);
    
    wp_localize_script('wawp-country-code-script', 'awpTelInputStrings', [
    'valid'             => __('✓ Valid phone number.', 'awp'),
    'invalid'           => __('✗ Invalid phone number.', 'awp'),
    'searchPlaceholder' => __('Search country', 'awp'),
    'waiting'           => __('Number updates automatically after writing.', 'awp')
]);

}
    
    public function register_settings() {
        register_setting('woo_intl_tel_options_group','woo_intl_tel_options');
        add_settings_section('woo_intl_tel_main_section','',null,'wawp-country-code-settings');
        add_settings_field('default_country_code', __('Default Country Code','awp'),[$this,'default_country_code_callback'],'wawp-country-code-settings','woo_intl_tel_main_section');
        add_settings_field('enable_ip_detection', __('Enable IP Detection','awp'),[$this,'enable_ip_detection_callback'],'wawp-country-code-settings','woo_intl_tel_main_section');
        add_settings_field('phone_fields', __('Phone Fields','awp'),[$this,'phone_fields_callback'],'wawp-country-code-settings','woo_intl_tel_main_section');
        add_settings_field('country_code_alignment', __('Country Code Alignment','awp'), [$this, 'country_code_alignment_callback'], 'wawp-country-code-settings','woo_intl_tel_main_section');
        add_settings_field('enabled_countries', __('Country Code Dropdown','awp'),[$this,'enabled_countries_callback'],'wawp-country-code-settings','woo_intl_tel_main_section');
    }
    
    public function country_code_alignment_callback() {
    $options   = get_option('woo_intl_tel_options', []);
    $alignment = isset($options['country_code_alignment']) ? $options['country_code_alignment'] : 'auto';
    ?>
    <div class="awp-country-code-alignment">
        <label>
            <input type="radio"
                   name="woo_intl_tel_options[country_code_alignment]"
                   value="auto"
                   <?php checked($alignment, 'auto'); ?>>
            <?php esc_html_e('Auto (Based on site language)', 'awp'); ?>
        </label>

        <label>
            <input type="radio"
                   name="woo_intl_tel_options[country_code_alignment]"
                   value="left"
                   <?php checked($alignment, 'left'); ?>>
            <?php esc_html_e('Left', 'awp'); ?>
        </label>

        <label>
            <input type="radio"
                   name="woo_intl_tel_options[country_code_alignment]"
                   value="right"
                   <?php checked($alignment, 'right'); ?>>
            <?php esc_html_e('Right', 'awp'); ?>
        </label>
    </div>
    <?php
}

    private function iso2_to_flag($iso) {
        $iso = strtoupper($iso);
        $flag='';
        for($i=0;$i<mb_strlen($iso);$i++){
            $code=127397+ord($iso[$i]);
            $flag.=mb_convert_encoding('&#'.$code.';','UTF-8','HTML-ENTITIES');
        }
        return $flag;
    }
    
    private function get_all_countries() {
        if (function_exists('AWP\\awp_get_all_countries')) return \AWP\awp_get_all_countries();
        return [
            ['iso2'=>'us','name'=>__('United States','awp'),'region'=>__('Americas','awp')],
            ['iso2'=>'eg','name'=>__('Egypt','awp'),'region'=>__('Middle East','awp')]
        ];
    }

    public function settings_page() {
        
        if (!current_user_can('manage_options')) wp_die(__('You do not have permission to view this page.','awp')); ?>
        <div class="wrap">
            
            <div class="page-header_row">
                    <div class="page-header">
                        <h2 class="page-title"><?php esc_html_e('Advanced Phone Field','awp'); ?></h2> 
                        <p><?php esc_html_e('Add country code selection directly into any phone field.','awp'); ?>
                        <a href="https://wawp.net/get-started/country-code-selection/" target="_blank"><?php esc_html_e('Learn more','awp'); ?></a>
                        </p>
                    </div>
            </div>
            <div class="awp-country-settings">
                <form action="options.php" method="post">
                    <?php settings_fields('woo_intl_tel_options_group'); ?>
                    
                    <?php do_settings_sections('wawp-country-code-settings'); ?>
                        
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
    <?php }

    public function default_country_code_callback() {
    $options = get_option('woo_intl_tel_options', []);
    $default = isset($options['default_country_code']) ? $options['default_country_code'] : '';
    $selected_countries = ! empty($options['enabled_countries']) && is_array($options['enabled_countries'])
        ? $options['enabled_countries']
        : [];
    
    $all = $this->get_all_countries();
    
    $filtered_countries = array_filter($all, function($c) use ($selected_countries) {
        return in_array($c['iso2'], $selected_countries);
    });
    
    echo '<div class="awp-setting-end"><select id="awp-default-country-code" name="woo_intl_tel_options[default_country_code]">';
    if ( empty($filtered_countries) ) {
        echo '<option value="">' . esc_html__('No country code enabled', 'awp') . '</option>';
    } else {
        foreach ($filtered_countries as $c) {
            $iso2   = $c['iso2'];
            $name   = $c['name'];
            $flag   = $this->iso2_to_flag($iso2);
            $selected_attr = selected($default, $iso2, false);
            printf(
                '<option value="%1$s" %2$s data-countryname="%3$s">%4$s %5$s</option>',
                esc_attr($iso2),
                $selected_attr,
                esc_attr(strtolower($name)),
                $flag,
                esc_html($name)
            );
        }
    }
    echo '</select></div>';
    ?>
    <script>
    (function($){
      $(document).ready(function(){
          function updateDefaultCountrySelect() {
              var enabledCountries = [];
              $('#awp-selected-countries-container input[name="woo_intl_tel_options[enabled_countries][]"]').each(function(){
                  enabledCountries.push($(this).val());
              });
              $('#awp-default-country-code option').each(function(){
                  var iso = $(this).val();
                  if (enabledCountries.indexOf(iso) > -1) {
                      $(this).show();
                  } else {
                      $(this).hide();
                  }
              });
              if (enabledCountries.length === 0) {
                  $('#awp-default-country-code').val('');
              } else {
                  var currentDefault = $('#awp-default-country-code').val();
                  if (enabledCountries.indexOf(currentDefault) === -1) {
                      $('#awp-default-country-code').val(enabledCountries[0]);
                  }
              }
          }
          
          updateDefaultCountrySelect();
          
          $('#awp-selected-countries-container input[name="woo_intl_tel_options[enabled_countries][]"]').on('change', function(){
              updateDefaultCountrySelect();
          });
          
      });
    })(jQuery);
    </script>
    <?php
}

    public function enable_ip_detection_callback() {
        $o = get_option('woo_intl_tel_options',[]);
        $e = isset($o['enable_ip_detection'])?$o['enable_ip_detection']:'';
        echo '<div class="custom-control custom-switch"><input type="checkbox" id="ip_detection" class="custom-control-input" name="woo_intl_tel_options[enable_ip_detection]" value="1" '.checked(1,$e,false).'>
                <label class="custom-control-label" for="ip_detection"></label></div>';
    }

    public function phone_fields_callback() {
        $o = get_option('woo_intl_tel_options',[]);
        if (empty($o['phone_fields'])||!is_array($o['phone_fields'])) {
            $o['phone_fields']=[
                ['id'=>'#billing-phone','name'=>'Woocommerce Gutenberg react','enabled'=>'1'],
                ['id'=>'#billing_phone','name'=>'Normal Woocommerce Checkout','enabled'=>'1'],
                ['id'=>'#awp_whatsapp','name'=>'Wawp Login Form','enabled'=>'1'],
                ['id'=>'#awp_phone','name'=>'Wawp Signup Form','enabled'=>'1'],
            ];
        }
        echo '<table class="awp-phone-fields-table">';
        $i=0;
        foreach($o['phone_fields'] as $pf){
            $fid=isset($pf['id'])?$pf['id']:'';
            $fname=isset($pf['name'])?$pf['name']:'';
            $fen=isset($pf['enabled'])?$pf['enabled']:'1';
            echo '<tr>';
            echo '<td class="awp-field"><label>'.esc_html__('Field ID','awp').'</label><input type="text" name="woo_intl_tel_options[phone_fields]['.$i.'][id]" value="'.esc_attr($fid).'" style="width:100%;"></td>';
            echo '<td class="awp-field"><label>'.esc_html__('Field Name','awp').'</label><input type="text" name="woo_intl_tel_options[phone_fields]['.$i.'][name]" value="'.esc_attr($fname).'" style="width:100%;"></td>';
            echo '<td class="adv-phone-enable"><span class="awp-phone-enable-btn awp-badge '.($fen==='1'?'success':'error').'">'.($fen==='1'?esc_html__('Enabled','awp'):esc_html__('Disabled','awp')).'</span>';
            echo '<input type="hidden" id="awp_phone_fields_enabled_'.$i.'" name="woo_intl_tel_options[phone_fields]['.$i.'][enabled]" value="'.esc_attr($fen).'"></td>';
            echo '</tr>';
            $i++;
        }
        echo '</table><p class="awp-card-btn"><button class="awp-btn secondary" id="awp-add-new-phone-field"><i class="ri-add-line"></i> '.esc_html__('Add New Phone Field','awp').'</button></p>';
    }

    public function enabled_countries_callback() {
        $o=get_option('woo_intl_tel_options',[]);
        $sel=isset($o['enabled_countries'])?$o['enabled_countries']:[];
        $all=$this->get_all_countries();
        $by=[];
        foreach($all as $c){
            $r=isset($c['region'])?$c['region']:__('Other','awp');
            if(!isset($by[$r]))$by[$r]=[]; $by[$r][]=$c;
        }
        ksort($by);
        echo '<div class="btn-group" style="margin-bottom: 1.25rem;">';
        echo '<input type="text" id="awp-country-search-input" placeholder="'.esc_attr__('Search countries...','awp').'" style="flex: 1;"> ';
        echo '<a href="#" id="awp-select-all" class="awp-btn"><i class="ri-checkbox-multiple-fill"></i> '.esc_html__('Select All','awp').'</a> ';
        echo '<a href="#" id="awp-deselect-all" class="awp-btn delete"><i class="ri-checkbox-indeterminate-line"></i> '.esc_html__('Deselect All','awp').'</a>';
        echo '</div><div id="awp-selected-countries-container" style="display:none;">';
        foreach($sel as $iso2) {
            printf('<input type="hidden" id="awp-country-hidden-input-%1$s" name="woo_intl_tel_options[enabled_countries][]" value="%1$s">',esc_attr($iso2));
        }
        echo '</div><div class="awp-country-list">';
        foreach($by as $region=>$cs){
            echo '<div class="awp-region-group collapsed"><div class="awp-region-title"> '.esc_html($region).'<i class="ri-arrow-right-s-line"></i></div><div class="awp-region-countries">';
            foreach($cs as $co){
                $iso2=$co['iso2'];
                $n=$co['name'];
                $fl=$this->iso2_to_flag($iso2);
                $has=in_array($iso2,$sel);
                echo '<div class="awp-country-card '.($has?'selected':'').'" data-iso2="'.esc_attr($iso2).'" data-countryname="'.esc_attr($n).'">';
                echo '<span class="awp-country-flag">'.$fl.'</span> <span class="awp-country-name">'.esc_html($n).'</span>';
                echo '</div>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }
    
}
