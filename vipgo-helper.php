<?php
/**
 * VIP Go helper for Edit Flow.
 *
 * Ensures Edit Flow is instantiated and provides necessary
 * capability filters for the VIP Go environment.
 *
 * @package EditFlow
 */

// Ensure Edit Flow is instantiated.
add_action( 'after_setup_theme', 'EditFlow' );

/**
 * Caps don't get loaded on install on VIP Go. Instead, let's add
 * them via filters.
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
