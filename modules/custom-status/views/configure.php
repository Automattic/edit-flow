<?php
/**
 * View for configuring custom statuses.
 *
 * @package EditFlow
 */

defined( 'ABSPATH' ) || exit();

// phpcs:disable:WordPress.Security.NonceVerification.Missing -- Disabling nonce verification because that is not available here, it's just rendering it. The actual save is done in helper_settings_validate_and_save and that's guarded well.

global $edit_flow;

?>

<div id="col-right">
	<div class="col-wrap">
		<?php $custom_status_list_table->display(); ?>
		<?php wp_nonce_field( 'custom-status-sortable', 'custom-status-sortable' ); ?>
		<p class="description" style="padding-top:10px;"><?php esc_html_e( 'Deleting a post status will assign all posts to the default post status.', 'edit-flow' ); ?></p>
	</div>
</div>

<div id="col-left">
	<div class="col-wrap">
		<div class="form-wrap">
			<h3 class="nav-tab-wrapper">
				<?php $add_new_nav_class = empty( $action ) ? 'nav-tab-active' : ''; ?>
				<a href="<?php echo esc_url( $this->get_link() ); ?>" class="nav-tab <?php echo esc_attr( $add_new_nav_class ); ?>"><?php esc_html_e( 'Add New', 'edit-flow' ); ?></a>
				<?php $options_nav_class = 'change-options' === $action ? 'nav-tab-active' : ''; ?>
				<a href="<?php echo esc_url( $this->get_link( array( 'action' => 'change-options' ) ) ); ?>" class="nav-tab <?php echo esc_attr( $options_nav_class ); ?>"><?php esc_html_e( 'Options', 'edit-flow' ); ?></a>
				<?php $migrate_nav_class = 'migrate-status' === $action ? 'nav-tab-active' : ''; ?>
				<a href="<?php echo esc_url( $this->get_link( array( 'action' => 'migrate-status' ) ) ); ?>" class="nav-tab <?php echo esc_attr( $migrate_nav_class ); ?>"><?php esc_html_e( 'Migrate', 'edit-flow' ); ?></a>
			</h3>

			<?php if ( 'change-options' === $action ) { ?>
			<form class="basic-settings" action="<?php echo esc_url( $this->get_link( array( 'action' => 'change-options' ) ) ); ?>" method="post">
				<?php settings_fields( $this->module->options_group_name ); ?>
				<?php do_settings_sections( $this->module->options_group_name ); ?>
				<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="<?php echo esc_attr( $this->module->name ); ?>" />
				<?php submit_button(); ?>
			</form>
			<?php } elseif ( 'migrate-status' === $action ) { ?>
			<!-- Migrate posts between statuses -->
				<?php
				$custom_statuses = $this->get_custom_statuses();
				$core_statuses   = [
					'draft'   => __( 'Draft', 'edit-flow' ),
					'pending' => __( 'Pending Review', 'edit-flow' ),
					'publish' => __( 'Published', 'edit-flow' ),
					'private' => __( 'Private', 'edit-flow' ),
					'trash'   => __( 'Trash', 'edit-flow' ),
				];
				?>
			<p class="description" style="margin-bottom: 1em;">
				<?php esc_html_e( 'Use this tool to migrate posts from one status to another. This is useful when deactivating Edit Flow or consolidating statuses.', 'edit-flow' ); ?>
			</p>
			<form action="<?php echo esc_url( $this->get_link( array( 'action' => 'migrate-status' ) ) ); ?>" method="post" id="migrate-status-form">
				<div class="form-field">
					<label for="migrate_from"><?php esc_html_e( 'From Status', 'edit-flow' ); ?></label>
					<select id="migrate_from" name="migrate_from">
						<option value=""><?php esc_html_e( '— Select Status —', 'edit-flow' ); ?></option>
						<optgroup label="<?php esc_attr_e( 'Custom Statuses', 'edit-flow' ); ?>">
							<?php foreach ( $custom_statuses as $custom_status ) : ?>
								<?php
								$count = $this->get_post_count_for_status( $custom_status->slug );
								?>
								<option value="<?php echo esc_attr( $custom_status->slug ); ?>">
									<?php echo esc_html( $custom_status->name ); ?> (<?php echo esc_html( $count ); ?>)
								</option>
							<?php endforeach; ?>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Core Statuses', 'edit-flow' ); ?>">
							<?php foreach ( $core_statuses as $slug => $label ) : ?>
								<?php
								$count = $this->get_post_count_for_status( $slug );
								?>
								<option value="<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $label ); ?> (<?php echo esc_html( $count ); ?>)
								</option>
							<?php endforeach; ?>
						</optgroup>
					</select>
					<p class="description"><?php esc_html_e( 'Select the status to migrate posts from.', 'edit-flow' ); ?></p>
				</div>

				<div class="form-field">
					<label for="migrate_to"><?php esc_html_e( 'To Status', 'edit-flow' ); ?></label>
					<select id="migrate_to" name="migrate_to">
						<option value=""><?php esc_html_e( '— Select Status —', 'edit-flow' ); ?></option>
						<optgroup label="<?php esc_attr_e( 'Custom Statuses', 'edit-flow' ); ?>">
							<?php foreach ( $custom_statuses as $custom_status ) : ?>
								<option value="<?php echo esc_attr( $custom_status->slug ); ?>">
									<?php echo esc_html( $custom_status->name ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Core Statuses', 'edit-flow' ); ?>">
							<?php foreach ( $core_statuses as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					</select>
					<p class="description"><?php esc_html_e( 'Select the target status for the posts.', 'edit-flow' ); ?></p>
				</div>

				<?php wp_nonce_field( 'custom-status-migrate-nonce' ); ?>
				<input type="hidden" name="action" value="migrate" />
				<?php submit_button( __( 'Migrate Posts', 'edit-flow' ), 'primary', 'submit', true, array( 'id' => 'migrate-submit' ) ); ?>
			</form>
			<?php } else { ?>
			<!-- Custom form for adding a new Custom Status term -->
			<form class="add:the-list:" action="<?php echo esc_url( $this->get_link() ); ?>" method="post" id="addstatus" name="addstatus">
				<div class="form-field form-required">
					<label for="status_name"><?php esc_html_e( 'Name', 'edit-flow' ); ?></label>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in form handler. ?>
					<input type="text" aria-required="true" size="20" maxlength="20" id="status_name" name="status_name" value="<?php echo ( empty( $_POST['status_name'] ) ? '' : esc_attr( sanitize_text_field( wp_unslash( $_POST['status_name'] ) ) ) ); ?>" />
					<?php $edit_flow->settings->helper_print_error_or_description( 'name', __( 'The name is used to identify the status. (Max: 20 characters)', 'edit-flow' ) ); ?>
				</div>

				<div class="form-field">
					<label for="status_description"><?php esc_html_e( 'Description', 'edit-flow' ); ?></label>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in form handler. ?>
					<textarea cols="40" rows="5" id="status_description" name="status_description"><?php echo ( empty( $_POST['status_description'] ) ? '' : esc_textarea( sanitize_textarea_field( wp_unslash( $_POST['status_description'] ) ) ) ); ?></textarea>
					<?php $edit_flow->settings->helper_print_error_or_description( 'description', __( 'The description is primarily for administrative use, to give you some context on what the custom status is to be used for.', 'edit-flow' ) ); ?>
				</div>

				<?php wp_nonce_field( 'custom-status-add-nonce' ); ?>
				<input id="action" name="action" type="hidden" value="add-new" />
				<?php submit_button( __( 'Add New Status', 'edit-flow' ) ); ?>
			</form>
			<?php } ?>
		</div>
	</div>
</div>

<?php

// phpcs:enable:WordPress.Security.NonceVerification.Missing

$custom_status_list_table->inline_edit();
