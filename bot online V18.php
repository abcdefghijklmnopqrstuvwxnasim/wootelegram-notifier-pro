<?php
/**
 * Plugin Name: WooTelegram Notifier Pro (Global Edition)
 * Description: Domain Locked Licensing (1-Year & Lifetime), Order notifications, VPN Block, 10-Digit Phone Validation, Popup UI Builder & WhatsApp Support.
 * Version: 18.0
 * Author: A.S.M Nasim
 * Author URI: https://www.nasimwebpro.com
 */

<?php
/**
 * Plugin Name: WooTelegram Notifier Pro (Global Edition)
 * Description: Domain Locked Licensing, Order notifications, VPN Block, Popup UI Builder & Auto Updates.
 * Version: 18.0
 * Author: A.S.M Nasim
 * Author URI: https://www.nasimwebpro.com
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ==========================================
 * গিটহাব অটো-আপডেট চেকার ইন্টিগ্রেশন
 * ==========================================
 */
require 'plugin-update-checker/plugin-update-checker.php';
$wtn_update_checker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/abcdefghijklmnopqrstuvwxnasim/bot', // <-- এখানে আপনার গিটহাব লিংক দিন
    __FILE__,
    'wootelegram-notifier-pro'
);
// যদি আপনার গিটহাবের মেইন ব্রাঞ্চ 'main' হয়, তবে নিচের লাইনটি এভাবেই রাখুন। 'master' হলে চেঞ্জ করে দিন।
$wtn_update_checker->setBranch('main'); 

// ... (এর নিচে আপনার আগের সব কোড যেমন ছিল, ঠিক তেমনই থাকবে) ...


if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. Domain Locked Key Generator & Static Trial Key
 */
function wtn_get_expected_keys() {
    $domain = parse_url(get_site_url(), PHP_URL_HOST);
    $domain = preg_replace('/^www\./', '', strtolower(trim($domain)));
    $salt = 'NASIM_PRO_2026_SECRET_SALT'; 
    $hash = strtoupper(substr(hash('sha256', $domain . $salt), 0, 12));
    $base_hash = substr($hash, 0, 4) . '-' . substr($hash, 4, 4) . '-' . substr($hash, 8, 4);

    $t_arr = [54, 54, 106, 118, 111, 108, 105, 103, 120, 118, 113, 102, 60, 117, 120, 108, 122, 120, 122, 114, 59, 58, 104, 117, 124, 120, 38, 124, 103, 114, 108, 118, 103];
    $trial_key = ''; foreach($t_arr as $v) { $trial_key .= chr($v - 3); }

    return [
        '1yr' => 'WTN-1YR-' . $base_hash,
        'lft' => 'WTN-LFT-' . $base_hash,
        'trial' => $trial_key
    ];
}

/**
 * 2. License Checker & Telegram Sender
 */
function wtn_is_license_valid() {
    $active_key = get_option('wtn_activation_key');
    $keys = wtn_get_expected_keys();
    
    if (!in_array($active_key, $keys)) return false;
    
    $type = get_option('wtn_license_type');
    if ($type === 'lft') return true; // Lifetime never expires
    
    $act_date = get_option('wtn_activation_date');
    $allowed_days = ($type === 'trial') ? 7 : 365; // Changed Trial to 7 Days
    if (!$act_date || time() > strtotime("+$allowed_days days", $act_date)) return false;
    
    return true;
}

function wtn_send_to_telegram($msg) {
    if (!wtn_is_license_valid()) return;
    $token = get_option('wtn_api_token');
    $cid = get_option('wtn_chat_id');
    if (!$token || !$cid) return;

    wp_remote_post("https://api.telegram.org/bot$token/sendMessage", [
        'body' => ['chat_id' => $cid, 'text' => $msg, 'parse_mode' => 'HTML']
    ]);
}

/**
 * 3. Real IP Extractor
 */
function wtn_get_real_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) { $ip = $_SERVER['HTTP_CF_CONNECTING_IP']; } 
    elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) { $ip = $_SERVER['HTTP_X_REAL_IP']; } 
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']); $ip = trim($ips[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $_SERVER['REMOTE_ADDR'];
}

/**
 * 4. Admin Menu & Settings Page
 */
