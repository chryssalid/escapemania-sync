<?php

/**
 * @author Łukasz Feller
 */
class EscapemaniaBooked implements EscapemaniaPluginInterface {

	protected $options;

	public function __construct($options) {
		$this->options = get_option('escapemania_booked');
	}

	public function getName() {
		return 'Booked';
	}

	public function init() {
		
	}

	public function registerSettings() {
		;
	}

	public function getPageTabKey() {
		return 'booked_plugin';
	}

	public function renderSettingsPage() {
		echo '';
	}
}