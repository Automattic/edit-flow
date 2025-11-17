<?php
/**
 * class EF_Story_Budget
 * This class displays a budgeting system for an editorial desk's publishing workflow.
 *
 * @author sbressler
 */
class EF_Story_Budget extends EF_Module {

	public $taxonomy_used = 'category';

	public $module;

	public $num_columns = 0;

	public $max_num_columns;

	public $no_matching_posts = true;

	public $terms = array();

	public $user_filters;

	// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
	const screen_id = 'dashboard_page_story-budget';

	// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
	const usermeta_key_prefix = 'ef_story_budget_';

	// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
	const default_num_columns = 1;

	private $term_columns;

	/**
	 * Register the module with Edit Flow but don't do anything else
	 */
	public function __construct() {

		$this->module_url = $this->get_module_url( __FILE__ );
		// Register the module with Edit Flow
		$args = array(
			'title' => __( 'Story Budget', 'edit-flow' ),
			// translators: %s is a link to the story budget page
			'short_description' => sprintf( __( 'View the status of all your content <a href="%s">at a glance</a>.', 'edit-flow' ), admin_url( 'index.php?page=story-budget' ) ),
			'extended_description' => __( 'Use the story budget to see how content on your site is progressing. Filter by specific categories or date ranges to see details about each post in progress.', 'edit-flow' ),
			'module_url' => $this->module_url,
			'img_url' => $this->module_url . 'lib/story_budget_s128.png',
			'slug' => 'story-budget',
			'default_options' => array(
				'enabled' => 'on',
			),
			'configure_page_cb' => false,
			'autoload' => false,
		);
		$this->module = EditFlow()->register_module( 'story_budget', $args );
	}

	/**
	 * Initialize the rest of the stuff in the class if the module is active
	 */
	public function init() {

		$view_story_budget_cap = apply_filters( 'ef_view_story_budget_cap', 'ef_view_story_budget' );
		if ( ! current_user_can( $view_story_budget_cap ) ) {
			return;
		}

		$this->num_columns = $this->get_num_columns();
		$this->max_num_columns = apply_filters( 'ef_story_budget_max_num_columns', 3 );

		// Filter to allow users to pick a taxonomy other than 'category' for sorting their posts
		$this->taxonomy_used = apply_filters( 'ef_story_budget_taxonomy_used', $this->taxonomy_used );

		add_action( 'admin_init', array( $this, 'handle_form_date_range_change' ) );
		add_action( 'admin_init', array( $this, 'add_screen_options_panel' ) );
		// Register the columns of data appearing on every term. This is hooked into admin_init
		// so other Edit Flow modules can register their filters if needed
		add_action( 'admin_init', array( $this, 'register_term_columns' ) );

		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		// Load necessary scripts and stylesheets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_enqueue_admin_styles' ) );
	}

	/**
	 * Give users the appropriate permissions to view the story budget the first time the module is loaded
	 *
	 * @since 0.7
	 */
	public function install() {

		$story_budget_roles = array(
			'administrator' => array( 'ef_view_story_budget' ),
			'editor' => array( 'ef_view_story_budget' ),
			'author' => array( 'ef_view_story_budget' ),
			'contributor' => array( 'ef_view_story_budget' ),
		);
		foreach ( $story_budget_roles as $role => $caps ) {
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
			// Migrate whether the story budget was enabled or not and clean up old option
			if ( $enabled = get_option( 'edit_flow_story_budget_enabled' ) ) {
				$enabled = 'on';
			} else {
				$enabled = 'off';
			}
			$edit_flow->update_module_option( $this->module->name, 'enabled', $enabled );
			delete_option( 'edit_flow_story_budget_enabled' );

			// Technically we've run this code before so we don't want to auto-install new data
			$edit_flow->update_module_option( $this->module->name, 'loaded_once', true );
		}
	}