add_action('admin_menu', 'wtn_telegram_menu');
function wtn_telegram_menu() {
    add_menu_page('Telegram Bot', 'Telegram Bot', 'manage_options', 'wtn-settings', 'wtn_settings_page', 'dashicons-megaphone');
}

function wtn_settings_page() {
    $api_token = get_option('wtn_api_token');
    $chat_id = get_option('wtn_chat_id');
    $activation_key = get_option('wtn_activation_key');
    $activation_date = get_option('wtn_activation_date');
    $license_type = get_option('wtn_license_type', 'none');
    
    $support_phone = get_option('wtn_support_phone', '+18001234567');
    $wa_phone = get_option('wtn_wa_phone', '18001234567');
    $max_cart_val = get_option('wtn_max_cart_value', '1000');
    
    $popup_title = get_option('wtn_popup_title', 'Security Alert!');
    $popup_btn_text = get_option('wtn_popup_btn_text', 'Close');
    $popup_btn_color = get_option('wtn_popup_btn_color', '#dc2626');

    $is_active = wtn_is_license_valid();
    
    $status_html = '';
    if ($is_active && !empty($activation_date)) {
        if ($license_type === 'lft') {
            $status_html = '<span style="color:green; font-weight:bold;">Active [LIFETIME VERSION] (Never Expires)</span>';
        } else {
            $allowed_days = ($license_type === 'trial') ? 7 : 365;
            $expiry_date = strtotime("+$allowed_days days", $activation_date);
            $days_left = ceil(($expiry_date - time()) / 86400);
            $badge = ($license_type === 'trial') ? '7-DAY TRIAL' : '1-YEAR LICENSE';
            $status_html = '<span style="color:green; font-weight:bold;">Active ['. $badge .'] (' . $days_left . ' days remaining)</span>';
        }
    } else {
        $status_html = '<span style="color:red; font-weight:bold;">Inactive / Expired / Invalid Domain</span>';
    }

    ?>
    <div class="wrap">
        <h1>WooTelegram Notifier Pro Settings</h1>
        <div class="notice notice-info"><p>License Status: <?php echo $status_html; ?></p></div>
        
        <form id="wtn-secure-ajax-form">
            <table class="form-table">
                <tr>
                    <th>Activation Key</th>
                    <td><input type="password" name="wtn_activation_key" value="" placeholder="<?php echo $is_active ? '••••••••••••••••' : 'Enter License Key'; ?>" class="regular-text" autocomplete="new-password"></td>
                </tr>
                <tr>
                    <th>Bot API Token</th>
                    <td><input type="password" name="wtn_api_token" value="" placeholder="<?php echo $api_token ? '••••••••••••••••' : 'Enter Bot Token'; ?>" class="regular-text" autocomplete="new-password"></td>
                </tr>
                <tr>
                    <th>Chat ID</th>
                    <td><input type="password" name="wtn_chat_id" value="" placeholder="<?php echo $chat_id ? '••••••••••••••••' : 'Enter Chat ID'; ?>" class="regular-text" autocomplete="new-password"></td>
                </tr>

                <tr><td colspan="2"><hr><h3>Popup UI Customization</h3></td></tr>
                <tr>
                    <th>Popup Title</th>
                    <td><input type="text" name="wtn_popup_title" value="<?php echo esc_attr($popup_title); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Button Text</th>
                    <td><input type="text" name="wtn_popup_btn_text" value="<?php echo esc_attr($popup_btn_text); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Button Background Color</th>
                    <td><input type="color" name="wtn_popup_btn_color" value="<?php echo esc_attr($popup_btn_color); ?>"></td>
                </tr>

                <tr><td colspan="2"><hr><h3>Security & Validation Controls</h3></td></tr>
                
                <tr>
                    <th>Support Phone Number</th>
                    <td><input type="text" name="wtn_support_phone" value="<?php echo esc_attr($support_phone); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>WhatsApp Number</th>
                    <td>
                        <input type="text" name="wtn_wa_phone" value="<?php echo esc_attr($wa_phone); ?>" class="regular-text">
                        <p class="description">Include Country Code (e.g., 18001234567)</p>
                        <br><label><input type="checkbox" name="wtn_enable_wa_btn" <?php checked(get_option('wtn_enable_wa_btn'), 'on'); ?>> Show WhatsApp button in Error Popup</label>
                    </td>
                </tr>
                <tr>
                    <th>Max Cart Value Limit</th>
                    <td>
                        $<input type="number" name="wtn_max_cart_value" value="<?php echo esc_attr($max_cart_val); ?>" class="regular-text" style="width: 100px;">
                        <br><label><input type="checkbox" name="wtn_enable_max_cart" <?php checked(get_option('wtn_enable_max_cart'), 'on'); ?>> Enable Max Cart Value Block</label>
                    </td>
                </tr>
                <tr>
                    <th>Strict Security</th>
                    <td>
                        <label><input type="checkbox" name="wtn_enable_phone_val" <?php checked(get_option('wtn_enable_phone_val'), 'on'); ?>> Enforce 10-Digit US Phone Number Validation</label><br><br>
                        <label><input type="checkbox" name="wtn_enable_ip_block" <?php checked(get_option('wtn_enable_ip_block'), 'on'); ?>> Enable 24-Hour IP Block for returning customers</label><br><br>
                        <label><input type="checkbox" name="wtn_enable_vpn_block" <?php checked(get_option('wtn_enable_vpn_block'), 'on'); ?>> Block VPN / Proxy / Cloudflare WARP connections</label>
                    </td>
                </tr>

                <tr><td colspan="2"><hr><h3>Telegram Notifications & Alerts</h3></td></tr>
                
                <tr>
                    <th>Advanced Alerts</th>
                    <td>
                        <label><input type="checkbox" name="wtn_enable_status_alert" <?php checked(get_option('wtn_enable_status_alert'), 'on'); ?>> Send alerts for Completed/Cancelled orders</label><br><br>
                        <label><input type="checkbox" name="wtn_enable_stock_alert" <?php checked(get_option('wtn_enable_stock_alert'), 'on'); ?>> Send alerts for Low Stock (<5) or Out of Stock</label>
                    </td>
                </tr>
                <tr>
                    <th>Order Message Data</th>
                    <td>
                        <?php 
                        $fields = ['wtn_show_id'=>'Order ID','wtn_show_name'=>'Customer Name','wtn_show_phone'=>'Phone Number','wtn_show_email'=>'Email Address','wtn_show_product'=>'Product Details','wtn_show_total'=>'Total Amount','wtn_show_address'=>'Shipping Address','wtn_show_payment'=>'Payment Method'];
                        foreach($fields as $key => $label): ?>
                            <label><input type="checkbox" name="<?php echo $key; ?>" <?php checked(get_option($key), 'on'); ?>> <?php echo $label; ?></label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <p class="submit"><button type="submit" id="wtn-btn-save" class="button button-primary">Save Settings</button><span id="wtn-msg-box" style="margin-left:10px; font-weight:bold;"></span></p>
        </form>
        <hr><p>Developed by <b>A.S.M Nasim</b> | <a href="https://www.nasimwebpro.com" target="_blank">Nasim Web Pro</a></p>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#wtn-secure-ajax-form').on('submit', function(e) {
            e.preventDefault();
            $('#wtn-btn-save').prop('disabled', true).text('Processing...');
            $.post(ajaxurl, { action: 'wtn_final_secure_action', data: $(this).serialize() }, function(res) {
                if(res.success) {
                    $('#wtn-msg-box').css('color', 'green').text('✔ Updated Successfully!');
                    setTimeout(function(){ location.reload(); }, 800);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * 5. Smart Ajax Save Logic with Multi-Tier Validation
 */
add_action('wp_ajax_wtn_final_secure_action', 'wtn_final_secure_save_handler');
function wtn_final_secure_save_handler() {
    parse_str($_POST['data'], $data);
    $keys = wtn_get_expected_keys();

    $fields = ['wtn_activation_key', 'wtn_api_token', 'wtn_chat_id', 'wtn_support_phone', 'wtn_wa_phone', 'wtn_max_cart_value', 'wtn_popup_title', 'wtn_popup_btn_text', 'wtn_popup_btn_color'];
    foreach ($fields as $f) {
        if (!empty($data[$f])) {
            $val = trim(sanitize_text_field($data[$f]));
            update_option($f, $val);
            
            if ($f === 'wtn_activation_key') {
                if ($val === $keys['1yr']) {
                    update_option('wtn_license_type', '1yr');
                    if (!get_option('wtn_1yr_start_date')) {
                        $now = time(); update_option('wtn_1yr_start_date', $now); update_option('wtn_activation_date', $now);
                    } else { update_option('wtn_activation_date', get_option('wtn_1yr_start_date')); }
                } elseif ($val === $keys['lft']) {
                    update_option('wtn_license_type', 'lft');
                    update_option('wtn_activation_date', time()); 
                } elseif ($val === $keys['trial']) {
                    update_option('wtn_license_type', 'trial');
                    if (!get_option('wtn_trial_start_date')) {
                        $now = time(); update_option('wtn_trial_start_date', $now); update_option('wtn_activation_date', $now);
                    } else { update_option('wtn_activation_date', get_option('wtn_trial_start_date')); }
                } else {
                    update_option('wtn_license_type', 'none');
                }
            }
        }
    }

    $cbs = ['wtn_enable_phone_val', 'wtn_enable_ip_block', 'wtn_enable_vpn_block', 'wtn_enable_wa_btn', 'wtn_enable_max_cart', 'wtn_enable_status_alert', 'wtn_enable_stock_alert', 'wtn_show_id', 'wtn_show_name', 'wtn_show_phone', 'wtn_show_email', 'wtn_show_product', 'wtn_show_total', 'wtn_show_address', 'wtn_show_payment'];
    foreach ($cbs as $cb) { update_option($cb, isset($data[$cb]) ? 'on' : 'off'); }
    
    wp_send_json_success();
}

/**
 * 6. Security Validations (USA 10-Digit & Messages in English)
 */
add_action('woocommerce_after_checkout_validation', 'wtn_advanced_security_validation', 10, 2);
function wtn_advanced_security_validation($data, $errors) {
    if (!wtn_is_license_valid()) return;

    $ip = wtn_get_real_ip();
    $support_num = get_option('wtn_support_phone', '+18001234567');
    $wa_num = preg_replace('/[^0-9]/', '', get_option('wtn_wa_phone', '18001234567'));
    
    $web_style = 'style="font-weight:bold; font-size:16px; color:#d63638;"';
    $action_html = '<br><br>For assistance, please call:<br><a href="tel:'.$support_num.'" style="color:#d63638; text-decoration:underline;">'.$support_num.'</a>';
    
    if (get_option('wtn_enable_wa_btn') === 'on') {
        $cart_details = "";
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_obj = $cart_item['data'];
            $cart_details .= "\n- " . $product_obj->get_name() . " (" . get_permalink($product_obj->get_id()) . ")";
        }
        $wa_msg = urlencode("Hello, I am facing an issue during checkout. My cart items:" . $cart_details);
        $action_html .= '<br><a href="https://wa.me/'.$wa_num.'?text='.$wa_msg.'" target="_blank" style="display:inline-block; margin-top:10px; background:#25D366; color:#fff; padding:8px 15px; border-radius:5px; text-decoration:none; font-weight:bold;">💬 WhatsApp Support</a>';
    }

    if (get_option('wtn_enable_max_cart') === 'on') {
        $cart_total = floatval( preg_replace( '#[^\d.]#', '', WC()->cart->get_total() ) );
        $max_limit = floatval(get_option('wtn_max_cart_value', '1000'));
        if ($cart_total > $max_limit) {
            $errors->add('validation', '<div class="wtn-dual-error" '.$web_style.'>We cannot process orders exceeding $'.$max_limit.' online. Please modify your cart.'.$action_html.'</div>');
            return;
        }
    }

    if (get_option('wtn_enable_phone_val') === 'on') {
        $billing_phone = preg_replace('/[^0-9]/', '', $data['billing_phone']);
        if (substr($billing_phone, 0, 1) === '1' && strlen($billing_phone) === 11) { $billing_phone = substr($billing_phone, 1); }
        if (strlen($billing_phone) !== 10) {
            $errors->add('validation', '<div class="wtn-dual-error" '.$web_style.'>Invalid phone format. Please enter a valid 10-digit US phone number.'.$action_html.'</div>');
            return;
        }
    }

    if (get_option('wtn_enable_ip_block') === 'on' && get_transient('wtn_ip_lock_' . $ip)) {
        $errors->add('validation', '<div class="wtn-dual-error" '.$web_style.'>You have recently placed an order. Please contact support to place another order within 24 hours.'.$action_html.'</div>');
        return;
    }

    if (get_option('wtn_enable_vpn_block') === 'on' && $ip !== '127.0.0.1' && $ip !== '::1') {
        $is_vpn = false;
        $req1 = wp_remote_get("https://ipwho.is/{$ip}", array('timeout' => 5));
        if (!is_wp_error($req1)) {
            $body = json_decode(wp_remote_retrieve_body($req1), true);
            if (isset($body['security']) && (!empty($body['security']['vpn']) || !empty($body['security']['proxy']) || !empty($body['security']['hosting']))) { $is_vpn = true; }
        }
        if (!$is_vpn) {
            $req2 = wp_remote_get("http://ip-api.com/json/{$ip}?fields=proxy,hosting,isp,as", array('timeout' => 5));
            if (!is_wp_error($req2)) {
                $body = json_decode(wp_remote_retrieve_body($req2), true);
                if (!empty($body['proxy']) || !empty($body['hosting'])) { $is_vpn = true; }
                if (isset($body['as']) && stripos($body['as'], 'Cloudflare') !== false) { $is_vpn = true; }
            }
        }
        if ($is_vpn) {
            $errors->add('validation', '<div class="wtn-dual-error" '.$web_style.'>For security reasons, orders placed via VPN or Proxy connections are blocked.'.$action_html.'</div>');
            return;
        }
    }
}

/**
 * 7. Dual Alert Script (Dynamic Popup UI Builder)
 */
add_action('wp_footer', 'wtn_checkout_dual_alert_script');
function wtn_checkout_dual_alert_script() {
    if ( ! is_checkout() ) return;
    
    $popup_title = esc_js(get_option('wtn_popup_title', 'Security Alert!'));
    $popup_btn_text = esc_js(get_option('wtn_popup_btn_text', 'Close'));
    $popup_btn_color = esc_js(get_option('wtn_popup_btn_color', '#dc2626'));
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $(document.body).on('checkout_error', function() {
            var errorDiv = $('.wtn-dual-error');
            if (errorDiv.length > 0) {
                var errorMessage = errorDiv.html();
                $('.wtn-custom-popup').remove();
                
                var msgHtml = '<div class="wtn-custom-popup" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:999999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(3px);">' +
                    '<div style="background:#fff;padding:35px 25px;border-radius:15px;width:90%;max-width:400px;text-align:center;box-shadow:0 15px 35px rgba(0,0,0,0.3);animation:wtnZoom 0.3s ease;">' +
                        '<div style="width:70px;height:70px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">' +
                            '<span style="font-size:35px;">⚠️</span>' +
                        '</div>' +
                        '<h2 style="margin:0 0 15px;font-size:24px;color:#1f2937;font-family:sans-serif;"><?php echo $popup_title; ?></h2>' +
                        '<p style="margin:0 0 25px;font-size:16px;color:#4b5563;line-height:1.6;font-family:sans-serif;">' + errorMessage + '</p>' +
                        '<button type="button" onclick="jQuery(\'.wtn-custom-popup\').fadeOut(200, function(){jQuery(this).remove();});" style="background:<?php echo $popup_btn_color; ?>;color:#fff;border:none;padding:12px 30px;font-size:16px;border-radius:8px;cursor:pointer;font-weight:bold;width:100%;font-family:sans-serif;transition:background 0.3s;"><?php echo $popup_btn_text; ?></button>' +
                    '</div>' +
                '</div>' +
                '<style>@keyframes wtnZoom{0%{transform:scale(0.8);opacity:0;}100%{transform:scale(1);opacity:1;}} .wtn-custom-popup button:hover{opacity:0.9;} .wtn-custom-popup a:hover{opacity:0.8;}</style>';
                
                $('body').append(msgHtml);
            }
        });
    });
    </script>
    <?php
}

/**
 * 8. Apply IP Lock
 */
add_action('woocommerce_thankyou', 'wtn_apply_ip_lock');
function wtn_apply_ip_lock($order_id) {
    if (wtn_is_license_valid() && get_option('wtn_enable_ip_block') === 'on') {
        set_transient('wtn_ip_lock_' . wtn_get_real_ip(), true, 24 * HOUR_IN_SECONDS);
    }
}

/**
 * 9. New Order Telegram Notification
 */
add_action('woocommerce_checkout_order_processed', 'wtn_trigger_telegram', 10, 1);
function wtn_trigger_telegram($order_id) {
    if (!wtn_is_license_valid()) return;
    $order = wc_get_order($order_id);
    $site = strtoupper(str_replace(['http://', 'https://', 'www.'], '', rtrim(get_site_url(), '/')));

    $msg = "🌐 <b>Website: $site</b>\n🔔 <b>New Order Received!</b>\n---------------------------\n";
    if (get_option('wtn_show_id') == 'on') $msg .= "🆔 <b>Order ID:</b> #$order_id\n";
    if (get_option('wtn_show_name') == 'on') $msg .= "👤 <b>Name:</b> " . $order->get_billing_first_name() . " " . $order->get_billing_last_name() . "\n";
    if (get_option('wtn_show_phone') == 'on') $msg .= "📞 <b>Phone:</b> " . $order->get_billing_phone() . "\n";
    if (get_option('wtn_show_email') == 'on') $msg .= "📧 <b>Email:</b> " . $order->get_billing_email() . "\n";
    
    if (get_option('wtn_show_product') == 'on') {
        $msg .= "🛒 <b>Products:</b>\n";
        foreach ($order->get_items() as $item) { $msg .= "- " . $item->get_name() . " (x" . $item->get_quantity() . ")\n"; }
    }
    if (get_option('wtn_show_total') == 'on') $msg .= "💰 <b>Total:</b> " . $order->get_total() . " " . $order->get_currency() . "\n";
    if (get_option('wtn_show_address') == 'on') $msg .= "📍 <b>Address:</b> " . ($order->get_shipping_address_1() ?: $order->get_billing_address_1()) . "\n";
    if (get_option('wtn_show_payment') == 'on') $msg .= "💳 <b>Payment:</b> " . $order->get_payment_method_title() . "\n";

    wtn_send_to_telegram($msg);
}

/**
 * 10. Order Status Change Alert
 */
add_action('woocommerce_order_status_changed', 'wtn_status_change_telegram_alert', 10, 4);
function wtn_status_change_telegram_alert($order_id, $old_status, $new_status, $order) {
    if (get_option('wtn_enable_status_alert') !== 'on') return;
    
    if ($new_status === 'completed' || $new_status === 'cancelled') {
        $status_emoji = ($new_status === 'completed') ? '✅' : '❌';
        $status_text = ($new_status === 'completed') ? 'Order Completed' : 'Order Cancelled';
        
        $msg = "{$status_emoji} <b>Order Status Update</b>\n---------------------------\n";
        $msg .= "🆔 <b>Order ID:</b> #{$order_id}\n";
        $msg .= "👤 <b>Customer:</b> " . $order->get_billing_first_name() . "\n";
        $msg .= "🔄 <b>Status:</b> {$status_text}\n";
        
        wtn_send_to_telegram($msg);
    }
}

/**
 * 11. Live Stock Alert
 */
add_action('woocommerce_updated_product_stock', 'wtn_stock_level_alert', 10, 1);
function wtn_stock_level_alert($product_id) {
    if (get_option('wtn_enable_stock_alert') !== 'on') return;
    
    $product = wc_get_product($product_id);
    if (!$product) return;
    
    if ($product->managing_stock()) {
        $stock_qty = $product->get_stock_quantity();
        
        if ($stock_qty <= 0) {
            $msg = "🚨 <b>Out of Stock Alert!</b>\n---------------------------\n";
            $msg .= "📦 <b>Product:</b> " . $product->get_name() . "\n";
            $msg .= "⚠️ <b>Status:</b> Out of Stock (0 remaining)\n";
            $msg .= "👉 <i>Please restock immediately!</i>";
            wtn_send_to_telegram($msg);
        } elseif ($stock_qty <= 5) {
            $msg = "⚠️ <b>Low Stock Alert!</b>\n---------------------------\n";
            $msg .= "📦 <b>Product:</b> " . $product->get_name() . "\n";
            $msg .= "📉 <b>Current Stock:</b> Only {$stock_qty} left\n";
            wtn_send_to_telegram($msg);
        }
    }
}