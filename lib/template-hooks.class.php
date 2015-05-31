<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Manages item post template functionality
 */
class WP_LIB_TEMPLATE_HOOKS {
	/**
	 * Single instance of core plugin class
	 * @var WP_LIBRARIAN
	 */
	public $wp_librarian;
	
	/**
	 * Registers hooks that allow templates to access functions of relevant classes
	 * @param   WP_LIBRARIAN    $wp_librarian   Single instance of core plugin class
	 */
	public function __construct(WP_LIBRARIAN $wp_librarian) {
		$this->wp_librarian = $wp_librarian;
		
		$wp_librarian->loadClass('library-object');
		$wp_librarian->loadClass('item');
		$wp_librarian->loadClass('loan');
		
		$this->registerHooks();
	}
	
	/**
	 * Registers hooks that templates can call
	 */
	protected function registerHooks() {
		add_action('wp_lib_display_item_meta', array($this, 'DisplayItemMeta'));
	}
	
	/**
	 * Displays item's meta details as a table
	 * Note this is different from the Dashboard meta boxes
	 */
	public function DisplayItemMeta() {
		// Fetches WP_POST object
		$post = get_post();
		
		// Creates instance of library item from post ID
		$item = WP_LIB_ITEM::create($this->wp_librarian, $post->ID);
		
		// If user is librarian (or higher), or if the donor is set to be displayed, fetches item donor
		// If user isn't a librarian, or there is no listed donor, returns false
		$donor_id = (wp_lib_is_librarian() || get_post_meta($item->ID, 'wp_lib_display_donor', true) ? get_post_meta($item->ID, 'wp_lib_item_donor', true) : false);
		
		// If donor ID belongs to a valid donor, fetch donor's name
		$donor = (is_numeric($donor_id) ? get_the_title($donor_id) : false);
		
		// Creates array of raw item meta
		$raw_meta = array(
			array('Title',      get_the_title($item->ID)),
			array('Media Type', get_the_terms($item->ID, 'wp_lib_media_type')),
			array('Author',     get_the_terms($item->ID, 'wp_lib_author')),
			array('Donor',      $donor),
			array('ISBN',       get_post_meta($item->ID, 'wp_lib_item_isbn', true)),
			array('Status',     $item->formattedStatus())
		);
		
		// If item is being displayed in an archive, item title is link to view single item
		if (is_archive())
			$raw_meta[0][2] = get_permalink($item->ID);
		
		// Initialises formatted meta output
		$meta_output = array();
		
		// Iterates over raw taxonomy 
		foreach ($raw_meta as $key => $meta) {
			// If meta value is a tax term
			if (is_array($meta[1])) {
				// Initilises output for tax terms
				$tax_terms_output = array();
				
				// Iterates through tax terms
				foreach ($meta[1] as $tax_key => $tax_term) {
					// Gets tax term's URL
					$tax_url = get_term_link($tax_term);
					
					// Deletes term if error occurred
					if (is_wp_error($tax_url))
						continue;
					
					// Formats tax item as link
					$tax_terms_output[] = '<a href="' . esc_url($tax_url) . '">' . $tax_term->name . '</a>';
				}
				
				// Overwrites tax term objects with formatted tax terms
				$meta[1] = $tax_terms_output;
				
				// Counts number of valid tax terms
				$count = count($meta[1]);
				
				// If all tax terms were invalid, remove meta value
				if ($count === 0) {
					unset($tax_array[$key]);
					continue;
				// If there is one than one of a taxonomy item it makes the term plural (Author -> Authors)
				} elseif ($count > 1) {
					$meta[0] .= 's';
				}
				
				// Implodes array into string of comma separated taxonomy terms
				$meta[1] = implode(', ', $meta[1]);
			}
			
			// If output is a string with a URL, create hyperlink
			if (isset($meta[2]))
				$meta[1] = '<a href="' . $meta[2] . '">' . $meta[1] . '</a>';
			
			// If meta output is valid, add to output
			if ($meta[1] !== false && $meta[1] !== '')
				$meta_output[] = $meta;
		}
		
		// Allows users to modify item meta output
		$meta_output = apply_filters('wp_lib_item_meta', $meta_output, $post, $item);
		
		// If there are any remaining valid meta fields
		if (count($meta_output) > 0) {
			// Renders description list
			echo '<table class="item-metabox"><tbody>';
			
			// Iterates over meta fields, rendering them to details list
			foreach ($meta_output as $meta_field) {
				echo '<tr class="meta-row"><th>' . $meta_field[0] . ':</th><td>' . $meta_field[1] . '</td></tr>';
			}
			
			// Ends Description list
			echo '</tbody></table>';
		}
	}
}
