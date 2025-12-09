<?php
/**
 * Editorial Comments integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Editorial_Comments;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class EditorialCommentsTest extends TestCase {

	protected static $admin_user_id;
	protected static $editor_user_id;
	protected static $contributor_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id       = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_user_id      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$contributor_user_id = $factory->user->create( array( 'role' => 'contributor' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::delete_user( self::$editor_user_id );
		self::delete_user( self::$contributor_user_id );
	}

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::$admin_user_id );
	}

	/**
	 * Test that the editorial_comments module exists and is accessible.
	 */
	public function test_editorial_comments_module_exists() {
		global $edit_flow;

		$this->assertNotNull( $edit_flow->editorial_comments );
		$this->assertInstanceOf( EF_Editorial_Comments::class, $edit_flow->editorial_comments );
	}

	/**
	 * Test that the editorial comment type constant is defined.
	 */
	public function test_comment_type_constant() {
		$this->assertEquals( 'editorial-comment', EF_Editorial_Comments::comment_type );
	}

	/**
	 * Test creating an editorial comment on a post.
	 */
	public function test_insert_editorial_comment() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_content'      => 'This is an editorial comment for testing.',
			'comment_type'         => EF_Editorial_Comments::comment_type,
			'comment_approved'     => EF_Editorial_Comments::comment_type,
			'user_id'              => self::$admin_user_id,
		);

		$comment_id = wp_insert_comment( $comment_data );

		$this->assertIsInt( $comment_id );
		$this->assertGreaterThan( 0, $comment_id );

		$comment = get_comment( $comment_id );
		$this->assertEquals( EF_Editorial_Comments::comment_type, $comment->comment_type );
		$this->assertEquals( 'This is an editorial comment for testing.', $comment->comment_content );
	}

	/**
	 * Test retrieving editorial comments for a post.
	 */
	public function test_get_editorial_comments() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		// Create multiple editorial comments
		for ( $i = 1; $i <= 3; $i++ ) {
			wp_insert_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_content'      => "Editorial comment {$i}",
					'comment_type'         => EF_Editorial_Comments::comment_type,
					'comment_approved'     => EF_Editorial_Comments::comment_type,
					'user_id'              => self::$admin_user_id,
				)
			);
		}

		$editorial_comments = get_comments(
			array(
				'post_id'      => $post_id,
				'comment_type' => EF_Editorial_Comments::comment_type,
				'status'       => EF_Editorial_Comments::comment_type,
			)
		);

		$this->assertCount( 3, $editorial_comments );
	}

	/**
	 * Test that editorial comments are separate from regular comments.
	 */
	public function test_editorial_comments_separate_from_regular_comments() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		// Create a regular comment
		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Regular User',
				'comment_author_email' => 'regular@example.com',
				'comment_content'      => 'This is a regular comment.',
				'comment_type'         => '',
				'comment_approved'     => 1,
			)
		);

		// Create an editorial comment
		wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'This is an editorial comment.',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$admin_user_id,
			)
		);

		// Get only editorial comments
		$editorial_comments = get_comments(
			array(
				'post_id'      => $post_id,
				'comment_type' => EF_Editorial_Comments::comment_type,
				'status'       => EF_Editorial_Comments::comment_type,
			)
		);

		// Get only regular comments
		$regular_comments = get_comments(
			array(
				'post_id'      => $post_id,
				'comment_type' => '',
				'status'       => 'approve',
			)
		);

		$this->assertCount( 1, $editorial_comments );
		$this->assertCount( 1, $regular_comments );
		$this->assertEquals( 'This is an editorial comment.', $editorial_comments[0]->comment_content );
		$this->assertEquals( 'This is a regular comment.', $regular_comments[0]->comment_content );
	}

	/**
	 * Test threaded/nested editorial comments.
	 */
	public function test_threaded_editorial_comments() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		// Create parent comment
		$parent_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'Parent editorial comment',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$admin_user_id,
			)
		);

		// Create child comment
		$child_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'Reply to parent comment',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'comment_parent'       => $parent_comment_id,
				'user_id'              => self::$admin_user_id,
			)
		);

		$child_comment = get_comment( $child_comment_id );
		$this->assertEquals( $parent_comment_id, $child_comment->comment_parent );
	}

	/**
	 * Test storing notification list in comment meta.
	 */
	public function test_notification_list_meta() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'Comment with notification',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$admin_user_id,
			)
		);

		// Add notification list meta (as the AJAX handler does)
		$notification_list = 'John Doe, Jane Smith, and Editors group';
		add_comment_meta( $comment_id, 'notification_list', $notification_list );

		$retrieved = get_comment_meta( $comment_id, 'notification_list', true );
		$this->assertEquals( $notification_list, $retrieved );
	}

	/**
	 * Test that editorial comments are associated with correct user.
	 */
	public function test_editorial_comment_user_association() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$editor_user_id );

		wp_set_current_user( self::$editor_user_id );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'Comment from editor',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$editor_user_id,
			)
		);

		$comment = get_comment( $comment_id );
		$this->assertEquals( self::$editor_user_id, $comment->user_id );
		$this->assertEquals( $user->user_email, $comment->comment_author_email );
	}

	/**
	 * Test the allowed HTML in editorial comments.
	 */
	public function test_comment_content_allowed_html() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		// Content with allowed HTML tags (as defined in ajax_insert_comment)
		$allowed_html = array(
			'a'          => array(
				'href'  => array(),
				'title' => array(),
			),
			'b'          => array(),
			'i'          => array(),
			'strong'     => array(),
			'em'         => array(),
			'u'          => array(),
			'del'        => array(),
			'blockquote' => array(),
			'sub'        => array(),
			'sup'        => array(),
		);

		$content_with_html = 'This has <strong>bold</strong> and <em>italic</em> and <a href="https://example.com">a link</a>.';
		$sanitized_content = wp_kses( $content_with_html, $allowed_html );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => $sanitized_content,
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$admin_user_id,
			)
		);

		$comment = get_comment( $comment_id );
		// The allowed tags should be preserved
		$this->assertStringContainsString( '<strong>bold</strong>', $comment->comment_content );
		$this->assertStringContainsString( '<em>italic</em>', $comment->comment_content );
		$this->assertStringContainsString( '<a href="https://example.com">', $comment->comment_content );
	}

	/**
	 * Test that disallowed HTML is stripped from comment content.
	 */
	public function test_comment_content_strips_disallowed_html() {
		$allowed_html = array(
			'a'          => array(
				'href'  => array(),
				'title' => array(),
			),
			'b'          => array(),
			'i'          => array(),
			'strong'     => array(),
			'em'         => array(),
			'u'          => array(),
			'del'        => array(),
			'blockquote' => array(),
			'sub'        => array(),
			'sup'        => array(),
		);

		// Content with disallowed tags
		$content_with_script = 'Safe text <script>alert("xss")</script> more safe text';
		$sanitized           = wp_kses( $content_with_script, $allowed_html );

		$this->assertStringNotContainsString( '<script>', $sanitized );
		$this->assertStringContainsString( 'Safe text', $sanitized );
		$this->assertStringContainsString( 'more safe text', $sanitized );
	}

	/**
	 * Test that post types setting is respected.
	 */
	public function test_module_post_types_setting() {
		global $edit_flow;

		$supported_post_types = $edit_flow->editorial_comments->get_post_types_for_module(
			$edit_flow->editorial_comments->module
		);

		// By default, post and page should be supported
		$this->assertContains( 'post', $supported_post_types );
		$this->assertContains( 'page', $supported_post_types );
	}

	/**
	 * Test that user capability check works correctly.
	 */
	public function test_edit_post_capability_for_comments() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$admin_user_id,
			)
		);

		// Admin can edit the post
		wp_set_current_user( self::$admin_user_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );

		// Editor can edit others' posts
		wp_set_current_user( self::$editor_user_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );

		// Contributor cannot edit others' published posts
		// First let's publish the post
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( self::$contributor_user_id );
		$this->assertFalse( current_user_can( 'edit_post', $post_id ) );
	}

	/**
	 * Test editorial comment on draft post.
	 */
	public function test_editorial_comment_on_draft_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
			)
		);
		$user = get_user_by( 'id', self::$admin_user_id );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'Comment on draft post',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$admin_user_id,
			)
		);

		$this->assertIsInt( $comment_id );
		$this->assertGreaterThan( 0, $comment_id );

		$post = get_post( $post_id );
		$this->assertEquals( 'draft', $post->post_status );
	}

	/**
	 * Test editorial comment ordering.
	 */
	public function test_editorial_comments_ordering() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		// Create comments with slight time differences
		$comment_ids = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$comment_ids[] = wp_insert_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_content'      => "Comment number {$i}",
					'comment_type'         => EF_Editorial_Comments::comment_type,
					'comment_approved'     => EF_Editorial_Comments::comment_type,
					'comment_date'         => date( 'Y-m-d H:i:s', strtotime( "+{$i} minutes" ) ),
					'user_id'              => self::$admin_user_id,
				)
			);
		}

		// Get comments in ASC order (oldest first, as module does)
		$comments = get_comments(
			array(
				'post_id'      => $post_id,
				'comment_type' => EF_Editorial_Comments::comment_type,
				'status'       => EF_Editorial_Comments::comment_type,
				'orderby'      => 'comment_date',
				'order'        => 'ASC',
			)
		);

		$this->assertCount( 3, $comments );
		$this->assertStringContainsString( 'Comment number 1', $comments[0]->comment_content );
		$this->assertStringContainsString( 'Comment number 3', $comments[2]->comment_content );
	}

	/**
	 * Test deleting editorial comments.
	 */
	public function test_delete_editorial_comment() {
		$post_id = self::factory()->post->create();
		$user    = get_user_by( 'id', self::$admin_user_id );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_content'      => 'Comment to be deleted',
				'comment_type'         => EF_Editorial_Comments::comment_type,
				'comment_approved'     => EF_Editorial_Comments::comment_type,
				'user_id'              => self::$admin_user_id,
			)
		);

		$this->assertIsInt( $comment_id );

		// Delete the comment
		$result = wp_delete_comment( $comment_id, true );
		$this->assertTrue( $result );

		// Verify it's gone
		$comment = get_comment( $comment_id );
		$this->assertNull( $comment );
	}
}
