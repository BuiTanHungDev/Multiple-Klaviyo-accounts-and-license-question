<?php

GFForms::include_feed_addon_framework();

class GFKlaviyoAPI extends GFFeedAddOn {

	protected $_version = GF_KLAVIYO_API_VERSION;
	protected $_min_gravityforms_version = '2.4';
	protected $_slug = 'klaviyoaddon';
	protected $_path = 'klaviyoaddon/klaviyoaddon.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Klaviyo Feed Add-On';
	protected $_short_title = 'Klaviyo';
	protected $matches = '';
	private static $_instance = null;

	private $gravityextra;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFKlaviyoAPI
	 */
	public static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new GFKlaviyoAPI();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {
		require_once('includes/gravity-extra-license.php');
		$addon_args = array(
			'_slug' => $this->_slug,
			'_path' => $this->_path,
			'_title' => $this->_title,
			'_short_title' => $this->_short_title
		);
		$this->gravityextra = new GravityExtra_KlaviyoAddon\License($addon_args, $this->is_gravityforms_supported('2.5-beta'));
		parent::init();

		$url = 'https://gravityextra.com/addon-version/';
		$curl = curl_init();
		$timeout = 5;
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($curl);
		curl_close($curl);
		preg_match('/<td\s+id="klaviyo-addon">([^<]+)<\/td>/', $data, $this->matches);
		add_action('load-plugins.php', function () {
			add_filter('site_transient_update_plugins', array($this, 'update_plugins_klaviyo_addon'));
		});
	}

	// Notify update plugin
	function update_plugins_klaviyo_addon($transient) {
		$plugin_path = plugin_dir_path(__FILE__);
		$plugin_directory_name = basename($plugin_path);
		$slug                   = $plugin_directory_name;
		$plugin_name            = $plugin_directory_name . '/klaviyoaddon.php';
		$plugin_data            = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_name);
		$plugin_current_version = $plugin_data['Version'];

		$new_version = '';
		if (!empty($this->matches)) {
			$new_version = $this->matches[1];
		}

		$package = 'https://gravityextra.com/gf-product-update/' . $plugin_directory_name . '-v-' . $new_version . '.zip';
		if (!$this->gravityextra->is_valid_license()) {
			$package = '';
		}

