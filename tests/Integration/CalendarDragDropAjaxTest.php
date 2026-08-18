<?php
/**
 * Calendar drag-and-drop AJAX integration tests.
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
class CalendarDragDropAjaxTest extends AjaxTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Use a non-UTC timezone so post_date and post_date_gmt genuinely differ,
		// proving the GMT conversion rather than passing vacuously on UTC.
		update_option( 'timezone_string', 'America/New_York' );

		global $edit_flow;
		$edit_flow->calendar->install();

		// Install and initialise custom statuses so draft dates float as they do in
		// production: EF re-registers 'draft' without 'date_floating' and relies on
		// fix_custom_status_timestamp() to keep an unset post_date_gmt zeroed.
		$edit_flow->custom_status->install();
		$edit_flow->custom_status->init();

		// EF_Calendar::init() only registers its AJAX actions for users who can view the
		// calendar; in production that runs per-request as the logged-in user. Set such a
		// user before init() so the ef_calendar_drag_and_drop action is actually wired up.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$edit_flow->calendar->init();
	}

	/**
	 * Test: dragging a scheduled post keeps post_date_gmt in sync with post_date
	 * and reschedules the publish_future_post cron event.
	 *
	 * @see https://github.com/Automattic/edit-flow/issues/1008
	 */
	public function test_drag_scheduled_post_keeps_post_date_gmt_in_sync(): void {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'future',
			'post_date'   => date( 'Y-m-d', strtotime( '+5 days' ) ) . ' 10:00:00',
		) );

		$post = get_post( $post_id );
		$this->assertNotEquals(
			'0000-00-00 00:00:00',
			$post->post_date_gmt,
			'Fixture sanity: a scheduled post must have a concrete post_date_gmt.'
		);

		$target_day = date( 'Y-m-d', strtotime( '+10 days' ) );

		$response_body = $this->drag_post_to_date( $post_id, $target_day );
		$this->assertStringContainsString( '"status":"success"', $response_body );

		$post = get_post( $post_id );
		$this->assertSame( $target_day . ' 10:00:00', $post->post_date, 'post_date should move to the new day, keeping the time.' );
		$this->assertSame(
			get_gmt_from_date( $post->post_date ),
			$post->post_date_gmt,
			'post_date_gmt must be updated to match the new post_date.'
		);
		$this->assertSame(
			strtotime( $post->post_date_gmt . ' GMT' ),
			wp_next_scheduled( 'publish_future_post', array( $post_id ) ),
			'The publish cron event must be rescheduled to the new publish time.'
		);
	}

	/**
	 * Test: dragging a scheduled post to a day in the past schedules its publish
	 * cron event in the past, so it publishes on the next cron run.
	 */
	public function test_drag_scheduled_post_to_past_schedules_immediate_publish(): void {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'future',
			'post_date'   => date( 'Y-m-d', strtotime( '+5 days' ) ) . ' 10:00:00',
		) );

		$target_day = date( 'Y-m-d', strtotime( '-3 days' ) );

		$response_body = $this->drag_post_to_date( $post_id, $target_day );
		$this->assertStringContainsString( '"status":"success"', $response_body );

		$post           = get_post( $post_id );
		$next_scheduled = wp_next_scheduled( 'publish_future_post', array( $post_id ) );

		$this->assertSame( get_gmt_from_date( $post->post_date ), $post->post_date_gmt );
		$this->assertSame( strtotime( $post->post_date_gmt . ' GMT' ), $next_scheduled );
		$this->assertLessThan( time(), $next_scheduled, 'The cron event should be due immediately.' );
	}

	/**
	 * Test: dragging a post with a floating date keeps it floating, preserving
	 * "publish immediately when published" semantics.
	 */
	public function test_drag_floating_date_post_keeps_date_floating(): void {
		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );

		$post = get_post( $post_id );
		$this->assertSame(
			'0000-00-00 00:00:00',
			$post->post_date_gmt,
			'Fixture sanity: a draft without an explicit date must have a floating post_date_gmt.'
		);

		$target_day = date( 'Y-m-d', strtotime( '+7 days' ) );

		$response_body = $this->drag_post_to_date( $post_id, $target_day );
		$this->assertStringContainsString( '"status":"success"', $response_body );

		$post = get_post( $post_id );
		$this->assertSame( $target_day, substr( $post->post_date, 0, 10 ), 'post_date should move to the new day.' );
		$this->assertSame(
			'0000-00-00 00:00:00',
			$post->post_date_gmt,
			'A floating post_date_gmt must stay floating by default.'
		);
	}

	/**
	 * Test: the ef_calendar_allow_ajax_to_set_timestamp filter still opts a
	 * floating-date post into having its publish timestamp set.
	 */
	public function test_drag_floating_date_post_sets_timestamp_when_filter_enabled(): void {
		add_filter( 'ef_calendar_allow_ajax_to_set_timestamp', '__return_true' );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
		) );

		$target_day = date( 'Y-m-d', strtotime( '+7 days' ) );

		$response_body = $this->drag_post_to_date( $post_id, $target_day );
		$this->assertStringContainsString( '"status":"success"', $response_body );

		$post = get_post( $post_id );
		$this->assertSame(
			get_gmt_from_date( $post->post_date ),
			$post->post_date_gmt,
			'With the filter enabled, post_date_gmt must be set to the GMT equivalent of the new post_date.'
		);
	}

	/**
	 * Drag a post to a new date via the AJAX endpoint and capture the JSON response body.
	 *
	 * @param int    $post_id  Post to move.
	 * @param string $new_date Target date in Y-m-d format.
	 */
	private function drag_post_to_date( int $post_id, string $new_date ): string {
		$_POST['nonce']     = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id']   = $post_id;
		$_POST['next_date'] = $new_date;

		try {
			$this->_handleAjax( 'ef_calendar_drag_and_drop' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->fail( 'Unexpected stop exception: ' . $e->getMessage() );
		}

		return (string) $this->_last_response;
	}
}
