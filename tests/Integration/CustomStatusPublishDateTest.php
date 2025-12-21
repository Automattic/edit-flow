<?php
/**
 * Tests for post date behavior when publishing from custom statuses.
 *
 * @package Automattic\EditFlow\Tests\Integration
 * @see https://github.com/Automattic/edit-flow/issues/750
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Custom_Status;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test that post_date is updated when publishing from a custom status.
 *
 * When a post is created with a custom status like "Pitch" or "Assigned",
 * and later published, the post_date should be updated to reflect the
 * actual publication time, not the original creation time.
 */
class CustomStatusPublishDateTest extends TestCase {

	protected static $admin_user_id;
	protected static $ef_custom_status;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$ef_custom_status = new EF_Custom_Status();
		self::$ef_custom_status->install();
		self::$ef_custom_status->init();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::$ef_custom_status = null;
	}

	protected function setUp(): void {
		parent::setUp();

		global $pagenow;
		$pagenow = 'post.php';
	}

	protected function tearDown(): void {
		global $pagenow;
		$pagenow = 'index.php';

		parent::tearDown();
	}

	/**
	 * Test that publishing a post from a custom status updates post_date to current time.
	 *
	 * This test demonstrates the issue reported in #750:
	 * Posts published from custom statuses retain their original creation date
	 * instead of being updated to the actual publication date.
	 */
	public function test_publishing_from_custom_status_updates_post_date() {
		// Create a post with 'pitch' status at a specific time in the past.
		$past_date = '2020-01-15 10:30:00';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Test Post',
				'post_date'   => $past_date,
			)
		);

		$post_before = get_post( $post_id );

		// Verify the post was created with the past date.
		$this->assertEquals( $past_date, $post_before->post_date, 'Post should be created with the specified past date.' );
		$this->assertEquals( '0000-00-00 00:00:00', $post_before->post_date_gmt, 'Custom status posts should have empty GMT date.' );

		// Now publish the post (simulating what happens when an editor clicks "Publish").
		$current_time_before_publish = current_time( 'mysql' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$current_time_after_publish = current_time( 'mysql' );

		$post_after = get_post( $post_id );

		// The post_date should be updated to reflect the actual publish time,
		// not retain the old creation date.
		$this->assertNotEquals(
			$past_date,
			$post_after->post_date,
			'Post date should be updated when publishing from custom status.'
		);

		// The new post_date should be approximately the current time.
		$this->assertGreaterThanOrEqual(
			$current_time_before_publish,
			$post_after->post_date,
			'Post date should be at or after the time we started publishing.'
		);

		$this->assertLessThanOrEqual(
			$current_time_after_publish,
			$post_after->post_date,
			'Post date should be at or before the time we finished publishing.'
		);

		// post_date_gmt should also be set correctly.
		$this->assertNotEquals(
			'0000-00-00 00:00:00',
			$post_after->post_date_gmt,
			'GMT date should be set when publishing.'
		);
	}

	/**
	 * Test that publishing a post from 'draft' status updates post_date.
	 *
	 * Draft is a core status that Edit Flow overrides; it should behave the same way.
	 */
	public function test_publishing_from_draft_status_updates_post_date() {
		$past_date = '2020-06-20 14:00:00';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Draft Test Post',
				'post_date'   => $past_date,
			)
		);

		$post_before = get_post( $post_id );
		$this->assertEquals( $past_date, $post_before->post_date );

		$current_time_before_publish = current_time( 'mysql' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$post_after = get_post( $post_id );

		$this->assertNotEquals(
			$past_date,
			$post_after->post_date,
			'Post date should be updated when publishing from draft status.'
		);

		$this->assertGreaterThanOrEqual(
			$current_time_before_publish,
			$post_after->post_date,
			'Post date should reflect the publish time.'
		);
	}

	/**
	 * Test that publishing a post from 'pending' status updates post_date.
	 */
	public function test_publishing_from_pending_status_updates_post_date() {
		$past_date = '2019-03-10 08:15:00';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'pending',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Pending Test Post',
				'post_date'   => $past_date,
			)
		);

		$post_before = get_post( $post_id );
		$this->assertEquals( $past_date, $post_before->post_date );

		$current_time_before_publish = current_time( 'mysql' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$post_after = get_post( $post_id );

		$this->assertNotEquals(
			$past_date,
			$post_after->post_date,
			'Post date should be updated when publishing from pending status.'
		);

		$this->assertGreaterThanOrEqual(
			$current_time_before_publish,
			$post_after->post_date,
			'Post date should reflect the publish time.'
		);
	}

	/**
	 * Test that a scheduled post retains its scheduled date when it becomes published.
	 *
	 * This is the expected behavior for scheduled posts - they should keep their
	 * scheduled date, not update to current time.
	 */
	public function test_scheduled_post_retains_date_when_published() {
		$future_date     = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$future_date_gmt = get_gmt_from_date( $future_date );

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'future',
				'post_author'   => self::$admin_user_id,
				'post_title'    => 'Scheduled Test Post',
				'post_date'     => $future_date,
				'post_date_gmt' => $future_date_gmt,
			)
		);

		$post_before = get_post( $post_id );
		$this->assertEquals( $future_date, $post_before->post_date );
		$this->assertEquals( $future_date_gmt, $post_before->post_date_gmt );

		// Simulate the scheduled publish (what wp-cron does).
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$post_after = get_post( $post_id );

		// Scheduled posts should retain their scheduled date.
		$this->assertEquals(
			$future_date,
			$post_after->post_date,
			'Scheduled posts should retain their scheduled date when published.'
		);
	}

	/**
	 * Test that publishing via REST API also updates the post date.
	 */
	public function test_publishing_from_custom_status_via_rest_api_updates_post_date() {
		wp_set_current_user( self::$admin_user_id );

		$past_date = '2021-02-28 16:45:00';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'REST API Test Post',
				'post_date'   => $past_date,
			)
		);

		$post_before = get_post( $post_id );
		$this->assertEquals( $past_date, $post_before->post_date );

		$current_time_before_publish = current_time( 'mysql' );

		// Publish via REST API.
		$request = new \WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params(
			array(
				'status' => 'publish',
			)
		);
		rest_get_server()->dispatch( $request );

		$post_after = get_post( $post_id );

		$this->assertNotEquals(
			$past_date,
			$post_after->post_date,
			'Post date should be updated when publishing via REST API from custom status.'
		);

		$this->assertGreaterThanOrEqual(
			$current_time_before_publish,
			$post_after->post_date,
			'Post date should reflect the publish time.'
		);
	}

	/**
	 * Test that if a user explicitly sets a post date before publishing, it is respected.
	 *
	 * If the user deliberately backdates or forward-dates a post, that should be honored.
	 */
	public function test_explicit_post_date_is_respected_when_publishing() {
		$creation_date = '2020-01-15 10:30:00';
		$explicit_date = '2023-06-15 12:00:00';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Explicit Date Test Post',
				'post_date'   => $creation_date,
			)
		);

		// Publish with an explicitly set date.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
				'post_date'   => $explicit_date,
			)
		);

		$post_after = get_post( $post_id );

		$this->assertEquals(
			$explicit_date,
			$post_after->post_date,
			'Explicitly set post date should be respected when publishing.'
		);
	}
}
