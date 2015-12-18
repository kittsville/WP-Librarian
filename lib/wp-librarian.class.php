<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Core plugin class. Registers post types, creates pages and manages all central plugin functionality
 * @todo Define custom post type/taxonomy names as class constants
 */
class WP_Librarian {
	/**
	 * Paths to plugin's subdirectories
	 * Use these rather than hard-coding subdirectory names
	 */
	const SCRIPT_DIR        = 'scripts';
	const STYLE_DIR         = 'styles';
	const MINIFIED_DIR      = 'data';
	const CLASS_DIR         = 'lib';
	const TEMPLATE_DIR      = 'templates';
	const ADMIN_TEMPLATE_DIR= 'admin-templates';
	
	/**
	 * Path to plugin folder, without trailing slash
	 * @var string
	 */
	public $plugin_path;
	
	/**
	 * Public URL to plugin folder, without trailing slash
	 * @var string
	 */
	public $plugin_url;
	
	/**
	 * Sets up plugin
	 * @todo Consider merging some required files into this class
	 */
	public function __construct() {
		$this->plugin_path  = dirname(dirname(__FILE__));
		$this->plugin_url   = plugins_url('', dirname(__FILE__));
		
		// Registers functions to WordPress hooks
		$this->registerHooks();
		
		// Automatically loads error class
		$this->loadClass('error');
		
		// Loads various necessary libraries
		require_once($this->plugin_path . '/wp-librarian-helpers.php');
		$this->loadClass('admin-tables');
	}

	/**
	 * Registers nearly all context-regardless WordPress hooks needed for the plugin to work
	 * Hooks relating to admin post tables are registered by the admin tables class
	 * @see http://codex.wordpress.org/Plugin_API/Hooks
	 */
	private function registerHooks() {
		// Allows plugins to access this class
		add_action('plugins_loaded',                    function(){do_action('wp_lib_loaded', $this);});
		
		// Registers custom post types, taxonomies and settings sections used by the plugin
		add_action('init',                              array($this, 'registerPostAndTax'));
		add_action('init',                              array($this, 'registerScripts'));
		add_action('admin_init',                        array($this, 'registerSettings'));
		
		// Enqueues registered scripts and styles
		add_action('wp_enqueue_scripts',                array($this, 'enqueueScripts'));
		add_action('admin_enqueue_scripts',             array($this, 'enqueueAdminScripts'),        10, 1);
		
		// Renames edit items 'Featured Image' box title
		add_action('admin_head-post-new.php',           array($this, 'replaceFeaturedImageTitle'));
		add_action('admin_head-post.php',               array($this, 'replaceFeaturedImageTitle'));
		
		// Renames greyed out 'Title here' prompt text for item/member descriptions
		add_filter('enter_title_here',                  array($this, 'replaceNewPostPromptText'));
		
		// Adds custom post and tax updated messages
		add_filter('post_updated_messages',             array($this, 'replacePostUpdatedMessages'), 10, 1);
		add_filter('term_updated_messages',             array($this, 'replaceTaxUpdatedMessages'),  10, 1);
		
		
		// Adds custom rewrite rules, allowing for pretty front-end URLs for item archives
		add_filter('generate_rewrite_rules',            array($this, 'generateRewriteRules'));
		
		// Checks posts before they're deleted to maintain the integrity of the library
		add_action('before_delete_post',                array($this, 'checkPostPreTrash'));
		
		// User permissions
		add_action('show_user_profile',                 array($this, 'addProfilePermissionsField'), 10, 1);
-       add_action('edit_user_profile',                 array($this, 'addProfilePermissionsField'), 10, 1);
		add_action('personal_options_update',           array($this, 'updateUserPermissions'),      10, 1);
		add_action('edit_user_profile_update',          array($this, 'updateUserPermissions'),      10, 1);
		
		// Loads front-end templates for post archive/single pages from current theme, defaulting to plugin's templates 
		add_filter('template_include',                  array($this, 'locatePostTemplate'),         10, 1);
		
		// Hides items specifically excluded from the public archive
		add_action('pre_get_posts',                     array($this, 'hideDelistedItems'),          10, 1);
		
		// Updates item/member meta when post is updated
		add_action('save_post',                         array($this, 'updatePostMeta'),             10, 2);
		
		// Removes "Additional Capabilities" section from user profile page
		add_filter('additional_capabilities_display',   function(){ return false; });
		
		// Adds Dashboard and, if user has sufficient permissions, Settings pages
		add_action('admin_menu',                        array($this, 'registerAdminPages'));
		
		add_action('admin_menu',                        array($this, 'removeAdminMetaBoxes'));
		
		// Performs AJAX related functions
		add_action('wp_ajax_wp_lib_page',               array($this, 'ajaxLoadPage'));
		add_action('wp_ajax_wp_lib_action',             array($this, 'ajaxDoAction'));
		add_action('wp_ajax_wp_lib_api',                array($this, 'ajaxDoApiRequest'));
	}
	
	/**
	 * Registers all scripts and styles. Enqueuing is done later.
	 */
	public function registerScripts() {
		if (defined('DOING_AJAX') && DOING_AJAX) {
			return;
		}
		
		wp_register_script('wp_lib_meta_core',  $this->getScriptUrl('admin-meta-core'), array('jquery', 'jquery-ui-datepicker', 'wp_lib_core'), '0.3');
		wp_register_script('hyphenateISBN',     $this->getScriptUrl('hyphenateISBN'),   array(),                                                '0.1');
		wp_register_script('wp_lib_edit_item',  $this->getScriptUrl('admin-edit-item'), array('wp_lib_meta_core', 'hyphenateISBN'),             '0.3');
		wp_register_script('wp_lib_dashboard',  $this->getScriptUrl('admin-dashboard'), array('wp_lib_core'),                                   '0.4');
		wp_register_script('dynatable',         $this->getScriptUrl('dynatable'),       array(),                                                '0.3.1');
		wp_register_script('wp_lib_settings',   $this->getScriptUrl('AdminSettings'),   array('wp_lib_core'),                                   '0.4');
		wp_register_script('wp_lib_core',       $this->getScriptUrl('admin-core'),      array('jquery', 'jquery-ui-datepicker'),                '0.4');
		
		wp_register_style('wp_lib_admin_post_table',    $this->getStyleUrl('admin-post-table'), array(),                    '0.2');
		wp_register_style('wp_lib_admin_settings',      $this->getStyleUrl('admin-settings'),   array('wp_lib_core'),       '0.2');
		wp_register_style('wp_lib_dashboard',           $this->getStyleUrl('admin-dashboard'),  array('wp_lib_core'),       '0.4');
		wp_register_style('wp_lib_mellon_datepicker',   $this->getStyleUrl('mellon-datepicker'),array(),                    '0.1'); // Styles Datepicker
		wp_register_style('jquery-ui',                  $this->getStyleUrl('jquery-ui'),        array(),                    '1.10.1'); // Core Datepicker Styles
		wp_register_style('dynatable',                  $this->getStyleUrl('dynatable'),        array('jquery-ui'),         '0.3.1');
		wp_register_style('wp_lib_meta_core',           $this->getStyleUrl('admin-meta-core'),  array(),                    '0.2');
		wp_register_style('wp_lib_core',                $this->getStyleUrl('admin-core'),       array(),                    '0.4');
		wp_register_style('wp_lib_admin_edit_item',     $this->getStyleUrl('admin-edit-item'),  array('wp_lib_meta_core'),  '0.2');
		wp_register_style('wp_lib_frontend',            $this->getStyleUrl('front-end-core'),   array(),                    '0.3');
		
		// Sends array of useful variables to client-side
		wp_localize_script('wp_lib_core', 'wp_lib_vars', apply_filters('wp_lib_script_vars', array(
				'siteUrl'       => site_url(),
				'adminUrl'      => admin_url(),
				'pluginsUrl'    => $this->plugin_url,
				'dashUrl'       => wp_lib_format_dash_url(),
				'siteName'      => get_bloginfo('name'),
				'getParams'     => $_GET,
				'debugMode'     => WP_LIB_DEBUG_MODE
		)));
		
		do_action('wp_lib_register_scripts');
	}
	
