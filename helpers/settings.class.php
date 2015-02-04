<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Holds basic properties/functions relating the plugin's settings
 */
class WP_LIB_SETTINGS {
	/**
	 * Single instance of core plugin class
	 * @var WP_LIBRARIAN
	 */
	protected $wp_librarian;
	
	/**
	 * All settings WP-Librarian is responsible for
	 * @var array
	 */
	private static $plugin_settings = array(
		/* -- Library Options -- */
		/* Settings relating to loaning/returning systems */
		
		// Loan length in days
		array(
			'key'		=> 'wp_lib_loan_length',
			'default'	=> array(12)
		),
		
		// Maximum number of times an item can be renewed while on loan
		array(
			'key'		=> 'wp_lib_renew_limit',
			'defualt'	=> array(2)
		),
		
		// Fine length (per day)
		array(
			'key'		=> 'wp_lib_fine_daily',
			'default'	=> array(0.20)
		),
		/* -- Slugs -- */
		/* Sections of site urls used when accessing plugin pages e.g. my-site.com/wp-librarian */
		
		array(
			'key' 		=> 'wp_lib_slugs',
			'default'	=> array(
				'wp-librarian',	// The slug for library items
				'item', 		// The sub-slug for single items e.g. library/item/moby-dick
				'author',		// The slug for viewing authors
				'type'			// The slug for viewing media types
			)
		),
		
		/* -- Formatting -- */
		/* Settings relating to plugin presentation */
		
		// Currency Symbol and position relative to the numerical value (2 - Before: £20, 3 - After: 20£)
		array(
			'key' 		=> 'wp_lib_currency',
			'default'	=> array('&pound;',2)
		),
		
		/* -- Dashboard -- */
		/* Settings relating to the Library Dashboard */
		
		// Whether to automatically search for an item with the given barcode when the given length is reached
		array(
			'key'		=> 'wp_lib_barcode_config',
			'default'	=> array(
				false,
				8
			)
		)
	);
	
	/**
	 * Adds instance of core plugin class to settings classes' properties
	 * @param WP_LIBRARIAN $wp_librarian Single instance of core plugin class
	 */
	function __construct( WP_LIBRARIAN $wp_librarian ) {
		$this->wp_librarian = $wp_librarian;
	}
	
	/**
	 * Adds all plugin default options. If a setting already exists it is ignored
	 */
	public function addPluginSettings() {
		foreach ( self::$plugin_settings as $setting ) {
			add_option( $setting['key'], $setting['default'] );
		}
	}
	
	/**
	 * Deletes all plugin options, as specified in $plugin_settings
	 * As the plugin relies on these options to function, be wary when using this function
	 */
	public function purgePluginSettings() {
		foreach ( self::$plugin_settings as $setting ) {
			delete_option( $setting['key'], $setting['default'] );
		}
	}
	
	/**
	 * Checks if all options controlled by plugin exist. Creates option if one does not
	 */
	public function checkPluginSettingsIntegrity() {
		foreach ( self::$plugin_settings as $setting ) {
			$setting_value = get_option( $setting['key'], false );
			
			// If setting doesn't exist or is invalid, resets to default value
			if ( !is_array( $setting_value ) ) {
				update_option( $setting['key'], $setting['default'] );
			}
		}
	}
	
	/**
	 * Checks if given options key is one controlled by this plugin
	 * @param	string	$settings_key	WordPress settings key
	 * @return	bool					If the option key matched a plugin key
	 */
	public function isValidSettingKey( $settings_key ) {
		foreach ( self::$plugin_settings as $setting ) {
			if ( $settings_key === $setting['key'] ) {
				return true;
			}
		}
		return false;
	}
}

/**
 * A helper class for registering settings, generating settings sections and rendering settings fields
 */
