<?php
/**
 * Calendar integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

class CalendarTest extends TestCase {

	protected static $admin_user_id;
	protected static $EF_Calendar;

	public static function wpSetUpBeforeClass( $factory ) {
		global $edit_flow;

		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		$edit_flow->custom_status->install();
		$edit_flow->custom_status->init();

		$edit_flow->calendar->install();
		$edit_flow->calendar->init();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	/**
	 * Test that calendar has default custom statuses
	 */
	public function test_calendar_custom_statuses() {
		global $edit_flow;

		$statuses = array_map(
			function ( $status ) {
				return $status->name;
			},
			$edit_flow->calendar->get_calendar_post_stati()
		);

		$this->assertContains( 'future', $statuses );
		$this->assertContains( 'pitch', $statuses );
	}

	/**
	 * With custom statuses turned off, the calendar falls back to core's statuses.
	 * Draft comes first in that list, and must not be dropped.
	 */
	public function test_calendar_post_stati_include_core_statuses_when_custom_statuses_disabled() {
		global $edit_flow;

		$options          = $edit_flow->custom_status->module->options;
		$original_enabled = $options->enabled;
		$options->enabled = 'off';

		try {
			$statuses = wp_list_pluck( $edit_flow->calendar->get_calendar_post_stati(), 'name' );
		} finally {
			$options->enabled = $original_enabled;
		}

		$this->assertContains( 'draft', $statuses );
		$this->assertContains( 'pending', $statuses );
		$this->assertContains( 'future', $statuses );
		$this->assertContains( 'publish', $statuses );
	}

	/**
	 * Test that calendar can show registered status
	 */
	public function test_calendar_custom_statuses_registered() {
		global $edit_flow;

		$new_custom_status = array(
			'term' => __( 'New Custom Status' ),
			'args' => array(
				'slug'        => 'new-custom-status',
				'description' => 'description',
				'position'    => 6,
			),
		);

		$edit_flow->custom_status->add_custom_status( $new_custom_status['term'], $new_custom_status['args'] );

		$statuses = array_map(
			function ( $status ) {
				return $status->name;
			},
			$edit_flow->calendar->get_calendar_post_stati()
		);

		$this->assertContains( 'future', $statuses );
	}
}
