<?php
/**
 * Base class for Edit Flow modules.
 *
 * @package EditFlow
 */

if ( ! class_exists( 'EF_Module' ) ) {

	/**
	 * Base class any Edit Flow module should extend.
	 */
	class EF_Module {

		/**
		 * Published post statuses.
		 *
		 * @var array
		 */
		public $published_statuses = array(
			'publish',
			'future',
			'private',
		);

		/**
		 * URL to the module directory.
		 *
		 * @var string
		 */
		public $module_url;

		/**
		 * Module data object.
		 *
		 * @var object
		 */
		public $module;

		/**
		 * Constructor.
		 */
		public function __construct() {}

		/**
		 * Returns whether the current module is enabled.
		 *
		 * @since 0.9.1
		 *
		 * @return bool True if the module is enabled, false otherwise.
		 */
		public function is_enabled() {
			return 'on' === $this->module->options->enabled;
		}

		/**
		 * Returns whether the module with the given name is enabled.
		 *
		 * @since 0.7
		 *
		 * @param string $slug Slug of the module to check.
		 * @return bool True if the module is enabled, false otherwise.
		 */
		public function module_enabled( $slug ) {
			global $edit_flow;

			return isset( $edit_flow->$slug ) && $edit_flow->$slug->is_enabled();
		}

		/**
		 * Returns whether analytics has been enabled or not.
		 *
		 * It's only enabled if the site is a production WPVIP site.
		 *
		 * @since 0.10.0
		 *
		 * @return bool True if analytics is enabled, false otherwise.
		 */
		public function is_analytics_enabled() {
			// Check if the site is a production WPVIP site and only then enable it.
			$is_analytics_enabled = $this->is_vip_site( true );

			// Filter to disable it.
			$is_analytics_enabled = apply_filters( 'ef_should_analytics_be_enabled', $is_analytics_enabled );

			return $is_analytics_enabled;
		}

		/**
		 * Check if the site is a WPVIP site.
		 *
		 * @since 0.10.0
		 *
		 * @param bool $only_production Whether to only allow production sites to be considered WPVIP sites.
		 * @return bool True if it is a WPVIP site, false otherwise.
		 */
		protected function is_vip_site( $only_production = false ) {
			$is_vip_site = defined( 'VIP_GO_ENV' )
				&& defined( 'WPCOM_SANDBOXED' ) && constant( 'WPCOM_SANDBOXED' ) === false
				&& defined( 'FILES_CLIENT_SITE_ID' );

			if ( $only_production ) {
				$is_vip_site = $is_vip_site && defined( 'VIP_GO_ENV' ) && 'production' === constant( 'VIP_GO_ENV' );
			}

			return $is_vip_site;
		}

		/**
		 * Gets an array of allowed post types for a module.
		 *
		 * @return array Post-type-slug => post-type-label.
		 */
		public function get_all_post_types() {

			$allowed_post_types = array(
				'post' => __( 'Post', 'edit-flow' ),
				'page' => __( 'Page', 'edit-flow' ),
			);
			$custom_post_types  = $this->get_supported_post_types_for_module();

			foreach ( $custom_post_types as $custom_post_type => $args ) {
				$allowed_post_types[ $custom_post_type ] = $args->label;
			}
			return $allowed_post_types;
		}

		/**
		 * Cleans up the 'on' and 'off' for post types on a given module (so we don't get warnings all over).
		 *
		 * For every post type that doesn't explicitly have the 'on' value, turn it 'off'.
		 * If add_post_type_support() has been used anywhere (legacy support), inherit the state.
		 *
		 * @since 0.7
		 *
		 * @param array  $module_post_types Current state of post type options for the module.
		 * @param string $post_type_support What the feature is called for post_type_support (e.g. 'ef_calendar').
		 * @return array The setting for each post type, normalized based on rules.
		 */
		public function clean_post_type_options( $module_post_types = array(), $post_type_support = null ) {
			$normalized_post_type_options = array();
			$all_post_types               = array_keys( $this->get_all_post_types() );
			foreach ( $all_post_types as $post_type ) {
				if ( ( isset( $module_post_types[ $post_type ] ) && 'on' == $module_post_types[ $post_type ] ) || post_type_supports( $post_type, $post_type_support ) ) {
					$normalized_post_type_options[ $post_type ] = 'on';
				} else {
					$normalized_post_type_options[ $post_type ] = 'off';
				}
			}
			return $normalized_post_type_options;
		}

		/**
		 * Get all of the possible post types that can be used with a given module.
		 *
		 * @since 0.7.2
		 *
		 * @param object $module The full module.
		 * @return array An array of post type objects.
		 */
		public function get_supported_post_types_for_module( $module = null ) {

			$pt_args = array(
				'_builtin' => false,
				'public'   => true,
			);
			$pt_args = apply_filters( 'edit_flow_supported_module_post_types_args', $pt_args, $module );
			return get_post_types( $pt_args, 'objects' );
		}

		/**
		 * Collect all of the active post types for a given module.
		 *
		 * @since 0.7
		 *
		 * @param object $module Module's data.
		 * @return array All of the post types that are 'on'.
		 */
		public function get_post_types_for_module( $module ) {

			$post_types = array();
			if ( isset( $module->options->post_types ) && is_array( $module->options->post_types ) ) {
				foreach ( $module->options->post_types as $post_type => $value ) {
					if ( 'on' == $value ) {
						$post_types[] = $post_type;
					}
				}
			}
			return $post_types;
		}

		/**
		 * Get all of the currently available post statuses.
		 *
		 * This should be used in favor of calling $edit_flow->custom_status->get_custom_statuses() directly.
		 *
		 * @since 0.7
		 *
		 * @return array All of the post statuses that aren't a published state.
		 */
		public function get_post_statuses() {
			global $edit_flow;

			if ( $this->module_enabled( 'custom_status' ) ) {
				return $edit_flow->custom_status->get_custom_statuses();
			} else {
				return $this->get_core_post_statuses();
			}
		}

		/**
		 * Get core's 'draft' and 'pending' post statuses, but include our special attributes.
		 *
		 * @since 0.8.1
		 *
		 * @return array
		 */
		protected function get_core_post_statuses() {

			return array(
				(object) array(
					'name'        => __( 'Draft', 'edit-flow' ),
					'description' => '',
					'slug'        => 'draft',
					'position'    => 1,
				),
				(object) array(
					'name'        => __( 'Pending Review', 'edit-flow' ),
					'description' => '',
					'slug'        => 'pending',
					'position'    => 2,
				),
			);
		}

		/**
		 * Gets the name of the default custom status. If custom statuses are disabled,
		 * returns 'draft'.
		 *
		 * @return string Name of the status.
		 */
		public function get_default_post_status() {

			// Check if custom status module is enabled.
			$custom_status_module = EditFlow()->custom_status->module->options;

			if ( 'on' == $custom_status_module->enabled ) {
				return $custom_status_module->default_status;
			} else {
				return 'draft';
			}
		}

		/**
		 * Filter to all posts with a given post status (can be a custom status or a built-in status) and optional custom post type.
		 *
		 * @since 0.7
		 *
		 * @param string $slug      The slug for the post status to which to filter.
		 * @param string $post_type Optional post type to which to filter.
		 * @return string An edit.php link to all posts with the given post status and, optionally, the given post type.
		 */
		public function filter_posts_link( $slug, $post_type = 'post' ) {
			$filter_link = add_query_arg( 'post_status', $slug, get_admin_url( null, 'edit.php' ) );
			if ( 'post' != $post_type && in_array( $post_type, get_post_types( '', 'names' ) ) ) {
				$filter_link = add_query_arg( 'post_type', $post_type, $filter_link );
			}
			return $filter_link;
		}

		/**
		 * Enqueue any resources (CSS or JS) associated with datepicker functionality.
		 *
		 * @since 0.7
		 */
		public function enqueue_datepicker_resources() {

			wp_enqueue_script( 'jquery-ui-datepicker' );

			// Build script dependencies. Add wp-data for Gutenberg integration if available.
			$dependencies = array( 'jquery', 'jquery-ui-datepicker' );
			if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( get_post() ) ) {
				$dependencies[] = 'wp-data';
			}

			wp_enqueue_script( 'edit_flow-date_picker', EDIT_FLOW_URL . 'common/js/ef_date.js', $dependencies, EDIT_FLOW_VERSION, true );
			wp_add_inline_script( 'edit_flow-date_picker', sprintf( 'var ef_week_first_day =  %s;', wp_json_encode( get_option( 'start_of_week' ) ) ), 'before' );

			// Now styles.
			wp_enqueue_style( 'jquery-ui-datepicker', EDIT_FLOW_URL . 'common/css/jquery.ui.datepicker.css', array( 'wp-jquery-ui-dialog' ), EDIT_FLOW_VERSION, 'screen' );
			wp_enqueue_style( 'jquery-ui-theme', EDIT_FLOW_URL . 'common/css/jquery.ui.theme.css', false, EDIT_FLOW_VERSION, 'screen' );
		}

		/**
		 * Checks for the current post type.
		 *
		 * @since 0.7
		 *
		 * @return string|null The post type we've found, or null if no post type.
		 */
		public function get_current_post_type() {
			global $post, $typenow, $pagenow, $current_screen;
			// get_post() needs a variable.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading post type from REQUEST for context detection, not processing form data.
			$post_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : false;

			if ( $post && $post->post_type ) {
				$post_type = $post->post_type;
			} elseif ( $typenow ) {
				$post_type = $typenow;
			} elseif ( $current_screen && ! empty( $current_screen->post_type ) ) {
				$post_type = $current_screen->post_type;
			} elseif ( isset( $_REQUEST['post_type'] ) ) {
				$post_type = sanitize_key( $_REQUEST['post_type'] );
			} elseif ( 'post.php' == $pagenow
			&& $post_id
			&& ! empty( get_post( $post_id )->post_type ) ) {
				$post_type = get_post( $post_id )->post_type;
			} elseif ( 'edit.php' == $pagenow && empty( $_REQUEST['post_type'] ) ) {
				$post_type = 'post';
			} else {
				$post_type = null;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			return $post_type;
		}

		/**
		 * Wrapper for the get_user_meta() function so we can replace it if we need to.
		 *
		 * @since 0.7
		 *
		 * @param int    $user_id Unique ID for the user.
		 * @param string $key     Key to search against.
		 * @param bool   $string  Whether or not to return just one value.
		 * @return string|bool|array Whatever the stored value was.
		 */
		public function get_user_meta( $user_id, $key, $string = true ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound -- Legacy parameter name.

			$response = null;
			$response = apply_filters( 'ef_get_user_meta', $response, $user_id, $key, $string );
			if ( ! is_null( $response ) ) {
				return $response;
			}

			return get_user_meta( $user_id, $key, $string );
		}

		/**
		 * Wrapper for the update_user_meta() function so we can replace it if we need to.
		 *
		 * @since 0.7
		 *
		 * @param int               $user_id  Unique ID for the user.
		 * @param string            $key      Key to search against.
		 * @param string|bool|array $value    The value to store.
		 * @param string|bool|array $previous Previous value to replace.
		 * @return bool Whether we were successful in saving.
		 */
		public function update_user_meta( $user_id, $key, $value, $previous = null ) {

			$response = null;
			$response = apply_filters( 'ef_update_user_meta', $response, $user_id, $key, $value, $previous );
			if ( ! is_null( $response ) ) {
				return $response;
			}

			return update_user_meta( $user_id, $key, $value, $previous );
		}

		/**
		 * Take a status and a message, JSON encode and print.
		 *
		 * @since 0.7
		 *
		 * @param string $status    Whether it was a 'success' or an 'error'.
		 * @param string $message   Optional message to include.
		 * @param int    $http_code HTTP response code.
		 */
		protected function print_ajax_response( $status, $message = '', $http_code = 200 ) {
			header( 'Content-type: application/json;' );
			http_response_code( $http_code );
			echo wp_json_encode(
				array(
					'status'  => $status,
					'message' => $message,
				)
			);
			wp_die();
		}

		/**
		 * Whether or not the current page is a post management page.
		 *
		 * A post management page is where the module's functionality is actually
		 * needed, such as post editing pages (post.php, post-new.php) or post listing
		 * pages (edit.php) for supported post types.
		 *
		 * @since 0.7
		 * @since 0.10.0 Actually implemented instead of returning true. Renamed from
		 *               is_whitelisted_functional_view().
		 *
		 * @see https://github.com/Automattic/Edit-Flow/issues/351
		 *
		 * @param string $module_name (Optional) Module name to check against.
		 * @return bool Whether the current page is a post management page for the module.
		 */
		public function is_post_management_page( $module_name = null ) {
			global $pagenow, $edit_flow;

			// Only load on post editing and listing pages.
			$functional_pages = [ 'post.php', 'post-new.php', 'edit.php' ];
			if ( ! in_array( $pagenow, $functional_pages, true ) ) {
				return false;
			}

			// Get the current post type.
			$current_post_type = $this->get_current_post_type();
			if ( ! $current_post_type ) {
				return false;
			}

			// If a module name is specified, check if this post type is supported by that module.
			if ( $module_name && isset( $edit_flow->modules->$module_name ) ) {
				$module               = $edit_flow->modules->$module_name;
				$supported_post_types = $this->get_post_types_for_module( $module );
				if ( ! in_array( $current_post_type, $supported_post_types, true ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Whether or not the current page is an Edit Flow settings view (either main or module).
		 *
		 * Determination is based on $pagenow, $_GET['page'], and the module's $settings_slug.
		 * If there's no module name specified, it will return true against all Edit Flow settings views.
		 *
		 * @since 0.7
		 *
		 * @param string $module_name Optional module name to check against.
		 * @return bool Return true if it is.
		 */
		public function is_whitelisted_settings_view( $module_name = null ) {
			global $pagenow, $edit_flow;

			// All of the settings views are based on admin.php and a $_GET['page'] parameter.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checking page parameter for context, not processing form data.
			if ( 'admin.php' != $pagenow || ! isset( $_GET['page'] ) ) {
				return false;
			}

			// Load all of the modules that have a settings slug/ callback for the settings page.
			foreach ( $edit_flow->modules as $mod_name => $mod_data ) {
				if ( isset( $mod_data->options->enabled ) && 'on' == $mod_data->options->enabled && $mod_data->configure_page_cb ) {
					$settings_view_slugs[] = $mod_data->settings_slug;
				}
			}

			// The current page better be in the array of registered settings view slugs.
			if ( ! in_array( $_GET['page'], $settings_view_slugs ) ) {
				return false;
			}

			if ( $module_name && $edit_flow->modules->$module_name->settings_slug != $_GET['page'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			return true;
		}


		/**
		 * This is a hack, Hack, HACK!!!
		 *
		 * Encode all of the given arguments as a serialized array, and then base64_encode.
		 * Used to store extra data in a term's description field.
		 *
		 * @since 0.7
		 *
		 * @param array $args The arguments to encode.
		 * @return string Arguments encoded in base64.
		 */
		public function get_encoded_description( $args = array() ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for term description storage.
			return base64_encode( maybe_serialize( $args ) );
		}

		/**
		 * If given an encoded string from a term's description field,
		 * return an array of values. Otherwise, return the original string.
		 *
		 * @since 0.7
		 *
		 * @param string $string_to_unencode Possibly encoded string.
		 * @return array Array if string was encoded, otherwise the string as the 'description' field.
		 */
		public function get_unencoded_description( $string_to_unencode ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for term description retrieval.
			return maybe_unserialize( base64_decode( $string_to_unencode ) );
		}

		/**
		 * Get the publicly accessible URL for the module based on the filename.
		 *
		 * @since 0.7
		 *
		 * @param string $file File path for the module.
		 * @return string Publicly accessible URL for the module.
		 */
		public function get_module_url( $file ) {
			$module_url = plugins_url( '/', $file );
			return trailingslashit( $module_url );
		}

		/**
		 * Displays a list of users that can be selected!
		 *
		 * @since 0.7
		 *
		 * @todo Add pagination support for blogs with billions of users.
		 *
		 * @param array|null $selected Selected users.
		 * @param array|null $args     Optional arguments for the form.
		 */
		public function users_select_form( $selected = null, $args = null ) {

			// Set up arguments.
			$defaults    = array(
				'list_class' => 'ef-users-select-form',
				'input_id'   => 'ef-selected-users',
			);
			$parsed_args = wp_parse_args( $args, $defaults );
			extract( $parsed_args, EXTR_SKIP );

			$args = array(
				'capability' => 'publish_posts',
				'fields'     => array(
					'ID',
					'display_name',
					'user_nicename',
					'user_email',
				),
				'orderby'    => 'display_name',
			);
			$args = apply_filters( 'ef_users_select_form_get_users_args', $args );

			$users = get_users( $args );

			if ( ! is_array( $selected ) ) {
				$selected = array();
			}
			?>

			<?php if ( ! empty( $users ) ) : ?>
			<ul class="<?php echo esc_attr( $list_class ); ?>">
				<?php
				foreach ( $users as $user ) :
					$checked = ( in_array( $user->ID, $selected ) ) ? 'checked="checked"' : '';
					// Add a class to checkbox of current user so we know not to add them in notified list during notifiedMessage() js function.
					$current_user_class = ( get_current_user_id() == $user->ID ) ? 'class="post_following_list-current_user" ' : '';
					?>
					<li>
						<label for="<?php echo esc_attr( $input_id . '-' . $user->ID ); ?>">
							<div class="ef-user-subscribe-actions">
								<?php do_action( 'ef_user_subscribe_actions', $user->ID, $checked ); ?>
								<input type="checkbox" id="<?php echo esc_attr( $input_id . '-' . $user->ID ); ?>" name="<?php echo esc_attr( $input_id ); ?>[]" value="<?php echo esc_attr( $user->ID ); ?>"
																		<?php
																		echo esc_attr( $checked );
																		echo esc_attr( $current_user_class );
																		?>
								/>
							</div>

							<span class="ef-user_displayname"><?php echo esc_html( $user->display_name ); ?></span>
							<?php
							/**
							 * Filters the secondary user identifier shown in the notifications list.
							 *
							 * By default, shows user_nicename for unique identification without exposing email.
							 * Return user_email to show email addresses, or empty string to hide.
							 *
							 * @since 0.10.1
							 *
							 * @param string $identifier The secondary identifier to display.
							 * @param object $user       The user object.
							 */
							$secondary_identifier = apply_filters( 'ef_user_secondary_identifier', $user->user_nicename, $user );
							if ( ! empty( $secondary_identifier ) ) :
								?>
								<span class="ef-user_useremail"><?php echo esc_html( $secondary_identifier ); ?></span>
							<?php endif; ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
			<?php
		}

		/**
		 * Adds an array of capabilities to a role.
		 *
		 * @since 0.7
		 *
		 * @param string $role A standard WP user role like 'administrator' or 'author'.
		 * @param array  $caps One or more user caps to add.
		 */
		public function add_caps_to_role( $role, $caps ) {

			// In some contexts, we don't want to add caps to roles.
			if ( apply_filters( 'ef_kill_add_caps_to_role', false, $role, $caps ) ) {
				return;
			}

			global $wp_roles;

			if ( $wp_roles->is_role( $role ) ) {
				$role = get_role( $role );
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}

		/**
		 * Add settings help menus to our module screens if the values exist.
		 *
		 * Auto-registered in Edit_Flow::register_module().
		 *
		 * @since 0.7
		 */
		public function action_settings_help_menu() {

			$screen = get_current_screen();

			if ( ! method_exists( $screen, 'add_help_tab' ) ) {
				return;
			}

			if ( 'edit-flow_page_' . $this->module->settings_slug != $screen->id ) {
				return;
			}

			// Make sure we have all of the required values for our tab.
			if ( isset( $this->module->settings_help_tab['id'], $this->module->settings_help_tab['title'], $this->module->settings_help_tab['content'] ) ) {
				$screen->add_help_tab( $this->module->settings_help_tab );

				if ( isset( $this->module->settings_help_sidebar ) ) {
					$screen->set_help_sidebar( $this->module->settings_help_sidebar );
				}
			}
		}

		/**
		 * Upgrade the term descriptions for all of the terms in a given taxonomy.
		 *
		 * @param string $taxonomy The taxonomy to upgrade.
		 */
		public function upgrade_074_term_descriptions( $taxonomy ) {
			$args = array(
				'hide_empty' => false,
			);
			// This is migration code, so it's being left as is for now.
			// phpcs:ignore WordPress.WP.DeprecatedParameters.Get_termsParam2Found
			$terms = get_terms( $taxonomy, $args );
			foreach ( $terms as $term ) {
				// If we can detect that this term already follows the new scheme, let's skip it.
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for term description retrieval.
				$maybe_serialized = base64_decode( $term->description );
				if ( is_serialized( $maybe_serialized ) ) {
					continue;
				}

				$description_args = array();
				// This description has been JSON-encoded, so let's decode it.
				if ( 0 === strpos( $term->description, '{' ) ) {
					$string_to_unencode = stripslashes( htmlspecialchars_decode( $term->description ) );
					$unencoded_array    = json_decode( $string_to_unencode, true );
					// Only continue processing if it actually was an array. Otherwise, set to the original string.
					if ( is_array( $unencoded_array ) ) {
						foreach ( $unencoded_array as $key => $value ) {
							// html_entity_decode only works on strings but sometimes we store nested arrays.
							if ( ! is_array( $value ) ) {
								$description_args[ $key ] = html_entity_decode( $value, ENT_QUOTES );
							} else {
								$description_args[ $key ] = $value;
							}
						}
					}
				} else {
					$description_args['description'] = $term->description;
				}
				$new_description = $this->get_encoded_description( $description_args );
				wp_update_term( $term->term_id, $taxonomy, array( 'description' => $new_description ) );
			}
		}
	}

}
