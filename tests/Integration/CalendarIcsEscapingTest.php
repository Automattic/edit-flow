<?php
/**
 * Tests for ICS text escaping per RFC 5545.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test that do_ics_escaping escapes text correctly per RFC 5545, section 3.3.11.
 */
class CalendarIcsEscapingTest extends TestCase {

	/**
	 * Calendar module instance.
	 *
	 * @var \EF_Calendar
	 */
	protected $calendar;

	protected function setUp(): void {
		parent::setUp();

		global $edit_flow;
		$this->calendar = $edit_flow->calendar;
	}

	/**
	 * A comma must be escaped as "\,".
	 */
	public function test_escapes_comma() {
		$this->assertSame( 'one\,two', $this->calendar->do_ics_escaping( 'one,two' ) );
	}

	/**
	 * A semicolon must be escaped as "\;" — not "\:".
	 */
	public function test_escapes_semicolon() {
		$this->assertSame( 'one\;two', $this->calendar->do_ics_escaping( 'one;two' ) );
	}

	/**
	 * A backslash must be escaped as "\\".
	 */
	public function test_escapes_backslash() {
		$this->assertSame( 'one\\\\two', $this->calendar->do_ics_escaping( 'one\\two' ) );
	}

	/**
	 * Newlines must be escaped as "\n" to avoid breaking the feed structure.
	 */
	public function test_escapes_newlines() {
		$this->assertSame( 'a\nb\nc\nd', $this->calendar->do_ics_escaping( "a\nb\rc\r\nd" ) );
	}

	/**
	 * Backslashes must be escaped first so the escape character introduced by
	 * subsequent replacements is not itself doubled.
	 */
	public function test_does_not_double_escape_inserted_backslashes() {
		$this->assertSame( '\,', $this->calendar->do_ics_escaping( ',' ) );
		$this->assertSame( '\;', $this->calendar->do_ics_escaping( ';' ) );
		$this->assertSame( '\n', $this->calendar->do_ics_escaping( "\n" ) );
	}

	/**
	 * A plain text string with none of the special characters must pass through untouched.
	 */
	public function test_plain_text_is_unchanged() {
		$this->assertSame( 'Hello world', $this->calendar->do_ics_escaping( 'Hello world' ) );
	}

	/**
	 * All escapable characters combined must be escaped together.
	 */
	public function test_combined_special_characters() {
		$input    = "Status: pending, draft; notes\\ with\nnewline";
		$expected = 'Status: pending\, draft\; notes\\\\ with\nnewline';
		$this->assertSame( $expected, $this->calendar->do_ics_escaping( $input ) );
	}
}
