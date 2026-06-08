<?php
/**
 * Notifications integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Notifications;
use WP_Error;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class NotificationsTest extends TestCase {

	protected static $admin_user_id;
	protected static $ef_notifications;

	public static function wpSetUpBeforeClass( $factory ) {
		global $edit_flow;

		/**
		 * `install` is hooked to `admin_init` and `init` is hooked to `init`.
		 * This means when running these tests, you can encounter a situation
		 * where the custom post type taxonomy has not been loaded into the database
		 * since the tests don't trigger `admin_init` and the `install` function is where
		 * the custom post type taxonomy is loaded into the DB.
		 *
		 * So make sure we do one cycle of `install` followed by `init` to ensure
		 * custom post type taxonomy has been loaded.
		 */
		$edit_flow->custom_status->install();
		$edit_flow->custom_status->init();

		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$ef_notifications = new EF_Notifications();
		self::$ef_notifications->install();
		self::$ef_notifications->init();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::$ef_notifications = null;
	}

	/**
	 * Test that a notification status change text is accurate when no status
	 * is provided in wp_insert_post
	 */
	function test_send_post_notification_no_status() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		$edit_flow->notifications->module->options->always_notify_admin = 'on';

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		wp_insert_post( $post );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( strpos( $mailer->get_sent()->body, 'New => Draft' ) > 0 );
	}

	/**
	 * Test that a notification status change text is accurate when no status
	 * is provided in wp_insert_post when the custom status module is disabled
	 */
	function test_send_post_notification_no_status_custom_status_disabled_for_post_type() {
		global $edit_flow, $typenow;

		wp_set_current_user( self::$admin_user_id );

		/**
		 * Prevent the registration of custom status to check if notification module will still
		 * work when custom status module is disabled and custom statuses are not registered
		 */
		$typenow = 'post';

		$edit_flow->custom_status->module->options->post_types = array( 'page' );
		/**
		 * Initiate a full cycle (install/init) to ensure the core statuses are returned
		 * instead of custom stautses (since we're disabling the module for this post type)
		 */
		$edit_flow->custom_status->install();
		$edit_flow->custom_status->init();

		$edit_flow->notifications->module->options->always_notify_admin = 'on';

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		wp_insert_post( $post );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( strpos( $mailer->get_sent()->body, 'New => Draft' ) > 0 );
	}

	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Saving subscriptions must ignore user IDs the subscription picker would not offer
	 * (users without publish_posts), so arbitrary users cannot be subscribed to a post.
	 */
	public function test_save_subscriptions_ignores_ineligible_users() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		$eligible_id   = self::factory()->user->create( array( 'role' => 'author' ) );      // has publish_posts.
		$ineligible_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );  // no publish_posts.

		$post_id = self::factory()->post->create( array( 'post_author' => self::$admin_user_id ) );

		$_POST['ef-save_followers']      = '1';
		$_POST['ef_notifications_nonce'] = wp_create_nonce( 'save_user_usergroups' );
		$_POST['ef-selected-users']      = array( (string) $eligible_id, (string) $ineligible_id );

		$edit_flow->notifications->save_post_subscriptions( 'publish', 'draft', get_post( $post_id ) );

		$followers = array_map( 'intval', (array) $edit_flow->notifications->get_following_users( $post_id, 'id' ) );

		$this->assertContains( $eligible_id, $followers, 'An eligible (publish_posts) user should be subscribed.' );
		$this->assertNotContains( $ineligible_id, $followers, 'A user the picker would not offer must not be subscribed.' );
	}

	/**
	 * A failed webhook request must not abort the request it runs within (post transitions
	 * and comment insertion are not always AJAX), so send_to_webhook returns instead of dying.
	 */
	public function test_webhook_failure_does_not_abort() {
		global $edit_flow;

		$edit_flow->notifications->module->options->webhook_url = 'https://example.com/webhook';

		$blocker = static function () {
			return new WP_Error( 'http_request_failed', 'blocked in test' );
		};
		add_filter( 'pre_http_request', $blocker );

		$post_id = self::factory()->post->create();
		$result  = $edit_flow->notifications->send_to_webhook(
			'A message',
			'status-change',
			get_user_by( 'id', self::$admin_user_id ),
			get_post( $post_id )
		);

		remove_filter( 'pre_http_request', $blocker );

		$this->assertNull( $result, 'A failed webhook should return without aborting the request.' );
	}
}
