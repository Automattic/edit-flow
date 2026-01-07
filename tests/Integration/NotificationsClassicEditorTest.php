<?php
/**
 * Notifications Classic Editor integration tests.
 *
 * Tests for the save_post_subscriptions function when used with Classic Editor.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for notifications module with Classic Editor.
 *
 * These tests verify that the notifications module correctly handles
 * nonce verification when posts are saved via Classic Editor, which
 * uses a different nonce action than the Block Editor's REST API.
 */
class NotificationsClassicEditorTest extends TestCase {

	protected static $editor_user_id;

	/**
	 * Set up test fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$editor_user_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Clean up test fixtures.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$editor_user_id );
	}

	/**
	 * Set up each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$editor_user_id );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Test that save_post_subscriptions accepts valid Classic Editor nonce.
	 *
	 * Classic Editor submits posts with a nonce verified against 'update-post_{$post_id}'.
	 * The save_post_subscriptions function should accept this valid nonce.
	 *
	 * @ticket https://wordpress.org/support/topic/upgrading-to-0-10-0-breaks-funtionality-for-editor-role/
	 */
	public function test_save_post_subscriptions_accepts_valid_classic_editor_nonce() {
		global $edit_flow;

		// Create a post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Simulate Classic Editor POST request with a valid nonce.
		// Classic Editor uses 'update-post_{$post_id}' as the nonce action.
		$_POST['_wpnonce']          = wp_create_nonce( 'update-post_' . $post_id );
		$_POST['ef-save_followers'] = '1';
		$_POST['ef-selected-users'] = array( self::$editor_user_id );

		// Set up wp_die handler to catch any wp_die calls.
		$wp_die_called  = false;
		$wp_die_message = '';

		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called, &$wp_die_message ) {
				return function ( $message ) use ( &$wp_die_called, &$wp_die_message ) {
					$wp_die_called  = true;
					$wp_die_message = $message;
					// Don't actually die - throw exception to stop execution.
					throw new \Exception( 'wp_die called: ' . $message );
				};
			}
		);

		// Attempt to trigger save_post_subscriptions via status transition.
		$exception_thrown = false;
		try {
			// Directly call save_post_subscriptions to test the nonce check.
			$edit_flow->notifications->save_post_subscriptions( 'draft', 'draft', $post );
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		// The function should NOT call wp_die when given a valid Classic Editor nonce.
		// If this assertion fails, it means the nonce check is incorrectly rejecting
		// valid Classic Editor nonces (the bug reported in 0.10.0).
		$this->assertFalse(
			$wp_die_called,
			'save_post_subscriptions should accept valid Classic Editor nonce (update-post_{$post_id}), but it called wp_die instead. This indicates the nonce action string is incorrect.'
		);
		$this->assertFalse(
			$exception_thrown,
			'save_post_subscriptions threw an exception due to wp_die being called with a valid nonce.'
		);
	}

	/**
	 * Test that saving a post via wp_update_post does not fail due to revision nonce mismatch.
	 *
	 * When WordPress saves a post, it creates a revision which triggers transition_post_status
	 * with a different post ID (the revision ID). The nonce was created for the original post,
	 * so if save_post_subscriptions doesn't skip revisions, the nonce check will fail.
	 *
	 * @ticket https://wordpress.org/support/topic/upgrading-to-0-10-0-breaks-funtionality-for-editor-role/
	 */
	public function test_save_post_with_revision_does_not_fail_nonce_check() {
		// Create a post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		// Simulate Classic Editor POST request with a valid nonce for the original post.
		$_POST['_wpnonce']          = wp_create_nonce( 'update-post_' . $post_id );
		$_POST['ef-save_followers'] = '1';
		$_POST['ef-selected-users'] = array( self::$editor_user_id );

		// Track if wp_die was called.
		$wp_die_called = false;

		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_called ) {
				return function ( $message ) use ( &$wp_die_called ) {
					$wp_die_called = true;
					throw new \Exception( 'wp_die called: ' . $message );
				};
			}
		);

		// Update the post which triggers the full save lifecycle including revision creation.
		// This will fire transition_post_status for both the post AND the revision.
		// The revision has a different ID, so without the revision skip fix, nonce check fails.
		$exception_thrown = false;
		try {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => 'Updated content to trigger revision',
				)
			);
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse(
			$wp_die_called,
			'wp_update_post should not trigger wp_die - save_post_subscriptions must skip revisions to avoid nonce mismatch'
		);
		$this->assertFalse(
			$exception_thrown,
			'wp_update_post threw an exception due to wp_die being called during revision save'
		);
	}

	/**
	 * Test that the nonce verification uses the correct action string.
	 *
	 * WordPress Classic Editor uses 'update-post_{$post_id}' for the edit form nonce.
	 * This test verifies that a nonce created with this action is considered valid.
	 */
	public function test_classic_editor_nonce_action_is_update_post() {
		$post_id = self::factory()->post->create();

		// Create a nonce using the Classic Editor action.
		$nonce = wp_create_nonce( 'update-post_' . $post_id );

		// Verify the nonce is valid for the correct action.
		$this->assertNotFalse(
			wp_verify_nonce( $nonce, 'update-post_' . $post_id ),
			'Nonce should be valid for update-post_{$post_id} action'
		);

		// The buggy code uses 'editpost' which is NOT a valid WordPress nonce action.
		// This should fail to verify.
		$this->assertFalse(
			wp_verify_nonce( $nonce, 'editpost' ),
			'Nonce created for update-post_{$post_id} should NOT verify against editpost action'
		);
	}
}
