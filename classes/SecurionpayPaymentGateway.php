<?php
require_once 'SecurionpayApi.php';
require_once 'SecurionpayCustomer.php';

class SecurionpayPaymentGateway extends WC_Payment_Gateway {
	
	private $api;
	
	public function __construct() {
		$this->api = new SecurionpayApi();

		$this->id = 'securionpay4wc';
		$this->method_title = 'SecurionPay for WooCommerce';
		$this->has_fields = true;
		$this->supports = array(
			'default_credit_card_form',
			'products',
			'refunds',
		);
		
		// Init settings
		$this->init_form_fields();
		$this->init_settings();
		
		// Use settings
		$this->enabled = $this->settings['enabled'];
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];

		// Hooks
		add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('admin_notices', array($this, 'admin_notices'));
		add_action('wp_enqueue_scripts', array($this, 'load_scripts'));
	}

	public function payment_fields() {
		$data = array(
			'description' => $this->description,
			'cards' => null,
			'selectedCard' => null
		);
		
		if ($this->allowSavedCards()) {
			$customer = new SecurionpayCustomer(get_current_user_id());
			$data['cards'] = $customer->getCards();
			
			if (isset($_POST['securionpay4wc-card'])) {
				$data['selectedCard'] = $_POST['securionpay4wc-card'];
			} else {
				$data['selectedCard'] = $customer->getDefaultCardIndex();
			}
		}

		Securionpay4WC::getTemplate('payment-fields.php', $data);

		if (class_exists('WC_Payment_Gateway_CC')) {
		    $cc_form = new WC_Payment_Gateway_CC;
		    $cc_form->id       = $this->id;
		    $cc_form->supports = $this->supports;
		    $cc_form->supports[] = 'tokenization';
		    $cc_form->form();
        } else {
    		$this->credit_card_form(array(
    			'fields_have_names' => false
    		));
        }
	}
	
	public function validate_fields() {
		$cardNumber   = isset($_POST['securionpay4wc-card-number']) ? $_POST['securionpay4wc-card-number'] : null;
		$cardExpiry   = isset($_POST['securionpay4wc-card-expiry']) ? $_POST['securionpay4wc-card-expiry'] : null;
		$cardCvc      = isset($_POST['securionpay4wc-card-cvc']) ? $_POST['securionpay4wc-card-cvc'] : null;
		$errorType    = isset($_POST['securionpay4wc-error-type']) ? $_POST['securionpay4wc-error-type'] : null;
		$errorCode    = isset($_POST['securionpay4wc-error-code']) ? $_POST['securionpay4wc-error-code'] : null;
		$errorMessage = isset($_POST['securionpay4wc-error-message']) ? $_POST['securionpay4wc-error-message'] : null;
		
		if ($cardNumber) {
			$field = __('Credit Card Number', 'securionpay-for-woocommerce');
			wc_add_notice($this->getValidationErrorMessage($field, $cardNumber), 'error');
		}
		if ($cardExpiry) {
			$field = __('Credit Card Expiration', 'securionpay-for-woocommerce');
			wc_add_notice($this->getValidationErrorMessage($field, $cardExpiry), 'error');
		}
		if ($cardCvc) {
			$field = __('Credit Card CVC', 'securionpay-for-woocommerce');
			wc_add_notice($this->getValidationErrorMessage($field, $cardCvc), 'error');
		}
	
		if ($errorType || $errorCode) {
			$this->api->handleError($errorType, $errorCode, $errorMessage);
		}
	}

	public function process_payment($order_id) {
		$order = new WC_Order($order_id);
		
		try {
			$customer = null;
			$cardId = null;
			$newCard = false;

			if ($this->allowSavedCards()) {
				$customer = new SecurionpayCustomer(get_current_user_id());
				if (!$customer->getCustomerId()) {
					$customerResponse = $this->api->createCustomer();
					
					$customer->setCustomerId($customerResponse->getId());
					$customer->save();
				}

                $cardIndex = isset($_POST['securionpay4wc-card']) ? $_POST['securionpay4wc-card'] : '';
                $card = $customer->getCard($cardIndex);
                if ($card) {
                    $cardId = $card['id'];
                }
			}

			if (!$cardId) {
				$newCard = true;
				$cardId = isset($_POST['securionpay4wc-token']) ? $_POST['securionpay4wc-token'] : '';
				if (!$cardId) {
					$field = __('Credit Card Number', 'securionpay-for-woocommerce');
					wc_add_notice($this->getValidationErrorMessage($field), 'error');
					return;
				}
			}
			
			$charge = $this->api->createCharge($customer, $cardId, $order);

			if ($customer) {
				$card = $charge->getCard();
				if ($newCard) {
					$customer->addCard($card->getId(), $card->getLast4(), $card->getExpMonth(), $card->getExpYear(), $card->getBrand());
				}
                $customer->setDefaultCardId($card->getId());
				$customer->save();
			}

			$this->completePayment($order, $charge->getId());
			return array('result' => 'success', 'redirect' => $this->get_return_url($order));

		} catch (Exception $e) {
			$this->api->handleException($e, '<span class="securionpay4wc-token-consumed"></span>');
		}
	}

	private function completePayment($order, $chargeId) {
		if ($order->status == 'completed') {
			return;
		}
	
		$order->payment_complete($chargeId);
		$order->add_order_note(
				sprintf(__('%s: payment completed with charge-id of "%s"', 'securionpay-for-woocommerce'),
						$this->method_title,
						$chargeId
				)
		);
	}

	public function process_refund($order_id, $amount = null, $reason = '') {
		$order = new WC_Order($order_id);
	
		try {
			$this->api->refundCharge($order, $amount, $reason);

			$order->add_order_note(
					sprintf(__('%s: refunded %.2f from charge "%s"', 'securionpay-for-woocommerce'),
							$this->method_title, $amount, $order->get_transaction_id()
					)
			);

			return true;
			
		} catch (Exception $e) {
			$order->add_order_note(
					sprintf(__('%s: refund of charge "%s" failed with message: "%s"', 'securionpay-for-woocommerce'),
							$this->method_title,
							$order->get_transaction_id(),
							$e->getMessage()
					)
			);

			return new WP_Error('securionpay4wc_refund_error', $e->getMessage());
		}
	}

	private function getValidationErrorMessage($field, $errorType = 'undefined') {
		if ($errorType === 'invalid') {
			return sprintf(__('Please enter a valid %s.', 'securionpay-for-woocommerce'), "<strong>$field</strong>");
		} else {
			return sprintf(__('%s is a required field.', 'securionpay-for-woocommerce'), "<strong>$field</strong>");
		}
	}
	
	private function allowSavedCards() {
		return Securionpay4WC::allowSavedCards();
	}

	public function init_form_fields() {
		$this->form_fields = array(
				'enabled' => array(
						'type'        => 'checkbox',
						'title'       => __('Enable/Disable', 'securionpay-for-woocommerce'),
						'label'       => __('Enable SecurionPay for WooCommerce', 'securionpay-for-woocommerce'),
						'default'     => 'yes'
				),
				'title' => array(
						'type'        => 'text',
						'title'       => __('Title', 'securionpay-for-woocommerce'),
						'description' => __('This controls the title which the user sees during checkout.', 'securionpay-for-woocommerce'),
						'default'     => __('Credit Card Payment', 'securionpay-for-woocommerce')
				),
				'description' => array(
						'type'        => 'textarea',
						'title'       => __('Description', 'securionpay-for-woocommerce'),
						'description' => __('This controls the description which the user sees during checkout.', 'securionpay-for-woocommerce'),
						'default'     => '',
				),
				'saved_cards' => array(
						'type'        => 'checkbox',
						'title'       => __('Saved Cards', 'securionpay-for-woocommerce'),
						'description' => __('Allow customers to use saved cards for future purchases.', 'securionpay-for-woocommerce'),
						'default'     => 'yes',
				),
				'testmode' => array(
						'type'        => 'checkbox',
						'title'       => __('Test Mode', 'securionpay-for-woocommerce'),
						'description' => __('Use the test mode on SecurionPay\'s dashboard to verify everything works before going live.', 'securionpay-for-woocommerce'),
						'label'       => __('Turn on testing', 'securionpay-for-woocommerce'),
						'default'     => 'yes'
				),
				'test_secret_key' => array(
						'type'        => 'text',
						'title'       => __('SecurionPay API Test Secret Key', 'securionpay-for-woocommerce'),
						'default'     => '',
				),
				'test_public_key' => array(
						'type'        => 'text',
						'title'       => __('SecurionPay API Test Public Key', 'securionpay-for-woocommerce'),
						'default'     => '',
				),
				'live_secret_key' => array(
						'type'        => 'text',
						'title'       => __('SecurionPay API Live Secret Key', 'securionpay-for-woocommerce'),
						'default'     => '',
				),
				'live_public_key' => array(
						'type'        => 'text',
						'title'       => __('SecurionPay API Live Public Key', 'securionpay-for-woocommerce'),
						'default'     => '',
				),
                '3dsecure' => array(
                    'type'        => 'checkbox',
                    'title'       => __('3D Secure', 'securionpay-for-woocommerce'),
                    'description' => __('Use 3D Secure functionality during payment.', 'securionpay-for-woocommerce'),
                    'label'       => __('Turn on 3D Secure', 'securionpay-for-woocommerce'),
                    'default'     => 'no'
                ),
		);
	}

	public function admin_notices() {
		global $securionpay4wc;
	
		if ($this->enabled == 'no') {
			return;
		}

		// Check for API Keys
		if (!$securionpay4wc->settings['public_key'] && !$securionpay4wc->settings['secret_key']) {
			echo '<div class="error"><p>'
					. sprintf(__('%s: API Keys are not configured.', 'securionpay-for-woocommerce'),
							$this->method_title)
					. '</p></div>';
		}

		// Force SSL on production
		if ($this->settings['testmode'] == 'no' && get_option( 'woocommerce_force_ssl_checkout') == 'no') {
			echo '<div class="error"><p>'
					. sprintf(__('%s: for security reasons SSL is required on checkout pages - read more in <a href="http://docs.woothemes.com/document/ssl-and-https/" target="_blank">the WooCommerce documentation</a>.', 'securionpay-for-woocommerce'),
							$this->method_title)
					. '</p></div>';
		}
	}

	public function load_scripts() {
		global $securionpay4wc;

		$is3DSecure = (isset($securionpay4wc->settings['3dsecure']) && $securionpay4wc->settings['3dsecure'] === 'yes') ? true : false;

		$scriptQueryParam = '';
		if ($is3DSecure) {
            $scriptQueryParam = '?mode=3ds';
        }

		wp_enqueue_script('securionpay', 'https://securionpay.com/js/securionpay.js', false, false, true);
		wp_enqueue_script(
		    'securionpay4wc_js',
            plugins_url('assets/js/securionpay4wc.js'.$scriptQueryParam, dirname(__FILE__)),
            array('securionpay', 'wc-credit-card-form'),
            false,
            true
        );

		$data = array(
			'publicKey' => $this->api->getPublicKey(),
            'threedsecure' => $is3DSecure ? 'yes' : 'no',
            'ajax_url' => admin_url('admin-ajax.php')
		);

		if (is_checkout_pay_page()) {
			$order_key = urldecode($_GET['key']);
			$order_id = absint(get_query_var('order-pay'));
			$order = new WC_Order($order_id);

			if ($order->get_id() == $order_id && $order->order_key == $order_key) {
				$data['billing_name'] = $order->billing_first_name . ' ' . $order->billing_last_name;
				$data['billing_address_1'] = $order->billing_address_1;
				$data['billing_address_2'] = $order->billing_address_2;
				$data['billing_city'] = $order->billing_city;
				$data['billing_state'] = $order->billing_state;
				$data['billing_postcode'] = $order->billing_postcode;
				$data['billing_country'] = $order->billing_country;
			}
		}
	
		wp_localize_script('securionpay4wc_js', 'securionpay4wc_data', $data);
	}
}
