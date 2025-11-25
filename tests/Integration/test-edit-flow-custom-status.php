<?php

class WP_Test_Edit_Flow_Custom_Status extends WP_UnitTestCase {

	protected static $admin_user_id;
	protected static $ef_custom_status;


	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$ef_custom_status = new EF_Custom_Status();
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
	 * Test that a published post post_date_gmt is not altered
	 */
	function test_insert_post_publish_respect_post_date_gmt() {
		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'publish',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertEquals( $post['post_content'], $out->post_content );
		$this->assertEquals( $post['post_title'], $out->post_title );
		$this->assertEquals( get_date_from_gmt( $post['post_date_gmt'] ), $out->post_date) ;
		$this->assertEquals( $post['post_date_gmt'], $out->post_date_gmt );
	}

	/**
	 * Test that when post is published, post_date_gmt is set to post_date
	 */
	function test_insert_post_publish_post_date_set() {
		$past_date = date( 'Y-m-d H:i:s', strtotime( '-1 second' ) );

		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'publish',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date' => $past_date,
			'post_date_gmt' => ''
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
			'post_author' => self::$admin_user_id,
			'post_status' => 'draft',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => ''
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
			'post_author' => self::$admin_user_id,
			'post_status' => 'pending',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => ''
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
			'post_author' => self::$admin_user_id,
			'post_status' => 'pitch',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date_gmt' => ''
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
		$future_date = date( 'Y-m-d H:i:s', strtotime('+1 day') );

		$post = array(
			'post_author' => self::$admin_user_id,
			'post_status' => 'draft',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date'  => $future_date,
			'post_date_gmt' => ''
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
			'post_author' => self::$admin_user_id,
			'post_status' => 'future',
			'post_content' => rand_str(),
			'post_title' => rand_str(),
			'post_date'  => $future_date,
			'post_date_gmt' => ''
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

		$found = get_sample_permalink_html( $p );
		$post = get_post( $p );
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
			'post_name' => 'baz-صورة',
			'post_author' => self::$admin_user_id
		) );

		wp_set_current_user( self::$admin_user_id );

		$pagenow = 'index.php';

		$found = get_sample_permalink_html( $p );
		$post = get_post( $p );
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
			'post_name' => 'foo-صورة',
			'post_author' => self::$admin_user_id,
		) );

		wp_set_current_user( self::$admin_user_id );

		$found = get_sample_permalink_html( $p, null, 'new_slug-صورة' );
		$post = get_post( $p );
		$message = 'Published post';

		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/' . $post->post_name . '/"', $found, $message );
		$this->assertStringContainsString( '>new_slug-صورة<', $found, $message );
	}

	public function test_fix_get_sample_permalink_should_respect_pitch_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$page = self::factory()->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Pitch Page',
			'post_status' => 'pitch',
			'post_author' => self::$admin_user_id
		) );

		$actual = get_sample_permalink( $page );
		$this->assertSame( home_url() . '/%pagename%/', $actual[0] );
		$this->assertSame( 'pitch-page', $actual[1] );
	}

	public function test_fix_get_sample_permalink_should_respect_hierarchy_of_pitch_pages() {
		$this->set_permalink_structure( '/%postname%/' );

		$parent = self::factory()->post->create( array(
			'post_type'  => 'page',
			'post_title' => 'Parent Page',
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id,
			'post_name' => 'parent-page'
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
			'post_type'  => 'page',
			'post_title' => 'Publish Parent Page',
			'post_author' => self::$admin_user_id
		) );

		$child = self::factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Child Page',
			'post_parent' => $parent,
			'post_status' => 'publish',
			'post_author' => self::$admin_user_id
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
			'post_author' => self::$admin_user_id
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
			'post_author' => self::$admin_user_id
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'page',
			'post_title' => 'Page',
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
			'post_type' => 'page',
			'post_title' => 'Page',
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
			'post_type' => 'page',
			'post_title' => 'Page',
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
			'post_type' => 'page',
			'post_title' => 'Page',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'post_type' => 'post',
			'post_title' => 'Post',
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
			'title' => 'Post title',
			'content' => 'Post content',
			'status' => 'pitch',
			'author' => self::$admin_user_id,
			'type' => 'post',
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
			'title' => 'Post title',
			'content' => 'Post content',
			'status' => 'draft',
			'author' => self::$admin_user_id,
			'type' => 'post',
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
			'title' => 'Post title',
			'content' => 'Post content',
			'status' => 'pitch',
			'author' => self::$admin_user_id,
			'type' => 'post',
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$data = $response->get_data();

		$update_request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $data['id'] ) );
		$update_request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$update_params = array(
			'title' => 'Post title new',
			'content' => 'Post content new',
			'status' => 'pitch',
			'author' => self::$admin_user_id,
			'type' => 'post',
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
			'title' => 'Post title',
			'content' => 'Post content',
			'status' => 'publish',
			'author' => self::$admin_user_id,
			'type' => 'post',
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
				'post_author' => self::$admin_user_id ,
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $p ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title' => 'Post title new',
			'content' => 'Post content new',
			'slug' => $custom_post_name,
			'status' => 'pitch',
			'author' => self::$admin_user_id,
			'type' => 'post',
		);
		$request->set_body_params( $params );
		rest_get_server()->dispatch( $request );

		$update_request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/posts/%d', $p ) );
		$update_request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$update_params = array(
			'title' => 'Post title new',
			'content' => 'Post content new',
			'status' => 'pitch',
			'author' => self::$admin_user_id,
			'type' => 'post',
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
			'title' => 'Page title',
			'content' => 'Page content',
			'status' => 'pitch',
			'author' => self::$admin_user_id,
			'type' => 'page',
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
				'title' => 'Page title new',
				'content' => 'Page content new',
				'post_status' => 'pitch',
				'post_type' => 'page',
			)
		);

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $p ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$params = array(
			'title' => 'Page title new',
			'content' => 'Page content new',
			'slug' => $custom_post_name,
			'status' => 'pitch',
		);
		$request->set_body_params( $params );
		rest_get_server()->dispatch( $request );

		$update_request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/pages/%d', $p ) );
		$update_request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$update_params = array(
			'title' => 'Page title new',
			'content' => 'Page content new',
			'status' => 'pitch',
		);
		$update_request->set_body_params( $update_params );
		$update_response = rest_get_server()->dispatch( $update_request );

		$update_data = $update_response->get_data();
		$update_post = get_post( $update_data['id'] );

		$this->assertEquals( $custom_post_name, $update_post->post_name );
	}
}
