<?php

if ( ! function_exists( 'ef_draft_or_post_title' ) ) :
	/**
	 * Copy of core's _draft_or_post_title without the filters
	 *
	 * The post title is fetched and if it is blank then a default string is
	 * returned.
	 * @param int $post_id The post id. If not supplied the global $post is used.
	 * @return string The post title if set
	 */
	function ef_draft_or_post_title( $post_id = 0 ) {
		$post = get_post( $post_id );
		return ! empty( $post->post_title ) ? $post->post_title : __( '(no title)', 'edit-flow' );
	}
endif;

/**
 * This function is necessary to make post preview pagination work with custom post statuses
 * @see _wp_link_page()
 * @modified WordPress 4.9.2
 *
 * Original Comments:
 *
 * Helper function for wp_link_pages()
 *
 * @since 3.1.0
 * @access private
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int $i Page number.
 * @return string Link.
 */
if ( ! function_exists( '_ef_wp_link_page' ) ) {
	function _ef_wp_link_page( $i, $custom_statuses ) {
		global $wp_rewrite;
		$post = get_post();
		$query_args = array();

		if ( 1 == $i ) {
			$url = get_permalink();
		// phpcs:ignore Universal.ControlStructures.DisallowLonelyIf.Found
		} else {
			// Check for all custom post statuses, not just draft & pending
			if ( '' == get_option( 'permalink_structure' ) || in_array( $post->post_status, array_merge( $custom_statuses, array( 'pending' ) ) ) ) {
				$url = add_query_arg( 'page', $i, get_permalink() );
			} else if ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) == $post->ID ) {
				$url = trailingslashit( get_permalink() ) . user_trailingslashit( "$wp_rewrite->pagination_base/" . $i, 'single_paged' );
			} else {
				$url = trailingslashit( get_permalink() ) . user_trailingslashit( $i, 'single_paged' );
			}
		}

		if ( is_preview() ) {

			// Check for all custom post statuses, not just the draft
			if ( ( ! in_array( $post->post_status, $custom_statuses ) ) && isset( $_GET['preview_id'], $_GET['preview_nonce'] ) ) {
				$query_args['preview_id'] = wp_unslash( $_GET['preview_id'] );
				$query_args['preview_nonce'] = wp_unslash( $_GET['preview_nonce'] );
			}

			$url = get_preview_post_link( $post, $query_args, $url );
		}

		return '<a href="' . esc_url( $url ) . '">';
	}
}
