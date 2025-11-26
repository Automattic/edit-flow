<?php
/**
 * Editorial Comments AJAX integration tests.
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
class EditorialCommentsAjaxTest extends AjaxTestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
	}

	/**
	 * Test: Successfully inserting an editorial comment
	 */
	public function test_insert_comment_success(): void {
		// Create author user who can edit posts
		$author_user_id = $this->factory->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_user_id );

		// Create a draft post owned by this author
		$post_id = $this->factory->post->create( array(
			'post_title'  => 'Test Post',
			'post_status' => 'draft',
			'post_author' => $author_user_id,
		) );

		// Set up the AJAX request
		$_POST['_nonce']  = wp_create_nonce( 'comment' );
		$_POST['post_id'] = $post_id;
		$_POST['parent']  = 0;
		$_POST['content'] = 'This is a test editorial comment.';

		// Ensure global variables are set properly for the AJAX handler
		global $current_user, $user_ID;
		$current_user = wp_get_current_user();
		$user_ID      = $author_user_id;

		$exception_caught = false;
		try {
			$this->_handleAjax( 'editflow_ajax_insert_comment' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected - WP_Ajax_Response::send() calls wp_die()
			$exception_caught = true;
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			// If we get a stop exception, the comment was NOT created successfully
			$this->fail( 'AJAX handler failed with error: ' . $e->getMessage() . "\nResponse: " . $this->_last_response );
		}

		$this->assertTrue( $exception_caught, 'Expected WPAjaxDieContinueException to be thrown' );

		// Verify the response contains the comment HTML
		$this->assertStringContainsString( 'This is a test editorial comment.', $this->_last_response );
		$this->assertStringContainsString( 'editorial-comment', $this->_last_response );

		// Extract comment ID from the XML response
		if ( preg_match( '/<comment id=\'(\d+)\'/', $this->_last_response, $matches ) ) {
			$comment_id = (int) $matches[1];
			$this->assertGreaterThan( 0, $comment_id, 'Comment ID should be extracted from response' );

			// Verify comment exists in database
			global $wpdb;
			$comment_exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_ID = %d AND comment_type = %s",
				$comment_id,
				'editorial-comment'
			) );

			$this->assertEquals( 1, $comment_exists, 'Comment should exist in database' );
		} else {
			$this->fail( 'Could not extract comment ID from response' );
		}
	}

	/**
	 * Test: Insert comment fails with invalid nonce
	 */
	public function test_insert_comment_invalid_nonce(): void {
		$author_user_id = $this->factory->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_user_id );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $author_user_id,
		) );

		$_POST['_nonce']  = 'invalid_nonce';
		$_POST['post_id'] = $post_id;
		$_POST['content'] = 'Test comment';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'editflow_ajax_insert_comment' );
	}

	/**
	 * Test: Insert comment fails without edit_post capability
	 */
	public function test_insert_comment_no_permission(): void {
		// Create a subscriber (cannot edit posts)
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// Create a post owned by someone else
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$post_id   = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $author_id,
		) );

		$_POST['_nonce']  = wp_create_nonce( 'comment' );
		$_POST['post_id'] = $post_id;
		$_POST['content'] = 'Test comment';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'editflow_ajax_insert_comment' );
	}

	/**
	 * Test: Insert comment fails with empty content
	 */
	public function test_insert_comment_empty_content(): void {
		$author_user_id = $this->factory->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_user_id );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $author_user_id,
		) );

		$_POST['_nonce']  = wp_create_nonce( 'comment' );
		$_POST['post_id'] = $post_id;
		$_POST['content'] = '';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'editflow_ajax_insert_comment' );
	}

	/**
	 * Test: Insert comment fails with whitespace-only content
	 */
	public function test_insert_comment_whitespace_content(): void {
		$author_user_id = $this->factory->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_user_id );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'draft',
			'post_author' => $author_user_id,
		) );

		$_POST['_nonce']  = wp_create_nonce( 'comment' );
		$_POST['post_id'] = $post_id;
		$_POST['content'] = '   ';

		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'editflow_ajax_insert_comment' );
	}
}
