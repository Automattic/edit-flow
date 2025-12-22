<?php
/**
 * Tests for scoped module asset loading.
 *
 * @package Automattic\EditFlow\Tests\Integration
 * @see https://github.com/Automattic/edit-flow/issues/351
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Module;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test that is_post_management_page() properly scopes asset loading.
 *
 * Module assets should only load on pages where the module functionality is needed,
 * not on every admin page.
 */
class ModuleAssetScopingTest extends TestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Get a module instance for testing.
	 *
	 * @return EF_Module
	 */
	protected function get_test_module() {
		global $edit_flow;
		// Use notifications module as test subject since it uses is_post_management_page().
		return $edit_flow->notifications;
	}

	/**
	 * Test that is_post_management_page returns false on dashboard.
	 */
	public function test_is_post_management_page_returns_false_on_dashboard() {
		global $pagenow;
		$pagenow = 'index.php';

		$module = $this->get_test_module();
		$this->assertFalse(
			$module->is_post_management_page(),
			'is_post_management_page() should return false on dashboard'
		);
	}

	/**
	 * Test that is_post_management_page returns false on options page.
	 */
	public function test_is_post_management_page_returns_false_on_options() {
		global $pagenow;
		$pagenow = 'options-general.php';

		$module = $this->get_test_module();
		$this->assertFalse(
			$module->is_post_management_page(),
			'is_post_management_page() should return false on options page'
		);
	}

	/**
	 * Test that is_post_management_page returns false on plugins page.
	 */
	public function test_is_post_management_page_returns_false_on_plugins() {
		global $pagenow;
		$pagenow = 'plugins.php';

		$module = $this->get_test_module();
		$this->assertFalse(
			$module->is_post_management_page(),
			'is_post_management_page() should return false on plugins page'
		);
	}

	/**
	 * Test that is_post_management_page returns false on users page.
	 */
	public function test_is_post_management_page_returns_false_on_users() {
		global $pagenow;
		$pagenow = 'users.php';

		$module = $this->get_test_module();
		$this->assertFalse(
			$module->is_post_management_page(),
			'is_post_management_page() should return false on users page'
		);
	}

	/**
	 * Test that is_post_management_page returns true on post edit page.
	 */
	public function test_is_post_management_page_returns_true_on_post_edit() {
		global $pagenow, $typenow;
		$pagenow = 'post.php';
		$typenow = 'post';

		$module = $this->get_test_module();
		$this->assertTrue(
			$module->is_post_management_page(),
			'is_post_management_page() should return true on post.php'
		);
	}

	/**
	 * Test that is_post_management_page returns true on new post page.
	 */
	public function test_is_post_management_page_returns_true_on_post_new() {
		global $pagenow, $typenow;
		$pagenow = 'post-new.php';
		$typenow = 'post';

		$module = $this->get_test_module();
		$this->assertTrue(
			$module->is_post_management_page(),
			'is_post_management_page() should return true on post-new.php'
		);
	}

	/**
	 * Test that is_post_management_page returns true on posts list page.
	 */
	public function test_is_post_management_page_returns_true_on_edit() {
		global $pagenow, $typenow;
		$pagenow = 'edit.php';
		$typenow = 'post';

		$module = $this->get_test_module();
		$this->assertTrue(
			$module->is_post_management_page(),
			'is_post_management_page() should return true on edit.php'
		);
	}

	/**
	 * Test that is_post_management_page returns true on page edit page.
	 */
	public function test_is_post_management_page_returns_true_on_page_edit() {
		global $pagenow, $typenow;
		$pagenow = 'post.php';
		$typenow = 'page';

		$module = $this->get_test_module();
		$this->assertTrue(
			$module->is_post_management_page(),
			'is_post_management_page() should return true on post.php for pages'
		);
	}

	/**
	 * Test that is_post_management_page respects module-specific post types.
	 */
	public function test_is_post_management_page_respects_module_post_types() {
		global $pagenow, $typenow, $edit_flow;

		// Register a custom post type that's NOT supported by the module.
		register_post_type( 'unsupported_cpt', array( 'public' => true ) );

		$pagenow = 'post.php';
		$typenow = 'unsupported_cpt';

		$module = $this->get_test_module();

		// When passing module name, it should check if post type is supported.
		$result = $module->is_post_management_page( $module->module->name );

		// Clean up.
		unregister_post_type( 'unsupported_cpt' );

		$this->assertFalse(
			$result,
			'is_post_management_page() should return false for unsupported post types when module name is passed'
		);
	}

	/**
	 * Test that is_post_management_page without module name still works on supported post types.
	 */
	public function test_is_post_management_page_without_module_name() {
		global $pagenow, $typenow;
		$pagenow = 'post.php';
		$typenow = 'post';

		$module = $this->get_test_module();

		// Without module name, it should just check the page type.
		$this->assertTrue(
			$module->is_post_management_page(),
			'is_post_management_page() without module name should return true on post edit pages'
		);
	}
}