		$update = (object)[
			'url' => 'https://gravityextra.com/gravity-forms-klaviyo-premium-addon/',
			'plugin' => $plugin_name,
			'package' => $package,
			'new_version' => $new_version,
			'id' => $plugin_name,
		];
		if (isset($transient->response) && is_array($transient->response) && version_compare($plugin_current_version, $new_version, '<')) {
			$transient->response[$plugin_name] = $update;
		} else if (isset($transient->no_update) && is_array($transient->no_update)) {
			$transient->no_update[$plugin_name] = $update;
		}
		return $transient;
	}

	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed($feed, $entry, $form) {
		if ($this->gravityextra->is_valid_license()) {
			$list_active = $this->get_generic_map_fields($feed, 'genericLists');
			$contactStandard = $this->get_field_map_fields($feed, 'contactStandardFields');
			$metaData = $this->get_generic_map_fields($feed, 'metaData');
			$metaProperties = $this->get_dynamic_field_map_fields($feed, 'metaProperties');
			$contactStandard_value = $this->get_all_field_standard_value($form, $entry, $contactStandard);
			$metaData_value = $this->get_all_field_value($form, $entry, $metaData);
			$metaProperties_value = $this->get_all_field_value($form, $entry, $metaProperties);
			$track_event = !empty($feed['meta']['track_event']) ? $feed['meta']['track_event'] : 'GravityForm';
			$merge_vars = array_merge($contactStandard_value, $metaData_value, $metaProperties_value);
			$merge_vars['track_event'] = $track_event;
			$args = array(
				'contactStandard'	=> $contactStandard,
				'metaData'	=> $metaData,
				'metaProperties'	=> $metaProperties,
				'list_active'	=> $list_active,
				'contactStandard_value' 	=> $contactStandard_value,
				'metaData_value'	=> $metaData_value,
				'metaProperties_value'	=> $metaProperties_value,
				'merge_vars'	=> $merge_vars
			);

			$this->log_debug(__METHOD__ . '(): Data Feed =>' . print_r($args, true));

			if (empty($merge_vars['email']) ){
				$this->log_debug(__METHOD__ . '(): Fail! Email are required.');
				return;
			}

			$get_profile = $this->check_email_profile($merge_vars['email']);
			if ($get_profile == 'errors') {
				$this->log_debug(__METHOD__ . '(): An error occurred while querying the data, ending the data sending process.');
				return;
			}
			if (empty($get_profile)) {
				$profile = $this->create_profile($args);
				if (!empty($profile)) {
					$profile_id = $profile['id'];
					$this->update_profile_to_list($profile_id, $args);
					$this->update_consent($profile_id, $args);
				}
			}
			if (!empty($get_profile)) {
				$profile_id = $get_profile[0]['id'];
				$this->update_profile($profile_id, $args);
				$this->update_profile_to_list($profile_id, $args);
				$this->update_consent($profile_id, $args);
			}
		}
	}

	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'form_settings_klaviyo',
				'src'     => $this->get_base_url() . '/assets/js/form-settings.js',
				'version' => $this->_version,
				'deps'    => array('jquery'),
				'strings' => array(
					'is_gf_version_min_2_5' => GF_KLAVIYO_API::is_gf_version_min_2_5()
				),
				'enqueue' => array(
					array(
						'admin_page' => array('form_settings'),
						'tab'        => $this->_slug,
					),
				),
			),
		);
		return array_merge(parent::scripts(), $scripts);
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {

		$styles = array(
			array(
				'handle'  => 'admin_settings_klaviyo',
				'src'     => $this->get_base_url() . '/assets/css/admin-settings.css',
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array('form_settings'),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge(parent::styles(), $styles);
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		$license_section = $this->gravityextra->license_section();
		$arraysettingfields = array(
			'title'  => esc_html__('Insert your Klaviyo API keys below to connect. You can find them on your Klaviyo account page.', 'klaviyoaddon'),
			'fields' => array(
				array(
					'name'    => 'api_key',
					'label'   => esc_html__('Public API Key', 'klaviyoaddon'),
					'type'    => 'text',
					'class'   => 'small',
				),
				array(
					'name'    => 'private_api_key',
					'label'   => esc_html__('Private API Key', 'klaviyoaddon'),
					'type'    => 'text',
					'class'   => 'medium',
					'input_type'	=> 'password',
				),
			),
		);
		if ($this->gravityextra->is_valid_license()) {
			return array($license_section, $arraysettingfields);
		} else {
			return array($license_section);
		}
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Klaviyo area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		$settings_fields = array(
			'title'  => esc_html__('Klaviyo Feed Settings', 'klaviyoaddon'),
			'fields' => array(
				array(
					'label'   => esc_html__('Feed name', 'klaviyoaddon'),
					'type'    => 'text',
					'name'    => 'feedName',
					'class'   => 'small',
					'tooltip'  => '<h6>' . esc_html__('Name', 'klaviyoaddon') . '</h6>' . esc_html__('Enter a feed name to uniquely identify this setup.', 'klaviyoaddon')
				),
				array(
					'name'     => 'genericLists',
					'label'    => esc_html__('Add Klaviyo Lists', 'klaviyoaddon'),
					'type'      => 'generic_map',
					'key_field' => array(
						'title' => 'List Name',
						'type'  => 'select',
						'choices'  =>  $this->get_lists_klaviyo(),
						'allow_custom'	=> false,
					),
					'value_field'	=> array(
						'title'	=> 'Active Status',
						'type'	=> 'select',
						'choices'	=> array(
							array(
								'label'	=> 'Not Active',
								'value'	=> false
							),
							array(
								'label'	=>	'Active',
								'value'	=>	true
							),
						),
						'allow_custom'	=> false,
					),
					'tooltip'  => '<h6>' . esc_html__('Klaviyo List', 'klaviyoaddon') . '</h6>' . esc_html__('Select which Klaviyo list this feed will add contacts to.', 'klaviyoaddon')
				),
				// array(
				// 	'type'    => 'select',
				// 	'name'    => 'metric',
				// 	'label'   => esc_html__('Metric', 'klaviyoaddon'),
				// 	'choices' => $this->get_list_metrics(),
				// 	'tooltip'	=> esc_html__('Add metrics for profiles when creating or updating.', 'klaviyoaddon'),
				// ),
				array(
					'label' => esc_html__('Event Name', 'klaviyoaddon'),
					'name' => 'track_event',
					'type' => 'text',
					'tooltip'	=> esc_html__('Add metrics event. Default is "GravityForm" if left blank.', 'klaviyoaddon'),
				),
				array(
					'name'      => 'contactStandardFields',
					'label'     => esc_html__('Contact Standard', 'klaviyoaddon'),
					'type'      => 'field_map',
					'field_map' => array(
						array(
							'name'       => 'email',
							'label'      => esc_html__('Email', 'klaviyoaddon'),
							'required'   => true,
							'field_type' => array('email', 'hidden'),
						),
						array(
							'name'     => 'first_name',
							'label'    => esc_html__('First Name', 'klaviyoaddon'),
							'required' => false
						),
						array(
							'name'     => 'last_name',
							'label'    => esc_html__('Last Name', 'klaviyoaddon'),
							'required' => false
						),
						array(
							'name'      => 'email_consent',
							'label'     => esc_html__('Email Consent (True/False)', 'klaviyoaddon'),
							'tooltip'	=> esc_html__('Default True. The green check mark consent status is only updated when Opt-in Process sets the option to Single opt-in in List Settings.', 'klaviyoaddon'),
						),

					),
				),
				array(
					'name'      => 'metaData',
					'type'      => 'generic_map',
					'label'     => esc_html__('Map Special Fields', 'klaviyoaddon'),
					'key_field' => array(
						'title' => 'Klaviyo Field',
						'type'  => 'select',
						'choices'  => $this->list_properties_api(),
						'allow_custom'	=> false,
					),
					'value_field' => array(
						'title' => 'Value',
						'type'  => 'select',
						'choices'  => $this->get_all_field_map_choices(),
					),
				),
				array(
					'name'      => 'metaProperties',
					'label'     => esc_html__('Properties', 'klaviyoaddon'),
					'type'      => 'dynamic_field_map',
					'tooltip'	=> esc_html__('An object containing key/value pairs for any custom properties assigned to this profile', 'klaviyoaddon'),
				),
				array(
					'name'           => 'condition',
					'label'          => esc_html__('Condition', 'klaviyoaddon'),
					'type'           => 'feed_condition',
					'checkbox_label' => esc_html__('Enable Condition', 'klaviyoaddon'),
					'instructions'   => esc_html__('Process this feed if', 'klaviyoaddon'),
				),
			),
		);

		return array($settings_fields);
	}

	/**
	 * Get field map choices for specific form.
	 *
	 * @return array
	 */
	public function get_all_field_map_choices() {
		if (GF_KLAVIYO_API::is_gf_version_min_2_5()) {
			$form = $this->get_current_form();
			return $this->get_field_map_choices($form['id']);
		}
		return;
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__('Feed Name', 'klaviyoaddon'),
			'genericLists' => esc_html__('Klaviyo Lists', 'klaviyoaddon'),
		);
	}

	/**
	 * Custom feed colum value.
	 *
	 * @return string
	 */
	public function get_column_value_genericLists($feed) {
		$lists_form = rgars($feed, 'meta/genericLists');
		$validate_api = $this->validate_api();
		if (!$validate_api) return '';
		$lists = $this->get_lists_klaviyo();
		$list_label = array();
		foreach ($lists_form as $list_form) {
			$list_key = array_search($list_form['key'], array_column($lists, 'value'));
			if (!array_key_exists($list_key, $lists)) continue;
			$list_label[] = $lists[$list_key]['label'];
		}
		if (empty($list_label)) return 'No list is selected.';
		sort($list_label);
		return implode(', ', $list_label);
	}

	public function can_duplicate_feed($id) {
		return true;
	}

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		if ($this->gravityextra->is_valid_license() == false) return false;
		if ($this->validate_api() == false) return false;
		return true;
	}

	public function get_menu_icon() {
		return file_get_contents($this->get_base_path() . '/assets/images/klaviyo.svg');
	}


	/**
	 * Notify on feed if api key is invalid.
	 *
	 * @return bool|string
	 */
	public function feed_list_message() {
		$settings_label = sprintf(__('%s Settings', 'klaviyoaddon'), $this->get_short_title());
		$settings_link  = sprintf('<a href="%s">%s</a>', esc_url($this->get_plugin_settings_url()), $settings_label);
		if ($this->gravityextra->is_valid_license() == false) {
			return sprintf(__('Please activate the license key to use the full functionality of %s.', 'klaviyoaddon'), $settings_link);
		}
		if ($this->validate_api() == false) {
			return sprintf(__('Private API Key is not correct, please configure your %s.', 'klaviyoaddon'), $settings_link);
		}
		return false;
	}

	/**
	 * Set default list properties API.
	 *
	 * @return array
	 */
	public function list_properties_api() {
		$list_custom = array(
			array(
				'label' => 'Title',
				'value' => 'title'
			),
			array(
				'label' => 'Organization',
				'value'	=> 'organization'
			),
			array(
				'label' => 'External Id',
				'value'	=> 'external_id'
			),
			array(
				'label' => 'Image',
				'value'	=> 'image'
			),
			array(
				'label' => 'Phone Number (E.164 format)',
				'value' => 'phone_number'
			),
			array(
				'label' => 'SMS Consent (True/False And Phone Number require E.164 format)',
				'value'	=> 'sms_consent'
			),
			array(
				'label' => 'Address1',
				'value' => 'address1'
			),
			array(
				'label' => 'Address2',
				'value' => 'address2'
			),
			array(
				'label' => 'City',
				'value' => 'city'
			),
			array(
				'label' => 'Country',
				'value' => 'country'
			),
			array(
				'label' => 'Region',
				'value' => 'region'
			),
			array(
				'label' => 'Zip',
				'value' => 'zip'
			),
			array(
				'label' => 'Longitude',
				'value' => 'longitude'
			),
			array(
				'label' => 'Latitude',
				'value' => 'latitude'
			),
			array(
				'label' => 'Time Zone',
				'value' => 'timezone'
			),
		);
		return $list_custom;
	}

	public function validate_api() {
		$url = 'https://a.klaviyo.com/api/lists/';
		$responses = $this->request_api($url, 'GET');
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		if (!empty($responses['errors'])) {
			return false;
		}
		return true;
	}

	/**
	 * Get all List in Klaviyo.
	 *
	 * @return array
	 */
	public function get_lists_klaviyo() {
		$url = 'https://a.klaviyo.com/api/lists/';
		$lists = $this->get_list_klaviyo($url);
		$sort = array_column($lists, 'label');
		array_multisort($lists, SORT_ASC, $sort, SORT_STRING);
		return $lists;
	}

	public function get_list_klaviyo($url) {
		$lists = array();
		$responses = $this->request_api($url, 'GET');
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		if (!empty($responses['errors'])) {
			$this->log_debug(__METHOD__ . '(): Response data get list error =>' . print_r($responses['errors'], true));
			return $lists;
		}
		if (!empty($responses['data'])) {
			foreach ($responses['data'] as $list) {
				$lists[] = array(
					'label' => $list['attributes']['name'],
					'value' => $list['id'],
				);
			}
		}
		$next = rgars($responses, 'links/next');
		if (!empty($next)) {
			$list_page = $this->get_list_klaviyo($next);
			$lists = array_merge($lists, $list_page);
		}
		return $lists;
	}

	/**
	 * Get all Metric in Klaviyo.
	 *
	 * @return array
	 */
	public function get_list_metrics() {
		$url = 'https://a.klaviyo.com/api/metrics/';
		$responses = $this->request_api($url, 'GET');
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		if (!empty($responses['errors'])) {
			$this->log_debug(__METHOD__ . '(): Response data get metric error =>' . print_r($responses['errors'], true));
			return false;
		}
		$lists = array(
			array(
				'label' => esc_html__('Select Metric', 'klaviyoaddon'),
				'value' => '',
			)
		);
		if (!empty($responses['data'])) {
			foreach ($responses['data'] as $metric) {
				$lists[] = array(
					'label' => $metric['attributes']['name'],
					'value' => $metric['id'],
				);
			}
		}
		return $lists;
	}

	/**
	 * Get all Event in Klaviyo.
	 *
	 * @return array
	 */
	public function get_list_events() {
		$url = 'https://a.klaviyo.com/api/events/';
		$responses = $this->request_api($url, 'GET');
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		if (!empty($responses['errors'])) {
			$this->log_debug(__METHOD__ . '(): Response data get event error =>' . print_r($responses['errors'], true));
			return false;
		}
		if (!empty($responses['data'])) {
		}
		return false;
	}

	/**
	 * Get value pairs for all fields mapped.
	 *
	 * @return array
	 */
	public function get_all_field_standard_value($form, $entry, $list_id) {
		$data = array();
		foreach ($list_id as $name => $field_id) {
			$field = RGFormsModel::get_field($form, $field_id);
			if (isset($field) && isset($field['type'])) {
				if ($field['type'] == 'consent') {
					if ($name == 'email_consent' && $entry[$field_id] != "") {
						$data[$name] = '1';
					} else {
						$data[$name] = '';
					}
				} else {
					$data[$name] = $this->get_field_value($form, $entry, $field_id);
				}
			} else {
				$data[$name] = '';
			}
		}
		return $data;
	}
	public function get_all_field_value($form, $entry, $list_id) {
		$data = array();
		foreach ($list_id as $name => $field_id) {
			if ($name == 'email_consent') {
				$data[$name] = $entry[$field_id];
			} else {
				$data[$name] = $this->get_field_value($form, $entry, $field_id);
			}
		}
		return $data;
	}

	/**
	 * Check email profile.
	 *
	 * @return string|array
	 */
	public function check_email_profile($email) {
		$url = "https://a.klaviyo.com/api/profiles/?filter=equals(email,'{$email}')";
		$this->log_debug(__METHOD__ . '(): Start check email in profile.');
		$responses = $this->request_api($url, 'GET');
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		$this->log_debug(__METHOD__ . '(): Response data check email =>' . print_r($responses, true));
		if (!empty($responses['errors'])) {
			return 'errors';
		}
		return $responses['data'];
	}

	/**
	 * Create Profile.
	 *
	 * @return array
	 */
	public function create_profile($args) {
		extract($args);
		$location_key = array('address1', 'address2', 'city', 'country', 'region', 'zip', 'longitude', 'latitude', 'timezone');
		$location = array();
		foreach ($location_key as $key) {
			if (array_key_exists($key, $metaData_value)) {
				$location[$key] = $metaData_value[$key];
			} else {
				$location[$key] = null;
			}
		}
		$attributes = array(
			'email' => $merge_vars['email'],
			'first_name' => $merge_vars['first_name'],
		);
		$attributes['last_name'] = (!empty($merge_vars['last_name'])) ? $merge_vars['last_name'] : null;
		$attributes['phone_number'] = (!empty($merge_vars['phone_number'])) ? $merge_vars['phone_number'] : null;
		$attributes['external_id'] = (!empty($merge_vars['external_id'])) ? $merge_vars['external_id'] : null;
		$attributes['organization'] = (!empty($merge_vars['organization'])) ? $merge_vars['organization'] : null;
		$attributes['title'] = (!empty($merge_vars['title'])) ? $merge_vars['title'] : null;
		$attributes['image'] = (!empty($merge_vars['image'])) ? $merge_vars['image'] : null;
		$attributes['location'] = (!empty($location)) ? $location : null;
		$attributes['properties'] = (!empty($metaProperties_value)) ? $metaProperties_value : null;
		$url = 'https://a.klaviyo.com/api/profiles/';
		$body = array(
			'data' => array(
				'type' => 'profile',
				'attributes' => $attributes,
			),
		);
		$this->log_debug(__METHOD__ . '(): Start create profile.');
		$this->log_debug(__METHOD__ . '(): Data create profile =>' . print_r($body, true));
		$responses = $this->request_api($url, 'POST', $body);
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		$this->log_debug(__METHOD__ . '(): Response data create profile =>' . print_r($responses, true));
		if (!empty($responses['errors'])) {
			return false;
		}
		if (!empty($merge_vars['track_event'])) {
			$this->add_track_event($args, 'Create Profile');
		}
		return $responses['data'];
	}

	/**
	 * Update Profile.
	 *
	 * @return array
	 */
	public function update_profile($profile_id, $args) {
		extract($args);
		$location_key = array('address1', 'address2', 'city', 'country', 'region', 'zip', 'longitude', 'latitude', 'timezone');
		$location = array();
		foreach ($location_key as $key) {
			if (array_key_exists($key, $metaData_value)) {
				$location[$key] = $metaData_value[$key];
			} else {
				$location[$key] = null;
			}
		}
		$attributes = array(
			'first_name' => $merge_vars['first_name'],
		);
		$attributes['last_name'] = (!empty($merge_vars['last_name'])) ? $merge_vars['last_name'] : null;
		$attributes['phone_number'] = (!empty($merge_vars['phone_number'])) ? $merge_vars['phone_number'] : null;
		$attributes['external_id'] = (!empty($merge_vars['external_id'])) ? $merge_vars['external_id'] : null;
		$attributes['organization'] = (!empty($merge_vars['organization'])) ? $merge_vars['organization'] : null;
		$attributes['title'] = (!empty($merge_vars['title'])) ? $merge_vars['title'] : null;
		$attributes['image'] = (!empty($merge_vars['image'])) ? $merge_vars['image'] : null;
		$attributes['location'] = (!empty($location)) ? $location : null;
		$attributes['properties'] = (!empty($metaProperties_value)) ? $metaProperties_value : null;
		$url = 'https://a.klaviyo.com/api/profiles/' . $profile_id;
		$body = array(
			'data' => array(
				'type' => 'profile',
				'id'	=> $profile_id,
				'attributes' => $attributes,
			),
		);
		$this->log_debug(__METHOD__ . '(): Start update profile.');
		$this->log_debug(__METHOD__ . '(): Data update profile =>' . print_r($body, true));
		$responses = $this->request_api($url, 'PATCH', $body);
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		$this->log_debug(__METHOD__ . '(): Response data update profile =>' . print_r($responses, true));
		if (!empty($responses['errors'])) {
			return false;
		}
		if (!empty($merge_vars['track_event'])) {
			$this->add_track_event($args, 'Update Profile');
		}
		return $responses['data'];
	}

	/**
	 * Update Profile To List.
	 *
	 */
	public function update_profile_to_list($profile_id, $args) {
		extract($args);
		foreach ($list_active as $list_id => $status) {
			if (empty($list_id)) {
				$this->log_debug(__METHOD__ . '(): An error occurred while querying the data, ending the data sending process. List Id = NULL.');
				continue;
			}
			if (empty($status) || $status == 'false') {
				$this->log_debug(__METHOD__ . '(): Status Not Active in list ' . $list_id . '.');
				continue;
			}
			$url = "https://a.klaviyo.com/api/lists/{$list_id}/relationships/profiles/";
			$body = array(
				'data' => array(
					array(
						'type' => 'profile',
						'id' => $profile_id,
					)
				)
			);
			$this->log_debug(__METHOD__ . '(): Start add profile to list "' . $list_id . '".');
			$this->log_debug(__METHOD__ . '(): Data add profile to list "' . $list_id . '" =>' . print_r($body, true));
			$responses = $this->request_api($url, 'POST', $body);
			$responses = json_decode(wp_remote_retrieve_body($responses), true);
			if (!empty($responses['errors'])) {
				$this->log_debug(__METHOD__ . '(): Response data add profile to list "' . $list_id . '" fail" =>' . print_r($responses, true));
			}
			$this->log_debug(__METHOD__ . '(): Add profile to list "' . $list_id . '" success.');
		}
	}

	/**
	 * Update consent.
	 *
	 */
	public function update_consent($profile_id, $args) {
		extract($args);
		$email_consent = !empty($contactStandard['email_consent']) ? $merge_vars['email_consent'] : true;
		$sms_consent = !empty($merge_vars['sms_consent']) ? $merge_vars['sms_consent'] : null;
		$consent_enable = array('true', 'True', 'Yes', 'yes', 'Checked', 'checked', 'Selected', 'selected', 1);
		$consent_enable = apply_filters('klaviyoaddon_consent_value_default', $consent_enable);
		foreach ($list_active as $list_id => $status) {
			if (empty($list_id)) {
				$this->log_debug(__METHOD__ . '(): An error occurred while querying the data, ending the data sending process. List Id = NULL.');
				continue;
			}
			if ($status !== 'true') {
				$this->log_debug(__METHOD__ . '(): Status Not Active in list ' . $list_id . '.');
				continue;
			}

			$sub_attributes = array();
			$unsub_attributes = array();

			if (in_array($email_consent, $consent_enable)) {
				$sub_attributes['email'] = $merge_vars['email'];
				$sub_attributes['subscriptions']['email'] = array('marketing' => array('consent' => 'SUBSCRIBED'));
			} else {
				$unsub_attributes['email'] = $merge_vars['email'];
			}

			if (in_array($sms_consent, $consent_enable)) {
				$sub_attributes['phone_number'] = !empty($merge_vars['phone_number']) ? $merge_vars['phone_number'] : null;
				$sub_attributes['subscriptions']['sms'] = array('marketing' => array('consent' => 'SUBSCRIBED'));
			} else {
				$unsub_attributes['phone_number'] = !empty($merge_vars['phone_number']) ? $merge_vars['phone_number'] : null;
			}

			$sub_body = array(
				'data' => array(
					'type' => 'profile-subscription-bulk-create-job',
					'attributes' => array(
						'custom_source' => 'Marketing Event',
						'profiles' => array(
							'data' => array(
								array(
									'type' => 'profile',
									'id' => $profile_id,
									'attributes' => $sub_attributes,
								)
							)
						)
					),
					'relationships' => array(
						'list' => array(
							'data' => array(
								'type' => 'list',
								'id' => $list_id
							)
						)
					)
				),
			);

			$unsub_body = array(
				'data' => array(
					'type' => 'profile-subscription-bulk-delete-job',
					'attributes' => array(
						'profiles' => array(
							'data' => array(
								array(
									'type' => 'profile',
									'attributes' => $unsub_attributes,
								)
							)
						)
					),
					'relationships' => array(
						'list' => array(
							'data' => array(
								'type' => 'list',
								'id' => $list_id
							)
						)
					)
				)
			);

			if (!empty($sub_attributes['email']) || !empty($sub_attributes['phone_number'])) {
				$this->subscribe_profiles($sub_body, $list_id);
			}

			if (!empty($unsub_attributes['email']) || !empty($unsub_attributes['phone_number'])) {
				// $this->unsubscribe_profiles($unsub_body, $list_id);
			}
		}
	}

	/**
	 * Subscribe Profiles.
	 *
	 * @return array
	 */
	public function subscribe_profiles($body, $list_id) {
		$url = 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/';
		$this->log_debug(__METHOD__ . '(): Start subscribe profile in list "' . $list_id . '".');
		$this->log_debug(__METHOD__ . '(): Data subscribe profile in list "' . $list_id . '" =>' . print_r($body, true));
		$responses = $this->request_api($url, 'POST', $body);
		$result = array();
		$result['response'] = $responses['response'];
		$result['body'] = $responses['body'];
		$this->log_debug(__METHOD__ . '(): Response on subscribe profile request in list "' . $list_id . '" => ' . print_r($result, true));
		return $responses;
	}

	/**
	 * Unsubscribe Profiles.
	 *
	 * @return array
	 */
	public function unsubscribe_profiles($body, $list_id) {
		$url = 'https://a.klaviyo.com/api/profile-subscription-bulk-delete-jobs/';
		$this->log_debug(__METHOD__ . '(): Start unsubscribe profile in list "' . $list_id . '".');
		$this->log_debug(__METHOD__ . '(): Data unsubscribe profile in list "' . $list_id . '" =>' . print_r($body, true));
		$responses = $this->request_api($url, 'POST', $body);
		$result = array();
		$result['response'] = $responses['response'];
		$result['body'] = $responses['body'];
		$this->log_debug(__METHOD__ . '(): Response on unsubscribe profile request in list "' . $list_id . '" => ' . print_r($result, true));
		return $responses;
	}

	public function add_track_event($args, $method = '') {
		extract($args);
		$event_name = $merge_vars['track_event'];
		$api_endpoint = 'https://a.klaviyo.com/api/track';
		$body = array(
			'token' => $this->get_plugin_setting('private_api_key'),
			'event' => trim($event_name),
		);
		$customer_properties = array(
			'$email' => $merge_vars['email']
		);
		if (!empty($merge_vars['first_name'])) $customer_properties['$first_name'] = $merge_vars['first_name'];
		if (!empty($merge_vars['last_name'])) $customer_properties['$last_name'] = $merge_vars['last_name'];
		$body['customer_properties'] = $customer_properties;
		if (!empty($method)) {
			$body['properties'] = array(
				'Method' => $method,
			);
		}
		$this->log_debug(__METHOD__ . '(): Body on add track event => ' . print_r($body, true));
		$responses = $this->request_api($api_endpoint, 'POST', $body);
		$responses = json_decode(wp_remote_retrieve_body($responses), true);
		$this->log_debug(__METHOD__ . '(): Response on add track event => ' . print_r($responses, true));
		return $responses;
	}

	/**
	 * Send request to API.
	 *
	 * @return array
	 */
	public function request_api($url, $method, $body = array()) {
		if (empty($url) || empty($method)) return '';
		$request = array();
		$request['method'] = $method;
		$request['headers'] = array(
			'Authorization' => 'Klaviyo-API-Key ' . $this->get_plugin_setting('private_api_key'),
			'accept' => 'application/json',
			'content-type' => 'application/json',
			'revision' => '2024-02-15',
		);
		$request['timeout'] = 300;
		if (!empty($body)) $request['body'] = json_encode($body);
		$response = wp_safe_remote_post($url, $request);
		return $response;
	}
}
