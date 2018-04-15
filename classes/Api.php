<?php

use chryssalid\REST\RESTCommunication;

/**
 * Most pomiędzy pluginem WP, a RESTCommunication.
 * 
 * @author Łukasz Feller
 */
class Api {

	protected $RESTCommunication;
	protected $lastError = '';
	protected $lastErrorNo;

	public function __construct($apiKey, $apiSecret) {
		$this->RESTCommunication = new RESTCommunication($apiKey, $apiSecret, 'https://escapemania/app_dev.php/api/v' . RESTCommunication::API_VERSION . '/call');
	}

	protected function validateResponse($response) {
		if ($response) {
			if ($response['status'] === 'ok') {
				return $response;
			}
			else {
				$this->lastError = $response['message'];
			}
		} else {
			$this->lastError = $this->RESTCommunication->getError();
			$this->lastErrorNo = $this->RESTCommunication->getErrorNo();
		}
		return false;
	}

	/**
	 * @param string $url
	 * @return boolean
	 */
	public function registerSyncUrl($url) {
		$response = $this->validateResponse($this->RESTCommunication->request('register-sync-url', ['url' => $url], RESTCommunication::CALL_METHOD_POST));
		if ($response) {
			return true;
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function getRooms() {
		$response = $this->validateResponse($this->RESTCommunication->request('get-rooms'));
		if ($response) {
			return $response['rooms'];
		}
		return [];
	}

	public function read() {
		
	}

	/**
	 * @return string
	 */
	public function getLastError() {
		return $this->lastError;
	}

	/**
	 * @return int
	 */
	public function getLastErrorNo() {
		return $this->lastErrorNo;
	}
}