	/**
	 * Loads library class from /lib directory
	 * @param   string  $helper Name of library to be loaded, excluding .class.php
	 */
	public function loadClass($library) {
		require_once($this->plugin_path . '/' . self::CLASS_DIR . '/' . $library . '.class.php');
	}
	
	/**
	 * Loads classes that handle library's objects (items, loans, etc.)
	 */
	public function loadObjectClasses() {
		foreach (array('library-object', 'item', 'member', 'loan', 'fine') as $object_class) {
			$this->loadClass($object_class);
		}
	}
	
	/**
	 * Given the name of an admin template file, loads
	 * @param   string  $name   File name, e.g. 'settings'
	 * @param   array   $params OPTIONAL Associative array of parameters for the template
	 */
	public function loadAdminTemplate($name, Array $params = array()) {
		require_once($this->plugin_path . '/' . self::ADMIN_TEMPLATE_DIR . '/' . $name . '.php');
	}
	
	/**
	 * Sets up plugin on first activation or after an update
	 */
	public function runOnActivation() {
		$old_version = get_option('wp_lib_version', false);
		$version     = $this->getPluginVersion();
		
		// If plugin has been previously installed
		if (is_array($old_version)) {
			// Stops older version than last version installed being installed
			if (version_compare($version['version'], $old_version['version'], '<')) {
				wp_lib_activation_error("Can't activate WP-Librarian version " . $version['version'] . " because more recent version " . $old_version['version'] . " has previously been installed");
			}
			
			// If previous plugin version is version 0.1 or earlier, remove depreciated data
			if (version_compare($old_version['version'], '0.1', '<=')) {
				delete_option('wp_lib_default_media_types');
				delete_option('wp_lib_taxonomy_spacer');
				
				// Deletes member post meta 'wp_lib_owed'
				$members_query = new WP_Query(array(
					'post_type'         => 'wp_lib_members',
					'post_status'       => 'publish',
					'nopaging'          => true,
					'meta_query'        => array(
						array(
							'key'           => 'wp_lib_owed',
							'compare'       => 'EXISTS'
						)
					)
				));
				
				if ($members_query->have_posts()){
					while ($members_query->have_posts()) {
						$members_query->the_post();
						
						delete_post_meta(get_the_ID(), 'wp_lib_owed');
					}
				}
				
				// Renames fine post meta 'wp_lib_fine' to 'wp_lib_owed'
				$fines_query = new WP_Query(array(
					'post_type'     => 'wp_lib_fines',
					'post_status'   => 'publish',
					'nopaging'      => true
				));
				
				if ($fines_query->have_posts()){
					while ($fines_query->have_posts()) {
						$fines_query->the_post();
						
						$fine_id = get_the_ID();
						
						$fine_amount = get_post_meta($fine_id, 'wp_lib_fine', true);
						
						delete_post_meta($fine_id, 'wp_lib_fine');
						
						update_post_meta($fine_id, 'wp_lib_owed', $fine_amount);
					}
				}
			}
			
			/**
			 * If previous plugin version is version 0.2 or earlier, update handling of currency by:
			 * 1. Updating fine payments stored in member meta
			 * 2. Updating fine amounts stored in fine meta
			 * Also:
			 * Removed deprecated barcode auto-scanning
			 */
			if (version_compare($old_version['version'], '0.2', '<=')) {
				$members = new WP_Query(array(
					'post_type'     => 'wp_lib_members',
					'post_status'   => 'publish',
					'nopaging'      => true
				));
				
				if ($members->have_posts()) {
					$member_ids = wp_list_pluck($members->posts, 'ID');
					
					foreach ($member_ids as $member_id) {
						$fine_payments = get_post_meta($member_id, 'wp_lib_payments');
						
						delete_post_meta($member_id, 'wp_lib_payments');
						
						foreach ($fine_payments as $fine_payment) {
							$fine_payment[1] = intval($fine_payment[1] * 100);
							
							add_post_meta($member_id, 'wp_lib_payments', $fine_payment);
						}
					}
				}
				
				$fines = new WP_Query(array(
					'post_type'     => 'wp_lib_fines',
					'post_status'   => 'publish',
					'nopaging'      => true
				));
				
				if ($fines->have_posts()) {
					$fine_ids = wp_list_pluck($fines->posts, 'ID');
					
					foreach ($fine_ids as $fine_id) {
						$fine_amount = floatval(get_post_meta($fine_id, 'wp_lib_owed', true));
						
						update_post_meta($fine_id, 'wp_lib_owed', intval($fine_amount * 100));
					}
				}
				
				// Removed deprecated barcode auto-scanning
				delete_option('wp_lib_barcode_config');
			}
		} else {
			// Sets current user as a Library Admin
			$this->updateUserPermissions(get_current_user_id(), 10);
			
			// Creates all settings plugin needs to run
			$this->loadClass('settings');
			WP_Lib_Settings::addPluginSettings();
			
			// Registers default media types
			foreach (array(
				array(
					'name' => 'Book',
					'slug' => 'books',
				),
				array(
					'name' => 'DVD',
					'slug' => 'dvds',
				),
				array(
					'name' => 'Graphic Novel',
					'slug' => 'graphic-novels',
				)
			) as $type) {
				if (get_term_by('name', $type['name'], 'wp_lib_media_type') == false){
					wp_insert_term(
						$type['name'],
						'wp_lib_media_type',
						array('slug' => $type['slug'])
					);
				}
			}
		}
		
		// Adds or updates plugins current version
		update_option('wp_lib_version', $version);
		
		// Registers plugin post types then flushes permalinks, adding them to rewrite rules
		$this->flushPermalinks();
	}
	
