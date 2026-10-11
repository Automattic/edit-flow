<?php
/**
 * Calendar quick edit AJAX integration tests.
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
class CalendarQuickEditAjaxTest extends AjaxTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $edit_flow;
		$edit_flow->calendar->install();

		// EF_Calendar::init() only registers its AJAX actions for users who can view the calendar.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$edit_flow->calendar->init();
	}

	/**
	 * Test: a request without a valid nonce is rejected.
	 */
	public function test_get_quick_edit_rejects_invalid_nonce(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$_POST['nonce']   = 'bad-nonce';
		$_POST['post_id'] = $post_id;

		$response = $this->get_json_response();

		$this->assertSame( 'error', $response['status'] );
	}

	/**
	 * Test: the details of a post are returned, with a checkbox tree for categories.
	 */
	public function test_get_quick_edit_returns_post_details(): void {
		$category_id = $this->factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Quick Edit Cat',
			)
		);
		$post_id     = $this->factory->post->create(
			array(
				'post_status'   => 'draft',
				'post_author'   => get_current_user_id(),
				'post_category' => array( $category_id ),
			)
		);

		$_POST['nonce']   = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id'] = $post_id;

		$response = $this->get_json_response();

		$this->assertSame( 'success', $response['status'], (string) $response['message'] );
		$this->assertStringContainsString( 'class="item-information"', $response['message'] );
		$this->assertStringContainsString( 'data-type="taxonomy hierarchical"', $response['message'] );
		$this->assertStringContainsString( 'categorychecklist', $response['message'] );
		$this->assertMatchesRegularExpression( '/value="' . $category_id . '"[^>]*checked/', $response['message'] );
		$this->assertStringContainsString( 'id="save-editorial-metadata"', $response['message'] );
	}

	/**
	 * Test: a taxonomy whose name contains "tax_" keeps its full name in the editable field.
	 */
	public function test_get_quick_edit_keeps_full_taxonomy_name(): void {
		register_taxonomy(
			'ef_tax_region',
			'post',
			array(
				'public'       => true,
				'hierarchical' => true,
				'label'        => 'Regions',
			)
		);
		$term_id = $this->factory->term->create( array( 'taxonomy' => 'ef_tax_region' ) );
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		wp_set_object_terms( $post_id, array( $term_id ), 'ef_tax_region' );

		$_POST['nonce']   = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id'] = $post_id;

		$response = $this->get_json_response();

		$this->assertSame( 'success', $response['status'], (string) $response['message'] );
		$this->assertStringContainsString( 'data-metadataterm="ef_tax_region"', $response['message'] );
		$this->assertStringContainsString( 'value="' . $term_id . '"', $response['message'] );
	}

	/**
	 * Test: a trashed post is not returned.
	 */
	public function test_get_quick_edit_rejects_trashed_post(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'trash' ) );

		$_POST['nonce']   = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id'] = $post_id;

		$response = $this->get_json_response();

		$this->assertSame( 'error', $response['status'] );
	}

	/**
	 * Test: a post type that is not shown on the calendar is not returned.
	 */
	public function test_get_quick_edit_rejects_unsupported_post_type(): void {
		$page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);

		$_POST['nonce']   = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id'] = $page_id;

		$response = $this->get_json_response();

		$this->assertSame( 'error', $response['status'] );
	}

	/**
	 * Test: a private post is not returned to a user who cannot read it.
	 */
	public function test_get_quick_edit_rejects_unreadable_private_post(): void {
		$admin_id = get_current_user_id();
		$post_id  = $this->factory->post->create(
			array(
				'post_status' => 'private',
				'post_author' => $admin_id,
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'contributor' ) ) );

		$_POST['nonce']   = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id'] = $post_id;

		$response = $this->get_json_response();

		$this->assertSame( 'error', $response['status'] );
	}

	/**
	 * Test: a contributor can see another user's draft, as the calendar already shows it,
	 * but gets no editable fields or edit actions.
	 */
	public function test_get_quick_edit_shows_read_only_details_to_contributor(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'contributor' ) ) );

		$_POST['nonce']   = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id'] = $post_id;

		$response = $this->get_json_response();

		$this->assertSame( 'success', $response['status'], (string) $response['message'] );
		$this->assertStringNotContainsString( 'editable-value', $response['message'] );
		$this->assertStringNotContainsString( 'save-editorial-metadata', $response['message'] );
	}

	/**
	 * Run the AJAX action and decode its JSON response.
	 *
	 * @return array{status: string, message: mixed}
	 */
	private function get_json_response(): array {
		try {
			$this->_handleAjax( 'ef_calendar_get_quick_edit' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->fail( 'Unexpected stop exception: ' . $e->getMessage() );
		}

		$response = json_decode( (string) $this->_last_response, true );
		$this->assertIsArray( $response, 'The response should be JSON.' );

		return $response;
	}
}
