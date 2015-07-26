<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Holds basic properties/functions relating the plugin's settings
 */
class WP_Lib_Settings {
	/**
	 * All settings WP-Librarian is responsible for
	 * @var array
	 */
	public static $plugin_settings = array(
		/* -- Library Options -- */
		/* Settings relating to loaning/returning systems */
		
		// Loan length in days
		'wp_lib_loan_length'    => array(12),
		
		// Maximum number of times an item can be renewed while on loan
		'wp_lib_renew_limit'    => array(2),
		
		// Fine length (per day)
		'wp_lib_fine_daily'     => array(0.20),
		
		/* -- Slugs -- */
		/* Sections of site urls used when accessing plugin pages e.g. my-site.com/wp-librarian */
		
		'wp_lib_slugs'          => array(
			'wp-librarian', // The slug for library items
			'item',         // The sub-slug for single items e.g. library/item/moby-dick
			'author',       // The slug for viewing authors
			'type'          // The slug for viewing media types
		),
		
		/* -- Formatting -- */
		/* Settings relating to plugin presentation */
		
		// Currency Symbol and position relative to the numerical value (2 - Before: £20, 3 - After: 20£)
		'wp_lib_currency'   => array('&pound;', 2),
	);
	
	/**
	 * Adds all plugin default options. If a setting already exists it is ignored
	 */
	public static function addPluginSettings() {
		foreach (self::$plugin_settings as $setting) {
			add_option($setting['key'], $setting['default']);
		}
	}
	
	/**
	 * Deletes all plugin options, as specified in $plugin_settings
	 * As the plugin relies on these options to function, be wary when using this function
	 */
	public static function purgePluginSettings() {
		foreach (self::$plugin_settings as $key => $default) {
			delete_option($key, $default);
		}
	}
	
	/**
	 * Checks if all options controlled by plugin exist. Creates option if one does not
	 */
	public static function checkPluginSettingsIntegrity() {
		foreach (self::$plugin_settings as $key => $default) {
			$setting_value = get_option($key, false);
			
			// If setting doesn't exist or is invalid, resets to default value
			if (!is_array($setting_value)) {
				update_option($key, $default);
			}
		}
	}
	
	/**
	 * Checks if given options key is one controlled by this plugin
	 * @param   string  $settings_key   WordPress settings key
	 * @return  bool                    If the option key matched a plugin key
	 */
	public static function isValidSettingKey($settings_key) {
		if (isset(self::$plugin_settings[$settings_key]))
			return true;
		else
			return false;
	}
}

/**
 * A helper class for registering settings, generating settings sections and rendering settings fields
 */
class WP_Lib_Settings_Section extends WP_Lib_Settings {
	/**
	 * Registers settings section and all child settings and their child settings fields
	 * @param array         $section        Settings section to be registered
	 */
	public static function registerSection(array $section) {
		// If header to render section description is not provided, passes dummy callback
		if (!isset($section['callback']))
			$section['callback'] = false;
		
		// Settings sections displayed as library settings page tabs don't need to specify a page
		if (!isset($section['page']))
			$section['page'] = $section['name'] . '-options';
		
		add_settings_section($section['name'], $section['title'], $section['callback'], $section['page']);
		
		// Registers each setting and its respective fields
		foreach($section['settings'] as $setting) {
			// Skips registering any settings that aren't in $plugin_settings
			if (!self::isValidSettingKey($setting['name']))
				break;
			
			// Setting sanitization callback defaults to section callback if setting doesn't have one
			register_setting($section['name'], $setting['name'], (isset($setting['sanitize']) ? $setting['sanitize'] : $section['sanitize']));
			
			// Initialises if necessary
			$setting['classes'] = isset($setting['classes']) ? $setting['classes'] : array();
			
			// Registers setting's fields
			foreach ($setting['fields'] as $position => $field) {
				// Initialises if necessary
				$field['args'] = isset($field['args']) ? $field['args'] : array();
				
				// Adds setting name to arguments to be passed to field rendering callback
				$field['args']['setting_name'] = $setting['name'];
				
				// Iterates over field parameters which can be defined at a setting or field level, applying setting level prams to fields
				// Setting level param will only be inherited if the field hasn't its own specified param
				foreach (['field_type'] as $param) {
					if (!isset($field[$param]) && isset($setting[$param]))
						$field[$param] = $setting[$param];
				}
				
				// Iterates over field args which can be defined at a setting or field level, applying setting level prams to fields
				foreach (['html_filter'] as $arg) {
					if (isset($setting[$arg]))
						$field['args'][$arg] = $setting[$arg];
				}
				
				// Initialises if necessary
				$field['args']['classes'] = isset($field['args']['classes']) ? $field['args']['classes'] : array();
				
				// Merges parent (setting) classes into child (field)
				$field['args']['classes'] = array_merge($field['args']['classes'], $setting['classes']);
				
				// Prepares callback to render field for use by WordPress by adding class name
				$field['field_type'] = array(__CLASS__, $field['field_type']);
				
				// Adds field position in setting array to field args
				$field['args']['position'] = $position;
				
				// Registers setting field to setting
				// Passes callback to render field using specific callback for this field, if one exists. Falls back to setting's field rendering callback
				add_settings_field($setting['name'] . '[' . $position . ']', $field['name'], $field['field_type'], $section['page'], $section['name'], $field['args']);
			}
		}
	}
	
