<?php

/**
 * @author Łukasz Feller
 */
class EscapemaniaBooked implements EscapemaniaPluginInterface {

	const OPTION_GROUP = 'escapemania-booked';
	const OPTION_NAME = 'booked';

	protected $options;

	/**
	 * @var Api
	 */
	protected $api;

	public function __construct(Api $api) {
		$this->api = $api;
		$this->options = get_option(self::OPTION_NAME, ['is_enabled' => false]);
	}

	public function getName() {
		return 'Booked';
	}

	public function init() {
		if ($this->options['is_enabled']) {
			
		}
	}

	public function registerSettings() {
		register_setting(self::OPTION_GROUP, self::OPTION_NAME);
		$options = $this->options;

		add_settings_section(
			'escapemania_booked_settings_section',
			"Konfiguracja Booked",
			null,
			'escapemania-booked'
		);

		add_settings_field(
			'is_enabled',
			'Włączona synchronizacja',
			function() use($options) {
				echo '<input name="' . self::OPTION_NAME. '[is_enabled]"  type="checkbox" value="1" ' . checked(1, $options['is_enabled'], false) . ' />';
			},
			self::OPTION_GROUP,
			'escapemania_booked_settings_section'
		);

		if ($options['is_enabled']) {
			$rooms = $this->api->getRooms();

			$calendars = get_terms('booked_custom_calendars', 'orderby=slug&hide_empty=0');
			foreach ($calendars as $calendar) {
				add_settings_field(
					'calendar_' . $calendar->term_id,
					"Kalendarz {$calendar->name} dla pokoju w EscapeMania", function() use($options, $rooms, $calendar) {
						echo '<select name="' . self::OPTION_NAME . '[calendar_' . $calendar->term_id . ']">';
						echo '<option value="">--wybierz--</option>';
						foreach ($rooms as $room) {
							echo '<option value="' . $room['id'] . '" ' . selected(1, array_key_exists('calendar_' . $calendar->term_id, $options) && $room['id'] == $options['calendar_' . $calendar->term_id], false) . '>' . $room['name'] . ' (' . $room['city'] . ')</options>';
						}
						echo '</select>';
					},
					self::OPTION_GROUP,
					'escapemania_booked_settings_section'
				);
			}
		}
	}

	public function getPageTabKey() {
		return 'booked_plugin';
	}

	public function renderSettingsPage() {
		settings_fields(self::OPTION_GROUP);
		do_settings_sections(self::OPTION_GROUP);
	}
}