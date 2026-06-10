<?php
/**
 * Module class integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Module;
use WP_User;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class ModuleTest extends TestCase {

	protected static $admin_user_id;
	protected static $EditFlowModule;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	function _flush_roles() {
		// we want to make sure we're testing against the db, not just in-memory data
		// this will flush everything and reload it from the db
		global $wp_roles;
		if ( is_object( $wp_roles ) ) {
			$wp_roles->for_site();
		}
	}

	protected function setUp(): void {
		parent::setUp();

		self::$EditFlowModule = new EF_Module();

		$this->_flush_roles();
	}

	protected function tearDown(): void {
		self::$EditFlowModule = null;
	}

	function test_add_caps_to_role() {
		$usergroup_roles = array(
			'administrator' => array( 'edit_usergroups' ),
		);

		foreach ( $usergroup_roles as $role => $caps ) {
			self::$EditFlowModule->add_caps_to_role( $role, $caps );
		}

		$user = new WP_User( self::$admin_user_id );

		// Verify before flush
		$this->assertTrue( $user->has_cap( 'edit_usergroups' ), 'User did not have role edit_usergroups' );

		$this->_flush_roles();

		$this->assertTrue( $user->has_cap( 'edit_usergroups' ), 'User did not have role edit_usergroups' );
	}

	function test_current_post_type_post_type_set() {
		$_REQUEST['post_type'] = 'not-real';

		$this->assertEquals( 'not-real', self::$EditFlowModule->get_current_post_type() );
	}

	function test_current_post_type_post_screen() {
		set_current_screen( 'post.php' );

		$post_id = $this->factory->post->create(
			array(
				'post_author' => self::$admin_user_id,
			)
		);

		$_REQUEST['post'] = $post_id;

		$this->assertEquals( 'post', self::$EditFlowModule->get_current_post_type() );

		unset( $_REQUEST['post_type'] );
		set_current_screen( 'front' );
	}

	function test_current_post_type_edit_screen() {
		set_current_screen( 'edit.php' );

		$this->assertEquals( 'post', self::$EditFlowModule->get_current_post_type() );

		set_current_screen( 'front' );
	}

	function test_current_post_type_custom_post_type() {
		register_post_type( 'content' );
		set_current_screen( 'content' );

		$this->assertEquals( 'content', self::$EditFlowModule->get_current_post_type() );

		_unregister_post_type( 'content' );
		set_current_screen( 'front' );
	}

	/**
	 * The current user's checkbox must carry the marker class verbatim.
	 *
	 * Regression guard for PR #980: the class (and checked) attributes are
	 * built as complete HTML strings, so running them through esc_attr()
	 * entity-encodes the quotes and produces broken markup such as
	 * `class=&quot;post_following_list-current_user&quot;`, which silently
	 * drops the class the notifiedMessage() JS relies on.
	 */
	function test_users_select_form_outputs_current_user_class_unescaped() {
		wp_set_current_user( self::$admin_user_id );

		$html = $this->get_users_select_form_html( array( self::$admin_user_id ) );

		$this->assertStringContainsString(
			'class="post_following_list-current_user"',
			$html,
			'Current user checkbox is missing the marker class.'
		);
		$this->assertStringNotContainsString(
			'&quot;',
			$html,
			'Attributes were entity-encoded, indicating esc_attr() corruption.'
		);
	}

	/**
	 * A selected user's checkbox should render a working checked attribute.
	 */
	function test_users_select_form_marks_selected_user_checked() {
		wp_set_current_user( self::$admin_user_id );

		$html = $this->get_users_select_form_html( array( self::$admin_user_id ) );

		$this->assertMatchesRegularExpression(
			'/checked=([\'"])checked\1/',
			$html,
			'Selected user checkbox is not marked as checked.'
		);
	}

	/**
	 * A legitimate encoded description (a base64'd serialized array) must decode back to
	 * the original array, so the allowed_classes hardening does not regress normal storage.
	 */
	function test_get_unencoded_description_round_trips_array() {
		$data = array(
			'description' => "O'Brien & co <stuff>",
			'position'    => 3,
			'viewable'    => false,
		);

		$encoded = self::$EditFlowModule->get_encoded_description( $data );
		$decoded = self::$EditFlowModule->get_unencoded_description( $encoded );

		$this->assertSame( $data, $decoded, 'A legitimate encoded array should round-trip unchanged.' );
	}

	/**
	 * A crafted term description containing a serialized object must never be instantiated as
	 * that class: unserialisation here forbids objects, so PHP object injection cannot fire.
	 */
	function test_get_unencoded_description_blocks_object_injection() {
		// A serialized stdClass object (O:8:"stdClass":0:{}) standing in for any gadget payload.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Building a hostile payload for the test.
		$payload = base64_encode( 'O:8:"stdClass":0:{}' );

		$decoded = self::$EditFlowModule->get_unencoded_description( $payload );

		// allowed_classes => false turns any object into an inert __PHP_Incomplete_Class, so it is
		// never a real instance of the named class. Without the hardening this would be a stdClass.
		$this->assertNotInstanceOf( \stdClass::class, $decoded, 'A serialized object must not be instantiated as its class.' );
	}

	/**
	 * Capture the echoed output of users_select_form().
	 *
	 * @param array $selected User IDs to mark as selected.
	 * @return string Rendered HTML.
	 */
	private function get_users_select_form_html( array $selected ): string {
		ob_start();
		self::$EditFlowModule->users_select_form( $selected );
		return (string) ob_get_clean();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}
}
