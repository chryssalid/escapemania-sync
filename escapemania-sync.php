<?php

/*
  Plugin Name: Escapemania Sync
  Version:     20180402
  Author:      Łukasz Feller
  License:     GPLv3
  License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 */
defined('ABSPATH') or die('No script kiddies please!');

require_once __DIR__ . '/vendor/autoload.php';

global $escapemaniaSync;

define('ESCAPEMANIA_PLUGIN_DIR', dirname(__FILE__), true);
define('ESCAPEMANIA_PLUGIN_FILE', __FILE__, true);

require_once ESCAPEMANIA_PLUGIN_DIR . '/classes/Api.php';
require_once ESCAPEMANIA_PLUGIN_DIR . '/plugins/EscapemaniaPluginInterface.php';
require_once ESCAPEMANIA_PLUGIN_DIR . '/plugins/EscapemaniaBooked.class.php';

class EscapemaniaSync {

	/**
	 * @var Api
	 */
	protected $api;

	protected $plugins = array();
	protected $selectedTab;

	protected $RESTCommunication;

	public function __construct() {
		$this->options = get_option("escapemania_settings");

		$this->plugins['booked'] = new EscapemaniaBooked($this->getApi());
		foreach ($this->plugins as $plugin) {
			$plugin->init();
		}

		add_action('init', array(&$this, 'callback'));

		if (!session_id()) {
			session_start();
		}

		if (is_admin()) {
			$this->selectedTab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
			add_action('admin_init', array(&$this, 'registerSettings'));
			add_action('admin_menu', array(&$this, 'adminMenu'));
		}
	}

	/**
	 * 
	 */
	public function callback() {
		if (isset($_GET['escapemania_sync']) && $_GET['escapemania_sync'] === 'callback') {
			$api = $this->getApi();
			exit;
		}
	}

	public function registerSettings() {
		register_setting('escapemania-admin', 'escapemania_settings');
		$options = $this->options;

		$sync_url = get_site_url() . '/?escapemania_sync=callback';

		add_settings_section(
			'escapemania_settings_section',
			"Konfiguracja API",
			function() {
				echo '<p>Wprowadź tutaj dane z sekcji Konfiguracja API w panelu partnera Escapemania.</p>';
			},
			'escapemania-admin'
		);

		add_settings_field(
			'api_key',
			'Klucz API',
			function() use($options) {
				echo '<input style="min-width: 300px;" name="escapemania_settings[api_key]"  type="text" value="' . (isset($options['api_key']) ? $options['api_key'] : '') . '" />';
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		add_settings_field(
			'api_secret',
			'API secret',
			function() use($options) {
				echo '<input style="min-width: 300px;" name="escapemania_settings[api_secret]"  type="text" value="' . (isset($options['api_secret']) ? $options['api_secret'] : '') . '" />';
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		add_settings_field(
			'api_url',
			'Adres URL synchronizacji',
			function() use($sync_url) {
				echo '<input style="min-width: 400px;" readonly name="escapemania_settings[api_url]"  type="text" value="' . $sync_url . '" />';
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		add_settings_field(
			'app_is_registered',
			'Status rejestracji aplikacji WP',
			function() use($options) {
				$registerUrl = '<a href="?page=escapemania_settings&amp;tab=settings&amp;register=1">zarejestruj</a>';
				// sprawdzenie statusu aplikacji
				$api = $this->getApi();
				if ($api === null) {
					echo 'niezarejestrowana - ' . $registerUrl;
				} else {
					$registerResult = $api->registerSyncUrl($options['api_url']);
					if ($registerResult) {
						echo 'zarejestrowana';
					}
					else {
						echo 'niezarejestrowana - ' . $api->getLastError() . ' - ' . $registerUrl;
					}
				}
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		foreach ($this->plugins as $plugin) {
			$plugin->registerSettings();
		}
	}

	public function adminMenu() {
		add_options_page('Integracja z Escapemania', 'Escapemania Sync', 'manage_options', 'escapemania_settings', array(&$this, 'adminPage'));
	}

	public function adminPage() {
		if(!current_user_can( 'manage_options')){
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}

		?>
		<div class="nav-tab-wrapper">
			<h1>Escapemania Sync</h1>
    
		    <h2 class="nav-tab-wrapper">
				<a href="?page=escapemania_settings&amp;tab=settings" class="nav-tab <?php echo $this->selectedTab === 'settings' ? 'nav-tab-active' : '' ?>">Ustawienia API</a>
				<?php foreach ($this->plugins as $plugin): ?>
					<a href="?page=escapemania_settings&amp;tab=<?php echo $plugin->getPageTabKey() ?>" class="nav-tab <?php echo $this->selectedTab === $plugin->getPageTabKey() ? 'nav-tab-active' : '' ?>">Ustawienia <?php echo $plugin->getName() ?></a>
				<?php endforeach ?>
			</h2>

			<form method="post" action="options.php?escapemania_settings_updated=1">
				<?php
				switch ($this->selectedTab) {
					case 'settings':
						$this->renderApiSettingsPage();
						break;
					default:
						foreach ($this->plugins as $plugin) {
							if ($this->selectedTab === $plugin->getPageTabKey()) {
								$plugin->renderSettingsPage();
							}
						}
						break;
				}
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function renderApiSettingsPage() {
		settings_fields('escapemania-admin');
		do_settings_sections('escapemania-admin');
	}

	public function getApi() {
		if ($this->api === null && !empty($this->options['api_key']) && !empty($this->options['api_secret'])) {
			$this->api = new Api($this->options['api_key'], $this->options['api_secret']);
		}
		return $this->api;
	}
}

$escapemaniaSync = new EscapemaniaSync;
