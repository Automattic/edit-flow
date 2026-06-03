<?php
/**
 * Editorial Metadata integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Editorial_Metadata;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class EditorialMetadataTest extends TestCase {

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

	/**
	 * A stored "location" value must not be able to break out of the Google Maps
	 * link's href attribute and inject an event handler.
	 */
	function test_location_field_metabox_output_is_escaped() {
		global $edit_flow;

		wp_set_current_user( self::$admin_user_id );

		// The "location" field type is not part of the default install, so create one.
		$edit_flow->editorial_metadata->insert_editorial_metadata_term(
			array(
				'name' => 'Test Location',
				'slug' => 'test-location',
				'type' => 'location',
			)
		);
		$location_term = $edit_flow->editorial_metadata->get_editorial_metadata_term_by( 'slug', 'test-location' );
		$location_key  = $edit_flow->editorial_metadata->get_postmeta_key( $location_term );

		$post_id = wp_insert_post(
			array(
				'post_author'  => self::$admin_user_id,
				'post_status'  => 'draft',
				'post_title'   => rand_str(),
				'post_content' => rand_str(),
			)
		);

		// Attribute-injection payload: it contains no tags, so wp_strip_all_tags()/
		// sanitize_text_field() leave it intact - the defence must be at output.
		$payload = 'x onmouseover=alert(document.domain) y=';
		update_post_meta( $post_id, $location_key, $payload );

		ob_start();
		$edit_flow->editorial_metadata->display_meta_box( get_post( $post_id ) );
		$output = ob_get_clean();

		// The Google Maps link must use a single, properly quoted https href.
		$this->assertStringContainsString( 'href="https://maps.google.com', $output );
		// The old unquoted href that allowed the breakout must be gone.
		$this->assertStringNotContainsString( 'href=http://maps.google.com', $output );

		// No event-handler attribute may be injected into the anchor's opening tag.
		$anchor_start = strpos( $output, '<a href="https://maps.google.com' );
		$this->assertNotFalse( $anchor_start, 'Expected the Google Maps anchor in the metabox output.' );
		$opening_tag = substr( $output, $anchor_start, strpos( $output, '>', $anchor_start ) - $anchor_start + 1 );
		$this->assertStringNotContainsString( 'onmouseover=', $opening_tag );
	}

	/**
	 * Editorial metadata registered for the REST API must carry a sanitize_callback
	 * so the Gutenberg/REST write path cannot store unsanitised values.
	 */
	function test_rest_meta_registers_a_sanitize_callback() {
		global $edit_flow;

		$em = $edit_flow->editorial_metadata;

		// Ensure the module reports a post type so REST meta is registered for it.
		if ( ! isset( $em->module->options ) || ! is_object( $em->module->options ) ) {
			$em->module->options = new \stdClass();
		}
		$em->module->options->post_types = array( 'post' => 'on' );

		// Invoke the production registration routine (private; runs at init in normal use).
		$register = new \ReflectionMethod( $em, 'register_metadata_for_rest_api' );
		$register->setAccessible( true );
		$register->invoke( $em );

		$registered = get_registered_meta_keys( 'post', 'post' );

		$paragraph_term = $em->get_editorial_metadata_term_by( 'slug', 'assignment' ); // type: paragraph.
		$number_term    = $em->get_editorial_metadata_term_by( 'slug', 'word-count' );  // type: number.
		$paragraph_key  = $em->get_postmeta_key( $paragraph_term );
		$number_key     = $em->get_postmeta_key( $number_term );

		$this->assertArrayHasKey( $paragraph_key, $registered, 'Paragraph editorial-metadata key should be registered for REST.' );
		$this->assertArrayHasKey( $number_key, $registered, 'Number editorial-metadata key should be registered for REST.' );

		// Every write path must sanitise; paragraphs keep line breaks, other types are single-line.
		$this->assertSame( 'sanitize_textarea_field', $registered[ $paragraph_key ]['sanitize_callback'] );
		$this->assertSame( 'sanitize_text_field', $registered[ $number_key ]['sanitize_callback'] );
	}
}
