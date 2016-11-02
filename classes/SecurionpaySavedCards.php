<?php
require_once 'SecurionpayApi.php';
require_once 'SecurionpayCustomer.php';

class SecurionpaySavedCards {

	private $api;
	
	public function __construct() {
		$this->api = new SecurionpayApi();
	}
	
	public static function registerHooks() {
		$savedCards = new SecurionpaySavedCards();
		add_action('woocommerce_after_my_account', array($savedCards, 'accountSavedCards'));
	}

	public function accountSavedCards() {
		if (!Securionpay4WC::allowSavedCards()) {
			return;
		}

		$customer = new SecurionpayCustomer(get_current_user_id());
		if (!$customer || !$customer->getCards()) {
			return;
		}
		
		$this->handleDeleteCard($customer);

		$data = array(
			'cards' => $customer->getCards()
		);
	
		Securionpay4WC::getTemplate('saved-cards.php', $data);
	}
	
	private function handleDeleteCard($customer) {
		if (!isset($_POST['securionpay4wc-delete-card']) || !wp_verify_nonce($_POST['_wpnonce'], 'securionpay4wc-delete-card')) {
			return;
		}
		$deleteCardIndex = $_POST['securionpay4wc-delete-card'];
		$card = $customer->getCard($deleteCardIndex);
	
		if (!$card) {
			return;
		}

		try {
			$this->api->deleteCard($customer, $card['id']);
		} catch (Exception $e) {
			$this->api->handleException($e);
			return;
		}

		$customer->deleteCard($deleteCardIndex);
		$customer->save();
		
		wc_add_notice(__('Card deleted successfully', 'securionpay-for-woocommerce'));
	}
}
