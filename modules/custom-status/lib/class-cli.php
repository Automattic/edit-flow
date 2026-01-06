<?php
/**
 * WP-CLI commands for Edit Flow Custom Statuses.
 *
 * @package EditFlow
 * @subpackage CustomStatus
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands to manage Edit Flow custom statuses.
 *
 * @package EditFlow
 */
class EF_Custom_Status_CLI extends WP_CLI_Command {

	/**
	 * List all custom statuses.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all custom statuses
	 *     $ wp edit-flow custom-status list
	 *
	 *     # List statuses as JSON
	 *     $ wp edit-flow custom-status list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_statuses( $args, $assoc_args ) {
		$custom_status_module = $this->get_custom_status_module();

		if ( ! $custom_status_module ) {
			WP_CLI::error( 'Custom Status module is not available.' );
		}

		$statuses = $custom_status_module->get_custom_statuses();

		if ( empty( $statuses ) ) {
			WP_CLI::warning( 'No custom statuses found.' );
			return;
		}

		$items = [];
		foreach ( $statuses as $status ) {
			$count   = $this->get_post_count_for_status( $status->slug );
			$items[] = [
				'slug'        => $status->slug,
				'name'        => $status->name,
				'description' => $status->description,
				'position'    => $status->position ?? '',
				'post_count'  => $count,
			];
		}

		$format = $assoc_args['format'] ?? 'table';

		WP_CLI\Utils\format_items( $format, $items, [ 'slug', 'name', 'description', 'position', 'post_count' ] );
	}

	/**
	 * Migrate posts from one status to another.
	 *
	 * ## OPTIONS
	 *
	 * --from=<status>
	 * : The source status slug to migrate from.
	 *
	 * --to=<status>
	 * : The target status slug to migrate to.
	 *
	 * [--post-type=<post-type>]
	 * : Only migrate posts of this type. Default: all supported post types.
	 *
	 * [--dry-run]
	 * : Show what would be migrated without making changes.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Migrate all posts from 'pitch' to 'draft'
	 *     $ wp edit-flow custom-status migrate --from=pitch --to=draft
	 *
	 *     # Preview migration without making changes
	 *     $ wp edit-flow custom-status migrate --from=pitch --to=draft --dry-run
	 *
	 *     # Migrate only posts of type 'post'
	 *     $ wp edit-flow custom-status migrate --from=pitch --to=draft --post-type=post
	 *
	 * @subcommand migrate
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate( $args, $assoc_args ) {
		$custom_status_module = $this->get_custom_status_module();

		if ( ! $custom_status_module ) {
			WP_CLI::error( 'Custom Status module is not available.' );
		}

		$from_status = $assoc_args['from'] ?? '';
		$to_status   = $assoc_args['to'] ?? '';
		$post_type   = $assoc_args['post-type'] ?? '';
		$dry_run     = isset( $assoc_args['dry-run'] );

		if ( empty( $from_status ) ) {
			WP_CLI::error( 'Please specify a source status with --from=<status>' );
		}

		if ( empty( $to_status ) ) {
			WP_CLI::error( 'Please specify a target status with --to=<status>' );
		}

		if ( $from_status === $to_status ) {
			WP_CLI::error( 'Source and target status cannot be the same.' );
		}

		// Validate target status exists.
		$valid_statuses = $this->get_all_valid_statuses();
		if ( ! in_array( $to_status, $valid_statuses, true ) ) {
			WP_CLI::error( sprintf( "Target status '%s' is not a valid status. Valid statuses: %s", $to_status, implode( ', ', $valid_statuses ) ) );
		}

		// Get posts with the source status.
		$query_args = [
			'post_status'    => $from_status,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];

		if ( ! empty( $post_type ) ) {
			$query_args['post_type'] = $post_type;
		} else {
			$query_args['post_type'] = 'any';
		}

		$posts = get_posts( $query_args );
		$count = count( $posts );

		if ( 0 === $count ) {
			WP_CLI::success( sprintf( "No posts found with status '%s'.", $from_status ) );
			return;
		}

		if ( $dry_run ) {
			WP_CLI::log( sprintf( "[Dry Run] Would migrate %d post(s) from '%s' to '%s'.", $count, $from_status, $to_status ) );

			if ( $count <= 20 ) {
				foreach ( $posts as $post_id ) {
					$post = get_post( $post_id );
					WP_CLI::log( sprintf( '  - #%d: %s (%s)', $post_id, $post->post_title, $post->post_type ) );
				}
			}
			return;
		}

		// Confirmation prompt.
		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( sprintf( "Are you sure you want to migrate %d post(s) from '%s' to '%s'?", $count, $from_status, $to_status ) );
		}

		// Perform the migration.
		global $wpdb;

		if ( ! empty( $post_type ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk update for CLI migration command.
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_status = %s WHERE post_status = %s AND post_type = %s",
					$to_status,
					$from_status,
					$post_type
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk update for CLI migration command.
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_status = %s WHERE post_status = %s",
					$to_status,
					$from_status
				)
			);
		}

		// Clear caches.
		wp_cache_flush();
		clean_post_cache( $posts );

		WP_CLI::success( sprintf( "Migrated %d post(s) from '%s' to '%s'.", $count, $from_status, $to_status ) );
	}

	/**
	 * Get post counts for each status.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : Only count posts of this type. Default: post.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show post counts per status
	 *     $ wp edit-flow custom-status counts
	 *
	 *     # Show counts for pages
	 *     $ wp edit-flow custom-status counts --post-type=page
	 *
	 * @subcommand counts
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function counts( $args, $assoc_args ) {
		$post_type = $assoc_args['post-type'] ?? 'post';
		$format    = $assoc_args['format'] ?? 'table';

		$counts = wp_count_posts( $post_type );
		$items  = [];

		foreach ( $counts as $status => $count ) {
			if ( (int) $count > 0 ) {
				$status_obj = get_post_status_object( $status );
				$items[]    = [
					'status' => $status,
					'label'  => $status_obj ? $status_obj->label : $status,
					'count'  => (int) $count,
				];
			}
		}

		if ( empty( $items ) ) {
			WP_CLI::warning( sprintf( 'No posts found for post type: %s', $post_type ) );
			return;
		}

		// Sort by count descending.
		usort( $items, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		WP_CLI\Utils\format_items( $format, $items, [ 'status', 'label', 'count' ] );
	}

	/**
	 * Get the custom status module instance.
	 *
	 * @return EF_Custom_Status|null
	 */
	private function get_custom_status_module() {
		global $edit_flow;

		if ( isset( $edit_flow->custom_status ) ) {
			return $edit_flow->custom_status;
		}

		return null;
	}

	/**
	 * Get post count for a specific status.
	 *
	 * @param string $status The status slug.
	 * @return int
	 */
	private function get_post_count_for_status( $status ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI count query, caching not needed.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s",
				$status
			)
		);
	}

	/**
	 * Get all valid status slugs (custom + core).
	 *
	 * @return array
	 */
	private function get_all_valid_statuses() {
		$statuses = [];

		// Add core statuses.
		$core_statuses = [ 'publish', 'pending', 'draft', 'private', 'trash', 'future' ];
		$statuses      = array_merge( $statuses, $core_statuses );

		// Add custom statuses.
		$custom_status_module = $this->get_custom_status_module();
		if ( $custom_status_module ) {
			$custom_statuses = $custom_status_module->get_custom_statuses();
			foreach ( $custom_statuses as $status ) {
				$statuses[] = $status->slug;
			}
		}

		return array_unique( $statuses );
	}
}

WP_CLI::add_command( 'edit-flow custom-status', 'EF_Custom_Status_CLI' );
