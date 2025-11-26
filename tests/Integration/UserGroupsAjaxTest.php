<?php
/**
 * User Groups AJAX integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class UserGroupsAjaxTest extends AjaxTestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
	}

	/**
	 * Test: Successfully inline saving a usergroup with valid data
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_success(): void {
		global $edit_flow;

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Create a usergroup first
		$usergroup = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Original Group',
			'description' => 'Original description',
		) );

		$this->assertFalse( is_wp_error( $usergroup ) );

		// Set up the AJAX request
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name'] = 'Updated Group Name';
		$_POST['description'] = 'Updated description';

		try {
			$this->_handleAjax( 'inline_save_usergroup' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify the response
		$this->assertNotEmpty( $this->_last_response );

		// Verify the usergroup was updated
		$updated_usergroup = $edit_flow->user_groups->get_usergroup_by( 'id', $usergroup->term_id );
		$this->assertNotNull( $updated_usergroup );
		$this->assertEquals( 'Updated Group Name', $updated_usergroup->name );
		$this->assertEquals( 'Updated description', $updated_usergroup->description );
	}

	/**
	 * Test: Inline save usergroup fails with invalid nonce
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_invalid_nonce(): void {
		global $edit_flow;

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Create a usergroup first
		$usergroup = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Test Group',
			'description' => 'Test description',
		) );

		// Set up the AJAX request with invalid nonce
		$_POST['inline_edit'] = 'invalid_nonce';
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name'] = 'Updated Group Name';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails without proper permissions
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_no_permissions(): void {
		global $edit_flow;

		// Create a subscriber user (no edit_usergroups cap)
		$subscriber_user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_user_id );

		// Create a usergroup as admin first
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$usergroup = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Test Group',
			'description' => 'Test description',
		) );

		// Switch back to subscriber
		wp_set_current_user( $subscriber_user_id );

		// Set up the AJAX request
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name'] = 'Updated Group Name';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails with missing usergroup ID
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_missing_id(): void {
		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Set up the AJAX request without usergroup_id
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['name'] = 'New Group Name';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails with empty name
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_empty_name(): void {
		global $edit_flow;

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Create a usergroup first
		$usergroup = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Test Group',
			'description' => 'Test description',
		) );

		// Set up the AJAX request with empty name
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name'] = '';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails with name too long
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_name_too_long(): void {
		global $edit_flow;

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Create a usergroup first
		$usergroup = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Test Group',
			'description' => 'Test description',
		) );

		// Set up the AJAX request with name exceeding 40 characters
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name'] = str_repeat( 'a', 41 ); // 41 characters

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails with duplicate name
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_duplicate_name(): void {
		global $edit_flow;

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Create two usergroups
		$usergroup1 = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Group One',
			'description' => 'First group',
		) );

		$usergroup2 = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Group Two',
			'description' => 'Second group',
		) );

		// Try to update usergroup2 with usergroup1's name
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup2->term_id;
		$_POST['name'] = 'Group One';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'inline_save_usergroup' );
	}
}