	/**
	 * Formats settings field description as a paragraph if one was specified
	 * @param   array   $output An array of strings of HTML
	 * @param   array   $args   Settings field arguments
	 */
	private static function addDescription(array &$output, array $args) {
		if (isset($args['alt']))
			$output[] = '<p class="tooltip description">' . $args['alt'] . '</p>';
	}
	
	/**
	 * Gets WordPress option, selects settings field then applies any given filters to it
	 * @param   array $args Settings field arguments
	 * @return  mixed       Settings field's value
	 */
	private static function getOption(array $args) {
		// Fetches option value from database. Uses false if option does not exist
		$option = get_option($args['setting_name'], false);
		
		// If option does not exist or is invalid, attempts to fix settings then calls error
		if (!is_array($option)) {
			self::checkPluginSettingsIntegrity();
			wp_lib_error(114);
		}
		
		// Fetches field value from option array
		if (isset($option[$args['position']]))
			$option = $option[$args['position']];
		else
			wp_lib_error(115);
		
		// If filter exists, to prep the option for field display, filters
		if (isset($args['filter']))
			$option = $args['filter']($option, $args);
		
		return $option;
	}
	
	/**
	 * Combines given properties with default settings field properties and formats as HTML element properties
	 * @param   array   $args       Settings field arguments
	 * @param   array   $add_prop   Additional properties for the settings field input/select element
	 * @return  string              Settings field HTML element properties
	 */
	private static function setupFieldProperties(array $args, array $add_prop) {
		// Merges given field classes with default field classes
		$classes = array_merge(
			$args['classes'],
			array(
				'setting-' . $args['setting_name']
			)
		);
		
		// Merges additional field properties with default field properties
		$prop_array = array_merge(
			$add_prop,
			array(
				'class' => implode(' ', $classes),
				'name'  => $args['setting_name'] . '[' . $args['position'] . ']',
				'id'    => $args['setting_name'] . '[' . $args['position'] . ']'
			)
		);
		
		// Initialises element properties
		$properties = '';
		
		// Iterates over properties, formatting html element properties as a string
		foreach($prop_array as $key => $value) {
			$properties .= $key . '="' . $value . '" ';
		}
		
		return $properties;
	}
	
	/**
	 * Renders an HTML text input field
	 * @param   array   $args   Settings field arguments
	 */
	public static function textInput(array $args) {
		$properties = array(
			'type' => 'text',
			'value'=> self::getOption($args)
		);
		
		// Sets field output
		$output = array(
			'<input ' . self::setupFieldProperties($args, $properties) . '/>'
		);
		
		// Adds field description, if one exists
		self::addDescription($output, $args);
		
		// If hook exists to add html elements to the output, apply
		if (isset($args['html_filter']))
			$output = $args['html_filter']($output, $args);
		
		// Renders output to setting field
		self::outputLines($output);
	}
	
	/**
	 * Renders an HTML checkbox input
	 * @param   array   $args   Settings field arguments
	 */
	public static function checkboxInput(array $args) {
		$properties = array(
			'type'  => 'checkbox',
			'value' => 3
		);
		
		if (self::getOption($args) == 3)
			$properties['checked'] = 'checked';
		
		// Sets field output
		$output = array(
			'<input ' . self::setupFieldProperties($args, $properties) . '/>'
		);
		
		// Adds field description, if one exists
		self::addDescription($output, $args);
		
		// If hook exists to add html elements to the output, apply
		if (isset($args['html_filter']))
			$output = $args['html_filter']($output, $args);
		
		// Renders output to setting field
		self::outputLines($output);
	}
	
	/**
	 * Echoes an array of strings of HTML
	 * @param   array   $lines  An array of strings of HTML
	 */
	private static function outputLines(array $lines) {
		foreach ($lines as $line) {
			echo $line;
		}
	}
}