	/**
	 * Adds Library Role option to user profile page
	 * @param WP_User $user WP_User object of current user being edited
	 */
	public function addProfilePermissionsField($user) {
		if (!current_user_can('edit_users'))
			return;

		$roles = WP_Librarian::getUserRoles();

		// Fetches user's current roles
		$status = get_user_meta($user->ID, 'wp_lib_role', true);

		// If user has no role, role is 0
		if (!$status)
			$status = 1;

		// Adds nonce to section
		wp_nonce_field('Editing User: ' . $user->ID, 'wp_lib_edit_user_nonce');
		
		$this->loadAdminTemplate('edit-user', array($roles, $status));
	}
	
	/**
	 * Updates user's library role and capabilities
	 * @param   int         $user_id    ID of user to be updated
	 * @param   int|bool    $new_role   OPTIONAL New library role to assign user
	 * @see                             http://codex.wordpress.org/Roles_and_Capabilities
	 * @todo                            That 'new_role' check makes me feel dirty. There must be a better way of doing it.
	 */
	public function updateUserPermissions($user_id, $new_role = false) {
		// If new role wasn't specified, function has been called from user profile and nonce checking/sanitization is needed
		// Otherwise function has been called from plugin activation hook and new role will be passed directly to the function
		if (!$new_role) {
			// If user is not allowed to edit user meta or nonce fails, stops
			if (!current_user_can('edit_users') || !wp_verify_nonce($_POST['wp_lib_edit_user_nonce'], 'Editing User: ' . $user_id))
				return;
			
			$new_role = (int) isset($_POST['wp_lib_role']) ? $_POST['wp_lib_role'] :  0;
		}
		
		// Checks if given role exists
		if (!array_key_exists($new_role, $this->getUserRoles()))
			return;
		
		// Updates user's meta with new role
		update_user_meta($user_id, 'wp_lib_role', $new_role);
		
		// Fetches user object
		$user = new WP_User((int)$user_id);
		
		// Sets up all post types capacity headers. From these all capabilities relating to them are derived
		$post_cap_terms = array('wp_lib_items_cap', 'wp_lib_members_cap', 'wp_lib_loans_cap', 'wp_lib_fines_cap');
		
		// Iterates through custom post types, stripping user's capabilities to interact with them
		foreach($post_cap_terms as $term) {
			// Creates plural version of post type 
			$term_p = $term . 's';
			
			// Removes all general purpose capabilities
			$user->remove_cap('read_' . $term);
			$user->remove_cap('read_private_' . $term_p);
			$user->remove_cap('edit_' . $term);
			$user->remove_cap('edit_' . $term_p);
			$user->remove_cap('edit_others_' . $term_p);
			
			// Removes all item/member specific capabilities
			if ($term == 'wp_lib_items_cap' || $term == 'wp_lib_members_cap') {
				$user->remove_cap('edit_published_' . $term_p);
				$user->remove_cap('publish_' . $term_p);
				$user->remove_cap('delete_others_' . $term_p);
				$user->remove_cap('delete_private_' . $term_p);
				$user->remove_cap('delete_published_' . $term_p);
			}
		}
		
		// Removes capability to interact with tax terms
		$user->remove_cap('wp_lib_manage_taxs');
		
		// If new role has no capabilities, job is finished
		if ($new_role < 5)
			return;
		
		// Iterates through custom post types, adding capabilities to user
		foreach($post_cap_terms as $term) {
			// Creates plural version of post type 
			$term_p = $term . 's';
			
			// Adds all general purpose capabilities
			$user->add_cap('read_' . $term);
			$user->add_cap('read_private_' . $term_p);
			$user->add_cap('edit_' . $term);
			$user->add_cap('edit_' . $term_p);
			$user->add_cap('edit_others_' . $term_p);
			
			// Adds all item/member specific capabilities
			if ($term == 'wp_lib_items_cap' || $term == 'wp_lib_members_cap') {
				$user->add_cap('edit_published_' . $term_p);
				$user->add_cap('publish_' . $term_p);
				$user->add_cap('delete_others_' . $term_p);
				$user->add_cap('delete_private_' . $term_p);
				$user->add_cap('delete_published_' . $term_p);
			}
		}
		
		// Adds capability to interact with tax terms
		$user->add_cap('wp_lib_manage_taxs');
		
		// If role is not sufficient to have Library Admin caps, return
		if ($new_role < 10)
			return;
		
		// Adds capability to view settings page
		$user->add_cap('wp_lib_change_settings');
	}
	
