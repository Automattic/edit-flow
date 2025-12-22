<?php
/**
 * Tests for status migration functionality.
 *
 * @package Automattic\EditFlow\Tests\Integration
 * @see https://github.com/Automattic/edit-flow/issues/230
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test status migration functionality.
 *
 * Tests both the get_post_count_for_status() helper and the
 * reassign_post_status() migration method.
 */
class StatusMigrationTest extends TestCase {

	protected static $admin_user_id;

	/**
	 * Custom status module instance.
	 *
	 * @var \EF_Custom_Status
	 */
	protected $custom_status;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );

		global $edit_flow;
		$this->custom_status = $edit_flow->custom_status;
	}

	/**
	 * Test get_post_count_for_status returns correct count.
	 */
	public function test_get_post_count_for_status_returns_correct_count() {
		// Create posts with 'pitch' status.
		$this->factory()->post->create( array( 'post_status' => 'pitch' ) );
		$this->factory()->post->create( array( 'post_status' => 'pitch' ) );
		$this->factory()->post->create( array( 'post_status' => 'pitch' ) );

		$count = $this->custom_status->get_post_count_for_status( 'pitch' );

		$this->assertEquals( 3, $count, 'Should count 3 posts with pitch status' );
	}

	/**
	 * Test get_post_count_for_status returns zero for empty status.
	 */
	public function test_get_post_count_for_status_returns_zero_for_empty() {
		$count = $this->custom_status->get_post_count_for_status( 'nonexistent-status' );

		$this->assertEquals( 0, $count, 'Should return 0 for status with no posts' );
	}

	/**
	 * Test reassign_post_status migrates posts correctly.
	 */
	public function test_reassign_post_status_migrates_posts() {
		// Create posts with 'pitch' status.
		$post_id_1 = $this->factory()->post->create( array( 'post_status' => 'pitch' ) );
		$post_id_2 = $this->factory()->post->create( array( 'post_status' => 'pitch' ) );

		// Migrate from pitch to draft.
		$this->custom_status->reassign_post_status( 'pitch', 'draft' );

		// Clear caches.
		clean_post_cache( $post_id_1 );
		clean_post_cache( $post_id_2 );

		// Verify migration.
		$this->assertEquals( 'draft', get_post_status( $post_id_1 ), 'Post 1 should be migrated to draft' );
		$this->assertEquals( 'draft', get_post_status( $post_id_2 ), 'Post 2 should be migrated to draft' );
	}

	/**
	 * Test reassign_post_status only affects matching status.
	 */
	public function test_reassign_post_status_only_affects_matching_status() {
		// Create posts with different statuses.
		$pitch_post    = $this->factory()->post->create( array( 'post_status' => 'pitch' ) );
		$assigned_post = $this->factory()->post->create( array( 'post_status' => 'assigned' ) );
		$draft_post    = $this->factory()->post->create( array( 'post_status' => 'draft' ) );

		// Migrate only pitch to pending.
		$this->custom_status->reassign_post_status( 'pitch', 'pending' );

		// Clear caches.
		clean_post_cache( $pitch_post );
		clean_post_cache( $assigned_post );
		clean_post_cache( $draft_post );

		// Verify only pitch was migrated.
		$this->assertEquals( 'pending', get_post_status( $pitch_post ), 'Pitch post should be migrated to pending' );
		$this->assertEquals( 'assigned', get_post_status( $assigned_post ), 'Assigned post should remain unchanged' );
		$this->assertEquals( 'draft', get_post_status( $draft_post ), 'Draft post should remain unchanged' );
	}

	/**
	 * Test reassign_post_status uses default when no target specified.
	 */
	public function test_reassign_post_status_uses_default_when_no_target() {
		// Get the default status first.
		$default_status = $this->custom_status->get_default_custom_status();

		// Skip if no default status is set.
		if ( ! $default_status ) {
			$this->markTestSkipped( 'No default custom status is set in the test environment.' );
		}

		// Create post with 'assigned' status.
		$post_id = $this->factory()->post->create( array( 'post_status' => 'assigned' ) );

		// Migrate without specifying target (should use default).
		$this->custom_status->reassign_post_status( 'assigned' );

		// Clear cache.
		clean_post_cache( $post_id );

		$this->assertEquals(
			$default_status->slug,
			get_post_status( $post_id ),
			'Post should be migrated to default status when no target specified'
		);
	}

	/**
	 * Test migration works with core statuses.
	 */
	public function test_reassign_post_status_works_with_core_statuses() {
		// Create a draft post.
		$post_id = $this->factory()->post->create( array( 'post_status' => 'draft' ) );

		// Migrate to pending.
		$this->custom_status->reassign_post_status( 'draft', 'pending' );

		// Clear cache.
		clean_post_cache( $post_id );

		$this->assertEquals( 'pending', get_post_status( $post_id ), 'Draft post should be migrated to pending' );
	}

	/**
	 * Test migration from custom to core status.
	 */
	public function test_reassign_custom_to_core_status() {
		// Create post with custom status.
		$post_id = $this->factory()->post->create( array( 'post_status' => 'in-progress' ) );

		// Migrate to core draft status.
		$this->custom_status->reassign_post_status( 'in-progress', 'draft' );

		// Clear cache.
		clean_post_cache( $post_id );

		$this->assertEquals( 'draft', get_post_status( $post_id ), 'Custom status post should be migrated to core draft' );
	}

	/**
	 * Test get_post_count_for_status counts all post types.
	 */
	public function test_get_post_count_for_status_counts_all_post_types() {
		// Create posts of different types with same status.
		$this->factory()->post->create( array( 'post_status' => 'pitch', 'post_type' => 'post' ) );
		$this->factory()->post->create( array( 'post_status' => 'pitch', 'post_type' => 'page' ) );

		$count = $this->custom_status->get_post_count_for_status( 'pitch' );

		$this->assertEquals( 2, $count, 'Should count posts of all types' );
	}

	/**
	 * Test handle_migrate_status requires nonce.
	 */
	public function test_handle_migrate_status_requires_nonce() {
		$_POST['action']       = 'migrate';
		$_GET['page']          = $this->custom_status->module->settings_slug;
		$_POST['migrate_from'] = 'pitch';
		$_POST['migrate_to']   = 'draft';
		// No nonce set.

		$this->expectException( \WPDieException::class );
		$this->custom_status->handle_migrate_status();
	}

	/**
	 * Test handle_migrate_status requires capability.
	 */
	public function test_handle_migrate_status_requires_capability() {
		// Switch to a user without manage_options.
		$subscriber_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_POST['action']       = 'migrate';
		$_GET['page']          = $this->custom_status->module->settings_slug;
		$_POST['migrate_from'] = 'pitch';
		$_POST['migrate_to']   = 'draft';
		$_POST['_wpnonce']     = wp_create_nonce( 'custom-status-migrate-nonce' );

		$this->expectException( \WPDieException::class );
		$this->custom_status->handle_migrate_status();
	}
}
