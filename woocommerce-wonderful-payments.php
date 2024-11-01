<?php

/**
 * Plugin Name: Wonderful Payments for WooCommerce
 * Description: Account to account payments powered by Open Banking.
 * Author: Wonderful Payments Ltd
 * Author URI: https://www.wonderful.co.uk
 * Version: 0.7
 * Text Domain: wonderful-payments-gateway
 * Domain Path: /languages
 *
 * Copyright: (C) 2024, Wonderful Payments Limited <tek@wonderful.co.uk>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('ASPSP_PARAMETER', 'aspsp');

/**
 * Initialize the Wonderful Payments plugin.
 */
function wc_wonderful_plugin()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    require_once (plugin_dir_path(__FILE__)) . 'class-gateway.php';
}

/**
 * Add the Wonderful Payments gateway to the list of WooCommerce payment gateways.
 *
 * @param array $gateways The current list of gateways.
 * @return array The updated list of gateways.
 */
function add_wonderful_payments_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_Wonderful_Payments';
    return $gateways;
}

/**
 * Declare compatibility with the WooCommerce cart and checkout blocks.
 */
function declare_cart_checkout_blocks_compatibility()
{
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

/**
 * Register the custom block checkout class.
 */
function wonderful_register_order_approval_payment_method_type()
{
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    // Include the custom block checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_Gateway_Wonderful_Payments_Blocks);
        }
    );
}

function enqueue_custom_admin_scripts($hook)
{
    // Check if we are on the WooCommerce edit order page
//    if ($hook === 'woocommerce_page_wc-orders') {
        wp_enqueue_script(
            'custom-admin-scripts',
            plugin_dir_url(__FILE__) . 'custom-admin-scripts.js',
            array('jquery'),
            '1.0',
            true
        );
//    }
}

// Enqueue JavaScript for the admin area
add_action('admin_enqueue_scripts', 'enqueue_custom_admin_scripts');

function enqueue_wonderful_payments_script()
{
    $script_url = plugin_dir_url(__FILE__) . 'wonderful-payments.js';
    wp_enqueue_script(
        'wonderful-payments',
        $script_url,
        array('jquery'),
        '1.0',
        true
    );
}

add_action('wp_enqueue_scripts', 'enqueue_wonderful_payments_script');

add_action('woocommerce_admin_order_data_after_billing_address', 'add_custom_order_fields');

function add_custom_order_fields($order)
{
    echo '<p><strong>Wonderful Payment ID:</strong> ' . get_post_meta(
            $order->get_id(),
            'wonderful_payment_id',
            true
        ) . '</p>';
    echo '<p><strong>Wonderful Order ID:</strong> ' . get_post_meta(
            $order->get_id(),
            'wonderful_order_id',
            true
        ) . '</p>';
    echo '<p><strong>Wonderful Payments Ref:</strong> ' . get_post_meta(
            $order->get_id(),
            'wonderful_payment_ref',
            true
        ) . '</p>';
}

add_action('woocommerce_order_details_after_order_table', 'display_custom_order_fields');

function display_custom_order_fields($order)
{
    echo '<p><strong>Wonderful Payment ID:</strong> ' . get_post_meta(
            $order->get_id(),
            'wonderful_payment_id',
            true
        ) . '</p>';
    echo '<p><strong>Wonderful Order ID:</strong> ' . get_post_meta(
            $order->get_id(),
            'wonderful_order_id',
            true
        ) . '</p>';
    echo '<p><strong>Wonderful Payments Ref:</strong> ' . get_post_meta(
            $order->get_id(),
            'wonderful_payment_ref',
            true
        ) . '</p>';
}

function add_refund_via_wonderful_button()
{
    echo '<button type="button" class="button refund-via-wonderful">Refund via Wonderful</button>';
}

add_action('woocommerce_order_item_add_action_buttons', 'add_refund_via_wonderful_button');

add_action('woocommerce_order_item_add_action_buttons', 'add_wonderful_refund_panel');

