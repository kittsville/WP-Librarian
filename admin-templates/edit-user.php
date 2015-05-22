<?php
/**
 * WP-LIBRARIAN EDIT USER
 * Adds Library Role option to user profile pages and new user page
 */

// No direct loading
defined('ABSPATH') OR die('No');

?>
<h3>WP-Librarian</h3>
<table class="form-table">
	<tr>
		<th><label for="wp-lib-role-input">Library Role</label></th>
		<td>
			<select id="wp-lib-role-input" name="wp_lib_role">
				<?php
					foreach($params[0] as $numeric_role => $text_role) {
						if ($params[1] == $numeric_role)
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
