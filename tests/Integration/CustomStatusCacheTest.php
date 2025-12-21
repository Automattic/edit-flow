<?php
/**
 * Tests for Custom Status internal caching behavior.
 *
 * Verifies that:
 * 1. get_custom_statuses() doesn't corrupt WordPress's term object cache
 * 2. Cache is properly invalidated after delete/update operations
 *
 * @package Automattic\EditFlow\Tests\Integration
 * @see https://github.com/Automattic/Edit-Flow/issues/51
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Custom_Status;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test class for Custom Status caching behavior.
 *
 * @covers EF_Custom_Status::get_custom_statuses
 * @covers EF_Custom_Status::delete_custom_status
 * @covers EF_Custom_Status::update_custom_status
 */
class CustomStatusCacheTest extends TestCase {

	/**
	 * Administrator user ID for tests.
	 *
	 * @var int
	 */
	protected static $admin_user_id;

	/**
	 * Set up test fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ): void {
		self::$admin_user_id = $factory->user->create( [ 'role' => 'administrator' ] );
	}

	/**
	 * Tear down test fixtures.
	 */
	public static function wpTearDownAfterClass(): void {
		self::delete_user( self::$admin_user_id );
	}

	/**
	 * Set up each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear the internal module cache before each test.
		$this->reset_module_cache();
	}

	/**
	 * Get the custom status module from the global Edit Flow instance.
	 *
	 * @return \EF_Custom_Status
	 */
	private function get_custom_status_module(): \EF_Custom_Status {
		global $edit_flow;
		return $edit_flow->custom_status;
	}

	/**
	 * Test that get_custom_statuses() does not modify WordPress's cached term objects.
	 *
	 * When get_custom_statuses() processes terms, it adds pseudo-meta properties
	 * (like 'position'). This must not affect the original term objects in
	 * WordPress's term cache.
	 */
	public function test_get_custom_statuses_does_not_corrupt_wp_term_cache(): void {
		wp_set_current_user( self::$admin_user_id );
		$module = $this->get_custom_status_module();

		// Create a test status to work with.
		$result = $module->add_custom_status(
			'Cache Test Status',
			[
				'slug'        => 'cache-test-status',
				'description' => 'A status to test cache corruption.',
				'position'    => 99,
			]
		);
		$this->assertNotWPError( $result );
		$term_id = $result['term_id'];

		// Clear WordPress's term cache to ensure a fresh fetch.
		clean_term_cache( $term_id, EF_Custom_Status::taxonomy_key );

		// Get the term directly from WordPress.
		$test_term = get_term_by( 'slug', 'cache-test-status', EF_Custom_Status::taxonomy_key );
		$this->assertInstanceOf( \WP_Term::class, $test_term );

		// Store the original description before calling get_custom_statuses().
		$original_description = $test_term->description;

		// Verify that the term does NOT have our pseudo-meta properties initially.
		$this->assertObjectNotHasProperty( 'position', $test_term );

		// Reset the module's internal cache.
		$this->reset_module_cache();

		// Call get_custom_statuses() which processes terms and adds pseudo-meta.
		$statuses = $module->get_custom_statuses();

		// Find the test status in the returned array.
		$test_status = null;
		foreach ( $statuses as $status ) {
			if ( 'cache-test-status' === $status->slug ) {
				$test_status = $status;
				break;
			}
		}
		$this->assertNotNull( $test_status, 'Test status should be in the returned statuses' );

		// The returned status should have the position property.
		$this->assertObjectHasProperty( 'position', $test_status );

		// Now get the term from WordPress's cache again.
		$test_term_after = get_term_by( 'slug', 'cache-test-status', EF_Custom_Status::taxonomy_key );

		// The WordPress-cached term should NOT have position property.
		// If it does, it means get_custom_statuses() corrupted the cache.
		$this->assertObjectNotHasProperty(
			'position',
			$test_term_after,
			'get_custom_statuses() should not add properties to WordPress term cache objects'
		);

		// The description should be unchanged (still base64 encoded).
		$this->assertSame(
			$original_description,
			$test_term_after->description,
			'get_custom_statuses() should not modify term description in WordPress cache'
		);

		// Clean up.
		$module->delete_custom_status( $term_id );
	}

