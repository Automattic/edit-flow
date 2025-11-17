<?php
/**
 * class EF_Custom_Status
 * Custom statuses make it simple to define the different stages in your publishing workflow.
 *
 * @todo for v0.7
 * - Improve the copy
 * - Thoroughly test what happens when the default post statuses 'Draft' and 'Pending Review' no longer exist
 * - Ensure all of the form processing uses our messages functionality
 */

if ( ! class_exists( 'EF_Custom_Status' ) ) {

	class EF_Custom_Status extends EF_Module {

		public $module;

		private $custom_statuses_cache = [];

		// This is taxonomy name used to store all our custom statuses
		// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
		const taxonomy_key = 'post_status';

		/**
		 * Register the module with Edit Flow but don't do anything else
		 */
		public function __construct() {

			$this->module_url = $this->get_module_url( __FILE__ );
			// Register the module with Edit Flow
			$args         = [
				'title'                 => __( 'Custom Statuses', 'edit-flow' ),
				'short_description'     => __( 'Create custom post statuses to define the stages of your workflow.', 'edit-flow' ),
				'extended_description'  => __( 'Create your own post statuses to add structure your publishing workflow. You can change existing or add new ones anytime, and drag and drop to change their order.', 'edit-flow' ),
				'module_url'            => $this->module_url,
				'img_url'               => $this->module_url . 'lib/custom_status_s128.png',
				'slug'                  => 'custom-status',
				'default_options'       => [
					'enabled'              => 'on',
					'default_status'       => 'pitch',
					'always_show_dropdown' => 'off',
					'post_types'           => [
						'post' => 'on',
						'page' => 'on',
					],
				],
				'post_type_support'     => 'ef_custom_statuses', // This has been plural in all of our docs
				'configure_page_cb'     => 'print_configure_view',
				'configure_link_text'   => __( 'Edit Statuses', 'edit-flow' ),
				'messages'              => [
					'status-added'            => __( 'Post status created.', 'edit-flow' ),
					'status-missing'          => __( "Post status doesn't exist.", 'edit-flow' ),
					'default-status-changed'  => __( 'Default post status has been changed.', 'edit-flow' ),
					'term-updated'            => __( 'Post status updated.', 'edit-flow' ),
					'status-deleted'          => __( 'Post status deleted.', 'edit-flow' ),
					'status-position-updated' => __( 'Status order updated.', 'edit-flow' ),
				],
				'autoload'              => false,
				'settings_help_tab'     => [
					'id'      => 'ef-custom-status-overview',
					'title'   => __( 'Overview', 'edit-flow' ),
					'content' => __( '<p>Edit Flow’s custom statuses allow you to define the most important stages of your editorial workflow. Out of the box, WordPress only offers “Draft” and “Pending Review” as post states. With custom statuses, you can create your own post states like “In Progress”, “Pitch”, or “Waiting for Edit” and keep or delete the originals. You can also drag and drop statuses to set the best order for your workflow.</p><p>Custom statuses are fully integrated into the rest of Edit Flow and the WordPress admin. On the calendar and story budget, you can filter your view to see only posts of a specific post state. Furthermore, email notifications can be sent to a specific group of users when a post changes state.</p>', 'edit-flow' ),
				],
				'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/custom-statuses/">Custom Status Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/Automattic/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
			];
			$this->module = EditFlow()->register_module( 'custom_status', $args );
		}

		/**
		 * Initialize the EF_Custom_Status class if the module is active
		 */
		public function init() {
			global $edit_flow;

			// Register custom statuses as a taxonomy
			$this->register_custom_statuses();

			// Register our settings
			add_action( 'admin_init', [ $this, 'register_settings' ] );

			if ( ! $this->disable_custom_statuses_for_post_type() ) {
				// Load CSS and JS resources that we probably need in the admin page
				add_action( 'admin_enqueue_scripts', [ $this, 'action_admin_enqueue_scripts' ] );

				// Assets for block editor UI.
				add_action( 'enqueue_block_editor_assets', [ $this, 'load_scripts_for_block_editor' ] );

				// Assets for iframed block editor and editor UI.
				add_action( 'enqueue_block_editor_assets', [ $this, 'load_styles_for_block_editor' ] );
			}

			add_action( 'admin_notices', [ $this, 'no_js_notice' ] );
			add_action( 'admin_print_scripts', [ $this, 'post_admin_header' ] );

			// Add custom statuses to the post states.
			add_filter( 'display_post_states', [ $this, 'add_status_to_post_states' ], 10, 2 );

			// Methods for handling the actions of creating, making default, and deleting post stati
			add_action( 'admin_init', [ $this, 'handle_add_custom_status' ] );
			add_action( 'admin_init', [ $this, 'handle_edit_custom_status' ] );
			add_action( 'admin_init', [ $this, 'handle_make_default_custom_status' ] );
			add_action( 'admin_init', [ $this, 'handle_delete_custom_status' ] );
			add_action( 'wp_ajax_update_status_positions', [ $this, 'handle_ajax_update_status_positions' ] );
			add_action( 'wp_ajax_inline_save_status', [ $this, 'ajax_inline_save_status' ] );

			// These seven-ish methods are hacks for fixing bugs in WordPress core
			add_action( 'admin_init', [ $this, 'check_timestamp_on_publish' ] );
			add_filter( 'wp_insert_post_data', [ $this, 'fix_custom_status_timestamp' ], 10, 2 );
			add_filter( 'wp_insert_post_data', [ $this, 'maybe_keep_post_name_empty' ], 10, 2 );
			add_filter( 'pre_wp_unique_post_slug', [ $this, 'fix_unique_post_slug' ], 10, 6 );
			add_filter( 'preview_post_link', [ $this, 'fix_preview_link_part_one' ] );
			add_filter( 'post_link', [ $this, 'fix_preview_link_part_two' ], 10, 3 );
			add_filter( 'page_link', [ $this, 'fix_preview_link_part_two' ], 10, 3 );
			add_filter( 'post_type_link', [ $this, 'fix_preview_link_part_two' ], 10, 3 );
			add_filter( 'preview_post_link', [ $this, 'fix_preview_link_part_three' ], 11, 2 );
			add_filter( 'get_sample_permalink', [ $this, 'fix_get_sample_permalink' ], 10, 5 );
			add_filter( 'get_sample_permalink_html', [ $this, 'fix_get_sample_permalink_html' ], 10, 5 );
			add_filter( 'post_row_actions', [ $this, 'fix_post_row_actions' ], 10, 2 );
			add_filter( 'page_row_actions', [ $this, 'fix_post_row_actions' ], 10, 2 );

			// Pagination for custom post statuses when previewing posts
			add_filter( 'wp_link_pages_link', [ $this, 'modify_preview_link_pagination_url' ], 10, 2 );
		}

		/**
		 * Create the default set of custom statuses the first time the module is loaded
		 *
		 * @since 0.7
		 */
		public function install() {

			$default_terms = [
				[
					'term' => __( 'Pitch', 'edit-flow' ),
					'args' => [
						'slug'        => 'pitch',
						'description' => __( 'Idea proposed; waiting for acceptance.', 'edit-flow' ),
						'position'    => 1,
					],
				],
				[
					'term' => __( 'Assigned', 'edit-flow' ),
					'args' => [
						'slug'        => 'assigned',
						'description' => __( 'Post idea assigned to writer.', 'edit-flow' ),
						'position'    => 2,
					],
				],
				[
					'term' => __( 'In Progress', 'edit-flow' ),
					'args' => [
						'slug'        => 'in-progress',
						'description' => __( 'Writer is working on the post.', 'edit-flow' ),
						'position'    => 3,
					],
				],
				[
					'term' => __( 'Draft', 'edit-flow' ),
					'args' => [
						'slug'        => 'draft',
						'description' => __( 'Post is a draft; not ready for review or publication.', 'edit-flow' ),
						'position'    => 4,
					],
				],
				[
					'term' => __( 'Pending Review' ),
					'args' => [
						'slug'        => 'pending',
						'description' => __( 'Post needs to be reviewed by an editor.', 'edit-flow' ),
						'position'    => 5,
					],
				],
			];

			// Okay, now add the default statuses to the db if they don't already exist
			foreach ( $default_terms as $term ) {
				if ( ! term_exists( $term['term'], self::taxonomy_key ) ) {
					$this->add_custom_status( $term['term'], $term['args'] );
				}
			}
		}

		/**
		 * Upgrade our data in case we need to
		 *
		 * @since 0.7
		 */
		public function upgrade( $previous_version ) {
			global $edit_flow;

			// Upgrade path to v0.7
			if ( version_compare( $previous_version, '0.7', '<' ) ) {
				// Migrate dropdown visibility option
				$dropdown_visible = get_option( 'edit_flow_status_dropdown_visible' );
				if ( $dropdown_visible ) {
					$dropdown_visible = 'on';
				} else {
					$dropdown_visible = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'always_show_dropdown', $dropdown_visible );
				delete_option( 'edit_flow_status_dropdown_visible' );
				// Migrate default status option
				$default_status = get_option( 'edit_flow_custom_status_default_status' );
				if ( $default_status ) {
					$edit_flow->update_module_option( $this->module->name, 'default_status', $default_status );
				}
				delete_option( 'edit_flow_custom_status_default_status' );

				// Technically we've run this code before so we don't want to auto-install new data
				$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
			}
			// Upgrade path to v0.7.4
			if ( version_compare( $previous_version, '0.7.4', '<' ) ) {
				// Custom status descriptions become base64_encoded, instead of maybe json_encoded.
				$this->upgrade_074_term_descriptions( self::taxonomy_key );
			}
		}

		/**
		 * Makes the call to register_post_status to register the user's custom statuses.
		 * Also unregisters draft and pending, in case the user doesn't want them.
		 */
		public function register_custom_statuses() {
			global $wp_post_statuses;

			if ( $this->disable_custom_statuses_for_post_type() ) {
				return;
			}

			// Register new taxonomy so that we can store all our fancy new custom statuses (or is it stati?)
			if ( ! taxonomy_exists( self::taxonomy_key ) ) {
				$args = [
					'hierarchical'          => false,
					'update_count_callback' => '_update_post_term_count',
					'label'                 => false,
					'query_var'             => false,
					'rewrite'               => false,
					'show_ui'               => false,
				];
				register_taxonomy( self::taxonomy_key, 'post', $args );
			}

			if ( function_exists( 'register_post_status' ) ) {
				// Users can delete draft and pending statuses if they want, so let's get rid of them
				// They'll get re-added if the user hasn't "deleted" them
				unset( $wp_post_statuses['draft'] );
				unset( $wp_post_statuses['pending'] );

				$custom_statuses = $this->get_custom_statuses();

				// Unfortunately, register_post_status() doesn't accept a
				// post type argument, so we have to register the post
				// statuses for all post types. This results in
				// all post statuses for a post type appearing at the top
				// of manage posts if there is a post with the status
				foreach ( $custom_statuses as $status ) {
					register_post_status( $status->slug, [
						'label'       => $status->name,
						'protected'   => true,
						'_builtin'    => false,
						'label_count' => _n_noop( "{$status->name} <span class='count'>(%s)</span>", "{$status->name} <span class='count'>(%s)</span>" ),
					] );
				}
			}
		}

		/**
		 * Whether custom post statuses should be disabled for this post type.
		 * Used to stop custom statuses from being registered for post types that don't support them.
		 *
		 * @since 0.7.5
		 *
		 * @return bool
		 */
		public function disable_custom_statuses_for_post_type( $post_type = null ) {
			global $pagenow;

			// Only allow deregistering on 'edit.php' and 'post.php'
			if ( ! in_array( $pagenow, [ 'edit.php', 'post.php', 'post-new.php' ] ) ) {
				return false;
			}

			if ( is_null( $post_type ) ) {
				$post_type = $this->get_current_post_type();
			}

			if ( $post_type && ! in_array( $post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Enqueue Javascript resources that we need in the admin:
		 * - Primary use of Javascript is to manipulate the post status dropdown on Edit Post and Manage Posts
		 * - jQuery Sortable plugin is used for drag and dropping custom statuses
		 * - We have other custom code for Quick Edit and JS niceties
		 */
		public function action_admin_enqueue_scripts() {
			// Load Javascript we need to use on the configuration views (jQuery Sortable and Quick Edit)
			if ( $this->is_whitelisted_settings_view( $this->module->name ) ) {
				wp_enqueue_script( 'jquery-ui-sortable' );
				wp_enqueue_script( 'edit-flow-custom-status-configure', $this->module_url . 'lib/custom-status-configure.js', [ 'jquery', 'jquery-ui-sortable', 'edit-flow-settings-js' ], EDIT_FLOW_VERSION, true );

				wp_localize_script( 'edit-flow-custom-status-configure', '__ef_localize_custom_status_configure', [
					'delete_status_string' => __( 'Are you sure you want to delete the post status? All posts with this status will be assigned to the default status.', 'edit-flow' ),
				] );
			}

			// Custom javascript to modify the post status dropdown where it shows up
			if ( $this->is_whitelisted_page() ) {
				wp_enqueue_script( 'edit_flow-custom_status', $this->module_url . 'lib/custom-status.js', [ 'jquery', 'post' ], EDIT_FLOW_VERSION, true );
				wp_localize_script('edit_flow-custom_status', '__ef_localize_custom_status', [
					'no_change' => esc_html__( '&mdash; No Change &mdash;', 'edit-flow' ),
					'published' => esc_html__( 'Published', 'edit-flow' ),
					'save_as'   => esc_html__( 'Save as', 'edit-flow' ),
					'save'      => esc_html__( 'Save', 'edit-flow' ),
					'edit'      => esc_html__( 'Edit', 'edit-flow' ),
					'ok'        => esc_html__( 'OK', 'edit-flow' ),
					'cancel'    => esc_html__( 'Cancel', 'edit-flow' ),
				] );
			}
		}

		public function load_scripts_for_block_editor() {
			global $post;

			wp_enqueue_script( 'edit-flow-block-custom-status-script', EDIT_FLOW_URL . 'dist/custom-status.build.js', [ 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-plugins', 'wp-components' ], EDIT_FLOW_VERSION );

			$custom_statuses = apply_filters( 'ef_custom_status_list', $this->get_custom_statuses(), $post );

			wp_localize_script( 'edit-flow-block-custom-status-script', 'EditFlowCustomStatuses', array_values( $custom_statuses ) );
		}

		public function load_styles_for_block_editor() {
			wp_enqueue_style( 'edit-flow-block-custom-status-styles', EDIT_FLOW_URL . 'dist/custom-status.editor.build.css', false, EDIT_FLOW_VERSION );
		}

		/**
		 * Displays a notice to users if they have JS disabled
		 * Javascript is needed for custom statuses to be fully functional
		 */
		public function no_js_notice() {
			if ( $this->is_whitelisted_page() ) :
				?>
			<style type="text/css">
			/* Hide post status dropdown by default in case of JS issues **/
			label[for=post_status],
			#post-status-display,
			#post-status-select,
			#publish {
				display: none;
			}
			</style>
			<div class="update-nag hide-if-js">
				<?php _e( '<strong>Note:</strong> Your browser does not support JavaScript or has JavaScript disabled. You will not be able to access or change the post status.', 'edit-flow' ); ?>
			</div>
				<?php
			endif;
		}

		/**
		 * Check whether custom status stuff should be loaded on this page
		 *
		 * @todo migrate this to the base module class
		 */
		public function is_whitelisted_page() {
			global $pagenow;

			if ( ! in_array( $this->get_current_post_type(), $this->get_post_types_for_module( $this->module ) ) ) {
				return false;
			}

			$post_type_obj = get_post_type_object( $this->get_current_post_type() );

			if ( ! current_user_can( $post_type_obj->cap->edit_posts ) ) {
				return false;
			}

			// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
			return in_array( $pagenow, [ 'post.php', 'edit.php', 'post-new.php', 'page.php', 'edit-pages.php', 'page-new.php' ] );
		}

		/**
		 * Adds all necessary javascripts to make custom statuses work
		 *
		 * @todo Support private and future posts on edit.php view
		 */
		public function post_admin_header() {
			global $post, $edit_flow, $pagenow, $current_user;

			if ( $this->disable_custom_statuses_for_post_type() ) {
				return;
			}

			// Get current user
			wp_get_current_user();

			// Only add the script to Edit Post and Edit Page pages -- don't want to bog down the rest of the admin with unnecessary javascript
			if ( $this->is_whitelisted_page() ) {

				$custom_statuses = $this->get_custom_statuses();

				// $selected can be empty, but must be set because it's used as a JS variable
				$selected      = '';
				$selected_name = '';

				if ( ! empty( $post ) ) {
					// Get the status of the current post
					if ( 0 == $post->ID || 'auto-draft' == $post->post_status || 'edit.php' == $pagenow ) {
						// TODO: check to make sure that the default exists
						$selected = $this->get_default_custom_status()->slug;
					} else {
						$selected = $post->post_status;
					}

					// Get the label of current status
					foreach ( $custom_statuses as $status ) {
						if ( $status->slug == $selected ) {
							$selected_name = $status->name;
						}
					}
				}

				$custom_statuses = apply_filters( 'ef_custom_status_list', $custom_statuses, $post );

				// All right, we want to set up the JS var which contains all custom statuses
				$all_statuses = [];

				// The default statuses from WordPress
				$all_statuses[] = [
					'name'        => __( 'Published', 'edit-flow' ),
					'slug'        => 'publish',
					'description' => '',
				];
				$all_statuses[] = [
					'name'        => __( 'Privately Published', 'edit-flow' ),
					'slug'        => 'private',
					'description' => '',
				];
				$all_statuses[] = [
					'name'        => __( 'Scheduled', 'edit-flow' ),
					'slug'        => 'future',
					'description' => '',
				];

				// Load the custom statuses
				foreach ( $custom_statuses as $status ) {
					$all_statuses[] = [
						'name'        => esc_js( $status->name ),
						'slug'        => esc_js( $status->slug ),
						'description' => esc_js( $status->description ),
					];
				}

				$always_show_dropdown = ( 'on' == $this->module->options->always_show_dropdown ) ? 1 : 0;

				$post_type_obj = get_post_type_object( $this->get_current_post_type() );

				// Now, let's print the JS vars
				?>
			<script type="text/javascript">
				var custom_statuses = <?php echo json_encode( $all_statuses ); ?>;
				var ef_default_custom_status = '<?php echo esc_js( $this->get_default_custom_status()->slug ); ?>';
				var current_status = '<?php echo esc_js( $selected ); ?>';
				var current_status_name = '<?php echo esc_js( $selected_name ); ?>';
				var status_dropdown_visible = <?php echo esc_js( $always_show_dropdown ); ?>;
				var current_user_can_publish_posts = <?php echo current_user_can( $post_type_obj->cap->publish_posts ) ? 1 : 0; ?>;
				var current_user_can_edit_published_posts = <?php echo current_user_can( $post_type_obj->cap->edit_published_posts ) ? 1 : 0; ?>;
			</script>

				<?php

			}
		}

		/**
		 * Adds a new custom status as a term in the wp_terms table.
		 * Basically a wrapper for the wp_insert_term class.
		 *
		 * The arguments decide how the term is handled based on the $args parameter.
		 * The following is a list of the available overrides and the defaults.
		 *
		 * 'description'. There is no default. If exists, will be added to the database
		 * along with the term. Expected to be a string.
		 *
		 * 'slug'. Expected to be a string. There is no default.
		 *
		 * @param int|string $term The status to add or update
		 * @param array|string $args Change the values of the inserted term
		 * @return array|WP_Error $response The Term ID and Term Taxonomy ID
		 */
		public function add_custom_status( $term, $args = [] ) {
			$slug = ( ! empty( $args['slug'] ) ) ? $args['slug'] : sanitize_title( $term );
			unset( $args['slug'] );
			$encoded_description = $this->get_encoded_description( $args );
			$response            = wp_insert_term( $term, self::taxonomy_key, [
				'slug'        => $slug,
				'description' => $encoded_description,
			] );

			// Reset our internal object cache
			$this->custom_statuses_cache = [];

			return $response;
		}

		/**
		 * Update an existing custom status
		 *
		 * @param int @status_id ID for the status
		 * @param array $args Any arguments to be updated
		 * @return object $updated_status Newly updated status object
		 */
		public function update_custom_status( $status_id, $args = [] ) {
			global $edit_flow;

			$old_status = $this->get_custom_status_by( 'id', $status_id );
			if ( ! $old_status || is_wp_error( $old_status ) ) {
				return new WP_Error( 'invalid', __( "Custom status doesn't exist.", 'edit-flow' ) );
			}

			// Reset our internal object cache
			$this->custom_statuses_cache = [];

			// Prevent user from changing draft name or slug
			if ( 'draft' === $old_status->slug
			&& (
				( isset( $args['name'] ) && $args['name'] !== $old_status->name )
				||
				( isset( $args['slug'] ) && $args['slug'] !== $old_status->slug )
			) ) {
				return new WP_Error( 'invalid', __( 'Changing the name and slug of "Draft" is not allowed', 'edit-flow' ) );
			}

			// If the name was changed, we need to change the slug
			if ( isset( $args['name'] ) && $args['name'] != $old_status->name ) {
				$args['slug'] = sanitize_title( $args['name'] );
			}

			// Reassign posts to new status slug if the slug changed and isn't restricted
			if ( isset( $args['slug'] ) && $args['slug'] != $old_status->slug && ! $this->is_restricted_status( $old_status->slug ) ) {
				$new_status = $args['slug'];
				$this->reassign_post_status( $old_status->slug, $new_status );

				$default_status = $this->get_default_custom_status()->slug;
				if ( $old_status->slug == $default_status ) {
					$edit_flow->update_module_option( $this->module->name, 'default_status', $new_status );
				}
			}
			// We're encoding metadata that isn't supported by default in the term's description field
			$args_to_encode                = [];
			$args_to_encode['description'] = ( isset( $args['description'] ) ) ? $args['description'] : $old_status->description;
			$args_to_encode['position']    = ( isset( $args['position'] ) ) ? $args['position'] : $old_status->position;
			$encoded_description           = $this->get_encoded_description( $args_to_encode );
			$args['description']           = $encoded_description;

			$updated_status_array = wp_update_term( $status_id, self::taxonomy_key, $args );
			$updated_status       = $this->get_custom_status_by( 'id', $updated_status_array['term_id'] );

			return $updated_status;
		}

		/**
		 * Deletes a custom status from the wp_terms table.
		 *
		 * Partly a wrapper for the wp_delete_term function.
		 * BUT, also reassigns posts that currently have the deleted status assigned.
		 */
		public function delete_custom_status( $status_id, $args = [], $reassign = '' ) {
			global $edit_flow;
			// Reassign posts to alternate status

			// Get slug for the old status
			$old_status = $this->get_custom_status_by( 'id', $status_id )->slug;

			if ( $reassign == $old_status ) {
				return new WP_Error( 'invalid', __( 'Cannot reassign to the status you want to delete', 'edit-flow' ) );
			}

			// Reset our internal object cache
			$this->custom_statuses_cache = [];

			if ( ! $this->is_restricted_status( $old_status ) && 'draft' !== $old_status ) {
				$default_status = $this->get_default_custom_status()->slug;
				// If new status in $reassign, use that for all posts of the old_status
				if ( ! empty( $reassign ) ) {
					$new_status = $this->get_custom_status_by( 'id', $reassign )->slug;
				} else {
					$new_status = $default_status;
				}
				if ( $old_status == $default_status && $this->get_custom_status_by( 'slug', 'draft' ) ) { // Deleting default status
					$new_status = 'draft';
					$edit_flow->update_module_option( $this->module->name, 'default_status', $new_status );
				}

				$this->reassign_post_status( $old_status, $new_status );

				return wp_delete_term( $status_id, self::taxonomy_key, $args );
			} else {
				return new WP_Error( 'restricted', __( 'Restricted status ', 'edit-flow' ) . '(' . $this->get_custom_status_by( 'id', $status_id )->name . ')' );
			}
		}

		/**
		 * Get all custom statuses as an ordered array
		 *
		 * @param array|string $statuses
		 * @param array $args
		 * @return array $statuses All of the statuses
		 */
		public function get_custom_statuses( $args = [] ) {
			global $wp_post_statuses;

			if ( $this->disable_custom_statuses_for_post_type() ) {
				return $this->get_core_post_statuses();
			}

			// Internal object cache for repeat requests
			$arg_hash = md5( serialize( $args ) );
			if ( ! empty( $this->custom_statuses_cache[ $arg_hash ] ) ) {
				return $this->custom_statuses_cache[ $arg_hash ];
			}

			// Handle if the requested taxonomy doesn't exist
			$statuses = get_terms( [
				'taxonomy'   => self::taxonomy_key,
				'hide_empty' => false,
			]);

			if ( is_wp_error( $statuses ) || empty( $statuses ) ) {
				$statuses = [];
			}

			// Expand and order the statuses
			$ordered_statuses = [];
			$hold_to_end      = [];
			foreach ( $statuses as $key => $status ) {
				// Unencode and set all of our psuedo term meta because we need the position if it exists
				$unencoded_description = $this->get_unencoded_description( $status->description );
				if ( is_array( $unencoded_description ) ) {
					foreach ( $unencoded_description as $key => $value ) {
						$status->$key = $value;
					}
				}
				// We require the position key later on (e.g. management table)
				if ( ! isset( $status->position ) ) {
					$status->position = false;
				}
				// Only add the status to the ordered array if it has a set position and doesn't conflict with another key
				// Otherwise, hold it for later
				if ( $status->position && ! array_key_exists( $status->position, $ordered_statuses ) ) {
					$ordered_statuses[ (int) $status->position ] = $status;
				} else {
					$hold_to_end[] = $status;
				}
			}
			// Sort the items numerically by key
			ksort( $ordered_statuses, SORT_NUMERIC );
			// Append all of the statuses that didn't have an existing position
			foreach ( $hold_to_end as $unpositioned_status ) {
				$ordered_statuses[] = $unpositioned_status;
			}

			$this->custom_statuses_cache[ $arg_hash ] = $ordered_statuses;

			return $ordered_statuses;
		}

		/**
		 * Returns the a single status object based on ID, title, or slug
		 *
		 * @param string|int $string_or_int The status to search for, either by slug, name or ID
		 * @return object|WP_Error $status The object for the matching status
		 */
		public function get_custom_status_by( $field, $value ) {

			if ( ! in_array( $field, [ 'id', 'slug', 'name' ] ) ) {
				return false;
			}

			if ( 'id' == $field ) {
				$field = 'term_id';
			}

			$custom_statuses = $this->get_custom_statuses();
			$custom_status   = wp_filter_object_list( $custom_statuses, [ $field => $value ] );

			if ( ! empty( $custom_status ) ) {
				return array_shift( $custom_status );
			} else {
				return false;
			}
		}

		/**
		 * Get the term object for the default custom post status
		 *
		 * @return object $default_status Default post status object
		 */
		public function get_default_custom_status() {
			$default_status = $this->get_custom_status_by( 'slug', $this->module->options->default_status );
			if ( ! $default_status ) {
				$custom_statuses = $this->get_custom_statuses();
				$default_status  = array_shift( $custom_statuses );
			}
			return $default_status;
		}

		/**
		 * Assign new statuses to posts using value provided or the default
		 *
		 * @param string $old_status Slug for the old status
		 * @param string $new_status Slug for the new status
		 */
		public function reassign_post_status( $old_status, $new_status = '' ) {
			global $wpdb;

			if ( empty( $new_status ) ) {
				$new_status = $this->get_default_custom_status()->slug;
			}

			// Make the database call
			$result = $wpdb->update( $wpdb->posts, [ 'post_status' => $new_status ], [ 'post_status' => $old_status ], [ '%s' ] );
		}

		/**
		 * Display our custom post statuses in post listings when needed.
		 *
		 * @param array   $post_states An array of post display states.
		 * @param WP_Post $post The current post object.
		 *
		 * @return array $post_states
		 */
		public function add_status_to_post_states( $post_states, $post ) {
			if ( ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ), true ) ) {
				// Return early if this post type doesn't support custom statuses.
				return $post_states;
			}

			$post_status = get_post_status_object( get_post_status( $post->ID ) );

			$filtered_status = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : '';
			if ( $filtered_status === $post_status->name ) {
				// No need to display the post status if a specific status was already requested.
				return $post_states;
			}

			$statuses_to_ignore = [ 'future', 'trash', 'publish' ];
			if ( in_array( $post_status->name, $statuses_to_ignore, true ) ) {
				// Let WP core handle these more gracefully.
				return $post_states;
			}

			// Add the post status to display. Will also ensure the same status isn't shown twice.
			$post_states[ $post_status->name ] = $post_status->label;

			return $post_states;
		}

		/**
		 * Determines whether the slug indicated belongs to a restricted status or not
		 *
		 * @param string $slug Slug of the status
		 * @return bool $restricted True if restricted, false if not
		 */
		public function is_restricted_status( $slug ) {

			switch ( $slug ) {
				case 'publish':
				case 'private':
				case 'future':
				case 'new':
				case 'inherit':
				case 'auto-draft':
				case 'trash':
					$restricted = true;
					break;

				default:
					$restricted = false;
					break;
			}
			return $restricted;
		}

		/**
		 * Handles a form's POST request to add a custom status
		 *
		 * @since 0.7
		 */
		public function handle_add_custom_status() {

			// Check that the current POST request is our POST request
			if ( ! isset( $_POST['submit'], $_GET['page'], $_POST['action'] )
			|| $_GET['page'] != $this->module->settings_slug || 'add-new' != $_POST['action'] ) {
				return;
			}

			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'custom-status-add-nonce' ) ) {
				wp_die( esc_html( $this->module->messages['nonce-failed'] ) );
			}

			// Validate and sanitize the form data
			$status_name        = isset( $_POST['status_name'] ) ? sanitize_text_field( trim( $_POST['status_name'] ) ) : '';
			$status_slug        = sanitize_title( $status_name );
			$status_description = isset( $_POST['status_description'] ) ? stripslashes( wp_filter_nohtml_kses( trim( $_POST['status_description'] ) ) ) : '';

			/**
			 * Form validation
			 * - Name is required and can't conflict with an existing name or slug
			 * - Description is optional
			 */
			$_REQUEST['form-errors'] = [];
			// Check if name field was filled in
			if ( empty( $status_name ) ) {
				$_REQUEST['form-errors']['name'] = __( 'Please enter a name for the status', 'edit-flow' );
			}
			// Check that the name isn't numeric
			if ( 0 != (int) $status_name ) {
				$_REQUEST['form-errors']['name'] = __( 'Please enter a valid, non-numeric name for the status.', 'edit-flow' );
			}
			// Check that the status name doesn't exceed 20 chars
			if ( strlen( $status_name ) > 20 ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
			}
			// Check to make sure the status doesn't already exist as another term because otherwise we'd get a weird slug
			if ( term_exists( $status_slug, self::taxonomy_key ) ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name conflicts with existing term. Please choose another.', 'edit-flow' );
			}
			// Check to make sure the name is not restricted
			if ( $this->is_restricted_status( strtolower( $status_slug ) ) ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name is restricted. Please choose another name.', 'edit-flow' );
			}

			// If there were any form errors, kick out and return them
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( count( $_REQUEST['form-errors'] ) ) {
				$_REQUEST['error'] = 'form-error';
				return;
			}

			// Try to add the status
			$status_args = [
				'description' => $status_description,
				'slug'        => $status_slug,
			];
			$return      = $this->add_custom_status( $status_name, $status_args );
			if ( is_wp_error( $return ) ) {
				/* translators: %s: error message */
				wp_die( esc_html( sprintf( __( 'Could not add status: %s', 'edit-flow' ), $return->get_error_message() ) ) );
			}
			// Redirect if successful
			$redirect_url = $this->get_link( [ 'message' => 'status-added' ] );
			wp_redirect( $redirect_url );
			exit;
		}

		/**
		 * Handles a POST request to edit an custom status
		 *
		 * @since 0.7
		 */
		public function handle_edit_custom_status() {
			if ( ! isset( $_POST['submit'], $_GET['page'], $_GET['action'], $_GET['term-id'] )
			|| $_GET['page'] != $this->module->settings_slug || 'edit-status' != $_GET['action'] ) {
				return;
			}

			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'edit-status' ) ) {
				wp_die( esc_html( $this->module->messages['nonce-failed'] ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html( $this->module->messages['invalid-permissions'] ) );
			}

			$existing_status = $this->get_custom_status_by( 'id', (int) $_GET['term-id'] );
			if ( ! $existing_status ) {
				wp_die( esc_html( $this->module->messages['status-missing'] ) );
			}

			$name        = isset( $_POST['name'] ) ? sanitize_text_field( trim( $_POST['name'] ) ) : '';
			$description = isset( $_POST['description'] ) ? stripslashes( wp_filter_nohtml_kses( trim( $_POST['description'] ) ) ) : '';

			/**
			 * Form validation for editing custom status
			 *
			 * Details
			 * - 'name' is a required field and can't conflict with existing name or slug
			 * - 'description' is optional
			 */
			$_REQUEST['form-errors'] = [];
			// Check if name field was filled in
			if ( empty( $name ) ) {
				$_REQUEST['form-errors']['name'] = __( 'Please enter a name for the status', 'edit-flow' );
			}
			// Check that the name isn't numeric
			if ( is_numeric( $name ) ) {
				$_REQUEST['form-errors']['name'] = __( 'Please enter a valid, non-numeric name for the status.', 'edit-flow' );
			}
			// Check that the status name doesn't exceed 20 chars
			if ( strlen( $name ) > 20 ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' );
			}
			// Check to make sure the status doesn't already exist as another term because otherwise we'd get a weird slug
			$term_exists = term_exists( sanitize_title( $name ), self::taxonomy_key );
			if ( $term_exists && isset( $term_exists['term_id'] ) && $term_exists['term_id'] != $existing_status->term_id ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name conflicts with existing term. Please choose another.', 'edit-flow' );
			}
			// Check to make sure the status doesn't already exist
			$search_status = $this->get_custom_status_by( 'slug', sanitize_title( $name ) );
			if ( $search_status && $search_status->term_id != $existing_status->term_id ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name conflicts with existing status. Please choose another.', 'edit-flow' );
			}
			// Check to make sure the name is not restricted
			if ( $this->is_restricted_status( strtolower( sanitize_title( $name ) ) ) ) {
				$_REQUEST['form-errors']['name'] = __( 'Status name is restricted. Please choose another name.', 'edit-flow' );
			}

			// Kick out if there are any errors
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( count( $_REQUEST['form-errors'] ) ) {
				$_REQUEST['error'] = 'form-error';
				return;
			}

			// Try to add the new post status
			$args   = [
				'name'        => $name,
				'slug'        => sanitize_title( $name ),
				'description' => $description,
			];
			$return = $this->update_custom_status( $existing_status->term_id, $args );
			if ( is_wp_error( $return ) ) {
				wp_die( esc_html__( 'Error updating post status.', 'edit-flow' ) );
			}

			$redirect_url = $this->get_link( [ 'message' => 'status-updated' ] );
			wp_redirect( $redirect_url );
			exit;
		}

		/**
		 * Handles a GET request to make the identified status default
		 *
		 * @since 0.7
		 */
		public function handle_make_default_custom_status() {
			global $edit_flow;

			// Check that the current GET request is our GET request
			if ( ! isset( $_GET['page'], $_GET['action'], $_GET['term-id'], $_GET['nonce'] )
			|| $_GET['page'] != $this->module->settings_slug || 'make-default' != $_GET['action'] ) {
				return;
			}

			// Check for proper nonce
			if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'make-default' ) ) {
				wp_die( esc_html__( 'Invalid nonce for submission.', 'edit-flow' ) );
			}

			// Only allow users with the proper caps
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you do not have permission to edit custom statuses.', 'edit-flow' ) );
			}

			$term_id = (int) $_GET['term-id'];
			$term    = $this->get_custom_status_by( 'id', $term_id );
			if ( is_object( $term ) ) {
				$edit_flow->update_module_option( $this->module->name, 'default_status', $term->slug );
				// @todo How do we want to handle users who click the link from "Add New Status"
				$redirect_url = $this->get_link( [ 'message' => 'default-status-changed' ] );
				wp_redirect( $redirect_url );
				exit;
			} else {
				wp_die( esc_html__( 'Status doesn&#39;t exist.', 'edit-flow' ) );
			}
		}

		/**
		 * Handles a GET request to delete a specific term
		 *
		 * @since 0.7
		 */
		public function handle_delete_custom_status() {

			// Check that this GET request is our GET request
			if ( ! isset( $_GET['page'], $_GET['action'], $_GET['term-id'], $_GET['nonce'] )
			|| $_GET['page'] != $this->module->settings_slug || 'delete-status' != $_GET['action'] ) {
				return;
			}

			// Check for proper nonce
			if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'delete-status' ) ) {
				wp_die( esc_html__( 'Invalid nonce for submission.', 'edit-flow' ) );
			}

			// Only allow users with the proper caps
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you do not have permission to edit custom statuses.', 'edit-flow' ) );
			}

			// Check to make sure the status isn't already deleted
			$term_id = (int) $_GET['term-id'];
			$term    = $this->get_custom_status_by( 'id', $term_id );
			if ( ! $term ) {
				wp_die( esc_html__( 'Status does not exist.', 'edit-flow' ) );
			}

			// Don't allow deletion of default status
			if ( $term->slug == $this->get_default_custom_status()->slug ) {
				wp_die( esc_html__( 'Cannot delete default status.', 'edit-flow' ) );
			}

			$return = $this->delete_custom_status( $term_id );
			if ( is_wp_error( $return ) ) {
				wp_die( esc_html( __( 'Could not delete the status: ', 'edit-flow' ) . $return->get_error_message() ) );
			}

			$redirect_url = $this->get_link( [ 'message' => 'status-deleted' ] );
			wp_redirect( $redirect_url );
			exit;
		}

		/**
		 * Generate a link to one of the custom status actions
		 *
		 * @since 0.7
		 *
		 * @param array $args (optional) Action and any query args to add to the URL
		 * @return string $link Direct link to complete the action
		 */
		public function get_link( $args = [] ) {
			if ( ! isset( $args['action'] ) ) {
				$args['action'] = '';
			}
			if ( ! isset( $args['page'] ) ) {
				$args['page'] = $this->module->settings_slug;
			}
			// Add other things we may need depending on the action
			switch ( $args['action'] ) {
				case 'make-default':
				case 'delete-status':
					$args['nonce'] = wp_create_nonce( $args['action'] );
					break;
				default:
					break;
			}
			return add_query_arg( $args, get_admin_url( null, 'admin.php' ) );
		}

		/**
		 * Handle an ajax request to update the order of custom statuses
		 *
		 * @since 0.7
		 */
		public function handle_ajax_update_status_positions() {

			if ( ! isset( $_POST['custom_status_sortable_nonce'] ) || ! wp_verify_nonce( $_POST['custom_status_sortable_nonce'], 'custom-status-sortable' ) ) {
				$this->print_ajax_response( 'error', esc_html( $this->module->messages['nonce-failed'] ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				$this->print_ajax_response( 'error', esc_html( $this->module->messages['invalid-permissions'] ) );
			}

			if ( ! isset( $_POST['status_positions'] ) || ! is_array( $_POST['status_positions'] ) ) {
				$this->print_ajax_response( 'error', esc_html__( 'Terms not set.', 'edit-flow' ) );
			}

			// Update each custom status with its new position
			foreach ( $_POST['status_positions'] as $position => $term_id ) {

				// Have to add 1 to the position because the index started with zero
				$args   = [
					'position' => (int) $position + 1,
				];
				$return = $this->update_custom_status( (int) $term_id, $args );
				// @todo check that this was a valid return
			}
			$this->print_ajax_response( 'success', $this->module->messages['status-position-updated'] );
		}

		/**
		 * Handle an Inline Edit POST request to update status values
		 *
		 * @since 0.7
		 */
		public function ajax_inline_save_status() {
			global $edit_flow;

			if ( ! isset( $_POST['inline_edit'] ) || ! wp_verify_nonce( $_POST['inline_edit'], 'custom-status-inline-edit-nonce' ) ) {
				die( esc_html( $this->module->messages['nonce-failed'] ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				die( esc_html( $this->module->messages['invalid-permissions'] ) );
			}

			$term_id            = isset( $_POST['status_id'] ) ? (int) $_POST['status_id'] : 0;
			$status_name        = isset( $_POST['name'] ) ? sanitize_text_field( trim( $_POST['name'] ) ) : '';
			$status_slug        = isset( $_POST['name'] ) ? sanitize_title( trim( $_POST['name'] ) ) : '';
			$status_description = isset( $_POST['description'] ) ? stripslashes( wp_filter_nohtml_kses( trim( $_POST['description'] ) ) ) : '';

			// Check if name field was filled in
			if ( empty( $status_name ) ) {
				$change_error = new WP_Error( 'invalid', esc_html__( 'Please enter a name for the status.', 'edit-flow' ) );
				die( esc_html( $change_error->get_error_message() ) );
			}

			// Check that the name isn't numeric
			if ( is_numeric( $status_name ) ) {
				$change_error = new WP_Error( 'invalid', esc_html__( 'Please enter a valid, non-numeric name for the status.', 'edit-flow' ) );
				die( esc_html( $change_error->get_error_message() ) );
			}

			// Check that the status name doesn't exceed 20 chars
			if ( strlen( $status_name ) > 20 ) {
				$change_error = new WP_Error( 'invalid', esc_html__( 'Status name cannot exceed 20 characters. Please try a shorter name.', 'edit-flow' ) );
				die( esc_html( $change_error->get_error_message() ) );
			}

			// Check to make sure the name is not restricted
			if ( $edit_flow->custom_status->is_restricted_status( strtolower( $status_name ) ) ) {
				$change_error = new WP_Error( 'invalid', esc_html__( 'Status name is restricted. Please chose another name.', 'edit-flow' ) );
				die( esc_html( $change_error->get_error_message() ) );
			}

			// Check to make sure the status doesn't already exist
			if ( $this->get_custom_status_by( 'slug', $status_slug ) && ( $this->get_custom_status_by( 'id', $term_id )->slug != $status_slug ) ) {
				$change_error = new WP_Error( 'invalid', esc_html__( 'Status already exists. Please choose another name.', 'edit-flow' ) );
				die( esc_html( $change_error->get_error_message() ) );
			}

			// Check to make sure the status doesn't already exist as another term because otherwise we'd get a fatal error
			$term_exists = term_exists( sanitize_title( $status_name ), self::taxonomy_key );
			if ( $term_exists && isset( $term_exists['term_id'] ) && $term_exists['term_id'] != $term_id ) {
				$change_error = new WP_Error( 'invalid', esc_html__( 'Status name conflicts with existing term. Please choose another.', 'edit-flow' ) );
				die( esc_html( $change_error->get_error_message() ) );
			}

			// get status_name & status_description
			$args   = [
				'name'        => $status_name,
				'description' => $status_description,
				'slug'        => $status_slug,
			];
			$return = $this->update_custom_status( $term_id, $args );
			if ( ! is_wp_error( $return ) ) {
				set_current_screen( 'edit-custom-status' );
				$wp_list_table = new EF_Custom_Status_List_Table();
				$wp_list_table->prepare_items();
				echo wp_kses_post( $wp_list_table->single_row( $return ) );
				die();
			} else {
				/* translators: 1: the status's name */
				$change_error = new WP_Error( 'invalid', sprintf( __( 'Could not update the status: <strong>%s</strong>', 'edit-flow' ), $status_name ) );
				die( wp_kses( $change_error->get_error_message(), 'strong' ) );
			}
		}

		/**
		 * Register settings for notifications so we can partially use the Settings API
		 * (We use the Settings API for form generation, but not saving)
		 *
		 * @since 0.7
		 */
		public function register_settings() {

			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', __( 'Use on these post types:', 'edit-flow' ), [ $this, 'settings_post_types_option' ], $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'always_show_dropdown', __( 'Always show dropdown:', 'edit-flow' ), [ $this, 'settings_always_show_dropdown_option' ], $this->module->options_group_name, $this->module->options_group_name . '_general' );
		}

		/**
		 * Choose the post types that should be displayed on the calendar
		 *
		 * @since 0.7
		 */
		public function settings_post_types_option() {
			global $edit_flow;
			$edit_flow->settings->helper_option_custom_post_type( $this->module );
		}

		/**
		 * Option for whether the blog admin email address should be always notified or not
		 *
		 * @since 0.7
		 */
		public function settings_always_show_dropdown_option() {
			$options = [
				'off' => __( 'Disabled', 'edit-flow' ),
				'on'  => __( 'Enabled', 'edit-flow' ),
			];
			echo '<select id="always_show_dropdown" name="' . esc_attr( $this->module->options_group_name ) . '[always_show_dropdown]">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->always_show_dropdown, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Validate input from the end user
		 *
		 * @since 0.7
		 */
		public function settings_validate( $new_options ) {

			// Whitelist validation for the post type options
			if ( ! isset( $new_options['post_types'] ) ) {
				$new_options['post_types'] = [];
			}
			$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );

			// Whitelist validation for the 'always_show_dropdown' optoins
			if ( ! isset( $new_options['always_show_dropdown'] ) || 'on' != $new_options['always_show_dropdown'] ) {
				$new_options['always_show_dropdown'] = 'off';
			}

			return $new_options;
		}

		// phpcs:disable:WordPress.Security.NonceVerification.Missing -- Disabling nonce verification because that is not available here, it's just rendering it. The actual save is done in helper_settings_validate_and_save and that's guarded well.

		/**
		 * Primary configuration page for custom status class.
		 * Shows form to add new custom statuses on the left and a
		 * WP_List_Table with the custom status terms on the right
		 *
		 */
		public function print_configure_view() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- No verification required for unprivileged URL check.
			$action = isset( $_GET['action'] ) && in_array( $_GET['action'], [ 'edit-status', 'change-options' ] ) ? $_GET['action'] : '';

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No verification required for unprivileged URL check.
			$term_id = isset( $_GET['term-id'] ) ? absint( $_GET['term-id'] ) : false;

			if ( $term_id && 'edit-status' === $action ) {
				// Check whether the term exists
				$custom_status = $this->get_custom_status_by( 'id', $term_id );

				if ( ! $custom_status ) {
					printf( '<div class="error"><p>%s</p></div>', esc_html( $this->module->messages['status-missing'] ) );
					return;
				}

				$edit_status_link = $this->get_link( [
					'action'  => 'edit-status',
					'term-id' => $term_id,
				] );

				$name        = $custom_status->name;
				$description = $custom_status->description;

				$is_nonce_valid = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( wp_strip_all_tags( $_POST['_wpnonce'] ), 'edit-status' );

				if ( $is_nonce_valid ) {
					$name        = ( isset( $_POST['name'] ) ) ? wp_strip_all_tags( $_POST['name'] ) : $custom_status->name;
					$description = ( isset( $_POST['description'] ) ) ? wp_strip_all_tags( $_POST['description'] ) : $custom_status->description;
				}

				include_once __DIR__ . '/views/edit-status.php';
			} else {
				$custom_status_list_table = new EF_Custom_Status_List_Table();
				$custom_status_list_table->prepare_items();
				include_once __DIR__ . '/views/configure.php';
			}
		}

		/**
		 * This is a hack! hack! hack! until core is fixed/better supports custom statuses
		 *
		 * When publishing a post with a custom status, set the status to 'pending' temporarily
		 * @see Works around this limitation: http://core.trac.wordpress.org/browser/tags/3.2.1/wp-includes/post.php#L2694
		 * @see Original thread: http://wordpress.org/support/topic/plugin-edit-flow-custom-statuses-create-timestamp-problem
		 * @see Core ticket: http://core.trac.wordpress.org/ticket/18362
		 */
		public function check_timestamp_on_publish() {
			global $edit_flow, $pagenow, $wpdb;

			if ( $this->disable_custom_statuses_for_post_type() ) {
				return;
			}

			// Handles the transition to 'publish' on edit.php
			if ( isset( $edit_flow ) && 'edit.php' === $pagenow && isset( $_REQUEST['bulk_edit'] ) ) {
				// For every post_id, set the post_status as 'pending' only when there's no timestamp set for $post_date_gmt
				if ( isset( $_REQUEST['post'] ) && isset( $_REQUEST['_status'] ) && 'publish' == $_REQUEST['_status'] ) {
					$post_ids = array_map( 'intval', (array) $_REQUEST['post'] );
					foreach ( $post_ids as $post_id ) {
						$wpdb->update( $wpdb->posts, [ 'post_status' => 'pending' ], [
							'ID'            => $post_id,
							'post_date_gmt' => '0000-00-00 00:00:00',
						] );
						clean_post_cache( $post_id );
					}
				}
			}

			// Handles the transition to 'publish' on post.php
			if ( isset( $edit_flow ) && 'post.php' == $pagenow && isset( $_POST['publish'] ) ) {
				// Set the post_status as 'pending' only when there's no timestamp set for $post_date_gmt
				if ( isset( $_POST['post_ID'] ) ) {
					$post_id = (int) $_POST['post_ID'];
					$ret     = $wpdb->update( $wpdb->posts, [ 'post_status' => 'pending' ], [
						'ID'            => $post_id,
						'post_date_gmt' => '0000-00-00 00:00:00',
					] );
					clean_post_cache( $post_id );
					foreach ( [ 'aa', 'mm', 'jj', 'hh', 'mn' ] as $timeunit ) {
						if ( isset( $_POST[ $timeunit ] ) && ! empty( $_POST[ 'hidden_' . $timeunit ] ) && $_POST[ 'hidden_' . $timeunit ] != $_POST[ $timeunit ] ) {
							$edit_date = '1';
							break;
						}
					}
					if ( $ret && empty( $edit_date ) ) {
						add_filter( 'pre_post_date', [ $this, 'helper_timestamp_hack' ] );
						add_filter( 'pre_post_date_gmt', [ $this, 'helper_timestamp_hack' ] );
					}
				}
			}
		}
		//phpcs:enable:WordPress.Security.NonceVerification.Missing

		/**
		 * PHP < 5.3.x doesn't support anonymous functions
		 * This helper is only used for the check_timestamp_on_publish method above
		 *
		 * @since 0.7.3
		 */
		public function helper_timestamp_hack() {
			return ( 'pre_post_date' == current_filter() ) ? current_time( 'mysql' ) : '';
		}

		/**
		 * This is a hack! hack! hack! until core is fixed/better supports custom statuses
		 *
		 * @since 0.6.5
		 *
		 * Normalize post_date_gmt if it isn't set to the past or the future
		 * @see Works around this limitation: https://core.trac.wordpress.org/browser/tags/4.5.1/src/wp-includes/post.php#L3182
		 * @see Original thread: http://wordpress.org/support/topic/plugin-edit-flow-custom-statuses-create-timestamp-problem
		 * @see Core ticket: http://core.trac.wordpress.org/ticket/18362
		 */
		public function fix_custom_status_timestamp( $data, $postarr ) {
			global $edit_flow;
			// Don't run this if Edit Flow isn't active, or we're on some other page
			if ( $this->disable_custom_statuses_for_post_type()
			|| ! isset( $edit_flow ) ) {
				return $data;
			}

			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );

			//Post is scheduled or published? Ignoring.
			if ( ! in_array( $postarr['post_status'], $status_slugs ) ) {
				return $data;
			}

			//If empty, keep empty.
			if ( empty( $postarr['post_date_gmt'] )
			|| '0000-00-00 00:00:00' == $postarr['post_date_gmt'] ) {
				$data['post_date_gmt'] = '0000-00-00 00:00:00';
			}

			return $data;
		}

		/**
		 * A new hack! hack! hack! until core better supports custom statuses`
		 *
		 * @since 0.9.4
		 *
		 * If the post_name is set, set it, otherwise keep it empty
		 *
		 * @see https://github.com/Automattic/Edit-Flow/issues/523
		 * @see https://github.com/Automattic/Edit-Flow/issues/633
		 */
		public function maybe_keep_post_name_empty( $data, $postarr ) {
			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );

			// Ignore if it's not a post status and post type we support
			if ( ! in_array( $data['post_status'], $status_slugs )
			|| ! in_array( $data['post_type'], $this->get_post_types_for_module( $this->module ) ) ) {
				return $data;
			}

			// If the post_name was intentionally set, set the post_name
			if ( ! empty( $postarr['post_name'] ) ) {
				$data['post_name'] = sanitize_title( $postarr['post_name'] );
				return $data;
			}

			// Otherwise, keep the post_name empty
			$data['post_name'] = '';

			return $data;
		}

		/**
			 * A new hack! hack! hack! until core better supports custom statuses`
			 *
			 * @since 0.9.4
			 *
			 * `wp_unique_post_slug` is used to set the `post_name`. When a custom status is used, WordPress will try
			 * really hard to set `post_name`, and we leverage `wp_unique_post_slug` to prevent it being set
			 *
			 * @see: https://github.com/WordPress/WordPress/blob/396647666faebb109d9cd4aada7bb0c7d0fb8aca/wp-includes/post.php#L3932
			 */
		public function fix_unique_post_slug( $override_slug, $slug, $post_ID, $post_status, $post_type, $post_parent ) {
			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );

			if ( ! in_array( $post_status, $status_slugs )
			|| ! in_array( $post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return null;
			}

			$post = get_post( $post_ID );

			if ( empty( $post ) ) {
				return null;
			}

			if ( $post->post_name ) {
				return $slug;
			}

			return '';
		}


		/**
		 * Another hack! hack! hack! until core better supports custom statuses
		 *
		 * @since 0.7.4
		 *
		 * The preview link for an unpublished post should always be ?p=
		 */
		public function fix_preview_link_part_one( $preview_link ) {
			global $pagenow;

			$post = get_post( get_the_ID() );

			// Only modify if we're using a pre-publish status on a supported custom post type
			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
			if ( ! $post
			|| ! is_admin()
			|| 'post.php' != $pagenow
			|| ! in_array( $post->post_status, $status_slugs )
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) )
			|| strpos( $preview_link, 'preview_id' ) !== false
			|| 'sample' == $post->filter ) {
				return $preview_link;
			}

			return $this->get_preview_link( $post );
		}

		/**
		 * Another hack! hack! hack! until core better supports custom statuses
		 *
		 * @since 0.7.4
		 *
		 * The preview link for an unpublished post should always be ?p=
		 * The code used to trigger a post preview doesn't also apply the 'preview_post_link' filter
		 * So we can't do a targeted filter. Instead, we can even more hackily filter get_permalink
		 * @see http://core.trac.wordpress.org/ticket/19378
		 */
		public function fix_preview_link_part_two( $permalink, $post, $sample ) {
			global $pagenow;

			if ( is_int( $post ) ) {
				$post = get_post( $post );
			}

			//Should we be doing anything at all?
			if ( ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return $permalink;
			}

			//Is this published?
			if ( in_array( $post->post_status, $this->published_statuses ) ) {
				return $permalink;
			}

			//Are we overriding the permalink? Don't do anything
			// phpcs:ignore:WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['action'] ) && 'sample-permalink' == $_POST['action'] ) {
				return $permalink;
			}

			//Are we previewing the post from the normal post screen?
			if ( ( 'post.php' == $pagenow || 'post-new.php' == $pagenow )
			// phpcs:ignore:WordPress.Security.NonceVerification.Missing
			&& ! isset( $_POST['wp-preview'] ) ) {
				return $permalink;
			}

			//If it's a sample permalink, not a preview
			if ( $sample ) {
				return $permalink;
			}

			return $this->get_preview_link( $post );
		}

		/**
		 * Another hack! hack! hack! until core better supports custom statuses
		 *
		 * @since 0.9
		 *
		 * The preview link for a saved unpublished post with a custom status returns a 'preview_nonce'
		 * in it and needs to be removed when previewing it to return a viewable preview link.
		 * @see https://github.com/Automattic/Edit-Flow/issues/513
		 */
		public function fix_preview_link_part_three( $preview_link, $query_args ) {
			$autosave = wp_get_post_autosave( $query_args->ID, get_current_user_id() );
			if ( $autosave ) {
				foreach ( array_intersect( array_keys( _wp_post_revision_fields( $query_args ) ), array_keys( _wp_post_revision_fields( $autosave ) ) ) as $field ) {
					if ( normalize_whitespace( $query_args->$field ) != normalize_whitespace( $autosave->$field ) ) {
						// Pass through, it's a personal preview.
						return $preview_link;
					}
				}
			}
			return remove_query_arg( [ 'preview_nonce' ], $preview_link );
		}

		/**
		 * Fix get_sample_permalink. Previosuly the 'editable_slug' filter was leveraged
		 * to correct the sample permalink a user could edit on post.php. Since 4.4.40
		 * the `get_sample_permalink` filter was added which allows greater flexibility in
		 * manipulating the slug. Critical for cases like editing the sample permalink on
		 * hierarchical post types.
		 * @since 0.8.2
		 *
		 * @param string  $permalink Sample permalink
		 * @param int     $post_id   Post ID
		 * @param string  $title     Post title
		 * @param string  $name      Post name (slug)
		 * @param WP_Post $post      Post object
		 * @return string $link Direct link to complete the action
		 */
		public function fix_get_sample_permalink( $permalink, $post_id, $title, $name, $post ) {

			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );

			if ( ! in_array( $post->post_status, $status_slugs )
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return $permalink;
			}

			remove_filter( 'get_sample_permalink', [ $this, 'fix_get_sample_permalink' ], 10, 5 );

			$new_name  = ! is_null( $name ) ? $name : $post->post_name;
			$new_title = ! is_null( $title ) ? $title : $post->post_title;

			$post              = get_post( $post_id );
			$status_before     = $post->post_status;
			$post->post_status = 'draft';

			$permalink = get_sample_permalink( $post, $title, sanitize_title( $new_name ? $new_name : $new_title, $post->ID ) );

			$post->post_status = $status_before;

			add_filter( 'get_sample_permalink', [ $this, 'fix_get_sample_permalink' ], 10, 5 );

			return $permalink;
		}

		/**
		 * Hack to work around post status check in get_sample_permalink_html
		 *
		 *
		 * The get_sample_permalink_html checks the status of the post and if it's
		 * a draft generates a certain permalink structure.
		 * We need to do the same work it's doing for custom statuses in order
		 * to support this link
		 * @see https://core.trac.wordpress.org/browser/tags/4.5.2/src/wp-admin/includes/post.php#L1296
		 *
		 * @since 0.8.2
		 *
		 * @param string  $return    Sample permalink HTML markup.
		 * @param int     $post_id   Post ID.
		 * @param string  $new_title New sample permalink title.
		 * @param string  $new_slug  New sample permalink slug.
		 * @param WP_Post $post      Post object.
		 */
		public function fix_get_sample_permalink_html( $permalink, $post_id, $new_title, $new_slug, $post ) {
			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );

			if ( ! in_array( $post->post_status, $status_slugs )
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return $permalink;
			}

			remove_filter( 'get_sample_permalink_html', [ $this, 'fix_get_sample_permalink_html' ], 10, 5 );

			$post->post_status     = 'draft';
			$sample_permalink_html = get_sample_permalink_html( $post, $new_title, $new_slug );

			add_filter( 'get_sample_permalink_html', [ $this, 'fix_get_sample_permalink_html' ], 10, 5 );

			return $sample_permalink_html;
		}


		/**
		 * Fixes a bug where post-pagination doesn't work when previewing a post with a custom status
		 * @link https://github.com/Automattic/Edit-Flow/issues/192
		 *
		 * This filter only modifies output if `is_preview()` is true
		 *
		 * Used by `wp_link_pages_link` filter
		 *
		 * @param $link
		 * @param $i
		 *
		 * @return string
		 */
		public function modify_preview_link_pagination_url( $link, $i ) {

			// Use the original $link when not in preview mode
			if ( ! is_preview() ) {
				return $link;
			}

			// Get an array of valid custom status slugs
			$custom_statuses = wp_list_pluck( $this->get_custom_statuses(), 'slug' );

			// Apply original link filters from core `wp_link_pages()`
			$r = apply_filters( 'wp_link_pages_args', [
				'link_before' => '',
				'link_after'  => '',
				'pagelink'    => '%',
			]);

			// _wp_link_page() && _ef_wp_link_page() produce an opening link tag ( <a href=".."> )
			// This is necessary to replicate core behavior:
			$link = $r['link_before'] . str_replace( '%', $i, $r['pagelink'] ) . $r['link_after'];
			$link = _ef_wp_link_page( $i, $custom_statuses ) . $link . '</a>';


			return $link;
		}

		/**
		 * Get the proper preview link for a post
		 *
		 * @since 0.8
		 */
		private function get_preview_link( $post ) {

			if ( 'page' == $post->post_type ) {
				$args = [
					'page_id' => $post->ID,
				];
			} elseif ( 'post' == $post->post_type ) {
				$args = [
					'p'       => $post->ID,
					'preview' => 'true',
				];
			} else {
				$args = [
					'p'         => $post->ID,
					'post_type' => $post->post_type,
				];
			}

			$args['preview_id'] = $post->ID;
			return add_query_arg( $args, home_url( '/' ) );
		}

		/**
		 * Another hack! hack! hack! until core better supports custom statuses
		 *
		 * @since 0.7.4
		 *
		 * The preview link for an unpublished post should always be ?p=, even in the list table
		 * @see http://core.trac.wordpress.org/ticket/19378
		 */
		public function fix_post_row_actions( $actions, $post ) {
			global $pagenow;

			// Only modify if we're using a pre-publish status on a supported custom post type
			$status_slugs = wp_list_pluck( $this->get_custom_statuses(), 'slug' );
			if ( 'edit.php' != $pagenow
			|| ! in_array( $post->post_status, $status_slugs )
			|| ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return $actions;
			}

			// 'view' is only set if the user has permission to post
			if ( empty( $actions['view'] ) ) {
				return $actions;
			}

			if ( 'page' == $post->post_type ) {
				$args = [
					'page_id' => $post->ID,
				];
			} elseif ( 'post' == $post->post_type ) {
				$args = [
					'p' => $post->ID,
				];
			} else {
				$args = [
					'p'         => $post->ID,
					'post_type' => $post->post_type,
				];
			}
			$args['preview'] = 'true';
			$preview_link    = add_query_arg( $args, home_url( '/' ) );

			/* translators: %s: post title */
			$actions['view'] = '<a href="' . esc_url( $preview_link ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Preview' ) . '</a>';
			return $actions;
		}
	}

}


 // phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Custom Statuses uses WordPress' List Table API for generating the custom status management table
 *
 * @since 0.7
 */
