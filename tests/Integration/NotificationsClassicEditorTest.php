<?php
/**
 * Notifications save_post_subscriptions integration tests.
 *
 * Tests for the save_post_subscriptions function to ensure it uses Edit Flow's
 * own nonce and doesn't interfere with other forms or requests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for notifications module save_post_subscriptions function.
 *
 * These tests verify that the notifications module correctly handles
 * nonce verification using Edit Flow's own nonce field, and doesn't
 * interfere with other forms that may trigger post status transitions.
 *
 * @ticket https://github.com/Automattic/edit-flow/issues/882
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
	 * Test that save_post_subscriptions accepts Edit Flow's own nonce.
	 *
	 * The function should process subscriptions when the ef_notifications_nonce
	 * field is present with a valid nonce for 'save_user_usergroups' action.
	 *
	 * @covers EF_Notifications::save_post_subscriptions
	 */
	public function test_save_post_subscriptions_accepts_edit_flow_nonce() {
		global $edit_flow;

		// Create a post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Simulate Edit Flow form submission with its own nonce.
		$_POST['ef_notifications_nonce'] = wp_create_nonce( 'save_user_usergroups' );
		$_POST['ef-save_followers']      = '1';
		$_POST['ef-selected-users']      = array( self::$editor_user_id );

		// Set up wp_die handler to catch any wp_die calls.
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

		$exception_thrown = false;
		try {
			$edit_flow->notifications->save_post_subscriptions( 'draft', 'draft', $post );
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse(
			$wp_die_called,
			'save_post_subscriptions should not call wp_die when given a valid Edit Flow nonce.'
		);
		$this->assertFalse(
			$exception_thrown,
			'save_post_subscriptions should not throw an exception with a valid Edit Flow nonce.'
		);
	}

	/**
	 * Test that save_post_subscriptions ignores requests without Edit Flow form data.
	 *
	 * When ef-save_followers is not set, the function should return early
	 * without processing or dying.
	 *
	 * @covers EF_Notifications::save_post_subscriptions
	 */
	public function test_save_post_subscriptions_ignores_non_edit_flow_requests() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Simulate a request without Edit Flow form data (e.g., REST API, other forms).
		$_POST = array();

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

		$exception_thrown = false;
		try {
			$edit_flow->notifications->save_post_subscriptions( 'draft', 'draft', $post );
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse(
			$wp_die_called,
			'save_post_subscriptions should return early without dying when ef-save_followers is not set.'
		);
		$this->assertFalse(
			$exception_thrown,
			'save_post_subscriptions should not throw when ef-save_followers is not set.'
		);
	}

	/**
	 * Test that save_post_subscriptions doesn't die when another form's nonce is present.
	 *
	 * This is the core fix for #882: when a contact form (or any other form) triggers
	 * a post status transition with its own _wpnonce field, Edit Flow should not
	 * kill the request by calling wp_die().
	 *
	 * @ticket https://github.com/Automattic/edit-flow/issues/882
	 * @covers EF_Notifications::save_post_subscriptions
	 */
	public function test_save_post_subscriptions_does_not_die_with_unrelated_nonce() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Simulate a contact form submission that happens to trigger post status change.
		// The form has its own _wpnonce for a different action (e.g., speaker_submission).
		$_POST['_wpnonce'] = wp_create_nonce( 'speaker_submission' );
		// Note: ef-save_followers is NOT set because this is not an Edit Flow form.

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

		$exception_thrown = false;
		try {
			$edit_flow->notifications->save_post_subscriptions( 'draft', 'publish', $post );
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse(
			$wp_die_called,
			'save_post_subscriptions should NOT die when an unrelated form triggers post transition. ' .
			'This was the bug in #882 where contact form submissions failed because Edit Flow ' .
			'was checking the generic _wpnonce field.'
		);
		$this->assertFalse(
			$exception_thrown,
			'save_post_subscriptions should not throw when an unrelated form triggers post transition.'
		);
	}

	/**
	 * Test that save_post_subscriptions returns early with invalid Edit Flow nonce.
	 *
	 * When Edit Flow's form is submitted but the nonce is invalid, the function
	 * should return early without processing (and importantly, without dying).
	 *
	 * @covers EF_Notifications::save_post_subscriptions
	 */
	public function test_save_post_subscriptions_returns_with_invalid_nonce() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Simulate Edit Flow form submission with an invalid nonce.
		$_POST['ef_notifications_nonce'] = 'invalid_nonce_value';
		$_POST['ef-save_followers']      = '1';
		$_POST['ef-selected-users']      = array( self::$editor_user_id );

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

		$exception_thrown = false;
		try {
			$edit_flow->notifications->save_post_subscriptions( 'draft', 'draft', $post );
		} catch ( \Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse(
			$wp_die_called,
			'save_post_subscriptions should return early, not die, when nonce is invalid. ' .
			'Dying in a transition_post_status hook kills unrelated functionality.'
		);
		$this->assertFalse(
			$exception_thrown,
			'save_post_subscriptions should not throw when nonce is invalid.'
		);
	}

	/**
	 * Test that save_post_subscriptions skips revisions.
	 *
	 * Revisions have different post IDs which would cause nonce verification to fail
	 * if not skipped. This test ensures revisions are properly handled.
	 *
	 * @covers EF_Notifications::save_post_subscriptions
	 */
	public function test_save_post_subscriptions_skips_revisions() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		// Simulate Edit Flow form submission.
		$_POST['ef_notifications_nonce'] = wp_create_nonce( 'save_user_usergroups' );
		$_POST['ef-save_followers']      = '1';
		$_POST['ef-selected-users']      = array( self::$editor_user_id );

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

		// Update the post which triggers revision creation and transition_post_status hooks.
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
			'save_post_subscriptions should skip revisions to avoid nonce mismatch issues.'
		);
		$this->assertFalse(
			$exception_thrown,
			'wp_update_post should not throw due to revision handling.'
		);
	}

	/**
	 * Test that subscriptions are saved when valid Edit Flow nonce is provided.
	 *
	 * @covers EF_Notifications::save_post_subscriptions
	 */
	public function test_save_post_subscriptions_saves_data_with_valid_nonce() {
		global $edit_flow;

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$editor_user_id,
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Simulate Edit Flow form submission with valid nonce.
		$_POST['ef_notifications_nonce'] = wp_create_nonce( 'save_user_usergroups' );
		$_POST['ef-save_followers']      = '1';
		$_POST['ef-selected-users']      = array( self::$editor_user_id );

		// Call the function.
		$edit_flow->notifications->save_post_subscriptions( 'draft', 'draft', $post );

		// Verify the subscription was saved.
		// Note: get_following_users with 'id' returns IDs as integers.
		$following_users = $edit_flow->notifications->get_following_users( $post_id, 'id' );

		$this->assertContains(
			self::$editor_user_id,
			$following_users,
			'User should be saved as a follower when valid Edit Flow nonce is provided.'
		);
	}
}
