<?php
/**
 * Custom Status integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use WP_REST_Request;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class CustomStatusTest extends TestCase {

	protected static $admin_user_id;
	protected static $ef_custom_status;


	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		// Use the singleton the plugin bootstraps, rather than a second, separately
		// constructed instance. A parallel instance never has its module options loaded
		// (EditFlow::load_module_options() only runs for the registered singleton), so its
		// custom-status filters silently bail and the slug-emptying behaviour is not applied.
		// On WordPress 7.0 the resulting double registration also lets core mint a slug for
		// custom-status posts. init() is idempotent for the singleton's hooks and re-runs the
		// status registration now that install() has seeded the default terms.
		global $edit_flow;
		self::$ef_custom_status = $edit_flow->custom_status;
		self::$ef_custom_status->install();
		self::$ef_custom_status->init();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
		self::$ef_custom_status = null;
	}

	protected function setUp(): void {
		parent::setUp();

		global $pagenow;
		$pagenow = 'post.php';
	}

	protected function tearDown(): void {
		global $pagenow;
		$pagenow = 'index.php';

		parent::tearDown();
	}

	/**
	 * A custom status name must be escaped when rendered into the list table's
	 * title attribute, so it cannot break out and inject markup.
	 */
	function test_list_table_column_escapes_status_name() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		set_current_screen( 'edit-post' );

		$table = new \EF_Custom_Status_List_Table();

		$item = (object) array(
			'term_id'  => 0,
			'name'     => 'Bad" onmouseover=alert(1) x="',
			'slug'     => 'bad-status',
			'position' => 0,
		);

		$output = $table->column_default( $item, 'post' );

		// The status name's double quote must be entity-encoded so it cannot close
		// the title attribute and turn the rest of the value into tag attributes.
		$this->assertStringContainsString( 'Bad&quot;', $output );
		$this->assertStringNotContainsString( 'Bad" onmouseover', $output );
		$this->assertStringContainsString( 'href="', $output );
	}

	/**
	 * Test that a published post post_date_gmt is not altered
	 */
	function test_insert_post_publish_respect_post_date_gmt() {
		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'publish',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( get_date_from_gmt( $post['post_date_gmt'] ), $out->post_date );
		$this->assertEquals( $post['post_date_gmt'], $out->post_date_gmt );
	}

	/**
	 * Test that when post is published, post_date_gmt is set to post_date
	 */
	function test_insert_post_publish_post_date_set() {
		$past_date = date( 'Y-m-d H:i:s', strtotime( '-1 second' ) );

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'publish',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date'     => $past_date,
			'post_date_gmt' => '',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, $past_date );
		$this->assertEquals( $out->post_date, $past_date );
	}


	/**
	 * Test that post_date_gmt is unset when using 'draft' status
	 */
	function test_insert_post_draft_post_date_gmt_empty() {
		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'draft',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertNotEquals( $out->post_date, '0000-00-00 00:00:00' );
	}


	/**
	 * Test that post_date_gmt is unset when using 'pending' status
	 */
	function test_insert_post_pending_post_date_gmt_unset() {
		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'pending',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertNotEquals( $out->post_date, '0000-00-00 00:00:00' );
	}

	/**
	 * Test that post_date_gmt is unset when using 'pitch' status
	 */
	function test_insert_post_pitch_post_date_gmt_unset() {
		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'pitch',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertNotEquals( $out->post_date, '0000-00-00 00:00:00' );
	}


	/**
	 * When a post_date is in the future check that post_date_gmt
	 * is not set when the status is not 'future'
	 */
	function test_insert_scheduled_post_gmt_set() {
		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 day' ) );

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'draft',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date'     => $future_date,
			'post_date_gmt' => '',
		);

		$id = wp_insert_post( $post );


		// fetch the post and make sure it matches
		$out = get_post( $id );


		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, '0000-00-00 00:00:00' );
		$this->assertEquals( $post['post_date'], $out->post_date );
	}

	/**
	 * A post with 'future' status should correctly set post_date_gmt from post_date
	 */
	function test_insert_draft_to_future_post_date_gmt_set() {
		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 day' ) );

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'future',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date'     => $future_date,
			'post_date_gmt' => '',
		);

		$id = wp_insert_post( $post );


		// fetch the post and make sure it matches
		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( $out->post_date_gmt, $future_date );
		$this->assertEquals( $post['post_date'], $out->post_date );
	}

	function test_fix_sample_permalink_html_on_pitch_when_pretty_permalinks_are_disabled() {
		global $pagenow;
		wp_set_current_user( self::$admin_user_id );

		$p = self::factory()->post->create( array(
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		) );

		$pagenow = 'index.php';

		$found   = get_sample_permalink_html( $p );
		$post    = get_post( $p );
		$message = 'Pending post';

		$preview_link = get_permalink( $post->ID );
		$preview_link = add_query_arg( 'preview', 'true', $preview_link );

		$this->assertStringContainsString( 'href="' . esc_url( $preview_link ) . '"', $found, $message );
	}

	function test_fix_sample_permalink_html_on_pitch_when_pretty_permalinks_are_enabled() {
		global $pagenow;

		$this->set_permalink_structure( '/%postname%/' );

		$p = self::factory()->post->create( array(
			'post_status' => 'pending',
			'post_name'   => 'baz-صورة',
			'post_author' => self::$admin_user_id,
		) );

		wp_set_current_user( self::$admin_user_id );

		$pagenow = 'index.php';

		$found   = get_sample_permalink_html( $p );
		$post    = get_post( $p );
		$message = 'Pending post';

		$preview_link = get_permalink( $post->ID );
		$preview_link = add_query_arg( 'preview', 'true', $preview_link );

		$this->assertStringContainsString( 'href="' . esc_url( $preview_link ) . '"', $found, $message );
	}

	function test_fix_sample_permalink_html_on_publish_when_pretty_permalinks_are_enabled() {
		$this->set_permalink_structure( '/%postname%/' );

		// Published posts should use published permalink
		$p = self::factory()->post->create( array(
			'post_status' => 'publish',
			'post_name'   => 'foo-صورة',
			'post_author' => self::$admin_user_id,
		) );

		wp_set_current_user( self::$admin_user_id );

		$found   = get_sample_permalink_html( $p, null, 'new_slug-صورة' );
		$post    = get_post( $p );
		$message = 'Published post';

		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/' . $post->post_name . '/"', $found, $message );
		$this->assertStringContainsString( '>new_slug-صورة<', $found, $message );
	}

	public function test_fix_get_sample_permalink_should_respect_pitch_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$page = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Pitch Page',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		) );

		$actual = get_sample_permalink( $page );
		$this->assertSame( home_url() . '/%pagename%/', $actual[0] );
		$this->assertSame( 'pitch-page', $actual[1] );
	}

	public function test_fix_get_sample_permalink_should_respect_hierarchy_of_pitch_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$parent = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Parent Page',
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
			'post_name'   => 'parent-page',
		) );

		$child = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Child Page',
			'post_parent' => $parent,
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		) );


		$actual = get_sample_permalink( $child );
		$this->assertSame( home_url() . '/parent-page/%pagename%/', $actual[0] );
		$this->assertSame( 'child-page', $actual[1] );
	}

	public function test_fix_get_sample_permalink_should_respect_hierarchy_of_publish_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$parent = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Publish Parent Page',
			'post_author' => self::$admin_user_id,
		) );

		$child = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Child Page',
			'post_parent' => $parent,
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
		) );

		$actual = get_sample_permalink( $child );
		$this->assertSame( home_url() . '/publish-parent-page/%pagename%/', $actual[0] );
		$this->assertSame( 'child-page', $actual[1] );
	}

	public function test_ensure_post_state_is_added() {
		$post = self::factory()->post->create( array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		) );

		$post_states = apply_filters( 'display_post_states', array(), get_post( $post ) );
		$this->assertArrayHasKey( 'pitch', $post_states );
	}

	public function test_ensure_post_state_is_skipped_for_unsupported_post_type() {
		$post = self::factory()->post->create( array(
			'post_type'   => 'customposttype',
			'post_title'  => 'Post',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		) );

		$post_states = apply_filters( 'display_post_states', array(), get_post( $post ) );
		$this->assertFalse( array_key_exists( 'pitch', $post_states ) );
	}

	public function test_ensure_post_state_is_skipped_when_filtered() {
		$post = self::factory()->post->create( array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		) );

		// Act like the status has been filtered.
		$_REQUEST['post_status'] = 'pitch';

		$post_states = apply_filters( 'display_post_states', array(), get_post( $post ) );
		$this->assertFalse( array_key_exists( 'pitch', $post_states ) );
	}

	/**
	 * When a post with a custom status is inserted, post_name should remain empty
	 */
	public function test_post_with_custom_status_post_name_not_set() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a custom status that replaces a core status is inserted, post_name should remain empty
	 */
	public function test_post_with_custom_status_replacing_core_status_post_name_not_set() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'draft',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a "scheduled" status is inserted, post_name should be set
	 */
	public function test_post_with_scheduled_status_post_name_not_set() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'future',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertNotEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a "publish" status is inserted, post_name should be set
	 */
	public function test_post_with_publish_status_post_name_is_set() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertNotEmpty( $post_inserted->post_name );
	}

	/**
	 * When a page with a custom status is inserted, post_name should remain empty
	 */
	public function test_page_with_custom_status_post_name_not_set() {
		$post = array(
			'post_type'   => 'page',
			'post_title'  => 'Page',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( $post_inserted->post_name );
	}

	/**
	 * When a page with a custom status that replaces a core status is inserted, post_name should remain empty
	 */
	public function test_page_with_custom_status_replacing_core_status_post_name_not_set() {
		$post = array(
			'post_type'   => 'page',
			'post_title'  => 'Page',
			'post_status' => 'draft',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( $post_inserted->post_name );
	}

	/**
	 * When a page with a "scheduled" status is inserted, post_name should be set
	 */
	public function test_page_with_scheduled_status_post_name_not_set() {
		$post = array(
			'post_type'   => 'page',
			'post_title'  => 'Page',
			'post_status' => 'future',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertNotEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a "publish" status is inserted, post_name should be set
	 */
	public function test_page_with_publish_status_post_name_is_set() {
		$post = array(
			'post_type'   => 'page',
			'post_title'  => 'Page',
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertNotEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a custom status is updated, post_name should remain empty
	 */
	public function test_post_with_custom_status_updated_post_name_not_set() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_insert_post( array_merge( $post, array( 'post_title' => 'New Post' ) ) );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a custom status replacing a core status is updated, post_name should remain empty
	 */
	public function test_post_with_custom_status_replacing_core_status_updated_post_name_not_set() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'draft',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_insert_post( array_merge( $post, array( 'post_title' => 'New Post' ) ) );

		wp_delete_post( $post_id, true );

		$this->assertEmpty( $post_inserted->post_name );
	}

	/**
	 * When a post with a "publish" status is updated, post_name should not change
	 */
	public function test_post_with_publish_status_updated_post_name_does_not_change() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_insert_post( array_merge( $post_inserted->to_array(), array( 'post_title' => 'New Post' ) ) );

		$post_updated = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertEquals( $post_inserted->post_name, $post_updated->post_name );
	}

	/**
	 * When a post with a "publish" status is updated and post name is explicitly set, post_name should change
	 */
	public function test_post_with_publish_status_updated_post_name_set_post_name_should_change() {
		$post = array(
			'post_type'   => 'post',
			'post_title'  => 'Post',
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
		);

		$post_id = wp_insert_post( $post );

		$post_inserted = get_post( $post_id );

		wp_insert_post( array_merge( $post_inserted->to_array(), array( 'post_name' => 'a-new-slug' ) ) );

		$post_updated = get_post( $post_id );

		wp_delete_post( $post_id, true );

		$this->assertNotEquals( $post_inserted->post_name, $post_updated->post_name );
	}

	/**
	 * When a request with the REST API is made to create a post with a custom status,
	 * the post name should not be set
	 */
	public function test_post_with_custom_status_post_name_not_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Post title',
			'content' => 'Post content',
			'status'  => 'pitch',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$post = get_post( $data['id'] );

		$this->assertEmpty( $post->post_name );
	}

	/**
	 * When a request with the REST API is made to create a post with a custom status that replaces a core status,
	 * the post name should not be set
	 */
	public function test_post_with_custom_status_replacing_core_status_post_name_not_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Post title',
			'content' => 'Post content',
			'status'  => 'draft',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$post = get_post( $data['id'] );

		$this->assertEmpty( $post->post_name );
	}

	/**
	 * When a request with the REST API is made to update a post with a custom status,
	 * the post name should not be set
	 */
	public function test_post_with_custom_status_updated_post_name_not_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Post title',
			'content' => 'Post content',
			'status'  => 'pitch',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$update_request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $data['id'] ) );
		$update_request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$update_params = array(
			'title'   => 'Post title new',
			'content' => 'Post content new',
			'status'  => 'pitch',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);

		$update_request->set_body_params( $update_params );
		$update_response = rest_get_server()->dispatch( $update_request );

		$updated_data = $update_response->get_data();
		$updated_post = get_post( $updated_data['id'] );

		$this->assertEmpty( $updated_post->post_name );
	}

	/**
	 * When a request with the REST API is made to create a post with a "publish" status,
	 * the post name should be set
	 */
	public function test_post_with_publish_status_post_name_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Post title',
			'content' => 'Post content',
			'status'  => 'publish',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);

		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$post = get_post( $data['id'] );

		$this->assertNotEmpty( $post->post_name );
	}

	/**
	 * When a request with the REST API is made to create a post with a custom status, and the the post_name is set,
	 * if the post is updated the post_name should remain the same
	 */
	public function test_post_with_custom_status_set_post_name_stays_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$custom_post_name = 'a-post-name';

		$p = self::factory()->post->create(
			array(
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $p ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Post title new',
			'content' => 'Post content new',
			'slug'    => $custom_post_name,
			'status'  => 'pitch',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);
		$request->set_body_params( $params );
		rest_get_server()->dispatch( $request );

		$update_request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $p ) );
		$update_request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$update_params = array(
			'title'   => 'Post title new',
			'content' => 'Post content new',
			'status'  => 'pitch',
			'author'  => self::$admin_user_id,
			'type'    => 'post',
		);
		$update_request->set_body_params( $update_params );
		$update_response = rest_get_server()->dispatch( $update_request );

		$update_data = $update_response->get_data();
		$update_post = get_post( $update_data['id'] );

		$this->assertEquals( $custom_post_name, $update_post->post_name );
	}

	/**
	 * When a request with the REST API is made to create a page with a custom status,
	 * the page name should not be set
	 */
	public function test_page_with_custom_status_post_name_not_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/pages' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Page title',
			'content' => 'Page content',
			'status'  => 'pitch',
			'author'  => self::$admin_user_id,
			'type'    => 'page',
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$post = get_post( $data['id'] );

		$this->assertEmpty( $post->post_name );
	}

	/**
	 * When a request with the REST API is made to create a page with a custom status, and the the post_name is set,
	 * if the page is updated the post_name should remain the same
	 */
	public function test_page_with_custom_status_set_post_name_stays_set_rest_api() {
		wp_set_current_user( self::$admin_user_id );

		$custom_post_name = 'a-page-name';

		$p = self::factory()->post->create(
			array(
				'title'       => 'Page title new',
				'content'     => 'Page content new',
				'post_status' => 'pitch',
				'post_type'   => 'page',
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $p ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title'   => 'Page title new',
			'content' => 'Page content new',
			'slug'    => $custom_post_name,
			'status'  => 'pitch',
		);
		$request->set_body_params( $params );
		rest_get_server()->dispatch( $request );

		$update_request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $p ) );
		$update_request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$update_params = array(
			'title'   => 'Page title new',
			'content' => 'Page content new',
			'status'  => 'pitch',
		);
		$update_request->set_body_params( $update_params );
		$update_response = rest_get_server()->dispatch( $update_request );

		$update_data = $update_response->get_data();
		$update_post = get_post( $update_data['id'] );

		$this->assertEquals( $custom_post_name, $update_post->post_name );
	}

	/**
	 * The status-migration allow-list must cover every core status and every registered
	 * custom status, while rejecting anything that is not a real status. This is what the
	 * migration handler validates the target against before reassigning posts.
	 */
	public function test_get_all_valid_statuses_includes_core_and_custom() {
		$valid = self::$ef_custom_status->get_all_valid_statuses();

		foreach ( array( 'publish', 'pending', 'draft', 'private', 'trash', 'future' ) as $core_status ) {
			$this->assertContains( $core_status, $valid, "Core status '$core_status' should be a valid target." );
		}

		// 'pitch' is one of the default custom statuses installed in wpSetUpBeforeClass().
		$this->assertContains( 'pitch', $valid, 'A registered custom status should be a valid target.' );

		$this->assertNotContains( 'definitely-not-a-status', $valid, 'An unknown status must not be a valid target.' );
	}

	/**
	 * The publish-timestamp workaround writes directly to the posts table, so it must not act
	 * on a post.php request that lacks a valid edit nonce; otherwise a forged request could
	 * flip another user's post to 'pending'. A properly nonced request still works.
	 */
	public function test_check_timestamp_on_publish_requires_valid_nonce() {
		global $pagenow, $typenow, $wpdb;

		wp_set_current_user( self::$admin_user_id );

		$pagenow = 'post.php';
		$typenow = 'post';

		// Custom statuses must be active for this post type, or the workaround bails early.
		if ( ! is_object( self::$ef_custom_status->module->options ) ) {
			self::$ef_custom_status->module->options = new \stdClass();
		}
		self::$ef_custom_status->module->options->post_types = array(
			'post' => 'on',
			'page' => 'on',
		);

		$post_id = self::factory()->post->create( array(
			'post_status' => 'draft',
			'post_author' => self::$admin_user_id,
		) );

		// The workaround only fires when post_date_gmt is unset, so guarantee that state.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test setup.
		$wpdb->update( $wpdb->posts, array( 'post_date_gmt' => '0000-00-00 00:00:00' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		$_POST['publish'] = '1';
		$_POST['post_ID'] = (string) $post_id;

		// Without a nonce the handler must leave the post untouched.
		unset( $_POST['_wpnonce'] );
		self::$ef_custom_status->check_timestamp_on_publish();
		$this->assertSame( 'draft', get_post_status( $post_id ), 'A request with no nonce must not change the status.' );

		// With the correct post-edit nonce the workaround applies as before.
		$_POST['_wpnonce'] = wp_create_nonce( 'update-post_' . $post_id );
		self::$ef_custom_status->check_timestamp_on_publish();
		$this->assertSame( 'pending', get_post_status( $post_id ), 'A correctly nonced request should apply the workaround.' );

		$_POST = array();
	}

	/**
	 * Capture the page template hierarchy candidates for the current main query.
	 *
	 * @return string[] The ordered list of candidate template file names.
	 */
	private function capture_page_template_candidates() {
		$candidates = array();
		$callback   = function ( $templates ) use ( &$candidates ) {
			$candidates = $templates;
			return $templates;
		};
		add_filter( 'page_template_hierarchy', $callback, 99 );
		get_page_template();
		remove_filter( 'page_template_hierarchy', $callback, 99 );

		return $candidates;
	}

	/**
	 * The fix_preview_template() method should be hooked to template_redirect.
	 */
	public function test_fix_preview_template_is_registered() {
		$this->assertNotFalse(
			has_action( 'template_redirect', array( self::$ef_custom_status, 'fix_preview_template' ) )
		);
	}

	/**
	 * When previewing a custom-status page, the slug-specific template should be a
	 * candidate even though the stored post_name is empty.
	 */
	public function test_fix_preview_template_adds_slug_template_for_custom_status_page() {
		wp_set_current_user( self::$admin_user_id );

		$id = self::factory()->post->create(
			array(
				'post_title'  => 'My Test Page',
				'post_type'   => 'page',
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
			)
		);

		// Edit Flow keeps the slug empty for custom statuses.
		$this->assertEmpty( get_post( $id )->post_name );

		$this->go_to( '/?page_id=' . $id . '&preview_id=' . $id );

		// Without the fix, only page-{id}.php and page.php would be candidates.
		$this->assertNotContains( 'page-my-test-page.php', $this->capture_page_template_candidates() );

		self::$ef_custom_status->fix_preview_template();

		$this->assertContains( 'page-my-test-page.php', $this->capture_page_template_candidates() );
	}

	/**
	 * The fix should also help the "draft" custom status, where the empty slug is
	 * actually WordPress core's own behaviour for draft/pending posts.
	 */
	public function test_fix_preview_template_adds_slug_template_for_draft_status_page() {
		wp_set_current_user( self::$admin_user_id );

		$id = self::factory()->post->create(
			array(
				'post_title'  => 'My Test Page',
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_author' => self::$admin_user_id,
			)
		);

		$this->assertEmpty( get_post( $id )->post_name );

		$this->go_to( '/?page_id=' . $id . '&preview_id=' . $id );
		self::$ef_custom_status->fix_preview_template();

		$this->assertContains( 'page-my-test-page.php', $this->capture_page_template_candidates() );
	}

	/**
	 * The synthesised slug is in-memory only and must never be persisted.
	 */
	public function test_fix_preview_template_does_not_persist_slug() {
		wp_set_current_user( self::$admin_user_id );

		$id = self::factory()->post->create(
			array(
				'post_title'  => 'My Test Page',
				'post_type'   => 'page',
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
			)
		);

		$this->go_to( '/?page_id=' . $id . '&preview_id=' . $id );
		self::$ef_custom_status->fix_preview_template();

		// The in-memory queried object carries the synthesised slug...
		$this->assertSame( 'my-test-page', get_queried_object()->post_name );

		// ...but nothing is written to the database.
		clean_post_cache( $id );
		$this->assertEmpty( get_post( $id )->post_name );
	}

	/**
	 * A published page already has a real slug and must be left untouched.
	 */
	public function test_fix_preview_template_leaves_published_page_untouched() {
		wp_set_current_user( self::$admin_user_id );

		$id = self::factory()->post->create(
			array(
				'post_title'  => 'My Test Page',
				'post_name'   => 'a-real-slug',
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_author' => self::$admin_user_id,
			)
		);

		$this->go_to( '/?page_id=' . $id );
		self::$ef_custom_status->fix_preview_template();

		$this->assertSame( 'a-real-slug', get_queried_object()->post_name );
	}

	/**
	 * The ef_preview_template_post_name filter can return '' to opt out.
	 */
	public function test_fix_preview_template_respects_opt_out_filter() {
		wp_set_current_user( self::$admin_user_id );

		$id = self::factory()->post->create(
			array(
				'post_title'  => 'My Test Page',
				'post_type'   => 'page',
				'post_status' => 'pitch',
				'post_author' => self::$admin_user_id,
			)
		);

		$this->go_to( '/?page_id=' . $id . '&preview_id=' . $id );

		add_filter( 'ef_preview_template_post_name', '__return_empty_string' );
		self::$ef_custom_status->fix_preview_template();
		remove_filter( 'ef_preview_template_post_name', '__return_empty_string' );

		$this->assertEmpty( get_queried_object()->post_name );
		$this->assertNotContains( 'page-my-test-page.php', $this->capture_page_template_candidates() );
	}
}
