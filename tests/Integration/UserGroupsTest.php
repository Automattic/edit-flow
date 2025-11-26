<?php
/**
 * User Groups integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

class UserGroupsTest extends TestCase {

	protected static $admin_user_id;
	protected static $editor_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_user_id = $factory->user->create( array( 'role' => 'editor' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::delete_user( self::$editor_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Test that the user_groups module exists and is accessible.
	 */
	public function test_user_groups_module_exists() {
		global $edit_flow;

		$this->assertNotNull( $edit_flow->user_groups );
		$this->assertInstanceOf( 'EF_User_Groups', $edit_flow->user_groups );
	}

	/**
	 * Test creating a usergroup with valid data.
	 */
	public function test_add_usergroup_with_valid_data() {
		global $edit_flow;

		$args = array(
			'name'        => 'Test Group',
			'description' => 'A test user group',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args );

		$this->assertNotInstanceOf( 'WP_Error', $usergroup );
		$this->assertIsObject( $usergroup );
		$this->assertEquals( 'Test Group', $usergroup->name );
		$this->assertEquals( 'A test user group', $usergroup->description );
		$this->assertIsArray( $usergroup->user_ids );
		$this->assertEmpty( $usergroup->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test creating a usergroup without a name returns WP_Error.
	 */
	public function test_add_usergroup_without_name_returns_error() {
		global $edit_flow;

		$args = array(
			'description' => 'A test user group without name',
		);

		$result = $edit_flow->user_groups->add_usergroup( $args );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'invalid', $result->get_error_code() );
	}

	/**
	 * Test creating a usergroup with users.
	 */
	public function test_add_usergroup_with_users() {
		global $edit_flow;

		$args = array(
			'name'        => 'Group With Users',
			'description' => 'A group with initial users',
		);

		$user_ids  = array( self::$admin_user_id, self::$editor_user_id );
		$usergroup = $edit_flow->user_groups->add_usergroup( $args, $user_ids );

		$this->assertNotInstanceOf( 'WP_Error', $usergroup );
		$this->assertCount( 2, $usergroup->user_ids );
		$this->assertContains( self::$admin_user_id, $usergroup->user_ids );
		$this->assertContains( self::$editor_user_id, $usergroup->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test retrieving a usergroup by ID.
	 */
	public function test_get_usergroup_by_id() {
		global $edit_flow;

		$args = array(
			'name'        => 'Retrievable Group',
			'description' => 'For retrieval testing',
		);

		$created   = $edit_flow->user_groups->add_usergroup( $args );
		$retrieved = $edit_flow->user_groups->get_usergroup_by( 'id', $created->term_id );

		$this->assertIsObject( $retrieved );
		$this->assertEquals( $created->term_id, $retrieved->term_id );
		$this->assertEquals( 'Retrievable Group', $retrieved->name );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $created->term_id );
	}

	/**
	 * Test retrieving a usergroup by name.
	 */
	public function test_get_usergroup_by_name() {
		global $edit_flow;

		$args = array(
			'name'        => 'Named Group',
			'description' => 'For name retrieval testing',
		);

		$created   = $edit_flow->user_groups->add_usergroup( $args );
		$retrieved = $edit_flow->user_groups->get_usergroup_by( 'name', 'Named Group' );

		$this->assertIsObject( $retrieved );
		$this->assertEquals( $created->term_id, $retrieved->term_id );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $created->term_id );
	}

	/**
	 * Test retrieving a non-existent usergroup returns false.
	 */
	public function test_get_nonexistent_usergroup_returns_false() {
		global $edit_flow;

		$result = $edit_flow->user_groups->get_usergroup_by( 'id', 999999 );

		$this->assertFalse( $result );
	}

	/**
	 * Test updating a usergroup name.
	 */
	public function test_update_usergroup_name() {
		global $edit_flow;

		$args = array(
			'name'        => 'Original Name',
			'description' => 'Original description',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args );

		$update_args = array(
			'name' => 'Updated Name',
		);

		$updated = $edit_flow->user_groups->update_usergroup( $usergroup->term_id, $update_args );

		$this->assertNotInstanceOf( 'WP_Error', $updated );
		$this->assertEquals( 'Updated Name', $updated->name );
		// Description should remain unchanged
		$this->assertEquals( 'Original description', $updated->description );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test updating a usergroup description.
	 */
	public function test_update_usergroup_description() {
		global $edit_flow;

		$args = array(
			'name'        => 'Desc Test Group',
			'description' => 'Original description',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args );

		$update_args = array(
			'description' => 'Updated description',
		);

		$updated = $edit_flow->user_groups->update_usergroup( $usergroup->term_id, $update_args );

		$this->assertNotInstanceOf( 'WP_Error', $updated );
		$this->assertEquals( 'Updated description', $updated->description );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test updating usergroup users replaces existing users.
	 */
	public function test_update_usergroup_users() {
		global $edit_flow;

		$args = array(
			'name' => 'Users Update Group',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args, array( self::$admin_user_id ) );
		$this->assertCount( 1, $usergroup->user_ids );

		// Update with different user
		$updated = $edit_flow->user_groups->update_usergroup(
			$usergroup->term_id,
			array(),
			array( self::$editor_user_id )
		);

		$this->assertCount( 1, $updated->user_ids );
		$this->assertContains( self::$editor_user_id, $updated->user_ids );
		$this->assertNotContains( self::$admin_user_id, $updated->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test deleting a usergroup.
	 */
	public function test_delete_usergroup() {
		global $edit_flow;

		$args = array(
			'name' => 'Deletable Group',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args );
		$term_id   = $usergroup->term_id;

		$result = $edit_flow->user_groups->delete_usergroup( $term_id );

		$this->assertTrue( $result );

		// Verify it's gone
		$retrieved = $edit_flow->user_groups->get_usergroup_by( 'id', $term_id );
		$this->assertFalse( $retrieved );
	}

	/**
	 * Test adding a user to a usergroup.
	 */
	public function test_add_user_to_usergroup() {
		global $edit_flow;

		$args = array(
			'name' => 'Add User Test Group',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args );
		$this->assertEmpty( $usergroup->user_ids );

		$result = $edit_flow->user_groups->add_user_to_usergroup( self::$admin_user_id, $usergroup->term_id );

		$this->assertTrue( $result );

		// Verify user was added
		$updated = $edit_flow->user_groups->get_usergroup_by( 'id', $usergroup->term_id );
		$this->assertContains( self::$admin_user_id, $updated->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test adding a user to multiple usergroups at once.
	 */
	public function test_add_user_to_multiple_usergroups() {
		global $edit_flow;

		$group1 = $edit_flow->user_groups->add_usergroup( array( 'name' => 'Multi Group 1' ) );
		$group2 = $edit_flow->user_groups->add_usergroup( array( 'name' => 'Multi Group 2' ) );

		$result = $edit_flow->user_groups->add_user_to_usergroup(
			self::$admin_user_id,
			array( $group1->term_id, $group2->term_id )
		);

		$this->assertTrue( $result );

		// Verify user was added to both
		$updated1 = $edit_flow->user_groups->get_usergroup_by( 'id', $group1->term_id );
		$updated2 = $edit_flow->user_groups->get_usergroup_by( 'id', $group2->term_id );

		$this->assertContains( self::$admin_user_id, $updated1->user_ids );
		$this->assertContains( self::$admin_user_id, $updated2->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $group1->term_id );
		$edit_flow->user_groups->delete_usergroup( $group2->term_id );
	}

	/**
	 * Test removing a user from a usergroup.
	 */
	public function test_remove_user_from_usergroup() {
		global $edit_flow;

		$args = array(
			'name' => 'Remove User Test Group',
		);

		$usergroup = $edit_flow->user_groups->add_usergroup( $args, array( self::$admin_user_id, self::$editor_user_id ) );
		$this->assertCount( 2, $usergroup->user_ids );

		$result = $edit_flow->user_groups->remove_user_from_usergroup( self::$admin_user_id, $usergroup->term_id );

		$this->assertTrue( $result );

		// Verify user was removed but other user remains
		$updated = $edit_flow->user_groups->get_usergroup_by( 'id', $usergroup->term_id );
		$this->assertNotContains( self::$admin_user_id, $updated->user_ids );
		$this->assertContains( self::$editor_user_id, $updated->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test getting all usergroups for a specific user.
	 */
	public function test_get_usergroups_for_user() {
		global $edit_flow;

		$group1 = $edit_flow->user_groups->add_usergroup(
			array( 'name' => 'User Groups Test 1' ),
			array( self::$admin_user_id )
		);
		$group2 = $edit_flow->user_groups->add_usergroup(
			array( 'name' => 'User Groups Test 2' ),
			array( self::$admin_user_id )
		);
		$group3 = $edit_flow->user_groups->add_usergroup(
			array( 'name' => 'User Groups Test 3' ),
			array( self::$editor_user_id ) // Different user
		);

		$admin_groups = $edit_flow->user_groups->get_usergroups_for_user( self::$admin_user_id, 'ids' );

		$this->assertIsArray( $admin_groups );
		$this->assertContains( $group1->term_id, $admin_groups );
		$this->assertContains( $group2->term_id, $admin_groups );
		$this->assertNotContains( $group3->term_id, $admin_groups );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $group1->term_id );
		$edit_flow->user_groups->delete_usergroup( $group2->term_id );
		$edit_flow->user_groups->delete_usergroup( $group3->term_id );
	}

	/**
	 * Test getting usergroups for user returns objects when requested.
	 */
	public function test_get_usergroups_for_user_returns_objects() {
		global $edit_flow;

		$group = $edit_flow->user_groups->add_usergroup(
			array( 'name' => 'Object Return Test' ),
			array( self::$admin_user_id )
		);

		$groups = $edit_flow->user_groups->get_usergroups_for_user( self::$admin_user_id, 'objects' );

		$this->assertIsArray( $groups );
		$this->assertNotEmpty( $groups );
		$this->assertIsObject( $groups[0] );
		$this->assertObjectHasProperty( 'name', $groups[0] );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $group->term_id );
	}

	/**
	 * Test that user IDs are stored uniquely (no duplicates).
	 */
	public function test_usergroup_user_ids_are_unique() {
		global $edit_flow;

		$args = array(
			'name' => 'Unique Users Test',
		);

		// Add same user twice
		$usergroup = $edit_flow->user_groups->add_usergroup(
			$args,
			array( self::$admin_user_id, self::$admin_user_id, self::$admin_user_id )
		);

		$this->assertCount( 1, $usergroup->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test getting all usergroups.
	 */
	public function test_get_usergroups() {
		global $edit_flow;

		// Create some test groups
		$group1 = $edit_flow->user_groups->add_usergroup( array( 'name' => 'All Groups Test 1' ) );
		$group2 = $edit_flow->user_groups->add_usergroup( array( 'name' => 'All Groups Test 2' ) );

		$all_groups = $edit_flow->user_groups->get_usergroups();

		$this->assertIsArray( $all_groups );
		$this->assertGreaterThanOrEqual( 2, count( $all_groups ) );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $group1->term_id );
		$edit_flow->user_groups->delete_usergroup( $group2->term_id );
	}

	/**
	 * Test that usergroup slug is properly prefixed.
	 */
	public function test_usergroup_slug_is_prefixed() {
		global $edit_flow;

		$usergroup = $edit_flow->user_groups->add_usergroup( array( 'name' => 'Slug Prefix Test' ) );

		$this->assertStringStartsWith( 'ef-usergroup-', $usergroup->slug );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test adding users to usergroup by login name.
	 */
	public function test_add_user_to_usergroup_by_login() {
		global $edit_flow;

		$user      = get_user_by( 'id', self::$admin_user_id );
		$usergroup = $edit_flow->user_groups->add_usergroup( array( 'name' => 'Login Test Group' ) );

		$result = $edit_flow->user_groups->add_user_to_usergroup( $user->user_login, $usergroup->term_id );

		$this->assertTrue( $result );

		$updated = $edit_flow->user_groups->get_usergroup_by( 'id', $usergroup->term_id );
		$this->assertContains( self::$admin_user_id, $updated->user_ids );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}

	/**
	 * Test that description can contain special characters.
	 */
	public function test_usergroup_description_with_special_characters() {
		global $edit_flow;

		$description = "Test with special chars: <>&\"' and unicode: éàü";

		$usergroup = $edit_flow->user_groups->add_usergroup(
			array(
				'name'        => 'Special Chars Group',
				'description' => $description,
			)
		);

		$retrieved = $edit_flow->user_groups->get_usergroup_by( 'id', $usergroup->term_id );

		// The description should be stored and retrieved (may be sanitized)
		$this->assertNotEmpty( $retrieved->description );

		// Clean up
		$edit_flow->user_groups->delete_usergroup( $usergroup->term_id );
	}
}