class EF_Custom_Status_List_Table extends WP_List_Table {

	protected $callback_args;
	protected $default_status;

	/**
	 * Construct the extended class
	 */
	public function __construct() {

		parent::__construct( [
			'plural'   => 'custom statuses',
			'singular' => 'custom status',
			'ajax'     => true,
		] );
	}

	/**
	 * Pull in the data we'll be displaying on the table
	 *
	 * @since 0.7
	 */
	public function prepare_items() {
		global $edit_flow;

		$columns               = $this->get_columns();
		$hidden                = [
			'position',
		];
		$sortable              = [];
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->items          = $edit_flow->custom_status->get_custom_statuses();
		$total_items          = count( $this->items );
		$this->default_status = $edit_flow->custom_status->get_default_custom_status()->slug;

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $total_items,
		] );
	}

	/**
	 * Message to be displayed when there are no custom statuses. Should never be displayed, but we'll customize it
	 * just in case.
	 *
	 * @since 0.7
	 */
	public function no_items() {
		_e( 'No custom statuses found.', 'edit-flow' );
	}

	/**
	 * Table shows (hidden) position, status name, status description, and the post count for each activated
	 * post type
	 *
	 * @since 0.7
	 *
	 * @return array $columns Columns to be registered with the List Table
	 */
	public function get_columns() {
		global $edit_flow;

		$columns = [
			'position'    => __( 'Position', 'edit-flow' ),
			'name'        => __( 'Name', 'edit-flow' ),
			'description' => __( 'Description', 'edit-flow' ),
		];

		$post_types           = get_post_types( '', 'objects' );
		$supported_post_types = $edit_flow->helpers->get_post_types_for_module( $edit_flow->custom_status->module );
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, $supported_post_types ) ) {
				$columns[ $post_type->name ] = $post_type->label;
			}
		}

		return $columns;
	}

	/**
	 * Fallback column callback.
	 * Primarily used to display post count for each post type
	 *
	 * @since 0.7
	 *
	 * @param object $item Custom status as an object
	 * @param string $column_name Name of the column as registered in $this->prepare_items()
	 * @return string $output What will be rendered
	 */
	public function column_default( $item, $column_name ) {
		global $edit_flow;

		// Handle custom post counts for different post types
		$post_types = get_post_types( '', 'names' );
		if ( in_array( $column_name, $post_types ) ) {

			// @todo Cachify this
			$post_count = wp_cache_get( "ef_custom_status_count_$column_name" );
			if ( false === $post_count ) {
				$posts       = wp_count_posts( $column_name );
				$post_status = $item->slug;
				// To avoid error notices when changing the name of non-standard statuses
				if ( isset( $posts->$post_status ) ) {
					$post_count = $posts->$post_status;
				} else {
					$post_count = 0;
				}
				//wp_cache_set( "ef_custom_status_count_$column_name", $post_count );
			}
			$output = sprintf( '<a title="See all %1$ss saved as \'%2$s\'" href="%3$s">%4$s</a>', $column_name, $item->name, $edit_flow->helpers->filter_posts_link( $item->slug, $column_name ), $post_count );
			return $output;
		}
	}

	/**
	 * Hidden column for storing the status position
	 *
	 * @since 0.7
	 *
	 * @param object $item Custom status as an object
	 * @return string $output What will be rendered
	 */
	public function column_position( $item ) {
		return esc_html( $item->position );
	}

	/**
	 * Displayed column showing the name of the status
	 *
	 * @since 0.7
	 *
	 * @param object $item Custom status as an object
	 * @return string $output What will be rendered
	 */
	public function column_name( $item ) {
		global $edit_flow;

		$item_edit_link = esc_url( $edit_flow->custom_status->get_link( [
			'action'  => 'edit-status',
			'term-id' => $item->term_id,
		] ) );

		$output = '<strong><a href="' . $item_edit_link . '">' . esc_html( $item->name ) . '</a>';
		if ( $item->slug == $this->default_status ) {
			$output .= ' - ' . __( 'Default', 'edit-flow' );
		}
		$output .= '</strong>';

		// Don't allow for any of these status actions when adding a new custom status
		if ( isset( $_GET['action'] ) && 'add' == $_GET['action'] ) {
			return $output;
		}

		$actions                         = [];
		$actions['edit']                 = "<a href='$item_edit_link'>" . __( 'Edit', 'edit-flow' ) . '</a>';
		$actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . __( 'Quick&nbsp;Edit' ) . '</a>';
		$actions['make_default']         = sprintf( '<a href="%1$s">' . __( 'Make&nbsp;Default', 'edit-flow' ) . '</a>', $edit_flow->custom_status->get_link( [
			'action'  => 'make-default',
			'term-id' => $item->term_id,
		] ) );

		// Prevent deleting draft status
		if ( 'draft' !== $item->slug && $item->slug !== $this->default_status ) {
			$actions['delete delete-status'] = sprintf( '<a href="%1$s">' . __( 'Delete', 'edit-flow' ) . '</a>', $edit_flow->custom_status->get_link( [
				'action'  => 'delete-status',
				'term-id' => $item->term_id,
			] ) );
		}

		$output .= $this->row_actions( $actions, false );
		$output .= '<div class="hidden" id="inline_' . esc_attr( $item->term_id ) . '">';
		$output .= '<div class="name">' . esc_html( $item->name ) . '</div>';
		$output .= '<div class="description">' . esc_html( $item->description ) . '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Displayed column showing the description of the status
	 *
	 * @since 0.7
	 *
	 * @param object $item Custom status as an object
	 * @return string $output What will be rendered
	 */
	public function column_description( $item ) {
		return esc_html( $item->description );
	}

	/**
	 * Prepare and echo a single custom status row
	 *
	 * @since 0.7
	 */
	public function single_row( $item ) {
		static $alternate_class = '';
		$alternate_class        = ( '' == $alternate_class ? ' alternate' : '' );
		$row_class              = ' class="term-static' . $alternate_class . '"';

		echo wp_kses_post( '<tr id="term-' . $item->term_id . '"' . $row_class . '>' );
		echo wp_kses_post( $this->single_row_columns( $item ) );
		echo '</tr>';
	}

	/**
	 * Hidden form used for inline editing functionality
	 *
	 * @since 0.7
	 */
	public function inline_edit() {
		global $edit_flow;
		?>
	<form method="get" action=""><table style="display: none"><tbody id="inlineedit">
		<tr id="inline-edit" class="inline-edit-row" style="display: none"><td colspan="<?php echo esc_attr( $this->get_column_count() ); ?>" class="colspanchange">
			<fieldset><div class="inline-edit-col">
				<h4><?php _e( 'Quick Edit' ); ?></h4>
				<label>
					<span class="title"><?php _e( 'Name', 'edit-flow' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value="" maxlength="20" /></span>
				</label>
				<label>
					<span class="title"><?php _e( 'Description', 'edit-flow' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="description" class="pdescription" value="" /></span>
				</label>
			</div></fieldset>
		<p class="inline-edit-save submit">
			<a accesskey="c" href="#inline-edit" title="<?php _e( 'Cancel' ); ?>" class="cancel button-secondary alignleft"><?php _e( 'Cancel' ); ?></a>
			<?php $update_text = __( 'Update Status', 'edit-flow' ); ?>
			<a accesskey="s" href="#inline-edit" title="<?php echo esc_attr( $update_text ); ?>" class="save button-primary alignright"><?php echo esc_html( $update_text ); ?></a>
			<img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
			<span class="error" style="display:none;"></span>
			<?php wp_nonce_field( 'custom-status-inline-edit-nonce', 'inline_edit', false ); ?>
			<br class="clear" />
		</p>
		</td></tr>
		</tbody></table></form>
		<?php
	}
}
