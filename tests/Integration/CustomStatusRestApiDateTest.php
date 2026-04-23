<?php
/**
 * Tests for REST API date_gmt serialisation for posts in custom statuses.
 *
 * @package Automattic\EditFlow\Tests\Integration
 * @see https://github.com/Automattic/edit-flow/issues/925
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Custom_Status;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test that the REST API returns null for date and date_gmt on posts in a
 * custom status whose GMT date has not been explicitly set. Nulling date_gmt
 * matches WP core's handling of 'draft' and 'pending'; nulling date in
 * addition is required because Gutenberg's isEditedPostDateFloating selector
 * hardcodes the status whitelist and would otherwise render the concrete
 * date in the Publish field instead of "Immediately".
 */
class CustomStatusRestApiDateTest extends TestCase {

	protected static $admin_user_id;
	protected static $ef_custom_status;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$ef_custom_status = new EF_Custom_Status();
		self::$ef_custom_status->install();
		self::$ef_custom_status->init();

		// Ensure our REST filters are registered; rest_api_init only fires once
		// per REST request boot, so we invoke the registrar directly for tests.
		self::$ef_custom_status->register_rest_api_filters();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::$ef_custom_status = null;
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * A post in a custom status with no GMT date set should serialise both
	 * date and date_gmt as null so the block editor shows "Immediately".
	 */
	public function test_custom_status_post_returns_null_dates() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Pitch Post',
				'post_date'   => '2020-01-15 10:30:00',
			)
		);

		$post = get_post( $post_id );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt, 'Precondition: GMT date should be unset.' );

		$data = $this->fetch_rest_item( $post_id );

		$this->assertArrayHasKey( 'date_gmt', $data );
		$this->assertArrayHasKey( 'date', $data );
		$this->assertNull( $data['date_gmt'], 'date_gmt should be null for custom status posts with no GMT date.' );
		$this->assertNull( $data['date'], 'date should be null so Gutenberg renders "Immediately".' );
	}

	/**
	 * A published post should keep its concrete dates — our filter must
	 * not interfere with posts that have real GMT dates.
	 */
	public function test_published_post_retains_concrete_dates() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Published Post',
			)
		);

		$data = $this->fetch_rest_item( $post_id );

		$this->assertNotNull( $data['date_gmt'], 'Published posts should serialise a concrete date_gmt.' );
		$this->assertNotNull( $data['date'], 'Published posts should serialise a concrete date.' );
	}

	/**
	 * A draft post should still get null date_gmt — core already handles this,
	 * and our filter should not change that behaviour.
	 */
	public function test_draft_post_still_returns_null_date_gmt() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Draft Post',
				'post_date'   => '2020-01-15 10:30:00',
			)
		);

		$data = $this->fetch_rest_item( $post_id );

		$this->assertNull( $data['date_gmt'], 'Core draft behaviour must be preserved.' );
	}

	/**
	 * A post in a custom status but with an explicitly set GMT date (e.g. a
	 * scheduled pitch) should retain that date in the REST response.
	 */
	public function test_custom_status_post_with_explicit_gmt_date_is_preserved() {
		$future_date     = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$future_date_gmt = get_gmt_from_date( $future_date );

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'pitch',
				'post_author'   => self::$admin_user_id,
				'post_title'    => 'Scheduled Pitch',
				'post_date'     => $future_date,
				'post_date_gmt' => $future_date_gmt,
			)
		);

		$data = $this->fetch_rest_item( $post_id );

		$this->assertNotNull( $data['date_gmt'], 'Explicit GMT date should be preserved even for custom statuses.' );
		$this->assertNotNull( $data['date'], 'Explicit date should be preserved even for custom statuses.' );
	}

	/**
	 * A post in a non-registered status should be untouched by our filter.
	 */
	public function test_pending_post_is_unaffected() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'pending',
				'post_author' => self::$admin_user_id,
				'post_title'  => 'Pending Post',
				'post_date'   => '2020-01-15 10:30:00',
			)
		);

		$data = $this->fetch_rest_item( $post_id );

		// Core returns null for pending — we're just asserting we didn't break that.
		$this->assertNull( $data['date_gmt'] );
	}

	private function fetch_rest_item( int $post_id ): array {
		$request  = new \WP_REST_Request( 'GET', sprintf( '/wp/v2/posts/%d', $post_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'REST request should succeed.' );

		return $response->get_data();
	}
}
