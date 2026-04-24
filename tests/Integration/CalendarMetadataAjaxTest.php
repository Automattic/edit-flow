<?php
/**
 * Calendar metadata update AJAX integration tests.
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
class CalendarMetadataAjaxTest extends AjaxTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $edit_flow;
		$edit_flow->calendar->install();
		$edit_flow->calendar->init();
		$edit_flow->editorial_metadata->install();
		$edit_flow->editorial_metadata->init();
	}

	/**
	 * Test: Unknown editorial metadata slug is rejected without writing post meta.
	 */
	public function test_update_metadata_rejects_unknown_editorial_term(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $admin_id,
		) );

		$_POST['nonce']          = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id']        = $post_id;
		$_POST['metadata_type']  = 'text';
		$_POST['metadata_term']  = 'bogus-nonexistent-slug';
		$_POST['metadata_value'] = 'value';

		$response_body = $this->capture_ajax_response( 'ef_calendar_update_metadata' );

		$this->assertStringContainsString( '"status":"error"', $response_body );

		// Ensure no post meta was written with the rogue key.
		$this->assertEmpty(
			get_post_meta( $post_id, '_ef_editorial_meta_text_bogus-nonexistent-slug', true ),
			'Rogue metadata term must not create post meta.'
		);
	}

	/**
	 * Test: Unknown taxonomy name is rejected without attempting to set terms.
	 */
	public function test_update_metadata_rejects_unknown_taxonomy(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $admin_id,
		) );

		$_POST['nonce']          = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id']        = $post_id;
		$_POST['metadata_type']  = 'taxonomy';
		$_POST['metadata_term']  = 'not-a-real-taxonomy';
		$_POST['metadata_value'] = 'something';

		$response_body = $this->capture_ajax_response( 'ef_calendar_update_metadata' );

		$this->assertStringContainsString( '"status":"error"', $response_body );
	}

	/**
	 * Test: Empty metadata_term is rejected.
	 */
	public function test_update_metadata_rejects_empty_term(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $admin_id,
		) );

		$_POST['nonce']          = wp_create_nonce( 'ef-calendar-modify' );
		$_POST['post_id']        = $post_id;
		$_POST['metadata_type']  = 'text';
		$_POST['metadata_term']  = '';
		$_POST['metadata_value'] = 'value';

		$response_body = $this->capture_ajax_response( 'ef_calendar_update_metadata' );

		$this->assertStringContainsString( '"status":"error"', $response_body );
	}

	/**
	 * Capture the JSON body emitted by a print_ajax_response call.
	 *
	 * @param string $action AJAX action name.
	 */
	private function capture_ajax_response( string $action ): string {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			$this->fail( 'Unexpected stop exception: ' . $e->getMessage() );
		}

		return (string) $this->_last_response;
	}
}