class WP_LIB_SETTINGS_SECTION extends WP_LIB_SETTINGS {
	/**
	 * Registers settings section and all child settings and their child settings fields
	 * @param WP_LIBRARIAN	$wp_librarian	Single instance of core plugin class
	 * @param array			$section		Settings section to be registered
	 */
	function __construct( WP_LIBRARIAN $wp_librarian, array $section ) {
		// Sets core plugin class as function property
		parent::__construct( $wp_librarian );
		
		// If header to render section description is not provided, passes dummy callback
		if ( !isset( $section['callback'] ) )
			$section['callback'] = false;
		
		// Registers setting section
		add_settings_section( $section['name'], $section['title'], $section['callback'], $section['page'] );
		
		// Iterates over settings, generating fields for each settings' fields 
		foreach( $section['settings'] as $setting ) {
			// Skips registering any settings that are listed as a WP-Librarian setting
			if ( !$this->isValidSettingKey( $setting['name'] ) )
				break;
			
			// Registers setting section's page
			// Uses sanitization callback specific to current setting, if one exists, defaults to section's sanitization callback
			register_setting( $section['name'], $setting['name'], ( isset( $setting['sanitize'] ) ? $setting['sanitize'] : $section['sanitize'] ) );
			
			// If undefined, initialises setting level classes
			if ( !isset( $setting['classes'] ) )
				$setting['classes'] = array();
			
			// Iterates over setting's fields, registering them
			foreach ( $setting['fields'] as $position => $field ) {
				// Initialises field args, if necessary
				if ( !isset( $field['args'] ) )
					$field['args'] = array();
				
				// Adds setting name to arguments to be passed to field rendering callback
				$field['args']['setting_name'] = $setting['name'];
				
				// Iterates over field parameters which can be defined at a setting or field level, applying setting level prams to fields
				// Setting level param will only be inherited if the field hasn't its own specified param
				foreach ( ['field_type'] as $param ) {
					if ( !isset($field[$param]) && isset( $setting[$param] ) )
						$field[$param] = $setting[$param];
				}
				
				// Iterates over field args which can be defined at a setting or field level, applying setting level prams to fields
				foreach ( ['html_filter'] as $arg ) {
					if ( isset( $setting[$arg] ) )
						$field['args'][$arg] = $setting[$arg];
				}
				
				// If undefined, initialises field level classes
				if ( !isset( $field['args']['classes'] ) )
					$field['args']['classes'] = array();
				
				// Merges parent (setting) classes into child (field)
				$field['args']['classes'] = array_merge( $field['args']['classes'], $setting['classes'] );
				
				// Prepares callback to render field for use by WordPress by adding class name
				$field['field_type'] = array( $this, $field['field_type'] );
				
				// Adds field position in setting array to field args
				$field['args']['position'] = $position;
				
				// Registers setting field to setting
				// Passes callback to render field using specific callback for this field, if one exists. Falls back to setting's field rendering callback
				add_settings_field( $setting['name'] . '[' . $position . ']' , $field['name'], $field['field_type'], $section['page'], $section['name'], $field['args'] );
			}
		}
	}
	
	/**
	 * If settings field has a description field specified, formats as paragraph
	 * @param	array	$output	An array of strings of HTML
	 * @param	array	$args	Settings field arguments
	 */
	private function addDescription( array &$output, array $args ) {
		if ( isset( $args['alt'] ) )
			$output[] = '<p class="tooltip description">' . $args['alt'] . '</p>';
	}
	
	/**
	 * Gets WordPress option, selects settings field then applies any given filters to it
	 * @param	array $args	Settings field arguments
	 * @return	mixed		Settings field's value
	 */
	private function getOption( array $args ) {
		// Fetches option value from database. Uses false if option does not exist
		$option = get_option( $args['setting_name'], false );
		
		// If option does not exist or is invalid, attempts to fix settings then calls error
		if ( !is_array( $option ) ) {
			$this->checkPluginSettingsIntegrity();
			wp_lib_error( 114 );
		}
		
		// Fetches field value from option array
		if ( isset( $option[$args['position']] ) )
			$option = $option[$args['position']];
		else
			wp_lib_error( 115 );
		
		// If filter exists, to prep the option for field display, filters
		if ( isset( $args['filter'] ) )
			$option = $args['filter']( $option, $args );
		
		return $option;
	}
	
	/**
	 * Combines given properties with default settings field properties and formats as HTML element properties
	 * @param	array	$args		Settings field arguments
	 * @param	array	$add_prop	Additional properties for the settings field input/select element
	 * @return	string				Settings field HTML element properties
	 */
	private function setupFieldProperties( array $args, array $add_prop ) {
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
				'class'	=> implode( ' ', $classes ),
				'name'	=> $args['setting_name'] . '[' . $args['position'] . ']',
				'id'	=> $args['setting_name'] . '[' . $args['position'] . ']'
			)
		);
		
		// Initialises element properties
		$properties = '';
		
		// Iterates over properties, formatting html element properties as a string
		foreach( $prop_array as $key => $value ) {
			$properties .= $key . '="' . $value . '" ';
		}
		
		return $properties;
	}
	
	/**
	 * Renders an HTML text input field
	 * @param	array	$args	Settings field arguments
	 */
	public function textInput( array $args ) {
		$properties = array(
			'type' => 'text',
			'value'=> $this->getOption( $args )
		);
		
		// Sets field output
		$output = array(
			'<input ' . $this->setupFieldProperties( $args, $properties ) . '/>'
		);
		
		// Adds field description, if one exists
		$this->addDescription( $output, $args );
		
		// If hook exists to add html elements to the output, apply
		if ( isset( $args['html_filter'] ) )
			$output = $args['html_filter']( $output, $args );
		
		// Renders output to setting field
		$this->outputLines( $output );
	}
	
	/**
	 * Renders an HTML checkbox input
	 * @param	array	$args	Settings field arguments
	 */
	public function checkboxInput( array $args ) {
		$properties = array(
			'type'	=> 'checkbox',
			'value'	=> 3
		);
		
		if ( $this->getOption( $args ) == 3 )
			$properties['checked'] = 'checked';
		
		// Sets field output
		$output = array(
			'<input ' . $this->setupFieldProperties( $args, $properties ) . '/>'
		);
		
		// Adds field description, if one exists
		$this->addDescription( $output, $args );
		
		// If hook exists to add html elements to the output, apply
		if ( isset( $args['html_filter'] ) )
			$output = $args['html_filter']( $output, $args );
		
		// Renders output to setting field
		$this->outputLines( $output );
	}
	
	/**
	 * Echoes an array of strings of HTML
	 * @param	array	$lines	An array of strings of HTML
	 */
	private function outputLines( array $lines ) {
		foreach ( $lines as $line ) {
			echo $line;
		}
	}
}
?>