<?php
/**
 * Custom Status AJAX integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use WP_User;
use WPAjaxDieContinueException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CustomStatusAjaxTest extends AjaxTestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
	}

	/**
	 * A post with 'future' status should not have post_date_gmt
	 * altered when an autosave occurs
	 */
	function test_autosave_post() {
		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		// The original post_author
		wp_set_current_user( $admin_user_id );

		$user = new WP_User( $admin_user_id );

		$future_date = date( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$post        = $this->factory->post->create_and_get(
			array(
				'post_author'   => $admin_user_id,
				'post_status'   => 'future',
				'post_content'  => rand_str(),
				'post_title'    => rand_str(),
				'post_date'     => $future_date,
				'post_date_gmt' => $future_date,
			)
		);

		// Set up the $_POST request
		$md5   = md5( uniqid() );
		$_POST = array(
			'action' => 'heartbeat',
			'_nonce' => wp_create_nonce( 'heartbeat-nonce' ),
			'data'   => array(
				'wp_autosave' => array(
					'post_id'      => $post->ID,
					'_wpnonce'     => wp_create_nonce( 'update-post_' . $post->ID ),
					'post_content' => $post->post_content . PHP_EOL . $md5,
					'post_type'    => 'post',
				),
			),
		);

		try {
			$this->_handleAjax( 'heartbeat' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Get the response, it is in heartbeat's response
		$response = json_decode( $this->_last_response, true );

		// Ensure everything is correct
		$this->assertNotEmpty( $response['wp_autosave'] );
		$this->assertTrue( $response['wp_autosave']['success'] );

		$autoSavedPost = get_post( $post->ID );
		$this->assertEquals( $autoSavedPost->post_date_gmt, $future_date );
	}
}
