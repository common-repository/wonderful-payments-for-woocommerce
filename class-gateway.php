<?php

// Bail If Accessed Directly
use GPBMetadata\Google\Api\Log;

if (!defined('ABSPATH')) {
    exit;
}

class InvalidDomainException extends Exception
{
}

/**
 * Class WC_Gateway_Wonderful_Payments
 *
 * Payment Gateway Class for Wonderful Payments
 */
class WC_Gateway_Wonderful_Payments extends WC_Payment_Gateway
{
    private const PLUGIN_VERSION = '0.7';
    private const ENDPOINT = 'https://api.wonderful.one';
    private const WONDERFUL_ONE = 'https://wonderful.one';
    private const CURRENCY = 'GBP';
    private const ID = 'wonderful_payments_gateway';
    private const SSL_VERIFY = true;
    private const ONLINE = 'online';
    private const ISSUES = 'issues';
    private const OFFLINE = 'offline';
    private const COMPLETED = 'completed';
    private string $merchant_key;
    private $banks;

    /**
     * WC_Gateway_Wonderful_Payments constructor.
     *
     * Initializes the payment gateway.
     */
    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = __('Wonderful Payments', 'wc-gateway-wonderful');
        $this->method_description = __(
            'Account to account bank payments, powered by Open Banking',
            'wc-gateway-wonderful'
        );
        $this->icon = '';
        $this->has_fields = true;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_key = $this->get_option('merchant_key');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id, array($this, 'webhook'));
        add_action('woocommerce_api_' . $this->id . '_pending', array($this, 'payment_pending'));
        add_action('woocommerce_api_' . $this->id . '_refund', array($this, 'refund'));
    }

    /**
     * Initialize form fields.
     */
    public function init_form_fields()
    {
        // Get permalink structure
        $permalink_structure = get_option('permalink_structure');

        // Check if plain permalinks are set
        $is_plain_permalink = empty($permalink_structure);

        // Warning message
        $warning_message = '';
        if ($is_plain_permalink) {
            $warning_message = 'Warning: Your permalink structure is set to "Plain". This may cause issues with this plugin.';
        }

        $this->form_fields = array(

            'warning' => array(
                'title' => $warning_message ? __('Warning', 'wc-gateway-wonderful') : '',
                'type' => 'title',
                'description' => $warning_message,
            ),

            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-gateway-wonderful'),
                'type' => 'checkbox',
                'label' => __('Enable Wonderful Payments', 'wc-gateway-wonderful'),
                'default' => 'yes'
            ),

            'title' => array(
                'title' => __('Title', 'wc-gateway-wonderful'),
                'type' => 'text',
                'description' => __(
                    'This controls the title for the payment method the customer sees during checkout.',
                    'wc-gateway-wonderful'
                ),
                'default' => __('Instant bank payment', 'wc-gateway-wonderful'),
                'desc_tip' => true,
            ),

            'description' => array(
                'title' => __('Description', 'wc-gateway-wonderful'),
                'type' => 'textarea',
                'description' => __(
                    'Payment method description that the customer will see on your checkout.',
                    'wc-gateway-wonderful'
                ),
                'default' => __(
                    'No data entry. Pay effortlessly and securely through your mobile banking app. Powered by Wonderful Payments.',
                    'wc-gateway-wonderful'
                ),
                'desc_tip' => true,
            ),

            'merchant_key' => array(
                'title' => __('Wonderful Payments Merchant Token', 'wc-gateway-wonderful'),
                'type' => 'text',
                'description' => __(
                    'Your merchant token will be provided to you when you register with Wonderful Payments',
                    'wc-gateway-wonderful'
                ),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Process the payment.
     *
     * @param int $order_id The ID of the order.
     * @return array|void The result of the payment processing.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Get permalink structure
        $permalink_structure = get_option('permalink_structure');

        // Check if plain permalinks are set
        $is_plain_permalink = empty($permalink_structure);

        // Create a merchant payment reference
        $merchant_payment_reference = 'WOO-' . strtoupper(
                substr(md5(uniqid(rand(), true)), 0, 6)
            ) . '-' . $order->get_order_number();

        // Get the checkout data
        $checkout_data = WC()->session->get('wc_checkout_params');

        // Get the 'aspsp' value from the checkout data
        $_POST['aspsp_name'] = $checkout_data['aspsp'] ?? $_POST['aspsp_name'];

        if (empty($_POST['aspsp_name'])) {
            wc_add_notice(__('Select your bank from the list provided.', 'wc-gateway-wonderful'), 'error');

            return;
        }

        $order_lines = array();

        foreach ($order->get_items() as $item_id => $item) {
            // Get the product name
            $product_name = $item->get_name();

            // Get the product ID
            $product_id = $item->get_product_id();

            // Get the item quantity
            $quantity = $item->get_quantity();

            // Get the underlying product so we can get the price
            $product = $item->get_product();

            // Check if product exists
            $unit_price = 0;
            if (is_object($product) && $product->is_type('simple')) { // Check if it's a simple product
                $unit_price = $product->get_price();
            } elseif (is_object($product) && $product->is_type('variation')) { // Check if it's a variation product
                $variation_id = $item->get_variation_id();
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $unit_price = $variation->get_price();
                }
            }

            // Add item details to the order lines array
            $order_lines[] = array(
                "product_name" => $product_name,
                "product_id" => $product_id,
                "quantity" => $quantity,
                "total" => $unit_price * 100, // in pence
            );
        }

        // Convert the order lines array to a JSON string
        $order_lines_json = json_encode($order_lines);

        // Initiate a new payment and redirect to WP to pay
        $payload = [
            'amount' => round($order->get_total() * 100), // in pence
            'clientBrowserAgent' => $order->get_customer_ip_address(),
            'clientIpAddress' => $order->get_customer_user_agent(),
            'consented_at' => date('c', time()),
            'currency' => self::CURRENCY,
            'customer_email_address' => $order->get_billing_email(),
            'is_plain_permalink' => $is_plain_permalink ?? 1,
            'merchant_payment_reference' => $merchant_payment_reference,
            'order_id' => $order_id,
            'order_lines' => $order_lines_json,
            'plugin_id' => $this->id,
            'selected_aspsp' => sanitize_text_field($_POST['aspsp_name']),
            'source' => sprintf('woocommerce_%s', self::PLUGIN_VERSION),
            'woo_url' => get_site_url(),
            'checkout' => wc_get_checkout_url(),
        ];

        // Hash the encryption key
        $key = hash('sha256', $this->merchant_key);

        // Create an iv
        $iv = substr(hash('sha256', $this->merchant_key), 0, 16);

        // Encrypt the payload
        $encrypted_payload = base64_encode(openssl_encrypt(json_encode($payload), "AES-256-CBC", $key, 0, $iv));

        // Get a reference from Wonderful Payments
        $response = wp_remote_get(self::ENDPOINT . '/v2/ref', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->merchant_key,
            ],
            'sslverify' => self::SSL_VERIFY,
        ]);

        if (is_a($response, WP_Error::class)) {
            wc_get_logger()->error(
                'Unable to connect to Wonderful Payments, please try again or select another payment method.'
            );
            wc_add_notice(
                __(
                    'Unable to connect to Wonderful Payments, please try again or select another payment method.',
                    'wc-gateway-wonderful'
                ),
                'error'
            );

            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (404 === $status_code) {
            wc_get_logger()->error(
                'Unable to connect to Wonderful Payments, please try again or select another payment method.'
            );
            wc_add_notice(
                __(
                    'Unable to connect to Wonderful Payments, please try again or select another payment method.',
                    'wc-gateway-wonderful'
                ),
                'error'
            );

            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        $url = self::WONDERFUL_ONE . '/woo-redirect?payload=' . $encrypted_payload . '&ref=' . $body->ref;

        // redirect to WP
        return array(
            'result' => 'success',
            'redirect' => $url,
        );
    }

    /**
     * Handle the webhook.
     */
    public function webhook()
    {
        // Get the raw POST data
        $rawData = file_get_contents("php://input");

        // This returns a string, so decode it into an object
        $decodedData = json_decode($rawData);

        // Get the Wonderful Payments ID from the URL
        $wonderfulPaymentId = (isset($_GET['wonderfulPaymentId'])) ? sanitize_text_field(
            $_GET['wonderfulPaymentId']
        ) : null;

        // If the Wonderful Payments ID is not in the URL, check the POST data
        if (null === $wonderfulPaymentId) {
            $wonderfulPaymentId = $decodedData->wonderful_payment_id ?? null;
        }

        // If the Wonderful Payments ID is still not found, bail
        if (null === $wonderfulPaymentId) {
            echo $wonderfulPaymentId . 'error';
            exit;
        }

        // Get the payment status from Wonderful Payments, work out which order it is for, update and redirect.
        $response = wp_remote_get(
            self::ENDPOINT . '/v2/woo/' . $wonderfulPaymentId,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->merchant_key,
                ],
                'sslverify' => self::SSL_VERIFY,
            ]
        );
        $body = json_decode(wp_remote_retrieve_body($response));

        // Extract the Order ID, lookup the WooCommerce order and then process according to the Wonderful Payments order state
        $order = wc_get_order(explode('-', $body->paymentReference)[2]);
        $order->add_order_note(
            sprintf('Payment Update. Wonderful Payments ID: %s, Status: %s', $body->wonderfulPaymentsId, $body->status)
        );

        // Check payment state and handle accordingly
        switch ($body->status) {
            case 'completed':
                // Mark order payment complete
                $order->add_order_note(
                    sprintf(
                        'Payment Success. Order reference: %s, Customer Bank: %s',
                        $body->paymentReference,
                        $body->selectedAspsp
                    )
                );
                $order->payment_complete($body->paymentReference);

                // Update the "Wonderful Payment ID" field value
                update_post_meta($order->get_id(), 'wonderful_payment_id', $body->wonderfulPaymentsId);
                update_post_meta($order->get_id(), 'wonderful_payment_ref', $body->paymentReference);
                if ($decodedData->wonderful_order_id) {
                    update_post_meta($order->get_id(), 'wonderful_order_id', $decodedData->wonderful_order_id);
                }

                if ($rawData) {
                    echo $rawData;
                    exit;
                }
                wp_safe_redirect($this->get_return_url($order));
                exit;

            case 'accepted':
            case 'pending':
                // Mark order "on hold" for manual review
                $order->update_status(
                    'on-hold',
                    'Payment has been processed but has not been confirmed. Please manually check payment status before order processing.'
                );
                if ($rawData) {
                    echo $rawData;
                    exit;
                }
                wp_safe_redirect($this->get_return_url($order));
                exit;

            case 'rejected':
                // Payment has failed, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment was rejected at the bank');
                wc_add_notice(
                    'Your payment was rejected by your bank, you have not be charged. Please try again.',
                    'error'
                );
                if ($rawData) {
                    echo $rawData;
                    exit;
                }
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

            case 'cancelled':
                // Payment was explicitly cancelled, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment was cancelled by the customer');
                wc_add_notice('Your payment was cancelled.', 'notice');
                if ($rawData) {
                    echo $rawData;
                    exit;
                }
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

            case 'errored':
                // Payment errored, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment error during checkout');
                wc_add_notice('Your payment errored during checkout, please try again.', 'error');
                if ($rawData) {
                    echo $rawData;
                    exit;
                }
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;

            case 'expired':
                // Payment has expired, move order to "failed" and redirect back to checkout
                $order->update_status('failed', 'Payment expired');
                wc_add_notice(
                    'Your payment was not completed in time, you have not been charged. Please try again.',
                    'error'
                );
                if ($rawData) {
                    echo $rawData;
                    exit;
                }
                wp_safe_redirect($order->get_checkout_payment_url());
                exit;
        }

        // If we get this far, an unknown error has occurred
        $order->update_status('failed', sprintf('Payment error: unknown payment state %s', $body->status));
        wc_add_notice('An unexpected error occurred while processing your payment.');
        if ($rawData) {
            echo 'error';
            exit;
        }
        wp_safe_redirect($order->get_checkout_payment_url());
        exit;
    }


    /**
     * Handle the refund webhook.
     */
    public function refund()
    {
        // Get the raw POST data
        $refund = json_decode(file_get_contents("php://input"));

        // Get the order
        $order = wc_get_order($refund->woo_order_id);

        if (!$order) {
            wc_get_logger()->error('Could not find order to refund: ' . $refund->woo_order_id);
            return;
        }

        // Add a note for this refund update to the order
        $order->add_order_note(
            sprintf(
                'Refund %s %s £%s',
                $refund->state,
                $refund->reason,
                $refund->amount / 100,
            )
        );

        // Record the successful refund against the order
        if ($refund->state == self::COMPLETED) {
            $amount = ($refund->amount / 100);
            if ($order->get_remaining_refund_amount() >= $amount) {
                $wooRefund = wc_create_refund(array(
                    'amount' => $amount,
                    'reason' => $refund->reason,
                    'order_id' => $refund->woo_order_id,
                    'refund_payment' => false
                ));
                if (is_wp_error($wooRefund)) {
                    if ($wooRefund->get_error_message() == 'Invalid refund amount.') {
                        wc_get_logger()->error(
                            'Refund requested exceeds remaining order balance of ' . $order->get_formatted_order_total()
                        );
                    } else {
                        wc_get_logger()->error($wooRefund->get_error_message());
                    }
                }
            } else {
                wc_get_logger()->error(
                    'Refund requested exceeds remaining order balance of ' . $order->get_formatted_order_total()
                );
            }
        }
    }

    // Webhook to update the payment status.
    public function payment_pending()
    {
        // Get the Wonderful Payments ID from the URL
        $wonderfulPaymentId = (isset($_GET['wonderfulPaymentId'])) ? sanitize_text_field(
            $_GET['wonderfulPaymentId']
        ) : null;
        $order_id = (isset($_GET['order_id'])) ? sanitize_text_field($_GET['order_id']) : null;

        // If the Wonderful Payments ID is still not found, bail
        if (null === $wonderfulPaymentId || null === $order_id) {
            echo 'error';
            exit;
        }

        // Get the order
        $order = wc_get_order($order_id);

        // Mark as pending (we're awaiting the payment)
        $updated = $order->update_status(
            'pending'
        );
        if ($updated) {
            $order->add_order_note('Payment Created - Wonderful Payments ID:' . $wonderfulPaymentId);
        } else {
            // The status update failed
            wc_get_logger()->error('Order status update failed');
        }
        exit;
    }

    public function payment_fields()
    {
        // Get the supported banks from Wonderful Payments
        if ($this->banks === null) {
            $response = $this->loadSupportedBanks();
            if (!$response) {
                return;
            }

            $this->banks = json_decode(wp_remote_retrieve_body($response));

            // Error checking and bail if creation failed!
            if (200 != $response['response']['code']) {
                wc_get_logger()->error(
                    'Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code']
                );
                wc_add_notice(
                    'Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code'],
                    'error'
                );
                return;
            }
        }

        // Define the plugin description and the 'What is Wonderful' button
        $description = '<p>' . $this->get_description() . '</p>';
        $whatIsWonderfulButton = '<div style="text-align: center; margin-bottom: 5px;  margin-top: 5px;">
        <a href="https://wonderful.co.uk" target="_blank" style="text-align: center; text-decoration: none; border: 1px solid #0073aa; border-radius: 5px; padding: 5px 10px; margin-left: 10px; display: inline-flex; align-items: center;">
            What is 
            <img src="https://wonderful.one/images/logo.png" alt="Wonderful" style="width: 60px; height: 20px; margin-left: 1px; margin-bottom: 7px;">
            ?
        </a></div>';

        echo '
    <div style="margin-left: auto; margin-right: auto; display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); align-items: start; gap: 1rem; margin-top: 1rem;">
        <div style="background-color: white; height: 28rem; overflow-y: auto;">';

        // Output the description and the 'What is Wonderful' button
        echo $description;
        echo $whatIsWonderfulButton;

        // Output the bank buttons.
        echo '
        <div style="margin-left: auto; margin-right: auto; display: grid; grid-template-columns: repeat(1, minmax(0, 1fr)); align-items: start; gap: 1rem; margin-top: 1rem;">
            <div style="background-color: white; height: 28rem; overflow-y: auto;">';

        foreach ($this->banks->data as $bank) {
            echo '<div class="bank-button" data-bank-id="' . $bank->bank_id . '" style="width: 100%; border: 1px solid #E2E8F0; transition: box-shadow 0.15s ease-in-out, border-color 0.15s ease-in-out;"
                     onmouseover="this.style.boxShadow = \'0 4px 6px rgba(0, 0, 0, 0.1)\'; this.style.borderColor = \'#4299e1\';"
                     onmouseout="this.style.boxShadow = \'none\'; this.style.borderColor = \'#E2E8F0\';"
                     onclick="this.style.backgroundColor = \'#1F2A64\';">
                    <span data-bank-id="' . $bank->bank_id . '" style="display: flex; align-items: center;">
                        <span data-bank-id="' . $bank->bank_id . '">
                            <img data-bank-id="' . $bank->bank_id . '" src="' . $bank->bank_logo . '" alt="" style="height: 2.5rem; width: 2.5rem; min-width: 2.5rem; margin: 1rem;">
                        </span>
                        <span data-bank-id="' . $bank->bank_id . '" style="text-align: left; color: #718096; overflow: hidden; padding-right: 1rem; font-family: paralucent, sans-serif; line-height: 0.1px">
                            <span data-bank-id="' . $bank->bank_id . '" style="font-size: 1.35rem; line-height: 2rem;">' . $bank->bank_name . '</span>';
            // PHP: If ($bank->status === self::ISSUES)
            if ($bank->status === self::ISSUES) {
                echo '<i class="fas fa-exclamation-triangle" style="color: #FFC107;" data-toggle="tooltip" data-placement="top" title="This bank may be experiencing issues"></i>';
            }
            // PHP: If ($bank->status === self::OFFLINE)
            if ($bank->status === self::OFFLINE) {
                echo '<i class="fas fa-exclamation-square" style="color: #A0AEC0;" data-toggle="tooltip" data-placement="top" title="This bank is currently offline"></i>';
            }
            echo '</span>
                    </span>
                </div>';
        }
        echo '<input type="hidden" id="selected_bank_id" name="aspsp_name">
                    <p style="font-size: 0.7em; text-align: center;">Instant payments are processed by <a href="https://wonderful.co.uk" target="_blank">Wonderful Payments</a>
                    and are subject to their <a href="https://wonderful.co.uk/legal" target="_blank">Consumer Terms and Privacy Policy</a>.</p>
                    ';
    }

    public function banks()
    {
        // Get the supported banks from Wonderful Payments
        if ($this->banks === null) {
            $response = $this->loadSupportedBanks();

            $this->banks = json_decode(wp_remote_retrieve_body($response));

            // Error checking and bail if creation failed!
            if (!isset($response['response']['code']) || 200 != $response['response']['code']) {
                wc_get_logger()->error(
                    'Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code']
                );
                wc_add_notice(
                    'Unable to initiate Wonderful Payments checkout, please try again or select another payment method. Code: ' . $response['response']['code'],
                    'error'
                );
                exit;
            }

            return $this->banks;
        }
    }

    private function loadSupportedBanks()
    {
        $response = wp_remote_get(
            self::ENDPOINT . '/v2/supported-banks',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->merchant_key,
                ],
                'sslverify' => self::SSL_VERIFY,
            ]
        );

        // Decode the response body
        $body = json_decode(wp_remote_retrieve_body($response));

        // Check if the response contains banks and filter them
        if (!empty($body->data)) {
            // Filter banks to only allow those with statuses "online" or "issues"
            $filtered_banks = array_filter($body->data, function ($bank) {
                return isset($bank->status) && ($bank->status === self::ONLINE || $bank->status === self::ISSUES);
            });

            // Update the response body with the filtered banks
            $body->data = $filtered_banks;
        }

        if (is_a($response, WP_Error::class)) {
            wc_get_logger()->error(
                'Unable to connect to Wonderful Payments, please try again or select another payment method.'
            );
            wc_add_notice(
                'Unable to connect to Wonderful Payments, please try again or select another payment method.',
                'error'
            );
            return false;
        }

        return $response;
    }
}
