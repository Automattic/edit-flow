<?php
/**
 * Tests for notification subscriber warning badges feature.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Notifications;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Tests for the display_subscriber_warning_badges method in EF_Notifications.
 */
class NotificationsBadgesTest extends TestCase {

	protected static $admin_user_id;
	protected static $editor_user_id;
	protected static $author_user_id;
	protected static $subscriber_user_id;
	protected static $no_email_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		global $edit_flow;

		// Ensure notifications module is initialized.
		$edit_flow->notifications->install();
		$edit_flow->notifications->init();

		self::$admin_user_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin@example.com',
			)
		);

		self::$editor_user_id = $factory->user->create(
			array(
				'role'       => 'editor',
				'user_email' => 'editor@example.com',
			)
		);

		self::$author_user_id = $factory->user->create(
			array(
				'role'       => 'author',
				'user_email' => 'author@example.com',
			)
		);

		self::$subscriber_user_id = $factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'subscriber@example.com',
			)
		);

		// Create a user without an email address.
		self::$no_email_user_id = $factory->user->create(
			array(
				'role'       => 'editor',
				'user_email' => '',
			)
		);
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::delete_user( self::$editor_user_id );
		self::delete_user( self::$author_user_id );
		self::delete_user( self::$subscriber_user_id );
		self::delete_user( self::$no_email_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	protected function tearDown(): void {
		// Clean up any filters we may have added.
		remove_all_filters( 'ef_notification_auto_subscribe_post_author' );
		parent::tearDown();
	}

	/**
	 * Test that post author gets "Post Author" badge.
	 */
	public function test_post_author_gets_post_author_badge() {
		global $edit_flow, $post;

		// Create a post with a specific author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Set the global $post variable as the method relies on it.
		$post = get_post( $post_id );

		// Capture the output.
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$author_user_id, false );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'post_following_list-post_author', $output );
		$this->assertStringContainsString( 'Post Author', $output );
	}

	/**
	 * Test that post author gets "Auto-subscribed" badge when auto-subscribe is enabled and they're subscribed.
	 */
	public function test_post_author_gets_auto_subscribed_badge_when_subscribed() {
		global $edit_flow, $post;

		// Create a post with a specific author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Ensure auto-subscribe filter returns true (default behavior).
		add_filter( 'ef_notification_auto_subscribe_post_author', '__return_true' );

		// Capture the output when user IS subscribed (checked = true).
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$author_user_id, true );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'post_following_list-auto_subscribed', $output );
		$this->assertStringContainsString( 'Auto-subscribed', $output );
	}

	/**
	 * Test that post author does NOT get "Auto-subscribed" badge when not subscribed.
	 */
	public function test_post_author_no_auto_subscribed_badge_when_not_subscribed() {
		global $edit_flow, $post;

		// Create a post with a specific author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Ensure auto-subscribe filter returns true.
		add_filter( 'ef_notification_auto_subscribe_post_author', '__return_true' );

		// Capture the output when user is NOT subscribed (checked = false).
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$author_user_id, false );
		$output = ob_get_clean();

		// Should have "Post Author" badge but NOT "Auto-subscribed" badge.
		$this->assertStringContainsString( 'post_following_list-post_author', $output );
		$this->assertStringNotContainsString( 'post_following_list-auto_subscribed', $output );
	}

	/**
	 * Test that post author does NOT get "Auto-subscribed" badge when auto-subscribe is disabled.
	 */
	public function test_post_author_no_auto_subscribed_badge_when_filter_disabled() {
		global $edit_flow, $post;

		// Create a post with a specific author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Disable auto-subscribe via filter.
		add_filter( 'ef_notification_auto_subscribe_post_author', '__return_false' );

		// Capture the output when user IS subscribed (checked = true).
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$author_user_id, true );
		$output = ob_get_clean();

		// Should have "Post Author" badge but NOT "Auto-subscribed" badge.
		$this->assertStringContainsString( 'post_following_list-post_author', $output );
		$this->assertStringNotContainsString( 'post_following_list-auto_subscribed', $output );
	}

	/**
	 * Test that non-author users don't get "Post Author" badge.
	 */
	public function test_non_author_does_not_get_post_author_badge() {
		global $edit_flow, $post;

		// Create a post with admin as the author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Capture the output for a different user (editor, not the post author).
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$editor_user_id, true );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'post_following_list-post_author', $output );
		$this->assertStringNotContainsString( 'Post Author', $output );
	}

	/**
	 * Test that subscriber user gets "No Access" badge when subscribed.
	 */
	public function test_subscriber_gets_no_access_badge_when_subscribed() {
		global $edit_flow, $post;

		// Create a post.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Capture the output for a subscriber (who can't edit the post).
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$subscriber_user_id, true );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'post_following_list-no_access', $output );
		$this->assertStringContainsString( 'No Access', $output );
	}

	/**
	 * Test that subscriber user does NOT get "No Access" badge when not subscribed.
	 */
	public function test_subscriber_no_access_badge_only_when_subscribed() {
		global $edit_flow, $post;

		// Create a post.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Capture the output for a subscriber when NOT subscribed.
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$subscriber_user_id, false );
		$output = ob_get_clean();

		// No Access badge should NOT appear when not subscribed.
		$this->assertStringNotContainsString( 'post_following_list-no_access', $output );
	}

	/**
	 * Test that user with no email gets "No Email" badge when subscribed.
	 */
	public function test_user_without_email_gets_no_email_badge_when_subscribed() {
		global $edit_flow, $post;

		// Create a post.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Capture the output for user without email.
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$no_email_user_id, true );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'post_following_list-no_email', $output );
		$this->assertStringContainsString( 'No Email', $output );
	}

	/**
	 * Test that user with no email does NOT get "No Email" badge when not subscribed.
	 */
	public function test_user_without_email_no_email_badge_only_when_subscribed() {
		global $edit_flow, $post;

		// Create a post.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Capture the output for user without email when NOT subscribed.
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$no_email_user_id, false );
		$output = ob_get_clean();

		// No Email badge should NOT appear when not subscribed.
		$this->assertStringNotContainsString( 'post_following_list-no_email', $output );
	}

	/**
	 * Test that method returns early when global $post is not set.
	 */
	public function test_returns_early_when_no_post() {
		global $edit_flow, $post;

		// Unset the global $post variable.
		$post = null;

		// Capture the output.
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( self::$author_user_id, true );
		$output = ob_get_clean();

		// Should output nothing when $post is not set.
		$this->assertEmpty( $output );
	}

	/**
	 * Test that invalid user_id returns "No Email" badge (since get_user_by returns false).
	 */
	public function test_invalid_user_id_gets_no_email_badge_when_subscribed() {
		global $edit_flow, $post;

		// Create a post.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Use an invalid user ID.
		$invalid_user_id = 999999;

		// Capture the output.
		ob_start();
		$edit_flow->notifications->display_subscriber_warning_badges( $invalid_user_id, true );
		$output = ob_get_clean();

		// Should have "No Email" badge since get_user_by will return false.
		$this->assertStringContainsString( 'post_following_list-no_email', $output );
	}

	/**
	 * Test that localization data includes post_author_id when on a post edit screen.
	 *
	 * The enqueue_admin_scripts method adds post_author_id, post_author_auto_subscribe,
	 * and post_author_is_following to the localization data when $post is set.
	 */
	public function test_localization_includes_post_author_id_on_post_edit_screen() {
		global $edit_flow, $post, $pagenow, $typenow;

		// Set up the admin context for a post edit screen.
		$original_pagenow = $pagenow;
		$original_typenow = $typenow;
		$pagenow          = 'post.php';
		$typenow          = 'post';

		// Create a post with a specific author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Set current screen to simulate the post edit screen.
		set_current_screen( 'post' );

		// Dequeue any previously registered script to ensure fresh state.
		wp_deregister_script( 'edit-flow-notifications-js' );

		// Call enqueue_admin_scripts.
		$edit_flow->notifications->enqueue_admin_scripts();

		// Get the localized data for the script.
		$wp_scripts     = wp_scripts();
		$localized_data = $wp_scripts->get_data( 'edit-flow-notifications-js', 'data' );

		// Restore original globals.
		$pagenow = $original_pagenow;
		$typenow = $original_typenow;
		set_current_screen( 'front' );

		// Verify the script was registered and has localization data.
		$this->assertNotEmpty( $localized_data, 'Script should have localization data' );

		// The localization data is a JavaScript string, so check for the expected values.
		$this->assertStringContainsString( 'post_author_id', $localized_data );
		$this->assertStringContainsString( (string) self::$author_user_id, $localized_data );
		$this->assertStringContainsString( 'post_author_auto_subscribe', $localized_data );
		$this->assertStringContainsString( 'post_author_is_following', $localized_data );
	}

	/**
	 * Test that localization data includes correct badge labels for JavaScript.
	 */
	public function test_localization_includes_badge_labels() {
		global $edit_flow, $post, $pagenow, $typenow;

		// Set up the admin context for a post edit screen.
		$original_pagenow = $pagenow;
		$original_typenow = $typenow;
		$pagenow          = 'post.php';
		$typenow          = 'post';

		// Create a post.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$admin_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Set current screen to simulate the post edit screen.
		set_current_screen( 'post' );

		// Dequeue any previously registered script to ensure fresh state.
		wp_deregister_script( 'edit-flow-notifications-js' );

		// Call enqueue_admin_scripts.
		$edit_flow->notifications->enqueue_admin_scripts();

		// Get the localized data for the script.
		$wp_scripts     = wp_scripts();
		$localized_data = $wp_scripts->get_data( 'edit-flow-notifications-js', 'data' );

		// Restore original globals.
		$pagenow = $original_pagenow;
		$typenow = $original_typenow;
		set_current_screen( 'front' );

		// Verify all badge labels are included in the localization data.
		$this->assertStringContainsString( 'no_access', $localized_data );
		$this->assertStringContainsString( 'No Access', $localized_data );
		$this->assertStringContainsString( 'no_email', $localized_data );
		$this->assertStringContainsString( 'No Email', $localized_data );
		$this->assertStringContainsString( 'post_author', $localized_data );
		$this->assertStringContainsString( 'Post Author', $localized_data );
		$this->assertStringContainsString( 'auto_subscribed', $localized_data );
		$this->assertStringContainsString( 'Auto-subscribed', $localized_data );
	}

	/**
	 * Test that post author with no followers shows post_author_is_following as false.
	 */
	public function test_localization_post_author_is_following_false_when_not_subscribed() {
		global $edit_flow, $post, $pagenow, $typenow;

		// Set up the admin context for a post edit screen.
		$original_pagenow = $pagenow;
		$original_typenow = $typenow;
		$pagenow          = 'post.php';
		$typenow          = 'post';

		// Create a post with a specific author but don't add any followers.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Set current screen to simulate the post edit screen.
		set_current_screen( 'post' );

		// Dequeue any previously registered script to ensure fresh state.
		wp_deregister_script( 'edit-flow-notifications-js' );

		// Call enqueue_admin_scripts.
		$edit_flow->notifications->enqueue_admin_scripts();

		// Get the localized data for the script.
		$wp_scripts     = wp_scripts();
		$localized_data = $wp_scripts->get_data( 'edit-flow-notifications-js', 'data' );

		// Restore original globals.
		$pagenow = $original_pagenow;
		$typenow = $original_typenow;
		set_current_screen( 'front' );

		// Verify post_author_is_following is false (represented as empty string "" in the JS output).
		$this->assertStringContainsString( 'post_author_is_following', $localized_data );
		// When false, wp_localize_script outputs an empty string.
		$this->assertStringContainsString( '"post_author_is_following":""', $localized_data );
	}

	/**
	 * Test that post author who is subscribed shows post_author_is_following as true.
	 */
	public function test_localization_post_author_is_following_true_when_subscribed() {
		global $edit_flow, $post, $pagenow, $typenow;

		// Set up the admin context for a post edit screen.
		$original_pagenow = $pagenow;
		$original_typenow = $typenow;
		$pagenow          = 'post.php';
		$typenow          = 'post';

		// Create a post with a specific author.
		$post_id = self::factory()->post->create(
			array( 'post_author' => self::$author_user_id )
		);

		// Add the author as a follower.
		$edit_flow->notifications->follow_post_user( $post_id, self::$author_user_id );

		// Set the global $post variable.
		$post = get_post( $post_id );

		// Set current screen to simulate the post edit screen.
		set_current_screen( 'post' );

		// Dequeue any previously registered script to ensure fresh state.
		wp_deregister_script( 'edit-flow-notifications-js' );

		// Call enqueue_admin_scripts.
		$edit_flow->notifications->enqueue_admin_scripts();

		// Get the localized data for the script.
		$wp_scripts     = wp_scripts();
		$localized_data = $wp_scripts->get_data( 'edit-flow-notifications-js', 'data' );

		// Restore original globals.
		$pagenow = $original_pagenow;
		$typenow = $original_typenow;
		set_current_screen( 'front' );

		// Verify post_author_is_following is true (wp_localize_script outputs "1" for true).
		$this->assertStringContainsString( '"post_author_is_following":"1"', $localized_data );
	}
}
