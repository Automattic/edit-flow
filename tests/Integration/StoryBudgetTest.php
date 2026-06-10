<?php
/**
 * Story Budget integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use Yoast\WPTestUtils\WPIntegration\TestCase;

class StoryBudgetTest extends TestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	/**
	 * Test that the story budget date filter handles valid date
	 */
	public function test_story_budget_set_start_date_filter() {
		global $edit_flow;

		$user = get_user_by( 'id', self::$admin_user_id );

		wp_set_current_user( self::$admin_user_id );

		// Users filters need to be set (they're set by default)
		$edit_flow->story_budget->update_user_filters();

		$new_filters['start_date'] = '2019-12-01';

		$users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

		$this->assertEquals( '2019-12-01', $users_filters['start_date'] );
	}

	/**
	 * Test that the story budget date filter handles invalid date
	 */
	public function test_story_budget_set_start_date_filter_invalid() {
		global $edit_flow;

		$user = get_user_by( 'id', self::$admin_user_id );

		wp_set_current_user( self::$admin_user_id );

		// Users filters need to be set (they're set by default)
		$edit_flow->story_budget->update_user_filters();

		$new_filters['start_date'] = 'not a date';

		$users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

		$this->assertEquals( date( 'Y-m-d' ), $users_filters['start_date'] );
	}

	/**
	 * Test that the story budget number of days filter handles valid number of days
	 */
	public function test_story_budget_set_number_days_filter() {
		global $edit_flow;

		$user = get_user_by( 'id', self::$admin_user_id );

		wp_set_current_user( self::$admin_user_id );

		// Users filters need to be set (they're set by default)
		$edit_flow->story_budget->update_user_filters();

		$new_filters['number_days'] = 10;

		$users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

		$this->assertEquals( 10, $users_filters['number_days'] );
	}

	/**
	 * Test that the story budget number of days filter handles invalid number of days
	 */
	public function test_story_budget_set_number_days_filter_invalid() {
		global $edit_flow;

		$user = get_user_by( 'id', self::$admin_user_id );

		wp_set_current_user( self::$admin_user_id );

		// Users filters need to be set (they're set by default)
		$edit_flow->story_budget->update_user_filters();

		$new_filters['number_days'] = 'not days';

		$users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

		$this->assertEquals( 1, $users_filters['number_days'] );
	}

	/**
	 * Test that the story budget handles both valid date and number of days filters
	 */
	public function test_story_budget_set_date_and_number_days_filters() {
		global $edit_flow;

		$user = get_user_by( 'id', self::$admin_user_id );

		wp_set_current_user( self::$admin_user_id );

		// Users filters need to be set (they're set by default)
		$edit_flow->story_budget->update_user_filters();

		$new_filters['start_date']  = '2019-12-01';
		$new_filters['number_days'] = 10;

		$users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

		$this->assertEquals( 10, $users_filters['number_days'] );
		$this->assertEquals( '2019-12-01', $users_filters['start_date'] );
	}

	/**
	 * The Story Budget must not surface another user's unpublished posts to a
	 * low-privileged role that can view the budget but cannot read those posts.
	 */
	public function test_get_posts_for_term_hides_other_users_unpublished_posts() {
		global $edit_flow;
		$story_budget = $edit_flow->story_budget;

		$author_id      = self::factory()->user->create( array( 'role' => 'author' ) );
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );

		$cat_id = self::factory()->category->create( array( 'name' => 'Budget Cat' ) );
		$term   = get_term( $cat_id, 'category' );

		$now = date( 'Y-m-d H:i:s' );

		$others_draft = wp_insert_post(
			array(
				'post_author'   => $author_id,
				'post_status'   => 'draft',
				'post_title'    => 'Another author secret draft',
				'post_date'     => $now,
				'post_category' => array( $cat_id ),
			)
		);
		$own_draft    = wp_insert_post(
			array(
				'post_author'   => $contributor_id,
				'post_status'   => 'draft',
				'post_title'    => 'My own draft',
				'post_date'     => $now,
				'post_category' => array( $cat_id ),
			)
		);

		$story_budget->taxonomy_used = 'category';
		$story_budget->user_filters  = array(
			'post_status' => 'draft',
			'cat'         => '',
			'author'      => '',
			'start_date'  => date( 'Y-m-d', strtotime( '-1 day' ) ),
			'number_days' => 30,
		);

		// As the contributor: own draft is visible, the other author's draft is not.
		wp_set_current_user( $contributor_id );
		$contributor_ids = wp_list_pluck( $story_budget->get_posts_for_term( $term, $story_budget->user_filters ), 'ID' );
		$this->assertContains( $own_draft, $contributor_ids, 'Contributor should see their own draft.' );
		$this->assertNotContains( $others_draft, $contributor_ids, "Contributor must not see another user's draft." );

		// An administrator still sees the whole pipeline.
		wp_set_current_user( self::$admin_user_id );
		$admin_ids = wp_list_pluck( $story_budget->get_posts_for_term( $term, $story_budget->user_filters ), 'ID' );
		$this->assertContains( $own_draft, $admin_ids );
		$this->assertContains( $others_draft, $admin_ids );
	}

	/**
	 * Story Budget filters must only persist to user meta when submitted with a valid nonce,
	 * not on a plain page load or a crafted URL.
	 */
	public function test_filters_persist_only_with_valid_nonce() {
		global $edit_flow;
		$story_budget = $edit_flow->story_budget;

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$meta_key = 'ef_story_budget_filters';

		// A URL carrying filter params but no valid nonce must not write user meta.
		$_GET = array(
			'page'        => 'story-budget',
			'post_status' => 'draft',
		);
		$story_budget->update_user_filters();
		$this->assertEmpty( get_user_meta( $user_id, $meta_key, true ), 'Filters must not persist without a valid nonce.' );

		// A genuine filter submission carrying a valid nonce persists.
		$_GET['ef-sb-filter-nonce'] = wp_create_nonce( 'ef-story-budget-filter' );
		$story_budget->update_user_filters();
		$saved = get_user_meta( $user_id, $meta_key, true );
		$this->assertNotEmpty( $saved, 'Filters should persist when submitted with a valid nonce.' );
		$this->assertSame( 'draft', $saved['post_status'] );

		$_GET = array();
	}

	/**
	 * Test that calendar has default custom statuses
	 */
	public function test_calendar_custom_statuses() {
		global $edit_flow;

		$statuses = array_map(
			function ( $status ) {
				return $status->name;
			},
			$edit_flow->calendar->get_calendar_post_stati()
		);

		$this->assertContains( 'future', $statuses );
		$this->assertContains( 'pitch', $statuses );
	}

	/**
	 * Test that calendar can show registered status
	 */
	public function test_calendar_custom_statuses_registered() {
		global $edit_flow;

		$new_custom_status = array(
			'term' => __( 'New Custom Status' ),
			'args' => array(
				'slug'        => 'new-custom-status',
				'description' => 'description',
				'position'    => 6,
			),
		);

		$edit_flow->custom_status->add_custom_status( $new_custom_status['term'], $new_custom_status['args'] );

		$statuses = array_map(
			function ( $status ) {
				return $status->name;
			},
			$edit_flow->calendar->get_calendar_post_stati()
		);

		$this->assertContains( 'future', $statuses );
	}

	/**
	 * The author column must escape the display name, which is the one attacker-influenced value
	 * in that column, so a user whose display name contains markup cannot inject it into the
	 * Story Budget table.
	 */
	public function test_author_column_escapes_display_name() {
		global $edit_flow;

		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_update_user( array(
			'ID'           => $author_id,
			'display_name' => 'A & B <evil>',
		) );
		$post_id = self::factory()->post->create( array( 'post_author' => $author_id ) );

		$output = $edit_flow->story_budget->term_column_default( get_post( $post_id ), 'author', null );

		$this->assertStringContainsString( '&amp;', $output, 'The display name should be HTML-escaped at output.' );
		$this->assertStringNotContainsString( '<evil>', $output, 'Raw markup must not survive into the author column.' );
	}
}
