<?php
/**
 * Tests for user email privacy features.
 *
 * @package EditFlow
 * @subpackage Tests
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test class for user email privacy.
 *
 * @covers EF_Module::users_select_form
 * @covers EF_Editorial_Comments::the_comment
 */
class UserPrivacyTest extends TestCase {

	/**
	 * Test user for assertions.
	 *
	 * @var \WP_User
	 */
	private static $test_user;

	/**
	 * Set up test fixtures.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		self::$test_user = self::factory()->user->create_and_get(
			[
				'role'         => 'editor',
				'display_name' => 'Test Editor',
				'user_login'   => 'testeditor',
				'user_email'   => 'testeditor@example.com',
			]
		);
	}

	/**
	 * Test that users_select_form shows user_nicename by default instead of email.
	 */
	public function test_users_select_form_shows_nicename_by_default(): void {
		global $edit_flow;

		// Capture the output of users_select_form.
		ob_start();
		$edit_flow->notifications->users_select_form();
		$output = ob_get_clean();

		// Should contain the user's nicename.
		$this->assertStringContainsString( self::$test_user->user_nicename, $output );

		// Should NOT contain the user's email.
		$this->assertStringNotContainsString( self::$test_user->user_email, $output );
	}

	/**
	 * Test that ef_user_secondary_identifier filter can restore email display.
	 */
	public function test_users_select_form_filter_can_show_email(): void {
		global $edit_flow;

		// Add filter to show email instead of nicename.
		add_filter(
			'ef_user_secondary_identifier',
			function ( $identifier, $user ) {
				return $user->user_email;
			},
			10,
			2
		);

		// Capture the output.
		ob_start();
		$edit_flow->notifications->users_select_form();
		$output = ob_get_clean();

		// Should contain the user's email.
		$this->assertStringContainsString( self::$test_user->user_email, $output );

		// Clean up.
		remove_all_filters( 'ef_user_secondary_identifier' );
	}

	/**
	 * Test that ef_user_secondary_identifier filter can hide all secondary info.
	 */
	public function test_users_select_form_filter_can_hide_identifier(): void {
		global $edit_flow;

		// Add filter to hide secondary identifier.
		add_filter( 'ef_user_secondary_identifier', '__return_empty_string' );

		// Capture the output.
		ob_start();
		$edit_flow->notifications->users_select_form();
		$output = ob_get_clean();

		// Should NOT contain the ef-user_useremail span at all.
		$this->assertStringNotContainsString( 'ef-user_useremail', $output );

		// Clean up.
		remove_all_filters( 'ef_user_secondary_identifier' );
	}
}