	/**
	 * Include the story budget link in the admin menu.
	 *
	 * @uses add_submenu_page()
	 */
	public function action_admin_menu() {
		add_submenu_page( 'index.php', __( 'Story Budget', 'edit-flow' ), __( 'Story Budget', 'edit-flow' ), apply_filters( 'ef_view_story_budget_cap', 'ef_view_story_budget' ), $this->module->slug, array( $this, 'story_budget' ) );
	}

	/**
	 * Enqueue necessary admin scripts only on the story budget page.
	 *
	 * @uses enqueue_admin_script()
	 */
	public function enqueue_admin_scripts() {
		global $current_screen;

		if ( self::screen_id != $current_screen->id ) {
			return;
		}

		$num_columns = $this->get_num_columns();
		echo '<script type="text/javascript"> var ef_story_budget_number_of_columns="' . esc_js( $this->num_columns ) . '";</script>';

		$this->enqueue_datepicker_resources();
		wp_enqueue_script( 'edit_flow-story_budget', $this->module_url . 'lib/story-budget.js', array( 'edit_flow-date_picker' ), EDIT_FLOW_VERSION, true );
	}

	/**
	 * Enqueue a screen and print stylesheet for the story budget.
	 */
	public function action_enqueue_admin_styles() {
		global $current_screen;

		if ( self::screen_id != $current_screen->id ) {
			return;
		}

		wp_enqueue_style( 'edit_flow-story_budget-styles', $this->module_url . 'lib/story-budget.css', false, EDIT_FLOW_VERSION, 'screen' );
		wp_enqueue_style( 'edit_flow-story_budget-print-styles', $this->module_url . 'lib/story-budget-print.css', false, EDIT_FLOW_VERSION, 'print' );
	}

	/**
	 * Register the columns of information that appear for each term module.
	 * Modeled after how WP_List_Table works, but focused on hooks instead of OOP extending
	 *
	 * @since 0.7
	 */
	public function register_term_columns() {

		$term_columns = array(
			'title' => __( 'Title', 'edit-flow' ),
			'status' => __( 'Status', 'edit-flow' ),
			'author' => __( 'Author', 'edit-flow' ),
			'post_date' => __( 'Post Date', 'edit-flow' ),
			'post_modified' => __( 'Last Modified', 'edit-flow' ),
		);

		$term_columns = apply_filters( 'ef_story_budget_term_columns', $term_columns );
		$this->term_columns = $term_columns;
	}

	/**
	 * Handle a form submission to change the user's date range on the budget
	 *
	 * @since 0.7
	 */
	public function handle_form_date_range_change() {

		if ( ! isset( $_POST['ef-story-budget-range-submit'], $_POST['ef-story-budget-number-days'], $_POST['ef-story-budget-start-date_hidden'] ) ) {
			return;
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'change-date' ) ) {
			wp_die( esc_html( $this->module->messages['nonce-failed'] ) );
		}

		$current_user = wp_get_current_user();
		$new_filters = array(
			'start_date' => $_POST['ef-story-budget-start-date_hidden'],
			'number_days' => (int) $_POST['ef-story-budget-number-days'],
		);
		$user_filters = $this->update_user_filters_from_form_date_range_change( $current_user, $new_filters );

