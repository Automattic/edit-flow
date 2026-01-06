<?php
/**
 * Calendar module for Edit Flow.
 *
 * This class displays an editorial calendar for viewing upcoming and past content at a glance.
 *
 * @package EditFlow
 */

if ( ! class_exists( 'EF_Calendar' ) ) {

	/**
	 * Calendar module class.
	 *
	 * Displays an editorial calendar for viewing upcoming and past content at a glance.
	 */
	class EF_Calendar extends EF_Module {

		// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
		const usermeta_key_prefix = 'ef_calendar_';
		// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
		const screen_id = 'dashboard_page_calendar';

		/**
		 * Module instance.
		 *
		 * @var object
		 */
		public $module;

		/**
		 * Start date for the calendar view.
		 *
		 * @var string
		 */
		public $start_date = '';

		/**
		 * Current week number.
		 *
		 * @var int
		 */
		public $current_week = 1;

		/**
		 * Default number of weeks to show per screen.
		 *
		 * @var int
		 */
		public $total_weeks = 6;

		/**
		 * Counter of hidden posts per date square.
		 *
		 * @var int
		 */
		public $hidden = 0;

		/**
		 * Total number of posts to be shown per square before 'more' link.
		 *
		 * @var int
		 */
		public $max_visible_posts_per_date = 4;

		/**
		 * Cache for post dates.
		 *
		 * @var array
		 */
		private $post_date_cache = array();

		/**
		 * Maximum weeks to show.
		 *
		 * @var int
		 */
		private int $max_weeks;

		/**
		 * Capability required to create posts.
		 *
		 * @var string
		 */
		private string $create_post_cap;

		/**
		 * Calendar published statuses.
		 *
		 * Same as other components but without the future status.
		 *
		 * @var array
		 */
		public $published_statuses = array(
			'publish',
			'private',
		);

		/**
		 * Construct the EF_Calendar class
		 */
		public function __construct() {
			$this->max_weeks = 12;

			$this->module_url = $this->get_module_url( __FILE__ );
			// Register the module with Edit Flow.
			$args         = array(
				'title'                 => __( 'Calendar', 'edit-flow' ),
				/* translators: %s: URL to the calendar page */
				'short_description'     => sprintf( __( 'View upcoming content in a <a href="%s">customizable calendar</a>.', 'edit-flow' ), admin_url( 'index.php?page=calendar' ) ),
				'extended_description'  => __( 'Edit Flow’s calendar lets you see your posts over a customizable date range. Filter by status or click on the post title to see its details. Drag and drop posts between days to change their publication date.', 'edit-flow' ),
				'module_url'            => $this->module_url,
				'img_url'               => $this->module_url . 'lib/calendar_s128.png',
				'slug'                  => 'calendar',
				'post_type_support'     => 'ef_calendar',
				'default_options'       => array(
					'enabled'                => 'on',
					'post_types'             => array(
						'post' => 'on',
						'page' => 'off',
					),
					'quick_create_post_type' => 'post',
					'ics_subscription'       => 'off',
					'ics_secret_key'         => '',
				),
				'messages'              => array(
					'post-date-updated'   => __( 'Post date updated.', 'edit-flow' ),
					'update-error'        => __( 'There was an error updating the post. Please try again.', 'edit-flow' ),
					/* translators: %s: URL to the published post */
					'published-post-ajax' => __( "Updating the post date dynamically doesn't work for published content. Please <a href='%s'>edit the post</a>.", 'edit-flow' ),
					'key-regenerated'     => __( 'iCal secret key regenerated. Please inform all users they will need to resubscribe.', 'edit-flow' ),
				),
				'configure_page_cb'     => 'print_configure_view',
				'configure_link_text'   => __( 'Calendar Options', 'edit-flow' ),
				'settings_help_tab'     => array(
					'id'      => 'ef-calendar-overview',
					'title'   => __( 'Overview', 'edit-flow' ),
					// phpcs:ignore WordPress.WP.I18n.NoHtmlWrappedStrings -- HTML is intentional for help tab content.
					'content' => __( '<p>The calendar is a convenient week-by-week or month-by-month view into your content. Quickly see which stories are on track to being published on time, and which will need extra effort.</p>', 'edit-flow' ),
				),
				'settings_help_sidebar' => __( '<p><strong>For more information:</strong></p><p><a href="http://editflow.org/features/calendar/">Calendar Documentation</a></p><p><a href="http://wordpress.org/tags/edit-flow?forum_id=10">Edit Flow Forum</a></p><p><a href="https://github.com/danielbachhuber/Edit-Flow">Edit Flow on Github</a></p>', 'edit-flow' ),
			);
			$this->module = EditFlow()->register_module( 'calendar', $args );
		}

		/**
		 * Initialize all of our methods and such. Only runs if the module is active
		 *
		 * @uses add_action()
		 */
		public function init() {

			// .ics calendar subscriptions.
			add_action( 'wp_ajax_ef_calendar_ics_subscription', array( $this, 'handle_ics_subscription' ) );
			add_action( 'wp_ajax_nopriv_ef_calendar_ics_subscription', array( $this, 'handle_ics_subscription' ) );

			// Check whether the user should have the ability to view the calendar.
			$view_calendar_cap = 'ef_view_calendar';
			$view_calendar_cap = apply_filters( 'ef_view_calendar_cap', $view_calendar_cap );
			if ( ! current_user_can( $view_calendar_cap ) ) {
				return false;
			}

			// Define the create-post capability.
			$this->create_post_cap = apply_filters( 'ef_calendar_create_post_cap', 'edit_posts' );

			add_action( 'admin_init', array( $this, 'add_screen_options_panel' ) );
			add_action( 'admin_init', array( $this, 'handle_save_screen_options' ) );

			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
			add_action( 'admin_print_styles', array( $this, 'add_admin_styles' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

			// Ajax manipulation for the calendar.
			add_action( 'wp_ajax_ef_calendar_drag_and_drop', array( $this, 'handle_ajax_drag_and_drop' ) );

			// Ajax insert post placeholder for a specific date.
			add_action( 'wp_ajax_ef_insert_post', array( $this, 'handle_ajax_insert_post' ) );

			// Update metadata.
			add_action( 'wp_ajax_ef_calendar_update_metadata', array( $this, 'handle_ajax_update_metadata' ) );

			// Action to regenerate the calendar feed secret.
			add_action( 'admin_init', array( $this, 'handle_regenerate_calendar_feed_secret' ) );

			// Hacks to fix deficiencies in core.
			add_action( 'pre_post_update', array( $this, 'fix_post_date_on_update_part_one' ), 10, 2 );
			add_action( 'post_updated', array( $this, 'fix_post_date_on_update_part_two' ), 10, 3 );
		}

		/**
		 * Load the capabilities onto users the first time the module is run
		 *
		 * @since 0.7
		 */
		public function install() {

			// Add necessary capabilities to allow management of calendar.
			// Adds view_calendar capability from administrator to contributor.
			$calendar_roles = array(
				'administrator' => array( 'ef_view_calendar' ),
				'editor'        => array( 'ef_view_calendar' ),
				'author'        => array( 'ef_view_calendar' ),
				'contributor'   => array( 'ef_view_calendar' ),
			);

			foreach ( $calendar_roles as $role => $caps ) {
				$this->add_caps_to_role( $role, $caps );
			}
		}

		/**
		 * Upgrade our data in case we need to.
		 *
		 * @since 0.7
		 *
		 * @param string $previous_version Previous plugin version.
		 */
		public function upgrade( $previous_version ) {
			global $edit_flow;

			// Upgrade path to v0.7.
			if ( version_compare( $previous_version, '0.7', '<' ) ) {
				// Migrate whether the calendar was enabled or not and clean up old option.
				$enabled = get_option( 'edit_flow_calendar_enabled' );
				if ( $enabled ) {
					$enabled = 'on';
				} else {
					$enabled = 'off';
				}
				$edit_flow->update_module_option( $this->module->name, 'enabled', $enabled );
				delete_option( 'edit_flow_calendar_enabled' );

				// Technically we've run this code before so we don't want to auto-install new data.
				$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
			}
		}

		/**
		 * Add the calendar link underneath the "Dashboard"
		 *
		 * @uses add_submenu_page
		 */
		public function action_admin_menu() {
			add_submenu_page( 'index.php', __( 'Calendar', 'edit-flow' ), __( 'Calendar', 'edit-flow' ), apply_filters( 'ef_view_calendar_cap', 'ef_view_calendar' ), $this->module->slug, array( $this, 'view_calendar' ) );
		}

		/**
		 * Add any necessary CSS to the WordPress admin
		 *
		 * @uses wp_enqueue_style()
		 */
		public function add_admin_styles() {
			global $pagenow;
			// Only load calendar styles on the calendar page.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking page name, not processing data.
			if ( 'index.php' === $pagenow && isset( $_GET['page'] ) && 'calendar' === $_GET['page'] ) {
				wp_enqueue_style( 'edit-flow-calendar-css', $this->module_url . 'lib/calendar.css', false, EDIT_FLOW_VERSION );

				$asset_file = EDIT_FLOW_ROOT . '/build/calendar-react.asset.php';
				$asset      = file_exists( $asset_file ) ? require $asset_file : [
					'dependencies' => [],
					'version'      => EDIT_FLOW_VERSION,
				];

				wp_enqueue_style(
					'edit-flow-calendar-react-css',
					EDIT_FLOW_URL . 'build/calendar-react.css',
					[ 'wp-components' ],
					$asset['version']
				);
			}
		}

		/**
		 * Add any necessary JS to the WordPress admin
		 *
		 * @since 0.7
		 * @uses wp_enqueue_script()
		 */
		public function enqueue_admin_scripts() {
			global $pagenow;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking page name, not processing data.
			if ( 'index.php' === $pagenow && isset( $_GET['page'] ) && 'calendar' === $_GET['page'] ) {
				$this->enqueue_datepicker_resources();

				/**
				 * Powering the new React interface.
				 * Must be enqueued first because it registers the 'edit-flow/calendar' data store
				 * that calendar.js depends on for drag-and-drop functionality.
				 */
				$asset_file = EDIT_FLOW_ROOT . '/build/calendar-react.asset.php';
				$asset      = file_exists( $asset_file ) ? require $asset_file : [
					'dependencies' => [],
					'version'      => EDIT_FLOW_VERSION,
				];

				wp_enqueue_script(
					'edit-flow-calendar-react-js',
					EDIT_FLOW_URL . 'build/calendar-react.js',
					$asset['dependencies'],
					$asset['version'],
					true
				);

				$js_libraries = array(
					'jquery',
					'jquery-ui-core',
					'jquery-ui-sortable',
					'jquery-ui-draggable',
					'jquery-ui-droppable',
					'wp-data',
					'edit-flow-calendar-react-js', // Required for the 'edit-flow/calendar' data store.
				);
				foreach ( $js_libraries as $js_library ) {
					wp_enqueue_script( $js_library );
				}
				wp_enqueue_script( 'edit-flow-calendar-js', $this->module_url . 'lib/calendar.js', $js_libraries, EDIT_FLOW_VERSION, true );

				$ef_cal_js_params = array( 'can_add_posts' => current_user_can( $this->create_post_cap ) ? 'true' : 'false' );
				wp_localize_script( 'edit-flow-calendar-js', 'ef_calendar_params', $ef_cal_js_params );

				wp_add_inline_script(
					'edit-flow-calendar-react-js',
					'var EF_CALENDAR = ' . wp_json_encode( $this->get_calendar_frontend_config() ),
					'before'
				);
			}
		}

		/**
		 * Prepare the options that need to appear in Screen Options
		 *
		 * @since 0.7
		 */
		public function generate_screen_options() {

			$output = '';

			$args              = array(
				'action'   => 'ef_calendar_ics_subscription',
				'user'     => wp_get_current_user()->user_login,
				'user_key' => md5( wp_get_current_user()->user_login . $this->module->options->ics_secret_key ),
			);
			$subscription_link = add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
			$output           .= '<br />';
			$output           .= __( 'Subscribe in iCal or Google Calendar', 'edit-flow' );
			$output           .= ':<br /><input type="text" size="100" value="' . esc_attr( $subscription_link ) . '" />';

			return $output;
		}

		/**
		 * Add module options to the screen panel
		 *
		 * @since 0.8.3
		 */
		public function add_screen_options_panel() {
			require_once EDIT_FLOW_ROOT . '/common/php/screen-options.php';
			if ( 'on' == $this->module->options->ics_subscription && $this->module->options->ics_secret_key ) {
				add_screen_options_panel( self::usermeta_key_prefix . 'screen_options', __( 'Calendar Options', 'edit-flow' ), array( $this, 'generate_screen_options' ), self::screen_id, false, true );
			}
		}

		/**
		 * Handle the request to save the screen options
		 *
		 * @since 0.7
		 */
		public function handle_save_screen_options() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified below.

			// Only handle screen options submissions from the current screen.
			if ( ! isset( $_POST['screen-options-apply'] ) ) {
				return;
			}

			// phpcs:enable WordPress.Security.NonceVerification.Missing

			// Nonce check.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value passed directly to wp_verify_nonce().
			if ( ! isset( $_POST[ '_wpnonce-' . self::usermeta_key_prefix . 'screen_options' ] ) || ! wp_verify_nonce( $_POST[ '_wpnonce-' . self::usermeta_key_prefix . 'screen_options' ], 'save_settings-' . self::usermeta_key_prefix . 'screen_options' ) ) {
				wp_die( esc_html( $this->module->messages['nonce-failed'] ) );
			}

			// Get the current screen options.
			$screen_options = $this->get_screen_options();

			// Save the screen options.
			$current_user = wp_get_current_user();
			$this->update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_options', $screen_options );

			// Redirect after we're complete.
			$redirect_to = menu_page_url( $this->module->slug, false );
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirect URL is constructed internally.
			wp_redirect( $redirect_to );
			exit;
		}

		/**
		 * Handle an AJAX request from the calendar to update a post's timestamp.
		 * Notes:
		 * - For Post Time, if the post is unpublished, the change sets the publication timestamp
		 * - If the post was published or scheduled for the future, the change will change the timestamp. 'publish' posts
		 * will become scheduled if moved past today and 'future' posts will be published if moved before today
		 * - Need to respect user permissions. Editors can move all, authors can move their own, and contributors can't move at all
		 *
		 * @since 0.7
		 */
		public function handle_ajax_drag_and_drop() {
			global $wpdb;

			// Nonce check.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ef-calendar-modify' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value passed directly to wp_verify_nonce().
				$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
			}

			if ( ! isset( $_POST['post_id'] ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			}

			// Check that we got a proper post.
			$post_id = (int) $_POST['post_id'];
			$post    = get_post( $post_id );
			if ( ! $post ) {
				$this->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			}

			// Check that the user can modify the post.
			if ( ! $this->current_user_can_modify_post( $post ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
			}

			// Check that it's not yet published.
			if ( in_array( $post->post_status, $this->published_statuses ) ) {
				$this->print_ajax_response( 'error', sprintf( $this->module->messages['published-post-ajax'], get_edit_post_link( $post_id ) ) );
			}

			if ( ! isset( $_POST['next_date'] ) ) {
				$this->print_ajax_response( 'error', __( 'Missing new date.', 'edit-flow' ) );
			}

			// Check that the new date passed is a valid one.
			$next_date_full = strtotime( $_POST['next_date'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used with strtotime() for date parsing only.
			if ( ! $next_date_full ) {
				$this->print_ajax_response( 'error', __( 'Something is wrong with the format for the new date.', 'edit-flow' ) );
			}

			// Persist the old hourstamp because we can't manipulate the exact time on the calendar.
			// Bump the last modified timestamps too.
			$existing_time     = date( 'H:i:s', strtotime( $post->post_date ) );
			$existing_time_gmt = date( 'H:i:s', strtotime( $post->post_date_gmt ) );
			$new_values        = array(
				'post_date'         => date( 'Y-m-d', $next_date_full ) . ' ' . $existing_time,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			);

			// By default, changing a post on the calendar won't set the timestamp.
			// If the user desires that to be the behaviour, they can set the result of this filter to 'true'.
			// With how WordPress works internally, setting 'post_date_gmt' will set the timestamp.
			if ( apply_filters( 'ef_calendar_allow_ajax_to_set_timestamp', false ) ) {
				$new_values['post_date_gmt'] = date( 'Y-m-d', $next_date_full ) . ' ' . $existing_time_gmt;
			}

			// We have to do SQL unfortunately because of core bugginess.
			// Note to those reading this: bug Nacin to allow us to finish the custom status API.
			// See http://core.trac.wordpress.org/ticket/18362.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Core workaround for custom status API limitations.
			$response = $wpdb->update( $wpdb->posts, $new_values, array( 'ID' => $post->ID ) );
			clean_post_cache( $post->ID );

			if ( ! $response ) {
				$this->print_ajax_response( 'error', $this->module->messages['update-error'] );
			}

			$this->print_ajax_response( 'success', $this->module->messages['post-date-updated'] );
		}

		/**
		 * After checking that the request is valid, do an .ics file
		 *
		 * @since 0.8
		 */
		public function handle_ics_subscription() {

			// Only do .ics subscriptions when the option is active.
			if ( 'on' != $this->module->options->ics_subscription ) {
				wp_die(); // @todo Return accepted response value.
			}

			// Confirm all of the arguments are present.
			if ( ! isset( $_GET['user'], $_GET['user_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public feed with secret key validation.
				wp_die(); // @todo Return an error response.
			}

			// Confirm this is a valid request.
			$user           = sanitize_user( $_GET['user'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public feed with secret key validation.
			$user_key       = sanitize_user( $_GET['user_key'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public feed with secret key validation.
			$ics_secret_key = $this->module->options->ics_secret_key;
			if ( ! $ics_secret_key || md5( $user . $ics_secret_key ) !== $user_key ) {
				wp_die( esc_html( $this->module->messages['nonce-failed'] ) );
			}

			// Set up the post data to be printed.
			$post_query_args  = array();
			$calendar_filters = $this->calendar_filters();
			foreach ( $calendar_filters as $filter ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public feed with secret key validation, sanitized by sanitize_filter().
				if ( isset( $_GET[ $filter ] ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public feed with secret key validation, sanitized by sanitize_filter().
					$value = $this->sanitize_filter( $filter, $_GET[ $filter ] );
					if ( false !== $value ) {
						$post_query_args[ $filter ] = $value;
					}
				}
			}

			// Set the start date for the posts_where filter.
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for date calculation in calendar context.
			$this->start_date = apply_filters( 'ef_calendar_ics_subscription_start_date', $this->get_beginning_of_week( date( 'Y-m-d', current_time( 'timestamp' ) ) ) );

			$this->total_weeks = apply_filters( 'ef_calendar_total_weeks', $this->total_weeks, 'ics_subscription' );

			$formatted_posts = array();
			for ( $current_week = 1; $current_week <= $this->total_weeks; $current_week++ ) {
				// We need to set the object variable for our posts_where filter.
				$this->current_week = $current_week;
				$week_posts         = $this->get_calendar_posts_for_week( $post_query_args, 'ics_subscription' );
				foreach ( $week_posts as $date => $day_posts ) {
					foreach ( $day_posts as $num => $post ) {
						$start_date      = self::ics_format_time( $post->post_date );
						$end_date        = self::ics_format_time( $post->post_date, 5 * MINUTE_IN_SECONDS );
						$last_modified   = self::ics_format_time( $post->post_modified );
						$post_status_obj = get_post_status_object( get_post_status( $post->ID ) );
						// Remove the convert chars and wptexturize filters from the title.
						remove_filter( 'the_title', 'convert_chars' );
						remove_filter( 'the_title', 'wptexturize' );

						$formatted_post = array(
							'BEGIN'         => 'VEVENT',
							'UID'           => $post->guid,
							'SUMMARY'       => $this->do_ics_escaping( apply_filters( 'the_title', $post->post_title ) ) . ' - ' . $post_status_obj->label,
							'DTSTART'       => $start_date,
							'DTEND'         => $end_date,
							'LAST-MODIFIED' => $last_modified,
							'URL'           => get_post_permalink( $post->ID ),
						);

						// Description should include everything visible in the calendar popup.
						$information_fields            = $this->get_post_information_fields( $post );
						$formatted_post['DESCRIPTION'] = '';
						if ( ! empty( $information_fields ) ) {
							foreach ( $information_fields as $key => $values ) {
								$formatted_post['DESCRIPTION'] .= $values['label'] . ': ' . $values['value'] . '\n';
							}
							$formatted_post['DESCRIPTION'] = rtrim( $formatted_post['DESCRIPTION'] );
						}

						$formatted_post['END'] = 'VEVENT';

						// @todo Auto format any field longer than 75 bytes.

						$formatted_posts[] = $formatted_post;
					}
				}
			}

			// Other template data.
			$header = array(
				'BEGIN'   => 'VCALENDAR',
				'VERSION' => '2.0',
				'PRODID'  => '-//Edit Flow//Edit Flow ' . EDIT_FLOW_VERSION . '//EN',
			);

			$footer = array(
				'END' => 'VCALENDAR',
			);

			// Render the .ics template and set the content type.
			header( 'Content-type: text/calendar' );
			foreach ( array( $header, $formatted_posts, $footer ) as $section ) {
				foreach ( $section as $key => $value ) {
					/**
					 * This is output to text/calendar content-type
					 */
					if ( is_string( $value ) ) {
						echo $this->do_ics_line_folding( $key . ':' . $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						foreach ( $value as $k => $v ) {
							echo $this->do_ics_line_folding( $k . ':' . $v ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
					}
				}
			}
			wp_die();
		}

		/**
		 * Perform line folding according to RFC 5545.
		 *
		 * @param string $line The line without trailing CRLF.
		 * @return string The line after line-folding with all necessary CRLF.
		 */
		public function do_ics_line_folding( $line ) {
			$len = mb_strlen( $line );
			if ( $len <= 75 ) {
				return $line . "\r\n";
			}

			$chunks = array();
			$start  = 0;
			while ( true ) {
				$chunk     = mb_substr( $line, $start, 75 );
				$chunk_len = mb_strlen( $chunk );
				$start    += $chunk_len;
				if ( $start < $len ) {
					$chunks[] = $chunk . "\r\n ";
				} else {
					$chunks[] = $chunk . "\r\n";
					return implode( '', $chunks );
				}
			}
		}

		/**
		 * Perform the encoding necessary for ICS feed text.
		 *
		 * @param string $text The string that needs to be escaped.
		 * @return string The string after escaping for ICS.
		 * @since 0.8
		 */
		public function do_ics_escaping( $text ) {
			$text = str_replace( ',', '\,', $text );
			$text = str_replace( ';', '\:', $text );
			$text = str_replace( '\\', '\\\\', $text );
			return $text;
		}

		/**
		 * Convert a time string into a `.ics` formatted time string with the proper GMT offset.
		 *
		 * @param string $time_string       Any time string that `strtotime()` can understand.
		 * @param int    $offset_in_seconds Allows to offset the timestamp generated from $time_string.
		 *
		 * @return string|false
		 */
		public static function ics_format_time( $time_string, $offset_in_seconds = 0 ) {

			// Timestamp it.
			$timestamp = strtotime( $time_string );

			if ( ! $timestamp ) {
				return false;
			}

			// Subtract GMT Offset to return to UTC+0.
			$timestamp -= get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;

			// Add manual offset.
			$timestamp += $offset_in_seconds;

			// \T and \Z are escaped for literal T and Z characters
			return date( 'Ymd\THis\Z', $timestamp );
		}

		/**
		 * Handle a request to regenerate the calendar feed secret
		 *
		 * @since 0.8
		 */
		public function handle_regenerate_calendar_feed_secret() {

			if ( ! isset( $_GET['action'] ) || 'ef_calendar_regenerate_calendar_feed_secret' != $_GET['action'] ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html( $this->module->messages['invalid-permissions'] ) );
			}

			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ef-regenerate-ics-key' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				wp_die( esc_html( $this->module->messages['nonce-failed'] ) );
			}

			EditFlow()->update_module_option( $this->module->name, 'ics_secret_key', wp_generate_password() );

			wp_safe_redirect( add_query_arg( 'message', 'key-regenerated', menu_page_url( $this->module->settings_slug, false ) ) );
			exit;
		}

		/**
		 * Get a user's screen options
		 *
		 * @since 0.7
		 * @uses get_user_meta()
		 *
		 * @return array $screen_options The screen options values
		 */
		public function get_screen_options() {

			/**
			 * `num_weeks` has been moved to a filter and out of screen options, it's maintained here for legacy purposes
			 *
			 * @deprecated `num_weeks`
			 */
			$defaults       = array(
				'num_weeks' => (int) $this->total_weeks,
			);
			$current_user   = wp_get_current_user();
			$screen_options = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_options', true );
			$screen_options = array_merge( (array) $defaults, (array) $screen_options );

			return $screen_options;
		}

		/**
		 * Get the user's filters for calendar, either with $_GET or from saved
		 *
		 * @uses get_user_meta()
		 * @return array $filters All of the set or saved calendar filters
		 */
		public function get_filters() {
			$current_user = wp_get_current_user();
			$filters      = array();
			$old_filters  = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );

			/**
			 * To support legacy screen option for num_weeks
			 */
			$screen_options = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_options', true );

			$default_filters = array(
				'post_status' => '',
				'cpt'         => '',
				'cat'         => '',
				'author'      => '',
				'num_weeks'   => $this->total_weeks,
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for date calculation in calendar context.
				'start_date'  => date( 'Y-m-d', current_time( 'timestamp' ) ),
			);
			$old_filters     = array_merge( $default_filters, isset( $screen_options['num_weeks'] ) ? array( 'num_weeks' => $screen_options['num_weeks'] ) : array(), (array) $old_filters );

			// Sanitize and validate any newly added filters.
			foreach ( $old_filters as $key => $old_value ) {
				if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter values are sanitized below and stored per user.
					$new_value = $this->sanitize_filter( $key, $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Filter values are sanitized by sanitize_filter().
					if ( false !== $new_value ) {
						$filters[ $key ] = $new_value;
						continue;
					}
				}
				$filters[ $key ] = $old_value;
			}

			// Set the start date as the beginning of the week, according to blog settings.
			$filters['start_date'] = $this->get_beginning_of_week( $filters['start_date'] );

			$filters = apply_filters( 'ef_calendar_filter_values', $filters, $old_filters );

			$this->update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $filters );

			return $filters;
		}

		/**
		 * Build all of the HTML for the calendar view.
		 */
		public function view_calendar() {
			$supported_post_types = $this->get_post_types_for_module( $this->module );

			// Get filters either from $_GET or from user settings.
			$filters = $this->get_filters();

			// Total number of weeks to display on the calendar. Run it through a filter in case we want to override the
			// user's standard.
			$this->total_weeks = apply_filters( 'ef_calendar_total_weeks', $filters['num_weeks'], 'dashboard' );

			$dotw = array(
				'Sat',
				'Sun',
			);
			$dotw = apply_filters( 'ef_calendar_weekend_days', $dotw );

			// For generating the WP Query objects later on.
			$post_query_args  = array(
				'post_status' => $filters['post_status'],
				'post_type'   => $filters['cpt'],
				'cat'         => $filters['cat'],
				'author'      => $filters['author'],
			);
			$this->start_date = $filters['start_date'];

			// We use this later to label posts if they need labeling.
			if ( count( $supported_post_types ) > 1 ) {
				$all_post_types = get_post_types( null, 'objects' );
			}
			$dates        = array();
			$heading_date = $filters['start_date'];
			for ( $i = 0; $i < 7; $i++ ) {
				$dates[ $i ]  = $heading_date;
				$heading_date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $heading_date ) ) );
			}

			// We sort by post statuses, eventually.
			$post_statuses = $this->get_calendar_post_stati();
			?>
		<div class="wrap">
			<div id="ef-calendar-title"><!-- Calendar Title -->
				<?php echo '<img src="' . esc_url( $this->module->img_url ) . '" class="module-icon icon32" />'; ?>
				<h2><?php esc_html_e( 'Calendar', 'edit-flow' ); ?>&nbsp;<span class="time-range"><?php $this->calendar_time_range(); ?></span></h2>
			</div><!-- /Calendar Title -->

			<?php
				// Handle posts that have been trashed or untrashed.
				// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- These GET params are set by WordPress core's trash/untrash actions.
			if ( isset( $_GET['trashed'] ) || isset( $_GET['untrashed'] ) ) {
				echo '<div id="trashed-message" class="updated"><p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( isset( $_GET['trashed'] ) && (int) $_GET['trashed'] ) {
					/* translators: %d: number of posts trashed */
					echo esc_html( sprintf( _n( '%d post moved to the trash.', '%d posts moved to the trash.', $_GET['trashed'], 'edit-flow' ), number_format_i18n( $_GET['trashed'] ) ) );
					$ids       = isset( $_GET['ids'] ) ? $_GET['ids'] : 0;
					$pid       = explode( ',', $ids );
					$post_type = get_post_type( $pid[0] );
					echo ' <a href="' . esc_url( wp_nonce_url( "edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids", 'bulk-posts' ) ) . '">' . esc_html__( 'Undo', 'edit-flow' ) . '</a><br />';
					unset( $_GET['trashed'] );
				}
				if ( isset( $_GET['untrashed'] ) && (int) $_GET['untrashed'] ) {
					/* translators: %d: number of posts restored */
					echo esc_html( sprintf( _n( '%d post restored from the Trash.', '%d posts restored from the Trash.', $_GET['untrashed'], 'edit-flow' ), number_format_i18n( $_GET['untrashed'] ) ) );
					unset( $_GET['undeleted'] );
				}
				echo '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			?>

			<div id="ef-calendar-navigation-mount"></div> <!-- Mount point for React -->

			<div id="ef-calendar-wrap"><!-- Calendar Wrapper -->

			<?php
				$table_classes = array();
				// CSS doesn't like our classes to start with numbers.
			if ( 1 == $this->total_weeks ) {
				$table_classes[] = 'one-week-showing';
			} elseif ( 2 == $this->total_weeks ) {
				$table_classes[] = 'two-weeks-showing';
			} elseif ( 3 == $this->total_weeks ) {
				$table_classes[] = 'three-weeks-showing';
			}

				$table_classes = apply_filters( 'ef_calendar_table_classes', $table_classes );
			?>
			<table id="ef-calendar-view" class="<?php echo esc_attr( implode( ' ', $table_classes ) ); ?>">
				<thead>
				<tr class="calendar-heading">
					<?php echo $this->get_time_period_header( $dates ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</tr>
				</thead>
				<tbody>

				<?php
				$current_month = date_i18n( 'F', strtotime( $filters['start_date'] ) );
				for ( $current_week = 1; $current_week <= $this->total_weeks; $current_week++ ) :
					// We need to set the object variable for our posts_where filter.
					$this->current_week = $current_week;
					$week_posts         = $this->get_calendar_posts_for_week( $post_query_args );
					$date_format        = 'Y-m-d';
					$week_single_date   = $this->get_beginning_of_week( $filters['start_date'], $date_format, $current_week );
					$week_dates         = array();
					$split_month        = false;
					for ( $i = 0; $i < 7; $i++ ) {
						$week_dates[ $i ]  = $week_single_date;
						$single_date_month = date_i18n( 'F', strtotime( $week_single_date ) );
						if ( $single_date_month != $current_month ) {
							$split_month   = $single_date_month;
							$current_month = $single_date_month;
						}
						$week_single_date = date( 'Y-m-d', strtotime( '+1 day', strtotime( $week_single_date ) ) );
					}
					?>
					<?php if ( $split_month ) : ?>
				<tr class="month-marker">
						<?php
						foreach ( $week_dates as $key => $week_single_date ) {
							if ( date_i18n( 'F', strtotime( $week_single_date ) ) != $split_month && date_i18n( 'F', strtotime( '+1 day', strtotime( $week_single_date ) ) ) == $split_month ) {
								$previous_month = date_i18n( 'F', strtotime( $week_single_date ) );
								echo '<td class="month-marker-previous">' . esc_html( $previous_month ) . '</td>';
							} elseif ( date_i18n( 'F', strtotime( $week_single_date ) ) == $split_month && date_i18n( 'F', strtotime( '-1 day', strtotime( $week_single_date ) ) ) != $split_month ) {
								echo '<td class="month-marker-current">' . esc_html( $split_month ) . '</td>';
							} else {
								echo '<td class="month-marker-empty"></td>';
							}
						}
						?>
				</tr>
				<?php endif; ?>

				<tr class="week-unit">
					<?php foreach ( $week_dates as $day_num => $week_single_date ) : ?>
						<?php
						// Sort all of the day's posts by post status order.
						if ( ! empty( $week_posts[ $week_single_date ] ) ) {
							$week_posts_by_status = array();
							foreach ( $post_statuses as $post_status ) {
								$week_posts_by_status[ $post_status->name ] = array();
							}
							// These statuses aren't handled by custom statuses or post statuses.
							$week_posts_by_status['private'] = array();
							$week_posts_by_status['publish'] = array();
							$week_posts_by_status['future']  = array();
							foreach ( $week_posts[ $week_single_date ] as $num => $post ) {
								$week_posts_by_status[ $post->post_status ][ $num ] = $post;
							}
							unset( $week_posts[ $week_single_date ] );
							foreach ( $week_posts_by_status as $status ) {
								foreach ( $status as $num => $post ) {
									$week_posts[ $week_single_date ][] = $post;
								}
							}
						}

						$td_classes = array(
							'day-unit',
						);
						$day_name   = date( 'D', strtotime( $week_single_date ) );

						if ( in_array( $day_name, $dotw ) ) {
							$td_classes[] = 'weekend-day';
						}

						// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for date comparison in calendar display.
						if ( date( 'Y-m-d', current_time( 'timestamp' ) ) == $week_single_date ) {
							$td_classes[] = 'today';
						}

						// Last day of the week.
						if ( 6 == $day_num ) {
							$td_classes[] = 'last-day';
						}

						$td_classes = apply_filters( 'ef_calendar_table_td_classes', $td_classes, $week_single_date );
						// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for date comparison in calendar display.
						$is_today = date( 'Y-m-d', current_time( 'timestamp' ) ) == $week_single_date;
						?>
				<td class="<?php echo esc_attr( implode( ' ', $td_classes ) ); ?>" id="date-<?php echo esc_attr( $week_single_date ); ?>">
					<button class='schedule-new-post-button'>+</button>
						<?php if ( $is_today ) : ?>
						<div class="day-unit-today"><?php esc_html_e( 'Today', 'edit-flow' ); ?></div>
					<?php endif; ?>
					<div class="day-unit-label"><?php echo esc_html( date( 'j', strtotime( $week_single_date ) ) ); ?></div>
					<ul class="post-list">
						<?php
						$this->hidden = 0;
						if ( ! empty( $week_posts[ $week_single_date ] ) ) {
							$week_posts[ $week_single_date ] = apply_filters( 'ef_calendar_posts_for_week', $week_posts[ $week_single_date ], $week_single_date );

							foreach ( $week_posts[ $week_single_date ] as $num => $post ) {
								$output = apply_filters( 'ef_pre_calendar_single_date_item_html', '', $this, $num, $post, $week_single_date );
								if ( ! $output ) {
									$output = $this->generate_post_li_html( $post, $week_single_date, $num );
								}
								echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
						}
						?>
					</ul>
						<?php if ( $this->hidden ) : ?>
						<a class="show-more" href="#"><?php /* translators: %d = number of posts to show */ printf( esc_html__( 'Show %d more', 'edit-flow' ), absint( $this->hidden ) ); ?></a>
					<?php endif; ?>

						<?php
						if ( current_user_can( $this->create_post_cap ) ) :
							$date_formatted = date( 'D, M jS, Y', strtotime( $week_single_date ) );
							?>

						<form method="POST" class="post-insert-dialog">
							<?php /* translators: %1$s = post type name, %2$s = date */ ?>
							<h1><?php printf( esc_html__( 'Schedule a %1$s for %2$s', 'edit-flow' ), esc_html( $this->get_quick_create_post_type_name() ), esc_html( $date_formatted ) ); ?></h1>
							<?php /* translators: %s = post type name */ ?>
							<input type="text" class="post-insert-dialog-post-title" name="post-insert-dialog-post-title" placeholder="<?php echo esc_attr( sprintf( _x( '%s Title', 'post type name', 'edit-flow' ), $this->get_quick_create_post_type_name() ) ); ?>">
							<input type="hidden" class="post-insert-dialog-post-date" name="post-insert-dialog-post-title" value="<?php echo esc_attr( $week_single_date ); ?>">
							<div class="post-insert-dialog-controls">
								<input type="submit" class="button left" value="<?php /* translators: %s = post type name */ echo esc_attr( sprintf( _x( 'Create %s', 'post type name', 'edit-flow' ), $this->get_quick_create_post_type_name() ) ); ?>">
								<a class="post-insert-dialog-edit-post-link" href="#"><?php /* translators: %s = post type name */ echo esc_html( sprintf( _x( 'Edit %s', 'post type name', 'edit-flow' ), $this->get_quick_create_post_type_name() ) ); ?>&nbsp;&raquo;</a>
							</div>
							<div class="spinner">&nbsp;</div>
						</form>
						<?php endif; ?>

					</td>
					<?php endforeach; ?>
					</tr>

					<?php endfor; ?>

					</tbody>
					</table><!-- /Week Wrapper -->
					<?php
					// Nonce field for AJAX actions.
					wp_nonce_field( 'ef-calendar-modify', 'ef-calendar-modify' );
					?>

					<div class="clear"></div>
				</div><!-- /Calendar Wrapper -->

				</div>

			<?php
		}

		/**
		 * Generates the HTML for a single post item in the calendar.
		 *
		 * @param object $post      The WordPress post in question.
		 * @param string $post_date The date of the post.
		 * @param int    $num       The index of the post.
		 *
		 * @return string HTML for a single post item.
		 */
		public function generate_post_li_html( $post, $post_date, $num = 0 ) {

			ob_start();
			$post_id       = $post->ID;
			$status_object = get_post_status_object( get_post_status( $post_id ) );

			$post_classes = array(
				'day-item',
				'custom-status-' . $post->post_status,
			);
			// Only allow the user to drag the post if they have permissions to
			// or if it's in an approved post status
			// This is checked on the ajax request too.
			if ( $this->current_user_can_modify_post( $post ) && ! in_array( $post->post_status, $this->published_statuses ) ) {
				$post_classes[] = 'sortable';
			}

			if ( in_array( $post->post_status, $this->published_statuses ) ) {
				$post_classes[] = 'is-published';
			}

			// Hide posts over a certain number to prevent clutter, unless user is only viewing 1 or 2 weeks.
			$max_visible_posts = apply_filters( 'ef_calendar_max_visible_posts_per_date', $this->max_visible_posts_per_date );

			if ( $num >= $max_visible_posts && $this->total_weeks > 2 ) {
				$post_classes[] = 'hidden';
				++$this->hidden;
			}
			$post_classes = apply_filters( 'ef_calendar_table_td_li_classes', $post_classes, $post_date, $post->ID );

			?>
		<li class="<?php echo esc_attr( implode( ' ', $post_classes ) ); ?>" id="post-<?php echo esc_attr( $post->ID ); ?>">
			<div style="clear:right;"></div>
			<div class="item-static">
				<div class="item-default-visible">
					<div class="item-status"><span class="status-text"><?php echo esc_html( $status_object->label ); ?></span></div>
					<div class="inner">
						<span class="item-headline post-title"><strong><?php echo esc_html( _draft_or_post_title( $post->ID ) ); ?></strong></span>
					</div>
					<?php do_action( 'ef_calendar_item_html', $post->ID ); ?>
				</div>
				<div class="item-inner">
					<?php $this->get_inner_information( $this->get_post_information_fields( $post ), $post ); ?>
				</div>
			</div>
		</li>
			<?php

			$post_li_html = ob_get_contents();
			ob_end_clean();

			return $post_li_html;
		}

		/**
		 * Generate the inner HTML elements for a calendar item.
		 *
		 * Functionality for generating the inner html elements on the calendar
		 * has been separated out so various ajax functions can reload certain
		 * parts of an inner html element.
		 *
		 * @since 0.8
		 *
		 * @param array   $ef_calendar_item_information_fields Array of information fields.
		 * @param WP_Post $post                                The post object.
		 */
		public function get_inner_information( $ef_calendar_item_information_fields, $post ) {
			?>
			<table class="item-information">
				<?php foreach ( $this->get_post_information_fields( $post ) as $field => $values ) : ?>
					<tr class="item-field item-information-<?php echo esc_attr( $field ); ?>">
						<th class="label"><?php echo esc_html( $values['label'] ); ?>:</th>
						<?php if ( $values['value'] && isset( $values['type'] ) ) : ?>
							<?php if ( isset( $values['editable'] ) && $this->current_user_can_modify_post( $post ) ) : ?>
								<?php $editable_class = $values['editable'] ? 'editable-value' : ''; ?>
								<td class="value <?php echo esc_attr( $editable_class ); ?>"><?php echo esc_html( $values['value'] ); ?></td>
								<?php if ( $values['editable'] ) : ?>
									<td class="editable-html hidden" data-type="<?php echo esc_attr( $values['type'] ); ?>" data-metadataterm="<?php echo esc_attr( str_replace( 'editorial-metadata-', '', str_replace( 'tax_', '', $field ) ) ); ?>"><?php echo $this->get_editable_html( $values['type'], $values['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<?php endif; ?>
							<?php else : ?>
								<td class="value"><?php echo esc_html( $values['value'] ); ?></td>
							<?php endif; ?>
						<?php elseif ( $values['value'] ) : ?>
							<td class="value"><?php echo esc_html( $values['value'] ); ?></td>
						<?php else : ?>
						<td class="value"><em class="none"><?php esc_html_e( 'None', 'edit-flow' ); ?></em></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<?php do_action( 'ef_calendar_item_additional_html', $post->ID ); ?>
			</table>
			<?php
				$post_type_object = get_post_type_object( $post->post_type );
				$item_actions     = array();
			if ( $this->current_user_can_modify_post( $post ) ) {
				// Edit this post.
				$item_actions['edit'] = '<a href="' . get_edit_post_link( $post->ID, true ) . '" title="' . esc_attr( __( 'Edit this item', 'edit-flow' ) ) . '">' . __( 'Edit', 'edit-flow' ) . '</a>';
				// Trash this post.
				$item_actions['trash'] = '<a href="' . get_delete_post_link( $post->ID ) . '" title="' . esc_attr__( 'Trash this item', 'edit-flow' ) . '">' . __( 'Trash', 'edit-flow' ) . '</a>';
				// Preview/view this post.
				if ( ! in_array( $post->post_status, $this->published_statuses ) ) {
					/* translators: %s: post title */
					$item_actions['view'] = '<a href="' . esc_url( apply_filters( 'preview_post_link', add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ), $post ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;', 'edit-flow' ), $post->post_title ) ) . '" rel="permalink">' . __( 'Preview', 'edit-flow' ) . '</a>';
				} elseif ( 'trash' != $post->post_status ) {
					/* translators: %s: post title */
					$item_actions['view'] = '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'edit-flow' ), $post->post_title ) ) . '" rel="permalink">' . __( 'View', 'edit-flow' ) . '</a>';
				}
				// Save metadata.
				/* translators: %s: post title */
				$item_actions['save hidden'] = '<a href="#savemetadata" id="save-editorial-metadata" class="post-' . esc_attr( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'Save &#8220;%s&#8221;', 'edit-flow' ), $post->post_title ) ) . '" >' . __( 'Save', 'edit-flow' ) . '</a>';
			}
				// Allow other plugins to add actions.
				$item_actions = apply_filters( 'ef_calendar_item_actions', $item_actions, $post->ID );
			if ( count( $item_actions ) ) {
				// Separate the save action to render it on its own row.
				$save_action = '';
				if ( isset( $item_actions['save hidden'] ) ) {
					$save_action = $item_actions['save hidden'];
					unset( $item_actions['save hidden'] );
				}

				echo '<div class="item-actions">';
				$html = '';
				foreach ( $item_actions as $class => $item_action ) {
					$html .= '<span class="' . esc_attr( $class ) . '">' . $item_action . '</span> | '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo rtrim( $html, ' | ' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				// Render save button on its own row (hidden by default, shown via JS when editing).
				if ( $save_action ) {
					echo '<span class="save hidden">' . $save_action . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				echo '</div>';
			}
			?>
			<div style="clear:right;"></div>
			<?php
		}

		/**
		 * Get editable HTML for a metadata field type.
		 *
		 * @param string $type  The metadata field type.
		 * @param string $value The current field value.
		 * @return string|void The HTML input element.
		 */
		public function get_editable_html( $type, $value ) {

			switch ( $type ) {
				case 'text':
				case 'location':
				case 'number':
					return '<input type="text" class="metadata-edit-' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"/>';
				case 'paragraph':
					return '<textarea type="text" class="metadata-edit-' . esc_attr( $type ) . '">' . esc_html( $value ) . '</textarea>';
				case 'date':
					// Convert display value to datetime-local format (Y-m-d\TH:i).
					$datetime_value = '';
					if ( ! empty( $value ) ) {
						$timestamp = strtotime( $value );
						if ( false !== $timestamp ) {
							$datetime_value = date( 'Y-m-d\TH:i', $timestamp );
						}
					}
					return '<input type="datetime-local" value="' . esc_attr( $datetime_value ) . '" class="metadata-edit-' . esc_attr( $type ) . '"/>';
				case 'checkbox':
					$output = '<select class="metadata-edit">';

					if ( 'No' == $value ) {
						$output .= '<option value="0">No</option><option value="1">Yes</option>';
					} else {
						$output .= '<option value="1">Yes</option><option value="0">No</option>';
					}

					$output .= '</select>';

					return $output;
				case 'user':
					return wp_dropdown_users( array( 'echo' => false ) );
				case 'taxonomy':
					return '<input type="text" class="metadata-edit-' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" />';
			}
		}

		/**
		 * Get the information fields to be presented with each post popup.
		 *
		 * @since 0.8
		 *
		 * @param object $post Post to gather information fields for.
		 * @return array $information_fields All of the information fields to be presented.
		 */
		public function get_post_information_fields( $post ) {

			$information_fields = array();
			// Post author.
			$information_fields['author'] = array(
				'label' => __( 'Author', 'edit-flow' ),
				'value' => get_the_author_meta( 'display_name', $post->post_author ),
				'type'  => 'author',
			);

			// If the calendar supports more than one post type, show the post type label.
			if ( count( $this->get_post_types_for_module( $this->module ) ) > 1 ) {
				$information_fields['post_type'] = array(
					'label' => __( 'Post Type', 'edit-flow' ),
					'value' => get_post_type_object( $post->post_type )->labels->singular_name,
				);
			}
			// Publication time for published statuses.
			$published_statuses = array(
				'publish',
				'future',
				'private',
			);
			if ( in_array( $post->post_status, $published_statuses ) ) {
				if ( 'future' == $post->post_status ) {
					$information_fields['post_date'] = array(
						'label' => __( 'Scheduled', 'edit-flow' ),
						'value' => get_the_time( null, $post->ID ),
					);
				} else {
					$information_fields['post_date'] = array(
						'label' => __( 'Published', 'edit-flow' ),
						'value' => get_the_time( null, $post->ID ),
					);
				}
			}
			// Taxonomies and their values.
			$args       = array(
				'post_type' => $post->post_type,
			);
			$taxonomies = get_object_taxonomies( $args, 'object' );
			foreach ( (array) $taxonomies as $taxonomy ) {
				// Sometimes taxonomies skip by, so let's make sure it has a label too.
				if ( ! $taxonomy->public || ! $taxonomy->label ) {
					continue;
				}

				$terms = get_the_terms( $post->ID, $taxonomy->name );
				if ( ! $terms || is_wp_error( $terms ) ) {
					continue;
				}

				$key = 'tax_' . $taxonomy->name;
				if ( count( $terms ) ) {
					$value = '';
					foreach ( (array) $terms as $term ) {
						$value .= $term->name . ', ';
					}
					$value = rtrim( $value, ', ' );
				} else {
					$value = '';
				}
				$information_fields[ $key ] = array(
					'label' => $taxonomy->label,
					'value' => $value,
				);

				// Only allow non-hierarchical taxonomies to be edited in the calendar.
				// Hierarchical taxonomies (like categories) cause performance issues and
				// the single-select UI removes all but one category when saved.
				if ( is_taxonomy_hierarchical( $taxonomy->name ) ) {
					$information_fields[ $key ]['type'] = 'taxonomy hierarchical';
				} else {
					$information_fields[ $key ]['type'] = 'taxonomy';

					if ( 'page' == $post->post_type ) {
						$ed_cap = 'edit_page';
					} else {
						$ed_cap = 'edit_post';
					}

					if ( current_user_can( $ed_cap, $post->ID ) ) {
						$information_fields[ $key ]['editable'] = true;
					}
				}
			}

			$information_fields = apply_filters( 'ef_calendar_item_information_fields', $information_fields, $post->ID );
			foreach ( $information_fields as $field => $values ) {
				// Allow filters to hide empty fields or to hide any given individual field. Hide empty fields by default.
				if ( ( apply_filters( 'ef_calendar_hide_empty_item_information_fields', true, $post->ID ) && empty( $values['value'] ) )
					|| apply_filters( "ef_calendar_hide_{$field}_item_information_field", false, $post->ID ) ) {
					unset( $information_fields[ $field ] );
				}
			}
			return $information_fields;
		}

		/**
		 * Generate the calendar header for a given range of dates.
		 *
		 * @param array $dates Date range for the header.
		 * @return string $html Generated HTML for the header.
		 */
		public function get_time_period_header( $dates ) {

			$html = '';
			foreach ( $dates as $date ) {
				$html .= '<th class="column-heading" >';
				$html .= esc_html( date_i18n( 'l', strtotime( $date ) ) );
				$html .= '</th>';
			}

			return $html;
		}

		/**
		 * Query to get all of the calendar posts for a given day.
		 *
		 * @param array  $args    Any filter arguments we want to pass.
		 * @param string $context Where the query is coming from, to distinguish dashboard and subscriptions.
		 * @return array $posts All of the posts as an array sorted by date.
		 */
		public function get_calendar_posts_for_week( $args = array(), $context = 'dashboard' ) {

			$supported_post_types = $this->get_post_types_for_module( $this->module );
			$defaults             = array(
				'post_status'    => null,
				'cat'            => null,
				'author'         => null,
				'post_type'      => $supported_post_types,
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Calendar needs to show all posts for the week.
				'posts_per_page' => 200,
			);

			$args = array_merge( $defaults, $args );

			// Unpublished as a status is just an array of everything but 'publish'.
			if ( 'unpublish' == $args['post_status'] ) {
				$args['post_status'] = '';
				$post_stati          = wp_filter_object_list( $this->get_calendar_post_stati(), array( 'name' => 'publish' ), 'not' );

				if ( ! apply_filters( 'ef_show_scheduled_as_unpublished', false ) ) {
					$post_stati = wp_filter_object_list( $post_stati, array( 'name' => 'future' ), 'not' );
				}

				$args['post_status'] .= implode( ',', wp_list_pluck( $post_stati, 'name' ) );
			}
			// The WP functions for printing the category and author assign a value of 0 to the default
			// options, but passing this to the query is bad (trashed and auto-draft posts appear!), so
			// unset those arguments.
			if ( '0' === $args['cat'] ) {
				unset( $args['cat'] );
			}
			if ( '0' === $args['author'] ) {
				unset( $args['author'] );
			}

			if ( empty( $args['post_type'] ) || ! in_array( $args['post_type'], $supported_post_types ) ) {
				$args['post_type'] = $supported_post_types;
			}

			$beginning_date = $this->get_beginning_of_week( $this->start_date, 'Y-m-d', $this->current_week );
			$ending_date    = date( 'Y-m-d', strtotime( $beginning_date ) + WEEK_IN_SECONDS );

			$args['date_query'] = array(
				'after'     => $beginning_date,
				'before'    => $ending_date,
				'inclusive' => true,
			);

			// Filter for an end user to implement any of their own query args.
			$args         = apply_filters( 'ef_calendar_posts_query_args', $args, $context );
			$post_results = new WP_Query( $args );

			$posts = array();
			while ( $post_results->have_posts() ) {
				$post_results->the_post();
				global $post;
				$key_date             = date( 'Y-m-d', strtotime( $post->post_date ) );
				$posts[ $key_date ][] = $post;
			}

			return $posts;
		}

		/**
		 * Gets the link for the next time period.
		 *
		 * @param string $direction    'previous' or 'next', direction to go in time.
		 * @param array  $filters      Any filters that need to be applied.
		 * @param int    $weeks_offset Number of weeks we're offsetting the range.
		 * @return string $url The URL for the next page.
		 */
		public function get_pagination_link( $direction = 'next', $filters = array(), $weeks_offset = null ) {

			$supported_post_types = $this->get_post_types_for_module( $this->module );

			if ( ! isset( $weeks_offset ) ) {
				$weeks_offset = $this->total_weeks;
			} elseif ( 0 == $weeks_offset ) {
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for date calculation in calendar context.
				$filters['start_date'] = $this->get_beginning_of_week( date( 'Y-m-d', current_time( 'timestamp' ) ) );
			}

			if ( 'previous' == $direction ) {
				$weeks_offset = '-' . $weeks_offset;
			}

			$filters['start_date'] = date( 'Y-m-d', strtotime( $weeks_offset . ' weeks', strtotime( $filters['start_date'] ) ) );
			$url                   = add_query_arg( $filters, menu_page_url( $this->module->slug, false ) );

			if ( count( $supported_post_types ) > 1 ) {
				$url = add_query_arg( 'cpt', $filters['cpt'], $url );
			}

			return $url;
		}

		/**
		 * Given a day in string format, returns the day at the beginning of that week, which can be the given date.
		 * The beginning of the week is determined by the blog option, 'start_of_week'.
		 *
		 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
		 *
		 * @param string $date   String representing a date.
		 * @param string $format Date format in which the beginning of the week should be returned.
		 * @param int    $week   Number of weeks we're offsetting the range.
		 * @return string $formatted_start_of_week Beginning of the week.
		 */
		public function get_beginning_of_week( $date, $format = 'Y-m-d', $week = 1 ) {

			$date                        = strtotime( $date );
			$start_of_week               = get_option( 'start_of_week' );
			$day_of_week                 = date( 'w', $date );
			$date                       += ( ( $start_of_week - $day_of_week - 7 ) % 7 ) * 60 * 60 * 24;
			$date                        = strtotime( '+' . ( $week - 1 ) . ' week', $date );
				$formatted_start_of_week = date( $format, $date );
			return $formatted_start_of_week;
		}

		/**
		 * Given a day in string format, returns the day at the end of that week, which can be the given date.
		 * The end of the week is determined by the blog option, 'start_of_week'.
		 *
		 * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
		 *
		 * @param string $date   String representing a date.
		 * @param string $format Date format in which the end of the week should be returned.
		 * @param int    $week   Number of weeks we're offsetting the range.
		 * @return string $formatted_end_of_week End of the week.
		 */
		public function get_ending_of_week( $date, $format = 'Y-m-d', $week = 1 ) {

			$date                  = strtotime( $date );
			$end_of_week           = get_option( 'start_of_week' ) - 1;
			$day_of_week           = date( 'w', $date );
			$date                 += ( ( $end_of_week - $day_of_week + 7 ) % 7 ) * 60 * 60 * 24;
			$date                  = strtotime( '+' . ( $week - 1 ) . ' week', $date );
			$formatted_end_of_week = date( $format, $date );
			return $formatted_end_of_week;
		}

		/**
		 * Human-readable time range for the calendar.
		 *
		 * Shows something like "for October 30th through November 26th" for a four-week period.
		 *
		 * @since 0.7
		 */
		public function calendar_time_range() {

			$first_datetime = strtotime( $this->start_date );
			$first_date     = date_i18n( get_option( 'date_format' ), $first_datetime );
			$total_days     = ( $this->total_weeks * 7 ) - 1;
			$last_datetime  = strtotime( '+' . $total_days . ' days', date( 'U', strtotime( $this->start_date ) ) );
			$last_date      = date_i18n( get_option( 'date_format' ), $last_datetime );
			// translators: %1$s = first date, %2$s = last date.
			echo esc_html( sprintf( __( 'for %1$s through %2$s', 'edit-flow' ), $first_date, $last_date ) );
		}

		/**
		 * Check whether the current user should have the ability to modify the post.
		 *
		 * @since 0.7
		 *
		 * @param object $post The post object we're checking.
		 * @return bool $can Whether or not the current user can modify the post.
		 */
		public function current_user_can_modify_post( $post ) {

			if ( ! $post ) {
				return false;
			}

			$post_type_object = get_post_type_object( $post->post_type );

			// Editors and admins are fine.
			if ( current_user_can( $post_type_object->cap->edit_others_posts, $post->ID ) ) {
				return true;
			}
			// Authors and contributors can move their own stuff if it's not published.
			if ( current_user_can( $post_type_object->cap->edit_post, $post->ID ) && wp_get_current_user()->ID == $post->post_author && ! in_array( $post->post_status, $this->published_statuses ) ) {
				return true;
			}
			// Those who can publish posts can move any of their own stuff.
			if ( current_user_can( $post_type_object->cap->publish_posts, $post->ID ) && wp_get_current_user()->ID == $post->post_author ) {
				return true;
			}

			return false;
		}

		/**
		 * Register settings for notifications so we can partially use the Settings API
		 * We use the Settings API for form generation, but not saving because we have our
		 * own way of handling the data.
		 *
		 * @since 0.7
		 */
		public function register_settings() {

			add_settings_section( $this->module->options_group_name . '_general', false, '__return_false', $this->module->options_group_name );
			add_settings_field( 'post_types', __( 'Post types to show', 'edit-flow' ), array( $this, 'settings_post_types_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'quick_create_post_type', __( 'Post type to create directly from calendar', 'edit-flow' ), array( $this, 'settings_quick_create_post_type_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
			add_settings_field( 'ics_subscription', __( 'Subscription in iCal or Google Calendar', 'edit-flow' ), array( $this, 'settings_ics_subscription_option' ), $this->module->options_group_name, $this->module->options_group_name . '_general' );
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
		 * Choose the post type that should be created on the calendar
		 *
		 * @since 0.8
		 */
		public function settings_quick_create_post_type_option() {

			$allowed_post_types = $this->get_all_post_types();

			echo "<select name='" . esc_attr( $this->module->options_group_name ) . "[quick_create_post_type]'>";
			foreach ( $allowed_post_types as $post_type => $title ) {
				echo "<option value='" . esc_attr( $post_type ) . "' " . selected( $post_type, $this->module->options->quick_create_post_type, false ) . '>' . esc_html( $title ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Enable calendar subscriptions via .ics in iCal or Google Calendar
		 *
		 * @since 0.8
		 */
		public function settings_ics_subscription_option() {
			$options = array(
				'off' => __( 'Disabled', 'edit-flow' ),
				'on'  => __( 'Enabled', 'edit-flow' ),
			);
			echo '<select id="ics_subscription" name="' . esc_attr( $this->module->options_group_name ) . '[ics_subscription]">';
			foreach ( $options as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"';
				echo selected( $this->module->options->ics_subscription, $value );
				echo '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';


			$regenerate_url = add_query_arg( 'action', 'ef_calendar_regenerate_calendar_feed_secret', admin_url( 'index.php' ) );
			$regenerate_url = wp_nonce_url( $regenerate_url, 'ef-regenerate-ics-key' );
			echo '&nbsp;&nbsp;&nbsp;<a href="' . esc_url( $regenerate_url ) . '">' . esc_html__( 'Regenerate calendar feed secret', 'edit-flow' ) . '</a>';

			// If our secret key doesn't exist, create a new one.
			if ( empty( $this->module->options->ics_secret_key ) ) {
				EditFlow()->update_module_option( $this->module->name, 'ics_secret_key', wp_generate_password() );
			}
		}

		/**
		 * Validate the data submitted by the user in calendar settings.
		 *
		 * @since 0.7
		 *
		 * @param array $new_options The new options to validate.
		 * @return array The validated options.
		 */
		public function settings_validate( $new_options ) {

			$options = (array) $this->module->options;

			$options['post_types'] = $this->clean_post_type_options( $new_options['post_types'], $this->module->post_type_support );

			if ( in_array( $new_options['quick_create_post_type'], array_keys( $this->get_all_post_types() ) ) ) {
				$options['quick_create_post_type'] = $new_options['quick_create_post_type'];
			}

			if ( 'on' != $new_options['ics_subscription'] ) {
				$options['ics_subscription'] = 'off';
			} else {
				$options['ics_subscription'] = 'on';
			}

			return $options;
		}

		/**
		 * Settings page for calendar.
		 */
		public function print_configure_view() {
			global $edit_flow;
			?>
		<form class="basic-settings" action="<?php echo esc_url( menu_page_url( $this->module->settings_slug, false ) ); ?>" method="post">
			<?php settings_fields( $this->module->options_group_name ); ?>
			<?php do_settings_sections( $this->module->options_group_name ); ?>
			<?php
				echo '<input id="edit_flow_module_name" name="edit_flow_module_name" type="hidden" value="' . esc_attr( $this->module->name ) . '" />';
			?>
			<?php submit_button(); ?>
		</form>
			<?php
		}

		/**
		 * Ajax callback to insert a post placeholder for a particular date.
		 *
		 * @since 0.8
		 */
		public function handle_ajax_insert_post() {

			// Nonce check.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value passed directly to wp_verify_nonce().
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ef-calendar-modify' ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
			}

			// Check that the user has the right capabilities to add posts to the calendar (defaults to 'edit_posts').
			if ( ! current_user_can( $this->create_post_cap ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
			}

			if ( empty( $_POST['ef_insert_date'] ) ) {
				$this->print_ajax_response( 'error', __( 'No date supplied.', 'edit-flow' ) );
			}

			// Post type has to be visible on the calendar to create a placeholder.
			if ( ! in_array( $this->module->options->quick_create_post_type, $this->get_post_types_for_module( $this->module ) ) ) {
				$this->print_ajax_response( 'error', __( 'Please change Quick Create to use a post type viewable on the calendar.', 'edit-flow' ) );
			}

			// Sanitize post values.
			$post_title = isset( $_POST['ef_insert_title'] ) ? sanitize_text_field( $_POST['ef_insert_title'] ) : null;

			if ( ! $post_title ) {
				$post_title = esc_html__( 'Untitled', 'edit-flow' );
			}

			$post_date = sanitize_text_field( $_POST['ef_insert_date'] );

			$post_status = $this->get_default_post_status();

			// Set new post parameters.
			$post_placeholder = array(
				'post_title'  => $post_title,
				'post_status' => $post_status,
				'post_date'   => date( 'Y-m-d H:i:s', strtotime( $post_date ) ),
				'post_type'   => $this->module->options->quick_create_post_type,
			);

			// By default, adding a post to the calendar won't set the timestamp.
			// If the user desires that to be the behavior, they can set the result of this filter to 'true'.
			// With how WordPress works internally, setting 'post_date_gmt' will set the timestamp.
			if ( apply_filters( 'ef_calendar_allow_ajax_to_set_timestamp', false ) ) {
				$post_placeholder['post_date_gmt'] = date( 'Y-m-d H:i:s', strtotime( $post_date ) );
			}

			// Create the post.
			$post_id = wp_insert_post( $post_placeholder );

			if ( $post_id ) {
				$post = get_post( $post_id );

				// Generate the HTML for the post item so it can be injected.
				$post_li_html = $this->generate_post_li_html( $post, $post_date );

				// Announce success and send back the html to inject.
				$this->print_ajax_response( 'success', $post_li_html );
			} else {
				$this->print_ajax_response( 'error', __( 'Post could not be created', 'edit-flow' ) );
			}
		}

		/**
		 * Returns the singular label for the posts that are quick-created on the calendar.
		 *
		 * @return string Singular label for a post-type.
		 */
		public function get_quick_create_post_type_name() {

			$post_type_slug = $this->module->options->quick_create_post_type;
			$post_type_obj  = get_post_type_object( $post_type_slug );

			return $post_type_obj->labels->singular_name ? $post_type_obj->labels->singular_name : $post_type_slug;
		}

		/**
		 * Update the metadata from the calendar via AJAX.
		 *
		 * @since 0.8
		 */
		public function handle_ajax_update_metadata() {
			global $wpdb;

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value passed directly to wp_verify_nonce().
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ef-calendar-modify' ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['nonce-failed'] );
			}

			if ( ! isset( $_POST['post_id'] ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			}

			// Check that we got a proper post.
			$post_id = (int) $_POST['post_id'];
			$post    = get_post( $post_id );

			if ( ! $post ) {
				$this->print_ajax_response( 'error', $this->module->messages['missing-post'] );
			}


			if ( 'page' == $post->post_type ) {
				$edit_check = 'edit_page';
			} else {
				$edit_check = 'edit_post';
			}

			if ( ! current_user_can( $edit_check, $post->ID ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
			}

			// Check that the user can modify the post.
			if ( ! $this->current_user_can_modify_post( $post ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['invalid-permissions'] );
			}

			$default_types = array(
				'author',
				'taxonomy',
			);

			$metadata_types = array();

			if ( ! $this->module_enabled( 'editorial_metadata' ) ) {
				$this->print_ajax_response( 'error', $this->module->messages['update-error'] );
			}

			$metadata_types = array_keys( EditFlow()->editorial_metadata->get_supported_metadata_types() );

			// Update an editorial metadata field.
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized below before use.
			$metadata_term           = isset( $_POST['metadata_term'] ) ? $_POST['metadata_term'] : '';
			$metadata_type           = isset( $_POST['metadata_type'] ) ? $_POST['metadata_type'] : '';
			$incoming_metadata_value = isset( $_POST['metadata_value'] ) ? $_POST['metadata_value'] : '';
			// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( isset( $_POST['metadata_type'] ) && in_array( $_POST['metadata_type'], $metadata_types ) ) {
				$post_meta_key = sanitize_text_field( '_ef_editorial_meta_' . $_POST['metadata_type'] . '_' . $metadata_term );

				// Javascript date parsing is terrible, so use strtotime in PHP.
				if ( 'date' == $metadata_type ) {
					$metadata_value = strtotime( sanitize_text_field( $incoming_metadata_value ) );
				} else {
					$metadata_value = sanitize_text_field( $incoming_metadata_value );
				}

				update_post_meta( $post->ID, $post_meta_key, $metadata_value );
				$response = 'success';
			} else {
				switch ( $_POST['metadata_type'] ) {
					case 'taxonomy':
						$response = wp_set_post_terms( $post->ID, $incoming_metadata_value, $metadata_term );
						break;
					default:
						$response = new WP_Error( 'invalid-type', __( 'Invalid metadata type', 'edit-flow' ) );
						break;
				}
			}

			// Assuming we've got to this point, just regurgitate the value.
			if ( ! is_wp_error( $response ) ) {
				$this->print_ajax_response( 'success', $incoming_metadata_value );
			} else {
				$this->print_ajax_response( 'error', __( 'Metadata could not be updated.', 'edit-flow' ) );
			}
		}

		/**
		 * Get the filter names used in calendar queries.
		 *
		 * @return array Filter names.
		 */
		public function calendar_filters() {
			$select_filter_names = array();

			$select_filter_names['post_status'] = 'post_status';
			$select_filter_names['cat']         = 'cat';
			$select_filter_names['author']      = 'author';
			$select_filter_names['type']        = 'cpt';
			$select_filter_name['num_weeks']    = 'num_weeks';

			return apply_filters( 'ef_calendar_filter_names', $select_filter_names );
		}

		/**
		 * Sanitize a $_GET or similar filter being used on the calendar.
		 *
		 * @since 0.8
		 *
		 * @param string $key         Filter being sanitized.
		 * @param string $dirty_value Value to be sanitized.
		 * @return string|int|false $sanitized_value Safe to use value.
		 */
		public function sanitize_filter( $key, $dirty_value ) {

			switch ( $key ) {
				case 'post_status':
					// Whitelist-based validation for this parameter.
					$valid_statuses   = wp_list_pluck( $this->get_calendar_post_stati(), 'name' );
					$valid_statuses[] = 'unpublish';

					if ( in_array( $dirty_value, $valid_statuses ) ) {
						return $dirty_value;
					} else {
						return '';
					}
				case 'cpt':
					$cpt                  = sanitize_key( $dirty_value );
					$supported_post_types = $this->get_post_types_for_module( $this->module );
					if ( $cpt && in_array( $cpt, $supported_post_types ) ) {
						return $cpt;
					} else {
						return '';
					}
				case 'start_date':
					return date( 'Y-m-d', strtotime( $dirty_value ) );
				case 'cat':
				case 'author':
					return intval( $dirty_value );
				case 'num_weeks':
					$num_weeks = intval( $dirty_value );
					if ( $num_weeks <= 0 ) {
						return $this->total_weeks;
					} elseif ( $num_weeks > $this->max_weeks ) {
						return $this->max_weeks;
					} else {
						return $num_weeks;
					}
				default:
					return false;
			}
		}

		/**
		 * Cache the post date before update to work around core resetting draft dates.
		 *
		 * The calendar uses 'post_date' field to store the position on the calendar.
		 * If a post has a core post status assigned (e.g. 'draft' or 'pending'), the `post_date`
		 * field will be reset when `wp_update_post()` is used.
		 *
		 * This method temporarily caches the `post_date` field if it needs to be restored.
		 *
		 * @see http://core.trac.wordpress.org/browser/tags/3.7.1/src/wp-includes/post.php#L2998
		 * @uses fix_post_date_on_update_part_two()
		 *
		 * @param int   $post_ID Post ID.
		 * @param array $data    Post data being saved.
		 */
		public function fix_post_date_on_update_part_one( $post_ID, $data ) {

			$post = get_post( $post_ID );

			// `post_date` is only nooped for these three statuses,
			// but don't try to persist if `post_date_gmt` is set.
			if ( ! in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) )
			|| '0000-00-00 00:00:00' !== $post->post_date_gmt
			|| '0000-00-00 00:00:00' !== $data['post_date_gmt'] ) {
				return;
			}

			$this->post_date_cache[ $post_ID ] = $post->post_date;
		}

		/**
		 * Restore the post date after update to work around core resetting draft dates.
		 *
		 * The calendar uses 'post_date' field to store the position on the calendar.
		 * If a post has a core post status assigned (e.g. 'draft' or 'pending'), the `post_date`
		 * field will be reset when `wp_update_post()` is used.
		 *
		 * This method restores the `post_date` field if it needs to be restored.
		 *
		 * @see http://core.trac.wordpress.org/browser/tags/3.7.1/src/wp-includes/post.php#L2998
		 * @uses fix_post_date_on_update_part_one()
		 *
		 * @param int     $post_ID     Post ID.
		 * @param WP_Post $post_after  Post object after the update.
		 * @param WP_Post $post_before Post object before the update.
		 */
		public function fix_post_date_on_update_part_two( $post_ID, $post_after, $post_before ) {
			global $wpdb;

			if ( empty( $this->post_date_cache[ $post_ID ] ) ) {
				return;
			}

			$post_date = $this->post_date_cache[ $post_ID ];
			unset( $this->post_date_cache[ $post_ID ] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Core workaround for custom status date handling.
			$wpdb->update( $wpdb->posts, array( 'post_date' => $post_date ), array( 'ID' => $post_ID ) );
			clean_post_cache( $post_ID );
		}

		/**
		 * Returns a list of custom status objects used by the calendar.
		 *
		 * @return array An array of StdClass objects representing statuses.
		 */
		public function get_calendar_post_stati() {
			$post_stati            = get_post_stati( array(), 'object' );
			$custom_status_slugs   = wp_list_pluck( $this->get_post_statuses(), 'slug' );
			$custom_status_slugs[] = 'future';
			$custom_status_slugs[] = 'publish';

			$custom_status_slug_keys = array_flip( $custom_status_slugs );

			$final_statuses = [];

			foreach ( $post_stati as $status ) {
				if ( ! empty( $custom_status_slug_keys[ $status->name ] ) ) {
					$final_statuses[] = $status;
				}
			}

			return apply_filters( 'ef_calendar_post_stati', $final_statuses );
		}

		/**
		 * Get users for the calendar dropdown filter.
		 *
		 * @return array Array of WP_User objects.
		 */
		public function get_calendar_users() {
			$users_args = array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'blog_id' => get_current_blog_id(),
			);

			$users_args = apply_filters( 'ef_calendar_dropdown_users_args', $users_args );

			return get_users( $users_args );
		}

		/**
		 * Get categories for the calendar dropdown filter.
		 *
		 * @return array Array of term objects.
		 */
		public function get_calendar_categories() {
			$categories_args = array(
				'orderby'      => 'id',
				'order'        => 'ASC',
				'hide_empty'   => 0,
				'hierarchical' => 0,
				'taxonomy'     => 'category',
			);

			return get_terms( $categories_args );
		}

		/**
		 * Get the frontend configuration for the calendar React component.
		 *
		 * @return array Configuration array for the frontend.
		 */
		public function get_calendar_frontend_config() {
			global $wp_version;

			$all_post_types = get_post_types( null, 'objects' );

			$config = array(
				'POST_STATI'        => $this->get_calendar_post_stati(),
				'USERS'             => array_map(
					function ( $item ) {
						return array(
							'id'           => $item->ID,
							'display_name' => $item->display_name,
						);
					},
					$this->get_calendar_users()
				),
				'CATEGORIES'        => $this->get_calendar_categories(),
				'POST_TYPES'        => array_map( function ( $item ) use ( $all_post_types ) {
					return $all_post_types[ $item ];
				}, $this->get_post_types_for_module( $this->module ) ),
				'NUM_WEEKS'         => array(
					'MAX'     => $this->max_weeks,
					'DEFAULT' => $this->total_weeks,
				),
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Used for date calculation in calendar context.
				'BEGINNING_OF_WEEK' => $this->get_beginning_of_week( date( 'Y-m-d', current_time( 'timestamp' ) ) ),
				'FILTERS'           => $this->get_filters(),
				'PAGE_URL'          => menu_page_url( $this->module->slug, false ),
				'WP_VERSION'        => $wp_version,
			);

			return apply_filters( 'ef_calendar_frontend_config', $config );
		}
	} // EF_Calendar

} // End class_exists check for EF_Calendar.
