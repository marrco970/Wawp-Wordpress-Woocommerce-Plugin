<?php
/**
 * class-awp-message-parser.php
 * 
 * Supports placeholders for both WooCommerce orders and WordPress user data (e.g. user login/signup).
 * Custom meta fields are also supported. 
 * 
 * USAGE:
 *   In your templates or messages, use {{placeholder_name}} to dynamically replace with order/user/site info.
 *
 * FULL PLACEHOLDER LIST:
 *
 * 1. GENERAL (always applies):
 *   {{sitename}}          -> Site Title (Blog Name)
 *   {{siteurl}}           -> Home URL of the site
 *   {{wordpress-url}}      -> WordPress install URL (site_url)
 *   {{tagline}}           -> Site Tagline / Description
 *   {{privacy-policy}}     -> Privacy Policy Page URL (if set)
 *
 * 2. WORDPRESS USER (if NO WC order, but user info is present):
 *   - Original:
 *     {{user_name}}            -> user_login
 *     {{user_first_last_name}} -> "first_name last_name" from user meta
 *     {{wc_billing_first_name}} -> billing_first_name user meta
 *     {{wc_billing_last_name}}  -> billing_last_name user meta
 *     {{wc_billing_phone}}      -> billing_phone user meta
 *     {{shop_name}}             -> Site Title
 *     {{current_date_time}}     -> Current server date/time (Y-m-d H:i:s)
 *     {{site_link}}             -> site_url()
 *     {{customer_note}}         -> 'N/A' or any base_replacement if provided
 *
 *   - New (wp- prefix):
 *     {{wp-first-name}}   -> user_meta('first_name')
 *     {{wp-last-name}}    -> user_meta('last_name')
 *     {{wp-username}}     -> user->user_login
 *     {{wp-nickname}}     -> user_meta('nickname')
 *     {{wp-display-name}} -> user->display_name
 *     {{wp-email}}        -> user->user_email
 *     {{wp-user-website}} -> user->user_url
 *     {{wp-user-bio}}     -> user->description
 *
 *   - Custom User Meta:
 *     e.g. {{any_user_meta_key}} -> get_user_meta($user->ID, 'any_user_meta_key', true)
 *
 * 3. WOOCOMMERCE ORDER (if an order object is present):
 *   - Legacy / Original:
 *     {{id}}, {{order_key}}, {{billing_first_name}}, {{billing_last_name}}, {{billing_company}},
 *     {{billing_address_1}}, {{billing_address_2}}, {{billing_city}}, {{billing_postcode}},
 *     {{billing_country}}, {{billing_state}}, {{billing_email}}, {{billing_phone}},
 *     {{shipping_first_name}}, {{shipping_last_name}}, {{shipping_company}},
 *     {{shipping_address_1}}, {{shipping_address_2}}, {{shipping_city}}, {{shipping_postcode}},
 *     {{shipping_country}}, {{shipping_state}}, {{shipping_method}}, {{shipping_method_title}},
 *     {{bacs_account}}, {{payment_method}}, {{payment_method_title}}, {{order_subtotal}},
 *     {{order_discount}}, {{cart_discount}}, {{order_tax}}, {{order_shipping}}, {{order_shipping_tax}},
 *     {{order_total}}, {{status}}, {{shop_name}}, {{currency}}, {{cust_note}}, {{note}},
 *     {{product}}, {{product_name}}, {{dpd}}, {{unique_transfer_code}}, {{order_date}},
 *     {{order_link}}, {{transaction_id}}, {{current_date_time}}, {{user_name}},
 *     {{user_first_last_name}}, {{wc_billing_first_name}}, {{wc_billing_last_name}},
 *     {{wc_billing_phone}}, {{site_link}}
 *
 *   - Additional WooCommerce (wc- prefix):
 *     {{wc-order}}, {{wc-order-id}}, {{wc-order-status}}, {{wc-order-date}},
 *     {{wc-product-names}}, {{wc-product-names-br}}, {{wc-product-names-variable}}, {{wc-product-names-variable-br}},
 *     {{wc-product-name-count}}, {{wc-product-link}}, {{wc-product-link-br}},
 *     {{wc-product-name-link}}, {{wc-product-name-link-br}}, {{wc-total-products}}, {{wc-total-items}},
 *     {{wc-order-items}}, {{wc-order-items-br}}, {{wc-order-items-variable}}, {{wc-order-items-variable-br}},
 *     {{wc-order-items-price}}, {{wc-order-items-price-br}}, {{wc-all-order-items-br}},
 *     {{wc-sku}}, {{wc-sku-br}}, {{wc-order-amount}}, {{wc-discount}}, {{wc-tax}}, {{wc-order-amount-ex-tax}},
 *     {{wc-payment-method}}, {{wc-transaction-id}}, {{wc-shipping-method}}, {{wc-shipping-cost}},
 *     {{wc-refund-amount}}, {{wc-refund-reason}}, {{wc-order-notes}}
 *
 *     - Examples (not actual placeholders but usage):
 *       https://yoursiteurl.com/checkout/order-received/{{wc-order}}/?key={{post-_order_key}}
 *       https://yoursiteurl.com/checkout/order-pay/{{wc-order}}/?pay_for_order=true&key={{post-_order_key}}
 *
 *   - WooCommerce Billing/Shipping (hyphen versions):
 *     {{wc-billing-first-name}}, {{wc-billing-last-name}}, {{wc-billing-company}},
 *     {{wc-billing-address-line-1}}, {{wc-billing-address-line-2}}, {{wc-billing-city}}, {{wc-billing-postcode}},
 *     {{wc-billing-state}}, {{wc-billing-country}}, {{wc-billing-email}}, {{wc-billing-phone}},
 *     {{wc-shipping-first-name}}, {{wc-shipping-last-name}}, {{wc-shipping-company}},
 *     {{wc-shipping-address-line-1}}, {{wc-shipping-address-line-2}}, {{wc-shipping-city}}, {{wc-shipping-postcode}},
 *     {{wc-shipping-state}}, {{wc-shipping-country}}
 *
 *   - Custom Order Meta:
 *     e.g. {{my_custom_meta}} or {{_my_custom_meta}} -> $order->get_meta() / get_post_meta()
 *
 * If a placeholder is not recognized or cannot be retrieved, it will default to “N/A”.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class AWP_Message_Parser {

    public static function parse_message_placeholders($msg, $base_replacements, $order_id, $user_id) {
        $awp_wa = [
            'id','order_key','billing_first_name','billing_last_name','billing_company','billing_address_1',
            'billing_address_2','billing_city','billing_postcode','billing_country','billing_state',
            'billing_email','billing_phone','shipping_first_name','shipping_last_name','shipping_company',
            'shipping_address_1','shipping_address_2','shipping_city','shipping_postcode','shipping_country',
            'shipping_state','shipping_method','shipping_method_title','bacs_account','payment_method',
            'payment_method_title','order_subtotal','order_discount','cart_discount','order_tax',
            'order_shipping','order_shipping_tax','order_total','status','shop_name','currency',
            'cust_note','note','product','product_name','dpd','unique_transfer_code','order_date',
            'order_link','transaction_id','current_date_time','user_name','user_first_last_name',
            'wc_billing_first_name','wc_billing_last_name','wc_billing_phone','site_link','customer_note'
        ];

        $order = null;
        $user  = null;

        if ($order_id) {
            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        }
        if ($user_id) {
            $user = get_userdata($user_id);
        }

        // Replace any "base replacements" first
        foreach ($base_replacements as $k => $v) {
            $msg = str_replace($k, $v, $msg);
        }

        // Attempt to get the global currency symbol (if WooCommerce is active)
        if (class_exists('WooCommerce') && function_exists('get_woocommerce_currency_symbol')) {
            $currency_symb = get_woocommerce_currency_symbol();
        } else {
            $currency_symb = ''; // fallback if WooCommerce not active
        }

        // Regex for {{placeholder}}
        preg_match_all('/{{(.*?)}}/', $msg, $search);
        $locale    = get_locale();
        $is_arabic = (strpos($locale, 'ar') === 0);

        foreach ($search[1] as $variable) {
            $var_lower = strtolower($variable);

            // --------------------------------------------------------------
            // 1) Handle "General" placeholders that always apply
            // --------------------------------------------------------------
            if (in_array($var_lower, ['sitename','siteurl','wordpress-url','tagline','privacy-policy'], true)) {
                switch ($var_lower) {
                    case 'sitename':
                        $msg = str_replace('{{' . $variable . '}}', get_bloginfo('name'), $msg);
                        break;
                    case 'siteurl':
                        $msg = str_replace('{{' . $variable . '}}', home_url(), $msg);
                        break;
                    case 'wordpress-url':
                        $msg = str_replace('{{' . $variable . '}}', site_url(), $msg);
                        break;
                    case 'tagline':
                        $msg = str_replace('{{' . $variable . '}}', get_bloginfo('description'), $msg);
                        break;
                    case 'privacy-policy':
                        $pp_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
                        $msg = str_replace('{{' . $variable . '}}', $pp_url ?: '', $msg);
                        break;
                }
                continue;
            }

            // --------------------------------------------------------------
            // 2) If NO WooCommerce Order but we DO have a WP user
            // --------------------------------------------------------------
            if (!$order && $user) {
                switch ($var_lower) {
                    case 'user_name':
                        $msg = str_replace('{{' . $variable . '}}', $user->user_login, $msg);
                        break;
                    case 'user_first_last_name':
                        $fn = get_user_meta($user->ID, 'first_name', true);
                        $ln = get_user_meta($user->ID, 'last_name', true);
                        $nm = trim($fn . ' ' . $ln);
                        $msg = str_replace('{{' . $variable . '}}', $nm, $msg);
                        break;
                    case 'wc_billing_first_name':
                    case 'wc_billing_last_name':
                    case 'wc_billing_phone':
                        $val = get_user_meta($user->ID, str_replace('wc_', '', $var_lower), true);
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'shop_name':
                        $msg = str_replace('{{' . $variable . '}}', get_bloginfo('name'), $msg);
                        break;
                    case 'current_date_time':
                        $formatted = $is_arabic
                            ? date_i18n('Y-m-d H:i:s', current_time('timestamp'), true)
                            : gmdate('Y-m-d H:i:s', current_time('timestamp'));
                        $msg = str_replace('{{' . $variable . '}}', $formatted, $msg);
                        break;
                    case 'site_link':
                        $msg = str_replace('{{' . $variable . '}}', site_url(), $msg);
                        break;
                    case 'customer_note':
                        $val = isset($base_replacements['{customer_note}']) ? $base_replacements['{customer_note}'] : 'N/A';
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;

                    // New WP placeholders
                    case 'wp-first-name':
                        $val = get_user_meta($user->ID, 'first_name', true);
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-last-name':
                        $val = get_user_meta($user->ID, 'last_name', true);
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-username':
                        $val = $user->user_login;
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-nickname':
                        $val = get_user_meta($user->ID, 'nickname', true);
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-display-name':
                        $val = $user->display_name;
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-email':
                        $val = $user->user_email;
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-user-website':
                        $val = $user->user_url;
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-user-bio':
                        $val = $user->description;
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;

                    default:
                        // Attempt to load from user meta for unknown placeholders
                        $val = get_user_meta($user->ID, $var_lower, true);
                        if (!$val) {
                            $val = 'N/A';
                        }
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                }
            }

            // --------------------------------------------------------------
            // 3) If we DO have a WooCommerce order
            // --------------------------------------------------------------
            elseif ($order instanceof \WC_Order) {
                // Load user from order if not already loaded
                if (!$user && $order->get_user_id()) {
                    $user = get_userdata($order->get_user_id());
                }

                switch ($var_lower) {
                    // Existing placeholders (unchanged)
                    case 'id':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_id(), $msg);
                        break;
                    case 'order_date':
                        $df   = get_option('date_format');
                        $tf   = get_option('time_format');
                        $od   = $order->get_date_created();
                        $d_str = $od ? $od->date("$df $tf") : '';
                        if ($is_arabic && $d_str) {
                            $d_str = date_i18n("$df $tf", strtotime($d_str));
                        }
                        $msg = str_replace('{{' . $variable . '}}', $d_str, $msg);
                        break;
                    case 'order_link':
                        $url = wc_get_endpoint_url('order-received', $order->get_id(), wc_get_checkout_url());
                        $url = add_query_arg('key', $order->get_order_key(), $url);
                        $msg = str_replace('{{' . $variable . '}}', $url, $msg);
                        break;
                        
                        case 'product':
                        $items = '';
                        $i = 0;
                        foreach ($order->get_items() as $it) {
                            $i++;
                            $nl  = ($i > 1) ? "\n" : '';
                            $p   = $it->get_product();
                            $pn  = $p ? $p->get_name() : '';
                            $qty = $it->get_quantity();
                            $tot = $it->get_total();
                    
                            $items .= $nl . $i . '. ' . $pn . ' * (' . $qty . ') = ' . number_format($tot, wc_get_price_decimals()) . ' ' . $currency_symb;
                        }
                        $msg = str_replace('{{' . $variable . '}}', html_entity_decode($items), $msg);

                        break;
                    case 'order_discount':
                        $val = number_format($order->get_total_discount(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'product_name':
                        $names = [];
                        foreach ($order->get_items() as $it) {
                            $prod = $it->get_product();
                            if ($prod) {
                                $names[] = $prod->get_name();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $names), $msg);
                        break;
                    case 'cart_discount':
                        $val = number_format($order->get_discount_total(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'order_subtotal':
                        $val = number_format($order->get_subtotal(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'order_tax':
                        $val = number_format($order->get_total_tax(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'currency':
                        $msg = str_replace('{{' . $variable . '}}', html_entity_decode($currency_symb), $msg);
                        break;
                    case 'shop_name':
                        $msg = str_replace('{{' . $variable . '}}', get_bloginfo('name'), $msg);
                        break;
                    case 'cust_note':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_customer_note(), $msg);
                        break;
                    case 'shipping_method':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_method(), $msg);
                        break;
                    case 'order_shipping':
                        $val = number_format($order->get_shipping_total(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'order_shipping_tax':
                        $val = number_format($order->get_shipping_tax(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'transaction_id':
                        $tid = $order->get_transaction_id();
                        $msg = str_replace('{{' . $variable . '}}', $tid ?: 'N/A', $msg);
                        break;
                    case 'bacs_account':
                        $info = get_option('woocommerce_bacs_accounts');
                        $accs = '';
                        if ($info) {
                            foreach ($info as $acc) {
                                $accs .= '🏦 ' . esc_attr(wp_unslash($acc['bank_name'])) . "\n";
                                $accs .= '👤 ' . esc_attr(wp_unslash($acc['account_name'])) . "\n";
                                $accs .= '🔢 ' . esc_attr($acc['account_number']) . "\n";
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', $accs, $msg);
                        break;
                    case 'note':
                        $msg = str_replace('{{' . $variable . '}}', 'N/A', $msg);
                        break;
                    case 'unique_transfer_code':
                        $val = $order->get_meta('_unique_transfer_code', true);
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'site_link':
                        $msg = str_replace('{{' . $variable . '}}', site_url(), $msg);
                        break;

                    // New WP placeholders if there's a user tied to the order
                    case 'wp-first-name':
                        $val = $user ? get_user_meta($user->ID, 'first_name', true) : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-last-name':
                        $val = $user ? get_user_meta($user->ID, 'last_name', true) : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-username':
                        $val = $user ? $user->user_login : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-nickname':
                        $val = $user ? get_user_meta($user->ID, 'nickname', true) : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-display-name':
                        $val = $user ? $user->display_name : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-email':
                        $val = $user ? $user->user_email : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-user-website':
                        $val = $user ? $user->user_url : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wp-user-bio':
                        $val = $user ? $user->description : '';
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;

                    // Additional WooCommerce placeholders
                    case 'wc-order':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_order_number(), $msg);
                        break;
                    case 'wc-order-id':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_id(), $msg);
                        break;
                    case 'wc-order-status':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_status(), $msg);
                        break;
                    case 'wc-order-date':
                        $df   = get_option('date_format');
                        $tf   = get_option('time_format');
                        $od   = $order->get_date_created();
                        $d_str = $od ? $od->date("$df $tf") : '';
                        if ($is_arabic && $d_str) {
                            $d_str = date_i18n("$df $tf", strtotime($d_str));
                        }
                        $msg = str_replace('{{' . $variable . '}}', $d_str, $msg);
                        break;
                    case 'wc-product-names':
                        $names = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $names[] = $prod->get_name();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $names), $msg);
                        break;
                    case 'wc-product-names-br':
                        $names = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $names[] = $prod->get_name();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $names), $msg);
                        break;
                    case 'wc-product-names-variable':
                        $names = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod && $prod->is_type('variable')) {
                                $names[] = $prod->get_name();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $names), $msg);
                        break;
                    case 'wc-product-names-variable-br':
                        $names = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod && $prod->is_type('variable')) {
                                $names[] = $prod->get_name();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $names), $msg);
                        break;
                    case 'wc-product-name-count':
                        $items = $order->get_items();
                        $count = count($items);
                        if ($count > 1) {
                            $first_item = reset($items);
                            $prod       = $first_item->get_product();
                            $first_name = $prod ? $prod->get_name() : '';
                            $others     = $count - 1;
                            $val        = $first_name . ' and ' . $others . ' more';
                        } else {
                            // Only 1 item
                            $prod = count($items) ? current($items)->get_product() : null;
                            $val  = $prod ? $prod->get_name() : 'N/A';
                        }
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-product-link':
                        $links = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $links[] = get_permalink($prod->get_id());
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $links), $msg);
                        break;
                    case 'wc-product-link-br':
                        $links = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $links[] = get_permalink($prod->get_id());
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $links), $msg);
                        break;
                    case 'wc-product-name-link':
                        $pairs = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $url     = get_permalink($prod->get_id());
                                $pairs[] = $prod->get_name() . ' (' . $url . ')';
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $pairs), $msg);
                        break;
                    case 'wc-product-name-link-br':
                        $pairs = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $url     = get_permalink($prod->get_id());
                                $pairs[] = $prod->get_name() . ' (' . $url . ')';
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $pairs), $msg);
                        break;
                    case 'wc-total-products':
                        $count = count($order->get_items());
                        $msg = str_replace('{{' . $variable . '}}', $count, $msg);
                        break;
                    case 'wc-total-items':
                        $qty = 0;
                        foreach ($order->get_items() as $item) {
                            $qty += $item->get_quantity();
                        }
                        $msg = str_replace('{{' . $variable . '}}', $qty, $msg);
                        break;
                    case 'wc-order-items':
                        $lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $lines[] = $prod->get_name() . ' x ' . $item->get_quantity();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $lines), $msg);
                        break;
                    case 'wc-order-items-br':
                        $lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $lines[] = $prod->get_name() . ' x ' . $item->get_quantity();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $lines), $msg);
                        break;
                    case 'wc-order-items-variable':
                        $lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod && $prod->is_type('variable')) {
                                $lines[] = $prod->get_name() . ' x ' . $item->get_quantity();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $lines), $msg);
                        break;
                    case 'wc-order-items-variable-br':
                        $lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod && $prod->is_type('variable')) {
                                $lines[] = $prod->get_name() . ' x ' . $item->get_quantity();
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $lines), $msg);
                        break;
                    case 'wc-order-items-price':
                        $lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $lines[] = $prod->get_name() . ' x ' . $item->get_quantity() . ' - ' .
                                           $currency_symb . number_format($item->get_total(), wc_get_price_decimals());
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $lines), $msg);
                        break;
                    case 'wc-order-items-price-br':
                        $lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $lines[] = $prod->get_name() . ' x ' . $item->get_quantity() . ' - ' .
                                           $currency_symb . number_format($item->get_total(), wc_get_price_decimals());
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $lines), $msg);
                        break;
                    case 'wc-all-order-items-br':
                        $detail_lines = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $detail_lines[] = sprintf(
                                    '%s x %d = %s%s',
                                    $prod->get_name(),
                                    $item->get_quantity(),
                                    $currency_symb,
                                    number_format($item->get_total(), wc_get_price_decimals())
                                );
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $detail_lines), $msg);
                        break;
                    case 'wc-sku':
                        $skus = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $sku = $prod->get_sku();
                                if ($sku) {
                                    $skus[] = $sku;
                                }
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $skus), $msg);
                        break;
                    case 'wc-sku-br':
                        $skus = [];
                        foreach ($order->get_items() as $item) {
                            $prod = $item->get_product();
                            if ($prod) {
                                $sku = $prod->get_sku();
                                if ($sku) {
                                    $skus[] = $sku;
                                }
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode("\n", $skus), $msg);
                        break;
                    case 'wc-order-amount':
                        $val = number_format($order->get_total(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-discount':
                        $val = number_format($order->get_total_discount(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-tax':
                        $val = number_format($order->get_total_tax(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-order-amount-ex-tax':
                        $total_ex_tax = $order->get_total() - $order->get_total_tax();
                        $val = number_format($total_ex_tax, wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-payment-method':
                        $val = $order->get_payment_method();
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wc-transaction-id':
                        $tid = $order->get_transaction_id();
                        $msg = str_replace('{{' . $variable . '}}', $tid ?: 'N/A', $msg);
                        break;
                    case 'wc-shipping-method':
                        $val = $order->get_shipping_method();
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;
                    case 'wc-shipping-cost':
                        $val = number_format($order->get_shipping_total(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-refund-amount':
                        $val = number_format($order->get_total_refunded(), wc_get_price_decimals());
                        $msg = str_replace('{{' . $variable . '}}', $val, $msg);
                        break;
                    case 'wc-refund-reason':
                        $refunds = $order->get_refunds();
                        $reasons = [];
                        foreach ($refunds as $refund) {
                            $reason = $refund->get_reason();
                            if ($reason) {
                                $reasons[] = $reason;
                            }
                        }
                        $msg = str_replace('{{' . $variable . '}}', implode(', ', $reasons), $msg);
                        break;
                    case 'wc-order-notes':
                        $val = $order->get_customer_note();
                        $msg = str_replace('{{' . $variable . '}}', $val ?: 'N/A', $msg);
                        break;

                    // WooCommerce Billing/Shipping with hyphens
                    case 'wc-billing-first-name':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_first_name(), $msg);
                        break;
                    case 'wc-billing-last-name':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_last_name(), $msg);
                        break;
                    case 'wc-billing-company':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_company(), $msg);
                        break;
                    case 'wc-billing-address-line-1':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_address_1(), $msg);
                        break;
                    case 'wc-billing-address-line-2':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_address_2(), $msg);
                        break;
                    case 'wc-billing-city':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_city(), $msg);
                        break;
                    case 'wc-billing-postcode':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_postcode(), $msg);
                        break;
                    case 'wc-billing-state':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_state(), $msg);
                        break;
                    case 'wc-billing-country':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_country(), $msg);
                        break;
                    case 'wc-billing-email':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_email(), $msg);
                        break;
                    case 'wc-billing-phone':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_billing_phone(), $msg);
                        break;
                    case 'wc-shipping-first-name':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_first_name(), $msg);
                        break;
                    case 'wc-shipping-last-name':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_last_name(), $msg);
                        break;
                    case 'wc-shipping-company':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_company(), $msg);
                        break;
                    case 'wc-shipping-address-line-1':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_address_1(), $msg);
                        break;
                    case 'wc-shipping-address-line-2':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_address_2(), $msg);
                        break;
                    case 'wc-shipping-city':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_city(), $msg);
                        break;
                    case 'wc-shipping-postcode':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_postcode(), $msg);
                        break;
                    case 'wc-shipping-state':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_state(), $msg);
                        break;
                    case 'wc-shipping-country':
                        $msg = str_replace('{{' . $variable . '}}', $order->get_shipping_country(), $msg);
                        break;

                    default:
                        // If it's a known placeholder in $awp_wa not handled above, or custom meta:
                        $meta_val = $order->get_meta($var_lower);
                        if (!$meta_val) {
                            // Try '_field' or 'field' in post meta
                            $meta_val = get_post_meta($order->get_id(), '_' . $var_lower, true);
                            if (!$meta_val) {
                                $meta_val = get_post_meta($order->get_id(), $var_lower, true);
                            }
                        }
                        // Fallback to user meta if order meta is empty
                        if (!$meta_val && $user) {
                            $meta_val = get_user_meta($user->ID, $var_lower, true);
                        }
                        if (!$meta_val) {
                            $meta_val = 'N/A';
                        }
                        $msg = str_replace('{{' . $variable . '}}', $meta_val, $msg);
                        break;
                }
            }

            // --------------------------------------------------------------
            // 4) If no order AND no user, just set placeholder to "N/A"
            // --------------------------------------------------------------
            else {
                $msg = str_replace('{{' . $variable . '}}', 'N/A', $msg);
            }
        }

        return $msg;
    }
}
