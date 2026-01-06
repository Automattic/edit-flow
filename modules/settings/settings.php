<?php
/**
 * Settings module for Edit Flow.
 *
 * @package EditFlow
 */

if ( ! class_exists( 'EF_Settings' ) ) {

	/**
	 * Settings module class for Edit Flow.
	 */
	class EF_Settings extends EF_Module {

		/**
		 * The module object.
		 *
		 * @var object
		 */
		public $module;

		/**
		 * Register the module with Edit Flow but don't do anything else.
		 */
		public function __construct() {
			// Register the module with Edit Flow.
			$this->module_url = $this->get_module_url( __FILE__ );
			$args             = array(
				'title'                => __( 'Edit Flow', 'edit-flow' ),
				'short_description'    => __( 'Edit Flow redefines your WordPress publishing workflow.', 'edit-flow' ),
				'extended_description' => __( 'Enable any of the features below to take control of your workflow. Custom statuses, email notifications, editorial comments, and more help you and your team save time so everyone can focus on what matters most: the content.', 'edit-flow' ),
				'module_url'           => $this->module_url,
				'img_url'              => $this->module_url . 'lib/eflogo_s128.png',
				'slug'                 => 'settings',
				'settings_slug'        => 'ef-settings',
				'default_options'      => array(
					'enabled' => 'on',
				),
				'configure_page_cb'    => 'print_default_settings',
				'autoload'             => true,
			);
			$this->module     = EditFlow()->register_module( 'settings', $args );
		}

		/**
		 * Initialize the rest of the stuff in the class if the module is active.
		 */
		public function init() {
			add_action( 'admin_init', array( $this, 'helper_settings_validate_and_save' ), 100 );

			add_action( 'admin_print_styles', array( $this, 'action_admin_print_styles' ) );
			add_action( 'admin_print_scripts', array( $this, 'action_admin_print_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

			add_action( 'wp_ajax_change_edit_flow_module_state', array( $this, 'ajax_change_edit_flow_module_state' ) );
		}

		/**
		 * Add necessary things to the admin menu.
		 */
		public function action_admin_menu() {
			global $edit_flow;

			$ef_logo = 'lib/eflogo_s32w.png';

			add_menu_page( $this->module->title, $this->module->title, 'manage_options', $this->module->settings_slug, array( $this, 'settings_page_controller' ), $this->module->module_url . $ef_logo );

			// Add "Features" as the first submenu item (replaces the duplicate "Edit Flow" item).
			add_submenu_page( $this->module->settings_slug, __( 'Features', 'edit-flow' ), __( 'Features', 'edit-flow' ), 'manage_options', $this->module->settings_slug, array( $this, 'settings_page_controller' ) );

			foreach ( $edit_flow->modules as $mod_name => $mod_data ) {
				if ( isset( $mod_data->options->enabled ) && 'on' == $mod_data->options->enabled
				&& $mod_data->configure_page_cb && $mod_name != $this->module->name ) {
					add_submenu_page( $this->module->settings_slug, $mod_data->title, $mod_data->title, 'manage_options', $mod_data->settings_slug, array( $this, 'settings_page_controller' ) );
				}
			}
		}

		/**
		 * Enqueue scripts for the settings page.
		 */
		public function action_admin_enqueue_scripts() {
			if ( $this->is_whitelisted_settings_view() ) {
				wp_enqueue_script( 'edit-flow-settings-js', $this->module_url . 'lib/settings.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
			}
		}

		/**
		 * Add settings styles to the settings page.
		 */
		public function action_admin_print_styles() {
			if ( $this->is_whitelisted_settings_view() ) {
				wp_enqueue_style( 'edit_flow-settings-css', $this->module_url . 'lib/settings.css', false, EDIT_FLOW_VERSION );
			}
		}

		/**
		 * Extra data we need on the page for transitions, etc.
		 *
		 * @since 0.7
		 */
		public function action_admin_print_scripts() {
			?>
		<script type="text/javascript">
			var ef_admin_url = '<?php echo esc_url( get_admin_url() ); ?>';
		</script>
			<?php
		}

		/**
		 * AJAX handler to enable or disable an Edit Flow module.
		 */
		public function ajax_change_edit_flow_module_state() {
			global $edit_flow;

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't need sanitization, just verification.
			if ( ! isset( $_POST['change_module_nonce'] ) || ! wp_verify_nonce( $_POST['change_module_nonce'], 'change-edit-flow-module-nonce' ) || ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; uh?', 'edit-flow' ) );
			}

			if ( ! isset( $_POST['module_action'], $_POST['slug'] ) ) {
				wp_die( '-1' );
			}

			$module_action = sanitize_key( $_POST['module_action'] );
			$slug          = sanitize_key( $_POST['slug'] );

			$module = $edit_flow->get_module_by( 'slug', $slug );

			if ( ! $module ) {
				wp_die( '-1' );
			}

			if ( 'enable' == $module_action ) {
				$return = $edit_flow->update_module_option( $module->name, 'enabled', 'on' );
			} elseif ( 'disable' == $module_action ) {
				$return = $edit_flow->update_module_option( $module->name, 'enabled', 'off' );
			}

			if ( $return ) {
				wp_die( '1' );
			} else {
				wp_die( '-1' );
			}
		}

		/**
		 * Handles all settings and configuration page requests. Required element for Edit Flow.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- Rendering only, no data modification.
		 */
		public function settings_page_controller() {
			global $edit_flow;

			$page_requested = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'settings';
			// phpcs:enable WordPress.Security.NonceVerification
			$requested_module = $edit_flow->get_module_by( 'settings_slug', $page_requested );
			if ( ! $requested_module ) {
				wp_die( esc_html__( 'Not a registered Edit Flow module', 'edit-flow' ) );
			}

			$configure_callback    = $requested_module->configure_page_cb;
			$requested_module_name = $requested_module->name;

			// Don't show the settings page for the module if the module isn't activated.
			if ( ! $this->module_enabled( $requested_module_name ) ) {
				/* translators: 1: link to the settings page for Edit Flow */
				echo '<div class="message error"><p>' . wp_kses( sprintf( __( 'Module not enabled. Please enable it from the <a href="%1$s">Edit Flow settings page</a>.', 'edit-flow' ), esc_url( EDIT_FLOW_SETTINGS_PAGE ) ), 'a' ) . '</p></div>';
				return;
			}

			$this->print_default_header( $requested_module );
			$edit_flow->$requested_module_name->$configure_callback();
			$this->print_default_footer( $requested_module );
		}

		/**
		 * Print the default header for the settings page.
		 *
		 * Nonce verification is not available here - it's just rendering. The actual save
		 * is done in helper_settings_validate_and_save and that's guarded well.
		 *
		 * @param object $current_module The current module being displayed.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification
		 */
		public function print_default_header( $current_module ) {
			// Register admin notices for standard WordPress display.
			if ( isset( $_GET['message'] ) ) {
				$message = sanitize_key( $_GET['message'] );
			} elseif ( isset( $_REQUEST['message'] ) ) {
				$message = sanitize_key( $_REQUEST['message'] );
			} elseif ( isset( $_POST['message'] ) ) {
				$message = sanitize_key( $_POST['message'] );
			} else {
				$message = false;
			}
			if ( $message && isset( $current_module->messages[ $message ] ) ) {
				add_settings_error(
					'edit-flow',
					'edit-flow-' . $message,
					$current_module->messages[ $message ],
					'success'
				);
			}

			// If there's been an error, register it as an admin notice.
			if ( isset( $_GET['error'] ) ) {
				$error = sanitize_key( $_GET['error'] );
			} elseif ( isset( $_REQUEST['error'] ) ) {
				$error = sanitize_key( $_REQUEST['error'] );
			} elseif ( isset( $_POST['error'] ) ) {
				$error = sanitize_key( $_POST['error'] );
			} else {
				$error = false;
			}
			if ( $error && isset( $current_module->messages[ $error ] ) ) {
				add_settings_error(
					'edit-flow',
					'edit-flow-' . $error,
					$current_module->messages[ $error ],
					'error'
				);
			}

			// Build the page title.
			if ( 'settings' !== $current_module->name ) {
				$page_title = sprintf(
					/* translators: %s: module title */
					__( 'Edit Flow: %s', 'edit-flow' ),
					$current_module->title
				);
			} else {
				$page_title = __( 'Edit Flow: Features', 'edit-flow' );
			}
			?>
		<div class="wrap edit-flow-admin">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<?php settings_errors( 'edit-flow' ); ?>

			<?php if ( $current_module->short_description || $current_module->extended_description ) : ?>
			<div class="explanation">
				<?php if ( $current_module->short_description ) : ?>
				<p class="description"><?php echo wp_kses_post( $current_module->short_description ); ?></p>
				<?php endif; ?>
				<?php if ( $current_module->extended_description ) : ?>
				<p><?php echo wp_kses_post( $current_module->extended_description ); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification

		/**
		 * Adds Settings page for Edit Flow.
		 */
		public function print_default_settings() {
			?>
		<div class="edit-flow-modules">
			<?php $this->print_modules(); ?>
		</div>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
			<?php settings_fields( $this->module->options_group_name ); ?>
			<?php do_settings_sections( $this->module->options_group_name ); ?>
			<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="<?php echo esc_attr( $this->module->name ); ?>" />
			<?php submit_button(); ?>
		</form>
			<?php
		}

		/**
		 * Print the default footer for the settings page.
		 *
		 * @param object $current_module The current module being displayed.
		 */
		public function print_default_footer( $current_module ) {
			?>
		</div>
			<?php
		}

		/**
		 * Print the list of Edit Flow modules on the settings page.
		 */
		public function print_modules() {
			global $edit_flow;

			if ( ! $edit_flow->modules_count ) {
				echo '<div class="message error">' . esc_html__( 'There are no Edit Flow modules registered', 'edit-flow' ) . '</div>';
			} else {
				foreach ( $edit_flow->modules as $mod_name => $mod_data ) {
					if ( $mod_data->autoload ) {
						continue;
					}

					$classes = array(
						'edit-flow-module',
					);
					if ( 'on' == $mod_data->options->enabled ) {
						$classes[] = 'module-enabled';
					} elseif ( 'off' == $mod_data->options->enabled ) {
						$classes[] = 'module-disabled';
					}
					if ( $mod_data->configure_page_cb ) {
						$classes[] = 'has-configure-link';
					}
					echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $mod_data->slug ) . '">';
					if ( $mod_data->img_url ) {
						echo '<img src="' . esc_url( $mod_data->img_url ) . '" height="24px" width="24px" class="float-right module-icon" />';
					}
					echo '<form method="get" action="' . esc_url( get_admin_url( null, 'options.php' ) ) . '">';
					echo '<h4>' . esc_html( $mod_data->title ) . '</h4>';
					echo '<p>' . wp_kses( $mod_data->short_description, 'post' ) . '</p>';
					echo '<p class="edit-flow-module-actions">';
					if ( $mod_data->configure_page_cb ) {
						$configure_url = add_query_arg( 'page', $mod_data->settings_slug, get_admin_url( null, 'admin.php' ) );
						echo '<a href="' . esc_url( $configure_url ) . '" class="configure-edit-flow-module';
						if ( 'off' == $mod_data->options->enabled ) {
							echo ' hidden" style="display:none;';
						}
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic configure link text.
						echo '">' . esc_html__( $mod_data->configure_link_text, 'edit-flow' ) . '</a>';
					}
					echo '<input type="submit" class="button-primary button enable-disable-edit-flow-module"';
					if ( 'on' == $mod_data->options->enabled ) {
						echo ' style="display:none;"';
					}
					echo ' value="' . esc_textarea( __( 'Enable', 'edit-flow' ) ) . '" />';
					echo '<input type="submit" class="button-secondary button-remove button enable-disable-edit-flow-module"';
					if ( 'off' == $mod_data->options->enabled ) {
						echo ' style="display:none;"';
					}
					echo ' value="' . esc_textarea( __( 'Disable', 'edit-flow' ) ) . '" />';
					echo '</p>';
					wp_nonce_field( 'change-edit-flow-module-nonce', 'change-module-nonce-' . $mod_data->slug, false );
					echo '</form>';
					echo '</div>';
				}
			}
		}

		/**
		 * Given a form field and a description, prints either the error associated with the field or the description.
		 *
		 * @since 0.7
		 *
		 * @param string $field       The form field for which to check for an error.
		 * @param string $description Unlocalized string to display if there was no error with the given field.
		 */
		public function helper_print_error_or_description( $field, $description ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking for error display, no data modification.
			if ( isset( $_REQUEST['form-errors'][ $field ] ) ) :
				?>
			<div class="form-error">
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Display only, esc_html handles output. ?>
				<p><?php echo esc_html( $_REQUEST['form-errors'][ $field ] ); ?></p>
			</div>
			<?php else : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php
		endif;
		}

		/**
		 * Generate an option field to turn post type support on/off for a given module.
		 *
		 * @since 0.7
		 *
		 * @param object $module Edit Flow module we're generating the option field for.
		 * @param array  $args   Optional. Additional arguments.
		 */
		public function helper_option_custom_post_type( $module, $args = array() ) {

			$all_post_types    = array(
				'post' => __( 'Posts', 'edit-flow' ),
				'page' => __( 'Pages', 'edit-flow' ),
			);
			$custom_post_types = $this->get_supported_post_types_for_module();
			if ( count( $custom_post_types ) ) {
				foreach ( $custom_post_types as $custom_post_type => $args ) {
					$all_post_types[ $custom_post_type ] = $args->label;
				}
			}

			foreach ( $all_post_types as $post_type => $title ) {
				echo '<label for="' . esc_attr( $post_type ) . '">';
				echo '<input id="' . esc_attr( $post_type ) . '" name="'
				. esc_attr( $module->options_group_name ) . '[post_types][' . esc_attr( $post_type ) . ']"';
				if ( isset( $module->options->post_types[ $post_type ] ) ) {
					checked( $module->options->post_types[ $post_type ], 'on' );
				}
				// Defining post_type_supports in the functions.php file or similar should disable the checkbox.
				disabled( post_type_supports( $post_type, $module->post_type_support ), true );
				echo ' type="checkbox" />&nbsp;&nbsp;&nbsp;' . esc_html( $title ) . '</label>';
				// Leave a note to the admin as a reminder that add_post_type_support has been used somewhere in their code.
				if ( post_type_supports( $post_type, $module->post_type_support ) ) {
					/* translators: 1: post type, 2: post type support */
					echo '&nbsp&nbsp;&nbsp;<span class="description">' . esc_html( sprintf( __( 'Disabled because add_post_type_support( \'%1$s\', \'%2$s\' ) is included in a loaded file.', 'edit-flow' ), $post_type, $module->post_type_support ) ) . '</span>';
				}
				echo '<br />';
			}
		}

		/**
		 * Validation and sanitization on the settings field.
		 *
		 * This method is called automatically and doesn't need to be registered anywhere.
		 *
		 * @since 0.7
		 *
		 * @return false|void Returns false if validation fails, otherwise redirects.
		 */
		public function helper_settings_validate_and_save() {

			if ( ! isset( $_POST['action'], $_POST['_wpnonce'], $_POST['option_page'], $_POST['_wp_http_referer'], $_POST['edit_flow_module_name'], $_POST['submit'] ) || ! is_admin() ) {
				return false;
			}

			global $edit_flow;
			$module_name = sanitize_key( $_POST['edit_flow_module_name'] );

			if ( 'update' != $_POST['action']
			|| $_POST['option_page'] != $edit_flow->$module_name->module->options_group_name ) {
				return false;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces don't need sanitization, just verification.
			if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], $edit_flow->$module_name->module->options_group_name . '-options' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; uh?', 'edit-flow' ) );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization is handled by each module's settings_validate method.
			$new_options = ( isset( $_POST[ $edit_flow->$module_name->module->options_group_name ] ) ) ? $_POST[ $edit_flow->$module_name->module->options_group_name ] : array();

			// Only call the validation callback if it exists.
			if ( method_exists( $edit_flow->$module_name, 'settings_validate' ) ) {
				$new_options = $edit_flow->$module_name->settings_validate( $new_options );
			}

			// Cast our object and save the data.
			$new_options = (object) array_merge( (array) $edit_flow->$module_name->module->options, $new_options );
			$edit_flow->update_all_module_options( $edit_flow->$module_name->module->name, $new_options );

			// Redirect back to the settings page that was submitted without any previous messages.
			$referer = wp_get_referer();
			if ( ! $referer ) {
				$referer = admin_url( 'admin.php?page=' . $edit_flow->$module_name->module->settings_slug );
			}
			$goback = add_query_arg( 'message', 'settings-updated', remove_query_arg( array( 'message' ), $referer ) );
			wp_safe_redirect( $goback );
			exit;
		}
	}

}