		$this->update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $user_filters );
		wp_redirect( menu_page_url( $this->module->slug, false ) );
		exit;
	}

	/**
	 * Handles updating the users
	 */
	public function update_user_filters_from_form_date_range_change( $current_user, $new_filters ) {
		$existing_filters = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );

		// Default start date value
		if ( isset( $new_filters['start_date'] ) ) {
			// Validate that it's a legitimate date
			$valid_date = DateTime::createFromFormat( 'Y-m-d', $new_filters['start_date'] );

			if ( false === $valid_date ) {
				$start_date = date_i18n( 'Y-m-d' );
			} else {
				$start_date = $valid_date->format( 'Y-m-d' );
			}

			// Set the start_date filter (to new value or default)
			$existing_filters['start_date'] = $start_date;
		}

		if ( isset( $new_filters['number_days'] ) ) {
			$new_filters['number_days'] = intval( $new_filters['number_days'] );
			if ( $new_filters['number_days'] <= 1 ) {
				$existing_filters['number_days'] = 1;
			} else {
				$existing_filters['number_days'] = $new_filters['number_days'];
			}
		}

		return $existing_filters;
	}

	/**
	 * Get the number of columns to show on the story budget
	 */
	public function get_num_columns() {

		if ( empty( $this->num_columns ) ) {
			$current_user = wp_get_current_user();
			$this->num_columns = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'screen_columns', true );
			// If usermeta didn't have a value already, use a default value and insert into DB
			if ( empty( $this->num_columns ) ) {
				$this->num_columns = self::default_num_columns;
				$this->save_column_prefs( array( self::usermeta_key_prefix . 'screen_columns' => $this->num_columns ) );
			}
		}
		return $this->num_columns;
	}

	/**
	 * Add module options to the screen panel
	 *
	 * @since 0.8.3
	 */
	public function add_screen_options_panel() {
		require_once EDIT_FLOW_ROOT . '/common/php/screen-options.php';
		add_screen_options_panel( self::usermeta_key_prefix . 'screen_columns', __( 'Screen Layout', 'edit-flow' ), array( $this, 'print_column_prefs' ), self::screen_id, array( $this, 'save_column_prefs' ), true );
	}

	/**
	 * Print column number preferences for screen options
	 */
	public function print_column_prefs() {
		$return_val = __( 'Number of Columns: ', 'edit-flow' );
		for ( $i = 1; $i <= $this->max_num_columns; ++$i ) {
			$return_val .= "<label><input type='radio' name='" . esc_attr( self::usermeta_key_prefix ) . "screen_columns' value='" . esc_attr( $i ) . "' " . checked( $this->get_num_columns(), $i, false ) . ' />&nbsp;' . esc_attr( $i ) . "</label>\n";
		}
		return $return_val;
	}

	/**
	 * Save the current user's preference for number of columns.
	 */
	public function save_column_prefs( $posted_fields ) {

		$key = self::usermeta_key_prefix . 'screen_columns';
		$this->num_columns = (int) $posted_fields[ $key ];

		$current_user = wp_get_current_user();
		$this->update_user_meta( $current_user->ID, $key, $this->num_columns );
	}

	/**
	 * Create the story budget view. This calls lots of other methods to do its work. This will
	 * ouput any messages, create the table navigation, then print the columns based on
	 * get_num_columns(), which will in turn print the stories themselves.
	 */
	public function story_budget() {

		// Update the current user's filters with the variables set in $_GET
		$this->user_filters = $this->update_user_filters();

		if ( ! empty( $this->user_filters[ $this->taxonomy_used ] ) ) {
			$terms = array();
			$terms[] = get_term( $this->user_filters[ $this->taxonomy_used ], $this->taxonomy_used );
		} else {
			// Get all of the terms from the taxonomy, regardless whether there are published posts
			$terms = get_terms( array(
				'taxonomy'   => $this->taxonomy_used,
				'orderby' => 'name',
				'order' => 'asc',
				'hide_empty' => 0,
				'parent' => 0,
			));
		}
		$this->terms = apply_filters( 'ef_story_budget_filter_terms', $terms ); // allow for reordering or any other filtering of terms

		?>
		<div class="wrap" id="ef-story-budget-wrap">
			<div id="ef-story-budget-title">
				<?php echo '<img src="' . esc_url( $this->module->img_url ) . '" class="module-icon icon32" />'; ?>
				<h2><?php _e( 'Story Budget', 'edit-flow' ); ?>&nbsp;<span class="time-range"><?php $this->story_budget_time_range(); ?></span></h2>
			</div><!-- /Story Budget Title -->
			<?php $this->print_messages(); ?>
			<?php $this->table_navigation(); ?>
			<div class="metabox-holder">
				<?php
					echo '<div class="postbox-container columns-number-' . absint( $this->num_columns ) . '">';
				foreach ( (array) $this->terms as $term ) {
					$this->print_term( $term );
				}

					echo '</div>';
				?>
				<style>
					<?php
					for ( $i = 1; $i <= $this->max_num_columns; ++$i ) {
						?>
					.columns-number-<?php echo (int) $i; ?> .postbox {
						flex-basis: <?php echo floatval( 99 / $i ); ?>%;
					}
						<?php
					}
					?>
				</style>
			</div>
		</div>
		<?php
	}

	/**
	 * Allow the user to define the date range in a new and exciting way
	 *
	 * @since 0.7
	 */
	public function story_budget_time_range() {
		?>
		<form method="POST" action="<?php echo esc_url( menu_page_url( $this->module->slug, false ) ); ?>">
			<?php _e( 'starting', 'edit-flow' ); ?>
			<span class="form-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $this->user_filters['start_date'] ) ) ); ?></span>
			<input type="text" id="ef-story-budget-start-date" name="ef-story-budget-start-date" size="20" autocomplete="off" class="date-pick" value="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $this->user_filters['start_date'] ) ) ); ?>" />
			<input type="hidden" id="ef-story-budget-start-date_hidden" name="ef-story-budget-start-date_hidden" />
			<?php _e( 'showing', 'edit-flow' ); ?>
			<input type="text" id="ef-story-budget-number-days" name="ef-story-budget-number-days" size="3" maxlength="3" value="<?php echo esc_attr( $this->user_filters['number_days'] ); ?>" />
			<span class="form-value"><?php echo esc_html( $this->user_filters['number_days'] ); ?></span>
			<?php echo esc_html( _n( 'day', 'days', $this->user_filters['number_days'], 'edit-flow' ) ); ?>
			<span class="change-date-buttons">
				<input id="ef-story-budget-range-submit" name="ef-story-budget-range-submit" type="submit" class="button-primary" value="<?php echo esc_attr( __( 'Change', 'edit-flow' ) ); ?>" />
				<a class="change-date-cancel hidden" href="#"><?php echo esc_html( __( 'Cancel', 'edit-flow' ) ); ?></a>
				<a class="change-date" href="#"><?php echo esc_html( __( 'Change', 'edit-flow' ) ); ?></a>
			</span>
			<?php wp_nonce_field( 'change-date', 'nonce', 'change-date-nonce', true ); ?>
		</form>
		<?php
	}

	/**
	 * Get all of the posts for a given term based on filters
	 *
	 * @param object $term The term we're getting posts for
	 * @return array $term_posts An array of post objects for the term
	 */
	public function get_posts_for_term( $term, $args = null ) {

		$defaults = array(
			'post_status' => null,
			'author'      => null,
			'posts_per_page' => apply_filters( 'ef_story_budget_max_query', 200 ),
		);
		$args = array_merge( $defaults, $args );

		// Filter to the term and any children if it's hierarchical
		$arg_terms = array(
			$term->term_id,
		);
		$arg_terms = array_merge( $arg_terms, get_term_children( $term->term_id, $this->taxonomy_used ) );
		$args['tax_query'] = array(
			array(
				'taxonomy' => $this->taxonomy_used,
				'field' => 'id',
				'terms' => $arg_terms,
				'operator' => 'IN',
			),
		);

		// Unpublished as a status is just an array of everything but 'publish'.
		if ( 'unpublish' == $args['post_status'] ) {
			$args['post_status'] = '';
			$post_stati = wp_filter_object_list( $this->get_budget_post_stati(), array( 'name' => 'publish' ), 'not' );

			if ( ! apply_filters( 'ef_show_scheduled_as_unpublished', false ) ) {
				$post_stati = wp_filter_object_list( $post_stati, array( 'name' => 'future' ), 'not' );
			}

			$args['post_status'] .= implode( ',', wp_list_pluck( $post_stati, 'name' ) );
		}

		// Filter by post_author if it's set
		if ( '0' === $args['author'] ) {
			unset( $args['author'] );
		}

		$beginning_date = strtotime( $this->user_filters['start_date'] );
		$days_to_show = $this->user_filters['number_days'];
		$ending_date = $beginning_date + ( $days_to_show * DAY_IN_SECONDS );

		$args['date_query'] = array(
			'after'     => date( 'Y-m-d', $beginning_date ),
			'before'    => date( 'Y-m-d', $ending_date ),
			'inclusive' => true,
		);

		// Filter for an end user to implement any of their own query args
		$args = apply_filters( 'ef_story_budget_posts_query_args', $args );

		$term_posts_query_results = new WP_Query( $args );

		$term_posts = array();
		while ( $term_posts_query_results->have_posts() ) {
			$term_posts_query_results->the_post();
			global $post;
			$term_posts[] = $post;
		}

		return $term_posts;
	}


	/**
	 * Prints the stories in a single term in the story budget.
	 *
	 * @param object $term The term to print.
	 */
	public function print_term( $term ) {
		global $wpdb;
		$posts = $this->get_posts_for_term( $term, $this->user_filters );
		if ( ! empty( $posts ) ) {
			// Don't display the message for $no_matching_posts
			$this->no_matching_posts = false;
		}

		?>
	<div class="postbox
		<?php
		if ( ! empty( $posts ) ) {
			echo ' postbox-has-posts';}
		?>
	">
		<div class="handlediv" title="<?php _e( 'Click to toggle', 'edit-flow' ); ?>"><br /></div>
		<h3 class='hndle'><span><?php echo esc_html( $term->name ); ?></span></h3>
		<div class="inside">
			<?php if ( ! empty( $posts ) ) : ?>
			<table class="widefat post fixed story-budget" cellspacing="0">
				<thead>
					<tr>
						<?php foreach ( (array) $this->term_columns as $key => $name ) : ?>
						<th scope="col" id="<?php echo esc_attr( sanitize_key( $key ) ); ?>" class="manage-column column-<?php echo esc_attr( sanitize_key( $key ) ); ?>" ><?php echo esc_html( $name ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tfoot></tfoot>
				<tbody>
				<?php
				foreach ( $posts as $post ) {
					$this->print_post( $post, $term );
				}
				?>
				</tbody>
			</table>
			<?php else : ?>
			<div class="message info"><p><?php _e( 'There are no posts for this term in the range or filter specified.', 'edit-flow' ); ?></p></div>
			<?php endif; ?>
		</div>
	</div>
		<?php
	}

	/**
	 * Prints a single post within a term in the story budget.
	 *
	 * @param object $post The post to print.
	 * @param object $parent_term The top-level term to which this post belongs.
	 */
	public function print_post( $post, $parent_term ) {
		?>
		<tr id='post-<?php echo esc_attr( $post->ID ); ?>' class='alternate' valign="top">
			<?php
			foreach ( (array) $this->term_columns as $key => $name ) {
				echo '<td>';
				if ( method_exists( $this, 'term_column_' . $key ) ) {
					$method = 'term_column_' . $key;
					echo wp_kses_post( $this->$method( $post, $parent_term ) );
				} else {
					echo wp_kses_post( $this->term_column_default( $post, $key, $parent_term ) );
				}
				echo '</td>';
			}
			?>
		</tr>
		<?php
	}

	/**
	 * Default callback for producing the HTML for a term column's single post value
	 * Includes a filter other modules can hook into
	 *
	 * @since 0.7
	 *
	 * @param object $post The post we're displaying
	 * @param string $column_name Name of the column, as registered with register_term_columns
	 * @param object $parent_term The parent term for the term column
	 * @return string $output Output value for the term column
	 */
	public function term_column_default( $post, $column_name, $parent_term ) {

		// Hook for other modules to get data into columns
		$column_value = null;
		$column_value = apply_filters( 'ef_story_budget_term_column_value', $column_name, $post, $parent_term );
		if ( ! is_null( $column_value ) && $column_value != $column_name ) {
			return $column_value;
		}

		switch ( $column_name ) {
			case 'status':
				$status_name = get_post_status_object( $post->post_status );
				return $status_name->label;
				break;
			case 'author':
				$post_author = get_userdata( $post->post_author );
				return $post_author->display_name;
				break;
			case 'post_date':
				$output = get_the_time( get_option( 'date_format' ), $post->ID ) . '<br />';
				$output .= get_the_time( get_option( 'time_format' ), $post->ID );
				return $output;
				break;
			case 'post_modified':
				// translators: %s is a human-readable time difference
				return sprintf( esc_html__( '%s ago', 'edit-flow' ), human_time_diff( get_the_time( 'U', $post->ID ), current_time( 'timestamp' ) ) );
				break;
			default:
				break;
		}
	}

	/**
	 * Prepare the data for the title term column
	 *
	 * @since 0.7
	 */
	public function term_column_title( $post, $parent_term ) {
		$post_title = _draft_or_post_title( $post->ID );

		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post = current_user_can( $post_type_object->cap->edit_post, $post->ID );
		if ( $can_edit_post ) {
			$output = '<strong><a href="' . get_edit_post_link( $post->ID ) . '">' . esc_html( $post_title ) . '</a></strong>';
		} else {
			$output = '<strong>' . esc_html( $post_title ) . '</strong>';
		}

		// Edit or Trash or View
		$output .= '<div class="row-actions">';
		$item_actions = array();
		if ( $can_edit_post ) {
			$item_actions['edit'] = '<a title="' . __( 'Edit this post', 'edit-flow' ) . '" href="' . get_edit_post_link( $post->ID ) . '">' . __( 'Edit', 'edit-flow' ) . '</a>';
		}
		if ( EMPTY_TRASH_DAYS > 0 && current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {
			$item_actions['trash'] = '<a class="submitdelete" title="' . __( 'Move this item to the Trash', 'edit-flow' ) . '" href="' . get_delete_post_link( $post->ID ) . '">' . __( 'Trash', 'edit-flow' ) . '</a>';
		}

		// Display a View or a Preview link depending on whether the post has been published or not
		if ( in_array( $post->post_status, array( 'publish' ) ) ) {
			// translators: %s is the post title
			$item_actions['view'] = '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'edit-flow' ), $post_title ) ) . '" rel="permalink">' . __( 'View', 'edit-flow' ) . '</a>';
		} else if ( $can_edit_post ) {
			// translators: %s is the post title
			$item_actions['previewpost'] = '<a href="' . esc_url( apply_filters( 'preview_post_link', add_query_arg( 'preview', 'true', get_permalink( $post->ID ) ), $post ) ) . '" title="' . esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;', 'edit-flow' ), $post_title ) ) . '" rel="permalink">' . __( 'Preview', 'edit-flow' ) . '</a>';
		}

		$item_actions = apply_filters( 'ef_story_budget_item_actions', $item_actions, $post->ID );
		if ( count( $item_actions ) ) {
			$output .= '<div class="row-actions">';
			$html = '';
			foreach ( $item_actions as $class => $item_action ) {
				$html .= '<span class="' . esc_attr( $class ) . '">' . $item_action . '</span> | ';
			}
			$output .= rtrim( $html, '| ' );
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Print any messages that should appear based on the action performed
	 */
	public function print_messages() {
		?>

		<?php
		if ( isset( $_GET['trashed'] ) || isset( $_GET['untrashed'] ) ) {

			echo '<div id="trashed-message" class="updated"><p>';

			// Following mostly stolen from edit.php

			if ( isset( $_GET['trashed'] ) && (int) $_GET['trashed'] ) {
				// translators: %d is the number of posts trashed
				printf( esc_html( _n( 'Item moved to the trash.', '%d items moved to the trash.', $_GET['trashed'] ), number_format_i18n( $_GET['trashed'] ) ) );
				$ids = isset( $_GET['ids'] ) ? $_GET['ids'] : 0;
				echo ' <a href="' . esc_url( wp_nonce_url( "edit.php?post_type=post&doaction=undo&action=untrash&ids=$ids", 'bulk-posts' ) ) . '">' . esc_html__( 'Undo', 'edit-flow' ) . '</a><br />';
				unset( $_GET['trashed'] );
			}

			if ( isset( $_GET['untrashed'] ) && (int) $_GET['untrashed'] ) {
				// translators: %d is the number of posts restored from the trash
				printf( esc_html( _n( 'Item restored from the Trash.', '%d items restored from the Trash.', $_GET['untrashed'] ), number_format_i18n( $_GET['untrashed'] ) ) );
				unset( $_GET['undeleted'] );
			}

			echo '</p></div>';
		}
	}

	/**
	 * Print the table navigation and filter controls, using the current user's filters if any are set.
	 */
	public function table_navigation() {
		?>
	<div class="tablenav" id="ef-story-budget-tablenav">
		<div class="alignleft actions">
			<form method="GET" style="float: left;">
				<input type="hidden" name="page" value="story-budget"/>
				<?php
				foreach ( $this->story_budget_filters() as $select_id => $select_name ) {
					echo wp_kses_post( $this->story_budget_filter_options( $select_id, $select_name, $this->user_filters ) );
				}
				?>
				<input type="submit" id="post-query-submit" value="<?php _e( 'Filter', 'edit-flow' ); ?>" class="button-primary button" />
			</form>
			<form method="GET" style="float: left;">
				<input type="hidden" name="page" value="story-budget"/>
				<input type="hidden" name="post_status" value=""/>
				<input type="hidden" name="cat" value=""/>
				<input type="hidden" name="author" value=""/>
				<?php
				foreach ( $this->story_budget_filters() as $select_id => $select_name ) {
					echo '<input type="hidden" name="' . esc_attr( $select_name ) . '" value="" />';
				}
				?>
				<input type="submit" id="post-query-clear" value="<?php _e( 'Reset', 'edit-flow' ); ?>" class="button-secondary button" />
			</form>
		</div><!-- /alignleft actions -->

		<div class="print-box" style="float:right; margin-right: 30px;"><!-- Print link -->
			<a href="#" id="print_link"><?php _e( 'Print', 'edit-flow' ); ?></a>
		</div>
		<div class="clear"></div>

	</div><!-- /tablenav -->
		<?php
	}

	/**
	 * Update the current user's filters for story budget display with the filters in $_GET. The filters
	 * in $_GET take precedence over the current users filters if they exist.
	 */
	public function update_user_filters() {

		$current_user = wp_get_current_user();

		$user_filters = array(
			'post_status'   => $this->filter_get_param( 'post_status' ),
			'cat'           => $this->filter_get_param( 'cat' ),
			'author'        => $this->filter_get_param( 'author' ),
			'start_date'    => $this->filter_get_param( 'start_date' ),
			'number_days'   => $this->filter_get_param( 'number_days' ),
		);

		$current_user_filters = array();
		$current_user_filters = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );

		// If any of the $_GET vars are missing, then use the current user filter
		foreach ( $user_filters as $key => $value ) {
			if ( is_null( $value ) && ! empty( $current_user_filters[ $key ] ) ) {
				$user_filters[ $key ] = $current_user_filters[ $key ];
			}
		}

		if ( ! $user_filters['start_date'] ) {
			$user_filters['start_date'] = date( 'Y-m-d' );
		}

		if ( ! $user_filters['number_days'] ) {
			$user_filters['number_days'] = 10;
		}

		$user_filters = apply_filters( 'ef_story_budget_filter_values', $user_filters, $current_user_filters );

		$this->update_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', $user_filters );
		return $user_filters;
	}

	/**
	 * Get the filters for the current user for the story budget display, or insert the default
	 * filters if not already set.
	 *
	 * @return array The filters for the current user, or the default filters if the current user has none.
	 */
	public function get_user_filters() {

		$current_user = wp_get_current_user();
		$user_filters = array();
		$user_filters = $this->get_user_meta( $current_user->ID, self::usermeta_key_prefix . 'filters', true );

		// If usermeta didn't have filters already, insert defaults into DB
		if ( empty( $user_filters ) ) {
			$user_filters = $this->update_user_filters();
		}
		return $user_filters;
	}

	/**
	 *
	 * @param string $param The parameter to look for in $_GET
	 * @return null if the parameter is not set in $_GET, empty string if the parameter is empty in $_GET,
	 *         or a sanitized version of the parameter from $_GET if set and not empty
	 */
	public function filter_get_param( $param ) {
		// Sure, this could be done in one line. But we're cooler than that: let's make it more readable!
		if ( ! isset( $_GET[ $param ] ) ) {
			return null;
		} else if ( empty( $_GET[ $param ] ) ) {
			return '';
		}

		return sanitize_key( $_GET[ $param ] );
	}

	public function story_budget_filters() {
		$select_filter_names = array();

		$select_filter_names['post_status'] = 'post_status';
		$select_filter_names['cat'] = 'cat';
		$select_filter_names['author'] = 'author';

		return apply_filters( 'ef_story_budget_filter_names', $select_filter_names );
	}

	public function story_budget_filter_options( $select_id, $select_name, $filters ) {
		switch ( $select_id ) {
			case 'post_status':
				$post_stati = $this->get_budget_post_stati();
				?>
				<select id="post_status" name="post_status"><!-- Status selectors -->
						<option value=""><?php _e( 'View all statuses', 'edit-flow' ); ?></option>
						<?php
						foreach ( $post_stati as $status ) {
							echo '<option value="' . esc_attr( $status->name ) . '" ' . selected( $status->name, $filters['post_status'] ) . '>' . esc_html( $status->label ) . '</option>';
						}
							echo '<option value="unpublish"' . selected( 'unpublish', $filters['post_status'] ) . '>' . esc_html__( 'Unpublished', 'edit-flow' ) . '</option>';
						?>
					</select>
				<?php
				break;
			case 'cat':
				// Borrowed from wp-admin/edit.php
				if ( taxonomy_exists( 'category' ) ) {
					$category_dropdown_args = array(
						'show_option_all' => __( 'View all categories', 'edit-flow' ),
						'hide_empty' => 0,
						'hierarchical' => 1,
						'show_count' => 0,
						'orderby' => 'name',
						'selected' => $this->user_filters['cat'],
					);
					wp_dropdown_categories( $category_dropdown_args );
				}
				break;
			case 'author':
				$users_dropdown_args = array(
					'show_option_all' => __( 'View all users', 'edit-flow' ),
					'name'     => 'author',
					'selected' => $this->user_filters['author'],
					'who' => 'authors',
				);
				$users_dropdown_args = apply_filters( 'ef_story_budget_users_dropdown_args', $users_dropdown_args );
				wp_dropdown_users( $users_dropdown_args );
				break;
			default:
				do_action( 'ef_story_budget_filter_display', $select_id, $select_name, $filters );
				break;
		}
	}

	/**
	 * Returns a list of custom status objects used by the story budget
	 *
	 * @return array An array of StdClass objects representing statuses
	 */
	public function get_budget_post_stati() {
		$post_stati = get_post_stati( array(), 'object' );
		$custom_status_slugs = wp_list_pluck( $this->get_post_statuses(), 'slug' );
		$custom_status_slugs[] = 'future';
		$custom_status_slugs[] = 'publish';

		$custom_status_slug_keys = array_flip( $custom_status_slugs );

		$final_statuses = [];

		foreach ( $post_stati as $status ) {
			if ( ! empty( $custom_status_slug_keys[ $status->name ] ) ) {
				$final_statuses[] = $status;
			}
		}

		return apply_filters( 'ef_budget_post_stati', $final_statuses );
	}
}
