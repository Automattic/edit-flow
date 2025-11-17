<?php
/**
 * A notepad for the dashboard
 */

class EF_Dashboard_Notepad_Widget {

	// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
	const notepad_post_type = 'dashboard-note';

	public $edit_cap = 'edit_others_posts';

	public function __construct() {
		// Silence is golden
	}

	public function init() {

		register_post_type( self::notepad_post_type, array(
			'rewrite' => false,
			'label' => __( 'Dashboard Note', 'edit-flow' ),
		));

		$this->edit_cap = apply_filters( 'ef_dashboard_notepad_edit_cap', $this->edit_cap );

		add_action( 'admin_init', array( $this, 'handle_notepad_update' ) );
	}

	/**
	 * Handle a dashboard note being created or updated
	 *
	 * @since 0.8
	 */
	public function handle_notepad_update() {
		global $pagenow;

		if ( 'index.php' !== $pagenow || empty( $_POST['action'] ) || 'dashboard-notepad' !== $_POST['action'] ) {
			return;
		}

		check_admin_referer( 'dashboard-notepad' );

		if ( ! current_user_can( $this->edit_cap ) ) {
			wp_die( esc_html( EditFlow()->dashboard->messages['invalid-permissions'] ) );
		}

		$note_data = array(
			'post_content' => isset( $_POST['note'] ) ? wp_filter_nohtml_kses( $_POST['note'] ) : '',
			'post_type'    => self::notepad_post_type,
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
		);

		$existing_notepad = isset( $_POST['notepad-id'] ) ? get_post( absint( $_POST['notepad-id'] ) ) : null;
		if ( isset( $existing_notepad->post_type ) && self::notepad_post_type === $existing_notepad->post_type ) {
			$note_data['ID'] = $existing_notepad->ID;
		}

		wp_insert_post( $note_data );
	}

	/**
	 * Notepad Widget
	 * Editors can leave notes in the dashboard for authors and contributors
	 *
	 * @since 0.8
	 */
	public function notepad_widget() {

		$args = array(
			'posts_per_page'   => 1,
			'post_status'      => 'draft',
			'post_type'        => self::notepad_post_type,
		);

		$posts = get_posts( $args );
		$current_note = ( ! empty( $posts[0]->post_content ) ) ? $posts[0]->post_content : '';
		$current_id = ( ! empty( $posts[0]->ID ) ) ? $posts[0]->ID : 0;
		$current_post = ( ! empty( $posts[0] ) ) ? $posts[0] : false;

		if ( $current_post ) {
			// translators: %1$s is the author name, %2$s is the date the note was last updated
			$last_updated = '<span id="dashboard-notepad-last-updated">' . sprintf( __( '%1$s last updated on %2$s', 'edit-flow' ), get_user_by( 'id', $current_post->post_author )->display_name, get_the_modified_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $current_post ) ) . '</span>';
		} else {
			$last_updated = '';
		}

		if ( current_user_can( $this->edit_cap ) ) {
			echo '<form method="post" id="dashboard-notepad">';
			echo '<input type="hidden" name="action" value="dashboard-notepad" />';
			echo '<input type="hidden" name="notepad-id" value="' . esc_attr( $current_id ) . '" />';
			echo '<textarea style="width:100%" rows="10" name="note">';
			echo esc_textarea( trim( $current_note ) );
			echo '</textarea>';
			echo '<p class="submit">';
			echo wp_kses_post( $last_updated );
			echo '<span id="dashboard-notepad-submit-buttons">';
			submit_button( __( 'Update Note', 'edit-flow' ), 'primary', 'update-note', false );
			echo '</span>';
			echo '<div style="clear:both;"></div>';
			wp_nonce_field( 'dashboard-notepad' );
			echo '</form>';
		} else {
			echo '<form id="dashboard-notepad">';
			echo '<textarea style="width:100%" rows="10" name="note" disabled="disabled">';
			echo esc_textarea( trim( $current_note ) );
			echo '</textarea>';
			echo wp_kses_post( $last_updated );
			echo '</form>';
		}
	}
}
