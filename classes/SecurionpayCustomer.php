<?php

class SecurionpayCustomer {

	private $userId;
	private $userMetaKey;
	
	private $customerId = null;
	private $defaultCardId = null;
	private $cards = array();

	public function __construct($userId) {
		global $securionpay4wc;
		
		$this->userId = $userId;
		$this->userMetaKey = $securionpay4wc->settings['securionpay_db_location'];

		$this->load();
	}
	
	private function load() {
		$data = maybe_unserialize(get_user_meta($this->userId, $this->userMetaKey, true));
		if (!$data) {
			return;
		}

		if (isset($data['customerId'])) {
			$this->customerId = $data['customerId'];
		}
		if (isset($data['defaultCardId'])) {
			$this->defaultCardId = $data['defaultCardId'];
		}
		if (isset($data['cards'])) {
			$this->cards = $data['cards'];
		}
	}

	public function save() {
		$data = array(
			'customerId' => $this->customerId,
			'defaultCardId' => $this->defaultCardId,
			'cards' => $this->cards	
		);
		
		return update_user_meta($this->userId, $this->userMetaKey, $data);		
	}

	public function getCustomerId() {
		return $this->customerId;
	}
	
	public function setCustomerId($customerId) {
		$this->customerId = $customerId;
	}

	public function getDefaultCardId() {
		return $this->defaultCardId;
	}
	
	public function getDefaultCardIndex() {
		foreach ($this->cards as $i => $card) {
			if ($card['id'] == $this->defaultCardId) {
				return $i;
			}
		}

		return null;
	}
	
	public function setDefaultCardId($defaultCardId) {
		$this->defaultCardId = $defaultCardId;
	}

	public function getCards() {
		return $this->cards;
	}
	
	public function getCard($index) {
		if (!isset($this->cards[$index])) {
			return null;
		}
		
		return $this->cards[$index];
	}

	public function addCard($cardId, $last4, $expMonth, $expYear, $brand) {
		$this->cards[] = array(
			'id' => $cardId,
			'last4' => $last4,
			'expMonth' => $expMonth,
			'expYear' => $expYear,
			'brand' => $brand	
		);
	}

	public function deleteCard($index) {
		unset($this->cards[$index]);
	}
}
