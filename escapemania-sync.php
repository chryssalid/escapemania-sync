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

require_once ESCAPEMANIA_PLUGIN_DIR . '/plugins/EscapemaniaPluginInterface.php';
require_once ESCAPEMANIA_PLUGIN_DIR . '/plugins/EscapemaniaBooked.class.php';

class EscapemaniaSync {

	protected $plugins = array();
	protected $selectedTab;

	public function __construct() {
		$this->options = get_option("escapemania_settings");
		$this->selectedTab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';

		add_action('init', array(&$this, 'init'));

		$this->plugins['booked'] = new EscapemaniaBooked(get_option('escapemania_booked'));
		foreach ($this->plugins as $plugin) {
			$plugin->init();
		}

		if (!session_id()) {
			session_start();
		}

		if (is_admin()) {
			add_action('admin_init', array(&$this, 'adminInit'));
			add_action('admin_menu', array(&$this, 'adminMenu'));
		}
	}

	public function init() {
		add_rewrite_tag('%escapemania_api%', '([^&]+)');
		add_rewrite_rule('escapemania-api', 'index.php?escapemania_api=1', 'top');
	}

	public function adminInit() {
		register_setting('escapemania-admin', 'escapemania_settings');
		$options = $this->options;

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
				echo '<input style="min-width: 300px;" name="escapemania_settings[api_key]"  type="text" value="' . isset($options['api_key']) ? $options['api_key'] : '' . '" />';
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		add_settings_field(
			'api_secret',
			'API secret',
			function() use($options) {
				echo '<input style="min-width: 300px;" name="escapemania_settings[api_secret]"  type="text" value="' . isset($options['api_secret']) ? $options['api_secret'] : '' . '" />';
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		add_settings_field(
			'api_url',
			'Adres URL synchronizacji',
			function() use($options) {
				echo '<input style="min-width: 400px;" readonly name="escapemania_settings[api_url]"  type="text" value="' . get_site_url() . '/escapemania-api/" />';
			},
			'escapemania-admin',
			'escapemania_settings_section',
			array()
		);

		add_settings_field(
			'app_is_registered',
			'Status rejestracji aplikacji',
			function() use($options) {
				echo '<input style="min-width: 400px;" readonly name="escapemania_settings[api_is_registered]"  type="hidden" value="' . isset($options['api_is_registered']) ? $options['api_is_registered'] : '' .'" />';
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

			<form method="post" action="options.php">
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
}

$escapemaniaSync = new EscapemaniaSync;
