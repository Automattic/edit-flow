<?php

class WP_Test_Edit_Flow_Editorial_Metadata extends WP_UnitTestCase {

	protected static $admin_user_id;
	protected static $EF_Editorial_Metadata;

	public static function wpSetUpBeforeClass( $factory ) {
		global $edit_flow;

		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );

		$edit_flow->editorial_metadata->install();
		$edit_flow->editorial_metadata->init();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	/**
	* Test that editorial metadata for date is saved
	*/
	function test_save_metabox_with_date() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );
		$_POST[ EF_Editorial_Metadata::metadata_taxonomy . '_nonce' ] = wp_create_nonce( 'ef-save-metabox' );
		$first_draft_date_term                                        = $edit_flow->editorial_metadata->get_editorial_metadata_term_by( 'slug', 'first-draft-date' );
		$ef_first_draft_date_key                                      = $edit_flow->editorial_metadata->get_postmeta_key( $first_draft_date_term );

		$_POST[ $ef_first_draft_date_key ] = '2019-01-02 01:00:00';

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'publish',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		$id = wp_insert_post( $post );

		$first_draft_date_value = $edit_flow->editorial_metadata->get_postmeta_value( $first_draft_date_term, $id );
		$this->assertEquals( '1546390800', $first_draft_date_value );
	}

	/**
	* Test that editorial metadata for date is saved
	*/
	function test_save_metabox_with_empty_date() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );
		$_POST[ EF_Editorial_Metadata::metadata_taxonomy . '_nonce' ] = wp_create_nonce( 'ef-save-metabox' );
		$first_draft_date_term                                        = $edit_flow->editorial_metadata->get_editorial_metadata_term_by( 'slug', 'first-draft-date' );
		$ef_first_draft_date_key                                      = $edit_flow->editorial_metadata->get_postmeta_key( $first_draft_date_term );

		$_POST[ $ef_first_draft_date_key ] = '';

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'publish',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		$id = wp_insert_post( $post );

		$first_draft_date_value = $edit_flow->editorial_metadata->get_postmeta_value( $first_draft_date_term, $id );
		$this->assertEmpty( $first_draft_date_value );
	}

	/**
	* Test that editorial metadata for date is saved
	*/
	function test_save_metabox_with_invalid_date() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );
		$_POST[ EF_Editorial_Metadata::metadata_taxonomy . '_nonce' ] = wp_create_nonce( 'ef-save-metabox' );
		$first_draft_date_term                                        = $edit_flow->editorial_metadata->get_editorial_metadata_term_by( 'slug', 'first-draft-date' );
		$ef_first_draft_date_key                                      = $edit_flow->editorial_metadata->get_postmeta_key( $first_draft_date_term );

		$_POST[ $ef_first_draft_date_key ] = 'Not a date';

		$post = array(
			'post_author'   => self::$admin_user_id,
			'post_status'   => 'publish',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2016-04-29 12:00:00',
		);

		$id = wp_insert_post( $post );

		$first_draft_date_value = $edit_flow->editorial_metadata->get_postmeta_value( $first_draft_date_term, $id );
		$this->assertEmpty( $first_draft_date_value );
	}
}