	/**
	 * Updates item and member post meta on post save hook
	 * @param   int     $post_id    Post ID of current post being saved
	 * @param   post    $post       WP Post object
	 * @return  int     $post_id    Post ID of current post being saved
	 */
	public function updatePostMeta($post_id, $post) {
		// Check if the current user has permission to change a Library item's meta
		if (!wp_lib_is_librarian())
			return $post_id;
		
		// Loads meta to be updated based on post type
		switch ($post->post_type) {
			case 'wp_lib_items':
				// Verifies meta box nonce
				if (!isset($_POST['wp_lib_item_meta_nonce']) || !wp_verify_nonce($_POST['wp_lib_item_meta_nonce'], "Updating item {$post_id} meta"))
					return $post_id;
				
				// Stores all meta box fields and sanitization methods
				$meta_array = array(
					array(
						'key'       => 'wp_lib_item_isbn',
						'sanitize'  => 'wp_lib_sanitize_isbn'
					),
					array(
						'key'       => 'wp_lib_item_loanable',
						'sanitize'  => 'wp_lib_sanitize_checkbox'
					),
					array(
						'key'       => 'wp_lib_item_delist',
						'sanitize'  => 'wp_lib_sanitize_checkbox'
					),
					array(
						'key'       => 'wp_lib_display_donor',
						'sanitize'  => 'wp_lib_sanitize_checkbox'
					),
					array(
						'key'       => 'wp_lib_item_condition',
						'sanitize'  => 'wp_lib_sanitize_number'
					),
					array(
						'key'       => 'wp_lib_item_barcode',
						'sanitize'  => 'wp_lib_sanitize_number'
					),
					array(
						'key'       => 'wp_lib_item_cover_type',
						'sanitize'  => 'wp_lib_sanitize_number'
					),
					array(
						'key'       => 'wp_lib_media_type',
						'sanitize'  => 'sanitize_title',
						'tax'       => true
					),
					array(
						'key'       => 'wp_lib_item_donor',
						'sanitize'  => 'wp_lib_sanitize_donor'
					)
				);
			break;
			
			case 'wp_lib_members':
				// Verifies meta box nonce
				if (!isset($_POST['wp_lib_member_meta_nonce']) || !wp_verify_nonce($_POST['wp_lib_member_meta_nonce'], "Updating member {$post_id} meta"))
					return $post_id;
				
				// Stores all meta box fields and sanitization methods
				$meta_array = array(
					array(
						'key'       => 'wp_lib_member_phone',
						'sanitize'  => 'wp_lib_sanitize_phone_number'
					),
					array(
						'key'       => 'wp_lib_member_mobile',
						'sanitize'  => 'wp_lib_sanitize_phone_number'
					),
					array(
						'key'       => 'wp_lib_member_email',
						'sanitize'  => 'sanitize_email'
					),
					array(
						'key'       => 'wp_lib_member_archive',
						'sanitize'  => 'wp_lib_sanitize_checkbox'
					)
				);
			break;
			
			default:
				return;
		}
		
		// Iterates through each meta field, fetching and sanitizing it then saving/updating/deleting as appropriate
		foreach ($meta_array as $meta) {
			// Checks if sanitizing function exists
			if (!is_callable($meta['sanitize']))
				return $post_id;
		
			// Get the posted data and sanitize it for use as an HTML class
			$new_meta_value = (isset($_POST[$meta['key']]) ? $meta['sanitize']($_POST[$meta['key']]) : '');
			
			// If data is a taxonomy term and not a meta value
			if ($meta['tax']) {
				// If new meta value is not empty
				if ($new_meta_value) {
					// Attempts to fetch term from its respective taxonomy
					$term = get_term_by('slug', $new_meta_value, $meta['key']);
					
					// If term exists
					if ($term)
						// Updates post's term for that taxonomy
						wp_set_object_terms($post_id, $term->name, $meta['key']);
				} else {
					// Removes any term(s) attached to post for current taxonomy
					wp_delete_object_term_relationships($post_id, $meta['key']);
				}
				// Skips rest of current loop
				continue;
			}
			
			// Get the meta key
			$meta_key = $meta['key'];
			
			// Get the meta value of the custom field key
			$meta_value = get_post_meta($post_id, $meta_key, true);
			
			// If a new meta value was added and there was no previous value, add it
			if ($new_meta_value && '' == $meta_value)
				add_post_meta($post_id, $meta_key, $new_meta_value, true);

			// If the new meta value does not match the old value, update it
			elseif ($new_meta_value && $new_meta_value != $meta_value)
				update_post_meta($post_id, $meta_key, $new_meta_value);

			// If there is no new meta value but an old value exists, delete it
			elseif ('' == $new_meta_value && $meta_value)
				delete_post_meta($post_id, $meta_key, $meta_value);
		}
	}
	
