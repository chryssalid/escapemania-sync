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
				if (isset($response['message'])) {
					$this->lastError = $response['message'];
				}
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
	 * Zwraca informacje o aktywnych pokojach.
	 * @return array
	 */
	public function getRooms() {
		$response = $this->validateResponse($this->RESTCommunication->request('get-rooms'));
		if ($response) {
			return $response['rooms'];
		}
		return [];
	}

	/**
	 * Zwraca listę rezerwacji dla wybranych pokoi
	 * @param int $ids
	 * @param DateTime $from
	 * @param DateTime $to
	 * @return array
	 */
	public function getBooking($ids = [], DateTime $from = null, DateTime $to = null) {
		if ($from === null || !($from instanceof DateTime)) {
			$from = new DateTime;
		}
		if ($to === null || !($to instanceof DateTime)) {
			$to = new DateTime;
		}
		$response = $this->validateResponse($this->RESTCommunication->request('get-booking', ['ids' => $ids, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]));
		if ($response) {
			return $response['bookings'];
		}
		return [];
	}

	/**
	 * Umieszcza listę rezerwacji.
	 * @param array $bookings
	 * @return string[]
	 */
	public function putBooking($bookings) {
		$response = $this->validateResponse($this->RESTCommunication->request('put-booking', $bookings, RESTCommunication::CALL_METHOD_PUT));
		return $response;
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