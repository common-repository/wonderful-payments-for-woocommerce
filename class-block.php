<?php

// Bail If Accessed Directly
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_Gateway_Wonderful_Payments_Blocks
 *
 * Handles the integration of the Wonderful Payments Gateway with WooCommerce Blocks.
 */
final class WC_Gateway_Wonderful_Payments_Blocks extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'wonderful_payments_gateway';

    /**
     * Initialize the payment method type.
     */
    public function initialize() {

        $this->settings = get_option('woocommerce_wonderful_payments_gateway_settings', []);
        foreach ( WC_Payment_Gateways::instance()->payment_gateways() as $gateway ) {
            if ( $gateway instanceof WC_Gateway_Wonderful_Payments ) {
                $this->gateway = $gateway;
                break;
            }
        }
    }

    /**
     * Check if the payment method is active.
     *
     * @return bool Whether the payment method is active.
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Get the handles of the scripts used by the payment method.
     *
     * @return array The script handles.
     */
    public function get_payment_method_script_handles() {
        wp_register_script(
            'wonderful_payments_gateway-blocks-integration',
            plugin_dir_url( __FILE__ ) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wonderful_payments_gateway-blocks-integration');
        }
        return [ 'wonderful_payments_gateway-blocks-integration' ];
    }

    /**
     * Get the data for the payment method.
     *
     * @return array The payment method data.
     */
    public function get_payment_method_data() {
        $banks = $this->gateway->banks();

        return [
            'title' => $this->gateway->title,
            'icon' =>  plugin_dir_url(__FILE__) . 'assets/logo.png',
            'banks' => $banks->data ?? [],
        ];
    }
}