	/**
	 * Registers all custom post types and taxonomies used by WordPress
	 * @todo Move default media type registration to plugin activation
	 */
	public function registerPostAndTax() {
		// Fetches slugs
		$slugs = get_option('wp_lib_slugs', array('wp-librarian','item','authors','type'));
		
		/**
		 * Registers custom post type items
		 * Items represent physical objects like books, comics and DVDs in the client's Library
		 */
		register_post_type('wp_lib_items',
			array(
				'labels' => array(
					'name'                  => 'Library',
					'singular_name'         => 'Library Item',
					'name_admin_bar'        => 'Library Item',
					'all_items'             => 'All Items',
					'add_new'               => 'New Item',
					'add_new_item'          => 'New Item',
					'edit'                  => 'Edit',
					'edit_item'             => 'Edit Item',
					'new_item'              => 'New Item',
					'view_item'             => 'View Library Item',
					'search_items'          => 'Search Library Items',
					'not_found'             => 'No Items found',
					'not_found_in_trash'    => 'No Items found in Trash',
				),
				'public'                => true,
				'menu_position'         => 15,
				'capability_type'       => 'wp_lib_items_cap',
				'map_meta_cap'          => true,
				'supports'              => array('title', 'editor', 'thumbnail'),
				'taxonomies'            => array(''),
				'menu_icon'             => 'dashicons-book-alt',
				'has_archive'           => true,
				'rewrite'               => array('slug' => $slugs[0].'/'.$slugs[1]),
				'register_meta_box_cb'  => function(){
					add_meta_box(
						'library_items_meta_box',
						'Item Details',
						function($item){require_once($this->plugin_path . '/' . self::ADMIN_TEMPLATE_DIR . '/edit-item-meta-box.php');},
						'wp_lib_items',
						'normal',
						'high'
					);
				}
			)
		);
		
		/**
		 * Registers custom post type members
		 * Members are people registered so that they may donate to or borrow books from the Library
		 */
		register_post_type('wp_lib_members',
			array(
				'labels' => array(
					'name'                  => 'Members',
					'singular_name'         => 'Member',
					'name_admin_bar'        => 'Library Member',
					'add_new'               => 'Add New',
					'add_new_item'          => 'Add New',
					'edit'                  => 'Edit',
					'edit_item'             => 'Edit Member',
					'new_item'              => 'New Member',
					'view_item'             => 'View Member',
					'search_items'          => 'Search All Members',
					'not_found'             => 'No Members found',
					'not_found_in_trash'    => 'No Members found in Trash',
				),
				'public'                => false,
				'show_ui'               => true,
				'capability_type'       => 'wp_lib_members_cap',
				'map_meta_cap'          => true,
				'exclude_from_search'   => true,
				'publicly_queryable'    => false,
				'show_in_menu'          => 'edit.php?post_type=wp_lib_items',
				'supports'              => array('title'),
				'register_meta_box_cb'  => function(){
					add_meta_box(
						'library_members_meta_box',
						'Member Details',
						function($member){require_once($this->plugin_path . '/' . self::ADMIN_TEMPLATE_DIR . '/edit-member-meta-box.php');},
						'wp_lib_members',
						'normal',
						'high'
					);
				}
			)
		);
		
		/**
		 * Registers custom post type loans
		 * Loans are created when members borrow items from the Library
		 * Loans hold information on when the item left the library and when it should be returned
		 */
		register_post_type('wp_lib_loans',
			array(
				'labels' => array(
					'name'              => 'Loans',
					'singular_name'     => 'Loan',
					'add_new'           => 'Add New Loan',
					'add_new_item'      => 'Add New Loan',
					'edit'              => 'Edit',
					'edit_item'         => 'Edit Loan',
					'new_item'          => 'New Loan',
					'view_item'         => 'View Loan',
					'search_items'      => 'Search Loans',
					'not_found'         => 'No Loans found',
					'not_found_in_trash'=> 'No Loans found in Trash',
				),
				'public'                => true,
				'public'                => true,
				'capability_type'       => 'wp_lib_loans_cap',
				'capabilities'          => array(
					'create_posts'          => false
				),
				'map_meta_cap'          => true,
				'exclude_from_search'   => true,
				'publicly_queryable'    => true,
				'show_in_menu'          => 'edit.php?post_type=wp_lib_items',
				'supports'              => array(''),
			)
		);
		
		/**
		 * Registers custom post type fines
		 * Fines can be created when an item is returned late, if a librarian chooses to fine the member
		 * Fines hold information about the late fine incurred such as the amount
		 */
		register_post_type('wp_lib_fines',
			array(
				'labels' => array(
					'name'              => 'Fines',
					'singular_name'     => 'Fine',
					'add_new'           => 'Add New Fine',
					'add_new_item'      => 'Add New Fine',
					'edit'              => 'Edit',
					'edit_item'         => 'Edit Fine',
					'new_item'          => 'New Fine',
					'view_item'         => 'View Fine',
					'search_items'      => 'Search Fines',
					'not_found'         => 'No Fines found',
					'not_found_in_trash'=> 'No Fines found in Trash',
				),
				'public'                => true,
				'capability_type'       => 'wp_lib_fines_cap',
				'capabilities'          => array(
					'create_posts'      => false
				),
				'map_meta_cap'          => true,
				'exclude_from_search'   => true,
				'publicly_queryable'    => true,
				'show_in_menu'          => 'edit.php?post_type=wp_lib_items',
				'supports'              => array(''),
			)
		);
		
		// Loads class to manage post tables of the post types registered above
		new WP_Lib_Admin_Tables($this);
		
		/**
		 * Registers custom taxonomy authors
		 * Authors are the creator(s) of an item, such as J.K. Rowling being the author of Harry Potter And The Philosopher's Stone
		 */
		register_taxonomy('wp_lib_author', 'wp_lib_items',
			array(
				'capabilities'      => array(  
					'manage_terms'  => 'wp_lib_manage_taxs',
					'edit_terms'    => 'wp_lib_manage_taxs',
					'delete_terms'  => 'wp_lib_manage_taxs',
					'assign_terms'  => 'wp_lib_manage_taxs'
				),
				'hierarchical'          => false,
				'show_ui'               => true,
				'show_admin_column'     => true,
				'update_count_callback' => '_update_post_term_count',
				'query_var'             => true,
				'rewrite'               => array(
					'slug'          => $slugs[0].'/'.$slugs[2],
					'with_front'    => false,
					'hierarchical'  => true
				),
				'labels'                => array(
					'name'                      => 'Authors',
					'singular_name'             => 'Author',
					'search_items'              => 'Search Authors',
					'popular_items'             => 'Popular Authors',
					'all_items'                 => 'All Authors',
					'edit_item'                 => 'Edit Author',
					'update_item'               => 'Update Author',
					'add_new_item'              => 'Add New Author',
					'new_item_name'             => 'New Author Name',
					'separate_items_with_commas'=> 'Separate authors with commas',
					'add_or_remove_items'       => 'Add or remove authors',
					'choose_from_most_used'     => 'Choose from the most used authors',
					'not_found'                 => 'No authors found.',
					'menu_name'                 => 'Authors'
				)
			)
		);
		
		/**
		 * Registers custom taxonomy media type
		 * Media Types define the type of an item, such as 'book' or 'DVD'
		 * An item's media type defines what post meta can be assigned to it
		 */
		register_taxonomy('wp_lib_media_type', 'wp_lib_items',
			array(
				'capabilities'      => array(  
					'manage_terms'  => 'wp_lib_manage_taxs',
					'edit_terms'    => 'wp_lib_manage_taxs',
					'delete_terms'  => 'wp_lib_manage_taxs',
					'assign_terms'  => 'wp_lib_manage_taxs'
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'          => $slugs[0].'/'.$slugs[3],
					'with_front'    => false,
					'hierarchical'  => true
				),
				'labels'            => array(
					'name'              => 'Media Types',
					'singular_name'     => 'Media Type',
					'search_items'      => 'Search Media Types',
					'all_items'         => 'All Media Types',
					'parent_item'       => 'Parent Media Type',
					'parent_item_colon' => 'Parent Media Type:',
					'edit_item'         => 'Edit Media Type',
					'update_item'       => 'Update Media Type',
					'add_new_item'      => 'Add New Media Type',
					'new_item_name'     => 'New Media Type Name',
					'menu_name'         => 'Media Types'
				)
			)
		);
	}
	
