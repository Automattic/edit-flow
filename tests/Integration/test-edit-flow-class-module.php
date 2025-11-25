<?php

class WP_Test_Edit_Flow_Class_Module extends WP_UnitTestCase {

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

		//Verify before flush
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

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}
}
