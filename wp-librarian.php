<?php
/**
 * Plugin Name: WP Librarian
 * Description: Manage a physical library of books and media. Loan, return and schedule with WP-Librarian.
 * Version: 0.0.1
 * Author: Kit Maywood
 * Text Domain: wp-librarian
 * Author URI: https://github.com/kittsville
 * License: GPL2
 */

/*
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Whether to display additional debugging information along with errors
define('WP_LIB_DEBUG_MODE', true);

// Whether to ensure connected objects can't be deleted individually (e.g. deleting a loan but not its fine). Turning this off is a great way to break your Library!
define('WP_LIB_MAINTAIN_INTEGRITY', true);

require_once (dirname(__FILE__) . '/lib/wp-librarian.class.php');

$wp_librarian = new WP_LIBRARIAN;

register_activation_hook(__FILE__, array($wp_librarian, 'runOnActivation'));

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
