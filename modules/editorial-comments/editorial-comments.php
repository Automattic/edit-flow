<?php
/**
 * class EF_Editorial_Comments
 * Threaded commenting in the admin for discussion between writers and editors
 *
 * @author batmoo
 */

if ( ! class_exists( 'EF_Editorial_Comments' ) ) {

	class EF_Editorial_Comments extends EF_Module {

		// This is comment type used to differentiate editorial comments
		// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
		const comment_type = 'editorial-comment';

		public function __construct() {

			$this->module_url = $this->get_module_url( __FILE__ );
			// Register the module with Edit Flow
			$args = array(
				'title' => __( 'Editorial Comments', 'edit-flow' ),
				'short_description' => __( 'Share internal notes with your team.', 'edit-flow' ),
				'extended_description' => __( 'Use editorial comments to hold a private discussion about a post. Communicate directly with your writers or editors about what works and what needs to be improved for each piece.', 'edit-flow' ),
				'module_url' => $this->module_url,
				'img_url' => $this->module_url . 'lib/editorial_comments_s128.png',
				'slug' => 'editorial-comments',
				'default_options' => array(
					'enabled' => 'on',
					'post_types' => array(
						'post' => 'on',
						'page' => 'on',
					),
				),
				'configure_page_cb' => 'print_configure_view',
				'configure_link_text' => __( 'Choose Post Types', 'edit-flow' ),
				'autoload' => false,
				'settings_help_tab' => array(
					'id' => 'ef-editorial-comments-overview',
					'title' => __( 'Overview', 'edit-flow' ),
					'content' => __( '<p>Editorial comments help you cut down on email overload and keep the conversation close to where it matters: your content. Threaded commenting in the admin, similar to what you find at the end of a blog post, allows writers and editors to privately leave feedback and discuss what needs to be changed before publication.</p><p>Anyone with access to view the story in progress will also have the ability to comment on it. If you have notifications enabled, those following the post will receive an email every time a comment is left.</p>', 'edit-flow' ),
				),
				'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/editorial-comments/">Editorial Comments Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/danielbachhuber/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
			);
			$this->module = EditFlow()->register_module( 'editorial_comments', $args );
		}

		/**
		 * Initialize the rest of the stuff in the class if the module is active
		 */
		public function init() {
			add_action( 'add_meta_boxes', array( $this, 'add_post_meta_box' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ) );
			add_action( 'wp_ajax_editflow_ajax_insert_comment', array( $this, 'ajax_insert_comment' ) );
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
				// Technically we've run this code before so we don't want to auto-install new data
				$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
			}
		}

		/**
		 * Load any of the admin scripts we need but only on the pages we need them
		 */
		public function add_admin_scripts() {
			global $pagenow;

			$post_type = $this->get_current_post_type();
			$supported_post_types = $this->get_post_types_for_module( $this->module );
			if ( ! in_array( $post_type, $supported_post_types ) ) {
				return;
			}

			if ( ! in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'page-new.php' ) ) ) {
				return;
			}

			wp_enqueue_script( 'edit_flow-post_comment', $this->module_url . 'lib/editorial-comments.js', array( 'jquery', 'wp-ajax-response' ), EDIT_FLOW_VERSION, true );
			wp_localize_script( 'edit_flow-post_comment', '__ef_localize_post_comment', array(
				'and'           => esc_html__( 'and', 'edit-flow' ),
				'none_notified' => esc_html__( 'No one will be notified.', 'edit-flow' ),
			) );

			wp_enqueue_style( 'edit-flow-editorial-comments-css', $this->module_url . 'lib/editorial-comments.css', false, EDIT_FLOW_VERSION, 'all' );

			$thread_comments = (int) get_option( 'thread_comments' );
			?>
		<script type="text/javascript">
			var ef_thread_comments = <?php echo $thread_comments ? intval( $thread_comments ) : 0; ?>;
		</script>
			<?php
		}

		/**
		 * Add the editorial comments metabox to enabled post types
		 *
		 * @uses add_meta_box()
		 */
		public function add_post_meta_box() {

			$supported_post_types = $this->get_post_types_for_module( $this->module );
			foreach ( $supported_post_types as $post_type ) {
				add_meta_box( 'edit-flow-editorial-comments', __( 'Editorial Comments', 'edit-flow' ), array( $this, 'editorial_comments_meta_box' ), $post_type, 'normal' );
			}
		}

		public function editorial_comments_meta_box() {
			global $post, $post_ID;
			?>
		<div id="ef-comments_wrapper">
			<a name="editorialcomments"></a>

			<?php
			// Show comments only if not a new post
			if ( ! in_array( $post->post_status, array( 'new', 'auto-draft' ) ) ) :

				// Unused since switched to wp_list_comments
				$editorial_comments = get_comments(
					array(
						'post_id' => $post->ID,
						'comment_type' => self::comment_type,
						'orderby' => 'comment_date',
						'order' => 'ASC',
						'status' => self::comment_type,
					)
				);
				?>

				<ul id="ef-comments">
					<?php
						// We use this so we can take advantage of threading and such

						wp_list_comments(
							array(
								'type' => self::comment_type,
								'callback' => array( $this, 'the_comment' ),
								'end-callback' => '__return_false',
							),
							$editorial_comments
						);
					?>
				</ul>

				<?php $this->the_comment_form(); ?>

				<?php
				else :
					?>
				<p><?php _e( 'You can add editorial comments to a post once you\'ve saved it for the first time.', 'edit-flow' ); ?></p>
					<?php
				endif;
				?>
			<div class="clear"></div>
		</div>
		<div class="clear"></div>
			<?php
		}

		/**
		 * Displays the main commenting form
		 */
		public function the_comment_form() {
			global $post;

			?>
		<a href="#" id="ef-comment_respond" onclick="editorialCommentReply.open();return false;" class="button-primary alignright hide-if-no-js" title=" <?php _e( 'Respond to this post', 'edit-flow' ); ?>"><span><?php _e( 'Respond to this post', 'edit-flow' ); ?></span></a>

		<!-- Reply form, hidden until reply clicked by user -->
		<div id="ef-replyrow" style="display: none;">
			<div id="ef-replycontainer">
				<textarea id="ef-replycontent" name="replycontent" cols="40" rows="5"></textarea>
			</div>

			<?php if ( $this->module_enabled( 'notifications' ) ) : ?>
				<label for="ef-reply-notifier"><?php esc_html_e( 'The following will be notified:', 'edit-flow' ); ?>
					<input id="ef-reply-notifier" class="ef-reply-notifier-message" readonly>
				</label>
			<?php endif; ?>

			<p id="ef-replysubmit">
				<a class="ef-replysave button-primary alignright" href="#comments-form">
					<span id="ef-replybtn"><?php esc_html_e( 'Submit Response', 'edit-flow' ); ?></span>
				</a>
				<a class="ef-replycancel button-secondary alignright" href="#comments-form"><?php _e( 'Cancel', 'edit-flow' ); ?></a>
				<img alt="Sending comment..." src="<?php echo esc_url( admin_url( '/images/wpspin_light.gif' ) ); ?>" class="alignright" style="display: none;" id="ef-comment_loading" />
				<br class="clear" style="margin-bottom:35px;" />
				<span style="display: none;" class="error"></span>
			</p>

			<input type="hidden" value="" id="ef-comment_parent" name="ef-comment_parent" />
			<input type="hidden" name="ef-post_id" id="ef-post_id" value="<?php echo esc_attr( $post->ID ); ?>" />

			<?php wp_nonce_field( 'comment', 'ef_comment_nonce', false ); ?>

			<br class="clear" />
		</div>

			<?php
		}

		/**
		 * Maybe display who was notified underneath an editorial comment.
		 *
		 * @param int $comment_id
		 * @return void
		 */
		public function maybe_output_comment_meta( $comment_id ) {
			if ( ! $this->module_enabled( 'notifications' ) || ! apply_filters( 'ef_editorial_comments_show_notified_users', true ) ) {
				return;
			}

			$notification = get_comment_meta( $comment_id, 'notification_list', true );

			if ( empty( $notification ) ) {
				$message = esc_html__( 'No users or groups were notified.', 'edit-flow' );
			} else {
				$message = '<strong>' . esc_html__( 'Notified', 'edit-flow' ) . ':</strong> ' . esc_html( $notification );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- It's already been escaped above
			echo '<p class="ef-notification-meta">' . $message . '</p>';
		}

		/**
		 * Displays a single comment
		 */
		public function the_comment( $comment, $args, $depth ) {
			global $current_user, $userdata;

			// Get current user
			wp_get_current_user();

			// ToDo: Find an alternative so we don't override global variables
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Without this, the comment will not appear.
			$GLOBALS['comment'] = $comment;

			$actions = array();

			$actions_string = '';
			// Comments can only be added by users that can edit the post
			if ( current_user_can( 'edit_post', $comment->comment_post_ID ) ) {
				// The output for this has been individually escaped. Escaping the entire string will break comment reply functionality.
				// ToDo: Use wp_kses with a custom set of allowed tags instead.
				$actions['reply'] = '<a onclick="editorialCommentReply.open(\'' . esc_html( $comment->comment_ID ) . '\',\'' . esc_html( $comment->comment_post_ID ) . '\');return false;" class="vim-r hide-if-no-js" title="' . esc_attr( __( 'Reply to this comment', 'edit-flow' ) ) . '" href="#">' . esc_html__( 'Reply', 'edit-flow' ) . '</a>';

				$sep = ' ';
				$i = 0;
				foreach ( $actions as $action => $link ) {
					++$i;
					// Reply and quickedit need a hide-if-no-js span
					if ( 'reply' == $action || 'quickedit' == $action ) {
						$action .= ' hide-if-no-js';
					}

					$actions_string .= "<span class='$action'>$sep$link</span>";
				}
			}

			?>

		<li id="comment-<?php echo esc_attr( $comment->comment_ID ); ?>" <?php comment_class( array( 'comment-item', wp_get_comment_status( $comment->comment_ID ) ) ); ?>>

			<?php echo get_avatar( $comment->comment_author_email, 50 ); ?>

			<div class="post-comment-wrap">
				<h5 class="comment-meta">
					<?php
					/* translators: 1: HTML email link to the author of the current comment, 2: the date of the comment, 3: the time of the comment */
					printf( wp_kses_post( __( '<span class="comment-author">%1$s</span><span class="meta"> said on %2$s at %3$s</span>', 'edit-flow' ) ),
						wp_kses_post( comment_author_email_link( $comment->comment_author ) ),
						esc_attr( get_comment_date( get_option( 'date_format' ) ) ),
					esc_attr( get_comment_time() ) );
					?>
				</h5>

				<div class="comment-content"><?php comment_text(); ?></div>
					<?php $this->maybe_output_comment_meta( $comment->comment_ID ); ?>
				<p class="row-actions"><?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ echo $actions_string; ?></p>
			</div>
		</li>
			<?php
		}

		/**
		 * Handles AJAX insert comment
		 */
		public function ajax_insert_comment() {
			global $current_user, $user_ID, $wpdb;

			// Verify nonce
			if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'comment' ) ) {
				die( esc_html__( "Nonce check failed. Please ensure you're supposed to be adding editorial comments.", 'edit-flow' ) );
			}

			// Get user info
			wp_get_current_user();

			// Set up comment data
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$post_id = absint( $_POST['post_id'] );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$parent  = absint( $_POST['parent'] );

			// Only allow the comment if user can edit post
			// @TODO: allow contributers to add comments as well (?)
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				die( esc_html__( 'Sorry, you don\'t have the privileges to add editorial comments. Please talk to your Administrator.', 'edit-flow' ) );
			}

			// Verify that comment was actually entered
			$comment_content = isset( $_POST['content'] ) ? trim( $_POST['content'] ) : '';
			if ( ! $comment_content ) {
				die( esc_html__( 'Please enter a comment.', 'edit-flow' ) );
			}

			// Check that we have a post_id and user logged in
			if ( $post_id && $current_user ) {

				// set current time
				$time = current_time( 'mysql', $gmt = 0 );

				// Set comment data
				$data = array(
					'comment_post_ID' => (int) $post_id,
					'comment_author' => esc_sql( $current_user->display_name ),
					'comment_author_email' => esc_sql( $current_user->user_email ),
					'comment_author_url' => esc_sql( $current_user->user_url ),
					'comment_content' => wp_kses( $comment_content, array(
						'a' => array(
							'href' => array(),
							'title' => array(),
						),
						'b' => array(),
						'i' => array(),
						'strong' => array(),
						'em' => array(),
						'u' => array(),
						'del' => array(),
						'blockquote' => array(),
						'sub' => array(),
						'sup' => array(),
					) ),
					'comment_type' => self::comment_type,
					'comment_parent' => (int) $parent,
					'user_id' => (int) $user_ID,
					// This is just used for logging in the DB, and it's been escaped as well
					// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
					'comment_author_IP' => isset( $_SERVER['REMOTE_ADDR'] ) ? esc_sql( $_SERVER['REMOTE_ADDR'] ) : '',
					// This is just used for logging in the DB, and it's been escaped as well.
					// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
					// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
					'comment_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? esc_sql( $_SERVER['HTTP_USER_AGENT'] ) : '',
					'comment_date' => $time,
					'comment_date_gmt' => $time,
					// Set to -1?
					'comment_approved' => self::comment_type,
				);

				$data = apply_filters( 'ef_pre_insert_editorial_comment', $data );

				// Insert Comment
				$comment_id = wp_insert_comment( $data );
				$comment = get_comment( $comment_id );

				// Save the list of notified users/usergroups.
				if ( $this->module_enabled( 'notifications' ) && apply_filters( 'ef_editorial_comments_show_notified_users', true ) ) {
					$notification = isset( $_POST['notification'] ) ? sanitize_text_field( $_POST['notification'] ) : '';

					if ( ! empty( $notification ) && __( 'No one will be notified.', 'edit-flow' ) !== $notification ) {
						add_comment_meta( $comment_id, 'notification_list', $notification );
					}
				}

				// Register actions -- will be used to set up notifications and other modules can hook into this
				if ( $comment_id ) {
					do_action( 'ef_post_insert_editorial_comment', $comment );
				}

				// Prepare response
				$response = new WP_Ajax_Response();

				ob_start();
				$this->the_comment( $comment, '', '' );
				$comment_list_item = ob_get_contents();
				ob_end_clean();

				$response->add( array(
					'what' => 'comment',
					'id' => $comment_id,
					'data' => $comment_list_item,
					'action' => ( $parent ) ? 'reply' : 'new',
				));

				$response->send();

			} else {
				die( esc_html__( 'There was a problem of some sort. Try again or contact your administrator.', 'edit-flow' ) );
			}
		}

		/**
		 * Register settings for editorial comments so we can partially use the Settings API
		 * (We use the Settings API for form generation, but not saving)
		 *
		 * @since 0.7
		 */
		public function register_settings() {
			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', __( 'Enable for these post types:', 'edit-flow' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
		}

		/**
		 * Chose the post types for editorial comments
		 *
		 * @since 0.7
		 */
		public function settings_post_types_option() {
			global $edit_flow;
			$edit_flow->settings->helper_option_custom_post_type( $this->module );
		}

		/**
		 * Validate our user input as the settings are being saved
		 *
		 * @since 0.7
		 */
		public function settings_validate( $new_options ) {

			// Whitelist validation for the post type options
			if ( ! isset( $new_options['post_types'] ) ) {
				$new_options['post_types'] = array();
			}
			$new_options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );

			return $new_options;
		}

		/**
		 * Settings page for editorial comments
		 *
		 * @since 0.7
		 */
		public function print_configure_view() {
			?>

		<form class="basic-settings" action="<?php echo esc_url( add_query_arg( 'page', $this->module->settings_slug, get_admin_url( null, 'admin.php' ) ) ); ?>" method="post">
			<?php settings_fields( $this->module->options_group_name ); ?>
			<?php do_settings_sections( $this->module->options_group_name ); ?>
			<?php
				echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
			?>
			<p class="submit"><?php submit_button( null, 'primary', 'submit', false ); ?><a class="cancel-settings-link" href="<?php echo esc_url( EDIT_FLOW_SETTINGS_PAGE ); ?>"><?php _e( 'Back to Edit Flow', 'edit-flow' ); ?></a></p>

		</form>
			<?php
		}
	}

}
