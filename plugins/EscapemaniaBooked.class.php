<?php

/**
 * @author Łukasz Feller
 */
class EscapemaniaBooked implements EscapemaniaPluginInterface {

	const OPTION_GROUP = 'escapemania-booked';
	const OPTION_NAME = 'booked';

	protected $options;
	protected $messages = [];

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

	public function adminInit() {
		// sprawdzenie, czy żądamy synchronizacji
		if (isset($_GET['tab']) && $_GET['tab'] === $this->getPageTabKey() && isset($_GET['synchronize']) && $_GET['synchronize'] === '1') {
			$this->synchronize();
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

			$calendars = get_terms(['taxonomy' => 'booked_custom_calendars', 'orderby' => 'slug', 'hide_empty' => 0]);
			foreach ($calendars as $calendar) {
				add_settings_field(
					'calendar_' . $calendar->term_id,
					"Kalendarz {$calendar->name} dla pokoju w EscapeMania", function() use($options, $rooms, $calendar) {
						echo '<select name="' . self::OPTION_NAME . '[calendar][' . $calendar->term_id . ']">';
						echo '<option value="">--wybierz--</option>';
						foreach ($rooms as $room) {
							echo '<option value="' . $room['id'] . '" ' . selected(1, isset($options['calendar'][$calendar->term_id]) && $room['id'] == $options['calendar'][$calendar->term_id], false) . '>' . $room['name'] . ' (' . $room['city'] . ')</options>';
						}
						echo '</select>';
					},
					self::OPTION_GROUP,
					'escapemania_booked_settings_section'
				);
			}

			add_settings_field(
				'synchronization_to_escapemania',
				'Synchronizacja z Escapemania',
				function() {
					echo '<a href="?page=escapemania_settings&amp;tab=' . $this->getPageTabKey() . '&amp;synchronize=1">synchronizuj</a> (koniecznie przeczytaj o tej opcji w dokumentacji API przed jej użyciem)';
				},
				self::OPTION_GROUP,
				'escapemania_booked_settings_section'
			);
		}
	}

	public function synchronize() {
		set_time_limit(0);

		// pobieramy informacje wyłącznie dla skonfigurowanych pokoi
		$calendars = get_terms(['taxonomy' => 'booked_custom_calendars', 'orderby' => 'slug', 'hide_empty' => 0]);
		$request = [];

		foreach ($calendars as $calendar) {
			// pokój ma swój odpowiednik w wp
			if (array_key_exists($calendar->term_id, $this->options['calendar']) && $this->options['calendar'][$calendar->term_id] != '') {
				// pobranie rezerwacji dla wybranego kalendarza
				$params = array(
					'post_type' => 'booked_appointments',
					'orderby' => 'ID',
					'meta_key' => '_appointment_timestamp',
					'meta_value_num' => strtotime("today"),
					'meta_compare' => '>',
					'posts_per_page' => -1,
					'tax_query' => array(
						array(
							'taxonomy' => 'booked_custom_calendars',
							'field' => 'term_id',
							'terms' => $calendar->term_id
						)
					)
				);
				// do zmiany - w tej chwili powiela te same posty dla każdego kolejnego kalendarza
				$query = new WP_Query($params);
				while ($query->have_posts()) {
					$query->the_post();
					$post = $query->post;
					$booking = $this->prepareBookingForRequest($this->options['calendar'][$calendar->term_id], $post, $calendar->name);
					if (count($booking)) {
						$request['bookings'][] = $booking;
					}
				}
			}
		}

		if (count($request)) {
			$result = $this->api->putBooking($request);
			if ($result['status'] === 'ok') {
				$this->messages = $result['messages'];
			} else {
				$this->messages[] = $this->api->getLastError();
			}
		}

		

		if (count($this->messages)) {
			var_dump($this->messages);
		}
	}

	protected function prepareBookingForRequest($roomId, $wpPost, $tag = '') {
		$booking = array();
		$timestamp = get_post_meta($wpPost->ID, "_appointment_timestamp", true);
		if ($timestamp >= strtotime("today")) {
			$timeslot = explode('-', get_post_meta($wpPost->ID, '_appointment_timeslot', true));
			$timeFrom = str_split($timeslot[0], 2);
			$timeTo   = str_split($timeslot[1], 2);

			$booking = array(
				'roomId' => $roomId,
				'foreignId' => $wpPost->ID,
				'action' => get_post_status($wpPost->ID) == 'trash' ? 'delete' : 'add',
				'bookedAt' => date('Y-m-d', $timestamp) . ' ' . $timeFrom[0] . ':' . $timeFrom[1],
				'bookedTo' => date('Y-m-d', $timestamp) . ' ' . $timeTo[0] . ':' . $timeTo[1],
				'tag' => $tag
			);
			if ($wpPost->post_author) {
				$userData = get_userdata($wpPost->post_author);
				$booking['user']['name'] = booked_get_name($wpPost->post_author);
				$booking['user']['email'] = $userData->user_email;
				$booking['user']['phone'] = get_user_meta($wpPost->post_author, 'booked_phone', true);
			}
			$booking['user']['name'] = get_post_meta($wpPost->ID, '_appointment_guest_name', true) ?: $booking['user']['name'];
			$booking['user']['email'] = get_post_meta($wpPost->ID, "_appointment_guest_email", true) ?: $booking['user']['email'];
		}
		return $booking;
	}

	public function getPageTabKey() {
		return 'booked_plugin';
	}

	public function renderSettingsPage() {
		settings_fields(self::OPTION_GROUP);
		do_settings_sections(self::OPTION_GROUP);
	}
}