	/**
	 * Test that calling get_custom_statuses() multiple times returns consistent results.
	 *
	 * The first call should cache results internally; subsequent calls should
	 * return the same data from the internal cache.
	 */
	public function test_get_custom_statuses_returns_consistent_results_across_calls(): void {
		$module = $this->get_custom_status_module();

		// First call populates the internal cache.
		$first_call = $module->get_custom_statuses();

		// Second call should use the internal cache.
		$second_call = $module->get_custom_statuses();

		// Both should return the same number of statuses.
		$this->assertCount( count( $first_call ), $second_call );

		// Compare each status.
		foreach ( $first_call as $key => $status ) {
			$this->assertEquals(
				$status->slug,
				$second_call[ $key ]->slug,
				'Status slugs should match across calls'
			);
			$this->assertEquals(
				$status->name,
				$second_call[ $key ]->name,
				'Status names should match across calls'
			);
		}
	}

	/**
	 * Test that delete_custom_status() properly invalidates the internal cache.
	 *
	 * After deleting a status, subsequent calls to get_custom_statuses() should
	 * NOT include the deleted status.
	 */
	public function test_delete_custom_status_invalidates_cache(): void {
		wp_set_current_user( self::$admin_user_id );
		$module = $this->get_custom_status_module();

		// Create a temporary status to delete.
		$result = $module->add_custom_status(
			'Temporary Status',
			[
				'slug'        => 'temporary-status',
				'description' => 'A status to test deletion cache invalidation.',
			]
		);

		$this->assertNotWPError( $result );
		$term_id = $result['term_id'];

		// Populate the internal cache by calling get_custom_statuses().
		$statuses_before = $module->get_custom_statuses();

		// Verify the temporary status exists in the cache.
		$found_before = false;
		foreach ( $statuses_before as $status ) {
			if ( 'temporary-status' === $status->slug ) {
				$found_before = true;
				break;
			}
		}
		$this->assertTrue( $found_before, 'Temporary status should exist before deletion' );

		// Delete the status.
		$delete_result = $module->delete_custom_status( $term_id );
		$this->assertTrue( $delete_result );

		// Get statuses again - this should return fresh data without the deleted status.
		$statuses_after = $module->get_custom_statuses();

		// Verify the deleted status is NOT in the cache.
		$found_after = false;
		foreach ( $statuses_after as $status ) {
			if ( 'temporary-status' === $status->slug ) {
				$found_after = true;
				break;
			}
		}
		$this->assertFalse(
			$found_after,
			'Deleted status should not appear in get_custom_statuses() after deletion'
		);
	}

	/**
	 * Test that update_custom_status() properly invalidates the internal cache.
	 *
	 * After updating a status name, subsequent calls to get_custom_statuses()
	 * should return the updated name.
	 */
	public function test_update_custom_status_invalidates_cache(): void {
		wp_set_current_user( self::$admin_user_id );
		$module = $this->get_custom_status_module();

		// Create a temporary status to update.
		$result = $module->add_custom_status(
			'Original Name',
			[
				'slug'        => 'update-test-status',
				'description' => 'A status to test update cache invalidation.',
			]
		);

		$this->assertNotWPError( $result );
		$term_id = $result['term_id'];

		// Populate the internal cache.
		$statuses_before = $module->get_custom_statuses();

		// Find the status and verify original name.
		$original_name = null;
		foreach ( $statuses_before as $status ) {
			if ( 'update-test-status' === $status->slug ) {
				$original_name = $status->name;
				break;
			}
		}
		$this->assertSame( 'Original Name', $original_name );

		// Update the status name.
		$update_result = $module->update_custom_status(
			$term_id,
			[ 'name' => 'Updated Name' ]
		);
		$this->assertNotWPError( $update_result );

		// Get statuses again - should return the updated name.
		$statuses_after = $module->get_custom_statuses();

		// Verify the updated name is returned.
		$updated_name = null;
		foreach ( $statuses_after as $status ) {
			if ( 'updated-name' === $status->slug ) { // Slug changes when name changes.
				$updated_name = $status->name;
				break;
			}
		}
		$this->assertSame(
			'Updated Name',
			$updated_name,
			'get_custom_statuses() should return updated name after update_custom_status()'
		);

		// Clean up.
		$module->delete_custom_status( $term_id );
	}

	/**
	 * Reset the module's internal cache for testing.
	 */
	private function reset_module_cache(): void {
		$module = $this->get_custom_status_module();

		// Use reflection to reset the private cache property.
		$reflection = new \ReflectionClass( $module );
		$property   = $reflection->getProperty( 'custom_statuses_cache' );
		$property->setAccessible( true );
		$property->setValue( $module, [] );
	}
}
