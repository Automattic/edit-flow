<?php

defined( 'ABSPATH' ) || exit();

global $edit_flow;

?>

<div id="ajax-response"></div>

<form method="post" action="<?php echo esc_url( $edit_status_link ); ?>" >
	<input type="hidden" name="term-id" value="<?php echo esc_attr( $term_id ); ?>" />

	<?php
	wp_original_referer_field();
	wp_nonce_field( 'edit-status' );
	?>

	<table class="form-table">
		<tr class="form-field form-required">
			<th scope="row" valign="top"><label for="name"><?php esc_html_e( 'Custom Status', 'edit-flow' ); ?></label></th>
			<?php $readonly_attr = 'draft' === $custom_status->slug ? 'readonly="readonly"' : ''; ?>
			<td><input name="name" id="name" type="text" value="<?php echo esc_attr( $name ); ?>" size="40" aria-required="true" <?php echo esc_attr( $readonly_attr ); ?> />
			<?php $edit_flow->settings->helper_print_error_or_description( 'name', __( 'The name is used to identify the status. (Max: 20 characters)', 'edit-flow' ) ); ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><?php esc_html_e( 'Slug', 'edit-flow' ); ?></th>
			<td>
				<input type="text" disabled="disabled" value="<?php echo esc_attr( $custom_status->slug ); ?>" />
				<?php $edit_flow->settings->helper_print_error_or_description( 'slug', __( 'The slug is the unique ID for the status and is changed when the name is changed.', 'edit-flow' ) ); ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="description"><?php esc_html_e( 'Description', 'edit-flow' ); ?></label></th>
			<td>
				<textarea name="description" id="description" rows="5" cols="50" style="width: 97%;"><?php echo esc_textarea( $description ); ?></textarea>
			<?php $edit_flow->settings->helper_print_error_or_description( 'description', __( 'The description is primarily for administrative use, to give you some context on what the custom status is to be used for.', 'edit-flow' ) ); ?>
			</td>
		</tr>
	</table>

	<p class="submit">
		<?php submit_button( __( 'Update Status', 'edit-flow' ), 'primary', 'submit', false ); ?>
		<a class="cancel-settings-link" href="<?php echo esc_url( $this->get_link() ); ?>"><?php esc_html_e( 'Cancel', 'edit-flow' ); ?></a>
	</p>
</form>
