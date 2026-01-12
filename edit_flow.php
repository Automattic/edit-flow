<?php
/**
 * Plugin Name: Edit Flow
 * Plugin URI: http://editflow.org/
 * Description: Remixing the WordPress admin for better editorial workflow options.
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and others
 * Version: 0.10.3
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright 2009-2019 Mohammad Jangda, Daniel Bachhuber, Automattic, et al.
 *
 * @package EditFlow
 */

/**
 * Print admin notice regarding having an old version of PHP.
 *
 * @since 0.9
 */
function _ef_print_php_version_admin_notice() {
	?>
	<div class="notice notice-error">
			<p><?php esc_html_e( 'Edit Flow requires PHP 7.4+. Please contact your host to update your PHP version.', 'edit-flow' ); ?></p>
		</div>
	<?php
}

if ( version_compare( phpversion(), '7.4', '<' ) ) {
	add_action( 'admin_notices', '_ef_print_php_version_admin_notice' );
	return;
}

// Define constants.
define( 'EDIT_FLOW_VERSION', '0.10.3' );
define( 'EDIT_FLOW_ROOT', __DIR__ );
define( 'EDIT_FLOW_FILE_PATH', EDIT_FLOW_ROOT . '/' . basename( __FILE__ ) );
define( 'EDIT_FLOW_URL', plugins_url( '/', __FILE__ ) );
define( 'EDIT_FLOW_SETTINGS_PAGE', add_query_arg( 'page', 'ef-settings', get_admin_url( null, 'admin.php' ) ) );

/**
 * Core Edit Flow class.
 */
#[\AllowDynamicProperties]
class edit_flow {

	/**
	 * Options group prefix.
	 *
	 * @var string
	 */
	public $options_group = 'edit_flow_';

	/**
	 * Options group name.
	 *
	 * @var string
	 */
	public $options_group_name = 'edit_flow_options';

	/**
	 * The one true EditFlow instance.
	 *
	 * @var edit_flow
	 */
	private static $instance;

	/**
	 * Active modules.
	 *
	 * @var \stdClass
	 */
	public $modules;

	/**
	 * Number of active modules.
	 *
	 * @var int
	 */
	public $modules_count;

	/**
	 * Helper modules.
	 *
	 * @var EF_Module
	 */
	public $helpers;

	/**
	 * Main EditFlow Instance.
	 *
	 * Insures that only one instance of EditFlow exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since EditFlow 0.7.4
	 * @staticvar array $instance
	 * @uses EditFlow::setup_globals() Setup the globals needed.
	 * @uses EditFlow::includes() Include the required files.
	 * @uses EditFlow::setup_actions() Setup the hooks and actions.
	 * @see EditFlow()
	 * @return edit_flow The one true EditFlow.
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new edit_flow();
			self::$instance->setup_globals();
			self::$instance->setup_actions();
			// Backwards compat for when we promoted use of the $edit_flow global.
			global $edit_flow;
			$edit_flow = self::$instance;
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Do nothing.
	}

	/**
	 * Set up global variables.
	 */
	private function setup_globals() {
		$this->modules       = new stdClass();
		$this->modules_count = 0;
	}

	/**
	 * Include the common resources to Edit Flow and dynamically load the modules.
	 */
	private function load_modules() {

		// We use the WP_List_Table API for some of the table gen.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		// Edit Flow base module.
		require_once EDIT_FLOW_ROOT . '/common/php/class-module.php';

		// Scan the modules directory and include any modules that exist there.
		$module_dirs = scandir( EDIT_FLOW_ROOT . '/modules/' );
		$class_names = array();
		foreach ( $module_dirs as $module_dir ) {
			if ( file_exists( EDIT_FLOW_ROOT . "/modules/{$module_dir}/$module_dir.php" ) ) {
				include_once EDIT_FLOW_ROOT . "/modules/{$module_dir}/$module_dir.php";

				// Prepare the class name because it should be standardized.
				$tmp        = explode( '-', $module_dir );
				$class_name = '';
				$slug_name  = '';
				foreach ( $tmp as $word ) {
					$class_name .= ucfirst( $word ) . '_';
					$slug_name  .= $word . '_';
				}
				$slug_name                 = rtrim( $slug_name, '_' );
				$class_names[ $slug_name ] = 'EF_' . rtrim( $class_name, '_' );
			}
		}

		// Instantiate EF_Module as $helpers for back compat and so we can use it in this class.
		$this->helpers = new EF_Module();

		// Other utils.
		require_once EDIT_FLOW_ROOT . '/common/php/util.php';

		// Instantiate all of our classes onto the Edit Flow object but make sure they exist too.
		foreach ( $class_names as $slug => $class_name ) {
			if ( class_exists( $class_name ) ) {
				$this->$slug = new $class_name();
			}
		}

		/**
		 * Fires after edit_flow has loaded all Edit Flow internal modules.
		 *
		 * Plugin authors can hook into this action, include their own modules add them to the $edit_flow object
		 */
		do_action( 'ef_modules_loaded' );
	}