function add_wonderful_refund_panel($order)
{
    ?>
    <div class="wc-order-data-row wc-order-refund-via-wonderful-items wc-order-data-row-toggle"
         style="display: none; padding: 20px;">
        <div class="wc-order-refund-panel-header" style="display: flex; align-items: center; margin-bottom: 20px;">
            <!-- Wonderful Logo -->
            <div class="wc-order-wonderful-logo">
                <img src="<?php
                echo plugin_dir_url(__FILE__); ?>assets/logo.png" alt="Wonderful Logo" style="height: 50px;">
            </div>
        </div>

        <div class="wc-order-refund-panel-body" style="display: flex; justify-content: space-between;">
            <div class="wc-order-refund-messages" style="flex: 2; padding-right: 20px;">
                <!-- Success Panel -->
                <div class="wc-order-successful-refund-panel"
                     style="display: none; margin-bottom: 20px; margin-right: 20px; text-align: left;">
                    <h4><?php
                        _e('Refund Request Submitted Successfully', 'woocommerce'); ?></h4>
                    <p><?php
                        _e(
                            'Your refund request has been submitted successfully. You can process the refund now in your One dashboard.',
                            'woocommerce'
                        ); ?></p>
                    <a href="https://my.wonderful.one" target="_blank" class="button"><?php
                        _e('Go to One Dashboard', 'woocommerce'); ?></a>
                </div>

                <!-- Failure Panel -->
                <div class="wc-order-failed-refund-panel"
                     style="display: none; margin-bottom: 20px; margin-right: 20px; text-align: left;">
                    <h4><?php
                        _e('Refund Request Failed', 'woocommerce'); ?></h4>
                    <p id="wonderful-refund-failure-reason"></p>
                    <p><?php
                        _e('Please try again.', 'woocommerce'); ?></p>
                </div>
            </div>

            <div class="wc-order-refund-info" style="flex: 1;">
                <div class="wc-order-refund-row"
                     style="display: flex; justify-content: space-between; margin-bottom: 10px; color: #a00;">
                    <div class="label"><?php
                        _e('Amount already refunded:', 'woocommerce'); ?></div>
                    <div class="total">-<span class="woocommerce-Price-amount amount"><bdi><span
                                        class="woocommerce-Price-currencySymbol">£</span><?php
                                echo number_format($order->get_total_refunded(), 2); ?></bdi></span></div>
                </div>
                <div class="wc-order-refund-row"
                     style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <div class="label"><?php
                        _e('Total available to refund:', 'woocommerce'); ?></div>
                    <div class="total"><span class="woocommerce-Price-amount amount"><bdi><span
                                        class="woocommerce-Price-currencySymbol">£</span><?php
                                echo number_format(
                                    $order->get_total() - $order->get_total_refunded(),
                                    2
                                ); ?></bdi></span></div>
                </div>
                <div class="wc-order-refund-row"
                     style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <div class="label"><label for="wonderful_refund_amount"><?php
                            _e('Refund amount:', 'woocommerce'); ?></label></div>
                    <div class="total"><input style="width: 100px; text-align: end;" type="text"
                                              id="wonderful_refund_amount" name="wonderful_refund_amount"
                                              class="wc_input_price" value="<?php
                        echo number_format($order->get_total() - $order->get_total_refunded(), 2); ?>"></div>
                </div>
                <div class="wc-order-refund-row"
                     style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <div class="label"><label for="wonderful_refund_reason"><?php
                            _e('Reason for refund:', 'woocommerce'); ?></label></div>
                    <div class="total"><input style="width: 100px" type="text" id="wonderful_refund_reason"
                                              name="wonderful_refund_reason"></div>
                </div>
            </div>
        </div>
        <div class="refund-actions" style="margin-top: 20px;">
            <button type="button" class="button button-primary do-wonderful-refund tips"><?php
                _e('Refund via Wonderful', 'woocommerce'); ?></button>
            <button type="button" class="button refund-via-wonderful"><?php
                _e('Cancel', 'woocommerce'); ?></button>
            <input type="hidden" id="wonderful_order_id" name="wonderful_order_id" value="<?php
            echo get_post_meta($order->get_id(), 'wonderful_order_id', true); ?>">
            <input type="hidden" id="woo_order_id" name="woo_order_id" value="<?php
            echo $order->get_id(); ?>">
            <div class="clear"></div>
        </div>
    </div>
    <?php
}

// Add the action hook for AJAX requests
add_action('wp_ajax_refund_via_wonderful', 'handle_refund_via_wonderful');

function handle_refund_via_wonderful()
{
    $order_id = sanitize_text_field($_POST['order_id']);
    $refund_amount = sanitize_text_field($_POST['refund_amount']);
    $reference = "WOO-" . sanitize_text_field($_POST['order_id']) . "-" . sanitize_text_field($_POST['woo_id']);
    $reason = sanitize_text_field($_POST['refund_reason']);

    // Retrieve the merchant key from WooCommerce options
    $gateway_options = get_option('woocommerce_wonderful_payments_gateway_settings');
    $merchant_key = $gateway_options['merchant_key'] ?? '';

    // Bail if we don;t have a merchant key
    if (empty($merchant_key)) {
        wp_send_json_error('Merchant key not found');
        return;
    }

    // Bail if the order was created with a previous plugin version that does nto support refunds
    if (empty($order_id)) {
        $error = '{"error": true,"message": "This order cannot be refunded via Wonderful.","invalid_fields": null}';
        wp_send_json_error($error);
        return;
    }

    $response = wp_remote_post( "https://api.wonderful.one/v2/orders/{$order_id}/refund", array(
        'headers' => array(
            'Authorization' => "Bearer {$merchant_key}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ),
        'body' => json_encode(array(
            'refund_amount' => (int)($refund_amount * 100),
            'reference' => (string)$reference,
            'reason' => (string)$reason
        )),
        'sslverify' => true
    ));

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    } else {
        wp_send_json_success(wp_remote_retrieve_body($response));
    }
}

function add_logo_link_to_gateway_title($gateways)
{
    if (isset($gateways['wonderful_payments_gateway'])) {
        $logo_url = 'https://wonderful.one/images/logo.png';
        $link_url = 'https://wonderful.co.uk';
        $gateways['wonderful_payments_gateway']->title;
    }
    return $gateways;
}

add_filter('woocommerce_available_payment_gateways', 'add_logo_link_to_gateway_title');

// Set up hooks
add_action('plugins_loaded', 'wc_wonderful_plugin', 11);
add_filter('woocommerce_payment_gateways', 'add_wonderful_payments_gateway');
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');
add_action('woocommerce_blocks_loaded', 'wonderful_register_order_approval_payment_method_type');
