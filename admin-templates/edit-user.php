<?php
/**
 * WP-LIBRARIAN EDIT USER
 * Adds Library Role option to user profile pages and new user page
 */

// No direct loading
defined('ABSPATH') OR die('No');


// If current user can't edit users (is admin), disallow from viewing form
// Note that regardless of being able to view the form, they wouldn't be able to change the user's meta
if (!current_user_can('edit_users'))
	return;

// Sets up user role options array
foreach([1,5,10] as $role_value) {
	$roles[$role_value] = wp_lib_format_user_permission_status($role_value);
}

// Fetches user's current roles
$status = get_user_meta($user_id, 'wp_lib_role', true);

// If user has no role, role is 0
if (!$status)
	$status = 1;

// Adds nonce to section
wp_nonce_field('Editing User: ' . $user_id, 'wp_lib_profile_nonce');
?>
<h3>WP-Librarian</h3>
<table class="form-table">
	<tr>
		<th><label for="wp-lib-role-input">Library Role</label></th>
		<td>
			<select id="wp-lib-role-input" name="wp_lib_role">
				<?php
					foreach($roles as $numeric_role => $text_role) {
						if ($status == $numeric_role)
							$selected = ' selected="selected" ';
						else
							$selected = '';
						echo '<option value="' . $numeric_role . '"' . $selected . '>' . $text_role . '</option>';
					}
				?>
			</select><br/>
			<span class="description" for="wp-lib-role-input">This defines how a user can interact with WP-Librarian.</span>
		</td>
	</tr>
</table>
<?php