<?php
/**
 * Tests for the handle_add_custom_status admin form handler.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test that handle_add_custom_status checks both nonce and capability.
 */
class CustomStatusAddHandlerTest extends TestCase {

	protected static $admin_user_id;

	/**
	 * Custom status module instance.
	 *
	 * @var \EF_Custom_Status
	 */
	protected $custom_status;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	protected function setUp(): void {
		parent::setUp();

		global $edit_flow;
		$this->custom_status = $edit_flow->custom_status;
	}

	protected function tearDown(): void {
		unset( $_POST['submit'], $_POST['action'], $_POST['status_name'], $_POST['_wpnonce'] );
		unset( $_GET['page'] );

		parent::tearDown();
	}

	/**
	 * A user without manage_options must not be able to add a custom status,
	 * even with a valid nonce.
	 */
	public function test_handle_add_custom_status_requires_capability() {
		$subscriber_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['submit']      = 'Add New Status';
		$_POST['action']      = 'add-new';
		$_GET['page']         = $this->custom_status->module->settings_slug;
		$_POST['status_name'] = 'Unauthorised Status';
		$_POST['_wpnonce']    = wp_create_nonce( 'custom-status-add-nonce' );

		$this->expectException( \WPDieException::class );
		$this->custom_status->handle_add_custom_status();
	}

	/**
	 * An administrator must be able to add a custom status end-to-end.
	 */
	public function test_handle_add_custom_status_succeeds_for_admin() {
		wp_set_current_user( self::$admin_user_id );

		$status_name = 'Security Test Status ' . wp_generate_password( 6, false );

		$_POST['submit']      = 'Add New Status';
		$_POST['action']      = 'add-new';
		$_GET['page']         = $this->custom_status->module->settings_slug;
		$_POST['status_name'] = $status_name;
		$_POST['_wpnonce']    = wp_create_nonce( 'custom-status-add-nonce' );

		try {
			$this->custom_status->handle_add_custom_status();
			// Successful paths end with wp_redirect(); exit. The test framework
			// may or may not throw depending on configuration, so both outcomes
			// are acceptable.
		} catch ( \WPDieException $e ) {
			$this->fail( 'Admin should not hit wp_die on a valid add request: ' . $e->getMessage() );
		}

		$status = $this->custom_status->get_custom_status_by( 'slug', sanitize_title( $status_name ) );
		$this->assertNotFalse( $status, 'Custom status should have been created.' );
	}
}