	/**
	 * Registers all settings sections so they can be loaded on the plugin settings page
	 */
	public function registerSettings() {
		// Loads file with settings classes
		$this->loadClass('settings');
		
		WP_Lib_Settings::$plugin_settings = apply_filters('wp_lib_plugin_settings', WP_Lib_Settings::$plugin_settings);
	
		/* -- General Library Settings -- */
		
		// Registers general settings section, settings and fields with sanitization callbacks
		WP_Lib_Settings_Section::registerSection(array(
			'name'      => 'wp_lib_library_group',
			'title'     => 'General Settings',
			'settings'  => array(
				array(
					'name'          => 'wp_lib_loan_length',
					'sanitize'      =>
						function($raw) {
							// Ensures loan length is an integer between 1-100 (inclusive)
							return array(min(max((int) abs(trim($raw[0])), 1), 100));
						},
					'fields'        => array(
						array(
							'name'          => 'Default Loan Length',
							'field_type'    => 'textInput',
							'args'          => array(
								'alt'       => 'The default number of days to loan an item'
							)
						)
					)
				),
				array(
					'name'      => 'wp_lib_renew_limit',
					'sanitize'  =>
						function($raw) {
							// Ensures input is a positive integer between 0-10 (inclusive)
							return array(min((int) abs(trim($raw[0])), 10));
						},
					'fields'    => array(
						array(
							'name'      => 'Renewing Limit',
							'field_type'=> 'textInput',
							'args'      => array(
								'alt'   => 'The maximum number of times an item can be renewed. 0 = no limit'
							)
						)
					)
				),
				array(
					'name'      => 'wp_lib_fine_daily',
					'sanitize'  =>
						function($raw) {
							// Ensures fine amount is a positive float with no more than 2 decimal places
							return array(round(max((float) trim($raw[0]), 0), 2));
						},
					'fields'    => array(
						array(
							'name'          => 'Late Fine',
							'field_type'    => 'textInput',
							'args'          => array(
								'alt'       => 'Amount to charge a member, per day, for a late item',
								'filter'    => function($input) { return number_format($input, 2); }
							)
						)
					)
				),
				array(
					'name'      => 'wp_lib_currency',
					'sanitize'  =>
						function($raw) {
							return array(
								htmlentities(substr(trim($raw[0]), 0, 4)),
								wp_lib_sanitize_option_checkbox($raw[1])
							);
						},
					'fields'    => array(
						array(
							'name'      => 'Currency Symbol',
							'field_type'=> 'textInput',
							'args'      => array(
								'alt'       => 'Set the symbol to be used before or after money is displayed'
							)
						),
						array(
							'name'      => 'Currency Position',
							'field_type'=> 'checkboxInput',
							'args'      => array(
								'alt'       => 'Check box to display currency symbol after value e.g. 0.40EUR'
							)
						)
					)
				)
			)
		));

		/* -- Slug Settings -- */

		// Registers settings groups and their sanitization callbacks for the slugs used on the front-end of the plugin
		WP_Lib_Settings_Section::registerSection(array(
			'name'      => 'wp_lib_slug_group',
			'title'     => 'Front-end Slugs',
			'callback'  => function(){
				echo '<p>These form the URLs of the front-end pages of your library</p>';
			},
			'settings'  => array(
				array(
					'name'          => 'wp_lib_slugs',
					'classes'       => array('slug-input'),
					'field_type'    => 'textInput',
					'sanitize'      =>
						function($raw) {
							foreach(range(0, 3) as $position) {
								$output[$position] = sanitize_title(trim($raw[$position]));
								
								// If there were no valid characters left in the slug, cancels saving
								if ($output[$position] === '')
									return;
							}
							
							// If author and media type slugs are identical, cancels saving
							if ($output[2] === $output[3])
								return;
							
							return $output;
						},
					'html_filter'   =>
						function($output, $args) {
							// Initialises url output preview
							$url = '<span>' . site_url() . '</span>/<span class="slug-preview" name="' . $args['setting_name'] . '[0]"></span>/';
							
							// If slug is not the main slug, add to preview
							if (isset($args['end']))
								$url .= '<span class="slug-preview" name="' . $args['setting_name'] . '[' . $args['position'] . ']"></span>/' . $args['end'] . '/';
							
							// Inserts preview of slug between input and description
							array_splice($output, 1, 0, '<label class="slug-label" for="' . $args['setting_name'] . '[' . $args['position'] . ']">' . $url . '</label>');
							
							return $output;
						},
					'fields'    => array(
						array(
							'name'  => 'Main',
							'args'  => array(
								'alt'       => 'This forms the base of all public Library pages',
								'classes'   => array('slug-main'),
							)
						),
						array(
							'name'  => 'Single Item',
							'args'  => array(
								'alt'   => 'This indicates the user is viewing a single item',
								'end'   => 'war-and-peace'
							)
						),
						array(
							'name'  => 'Authors',
							'args'  => array(
								'alt'   => 'This forms the url for browsing items by author',
								'end'   => 'terry-pratchett'
							)
						),
						array(
							'name'  => 'Media Type',
							'args'  => array(
								'alt'   => 'This forms the url for browsing items by media type',
								'end'   => 'comic-books'
							)
						)
					)
				)
			)
		));
		
		// Allows plugins to use WP-Librarian's settings class to handle their settings
		do_action('wp_lib_register_settings');
	}
	
	/**
	 * Registers all scripts and styles used on the front-end
	 */
	public function enqueueScripts() {
		do_action('wp_lib_enqueue_scripts');
		
		if (get_post_type() === 'wp_lib_items') {
			wp_enqueue_style('wp_lib_frontend');
		}
	}
	
	/**
	 * Registers and potentially enqueues scripts and styles used on the back end
	 * @param string $hook  The URL prefix of the current admin page
	 * @see                 http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	 */
	public function enqueueAdminScripts($hook) {
		do_action('wp_lib_admin_enqueue_scripts', $hook);
		
		if ($hook == 'post-new.php' || $hook == 'post.php') {
			switch ($GLOBALS['post_type']) {
				case 'wp_lib_items':
					wp_enqueue_style('wp_lib_admin_edit_item');
					wp_enqueue_script('wp_lib_edit_item');
				break;
				
				case 'wp_lib_members':
					wp_enqueue_style('wp_lib_meta_core');
					wp_enqueue_script('wp_lib_meta_core');
				break;
			}
		} elseif ($hook == 'edit.php' && in_array($GLOBALS['post_type'], array('wp_lib_items', 'wp_lib_members', 'wp_lib_loans', 'wp_lib_fines'), true)) {
			wp_enqueue_style('wp_lib_admin_post_table');
		}
		
		switch ($hook) {
			// Plugin settings page
			case 'wp_lib_items_page_wp-lib-settings':
				wp_enqueue_script('wp_lib_settings');
				wp_enqueue_style('wp_lib_admin_settings');
			break;
			
			// Library Dashboard
			case 'wp_lib_items_page_dashboard':
				wp_enqueue_script('wp_lib_dashboard');
				wp_enqueue_script('dynatable');
				wp_enqueue_style('wp_lib_dashboard');
				wp_enqueue_style('wp_lib_mellon_datepicker');   // Styles Datepicker
				wp_enqueue_style('jquery-ui');                  // Styles Datepicker
				wp_enqueue_style('dynatable');
			break;
		}
	}

	/**
	 * Adds Dashboard and (if user has sufficient permissions) the Settings page
	 */
	public function registerAdminPages() {
		if (wp_lib_is_library_admin()) {
			// Adds settings page to Library submenu of wp-admin menu
			$hook = add_submenu_page('edit.php?post_type=wp_lib_items', 'WP Librarian Settings', 'Settings', 'wp_lib_change_settings', 'wp-lib-settings', function(){self::loadAdminTemplate('settings');});
			
			// Registers hook to flush permalinks on successful settings update
			add_action('load-' . $hook, function() {
				// If settings have been updated (or failed to do so)
				if (isset($_GET['settings-updated'])) {
					// Loads helper to manage settings sections
					$this->loadClass('settings');
					
					// Checks that all plugin settings are valid, resets any settings that aren't
					WP_Lib_Settings::checkPluginSettingsIntegrity();
				}
				
				// Flushes permalinks, causing any new slugs to be propagated to the rewrite rules
				$this->flushPermalinks();
			});
		}

		// Registers Library Dashboard and saves handle to variable
		if (wp_lib_is_librarian()) {
			add_submenu_page('edit.php?post_type=wp_lib_items', 'Library Dashboard', 'Dashboard', 'edit_wp_lib_items_caps', 'dashboard', function(){self::loadAdminTemplate('dashboard');});
		}
	}
	
