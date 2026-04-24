<?php
/**
 * Notifications AJAX integration tests.
 *
 * Tests for the AJAX save functionality in the notifications module.
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
class NotificationsAjaxTest extends AjaxTestCase {

	protected static $admin_user_id;
	protected static $editor_user_id;
	protected static $author_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_user_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_user_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::delete_user( self::$editor_user_id );
		self::delete_user( self::$author_user_id );
	}

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

		// Ensure notifications module is initialized with capabilities.
		global $edit_flow;
		$edit_flow->notifications->install();
	}

	/**
	 * Test: AJAX save notifications for users returns success JSON.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_users_returns_json_success(): void {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		// Create a post.
		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		// Set up the AJAX request for saving users.
		$_POST['_nonce']                = wp_create_nonce( 'save_user_usergroups' );
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'ef-selected-users[]';
		$_POST['user_group_ids']        = array( self::$admin_user_id, self::$editor_user_id );

		try {
			$this->_handleAjax( 'save_notifications' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify we got a response.
		$this->assertNotEmpty( $this->_last_response, 'AJAX should return a response for user subscriptions' );

		// Verify it's valid JSON.
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be valid JSON' );
		$this->assertTrue( $response['success'], 'Response should indicate success' );
		$this->assertArrayHasKey( 'subscribers_with_no_access', $response['data'] );
		$this->assertArrayHasKey( 'subscribers_with_no_email', $response['data'] );
	}

	/**
	 * Test: AJAX save notifications for user groups returns success JSON.
	 *
	 * This was a bug where user groups AJAX save would return an empty response,
	 * causing the UI to not update properly.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_usergroups_returns_json_success(): void {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		// Create a post.
		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		// Create a user group.
		$usergroup = $edit_flow->user_groups->add_usergroup(
			array(
				'name'        => 'Test Notification Group',
				'description' => 'A group for testing notifications',
			)
		);

		$this->assertNotInstanceOf( 'WP_Error', $usergroup, 'User group should be created successfully' );

		// Set up the AJAX request for saving user groups.
		$_POST['_nonce']                = wp_create_nonce( 'save_user_usergroups' );
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'following_usergroups[]';
		$_POST['user_group_ids']        = array( $usergroup->term_id );

		try {
			$this->_handleAjax( 'save_notifications' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify we got a response (this was the bug - user groups returned empty).
		$this->assertNotEmpty( $this->_last_response, 'AJAX should return a response for user group subscriptions' );

		// Verify it's valid JSON.
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be valid JSON' );
		$this->assertTrue( $response['success'], 'Response should indicate success' );
		$this->assertArrayHasKey( 'subscribers_with_no_access', $response['data'] );
		$this->assertArrayHasKey( 'subscribers_with_no_email', $response['data'] );

		// Verify the user group was saved.
		$following_groups = $edit_flow->notifications->get_following_usergroups( $post_id, 'ids' );
		$this->assertContains( $usergroup->term_id, $following_groups, 'User group should be saved as following' );

		// Clean up.
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test: AJAX save notifications fails with invalid nonce.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_fails_with_invalid_nonce(): void {
		wp_set_current_user( self::$admin_user_id );

		$post_id = $this->factory->post->create();

		$_POST['_nonce']                = 'invalid_nonce';
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'ef-selected-users[]';
		$_POST['user_group_ids']        = array( self::$admin_user_id );

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'save_notifications' );
	}

	/**
	 * Test: AJAX save notifications fails without edit_post_subscriptions capability.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_fails_without_capability(): void {
		// Create a subscriber user (no edit_post_subscriptions cap).
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$post_id = $this->factory->post->create();

		$_POST['_nonce']                = wp_create_nonce( 'save_user_usergroups' );
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'ef-selected-users[]';
		$_POST['user_group_ids']        = array( self::$admin_user_id );

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'save_notifications' );
	}

	/**
	 * Test: AJAX save correctly persists user subscriptions.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_persists_user_subscriptions(): void {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		// Initially, no followers.
		$initial_followers = $edit_flow->notifications->get_following_users( $post_id, 'id' );
		$this->assertEmpty( $initial_followers );

		// Save users via AJAX.
		$_POST['_nonce']                = wp_create_nonce( 'save_user_usergroups' );
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'ef-selected-users[]';
		$_POST['user_group_ids']        = array( self::$admin_user_id, self::$editor_user_id );

		try {
			$this->_handleAjax( 'save_notifications' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify the subscriptions were saved.
		$followers = $edit_flow->notifications->get_following_users( $post_id, 'id' );
		$this->assertContains( self::$admin_user_id, $followers );
		$this->assertContains( self::$editor_user_id, $followers );
	}

	/**
	 * Test: AJAX save correctly persists user group subscriptions.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_persists_usergroup_subscriptions(): void {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		// Create user groups.
		$group1 = $edit_flow->user_groups->add_usergroup( array( 'name' => 'Test Group 1' ) );
		$group2 = $edit_flow->user_groups->add_usergroup( array( 'name' => 'Test Group 2' ) );

		// Initially, no following groups.
		$initial_groups = $edit_flow->notifications->get_following_usergroups( $post_id, 'ids' );
		$this->assertEmpty( $initial_groups );

		// Save user groups via AJAX.
		$_POST['_nonce']                = wp_create_nonce( 'save_user_usergroups' );
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'following_usergroups[]';
		$_POST['user_group_ids']        = array( $group1->term_id, $group2->term_id );

		try {
			$this->_handleAjax( 'save_notifications' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify the subscriptions were saved.
		$following_groups = $edit_flow->notifications->get_following_usergroups( $post_id, 'ids' );
		$this->assertContains( $group1->term_id, $following_groups );
		$this->assertContains( $group2->term_id, $following_groups );

		// Clean up.
		$edit_flow->user_groups->delete_usergroup( $group1->term_id );
		$edit_flow->user_groups->delete_usergroup( $group2->term_id );
	}

	/**
	 * Test: handle_user_post_subscription requires a nonce.
	 *
	 * This tests the fix for the nonce check logic. Previously the check was:
	 * `if ( ! empty( $_GET['_wpnonce'] ) && ! wp_verify_nonce(...) )`
	 * which allowed requests without any nonce to pass through.
	 *
	 * The fix changes it to:
	 * `if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce(...) )`
	 * which requires a valid nonce.
	 *
	 * @ticket https://github.com/Automattic/edit-flow/issues/882
	 * @covers EF_Notifications::handle_user_post_subscription
	 */
	public function test_handle_user_post_subscription_requires_nonce(): void {
		wp_set_current_user( self::$admin_user_id );

		$post_id = $this->factory->post->create();

		// Make request WITHOUT a nonce - this should fail now.
		$_GET['post_id'] = $post_id;
		$_GET['method']  = 'follow';
		// Intentionally NOT setting $_GET['_wpnonce']

		// print_ajax_response outputs JSON before dying, so we get WPAjaxDieContinueException.
		try {
			$this->_handleAjax( 'ef_notifications_user_post_subscription' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify error response.
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be valid JSON' );
		$this->assertEquals( 'error', $response['status'], 'Response should indicate error when nonce is missing' );
	}

	/**
	 * Test: handle_user_post_subscription fails with invalid nonce.
	 *
	 * @ticket https://github.com/Automattic/edit-flow/issues/882
	 * @covers EF_Notifications::handle_user_post_subscription
	 */
	public function test_handle_user_post_subscription_fails_with_invalid_nonce(): void {
		wp_set_current_user( self::$admin_user_id );

		$post_id = $this->factory->post->create();

		$_GET['_wpnonce'] = 'invalid_nonce';
		$_GET['post_id']  = $post_id;
		$_GET['method']   = 'follow';

		// print_ajax_response outputs JSON before dying, so we get WPAjaxDieContinueException.
		try {
			$this->_handleAjax( 'ef_notifications_user_post_subscription' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify error response.
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be valid JSON' );
		$this->assertEquals( 'error', $response['status'], 'Response should indicate error when nonce is invalid' );
	}

	/**
	 * Test: ajax_save_post_subscriptions fails when the user cannot edit the post.
	 *
	 * An author has the edit_post_subscriptions capability but can only edit their own
	 * posts. Attempting to save subscriptions for another user's post must be rejected,
	 * even when the nonce and edit_post_subscriptions capability checks pass.
	 *
	 * @covers EF_Notifications::ajax_save_post_subscriptions
	 */
	public function test_ajax_save_fails_when_user_cannot_edit_post(): void {
		// Authors have edit_post_subscriptions (granted by install()) but can only
		// edit their own posts. Log in as the author and target the admin's post.
		wp_set_current_user( self::$author_user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		$_POST['_nonce']                = wp_create_nonce( 'save_user_usergroups' );
		$_POST['post_id']               = $post_id;
		$_POST['ef_notifications_name'] = 'ef-selected-users[]';
		$_POST['user_group_ids']        = array( self::$author_user_id );

		// wp_die() with no output → WPAjaxDieStopException.
		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'save_notifications' );
	}

	/**
	 * Test: handle_user_post_subscription fails when the user cannot edit the post.
	 *
	 * A user may have edit_post_subscriptions but lack edit access for a specific post.
	 * The handler must reject follow/unfollow requests for posts the user cannot edit,
	 * mirroring the gate applied in filter_post_row_actions().
	 *
	 * @covers EF_Notifications::handle_user_post_subscription
	 */
	public function test_handle_user_post_subscription_fails_when_user_cannot_edit_post(): void {
		// Authors have edit_post_subscriptions but cannot edit another user's post.
		wp_set_current_user( self::$author_user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		$_GET['_wpnonce'] = wp_create_nonce( 'ef_notifications_user_post_subscription' );
		$_GET['post_id']  = $post_id;
		$_GET['method']   = 'follow';

		// print_ajax_response outputs JSON before dying → WPAjaxDieContinueException.
		try {
			$this->_handleAjax( 'ef_notifications_user_post_subscription' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be valid JSON' );
		$this->assertEquals( 'error', $response['status'], 'Response should indicate error when user cannot edit the post' );
	}

	/**
	 * Test: handle_user_post_subscription succeeds with valid nonce.
	 *
	 * @ticket https://github.com/Automattic/edit-flow/issues/882
	 * @covers EF_Notifications::handle_user_post_subscription
	 */
	public function test_handle_user_post_subscription_succeeds_with_valid_nonce(): void {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		$_GET['_wpnonce'] = wp_create_nonce( 'ef_notifications_user_post_subscription' );
		$_GET['post_id']  = $post_id;
		$_GET['method']   = 'follow';

		try {
			$this->_handleAjax( 'ef_notifications_user_post_subscription' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Verify we got a success response.
		$this->assertNotEmpty( $this->_last_response, 'AJAX should return a response' );

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, 'Response should be valid JSON' );
		$this->assertEquals( 'success', $response['status'], 'Response should indicate success' );

		// Verify the user is now following the post.
		$following_users = $edit_flow->notifications->get_following_users( $post_id, 'id' );
		$this->assertContains( self::$admin_user_id, $following_users, 'User should be following the post' );
	}
}
