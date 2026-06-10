<?php
/**
 * Calendar .ics subscription feed AJAX integration tests.
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
class CalendarIcsSubscriptionAjaxTest extends AjaxTestCase {

	const SECRET_META_KEY = 'ef_calendar_ics_secret';

	protected function setUp(): void {
		parent::setUp();

		global $edit_flow;
		$edit_flow->calendar->install();

		// init() registers the feed AJAX actions only after the ef_view_calendar gate, so
		// run it as a capable user (it runs per request as the logged-in user in production).
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$edit_flow->calendar->init();

		// The handler bails unless the subscription feature is enabled.
		$edit_flow->calendar->module->options->ics_subscription = 'on';
		// The module's post-type options are not loaded in the bare test harness; without a
		// supported post type the calendar query matches nothing.
		$edit_flow->calendar->module->options->post_types = array( 'post' => 'on' );
	}

	/**
	 * The unauthenticated feed handler must be registered for logged-out users, i.e. the
	 * nopriv hook must sit above the ef_view_calendar capability gate. This guards the
	 * structural root cause of the disclosure against future refactors.
	 */
	public function test_nopriv_hook_registered_for_logged_out_users(): void {
		global $edit_flow;

		wp_set_current_user( 0 );
		$edit_flow->calendar->init();

		$this->assertNotFalse(
			has_action( 'wp_ajax_nopriv_ef_calendar_ics_subscription', array( $edit_flow->calendar, 'handle_ics_subscription' ) ),
			'The .ics feed handler must be registered for logged-out users.'
		);
	}

	/**
	 * A valid per-user token returns that user's feed to a logged-out caller.
	 */
	public function test_valid_token_returns_user_feed(): void {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$secret    = 'valid-secret-token-aaaaaaaaaaaaaa';
		update_user_meta( $author_id, self::SECRET_META_KEY, $secret );
		$this->create_scheduled_post( $author_id, 'AAA_OWN_SCHEDULED' );

		$this->request_as_logged_out( get_userdata( $author_id )->user_login, $secret );
		$result = $this->dispatch_feed();

		$this->assertTrue( $result['ok'], 'A valid token should return the feed.' );
		$this->assertStringContainsString( 'AAA_OWN_SCHEDULED', $result['body'] );
	}

	/**
	 * Core regression: a leaked feed URL must not disclose other authors' unpublished posts.
	 */
	public function test_leaked_url_hides_other_authors_unpublished(): void {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$secret    = 'author-a-secret-token-bbbbbbbbbb';
		update_user_meta( $author_id, self::SECRET_META_KEY, $secret );

		$this->create_scheduled_post( $author_id, 'AAA_OWN_SCHEDULED' );
		$this->create_scheduled_post( $other_id, 'BBB_SECRET_SCHEDULED' );

		$this->request_as_logged_out( get_userdata( $author_id )->user_login, $secret );
		$result = $this->dispatch_feed();

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'AAA_OWN_SCHEDULED', $result['body'] );
		$this->assertStringNotContainsString( 'BBB_SECRET_SCHEDULED', $result['body'], "Another author's unpublished post must not leak." );
	}

	/**
	 * Privilege-correct: an editor (can edit others' posts) still sees the whole pipeline.
	 */
	public function test_editor_feed_includes_other_authors_unpublished(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$secret    = 'editor-secret-token-cccccccccccc';
		update_user_meta( $editor_id, self::SECRET_META_KEY, $secret );

		$this->create_scheduled_post( $editor_id, 'EEE_OWN_SCHEDULED' );
		$this->create_scheduled_post( $other_id, 'BBB_OTHER_SCHEDULED' );

		$this->request_as_logged_out( get_userdata( $editor_id )->user_login, $secret );
		$result = $this->dispatch_feed();

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'EEE_OWN_SCHEDULED', $result['body'] );
		$this->assertStringContainsString( 'BBB_OTHER_SCHEDULED', $result['body'] );
	}

	/**
	 * An incorrect token is rejected and emits no calendar body.
	 */
	public function test_invalid_token_rejected(): void {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		update_user_meta( $author_id, self::SECRET_META_KEY, 'the-real-secret-dddddddddddddddd' );

		$this->request_as_logged_out( get_userdata( $author_id )->user_login, 'wrong-token' );
		$result = $this->dispatch_feed();

		$this->assertFalse( $result['ok'] );
		$this->assertStringNotContainsString( 'BEGIN:VCALENDAR', $result['body'] );
	}

	/**
	 * Back-compat: legacy URLs (user_key = md5(login . site secret)) no longer validate.
	 */
	public function test_legacy_site_secret_url_rejected(): void {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$login     = get_userdata( $author_id )->user_login;
		// No per-user secret is stored; the old URL relied on a shared site secret.
		$legacy_key = md5( $login . 'legacy-site-wide-secret' );

		$this->request_as_logged_out( $login, $legacy_key );
		$result = $this->dispatch_feed();

		$this->assertFalse( $result['ok'], 'Legacy md5(site-secret) URLs must stop working.' );
	}

	/**
	 * Caller-supplied author/post_status filters are ignored on the public feed.
	 */
	public function test_request_author_and_status_filters_ignored(): void {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$secret    = 'filter-test-secret-eeeeeeeeeeee';
		update_user_meta( $author_id, self::SECRET_META_KEY, $secret );

		$this->create_scheduled_post( $author_id, 'AAA_OWN_SCHEDULED' );
		$this->create_scheduled_post( $other_id, 'BBB_SECRET_SCHEDULED' );

		$this->request_as_logged_out( get_userdata( $author_id )->user_login, $secret );
		// Attempt to widen the feed to the other author's posts.
		$_GET['author']      = (string) $other_id;
		$_GET['post_status'] = 'future';

		$result = $this->dispatch_feed();

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'AAA_OWN_SCHEDULED', $result['body'] );
		$this->assertStringNotContainsString( 'BBB_SECRET_SCHEDULED', $result['body'], 'Request filters must not widen the feed.' );
	}

	/**
	 * Per-user secrets are isolated: one user's token cannot read another user's feed.
	 */
	public function test_per_user_secrets_are_isolated(): void {
		$author_a = $this->factory->user->create( array( 'role' => 'author' ) );
		$author_b = $this->factory->user->create( array( 'role' => 'author' ) );
		update_user_meta( $author_a, self::SECRET_META_KEY, 'secret-a-ffffffffffffffffffffff' );
		update_user_meta( $author_b, self::SECRET_META_KEY, 'secret-b-gggggggggggggggggggggg' );

		// A's token presented for B's feed must be rejected.
		$this->request_as_logged_out( get_userdata( $author_b )->user_login, 'secret-a-ffffffffffffffffffffff' );
		$result = $this->dispatch_feed();

		$this->assertFalse( $result['ok'], "One user's secret must not validate another user's feed." );
	}

	/**
	 * An unknown username is rejected (and does not error on the null-user path).
	 */
	public function test_unknown_user_rejected(): void {
		$this->request_as_logged_out( 'ghost-user-does-not-exist', 'any-token' );
		$result = $this->dispatch_feed();

		$this->assertFalse( $result['ok'] );
	}

	/**
	 * Create a scheduled (future) post within the feed window.
	 *
	 * The feed renders unpublished content; in the test harness the inner WP_Query runs in
	 * non-admin mode, where scheduled posts are returned (drafts are not), so 'future' is
	 * used to exercise the unpublished-disclosure scoping faithfully.
	 *
	 * @param int    $author_id The post author.
	 * @param string $title     The post title to look for in the feed output.
	 * @return int The created post ID.
	 */
	private function create_scheduled_post( int $author_id, string $title ): int {
		return $this->factory->post->create( array(
			'post_status' => 'future',
			'post_author' => $author_id,
			'post_title'  => $title,
			'post_date'   => date( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ),
		) );
	}

	/**
	 * Simulate an unauthenticated feed request for the given login and key.
	 *
	 * @param string $login    The user_login to request the feed for.
	 * @param string $user_key The feed secret presented by the caller.
	 */
	private function request_as_logged_out( string $login, string $user_key ): void {
		wp_set_current_user( 0 );
		$_GET['user']     = $login;
		$_GET['user_key'] = $user_key;
	}

	/**
	 * Dispatch the feed action and report whether it produced a body.
	 *
	 * @return array{ok: bool, body: string}
	 */
	private function dispatch_feed(): array {
		$this->_last_response = '';
		try {
			$this->_handleAjax( 'ef_calendar_ics_subscription' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
			return array(
				'ok'   => true,
				'body' => (string) $this->_last_response,
			);
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
			return array(
				'ok'   => false,
				'body' => (string) $this->_last_response,
			);
		}
		return array(
			'ok'   => false,
			'body' => (string) $this->_last_response,
		);
	}
}
