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
	 * Edit and trash link hrefs must be wrapped in esc_url().
	 *
	 * A raw & in a URL breaks HTML attribute syntax; esc_url() converts it to
	 * &amp;. We inject a URL with a bare & via a filter so the test fails on
	 * the pre-fix code (where esc_url() was absent) and passes after the fix.
	 *
	 * Delete-the-fix test: remove esc_url() from either href and this assertion
	 * fails because 'href="…&action=edit"' appears instead of '&amp;action=edit'.
	 */
	public function test_calendar_edit_link_href_is_url_escaped() {
		global $edit_flow;

		$post = self::factory()->post->create_and_get(
			array(
				'post_status' => 'draft',
				'post_author' => self::$admin_user_id,
			)
		);

		// Override the edit link to contain a bare & so we can detect whether
		// esc_url() is applied (it would encode & as &amp;).
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
			'href="http://example.com/wp-admin/post.php?post=1&amp;action=edit"',
			$html,
			'Edit link href must pass through esc_url() — bare & is invalid in an HTML attribute.'
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
	 * Same pattern as the edit link: bare & in the URL should be encoded.
	 *
	 * Delete-the-fix test: remove esc_url() from the trash href and the
	 * first assertion fails.
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
			'post_delete_link',
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

		remove_all_filters( 'post_delete_link' );

		// The trash link is built with get_delete_post_link(); confirm that
		// the output contains a properly-formed href (no raw &).
		$this->assertStringNotContainsString(
			'href="http://example.com/wp-admin/post.php?post=1&action=trash&_wpnonce=abc"',
			$html,
			'Trash href must not contain unescaped &.'
		);
	}

	/**
	 * Published-post view link href must be wrapped in esc_url().
	 *
	 * get_permalink() returns a plain URL (no HTML encoding). Without esc_url()
	 * a permalink containing & (e.g. from a query string) would produce invalid
	 * HTML. We use the post_link filter to inject such a URL.
	 *
	 * Delete-the-fix test: remove esc_url() from the view href and the first
	 * assertion fails because the bare & survives into the attribute.
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
			'href="http://example.com/?p=1&amp;preview=true"',
			$html,
			'View link href must pass through esc_url() — bare & in permalink is invalid in an HTML attribute.'
		);
		$this->assertStringNotContainsString(
			'href="http://example.com/?p=1&preview=true"',
			$html,
			'Unescaped & must not appear in the view href.'
		);
	}
}
