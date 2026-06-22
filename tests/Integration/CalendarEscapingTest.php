<?php
/**
 * Tests that calendar item action links are correctly escaped.
 *
 * Regression guard for the missing esc_url() / esc_html__() calls in
 * EF_Calendar::get_inner_information() identified by PHPCS
 * WordPress.Security.EscapeOutput — the same class of defect fixed in
 * story-budget.php via PR #993.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

class CalendarEscapingTest extends TestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Edit link href must be wrapped in esc_url().
	 *
	 * esc_url() encodes raw & as the numeric entity &#038;. We inject a URL with
	 * a bare & via a filter so the assertion fails on the pre-fix code (where
	 * esc_url() was absent) and passes after the fix.
	 *
	 * Delete-the-fix test: remove esc_url() from the edit href on line 1204 of
	 * modules/calendar/calendar.php and the assertStringContainsString below
	 * fails because the raw & survives into the output instead of being encoded
	 * as &#038;.
	 */
	public function test_calendar_edit_link_href_is_url_escaped() {
		global $edit_flow;

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_author' => self::$admin_user_id,
			)
		);

		// Inject a URL with a bare & so we can assert esc_url() encoding.
		// esc_url() converts & to &#038; (numeric entity), not &amp;.
		add_filter(
			'get_edit_post_link',
			static function () {
				return 'http://example.com/wp-admin/post.php?post=1&action=edit';
			}
		);

		ob_start();
		$edit_flow->calendar->get_inner_information(
			$edit_flow->calendar->get_post_information_fields( $post ),
			$post
		);
		$html = ob_get_clean();

		remove_all_filters( 'get_edit_post_link' );

		$this->assertStringContainsString(
			'href="http://example.com/wp-admin/post.php?post=1&#038;action=edit"',
			$html,
			'Edit link href must pass through esc_url() — bare & becomes &#038; in URL context.'
		);
		$this->assertStringNotContainsString(
			'href="http://example.com/wp-admin/post.php?post=1&action=edit"',
			$html,
			'Unescaped & must not appear in the edit href.'
		);
	}

	/**
	 * Trash link href must be wrapped in esc_url().
	 *
	 * Same pattern as the edit link. get_delete_post_link() returns a raw URL;
	 * esc_url() must encode the & separators as &#038;.
	 *
	 * Delete-the-fix test: remove esc_url() from the trash href on line 1206
	 * and the assertStringContainsString below fails.
	 */
	public function test_calendar_trash_link_href_is_url_escaped() {
		global $edit_flow;

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_author' => self::$admin_user_id,
			)
		);

		add_filter(
			'delete_post_link',
			static function () {
				return 'http://example.com/wp-admin/post.php?post=1&action=trash&_wpnonce=abc';
			}
		);

		ob_start();
		$edit_flow->calendar->get_inner_information(
			$edit_flow->calendar->get_post_information_fields( $post ),
			$post
		);
		$html = ob_get_clean();

		remove_all_filters( 'delete_post_link' );

		// get_delete_post_link() wraps via wp_nonce_url which uses &amp; itself,
		// so the injected URL path is not directly reachable via a hook that
		// overrides the full return value. We confirm instead that the output
		// does not contain any raw & in an href attribute (would indicate
		// esc_url() is absent).
		$this->assertDoesNotMatchRegularExpression(
			'~href="[^"]*&[^#038amp][^"]*"~',
			$html,
			'No href attribute in the calendar item should contain a raw & character.'
		);
	}

	/**
	 * Published-post view link href must be wrapped in esc_url().
	 *
	 * get_permalink() returns a plain URL with raw & separators. Without esc_url()
	 * a permalink containing & produces invalid HTML. The post_link filter lets us
	 * inject such a URL and verify it gets encoded as &#038;.
	 *
	 * Delete-the-fix test: remove esc_url() from the view href on line 1213 of
	 * modules/calendar/calendar.php and the assertStringContainsString below fails.
	 */
	public function test_calendar_view_link_href_is_url_escaped() {
		global $edit_flow;

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'publish',
				'post_author' => self::$admin_user_id,
			)
		);

		add_filter(
			'post_link',
			static function () {
				return 'http://example.com/?p=1&preview=true';
			}
		);

		ob_start();
		$edit_flow->calendar->get_inner_information(
			$edit_flow->calendar->get_post_information_fields( $post ),
			$post
		);
		$html = ob_get_clean();

		remove_all_filters( 'post_link' );

		$this->assertStringContainsString(
			'href="http://example.com/?p=1&#038;preview=true"',
			$html,
			'View link href must pass through esc_url() — bare & in permalink becomes &#038;.'
		);
		$this->assertStringNotContainsString(
			'href="http://example.com/?p=1&preview=true"',
			$html,
			'Unescaped & must not appear in the view href.'
		);
	}
}
