<?php
/**
 * Calendar module integration tests.
 *
 * Tests date calculations, post retrieval, and permission checks.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Calendar;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class CalendarModuleTest extends TestCase {

	protected static $admin_user_id;
	protected static $editor_user_id;
	protected static $author_user_id;
	protected static $contributor_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_user_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author_user_id      = $factory->user->create( array( 'role' => 'author' ) );
		self::$contributor_user_id = $factory->user->create( array( 'role' => 'contributor' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::delete_user( self::$editor_user_id );
		self::delete_user( self::$author_user_id );
		self::delete_user( self::$contributor_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Test that the calendar module exists and is accessible.
	 */
	public function test_calendar_module_exists() {
		global $edit_flow;

		$this->assertNotNull( $edit_flow->calendar );
		$this->assertInstanceOf( EF_Calendar::class, $edit_flow->calendar );
	}

	/**
	 * Test get_beginning_of_week with Sunday as start.
	 */
	public function test_get_beginning_of_week_sunday_start() {
		global $edit_flow;

		// Set Sunday (0) as start of week
		update_option( 'start_of_week', 0 );

		// Wednesday, January 15, 2025
		$date   = '2025-01-15';
		$result = $edit_flow->calendar->get_beginning_of_week( $date );

		// Should return Sunday, January 12, 2025
		$this->assertEquals( '2025-01-12', $result );
	}

	/**
	 * Test get_beginning_of_week with Monday as start.
	 */
	public function test_get_beginning_of_week_monday_start() {
		global $edit_flow;

		// Set Monday (1) as start of week
		update_option( 'start_of_week', 1 );

		// Wednesday, January 15, 2025
		$date   = '2025-01-15';
		$result = $edit_flow->calendar->get_beginning_of_week( $date );

		// Should return Monday, January 13, 2025
		$this->assertEquals( '2025-01-13', $result );
	}

	/**
	 * Test get_beginning_of_week when date is already start of week.
	 */
	public function test_get_beginning_of_week_already_start() {
		global $edit_flow;

		// Set Monday (1) as start of week
		update_option( 'start_of_week', 1 );

		// Monday, January 13, 2025
		$date   = '2025-01-13';
		$result = $edit_flow->calendar->get_beginning_of_week( $date );

		// Should return the same date
		$this->assertEquals( '2025-01-13', $result );
	}

	/**
	 * Test get_beginning_of_week with week offset.
	 */
	public function test_get_beginning_of_week_with_offset() {
		global $edit_flow;

		update_option( 'start_of_week', 1 );

		// Wednesday, January 15, 2025
		$date   = '2025-01-15';
		$result = $edit_flow->calendar->get_beginning_of_week( $date, 'Y-m-d', 2 );

		// Week 2 should be January 20, 2025
		$this->assertEquals( '2025-01-20', $result );
	}

	/**
	 * Test get_ending_of_week with Sunday as start.
	 */
	public function test_get_ending_of_week_sunday_start() {
		global $edit_flow;

		// Set Sunday (0) as start of week
		update_option( 'start_of_week', 0 );

		// Wednesday, January 15, 2025
		$date   = '2025-01-15';
		$result = $edit_flow->calendar->get_ending_of_week( $date );

		// Should return Saturday, January 18, 2025
		$this->assertEquals( '2025-01-18', $result );
	}

	/**
	 * Test get_ending_of_week with Monday as start.
	 */
	public function test_get_ending_of_week_monday_start() {
		global $edit_flow;

		// Set Monday (1) as start of week
		update_option( 'start_of_week', 1 );

		// Wednesday, January 15, 2025
		$date   = '2025-01-15';
		$result = $edit_flow->calendar->get_ending_of_week( $date );

		// Should return Sunday, January 19, 2025
		$this->assertEquals( '2025-01-19', $result );
	}

	/**
	 * Test get_ending_of_week when date is already end of week.
	 */
	public function test_get_ending_of_week_already_end() {
		global $edit_flow;

		// Set Monday (1) as start of week
		update_option( 'start_of_week', 1 );

		// Sunday, January 19, 2025
		$date   = '2025-01-19';
		$result = $edit_flow->calendar->get_ending_of_week( $date );

		// Should return the same date
		$this->assertEquals( '2025-01-19', $result );
	}

	/**
	 * Test get_beginning_of_week with different format.
	 */
	public function test_get_beginning_of_week_custom_format() {
		global $edit_flow;

		update_option( 'start_of_week', 1 );

		$date   = '2025-01-15';
		$result = $edit_flow->calendar->get_beginning_of_week( $date, 'F j, Y' );

		$this->assertEquals( 'January 13, 2025', $result );
	}

	/**
	 * Test admin can modify any post.
	 */
	public function test_current_user_can_modify_post_admin() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$author_user_id,
				'post_status' => 'draft',
			)
		);

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertTrue( $result );
	}

	/**
	 * Test editor can modify any post.
	 */
	public function test_current_user_can_modify_post_editor() {
		global $edit_flow;

		wp_set_current_user( self::$editor_user_id );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$author_user_id,
				'post_status' => 'draft',
			)
		);

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertTrue( $result );
	}

	/**
	 * Test author can modify own unpublished post.
	 */
	public function test_current_user_can_modify_post_author_own_draft() {
		global $edit_flow;

		wp_set_current_user( self::$author_user_id );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$author_user_id,
				'post_status' => 'draft',
			)
		);

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertTrue( $result );
	}

	/**
	 * Test author can modify own published post (they can publish).
	 */
	public function test_current_user_can_modify_post_author_own_published() {
		global $edit_flow;

		wp_set_current_user( self::$author_user_id );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$author_user_id,
				'post_status' => 'publish',
			)
		);

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertTrue( $result );
	}

	/**
	 * Test author cannot modify others' posts.
	 */
	public function test_current_user_can_modify_post_author_others_post() {
		global $edit_flow;

		wp_set_current_user( self::$author_user_id );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$admin_user_id,
				'post_status' => 'draft',
			)
		);

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertFalse( $result );
	}

	/**
	 * Test contributor can modify own draft post.
	 */
	public function test_current_user_can_modify_post_contributor_own_draft() {
		global $edit_flow;

		wp_set_current_user( self::$contributor_user_id );

		$post = self::factory()->post->create_and_get(
			array(
				'post_author' => self::$contributor_user_id,
				'post_status' => 'draft',
			)
		);

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertTrue( $result );
	}

	/**
	 * Test contributor cannot modify own published post (they can't publish).
	 */
	public function test_current_user_can_modify_post_contributor_own_published() {
		global $edit_flow;

		wp_set_current_user( self::$contributor_user_id );

		// Create as admin, then test contributor permissions
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$contributor_user_id,
				'post_status' => 'publish',
			)
		);
		$post = get_post( $post_id );

		$result = $edit_flow->calendar->current_user_can_modify_post( $post );

		$this->assertFalse( $result );
	}

	/**
	 * Test current_user_can_modify_post with null post.
	 */
	public function test_current_user_can_modify_post_null() {
		global $edit_flow;

		$result = $edit_flow->calendar->current_user_can_modify_post( null );

		$this->assertFalse( $result );
	}

	/**
	 * Test week boundary calculations across month.
	 */
	public function test_week_boundaries_across_month() {
		global $edit_flow;

		update_option( 'start_of_week', 1 ); // Monday

		// January 30, 2025 (Thursday)
		$date           = '2025-01-30';
		$beginning      = $edit_flow->calendar->get_beginning_of_week( $date );
		$ending         = $edit_flow->calendar->get_ending_of_week( $date );

		// Week should span January to February
		$this->assertEquals( '2025-01-27', $beginning ); // Monday Jan 27
		$this->assertEquals( '2025-02-02', $ending );    // Sunday Feb 2
	}

	/**
	 * Test week boundary calculations across year.
	 */
	public function test_week_boundaries_across_year() {
		global $edit_flow;

		update_option( 'start_of_week', 1 ); // Monday

		// January 1, 2025 (Wednesday)
		$date           = '2025-01-01';
		$beginning      = $edit_flow->calendar->get_beginning_of_week( $date );
		$ending         = $edit_flow->calendar->get_ending_of_week( $date );

		// Week should span December 2024 to January 2025
		$this->assertEquals( '2024-12-30', $beginning ); // Monday Dec 30
		$this->assertEquals( '2025-01-05', $ending );    // Sunday Jan 5
	}

	/**
	 * Test get_time_period_header generates correct HTML.
	 */
	public function test_get_time_period_header() {
		global $edit_flow;

		$dates = array( '2025-01-13', '2025-01-14', '2025-01-15' );
		$html  = $edit_flow->calendar->get_time_period_header( $dates );

		$this->assertStringContainsString( '<th class="column-heading"', $html );
		$this->assertStringContainsString( 'Monday', $html );
		$this->assertStringContainsString( 'Tuesday', $html );
		$this->assertStringContainsString( 'Wednesday', $html );
	}

	/**
	 * Test multiple week offset calculation.
	 */
	public function test_get_beginning_of_week_multiple_weeks() {
		global $edit_flow;

		update_option( 'start_of_week', 1 );

		$date = '2025-01-15';

		$week1 = $edit_flow->calendar->get_beginning_of_week( $date, 'Y-m-d', 1 );
		$week2 = $edit_flow->calendar->get_beginning_of_week( $date, 'Y-m-d', 2 );
		$week3 = $edit_flow->calendar->get_beginning_of_week( $date, 'Y-m-d', 3 );

		$this->assertEquals( '2025-01-13', $week1 );
		$this->assertEquals( '2025-01-20', $week2 );
		$this->assertEquals( '2025-01-27', $week3 );
	}

	/**
	 * Test Saturday start of week.
	 */
	public function test_week_with_saturday_start() {
		global $edit_flow;

		// Set Saturday (6) as start of week
		update_option( 'start_of_week', 6 );

		// Wednesday, January 15, 2025
		$date      = '2025-01-15';
		$beginning = $edit_flow->calendar->get_beginning_of_week( $date );
		$ending    = $edit_flow->calendar->get_ending_of_week( $date );

		// Should return Saturday, January 11, 2025
		$this->assertEquals( '2025-01-11', $beginning );
		// Should return Friday, January 17, 2025
		$this->assertEquals( '2025-01-17', $ending );
	}
}
