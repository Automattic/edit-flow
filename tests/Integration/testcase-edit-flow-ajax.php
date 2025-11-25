<?php
/**
 * Edit Flow Ajax test cases
 */

abstract class WP_Edit_Flow_Ajax_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Last AJAX response.  This is set via echo -or- wp_die.
	 */
	protected $_last_response = '';

	/**
	 * Taken from testcase-ajax.php setUpBeforeClass function
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		add_action( 'wp_ajax_heartbeat', 'wp_ajax_heartbeat', 1 );
		add_action( 'wp_ajax_heartbeat', 'wp_ajax_nopriv_heartbeat', 1 );

		parent::setUpBeforeClass();
	}

	/**
	 * Taken from testcase-ajax.php setUp function
	 */
	protected function setUp(): void {
		parent::setUp();

		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );

		set_current_screen( 'ajax' );

		// Clear logout cookies
		add_action( 'clear_auth_cookie', array( $this, 'logout' ) );

		// Suppress warnings from "Cannot modify header information - headers already sent by"
		$this->_error_level = error_reporting();
		error_reporting( $this->_error_level & ~E_WARNING );
	}

	/**
	 * Taken from testcase-ajax.php tearDown function
	 */
	protected function tearDown(): void {
		parent::tearDown();
		$_POST = array();
		$_GET = array();
		unset( $GLOBALS['post'] );
		unset( $GLOBALS['comment'] );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
		remove_action( 'clear_auth_cookie', array( $this, 'logout' ) );
		error_reporting( $this->_error_level );
		set_current_screen( 'front' );
	}

	/**
	 * Taken from testcase-ajax.php _handleAjax function
	 * Mimic the ajax handling of admin-ajax.php
	 * Capture the output via output buffering, and if there is any, store
	 * it in $this->_last_response.
	 * @param string $action
	 */
	protected function _handleAjax( $action ) {
		// Start output buffering
		ini_set( 'implicit_flush', false );
		ob_start();

		// Build the request
		$_POST['action'] = $action;
		$_GET['action']  = $action;
		$_REQUEST        = array_merge( $_POST, $_GET );
		// Call the hooks
		do_action( 'admin_init' );
		do_action( 'wp_ajax_' . $_REQUEST['action'], null );

		// Save the output
		$buffer = ob_get_clean();
		if ( !empty( $buffer ) )
			$this->_last_response = $buffer;
	}

	/**
	 * Return our callback handler
	 * @return callback
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Handler for wp_die()
	 * Save the output for analysis, stop execution by throwing an exception.
	 * Error conditions (no output, just die) will throw <code>WPAjaxDieStopException( $message )</code>
	 * You can test for this with:
	 * <code>
	 * $this->setExpectedException( 'WPAjaxDieStopException', 'something contained in $message' );
	 * </code>
	 * Normal program termination (wp_die called at then end of output) will throw <code>WPAjaxDieContinueException( $message )</code>
	 * You can test for this with:
	 * <code>
	 * $this->setExpectedException( 'WPAjaxDieContinueException', 'something contained in $message' );
	 * </code>
	 * @param string $message
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				throw new WPAjaxDieStopException( (string) $message );
			} else {
				throw new WPAjaxDieStopException( '0' );
			}
		} else {
			throw new WPAjaxDieContinueException( $message );
		}
	}
}
