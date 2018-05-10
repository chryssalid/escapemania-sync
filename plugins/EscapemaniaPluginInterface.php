<?php

/**
 * @author Łukasz Feller
 */
interface EscapemaniaPluginInterface {

	public function getName();
    public function init();
	public function adminInit();
	public function registerSettings();
	public function renderSettingsPage();
	public function getPageTabKey();
}
