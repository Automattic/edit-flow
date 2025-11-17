<?php
/**
 * class EF_Dashboard
 * All of the code for the dashboard widgets from Edit Flow
 *
 * Dashboard widgets currently:
 * - Post Status Widget - Shows numbers for all (custom|post) statuses
 * - Posts I'm Following Widget - Show the headlines with edit links for posts I'm following
 *
 * @todo for 0.7
 * - Update the Posts I'm Following widget to use new activity class
 */

if ( ! class_exists( 'EF_Dashboard' ) ) {

	class EF_Dashboard extends EF_Module {

		public $widgets;

		/**
		 * Load the EF_Dashboard class as an Edit Flow module
		 */
		public function __construct() {

			// Register the module with Edit Flow
			$this->module_url = $this->get_module_url( __FILE__ );
			$args = array(
				'title' => __( 'Dashboard Widgets', 'edit-flow' ),
				'short_description' => __( 'Track your content from the WordPress dashboard.', 'edit-flow' ),
				'extended_description' => __( 'Enable dashboard widgets to quickly get an overview of what state your content is in.', 'edit-flow' ),
				'module_url' => $this->module_url,
				'img_url' => $this->module_url . 'lib/dashboard_s128.png',
				'slug' => 'dashboard',
				'post_type_support' => 'ef_dashboard',
				'default_options' => array(
					'enabled' => 'on',
					'post_status_widget' => 'on',
					'my_posts_widget' => 'on',
					'notepad_widget' => 'on',
				),
				'configure_page_cb' => 'print_configure_view',
				'configure_link_text' => __( 'Widget Options', 'edit-flow' ),
			);
			$this->module = EditFlow()->register_module( 'dashboard', $args );
		}

		/**
		 * Initialize all of the class' functionality if its enabled
		 */
		public function init() {

			$this->widgets = new stdClass();

			if ( 'on' == $this->module->options->notepad_widget ) {
				require_once __DIR__ . '/widgets/dashboard-notepad.php';
				$this->widgets->notepad_widget = new EF_Dashboard_Notepad_Widget();
				$this->widgets->notepad_widget->init();
			}

			// Add the widgets to the dashboard
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );

			// Register our settings
			add_action( 'admin_init', array( $this, 'register_settings' ) );
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
				// Migrate whether dashboard widgets were enabled or not
				if ( $enabled = get_option( 'edit_flow_dashboard_widgets_enabled' ) ) {
					$enabled = 'on';
				} else {
					$enabled = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'enabled', $enabled );
				delete_option( 'edit_flow_dashboard_widgets_enabled' );
				// Migrate whether the post status widget was on
				if ( $post_status_widget = get_option( 'edit_flow_post_status_widget_enabled' ) ) {
					$post_status_widget = 'on';
				} else {
					$post_status_widget = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'post_status_widget', $post_status_widget );
				delete_option( 'edit_flow_post_status_widget_enabled' );
				// Migrate whether the my posts widget was on
				if ( $my_posts_widget = get_option( 'edit_flow_myposts_widget_enabled' ) ) {
					$my_posts_widget = 'on';
				} else {
					$my_posts_widget = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'post_status_widget', $my_posts_widget );
				delete_option( 'edit_flow_myposts_widget_enabled' );
				// Delete legacy option
				delete_option( 'edit_flow_quickpitch_widget_enabled' );

				// Technically we've run this code before so we don't want to auto-install new data
				$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
			}
		}

		/**
		 * Add Edit Flow dashboard widgets to the WordPress admin dashboard
		 */
		public function add_dashboard_widgets() {

			// Only show dashboard widgets for Contributor or higher
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}

			wp_enqueue_style( 'edit-flow-dashboard-css', $this->module_url . 'lib/dashboard.css', false, EDIT_FLOW_VERSION, 'all' );

			// Set up Post Status widget but, first, check to see if it's enabled
			if ( 'on' == $this->module->options->post_status_widget ) {

				$status_widget_post_types = apply_filters( 'ef_dashboard_psw_post_types', $this->get_all_post_types() );

				foreach ( $status_widget_post_types as $post_type => $label ) {
					$this->add_dashboard_status_widget( $post_type );
				}
			}

			// Set up the Notepad widget if it's enabled
			if ( 'on' == $this->module->options->notepad_widget ) {
				wp_add_dashboard_widget( 'notepad_widget', __( 'Notepad', 'edit-flow' ), array( $this->widgets->notepad_widget, 'notepad_widget' ) );
			}

			// Add the MyPosts widget, if enabled
			if ( 'on' == $this->module->options->my_posts_widget && $this->module_enabled( 'notifications' ) ) {
				wp_add_dashboard_widget( 'myposts_widget', __( 'Posts I\'m Following', 'edit-flow' ), array( $this, 'myposts_widget' ) );
			}
		}

		/**
		 * Dynamically add a "Unpublished Content" post status widget for any post type
		 *
		 * @param $post_type_slug
		 */
		public function add_dashboard_status_widget( $post_type_slug ) {

			$post_type_labels = get_post_type_labels( get_post_type_object( $post_type_slug ) );

			if ( 'post' !== $post_type_slug ) {
				$widget_id    = 'post_status_widget_' . $post_type_slug;
				// translators: %s is the post type name
				$widget_title = sprintf( esc_html__( 'Unpublished Content: %s', 'edit-flow' ), $post_type_labels->name );
			} else {
				$widget_id    = 'post_status_widget';
				$widget_title = __( 'Unpublished Content', 'edit-flow' );
			}

			$widget_title = apply_filters( 'ef_dashboard_psw_title', $widget_title, $post_type_slug );
			$args         = array(
				'post_type' => $post_type_slug,
				'labels'    => $post_type_labels,
			);

			wp_add_dashboard_widget( $widget_id, $widget_title, array( $this, 'post_status_widget' ), null, $args );
		}

		/**
		 * Creates Post Status widget
		 * Display an at-a-glance view of post counts for all (post|custom) statuses in the system
		 */
		public function post_status_widget( $unused, $args ) {

			$labels    = $args['args']['labels'];
			$post_type = $args['args']['post_type'];

			$statuses = $this->get_post_statuses();
			$statuses[] = (object) array(
				'name' => __( 'Scheduled', 'edit-flow' ),
				'description' => '',
				'slug' => 'future',
			);
			$statuses = apply_filters( 'ef_dashboard_post_status_widget_statuses', $statuses );
			// If custom statuses are enabled, we'll output a link to edit the terms just below the post counts
			if ( $this->module_enabled( 'custom_status' ) ) {
				$edit_custom_status_url = add_query_arg( 'page', 'ef-custom-status-settings', get_admin_url( null, 'admin.php' ) );
			}

			?>
		<p class="sub ef-psw-title"><?php /* translators: %s is the type of content  */ printf( esc_html__( '%s at a Glance', 'edit-flow' ), esc_html( $labels->name ) ); ?></p>

		<div class="table ef-psw-content">
			<table>
				<tbody>
					<?php $post_count = wp_count_posts( $post_type ); ?>
					<?php foreach ( $statuses as $status ) : ?>
						<?php $filter_link = $this->filter_posts_link( $status->slug, $post_type ); ?>
						<tr>
							<td class="b">
								<a href="<?php echo esc_url( $filter_link ); ?>">
									<?php
									$slug = $status->slug;
									echo esc_html( $post_count->$slug );
									?>
								</a>
							</td>
							<td>
								<a href="<?php echo esc_url( $filter_link ); ?>"><?php echo esc_html( $status->name ); ?></a>
							</td>
						</tr>

					<?php endforeach; ?>
				</tbody>
			</table>
				<?php if ( isset( $edit_custom_status_url ) ) : ?>
				<span class="small"><a href="<?php echo esc_url( $edit_custom_status_url ); ?>"><?php _e( 'Edit Custom Statuses', 'edit-flow' ); ?></a></span>
			<?php endif; ?>
		</div>
			<?php
		}

		/**
		 * Creates My Posts widget
		 * Shows a list of the "posts you're following" sorted by most recent activity.
		 */
		public function myposts_widget() {
			global $edit_flow;

			$myposts = $edit_flow->notifications->get_user_following_posts();

			?>
		<div class="ef-myposts">
			<?php if ( ! empty( $myposts ) ) : ?>

				<?php foreach ( $myposts as $post ) : ?>
					<?php
					$url = esc_url( get_edit_post_link( $post->ID ) );
					$title = esc_html( $post->post_title );
					?>
					<li>
						<h4><a href="<?php echo esc_url( $url ); ?>" title="<?php esc_attr_e( 'Edit this post', 'edit-flow' ); ?>"><?php echo esc_html( $title ); ?></a></h4>
						<span class="ef-myposts-timestamp"><?php esc_html_e( 'This post was last updated on', 'edit-flow' ); ?> <?php echo esc_html( get_the_time( 'F j, Y \\a\\t g:i a', $post ) ); ?></span>
					</li>
				<?php endforeach; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Sorry! You\'re not subscribed to any posts!', 'edit-flow' ); ?></p>
			<?php endif; ?>
		</div>
			<?php
		}

		/**
		 * Register settings for notifications so we can partially use the Settings API
		 * (We use the Settings API for form generation, but not saving)
		 *
		 * @since 0.7
		 */
		public function register_settings() {

			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_status_widget', __( 'Post Status Widget', 'edit-flow' ), array( $this, 'settings_post_status_widget_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'my_posts_widget', __( 'Posts I\'m Following', 'edit-flow' ), array( $this, 'settings_my_posts_widget_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'notepad_widget', __( 'Notepad', 'edit-flow' ), array( $this, 'settings_notepad_widget_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
		}

		/**
		 * Enable or disable the Post Status Widget for the WP dashboard
		 *
		 * @since 0.7
		 */
		public function settings_post_status_widget_option() {
			$options = array(
				'off' => __( 'Disabled', 'edit-flow' ),
				'on' => __( 'Enabled', 'edit-flow' ),
			);
			echo '<select id="post_status_widget" name="' . esc_attr( $this->module->options_group_name ) . '[post_status_widget]">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->post_status_widget, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Enable or disable the Posts I'm Following Widget for the WP dashboard
		 *
		 * @since 0.7
		 */
		public function settings_my_posts_widget_option() {
			global $edit_flow;
			$options = array(
				'off' => __( 'Disabled', 'edit-flow' ),
				'on' => __( 'Enabled', 'edit-flow' ),
			);
			echo '<select id="my_posts_widget" name="' . esc_attr( $this->module->options_group_name ) . '[my_posts_widget]"';
			// Notifications module has to be enabled for the My Posts widget to work
			if ( ! $this->module_enabled( 'notifications' ) ) {
				echo ' disabled="disabled"';
				$this->module->options->my_posts_widget = 'off';
			}
			echo '>';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->my_posts_widget, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			if ( ! $this->module_enabled( 'notifications' ) ) {
				echo '&nbsp;&nbsp;&nbsp;<span class="description">' . esc_html__( 'The notifications module will need to be enabled for this widget to display.', 'edit-flow' );
			}
		}

		/**
		 * Enable or disable the Notepad widget for the dashboard
		 *
		 * @since 0.8
		 */
		public function settings_notepad_widget_option() {
			$options = array(
				'off' => __( 'Disabled', 'edit-flow' ),
				'on' => __( 'Enabled', 'edit-flow' ),
			);
			echo '<select id="notepad_widget" name="' . esc_attr( $this->module->options_group_name ) . '[notepad_widget]">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->notepad_widget, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Validate the field submitted by the user
		 *
		 * @since 0.7
		 */
		public function settings_validate( $new_options ) {

			// Follow whitelist validation for modules
			if ( array_key_exists( 'post_status_widget', $new_options ) && 'on' != $new_options['post_status_widget'] ) {
				$new_options['post_status_widget'] = 'off';
			}

			if ( array_key_exists( 'my_posts_widget', $new_options ) && 'on' != $new_options['my_posts_widget'] ) {
				$new_options['my_posts_widget'] = 'off';
			}

			return $new_options;
		}

		/**
		 * Settings page for the dashboard
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
	}

} // END - !class_exists('EF_Dashboard')
