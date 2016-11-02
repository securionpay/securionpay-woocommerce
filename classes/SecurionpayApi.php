<?php
use SecurionPay\SecurionPayGateway;
use SecurionPay\Exception\SecurionPayException;
use SecurionPay\Exception\ConnectionException;
use SecurionPay\Request\ChargeRequest;
use SecurionPay\Request\RefundRequest;
use SecurionPay\Request\CustomerRequest;
use SecurionPay\Request\CardRequest;

require_once 'SecurionPay/Util/SecurionPayAutoloader.php';
\SecurionPay\Util\SecurionPayAutoloader::register();


class SecurionpayApi {

	private $gateway = null;

	private $publicKey;
	private $privateKey;
	
	public function __construct() {
		global $securionpay4wc;
		
		$this->publicKey = $securionpay4wc->settings['public_key'];
		$this->privateKey = $securionpay4wc->settings['secret_key'];
	}

	/**
	 * @param SecurionpayCustomer $customer
	 */
	public function createCharge($customer, $cardId, $order) {
		$request = new ChargeRequest();
	
		$request->amount((int)($order->get_total() * 100));
		$request->currency(strtoupper($order->get_order_currency()));
	
		$request->card($cardId);
		if ($customer) {
			$request->customerId($customer->getCustomerId());
		}

		$request->description($this->createChargeDescription($order));
		$request->metadata($this->createChargeMetadata($order));
	
		return $this->gateway()->createCharge($request);
	}
	
	private function createChargeDescription($order) {
		$description = sprintf(__('WooCommerce order #%s', 'securionpay-for-woocommerce'), $order->get_order_number());
		return apply_filters('securionpay4wc_charge_description', $description, $order);
	}
	
	private function createChargeMetadata($order) {
		$metadata = array(
				'woocommerce-order-id' => $order->get_order_number()
		);
		return apply_filters('securionpay4wc_charge_metadata', $metadata, $order);
	}
	
	public function createCustomer() {
		$user = wp_get_current_user();
			
		$request = new CustomerRequest();
	
		$request->email($user->user_email);
	
		$request->description($this->createCustomerDescription($user));
		$request->metadata($this->createCustomerMetadata($user));
	
		return $this->gateway()->createCustomer($request);
	}
	
	private function createCustomerDescription($user) {
		$description = sprintf(__('WordPress user #%s', 'securionpay-for-woocommerce'), $user->ID, $user->user_login);
		return apply_filters('securionpay4wc_customer_description', $description, $user);
	}
	
	private function createCustomerMetadata($user) {
		$metadata = array(
				'wordpress-user-id' => $user->ID,
				'wordpress-user-login' => $user->user_login
		);
		return apply_filters('securionpay4wc_customer_metadata', $metadata, $user);
	}
	
	public function refundCharge($order, $amount, $reason) {
		$request = new RefundRequest();
	
		$request->chargeId($order->get_transaction_id());
	
		if ($amount) {
			$request->amount((int)($amount * 100));
		}
	
		return $this->gateway()->refundCharge($request);
	}
	
	/**
	 * @param SecurionpayCustomer $customer
	 */
	public function deleteCard($customer, $cardId) {
		$this->gateway()->deleteCard($customer->getCustomerId(), $cardId);
	}
	
	public function handleException($e, $prefix = '') {
		if ($e instanceof SecurionPayException) {
			if (!$e->getCode()) {
				$this->logError("SecurionPay exception", $e);
			}
			$this->handleError($e->getType(), $e->getCode(), $e->getMessage(), $prefix);

		} else if ($e instanceof ConnectionException) {
			$this->logError("Connection exception", $e);
			$this->handleError(null, null, $e->getMessage(), $prefix);

		} else {
			$this->logError("Unknown exception", $e);
			throw $e;
		}
	}

	private function logError($message, $exception = null) {
		try {
			$message = date('[Y-m-d H:i:s] ') . $message . "\n";
			if ($exception) {
				$message .= $exception . "\n";
			}

			error_log($message, 3, __DIR__ . '/../error.log');
		} catch (\Exception $e) {
			// ignore
    	}
	}

	public function handleError($type, $code, $message, $prefix = '') {
		if ($code) {
			wc_add_notice($prefix . $this->getMessageForErrorCode($code), 'error');
		} else {
			wc_add_notice($prefix . __('Problem connecting to the payment gateway. Please try again later.', 'securionpay-for-woocommerce'), 'error');
		}
	}
	
	private function getMessageForErrorCode($code) {
		switch ($code) {
			case 'invalid_number':
				return __('The card number is not a valid credit card number.', 'securionpay-for-woocommerce');
			case 'invalid_expiry_month':
				return __('The card\'s expiration month is invalid.', 'securionpay-for-woocommerce');
			case 'invalid_expiry_year':
				return __('The card\'s expiration year is invalid.', 'securionpay-for-woocommerce');
			case 'invalid_cvc':
				return __('Your card\'s security code is invalid.', 'securionpay-for-woocommerce');
			case 'incorrect_cvc':
				return __('The card\'s security code failed verification.', 'securionpay-for-woocommerce');
			case 'incorrect_zip':
				return __('The card\'s zip code failed verification.', 'securionpay-for-woocommerce');
			case 'expired_card':
				return __('The card has expired.', 'securionpay-for-woocommerce');
			case 'insufficient_funds':
			case 'lost_or_stolen':
			case 'suspected_fraud':
			case 'card_declined':
				return __('The card was declined.', 'securionpay-for-woocommerce');
			case 'processing_error':
			default:
				return __('An error occurred while processing the card.', 'securionpay-for-woocommerce');
		}
	}
	
	public function getPublicKey() {
		return $this->publicKey;
	}
	
	private function gateway() {
		if ($this->gateway == null) {
			$userAgent = 'SecurionPay-WooCommerce/' . Securionpay4WC::VERSION . ' (WordPress/' . get_bloginfo('version');
	        if (isset($GLOBALS['woocommerce'])) {
	            $userAgent .= ' WooCommerce/' . $GLOBALS['woocommerce']->version;
	        }
	        $userAgent .= ')';
		
			$this->gateway = new SecurionPayGateway($this->privateKey);
			$this->gateway->setUserAgent($userAgent);
		}
	
		return $this->gateway;
	}
}
