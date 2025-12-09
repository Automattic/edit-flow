<?php
/**
 * Ensure Edit Flow is instantiated
 */
add_action( 'after_setup_theme', 'EditFlow' );

/**
 * Don't load caps on install for WP.com. Instead, let's add
 * them with the WP.com + core caps approach
 */
add_filter( 'ef_kill_add_caps_to_role', '__return_true' );
add_filter( 'ef_view_calendar_cap', function () {
	return 'edit_posts';
} );
add_filter( 'ef_view_story_budget_cap', function () {
	return 'edit_posts';
} );
add_filter( 'ef_edit_post_subscriptions_cap', function () {
	return 'edit_others_posts';
} );
add_filter( 'ef_manage_usergroups_cap', function () {
	return 'manage_options';
} );

/**
 * Edit Flow loads modules after plugins_loaded, which has already been fired on WP.com
 * Let's run the method at after_setup_themes
 */
add_filter( 'after_setup_theme', 'edit_flow_wpcom_load_modules' );
function edit_flow_wpcom_load_modules() {
	global $edit_flow;
	if ( method_exists( $edit_flow, 'action_ef_loaded_load_modules' ) ) {
		$edit_flow->action_ef_loaded_load_modules();
	}
}

/**
 * Share A Draft on WordPress.com breaks when redirect canonical is enabled
 * get_permalink() doesn't respect custom statuses
 *
 * @see http://core.trac.wordpress.org/browser/tags/3.4.2/wp-includes/canonical.php#L113
 */
add_filter( 'redirect_canonical', 'edit_flow_wpcom_redirect_canonical' );
function edit_flow_wpcom_redirect_canonical( $redirect ) {

	if ( ! empty( $_GET['shareadraft'] ) ) {
		return false;
	}

	return $redirect;
}

// This should fix a caching race condition that can sometimes create a published post with an empty slug
add_filter( 'ef_fix_post_name_post', 'edit_flow_fix_fix_post_name' );
function edit_flow_fix_fix_post_name( $post ) {
	global $wpdb;
	$post_status = $wpdb->get_var( $wpdb->prepare( 'SELECT post_status FROM ' . $wpdb->posts . ' WHERE ID = %d', $post->ID ) );
	if ( null !== $post_status ) {
		$post->post_status = $post_status;
	}

	return $post;
}
