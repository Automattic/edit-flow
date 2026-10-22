<?php
/**
 * Tests for the custom status taxonomy rename.
 *
 * @package Automattic\EditFlow\Tests\Integration
 * @see https://github.com/Automattic/edit-flow/issues/905
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Custom_Status;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * Test that custom statuses no longer use the 'post_status' taxonomy name,
 * and that statuses saved under the old name are moved over.
 */
class CustomStatusTaxonomyMigrationTest extends TestCase {

	/**
	 * Custom status module instance.
	 *
	 * @var EF_Custom_Status
	 */
	protected $custom_status;

	protected function setUp(): void {
		parent::setUp();

		global $edit_flow;
		$this->custom_status = $edit_flow->custom_status;
	}

	/**
	 * Add a status, then move it back to the old taxonomy name like an older install would have it.
	 *
	 * @return int Term ID.
	 */
	private function add_legacy_status(): int {
		global $wpdb;

		$result = $this->custom_status->add_custom_status( 'Legacy Status', [ 'slug' => 'legacy-status' ] );
		$this->assertNotWPError( $result );
		$term_id = (int) $result['term_id'];

		$wpdb->update( $wpdb->term_taxonomy, [ 'taxonomy' => EF_Custom_Status::LEGACY_TAXONOMY_KEY ], [ 'term_id' => $term_id ] );
		clean_term_cache( $term_id, EF_Custom_Status::taxonomy_key );

		return $term_id;
	}

	/**
	 * Loading status terms should not run the core 'get_post_status' filter.
	 */
	public function test_loading_status_terms_does_not_fire_get_post_status_filter() {
		$this->assertNotWPError( $this->custom_status->add_custom_status( 'Filter Test', [ 'slug' => 'filter-test' ] ) );

		$calls  = 0;
		$filter = function ( $status ) use ( &$calls ) {
			++$calls;
			return $status;
		};
		add_filter( 'get_post_status', $filter );

		clean_term_cache( [], EF_Custom_Status::taxonomy_key );
		$statuses = get_terms( [
			'taxonomy'   => EF_Custom_Status::taxonomy_key,
			'hide_empty' => false,
		] );

		remove_filter( 'get_post_status', $filter );

		$this->assertIsArray( $statuses );
		$this->assertNotEmpty( $statuses );
		$this->assertSame( 0, $calls, "Status terms should not trigger the 'get_post_status' filter" );
	}

	/**
	 * Statuses under the old taxonomy name are moved to the new one.
	 */
	public function test_legacy_statuses_are_migrated() {
		$term_id = $this->add_legacy_status();
		delete_option( EF_Custom_Status::TAXONOMY_MIGRATED_OPTION );

		$this->custom_status->maybe_migrate_taxonomy();

		$term = get_term( $term_id, EF_Custom_Status::taxonomy_key );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( EF_Custom_Status::taxonomy_key, $term->taxonomy );
		$this->assertContains( 'legacy-status', wp_list_pluck( $this->custom_status->get_custom_statuses(), 'slug' ) );
		$this->assertTrue( (bool) get_option( EF_Custom_Status::TAXONOMY_MIGRATED_OPTION ) );
	}

	/**
	 * The migration does nothing once it has run.
	 */
	public function test_migration_only_runs_once() {
		global $wpdb;

		update_option( EF_Custom_Status::TAXONOMY_MIGRATED_OPTION, true );
		$term_id = $this->add_legacy_status();

		$this->custom_status->maybe_migrate_taxonomy();

		$taxonomy = $wpdb->get_var( $wpdb->prepare( "SELECT taxonomy FROM $wpdb->term_taxonomy WHERE term_id = %d", $term_id ) );
		$this->assertSame( EF_Custom_Status::LEGACY_TAXONOMY_KEY, $taxonomy );
	}
}
