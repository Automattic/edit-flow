<?php

class WP_Test_Edit_Flow_Notifications extends WP_UnitTestCase {

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

		$edit_flow->notifications->module->options->always_notify_admin = 'on';

		$post = array(
			'post_author' => self::$admin_user_id,
			'post_content' => rand_str(),
			'post_title' => rand_str(),
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
			'post_author' => self::$admin_user_id,
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		wp_insert_post( $post );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertTrue( strpos( $mailer->get_sent()->body, 'New => Draft' ) > 0 );
	}
}
