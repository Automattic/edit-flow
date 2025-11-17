<?php
/**
 * class EF_Notifications
 * Email notifications for Edit Flow and more
 */

if ( ! defined( 'EF_NOTIFICATION_USE_CRON' ) ) {
	define( 'EF_NOTIFICATION_USE_CRON', false );
}

if ( ! class_exists( 'EF_Notifications' ) ) {

	class EF_Notifications extends EF_Module {

		// Taxonomy name used to store users following posts
		public $following_users_taxonomy = 'following_users';
		// Taxonomy name used to store user groups following posts
		public $following_usergroups_taxonomy = EF_User_Groups::taxonomy_key;

		public $module;

		public $edit_post_subscriptions_cap = 'edit_post_subscriptions';

		/**
		 * Register the module with Edit Flow but don't do anything else
		 */
		public function __construct() {

			// Register the module with Edit Flow
			$this->module_url = $this->get_module_url( __FILE__ );
			$args             = [
				'title'                 => __( 'Notifications', 'edit-flow' ),
				'short_description'     => __( 'Update your team of important changes to your content.', 'edit-flow' ),
				'extended_description'  => __( 'With email notifications, you can keep everyone updated about what’s happening with a given content. Each status change or editorial comment sends out an email notification to users subscribed to a post. User groups can be used to manage who receives notifications on what. With webhook notifications, all notifications will also be sent to the specified webhook URL(i.e.: Slack incoming webhooks) but will ignore specific user or user groups subscription settings.', 'edit-flow' ),
				'module_url'            => $this->module_url,
				'img_url'               => $this->module_url . 'lib/notifications_s128.png',
				'slug'                  => 'notifications',
				'default_options'       => [
					'enabled'             => 'on',
					'post_types'          => [
						'post' => 'on',
						'page' => 'on',
					],
					'always_notify_admin' => 'off',
					'send_to_webhook'     => 'off',
					'webhook_url'         => '',
				],
				'configure_page_cb'     => 'print_configure_view',
				'post_type_support'     => 'ef_notification',
				'autoload'              => false,
				'settings_help_tab'     => [
					'id'      => 'ef-notifications-overview',
					'title'   => __( 'Overview', 'edit-flow' ),
					'content' => __( '<p>Notifications ensure you keep up to date with progress your most important content. Users can be subscribed to notifications on a post one by one or by selecting user groups.</p><p>When enabled, email notifications can be sent when a post changes status or an editorial comment is left by a writer or an editor.</p>', 'edit-flow' ),
				],
				'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/notifications/">Notifications Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/Automattic/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
			];
			$this->module     = EditFlow()->register_module( 'notifications', $args );
		}

		/**
		 * Initialize the notifications class if the plugin is enabled
		 */
		public function init() {

			// Register our taxonomies for managing relationships
			$this->register_taxonomies();

			// Allow users to use a different user capability for editing post subscriptions
			$this->edit_post_subscriptions_cap = apply_filters( 'ef_edit_post_subscriptions_cap', $this->edit_post_subscriptions_cap );

			// Set up metabox and related actions
			add_action( 'add_meta_boxes', [ $this, 'add_post_meta_box' ] );

			// Add "access badge" to the subscribers list.
			add_action( 'ef_user_subscribe_actions', [ $this, 'display_subscriber_warning_badges' ], 10, 2 );

			// Saving post actions
			// self::save_post_subscriptions() is hooked into transition_post_status so we can ensure usergroup data
			// is properly saved before sending notifs
			add_action( 'transition_post_status', [ $this, 'save_post_subscriptions' ], 0, 3 );
			add_action( 'transition_post_status', [ $this, 'notification_status_change' ], 10, 3 );
			add_action( 'ef_post_insert_editorial_comment', [ $this, 'notification_comment' ] );
			add_action( 'delete_user', [ $this, 'delete_user_action' ] );
			add_action( 'ef_send_scheduled_email', [ $this, 'send_single_email' ], 10, 4 );

			add_action( 'admin_init', [ $this, 'register_settings' ] );

			// Javascript and CSS if we need it
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );

			// Add a "Follow" link to posts
			if ( apply_filters( 'ef_notifications_show_follow_link', true ) ) {
				// A little extra JS for the follow button
				add_action( 'admin_head', [ $this, 'action_admin_head_follow_js' ] );
				// Manage Posts
				add_filter( 'post_row_actions', [ $this, 'filter_post_row_actions' ], 10, 2 );
				add_filter( 'page_row_actions', [ $this, 'filter_post_row_actions' ], 10, 2 );
				// Calendar and Story Budget
				add_filter( 'ef_calendar_item_actions', [ $this, 'filter_post_row_actions' ], 10, 2 );
				add_filter( 'ef_story_budget_item_actions', [ $this, 'filter_post_row_actions' ], 10, 2 );
			}

			//Ajax for saving notifiction updates
			add_action( 'wp_ajax_save_notifications', [ $this, 'ajax_save_post_subscriptions' ] );
			add_action( 'wp_ajax_ef_notifications_user_post_subscription', [ $this, 'handle_user_post_subscription' ] );
		}

		/**
		 * Load the capabilities onto users the first time the module is run
		 *
		 * @since 0.7
		 */
		public function install() {

			// Add necessary capabilities to allow management of notifications
			$notifications_roles = [
				'administrator' => [ 'edit_post_subscriptions' ],
				'editor'        => [ 'edit_post_subscriptions' ],
				'author'        => [ 'edit_post_subscriptions' ],
			];

			foreach ( $notifications_roles as $role => $caps ) {
				$this->add_caps_to_role( $role, $caps );
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
				// Migrate whether notifications were enabled or not
				$enabled = get_option( 'edit_flow_notifications_enabled' );
				if ( $enabled ) {
					$enabled = 'on';
				} else {
					$enabled = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'enabled', $enabled );
				delete_option( 'edit_flow_notifications_enabled' );
				// Migrate whether to always notify the admin
				$always_notify_admin = get_option( 'edit_flow_always_notify_admin' );
				if ( $always_notify_admin ) {
					$always_notify_admin = 'on';
				} else {
					$always_notify_admin = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'always_notify_admin', $always_notify_admin );
				delete_option( 'edit_flow_always_notify_admin' );

				// Technically we've run this code before so we don't want to auto-install new data
				$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
			}
		}

		/**
		 * Register the taxonomies we use to manage relationships
		 *
		 * @since 0.7
		 *
		 * @uses register_taxonomy()
		 */
		public function register_taxonomies() {

			// Load the currently supported post types so we only register against those
			$supported_post_types = $this->get_post_types_for_module( $this->module );

			$args = [
				'hierarchical'          => false,
				'update_count_callback' => '_update_post_term_count',
				'label'                 => false,
				'query_var'             => false,
				'rewrite'               => false,
				'public'                => false,
				'show_ui'               => false,
			];
			register_taxonomy( $this->following_users_taxonomy, $supported_post_types, $args );
		}

		/**
		 * Enqueue necessary admin scripts
		 *
		 * @since 0.7
		 *
		 * @uses wp_enqueue_script()
		 */
		public function enqueue_admin_scripts() {

			if ( $this->is_whitelisted_functional_view() ) {
				wp_enqueue_script( 'jquery-listfilterizer' );
				wp_enqueue_script( 'edit-flow-notifications-js', $this->module_url . 'lib/notifications.js', [ 'jquery', 'jquery-listfilterizer' ], EDIT_FLOW_VERSION, true );
				wp_localize_script(
					'edit-flow-notifications-js',
					'ef_notifications_localization',
					[
						'no_access' => esc_html__( 'No Access', 'edit-flow' ),
						'no_email'  => esc_html__( 'No Email', 'edit-flow' ),
					]
				);
			}
		}

		/**
		 * Enqueue necessary admin styles, but only on the proper pages
		 *
		 * @since 0.7
		 *
		 * @uses wp_enqueue_style()
		 */
		public function enqueue_admin_styles() {

			if ( $this->is_whitelisted_functional_view() || $this->is_whitelisted_settings_view() ) {
				wp_enqueue_style( 'jquery-listfilterizer' );
				wp_enqueue_style( 'edit-flow-notifications-css', $this->module->module_url . 'lib/notifications.css', false, EDIT_FLOW_VERSION );
			}
		}

		/**
		 * JS required for the Follow link to work
		 *
		 * @since 0.8
		 */
		public function action_admin_head_follow_js() {
			?>
	<script type='text/Javascript'>
	jQuery(document).ready(function($) {
		/**
		 * Action to Follow / Unfollow posts on the manage posts screen
		 */
		$('.wp-list-table, #ef-calendar-view, #ef-story-budget-wrap').on( 'click', '.ef_follow_link a', function(e){

			e.preventDefault();

			var link = $(this);

			$.ajax({
				type : 'GET',
				url : link.attr( 'href' ),
				success : function( data ) {
					if ( 'success' == data.status ) {
						link.attr( 'href', data.message.link );
						link.attr( 'title', data.message.title );
						link.text( data.message.text );
					}
					// @todo expose the error somehow
				}
			});
			return false;
		});
	});
	</script>
			<?php
		}

		/**
		 * Add a "Follow" link to supported post types Manage Posts view
		 *
		 * @since 0.8
		 *
		 * @param array      $actions   Any existing item actions
		 * @param int|object $post      Post id or object
		 * @return array     $actions   The follow link has been appended
		 */
		public function filter_post_row_actions( $actions, $post ) {

			$post = get_post( $post );

			if ( ! in_array( $post->post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				return $actions;
			}

			if ( ! current_user_can( $this->edit_post_subscriptions_cap ) || ! current_user_can( 'edit_post', $post->ID ) ) {
				return $actions;
			}

			$parts = $this->get_follow_action_parts( $post );

			$actions['ef_follow_link'] = '<a title="' . esc_attr( $parts['title'] ) . '" href="' . esc_url( $parts['link'] ) . '">' . $parts['text'] . '</a>';

			return $actions;
		}

		/**
		 * Get an action parts for a user to follow or unfollow a post
		 *
		 * @since 0.8
		 */
		private function get_follow_action_parts( $post ) {
			$args = [
				'action'  => 'ef_notifications_user_post_subscription',
				'post_id' => $post->ID,
			];

			$following_users = $this->get_following_users( $post->ID );
			if ( in_array( wp_get_current_user()->user_login, $following_users ) ) {
				$args['method'] = 'unfollow';
				$title_text     = __( 'Click to unfollow updates to this post', 'edit-flow' );
				$follow_text    = __( 'Following', 'edit-flow' );
			} else {
				$args['method'] = 'follow';
				$title_text     = __( 'Follow updates to this post', 'edit-flow' );
				$follow_text    = __( 'Follow', 'edit-flow' );
			}

			// wp_nonce_url() has encoding issues: http://core.trac.wordpress.org/ticket/20771
			$args['_wpnonce'] = wp_create_nonce( 'ef_notifications_user_post_subscription' );

			return [
				'title' => $title_text,
				'text'  => $follow_text,
				'link'  => add_query_arg( $args, admin_url( 'admin-ajax.php' ) ),
			];
		}

		/**
		 * Add the subscriptions meta box to relevant post types
		 */
		public function add_post_meta_box() {

			if ( ! current_user_can( $this->edit_post_subscriptions_cap ) ) {
				return;
			}

			$usergroup_post_types = $this->get_post_types_for_module( $this->module );
			foreach ( $usergroup_post_types as $post_type ) {
				add_meta_box( 'edit-flow-notifications', __( 'Notifications', 'edit-flow' ), [ $this, 'notifications_meta_box' ], $post_type, 'advanced' );
			}
		}

		/**
		 * Outputs box used to subscribe users and usergroups to Posts
		 *
		 * @todo add_cap to set subscribers for posts; default to Admin and editors
		 */
		public function notifications_meta_box() {
			global $post, $post_ID, $edit_flow;
			?>
			<div id="ef-post_following_box">
				<a name="subscriptions"></a>

				<p><?php _e( 'Select the users and user groups that should receive email notifications when the status of this post is updated or when an editorial comment is added.', 'edit-flow' ); ?></p>
				<div id="ef-post_following_users_box">
					<h4><?php _e( 'Users', 'edit-flow' ); ?></h4>
					<?php
					$followers        = $this->get_following_users( $post->ID, 'id' );
					$select_form_args = [
						'list_class' => 'ef-post_following_list',
					];
					$this->users_select_form( $followers, $select_form_args );
					?>
				</div>

				<?php if ( $this->module_enabled( 'user_groups' ) && in_array( $this->get_current_post_type(), $this->get_post_types_for_module( $edit_flow->user_groups->module ) ) ) : ?>
				<div id="ef-post_following_usergroups_box">
					<h4><?php _e( 'User Groups', 'edit-flow' ); ?></h4>
					<?php
					$following_usergroups = $this->get_following_usergroups( $post->ID, 'ids' );
					$edit_flow->user_groups->usergroups_select_form( $following_usergroups );
					?>
				</div>
				<?php endif; ?>
				<div class="clear"></div>
				<input type="hidden" name="ef-save_followers" value="1" /> <?php // Extra protection against autosaves ?>
				<?php wp_nonce_field( 'save_user_usergroups', 'ef_notifications_nonce', false ); ?>
			</div>

			<?php
		}

		/**
		 * Show warning badges next to a subscriber's name if they won't receive notifications
		 *
		 * Applies on initial loading of list via. PHP. JS will set these spans based on AJAX response when box is ticked/unticked.
		 *
		 * @param int $user_id
		 * @param bool $checked True if the user is subscribed already, false otherwise.
		 * @return void
		 */
		public function display_subscriber_warning_badges( $user_id, $checked ) {
			global $post;

			if ( ! isset( $post ) || ! $checked ) {
				return;
			}

			// Add No Access span if they won't be notified
			if ( ! $this->user_can_be_notified( get_user_by( 'id', $user_id ), $post->ID ) ) {
				// span.post_following_list-no_access is also added in notifications.js after AJAX that ticks/unticks a user
				echo '<span class="post_following_list-no_access">' . esc_html__( 'No Access', 'edit-flow' ) . '</span>';
			}

			// Add No Email span if they have no email
			$user_object = get_user_by( 'id', $user_id );
			if ( ! is_a( $user_object, 'WP_User' ) || empty( $user_object->user_email ) ) {
				// span.post_following_list-no_email is also added in notifications.js after AJAX that ticks/unticks a user
				echo '<span class="post_following_list-no_email">' . esc_html__( 'No Email', 'edit-flow' ) . '</span>';
			}
		}

		/**
		 * Called when a notification editorial metadata checkbox is checked. Handles saving of a user/usergroup to a post.
		 */
		public function ajax_save_post_subscriptions() {
			global $edit_flow;

			// Verify nonce.
			if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'save_user_usergroups' ) ) {
				die( esc_html__( 'Nonce check failed. Please ensure you can add users or user groups to a post.', 'edit-flow' ) );
			}

			$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
			$post    = get_post( $post_id );

			$valid_post = ! is_null( $post ) && ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id );
			if ( ! isset( $_POST['ef_notifications_name'] ) || ! $valid_post || ! current_user_can( $this->edit_post_subscriptions_cap ) ) {
				die();
			}

			$user_group_ids = [];
			if ( isset( $_POST['user_group_ids'] ) && is_array( $_POST['user_group_ids'] ) ) {
				$user_group_ids = array_map( 'intval', $_POST['user_group_ids'] );
			}

			if ( 'ef-selected-users[]' === $_POST['ef_notifications_name'] ) {
				// Prevent auto-subscribing users that have opted out of notifications.
				add_filter( 'ef_notification_auto_subscribe_current_user', '__return_false', PHP_INT_MAX );
				$this->save_post_following_users( $post, $user_group_ids );

				if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['post_id'] ) ) {

					// Determine if any of the selected users won't have notification access
					$subscribers_with_no_access = array_filter(
						$user_group_ids,
						function ( $user_id ) use ( $post_id ) {
							return ! $this->user_can_be_notified( get_user_by( 'id', $user_id ), $post_id );
						}
					);

					// Determine if any of the selected users are missing their emails
					$subscribers_with_no_email = [];
					foreach ( $user_group_ids as $user_id ) {
						$user_object = get_user_by( 'id', $user_id );
						if ( ! is_a( $user_object, 'WP_User' ) || empty( $user_object->user_email ) ) {
							$subscribers_with_no_email[] = $user_id;
						}
					}

					// Assemble the json reply with various lists of problematic users
					$json_success = [
						'subscribers_with_no_access' => array_values( $subscribers_with_no_access ),
						'subscribers_with_no_email'  => array_values( $subscribers_with_no_email ),
					];

					wp_send_json_success( $json_success );
				}
				// Remove auto-subscribe prevention behavior from earlier.
				remove_filter( 'ef_notification_auto_subscribe_current_user', '__return_false', PHP_INT_MAX );
			}

			$groups_enabled = $this->module_enabled( 'user_groups' ) && in_array( get_post_type( $post_id ), $this->get_post_types_for_module( $edit_flow->user_groups->module ) );
			if ( 'following_usergroups[]' === $_POST['ef_notifications_name'] && $groups_enabled ) {
				$this->save_post_following_usergroups( $post, $user_group_ids );
			}

			die();
		}

		/**
		 * Handle a request to update a user's post subscription
		 *
		 * @since 0.8
		 */
		public function handle_user_post_subscription() {

			if ( ! empty( $_GET['_wpnonce'] ) && ! wp_verify_nonce( $_GET['_wpnonce'], 'ef_notifications_user_post_subscription' ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
			}

			if ( ! current_user_can( $this->edit_post_subscriptions_cap ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
			}

			$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
			$post    = get_post( $post_id );

			if ( ! $post ) {
				$this->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			}

			if ( isset( $_GET['method'] ) && 'follow' == $_GET['method'] ) {
				$retval = $this->follow_post_user( $post, get_current_user_id() );
			} else {
				$retval = $this->unfollow_post_user( $post, get_current_user_id() );
			}

			if ( is_wp_error( $retval ) ) {
				$this->print_ajax_response( 'error', $retval->get_error_message() );
			}

			$this->print_ajax_response( 'success', (object) $this->get_follow_action_parts( $post ) );
		}


		/**
		 * Called when post is saved. Handles saving of user/usergroup followers
		 *
		 * @param int $post ID of the post
		 */
		public function save_post_subscriptions( $new_status, $old_status, $post ) {
			global $edit_flow;

			if ( ! empty( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'editpost' ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
			}

			// only if has edit_post_subscriptions cap
			if ( ( ! wp_is_post_revision( $post ) && ! wp_is_post_autosave( $post ) ) && isset( $_POST['ef-save_followers'] ) && current_user_can( $this->edit_post_subscriptions_cap ) ) {
				$users      = isset( $_POST['ef-selected-users'] ) ? $_POST['ef-selected-users'] : [];
				$usergroups = isset( $_POST['following_usergroups'] ) ? $_POST['following_usergroups'] : [];
				$this->save_post_following_users( $post, $users );
				if ( $this->module_enabled( 'user_groups' ) && in_array( $this->get_current_post_type(), $this->get_post_types_for_module( $edit_flow->user_groups->module ) ) ) {
					$this->save_post_following_usergroups( $post, $usergroups );
				}
			}
		}

		/**
		 * Sets users to follow specified post
		 *
		 * @param int|Object $post ID of the post
		 */
		public function save_post_following_users( $post, $users = null ) {
			if ( ! is_array( $users ) ) {
				$users = [];
			}

			// Add current user to following users
			$user = wp_get_current_user();
			if ( $user && apply_filters( 'ef_notification_auto_subscribe_current_user', true, 'subscription_action' ) ) {
				$users[] = $user->ID;
			}

			// Add post author to following users
			if ( apply_filters( 'ef_notification_auto_subscribe_post_author', true, 'subscription_action' ) ) {
				$users[] = $post->post_author;
			}

			$users = array_unique( array_map( 'intval', $users ) );

			$follow = $this->follow_post_user( $post, $users, false );
		}

		/**
		 * Sets usergroups to follow specified post
		 *
		 * @param int $post ID of the post
		 * @param array $usergroups Usergroups to follow posts
		 */
		public function save_post_following_usergroups( $post, $usergroups = null ) {

			if ( ! is_array( $usergroups ) ) {
				$usergroups = [];
			}
			$usergroups = array_map( 'intval', $usergroups );

			$follow = $this->follow_post_usergroups( $post, $usergroups, false );
		}

		/**
		 * Set up and send post status change notification email
		 */
		public function notification_status_change( $new_status, $old_status, $post ) {
			global $edit_flow;

			// Kill switch for notification
			if ( ! apply_filters( 'ef_notification_status_change', $new_status, $old_status, $post ) || ! apply_filters( "ef_notification_{$post->post_type}_status_change", $new_status, $old_status, $post ) ) {
				return false;
			}

			$supported_post_types = $this->get_post_types_for_module( $this->module );
			if ( ! in_array( $post->post_type, $supported_post_types ) ) {
				return;
			}

			// No need to notify if it's a revision, auto-draft, or if post status wasn't changed
			$ignored_statuses = apply_filters( 'ef_notification_ignored_statuses', [ $old_status, 'inherit', 'auto-draft' ], $post->post_type );

			if ( ! in_array( $new_status, $ignored_statuses ) ) {

				// Get current user
				$current_user = wp_get_current_user();

				$post_author = get_userdata( $post->post_author );
				//$duedate = $edit_flow->post_metadata->get_post_meta($post->ID, 'duedate', true);

				$blogname = get_option( 'blogname' );

				$body = '';

				$post_id    = $post->ID;
				$post_title = ef_draft_or_post_title( $post_id );
				$post_type  = get_post_type_object( $post->post_type )->labels->singular_name;

				if ( 0 != $current_user->ID ) {
					$current_user_display_name = $current_user->display_name;
					$current_user_email        = sprintf( '(%s)', $current_user->user_email );
				} else {
					$current_user_display_name = __( 'WordPress Scheduler', 'edit-flow' );
					$current_user_email        = '';
				}

				$old_status_post_obj      = get_post_status_object( $old_status );
				$new_status_post_obj      = get_post_status_object( $new_status );
				$old_status_friendly_name = '';
				$new_status_friendly_name = '';

				/**
				 * get_post_status_object will return null for certain statuses (i.e., 'new')
				 * The mega if/else block below should catch all cases, but just in case, we
				 * make sure to at least set $old_status_friendly_name and $new_status_friendly_name
				 * to an empty string to ensure they're at least set.
				 *
				 * Then, we attempt to set them to a sensible default before we start the
				 * mega if/else block
				 */
				if ( ! is_null( $old_status_post_obj ) ) {
					$old_status_friendly_name = $old_status_post_obj->label;
				}

				if ( ! is_null( $new_status_post_obj ) ) {
					$new_status_friendly_name = $new_status_post_obj->label;
				}

				// Email subject and first line of body
				// Set message subjects according to what action is being taken on the Post
				if ( 'new' == $old_status || 'auto-draft' == $old_status ) {
					$old_status_friendly_name = 'New';
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] New %2$s Created: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
					$body .= sprintf( __( 'A new %1$s (#%2$s "%3$s") was created by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user->display_name, $current_user->user_email ) . "\r\n";
				} elseif ( 'trash' == $new_status ) {
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] %2$s Trashed: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
					$body .= sprintf( __( '%1$s #%2$s "%3$s" was moved to the trash by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
				} elseif ( 'trash' == $old_status ) {
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] %2$s Restored (from Trash): "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
					$body .= sprintf( __( '%1$s #%2$s "%3$s" was restored from trash by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
				} elseif ( 'future' == $new_status ) {
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] %2$s Scheduled: "%3$s"' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email 6. scheduled date  */
					$body .= sprintf( __( '%1$s #%2$s "%3$s" was scheduled by %4$s %5$s.  It will be published on %6$s' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email, $this->get_scheduled_datetime( $post ) ) . "\r\n";
				} elseif ( 'publish' == $new_status ) {
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] %2$s Published: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
					$body .= sprintf( __( '%1$s #%2$s "%3$s" was published by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
				} elseif ( 'publish' == $old_status ) {
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] %2$s Unpublished: "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
					$body .= sprintf( __( '%1$s #%2$s "%3$s" was unpublished by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
				} else {
					/* translators: 1: site name, 2: post type, 3. post title */
					$subject = sprintf( __( '[%1$s] %2$s Status Changed for "%3$s"', 'edit-flow' ), $blogname, $post_type, $post_title );
					/* translators: 1: post type, 2: post id, 3. post title, 4. user name, 5. user email */
					$body .= sprintf( __( 'Status was changed for %1$s #%2$s "%3$s" by %4$s %5$s', 'edit-flow' ), $post_type, $post_id, $post_title, $current_user_display_name, $current_user_email ) . "\r\n";
				}

				/* translators: 1: date, 2: time, 3: timezone */
				$body .= sprintf( __( 'This action was taken on %1$s at %2$s %3$s', 'edit-flow' ), date_i18n( get_option( 'date_format' ) ), date_i18n( get_option( 'time_format' ) ), get_option( 'timezone_string' ) ) . "\r\n";

				// Email body
				$body .= "\r\n";
				/* translators: 1: old status, 2: new status */
				$body .= sprintf( __( '%1$s => %2$s', 'edit-flow' ), $old_status_friendly_name, $new_status_friendly_name );
				$body .= "\r\n\r\n";

				$body .= "--------------------\r\n\r\n";

				/* translators: 1: post type */
				$body .= sprintf( __( '== %s Details ==', 'edit-flow' ), $post_type ) . "\r\n";
				/* translators: 1: post title */
				$body .= sprintf( __( 'Title: %s', 'edit-flow' ), $post_title ) . "\r\n";
				if ( ! empty( $post_author ) ) {
					/* translators: 1: author name, 2: author email */
					$body .= sprintf( __( 'Author: %1$s (%2$s)', 'edit-flow' ), $post_author->display_name, $post_author->user_email ) . "\r\n";
				}

				$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
				if ( 'publish' != $new_status ) {
					$view_link = add_query_arg( [ 'preview' => 'true' ], wp_get_shortlink( $post_id ) );
				} else {
					$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );
				}
				$body .= "\r\n";
				$body .= __( '== Actions ==', 'edit-flow' ) . "\r\n";
				/* translators: 1: edit link */
				$body .= sprintf( __( 'Add editorial comment: %s', 'edit-flow' ), $edit_link . '#editorialcomments/add' ) . "\r\n";
				/* translators: 1: edit link */
				$body .= sprintf( __( 'Edit: %s', 'edit-flow' ), $edit_link ) . "\r\n";
				/* translators: 1: view link */
				$body .= sprintf( __( 'View: %s', 'edit-flow' ), $view_link ) . "\r\n";

				$body .= $this->get_notification_footer( $post );

				$this->send_email( 'status-change', $post, $subject, $body );

				if ( 'on' === $this->module->options->send_to_webhook ) {
					/* translators: 1: user name, 2: post type, 3: post id, 4: edit link, 5: post title, 6: old status, 7: new status */
					$format = __( '*%1$s* changed the status of *%2$s #%3$s - <%4$s|%5$s>* from *%6$s* to *%7$s*', 'edit-flow' );
					$text   = sprintf( $format, $current_user->display_name, $post_type, $post_id, $edit_link, $post_title, $old_status_friendly_name, $new_status_friendly_name );

					$this->send_to_webhook( $text, 'status-change', $current_user, $post );
				}
			}
		}

		/**
		 * Set up and set editorial comment notification email
		 *
		 * @param WP_Comment $comment
		 * @return boolean|null|void
		 */
		public function notification_comment( $comment ) {

			$post = get_post( $comment->comment_post_ID );

			$supported_post_types = $this->get_post_types_for_module( $this->module );
			if ( ! in_array( $post->post_type, $supported_post_types ) ) {
				return;
			}

			// Kill switch for notification
			if ( ! apply_filters( 'ef_notification_editorial_comment', $comment, $post ) ) {
				return false;
			}

			$user         = get_userdata( $post->post_author );
			$current_user = wp_get_current_user();

			$post_id    = $post->ID;
			$post_type  = get_post_type_object( $post->post_type )->labels->singular_name;
			$post_title = ef_draft_or_post_title( $post_id );

			// Fetch the text list of people who were notified from comment meta @see EF_Editorial_Comments->maybe_output_comment_meta()
			$notification_list = get_comment_meta( $comment->comment_ID, 'notification_list', true );

			// Check if this a reply
			//$parent_ID = isset( $comment->comment_parent_ID ) ? $comment->comment_parent_ID : 0;
			//if($parent_ID) $parent = get_comment($parent_ID);

			// Set user to follow post, but make it filterable
			if ( apply_filters( 'ef_notification_auto_subscribe_current_user', true, 'comment' ) ) {
				$this->follow_post_user( $post, (int) $current_user->ID );
			}

			// Set the post author to follow the post but make it filterable
			if ( apply_filters( 'ef_notification_auto_subscribe_post_author', true, 'comment' ) ) {
				$this->follow_post_user( $post, (int) $post->post_author );
			}

			$blogname = get_option( 'blogname' );

			/* translators: 1: blog name, 2: post title */
			$subject = sprintf( __( '[%1$s] New Editorial Comment: "%2$s"', 'edit-flow' ), $blogname, $post_title );

			/* translators: 1: post id, 2: post title, 3. post type */
			$body = sprintf( __( 'A new editorial comment was added to %3$s #%1$s "%2$s"', 'edit-flow' ), $post_id, $post_title, $post_type ) . "\r\n\r\n";
			/* translators: 1: comment author, 2: author email, 3: date, 4: time */
			$body .= sprintf( __( '%1$s (%2$s) said on %3$s at %4$s:', 'edit-flow' ), $current_user->display_name, $current_user->user_email, mysql2date( get_option( 'date_format' ), $comment->comment_date ), mysql2date( get_option( 'time_format' ), $comment->comment_date ) ) . "\r\n";
			$body .= "\r\n" . $comment->comment_content . "\r\n";

			// @TODO: mention if it was a reply
			/*
			if($parent) {

			}
			*/


			$body .= "\r\n--------------------\r\n";
			// Insert the notification list from comment meta @see EF_Editorial_Comments->maybe_output_comment_meta()
			if ( $notification_list ) {
				$body .= esc_html__( 'Notified', 'edit-flow' ) . ': ' . esc_html( $notification_list ) . "\n";
			}

			$edit_link = htmlspecialchars_decode( get_edit_post_link( $post_id ) );
			$view_link = htmlspecialchars_decode( get_permalink( $post_id ) );

			$body .= "\r\n";
			$body .= __( '== Actions ==', 'edit-flow' ) . "\r\n";
			/* translators: 1: edit link */
			$body .= sprintf( __( 'Reply: %s', 'edit-flow' ), $edit_link . '#editorialcomments/reply/' . $comment->comment_ID ) . "\r\n";
			/* translators: 1: edit link */
			$body .= sprintf( __( 'Add editorial comment: %s', 'edit-flow' ), $edit_link . '#editorialcomments/add' ) . "\r\n";
			/* translators: 1: edit link */
			$body .= sprintf( __( 'Edit: %s', 'edit-flow' ), $edit_link ) . "\r\n";
			/* translators: 1: view link */
			$body .= sprintf( __( 'View: %s', 'edit-flow' ), $view_link ) . "\r\n";

			/* translators: 1: post type */
			$body .= "\r\n" . sprintf( __( 'You can see all editorial comments on this %s here: ', 'edit-flow' ), $post_type ) . "\r\n";
			$body .= $edit_link . '#editorialcomments' . "\r\n\r\n";

			$body .= $this->get_notification_footer( $post );

			$this->send_email( 'comment', $post, $subject, $body );

			if ( 'on' === $this->module->options->send_to_webhook ) {
				/* translators: 1: comment author, 2: post type, 3: post id, 4: edit link, 5: post title, 6: comment content */
				$format  = __( '*%1$s* left a comment on *%2$s #%3$s - <%4$s|%5$s>*', 'edit-flow' ) . "\n\n";
				$format .= '%6$s';
				$text    = sprintf( $format, $comment->comment_author, $post_type, $post_id, $edit_link, $post_title, $comment->comment_content );

				$this->send_to_webhook( $text, 'comment', $current_user, $comment );
			}
		}

		public function get_notification_footer( $post ) {
			$body  = '';
			$body .= "\r\n--------------------\r\n";
			/* translators: 1: post title */
			$body .= sprintf( __( 'You are receiving this email because you are subscribed to "%s".', 'edit-flow' ), ef_draft_or_post_title( $post->ID ) );
			$body .= "\r\n";
			/* translators: 1: date */
			$body .= sprintf( __( 'This email was sent %s.', 'edit-flow' ), date( 'r' ) );
			$body .= "\r\n \r\n";
			$body .= get_option( 'blogname' ) . ' | ' . get_bloginfo( 'url' ) . ' | ' . admin_url( '/' ) . "\r\n";
			return $body;
		}

		/**
		 * send_email()
		 */
		public function send_email( $action, $post, $subject, $message, $message_headers = '' ) {

			// Get list of email recipients -- set them CC
			$recipients = $this->_get_notification_recipients( $post, true );

			if ( $recipients && ! is_array( $recipients ) ) {
				$recipients = explode( ',', $recipients );
			}

			$subject         = apply_filters( 'ef_notification_send_email_subject', $subject, $action, $post );
			$message         = apply_filters( 'ef_notification_send_email_message', $message, $action, $post );
			$message_headers = apply_filters( 'ef_notification_send_email_message_headers', $message_headers, $action, $post );

			if ( EF_NOTIFICATION_USE_CRON ) {
				$this->schedule_emails( $recipients, $subject, $message, $message_headers );
			} elseif ( ! empty( $recipients ) ) {
				foreach ( $recipients as $recipient ) {
					$this->send_single_email( $recipient, $subject, $message, $message_headers );
				}
			}
		}

		/**
		 * Send notifications to Slack
		 *
		 * @param string $message Message to be sent to webhook
		 * @param string $action Action being taken. Currently only `status-change` and `comment`
		 * @param WP_User $user User who is taking the action
		 * @param WP_Post|WP_Comment $post Post or comment that the action is being taken on
		 */
		public function send_to_webhook( $message, $action, $user, $post ) {
			$webhook_url = $this->module->options->webhook_url;

			// Bail if the webhook URL is not set
			if ( empty( $webhook_url ) ) {
				return;
			}

			// Set up the payload
			$payload = [
				'text' => $message,
			];

			// apply filters to the payload
			$payload = apply_filters( 'ef_notification_send_to_webhook_payload', $payload, $action, $user, $post );

			// Send the notification
			$response = wp_remote_post(
				$webhook_url,
				[
					'body'    => wp_json_encode( $payload ),
					'headers' => [ 'Content-Type' => 'application/json' ],
				]
			);
			if ( is_wp_error( $response ) ) {
				$this->print_ajax_response( 'error', 'Unable to send notification to webhook provided', 400 );
			}
		}

		/**
		 * Schedules emails to be sent in succession
		 *
		 * @param mixed $recipients Individual email or array of emails
		 * @param string $subject Subject of the email
		 * @param string $message Body of the email
		 * @param string $message_headers. (optional) Message headers
		 * @param int $time_offset (optional) Delay in seconds per email
		 */
		public function schedule_emails( $recipients, $subject, $message, $message_headers = '', $time_offset = 1 ) {
			$recipients = (array) $recipients;

			$send_time = time();

			foreach ( $recipients as $recipient ) {
				wp_schedule_single_event( $send_time, 'ef_send_scheduled_email', [ $recipient, $subject, $message, $message_headers ] );
				$send_time += $time_offset;
			}
		}

		/**
		 * Sends an individual email
		 *
		 * @param mixed $to Email to send to
		 * @param string $subject Subject of the email
		 * @param string $message Body of the email
		 * @param string $message_headers. (optional) Message headers
		 */
		public function send_single_email( $to, $subject, $message, $message_headers = '' ) {
			wp_mail( $to, $subject, $message, $message_headers );
		}

		/**
		 * Returns a list of recipients for a given post.
		 *
		 * @param WP_Post $post
		 * @param bool $string Whether to return recipients as comma-delimited string or array.
		 * @return string|array Recipients to receive notification.
		 */
		private function _get_notification_recipients( $post, $string = false ) {
			global $edit_flow;

			$post_id = $post->ID;
			if ( ! $post_id ) {
				return $string ? '' : [];
			}

			// Email all admins if enabled.
			$admins = [];
			if ( 'on' === $this->module->options->always_notify_admin ) {
				$admins[] = get_option( 'admin_email' );
			}

			$usergroup_recipients = [];
			if ( $this->module_enabled( 'user_groups' ) ) {
				$usergroups = $this->get_following_usergroups( $post_id, 'ids' );
				foreach ( (array) $usergroups as $usergroup_id ) {
					$usergroup = $edit_flow->user_groups->get_usergroup_by( 'id', $usergroup_id );
					foreach ( (array) $usergroup->user_ids as $user_id ) {
						$usergroup_user = get_user_by( 'id', $user_id );
						if ( $this->user_can_be_notified( $usergroup_user, $post_id ) ) {
							$usergroup_recipients[] = $usergroup_user->user_email;
						}
					}
				}
			}

			$user_recipients = $this->get_following_users( $post_id, 'user_email' );
			foreach ( $user_recipients as $key => $user ) {
				$user_object = get_user_by( 'email', $user );
				if ( ! $this->user_can_be_notified( $user_object, $post_id ) ) {
					unset( $user_recipients[ $key ] );
				}
			}

			// Merge arrays, filter any duplicates, and remove empty entries.
			$recipients = array_filter( array_unique( array_merge( $admins, $user_recipients, $usergroup_recipients ) ) );

			// Process the recipients for this email to be sent.
			foreach ( $recipients as $key => $user_email ) {
				// Don't send the email to the current user unless we've explicitly indicated they should receive it.
				if ( false === apply_filters( 'ef_notification_email_current_user', false ) && wp_get_current_user()->user_email == $user_email ) {
					unset( $recipients[ $key ] );
				}
			}

			/**
			 * Filters the list of notification recipients.
			 *
			 * @param array $recipients List of recipient email addresses.
			 * @param WP_Post $post
			 * @param bool $string True if the recipients list will later be returned as a string.
			 */
			$recipients = apply_filters( 'ef_notification_recipients', $recipients, $post, $string );

			// If string set to true, return comma-delimited.
			if ( $string && is_array( $recipients ) ) {
				return implode( ',', $recipients );
			} else {
				return $recipients;
			}
		}

		/**
		 * Check if a user can be notified.
		 * This is based off of the ability to edit the post/page by default.
		 *
		 * @since 0.8.3
		 * @param WP_User $user
		 * @param int $post_id
		 * @return bool True if the user can be notified, false otherwise.
		 */
		public function user_can_be_notified( $user, $post_id ) {
			$can_be_notified = false;

			if ( $user instanceof WP_User && is_user_member_of_blog( $user->ID ) && is_numeric( $post_id ) ) {
				// The 'edit_post' cap check also covers the undocumented 'edit_page' cap.
				$can_be_notified = $user->has_cap( 'edit_post', $post_id );
			}

			/**
			 * Filters if a user can be notified. Defaults to true if they can edit the post/page.
			 *
			 * @param bool $can_be_notified True if the user can be notified.
			 * @param WP_User|bool $user The user object, otherwise false.
			 * @param int $post_id The post the user will be notified about.
			 */
			return (bool) apply_filters( 'ef_notification_user_can_be_notified', $can_be_notified, $user, $post_id );
		}

		/**
		 * Set a user or users to follow a post
		 *
		 * @param int|object         $post      Post object or ID
		 * @param string|array       $users     User or users to subscribe to post updates
		 * @param bool               $append    Whether users should be added to following_users list or replace existing list
		 *
		 * @return true|WP_Error     $response  True on success, WP_Error on failure
		 */
		public function follow_post_user( $post, $users, $append = true ) {

			$post = get_post( $post );
			if ( ! $post ) {
				return new WP_Error( 'missing-post', $this->module->messages['missing-post'] );
			}

			if ( ! is_array( $users ) ) {
				$users = [ $users ];
			}

			$user_terms = [];
			foreach ( $users as $user ) {

				if ( is_int( $user ) ) {
					$user = get_user_by( 'id', $user );
				} elseif ( is_string( $user ) ) {
					$user = get_user_by( 'login', $user );
				}

				if ( ! is_object( $user ) ) {
					continue;
				}

				$name = $user->user_login;

				// Add user as a term if they don't exist
				$term = $this->add_term_if_not_exists( $name, $this->following_users_taxonomy );

				if ( ! is_wp_error( $term ) ) {
					$user_terms[] = $name;
				}
			}
			$set = wp_set_object_terms( $post->ID, $user_terms, $this->following_users_taxonomy, $append );

			if ( is_wp_error( $set ) ) {
				return $set;
			} else {
				return true;
			}
		}

		/**
		 * Removes user from following_users taxonomy for the given Post,
		 * so they no longer receive future notifications.
		 *
		 * @param object             $post      Post object or ID
		 * @param int|string|array   $users     One or more users to unfollow from the post
		 * @return true|WP_Error     $response  True on success, WP_Error on failure
		 */
		public function unfollow_post_user( $post, $users ) {

			$post = get_post( $post );
			if ( ! $post ) {
				return new WP_Error( 'missing-post', $this->module->messages['missing-post'] );
			}

			if ( ! is_array( $users ) ) {
				$users = [ $users ];
			}

			$terms = get_the_terms( $post->ID, $this->following_users_taxonomy );
			if ( is_wp_error( $terms ) ) {
				return $terms;
			}

			$user_terms = wp_list_pluck( $terms, 'slug' );
			foreach ( $users as $user ) {

				if ( is_int( $user ) ) {
					$user = get_user_by( 'id', $user );
				} elseif ( is_string( $user ) ) {
					$user = get_user_by( 'login', $user );
				}

				if ( ! is_object( $user ) ) {
					continue;
				}

				$key = array_search( $user->user_login, $user_terms );
				if ( false !== $key ) {
					unset( $user_terms[ $key ] );
				}
			}
			$set = wp_set_object_terms( $post->ID, $user_terms, $this->following_users_taxonomy, false );

			if ( is_wp_error( $set ) ) {
				return $set;
			} else {
				return true;
			}
		}

		/**
		 * follow_post_usergroups()
		 *
		 */
		public function follow_post_usergroups( $post, $usergroups = 0, $append = true ) {
			if ( ! $this->module_enabled( 'user_groups' ) ) {
				return;
			}

			$post_id = ( is_int( $post ) ) ? $post : $post->ID;

			if ( ! is_array( $usergroups ) ) {
				$usergroups = [ $usergroups ];
			}

			// make sure each usergroup id is an integer and not a number stored as a string
			foreach ( $usergroups as $key => $usergroup ) {
				$usergroups[ $key ] = intval( $usergroup );
			}

			wp_set_object_terms( $post_id, $usergroups, $this->following_usergroups_taxonomy, $append );
			return;
		}

		/**
		 * Removes users that are deleted from receiving future notifications (i.e. makes them unfollow posts FOREVER!)
		 *
		 * @param $id int ID of the user
		 */
		public function delete_user_action( $id ) {
			if ( ! $id ) {
				return;
			}

			// get user data
			$user = get_userdata( $id );

			if ( $user ) {
				// Delete term from the following_users taxonomy
				$user_following_term = get_term_by( 'name', $user->user_login, $this->following_users_taxonomy );
				if ( $user_following_term ) {
					wp_delete_term( $user_following_term->term_id, $this->following_users_taxonomy );
				}
			}
		}

		/**
		 * Add user as a term if they aren't already
		 * @param $term string term to be added
		 * @param $taxonomy string taxonomy to add term to
		 * @return WP_error if insert fails, true otherwise
		 */
		public function add_term_if_not_exists( $term, $taxonomy ) {
			if ( ! term_exists( $term, $taxonomy ) ) {
				$args = [ 'slug' => sanitize_title( $term ) ];
				return wp_insert_term( $term, $taxonomy, $args );
			}
			return true;
		}

		/**
		 * Gets a list of the users following the specified post
		 *
		 * @param int $post_id The ID of the post
		 * @param string $return The field to return
		 * @return array $users Users following the specified posts
		 */
		public function get_following_users( $post_id, $return = 'user_login' ) {

			// Get following_users terms for the post
			$users = wp_get_object_terms( $post_id, $this->following_users_taxonomy, [ 'fields' => 'names' ] );

			// Don't have any following users
			if ( ! $users || is_wp_error( $users ) ) {
				return [];
			}

			// if just want user_login, return as is
			if ( 'user_login' == $return ) {
				return $users;
			}

			foreach ( (array) $users as $key => $user ) {
				switch ( $user ) {
					case is_int( $user ):
						$search = 'id';
						break;
					case is_email( $user ):
						$search = 'email';
						break;
					default:
						$search = 'login';
						break;
				}
				$new_user = get_user_by( $search, $user );
				if ( ! $new_user || ! is_user_member_of_blog( $new_user->ID ) ) {
					unset( $users[ $key ] );
					continue;
				}
				switch ( $return ) {
					case 'user_login':
						$users[ $key ] = $new_user->user_login;
						break;
					case 'id':
						$users[ $key ] = $new_user->ID;
						break;
					case 'user_email':
						$users[ $key ] = $new_user->user_email;
						break;
				}
			}
			if ( ! $users || is_wp_error( $users ) ) {
				$users = [];
			}
			return $users;
		}

		/**
		 * Gets a list of the usergroups that are following specified post
		 *
		 * @param int $post_id
		 * @return array $usergroups All of the usergroup slugs
		 */
		public function get_following_usergroups( $post_id, $return = 'all' ) {
			global $edit_flow;

			// Workaround for the fact that get_object_terms doesn't return just slugs
			if ( 'slugs' == $return ) {
				$fields = 'all';
			} else {
				$fields = $return;
			}

			$usergroups = wp_get_object_terms( $post_id, $this->following_usergroups_taxonomy, [ 'fields' => $fields ] );

			if ( 'slugs' == $return ) {
				$slugs = [];
				foreach ( $usergroups as $usergroup ) {
					$slugs[] = $usergroup->slug;
				}
				$usergroups = $slugs;
			}
			return $usergroups;
		}

		/**
		 * Gets a list of posts that a user is following
		 *
		 * @param string|int $user user_login or id of user
		 * @param array $args
		 * @return array $posts Posts a user is following
		 */
		public function get_user_following_posts( $user = 0, $args = null ) {
			if ( ! $user ) {
				$user = (int) wp_get_current_user()->ID;
			}

			if ( is_int( $user ) ) {
				$user = get_userdata( $user )->user_login;
			}

			$post_args = [
				'tax_query'      => [
					[
						'taxonomy' => $this->following_users_taxonomy,
						'field'    => 'slug',
						'terms'    => $user,
					],
				],
				'posts_per_page' => '10',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'post_status'    => 'any',
			];
			$post_args = apply_filters( 'ef_user_following_posts_query_args', $post_args );
			$posts     = get_posts( $post_args );
			return $posts;
		}

		/**
		 * Register settings for notifications so we can partially use the Settings API
		 * (We use the Settings API for form generation, but not saving)
		 *
		 * @since 0.7
		 */
		public function register_settings() {
				add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
				add_settings_field( 'post_types', __( 'Post types for notifications:', 'edit-flow' ), [ $this, 'settings_post_types_option' ], $this->module->options_group_name, $this->module->options_group_name . '_general' );
				add_settings_field( 'always_notify_admin', __( 'Always notify blog admin', 'edit-flow' ), [ $this, 'settings_always_notify_admin_option' ], $this->module->options_group_name, $this->module->options_group_name . '_general' );
				add_settings_field( 'send_to_webhook', __( 'Send to Webhook', 'edit-flow' ), [ $this, 'settings_send_to_webhook' ], $this->module->options_group_name, $this->module->options_group_name . '_general' );
				add_settings_field( 'webhook_url', __( 'Webhook URL', 'edit-flow' ), [ $this, 'settings_webhook_url' ], $this->module->options_group_name, $this->module->options_group_name . '_general' );
		}

		/**
		 * Chose the post types for notifications
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
		public function settings_always_notify_admin_option() {
			$options = [
				'off' => __( 'Disabled', 'edit-flow' ),
				'on'  => __( 'Enabled', 'edit-flow' ),
			];
			echo '<select id="always_notify_admin" name="' . esc_attr( $this->module->options_group_name ) . '[always_notify_admin]">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->always_notify_admin, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Option to enable sending notifications to Slack
		 *
		 * @since 0.9.9
		 */
		public function settings_send_to_webhook() {
			$options = [
				'off' => __( 'Disabled', 'edit-flow' ),
				'on'  => __( 'Enabled', 'edit-flow' ),
			];
			echo '<select id="send_to_webhook" name="' . esc_attr( $this->module->options_group_name ) . '[send_to_webhook]">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->send_to_webhook, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Option to set the Slack webhook URL
		 *
		 * @since 0.9.9
		 */
		public function settings_webhook_url() {
			echo '<input type="text" id="webhook_url" name="' . esc_attr( $this->module->options_group_name ) . '[webhook_url]" value="' . esc_attr( $this->module->options->webhook_url ) . '" />';
		}

		/**
		 * Validate our user input as the settings are being saved
		 *
		 * @since 0.7
		 */
		public function settings_validate( $new_options ) {

			// Whitelist validation for the post type options
			if ( ! isset( $new_options['post_types'] ) ) {
				$new_options['post_types'] = [];
			}
			$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );

			// Whitelist validation for the 'always_notify_admin' options
			if ( ! isset( $new_options['always_notify_admin'] ) || 'on' != $new_options['always_notify_admin'] ) {
				$new_options['always_notify_admin'] = 'off';
			}

			// White list validation for the 'send_to_slack' option
			if ( ! isset( $new_options['send_to_webhook'] ) || 'on' != $new_options['send_to_webhook'] ) {
				$new_options['send_to_webhook'] = 'off';
			}

			// White list validation for the 'slack_webhook_url' option
			if ( ! isset( $new_options['webhook_url'] ) || esc_url_raw( $new_options['webhook_url'] ) !== $new_options['webhook_url'] ) {
				$new_options['webhook_url'] = '';
			} else {
				$new_options['webhook_url'] = esc_url_raw( $new_options['webhook_url'] );
			}

			return $new_options;
		}

		/**
		 * Settings page for notifications
		 *
		 * @since 0.7
		 */
		public function print_configure_view() {
			?>
			<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
				<?php settings_fields( $this->module->options_group_name ); ?>
				<?php do_settings_sections( $this->module->options_group_name ); ?>
				<?php
					echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
				?>
				<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?><a class="cancel-settings-link" href="<?php echo esc_url( EDIT_FLOW_SETTINGS_PAGE ); ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>
			</form>
			<?php
		}

		/**
		* Gets a simple phrase containing the formatted date and time that the post is scheduled for.
		*
		* @since 0.8
		*
		* @param  obj    $post               Post object
		* @return str    $scheduled_datetime The scheduled datetime in human-readable format
		*/
		private function get_scheduled_datetime( $post ) {

				$scheduled_ts = strtotime( $post->post_date );

				$date = date_i18n( get_option( 'date_format' ), $scheduled_ts );
				$time = date_i18n( get_option( 'time_format' ), $scheduled_ts );

				/* translators: 1: date, 2: time */
				return sprintf( __( '%1$s at %2$s', 'edit-flow' ), $date, $time );
		}
	}

}