	/**
	 * Setup the default hooks and actions.
	 *
	 * @since EditFlow 0.7.4
	 * @access private
	 * @uses add_action() To add various actions.
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'init', array( $this, 'action_init_after' ), 1000 );

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

		/**
		 * Fires after setup of all edit_flow actions.
		 *
		 * Plugin authors can hook into this action to manipulate the edit_flow class after initial actions have been registered.
		 *
		 * @param edit_flow $this The core edit flow class
		 */
		do_action_ref_array( 'editflow_after_setup_actions', array( &$this ) );
	}

	/**
	 * Inititalizes the Edit Flows!
	 *
	 * Loads options for each registered module and then initializes it if it's active.
	 */
	public function action_init() {

		load_plugin_textdomain( 'edit-flow', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		$this->load_modules();

		// Load all of the module options.
		$this->load_module_options();

		// Load all of the modules that are enabled. Modules won't have an options value if they aren't enabled.
		foreach ( $this->modules as $mod_name => $mod_data ) {
			if ( isset( $mod_data->options->enabled ) && 'on' == $mod_data->options->enabled ) {
				$this->$mod_name->init();
			}
		}

		/**
		 * Fires after edit_flow has loaded all modules and module options.
		 *
		 * Plugin authors can hook into this action to trigger functionaltiy after all Edit Flow module's have been loaded.
		 */
		do_action( 'ef_init' );
	}

	/**
	 * Initialize the plugin for the admin.
	 */
	public function action_admin_init() {

		// Upgrade if need be but don't run the upgrade if the plugin has never been used.
		$previous_version = get_option( $this->options_group . 'version' );
		if ( $previous_version && version_compare( $previous_version, EDIT_FLOW_VERSION, '<' ) ) {
			foreach ( $this->modules as $mod_name => $mod_data ) {
				if ( method_exists( $this->$mod_name, 'upgrade' ) ) {
						$this->$mod_name->upgrade( $previous_version );
				}
			}
			update_option( $this->options_group . 'version', EDIT_FLOW_VERSION );
		} elseif ( ! $previous_version ) {
			update_option( $this->options_group . 'version', EDIT_FLOW_VERSION );
		}

		// For each module that's been loaded, auto-load data if it's never been run before.
		foreach ( $this->modules as $mod_name => $mod_data ) {
			// If the module has never been loaded before, run the install method if there is one.
			if ( ! isset( $mod_data->options->loaded_once ) || ! $mod_data->options->loaded_once ) {
				if ( method_exists( $this->$mod_name, 'install' ) ) {
					$this->$mod_name->install();
				}
				$this->update_module_option( $mod_name, 'loaded_once', true );
			}
		}

		$this->register_scripts_and_styles();
	}

	/**
	 * Register a new module with Edit Flow.
	 *
	 * @param string $name Module name.
	 * @param array  $args Module arguments.
	 * @return object|false Module object on success, false on failure.
	 */
	public function register_module( $name, $args = array() ) {

		// A title and name is required for every module.
		if ( ! isset( $args['title'], $name ) ) {
			return false;
		}

		$defaults = array(
			'title'                => '',
			'short_description'    => '',
			'extended_description' => '',
			'img_url'              => false,
			'slug'                 => '',
			'post_type_support'    => '',
			'default_options'      => array(),
			'options'              => false,
			'configure_page_cb'    => false,
			'configure_link_text'  => __( 'Configure', 'edit-flow' ),
			// These messages are applied to modules and can be overridden if custom messages are needed.
			'messages'             => array(
				'settings-updated'    => __( 'Settings updated.', 'edit-flow' ),
				'form-error'          => __( 'Please correct your form errors below and try again.', 'edit-flow' ),
				'nonce-failed'        => __( 'Cheatin&#8217; uh?', 'edit-flow' ),
				'invalid-permissions' => __( 'You do not have necessary permissions to complete this action.', 'edit-flow' ),
				'missing-post'        => __( 'Post does not exist', 'edit-flow' ),
			),
			'autoload'             => false, // Autoloading a module will remove the ability to enable or disable it.
		);
		if ( isset( $args['messages'] ) ) {
			$args['messages'] = array_merge( (array) $args['messages'], $defaults['messages'] );
		}
		$args                       = array_merge( $defaults, $args );
		$args['name']               = $name;
		$args['options_group_name'] = $this->options_group . $name . '_options';
		if ( ! isset( $args['settings_slug'] ) ) {
			$args['settings_slug'] = 'ef-' . $args['slug'] . '-settings';
		}
		if ( empty( $args['post_type_support'] ) ) {
			$args['post_type_support'] = 'ef_' . $name;
		}
		// If there's a Help Screen registered for the module, make sure we auto-load it.
		if ( ! empty( $args['settings_help_tab'] ) ) {
			add_action( 'load-edit-flow_page_' . $args['settings_slug'], array( &$this->$name, 'action_settings_help_menu' ) );
		}

		$this->modules->$name = (object) $args;

		++$this->modules_count;

		/**
		 * Fires after edit_flow has registered a module.
		 *
		 * Plugin authors can hook into this action to trigger functionaltiy after a module has been loaded.
		 *
		 * @param string $name The name of the registered module
		 */
		do_action( 'ef_module_registered', $name );
		return $this->modules->$name;
	}

	/**
	 * Load all of the module options from the database.
	 *
	 * If a given option isn't yet set, then set it to the module's default (upgrades, etc.).
	 */
	public function load_module_options() {

		foreach ( $this->modules as $mod_name => $mod_data ) {
			$this->modules->$mod_name->options = get_option( $this->options_group . $mod_name . '_options', new stdClass() );
			foreach ( $mod_data->default_options as $default_key => $default_value ) {
				if ( ! isset( $this->modules->$mod_name->options->$default_key ) ) {
					$this->modules->$mod_name->options->$default_key = $default_value;
				}
			}

			$this->$mod_name->module = $this->modules->$mod_name;
		}

		/**
		 * Fires after edit_flow has loaded all of the module options from the database.
		 *
		 * Plugin authors can hook into this action to read and manipulate module settings.
		 */
		do_action( 'ef_module_options_loaded' );
	}

	/**
	 * Load the post type options again so we give add_post_type_support() a chance to work.
	 *
	 * @see http://dev.editflow.org/2011/11/17/edit-flow-v0-7-alpha2-notes/#comment-232
	 */
	public function action_init_after() {
		foreach ( $this->modules as $mod_name => $mod_data ) {
			if ( isset( $this->modules->$mod_name->options->post_types ) ) {
				$this->modules->$mod_name->options->post_types = $this->helpers->clean_post_type_options( $this->modules->$mod_name->options->post_types, $mod_data->post_type_support );
			}

			$this->$mod_name->module = $this->modules->$mod_name;
		}
	}

	/**
	 * Get a module by one of its descriptive values.
	 *
	 * @param string           $key   The property to use for searching a module (ex: 'name').
	 * @param string|int|array $value The value to compare (using ==).
	 * @return object|false Module object on success, false on failure.
	 */
	public function get_module_by( $key, $value ) {
		$module = false;
		foreach ( $this->modules as $mod_name => $mod_data ) {
			if ( 'name' === $key && $value === $mod_name ) {
				$module = $this->modules->$mod_name;
			} else {
				foreach ( $mod_data as $mod_data_key => $mod_data_value ) {
					if ( $mod_data_key === $key && $mod_data_value === $value ) {
						$module = $this->modules->$mod_name;
					}
				}
			}
		}
		return $module;
	}

	/**
	 * Update the $edit_flow object with new value and save to the database.
	 *
	 * @param string $mod_name Module name.
	 * @param string $key      Option key.
	 * @param mixed  $value    Option value.
	 * @return bool True on success, false on failure.
	 */
	public function update_module_option( $mod_name, $key, $value ) {
		$this->modules->$mod_name->options->$key = $value;
		$this->$mod_name->module                 = $this->modules->$mod_name;
		return update_option( $this->options_group . $mod_name . '_options', $this->modules->$mod_name->options );
	}

	/**
	 * Update all module options at once.
	 *
	 * @param string       $mod_name    Module name.
	 * @param array|object $new_options New options.
	 * @return bool True on success, false on failure.
	 */
	public function update_all_module_options( $mod_name, $new_options ) {
		if ( is_array( $new_options ) ) {
			$new_options = (object) $new_options;
		}
		$this->modules->$mod_name->options = $new_options;
		$this->$mod_name->module           = $this->modules->$mod_name;
		return update_option( $this->options_group . $mod_name . '_options', $this->modules->$mod_name->options );
	}

	/**
	 * Registers commonly used scripts + styles for easy enqueueing.
	 */
	public function register_scripts_and_styles() {
		wp_enqueue_style( 'ef-admin-css', EDIT_FLOW_URL . 'common/css/edit-flow-admin.css', false, EDIT_FLOW_VERSION, 'all' );

		wp_register_script( 'jquery-listfilterizer', EDIT_FLOW_URL . 'common/js/jquery.listfilterizer.js', array( 'jquery' ), EDIT_FLOW_VERSION, true );
		wp_register_style( 'jquery-listfilterizer', EDIT_FLOW_URL . 'common/css/jquery.listfilterizer.css', false, EDIT_FLOW_VERSION, 'all' );


		wp_localize_script(
			'jquery-listfilterizer',
			'__i18n_jquery_filterizer',
			array(
				'all'      => esc_html__( 'All', 'edit-flow' ),
				'selected' => esc_html__( 'Selected', 'edit-flow' ),
			)
		);
	}
}

/**
 * Get the Edit Flow instance.
 *
 * @return edit_flow The Edit Flow instance.
 */
function EditFlow() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Legacy function name.
	return edit_flow::instance();
}
add_action( 'plugins_loaded', 'EditFlow' );
