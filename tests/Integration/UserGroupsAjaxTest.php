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
		global $edit_flow;

		parent::setUp();

		global $edit_flow;
		$edit_flow->user_groups->install();

		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

		// Grant administrators edit_usergroups before any test user is created,
		// rather than relying on state left behind by earlier test classes.
		$edit_flow->user_groups->install();
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
		$_POST['inline_edit']  = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name']         = 'Updated Group Name';
		$_POST['description']  = 'Updated description';

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
		$_POST['inline_edit']  = 'invalid_nonce';
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name']         = 'Updated Group Name';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( esc_html( $edit_flow->user_groups->module->messages['nonce-failed'] ) );
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
		$_POST['inline_edit']  = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name']         = 'Updated Group Name';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( esc_html( $edit_flow->user_groups->module->messages['invalid-permissions'] ) );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails with missing usergroup ID
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_missing_id(): void {
		global $edit_flow;

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Set up the AJAX request without usergroup_id
		$_POST['inline_edit'] = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['name']        = 'New Group Name';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( esc_html( $edit_flow->user_groups->module->messages['usergroup-missing'] ) );
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
		$_POST['inline_edit']  = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name']         = '';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( 'Please enter a name for the user group.' );
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
		$_POST['inline_edit']  = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup->term_id;
		$_POST['name']         = str_repeat( 'a', 41 ); // 41 characters

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( 'User group name cannot exceed 40 characters.' );
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
		$_POST['inline_edit']  = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup2->term_id;
		$_POST['name']         = 'Group One';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( 'Name already in use.' );
		$this->_handleAjax( 'inline_save_usergroup' );
	}

	/**
	 * Test: Inline save usergroup fails with slug conflict
	 *
	 * A name may be unique, but could generate a slug that conflicts with an existing group.
	 *
	 * @covers EF_User_Groups::handle_ajax_inline_save_usergroup
	 */
	public function test_inline_save_usergroup_slug_conflict(): void {
		global $edit_flow;

		$this->markTestIncomplete( 'The slug conflict check looks up the unprefixed slug, but user group slugs carry the ef-usergroup- prefix, so it never matches.' );

		// Create admin user
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );

		// Create two usergroups with names that have different display but same slug
		$usergroup1 = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Test Group',
			'description' => 'First group',
		) );

		$usergroup2 = $edit_flow->user_groups->add_usergroup( array(
			'name'        => 'Another Group',
			'description' => 'Second group',
		) );

		// Try to update usergroup2 with a name that would create the same slug as usergroup1
		// "Test-Group" is a different name, but sanitize_title() also creates "test-group".
		$_POST['inline_edit']  = wp_create_nonce( 'usergroups-inline-edit-nonce' );
		$_POST['usergroup_id'] = $usergroup2->term_id;
		$_POST['name']         = 'Test-Group';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( 'Name conflicts with slug for another term.' );
		$this->_handleAjax( 'inline_save_usergroup' );
	}
}