	/**
	 * Generates a Dashboard page, dynamically loaded onto the Library Dashboard
	 */
	public function ajaxLoadPage() {
		$this->loadClass('ajax');
		new WP_Lib_AJAX_Page($this);
		die(0);
	}
	
	/**
	 * Performs a Dashboard action, modifying the Library in some way (such as loaning an item)
	 */
	public function ajaxDoAction() {
		$this->loadClass('ajax');
		new WP_Lib_AJAX_Action($this);
		die(0);
	}
	
	/**
	 * Performs an API request, fetching information for an already loaded Dashboard page
	 */
	public function ajaxDoApiRequest() {
		$this->loadClass('ajax');
		new WP_Lib_AJAX_API($this);
		die(0);
	}
	
	/**
	 * Adds custom rewrite rules to WordPress permalink rules, allowing for pretty URLs for Library archives and single items
	 * @return array Updated WP rewrite rules
	 * @see http://codex.wordpress.org/Class_Reference/WP_Rewrite
	 */
	public function generateRewriteRules($wp_rewrite) {
		// Fetches single/archive slugs
		$slugs      = get_option('wp_lib_slugs', array('wp-librarian','item','authors','type'));
		$archive    = trailingslashit($slugs[0]);
		$single     = $archive . trailingslashit($slugs[1]);
		
		$new_rules  = array();
		
		$new_rules[$archive.'?$']                               = 'index.php?post_type=wp_lib_items';
		$new_rules[$archive.'page/?([0-9]{1,})/?$']             = 'index.php?post_type=wp_lib_items&paged=' . $wp_rewrite->preg_index(1);
		$new_rules[$archive.'(feed|rdf|rss|rss2|atom)/?$']      = 'index.php?post_type=wp_lib_items&feed=' . $wp_rewrite->preg_index(1);
		$new_rules[$archive.'feed/(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?post_type=wp_lib_items&feed=' . $wp_rewrite->preg_index(1);
		
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
		
		return $wp_rewrite;
	}
	
	/**
	 * Registers custom post types and taxonomies then flushes rewrite WordPress's rewrite rules
	 * @see http://codex.wordpress.org/Using_Permalinks
	 */
	public function flushPermalinks() {
		$this->registerPostAndTax();
		
		flush_rewrite_rules();
	}
	
	/**
	 * Returns the current update channel, version, build and nickname of WP-Librarian
	 * @return  array   Current plugin version
	 */
	public function getPluginVersion() {
		return array(
			'channel'   => 'Alpha',
			'version'   => '0.3.1',
			'subversion'=> '010',
			'nickname'  => 'Badger Claw'
		);
	}
	
	/**
	 * Returns all valid WP-Librarian roles and their names
	 * @return array All library roles where the key is the role (int) and the value is the name (string)
	 */
	public function getUserRoles() {
		return array(
			1   => '',
			5   => 'Librarian',
			10  => 'Administrator'
		);
	}
	
	/**
	 * Given the name of a CSS file, returns its full URL
	 * @param   string  $name   File name e.g. 'front-end-core'
	 * @return  string          Full file URL e.g. '.../styles/front-end-core.css'
	 */
	public function getStyleUrl($name) {
		// Loads minified assets in production. Regular for dev/debugging
		if ((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || WP_LIB_DEBUG_MODE) {
			return $this->plugin_url . '/' . self::STYLE_DIR . '/' . $name . '.css';
		} else {
			return $this->plugin_url . '/' . self::MINIFIED_DIR . '/' . $name . '.min.css';
		}
	}
	
	/**
	 * Given the name of a JS file, returns its full URL
	 * @param   string  $name   File name e.g. 'admin-dashboard'
	 * @return  string          Full file URL e.g. '.../scripts/admin.js'
	 */
	public function getScriptUrl($name) {
		// Loads minified assets in production. Regular for dev/debugging
		if ((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || WP_LIB_DEBUG_MODE) {
			return $this->plugin_url . '/' . self::SCRIPT_DIR . '/' . $name . '.js';
		} else {
			return $this->plugin_url . '/' . self::MINIFIED_DIR . '/' . $name . '.min.js';
		}
	}
	
	/**
	 * Given the name of a template file, returns full path to template
	 * @param   string  $name   File name, e.g. 'archive-wp_lib_items'
	 * @return  string          Full file path e.g. '.../templates/archive-wp_lib_items.php'
	 */
	public function getTemplateDir($name) {
		return $this->plugin_path . '/' . self::TEMPLATE_DIR . '/' . $name . '.php';
	}
	
	/*
	 * If given ID belongs to a valid library object, returns formatted object type
	 * @param   int                 $post_id    Post ID of library object (item/member/etc.)
	 * @return  string|WP_Lib_Error             Formatted object type or instance of error
	 */
	public function getObjectType($post_id) {
		// Returns Library object type
		switch (get_post_type($post_id)) {
			case 'wp_lib_items':
				return 'item';
			break;
			
			case 'wp_lib_members':
				return 'member';
			break;
			
			case 'wp_lib_loans':
				return 'loan';
			break;
			
			case 'wp_lib_fines':
				return 'fine';
			break;
			
			default:
				// Otherwise object does not belong to the Library
				return wp_lib_error(317);
			break;
		}
	}
	
	/**
	 * Replaces 'Post title' prompt text with custom post type specific text
	 * @return string   Prompt text relevant to the current post type
	 */
	public function replaceNewPostPromptText() {
		switch (get_current_screen()->post_type) {
			case 'wp_lib_members':
				return 'Member Name';
			break;
			
			case 'wp_lib_items':
				return 'Item Title';
			break;
		}
	}
	
	/**
	 * Replaces 'Featured Image' title of post's featured image box with 'Item Cover'
	 */
	public function replaceFeaturedImageTitle() {
		if ($GLOBALS['post_type'] == 'wp_lib_items') {
			global $wp_meta_boxes;
			$wp_meta_boxes['wp_lib_items']['side']['low']['postimagediv']['title'] = 'Cover Image';
		}
	}
	
	/**
	 * Replaces item and member post updated messages with custom messages
	 * @param   array   $messages   An array of post types containing an array their post updated messages
	 * @return  array               The message array originally passed in with modifications made
	 */
	public function replacePostUpdatedMessages($messages) {
		// Fetches post and post type
		$post = get_post();
		$post_type = get_post_type($post);
		
		// Adds messages based on current post's post type
		switch($post_type) {
			case 'wp_lib_items':
				// Creates hyperlink to view or preview item
				$permalink      = get_permalink($post->ID);
				$view_link      = ' <a href="' . esc_url($permalink) . '">' . 'View Item' . '</a>';
				$preview_link   = ' <a target="_blank" href="' . esc_url(add_query_arg('preview', 'true', $permalink)) . '">' . 'Preview Item' . '</a>';
				$messages['wp_lib_items'] = array(
					1 => 'Item Updated.' . $view_link,
					6 => 'Item Published.' . $view_link,
					7 => 'Item Saved.',
					8 => 'Item Submitted.' . $preview_link,
					9 => 'Item\'s publishing scheduled for: <strong>' . date_i18n('M j, Y @ G:i', strtotime($post->post_date)) . '</strong>'. $view_link,
					10 => 'Item draft updated.' . $preview_link
				);
			break;
			
			case 'wp_lib_members':
				// Creates hyperlink to manage Member
				$manage_member_link = ' <a href="' . wp_lib_manage_member_url($post->ID) . '">' . 'Manage Member' . '</a>';
				$messages['wp_lib_members'] = array(
					1 => 'Member\'s details updated.' . $manage_member_link,
					6 => 'Member published.' . $manage_member_link,
					7 => 'Member\'s details saved.',
					8 => 'Member Submitted.',
					9 => 'Member will be published at: <strong>' . date_i18n('M j, Y @ G:i', strtotime($post->post_date)) . '</strong>',
					10 => 'Member details draft updated.'
				);
			break;
		}
		
		return $messages;
	}
	
	/**
	 * Replaces plugin's taxonomy updated messages with custom messages
	 * @param   array   $messages   Array of taxonomies, each containing an array of tax updated messages
	 * @return  array               Given array with modifications made to plugin's taxes
	 */
	public function replaceTaxUpdatedMessages($messages) {
		// Customises messages based on current taxonomy
		switch($_GET['taxonomy']) {
			case 'wp_lib_author':
				$messages['_item'] = array(
					1 => 'Author Created',
					2 => 'Author Deleted',
					3 => 'Author Updated',
					4 => 'Author not deleted',
					5 => 'Author not deleted',
					6 => 'Authors deleted'
				);
			break;
			case 'wp_lib_media_type':
				$messages['_item'] = array(
					1 => 'Media Type Created',
					2 => 'Media Type Deleted',
					3 => 'Media Type Updated',
					4 => 'Media Type not deleted',
					5 => 'Media Type not deleted',
					6 => 'Media Types deleted'
				);
			break;
		}
		
		return $messages;
	}
	
	/**
	 * Looks for archive/single post templates in current theme, failing that uses plugin defaults
	 */
	public function locatePostTemplate($template) {
		if (get_post_type() === 'wp_lib_items') {
			// Name of archive/single post templates
			$archive_name   = 'archive-wp_lib_items';
			$single_name    = 'single-wp_lib_items';
			
			// Loads template hook class
			$this->loadClass('template-hooks');
			new WP_Lib_Template_Hooks($this);
			
			// If page is archive of multiple items
			if (is_archive()) {
				// Looks for template in current theme
				$theme_file = locate_template(array($archive_name.'.php'));
				
				// If theme has template, uses it. Otherwise uses plugin default
				return ($theme_file === '') ? $this->getTemplateDir($archive_name) : $theme_file;
			}
			// If page is a single item
			elseif (is_single()) {
				// Looks for template in current theme
				$theme_file = locate_template(array('single-wp_lib_items.php'));
				
				// If theme has template, uses it. Otherwise uses plugin default
				return ($theme_file === '') ? $this->getTemplateDir($single_name) : $theme_file;
			}
		}
		// If post is not a Library item
		return $template;
	}
	
	/**
	 * Checks a post before it is deleted to ensure, if it a library post, its deletion does not damage the library's integrity
	 * @param int $post_id  The post ID of the post about to be deleted
	 * @todo                Create deletion class to handle authorised deletion buffering etc.
	 */
	public function checkPostPreTrash($post_id) {
		// If object doesn't belong to the Library, is an autosave or integrity checking is turned off, pre-deletion checking is skipped
		if (!in_array(get_post_type($post_id), array('wp_lib_items', 'wp_lib_members', 'wp_lib_loans', 'wp_lib_fines')) || wp_is_post_autosave($post_id) || !WP_LIB_MAINTAIN_INTEGRITY || apply_filters('wp_lib_bypass_deletion_checks', false, $post_id))
			return;
		
		// If object is being deleted via an AJAX request
		if (defined('DOING_AJAX') && DOING_AJAX) {
			die();
		} else {
			// Redirects user to page to confirm object deletion properly (with connected objects being deleted as well)
			wp_redirect(wp_lib_format_dash_url(
				array(
					'dash_page' => 'object-deletion',
					'post_id'   => $post_id
				)
			));
			die();
		}
	}
	
	/**
	 * Hides items marked as 'delisted' from public item archive
	 * Note that this does not hide them from being viewed via their direct URL
	 * For greater control of who can see what, use a permissions plugin like Page Security by Contexture
	 * @param   WP_Query    $query  Current posts query, passed by reference
	 */
	public function hideDelistedItems(WP_Query $query) {
		if ($query->is_post_type_archive('wp_lib_items') && $query->is_main_query() && !is_admin()) {
			$query->set('meta_query',
				array(
					'relation'      => 'OR',
					array(
						'key'       => 'wp_lib_item_delist',
						'value'     => '1',
						'compare'   => '!=',
					),
					array(
						'key'       => 'wp_lib_item_delist',
						'value'     => 'bug #23268', // Allows WP-Librarian to run on pre-3.9 WP installs (bug was fixed for 3.9, text is arbitrary)
						'compare'   => 'NOT EXISTS',
					)
				)
			);
		}
	}
	
	/**
	 * Hides 'Media Type' tax selection meta box from edit items screen
	 * Media Type is selected from meta box below item description
	 */
	public function removeAdminMetaBoxes() {
		remove_meta_box('wp_lib_media_typediv', 'wp_lib_items', 'side');
	}
}
