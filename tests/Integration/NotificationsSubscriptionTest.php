<?php
/**
 * Notifications subscription and permission integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Notifications;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class NotificationsSubscriptionTest extends TestCase {

	protected static $admin_user_id;
	protected static $editor_user_id;
	protected static $author_user_id;
	protected static $contributor_user_id;
	protected static $subscriber_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_user_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_user_id      = $factory->user->create( array( 'role' => 'author' ) );
		self::$contributor_user_id = $factory->user->create( array( 'role' => 'contributor' ) );
		self::$subscriber_user_id  = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::delete_user( self::$editor_user_id );
		self::delete_user( self::$author_user_id );
		self::delete_user( self::$contributor_user_id );
		self::delete_user( self::$subscriber_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Test that the notifications module exists and is accessible.
	 */
	public function test_notifications_module_exists() {
		global $edit_flow;

		$this->assertNotNull( $edit_flow->notifications );
		$this->assertInstanceOf( EF_Notifications::class, $edit_flow->notifications );
	}

	/**
	 * Test user_can_be_notified returns true for admin on any post.
	 */
	public function test_user_can_be_notified_admin() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		$result = $edit_flow->notifications->user_can_be_notified( $user, $post_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test user_can_be_notified returns true for editor on any post.
	 */
	public function test_user_can_be_notified_editor() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);
		$user = get_user_by( 'id', self::$editor_user_id );

		$result = $edit_flow->notifications->user_can_be_notified( $user, $post_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test user_can_be_notified returns true for author on their own post.
	 */
	public function test_user_can_be_notified_author_own_post() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);
		$user = get_user_by( 'id', self::$author_user_id );

		$result = $edit_flow->notifications->user_can_be_notified( $user, $post_id );

		$this->assertTrue( $result );
	}

	/**
	 * Test user_can_be_notified returns false for author on others' post.
	 */
	public function test_user_can_be_notified_author_others_post() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'publish',
			)
		);
		$user = get_user_by( 'id', self::$author_user_id );

		$result = $edit_flow->notifications->user_can_be_notified( $user, $post_id );

		$this->assertFalse( $result );
	}

	/**
	 * Test user_can_be_notified returns false for subscriber.
	 */
	public function test_user_can_be_notified_subscriber() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$subscriber_user_id );

		$result = $edit_flow->notifications->user_can_be_notified( $user, $post_id );

		$this->assertFalse( $result );
	}

	/**
	 * Test user_can_be_notified returns false for invalid user.
	 */
	public function test_user_can_be_notified_invalid_user() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		$result = $edit_flow->notifications->user_can_be_notified( false, $post_id );

		$this->assertFalse( $result );
	}

	/**
	 * Test following a post by user ID.
	 */
	public function test_follow_post_user_by_id() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		$result = $edit_flow->notifications->follow_post_user( $post_id, self::$admin_user_id );

		$this->assertTrue( $result );

		$followers = $edit_flow->notifications->get_following_users( $post_id );
		$admin     = get_user_by( 'id', self::$admin_user_id );

		$this->assertContains( $admin->user_login, $followers );
	}

	/**
	 * Test following a post by user login.
	 */
	public function test_follow_post_user_by_login() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$editor_user_id );

		$result = $edit_flow->notifications->follow_post_user( $post_id, $user->user_login );

		$this->assertTrue( $result );

		$followers = $edit_flow->notifications->get_following_users( $post_id );

		$this->assertContains( $user->user_login, $followers );
	}

	/**
	 * Test following a post with multiple users.
	 */
	public function test_follow_post_multiple_users() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$users   = array( self::$admin_user_id, self::$editor_user_id, self::$author_user_id );

		$result = $edit_flow->notifications->follow_post_user( $post_id, $users );

		$this->assertTrue( $result );

		$followers = $edit_flow->notifications->get_following_users( $post_id );

		$this->assertCount( 3, $followers );
	}

	/**
	 * Test appending followers to existing list.
	 */
	public function test_follow_post_user_append() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		// Add first user
		$edit_flow->notifications->follow_post_user( $post_id, self::$admin_user_id );

		// Append second user
		$edit_flow->notifications->follow_post_user( $post_id, self::$editor_user_id, true );

		$followers = $edit_flow->notifications->get_following_users( $post_id );

		$this->assertCount( 2, $followers );
	}

	/**
	 * Test replacing followers list (no append).
	 */
	public function test_follow_post_user_replace() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		// Add first user
		$edit_flow->notifications->follow_post_user( $post_id, self::$admin_user_id );

		// Replace with second user
		$edit_flow->notifications->follow_post_user( $post_id, self::$editor_user_id, false );

		$followers = $edit_flow->notifications->get_following_users( $post_id );

		$this->assertCount( 1, $followers );

		$editor = get_user_by( 'id', self::$editor_user_id );
		$this->assertContains( $editor->user_login, $followers );
	}

	/**
	 * Test unfollowing a post.
	 *
	 * Note: The unfollow_post_user method has a known issue where it compares
	 * user_login against term slugs. When user_login differs from the sanitized
	 * slug (e.g., uppercase letters), unfollow may not work correctly.
	 * This test verifies the method returns success and documents the behavior.
	 */
	public function test_unfollow_post_user() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		// Create a simple user with lowercase login to ensure slug matches
		$test_user_id = self::factory()->user->create(
			array(
				'user_login' => 'testunfollowuser',
				'role'       => 'editor',
			)
		);

		// Follow with test user
		$edit_flow->notifications->follow_post_user( $post_id, $test_user_id );

		// Verify following
		$followers = $edit_flow->notifications->get_following_users( $post_id );
		$this->assertContains( 'testunfollowuser', $followers );

		// Unfollow
		$result = $edit_flow->notifications->unfollow_post_user( $post_id, $test_user_id );

		$this->assertTrue( $result );

		// Verify unfollowed
		$followers_after = $edit_flow->notifications->get_following_users( $post_id );
		$this->assertNotContains( 'testunfollowuser', $followers_after );

		// Clean up
		wp_delete_user( $test_user_id );
	}

	/**
	 * Test follow_post_user with invalid post returns WP_Error.
	 */
	public function test_follow_post_user_invalid_post() {
		global $edit_flow;

		$result = $edit_flow->notifications->follow_post_user( 999999, self::$admin_user_id );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test getting following users returns empty array for post with no followers.
	 */
	public function test_get_following_users_empty() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		$followers = $edit_flow->notifications->get_following_users( $post_id );

		$this->assertIsArray( $followers );
		$this->assertEmpty( $followers );
	}

	/**
	 * Test getting following users by ID.
	 */
	public function test_get_following_users_by_id() {
		global $edit_flow;

		$post_id = self::factory()->post->create();

		$edit_flow->notifications->follow_post_user( $post_id, self::$admin_user_id );

		$followers = $edit_flow->notifications->get_following_users( $post_id, 'id' );

		$this->assertContains( self::$admin_user_id, $followers );
	}

	/**
	 * Test getting following users by email.
	 */
	public function test_get_following_users_by_email() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		$edit_flow->notifications->follow_post_user( $post_id, self::$admin_user_id );

		$followers = $edit_flow->notifications->get_following_users( $post_id, 'user_email' );

		$this->assertContains( $user->user_email, $followers );
	}

	/**
	 * Test following a post with usergroups.
	 */
	public function test_follow_post_usergroups() {
		global $edit_flow;

		// Skip if user_groups module is not enabled
		if ( ! $edit_flow->notifications->module_enabled( 'user_groups' ) ) {
			$this->markTestSkipped( 'User Groups module is not enabled.' );
		}

		$post_id = self::factory()->post->create();

		// Create a usergroup
		$usergroup = $edit_flow->user_groups->add_usergroup(
			array( 'name' => 'Notification Test Group' )
		);

		$edit_flow->notifications->follow_post_usergroups( $post_id, $usergroup->term_id );

		$following_groups = $edit_flow->notifications->get_following_usergroups( $post_id, 'ids' );

		$this->assertContains( $usergroup->term_id, $following_groups );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test getting posts that a user follows.
	 */
	public function test_get_user_following_posts() {
		global $edit_flow;

		// Create posts and have user follow them
		$post_ids = self::factory()->post->create_many( 3 );

		foreach ( $post_ids as $post_id ) {
			$edit_flow->notifications->follow_post_user( $post_id, self::$admin_user_id );
		}

		$following_posts = $edit_flow->notifications->get_user_following_posts( self::$admin_user_id );

		$this->assertCount( 3, $following_posts );
	}

	/**
	 * Test that deleting a user removes them from following taxonomy.
	 */
	public function test_delete_user_removes_from_following() {
		global $edit_flow;

		// Create a temporary user
		$temp_user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$temp_user    = get_user_by( 'id', $temp_user_id );

		$post_id = self::factory()->post->create();

		// Have user follow the post
		$edit_flow->notifications->follow_post_user( $post_id, $temp_user_id );

		// Verify they're following
		$followers = $edit_flow->notifications->get_following_users( $post_id );
		$this->assertContains( $temp_user->user_login, $followers );

		// Trigger the delete action
		$edit_flow->notifications->delete_user_action( $temp_user_id );

		// Verify the term is deleted
		$term = get_term_by( 'name', $temp_user->user_login, $edit_flow->notifications->following_users_taxonomy );
		$this->assertFalse( $term );

		// Clean up
		wp_delete_user( $temp_user_id );
	}

	/**
	 * Test add_term_if_not_exists creates term.
	 */
	public function test_add_term_if_not_exists() {
		global $edit_flow;

		$term_name = 'unique_test_term_' . time();
		$taxonomy  = $edit_flow->notifications->following_users_taxonomy;

		$result = $edit_flow->notifications->add_term_if_not_exists( $term_name, $taxonomy );

		$this->assertNotInstanceOf( 'WP_Error', $result );

		// Verify term exists
		$term = get_term_by( 'name', $term_name, $taxonomy );
		$this->assertNotFalse( $term );

		// Clean up
		wp_delete_term( $term->term_id, $taxonomy );
	}

	/**
	 * Test add_term_if_not_exists returns true for existing term.
	 */
	public function test_add_term_if_not_exists_already_exists() {
		global $edit_flow;

		$term_name = 'existing_test_term_' . time();
		$taxonomy  = $edit_flow->notifications->following_users_taxonomy;

		// Create term first
		wp_insert_term( $term_name, $taxonomy );

		// Try to add again
		$result = $edit_flow->notifications->add_term_if_not_exists( $term_name, $taxonomy );

		$this->assertTrue( $result );

		// Clean up
		$term = get_term_by( 'name', $term_name, $taxonomy );
		wp_delete_term( $term->term_id, $taxonomy );
	}

	/**
	 * Test that capability for editing subscriptions is defined.
	 */
	public function test_edit_post_subscriptions_cap() {
		global $edit_flow;

		// Verify the capability property is set correctly
		$this->assertEquals( 'edit_post_subscriptions', $edit_flow->notifications->edit_post_subscriptions_cap );

		// Run install to ensure capabilities are added to roles
		$edit_flow->notifications->install();

		// Create fresh users after install() so they pick up the new capabilities
		// (existing user objects have cached capabilities)
		$admin_id       = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor_id      = self::factory()->user->create( array( 'role' => 'editor' ) );
		$author_id      = self::factory()->user->create( array( 'role' => 'author' ) );
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );

		// Admin should have this cap
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'edit_post_subscriptions' ), 'Administrator should have edit_post_subscriptions cap' );

		// Editor should have this cap
		wp_set_current_user( $editor_id );
		$this->assertTrue( current_user_can( 'edit_post_subscriptions' ), 'Editor should have edit_post_subscriptions cap' );

		// Author should have this cap
		wp_set_current_user( $author_id );
		$this->assertTrue( current_user_can( 'edit_post_subscriptions' ), 'Author should have edit_post_subscriptions cap' );

		// Contributor should NOT have this cap by default
		wp_set_current_user( $contributor_id );
		$this->assertFalse( current_user_can( 'edit_post_subscriptions' ), 'Contributor should NOT have edit_post_subscriptions cap' );
	}

	/**
	 * Test user_can_be_notified filter.
	 */
	public function test_user_can_be_notified_filter() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$subscriber_user_id );

		// Subscriber normally can't be notified
		$this->assertFalse( $edit_flow->notifications->user_can_be_notified( $user, $post_id ) );

		// Add filter to allow subscriber
		add_filter(
			'ef_notification_user_can_be_notified',
			function ( $can_be_notified, $filter_user, $filter_post_id ) use ( $user, $post_id ) {
				if ( $filter_user->ID === $user->ID && $filter_post_id === $post_id ) {
					return true;
				}
				return $can_be_notified;
			},
			10,
			3
		);

		// Now subscriber can be notified
		$this->assertTrue( $edit_flow->notifications->user_can_be_notified( $user, $post_id ) );

		// Clean up filter
		remove_all_filters( 'ef_notification_user_can_be_notified' );
	}

	/**
	 * Test following with user object.
	 */
	public function test_follow_post_user_with_object() {
		global $edit_flow;

		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		$result = $edit_flow->notifications->follow_post_user( $post_id, $user );

		$this->assertTrue( $result );

		$followers = $edit_flow->notifications->get_following_users( $post_id );

		$this->assertContains( $user->user_login, $followers );
	}
}
