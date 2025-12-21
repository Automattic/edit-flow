jQuery( document ).ready( function () {
	const i18n = window.__ef_localize_custom_status;

	jQuery( 'label[for=post_status]' ).show();
	jQuery( '#post-status-display' ).show();

	if ( jQuery( 'select[name="_status"]' ).length == 0 ) {
		// not on quick edit
		if (
			current_user_can_publish_posts ||
			( current_status == 'publish' && current_user_can_edit_published_posts )
		) {
			// show publish button if allowed to publish
			jQuery( '#publish' ).show();
		} else {
			// mimic default post status dropdown
			jQuery(
				'<span>&nbsp;<a href="#post_status" class="edit-post-status" tabindex=\'4\'>' +
					i18n.edit +
					'</a></span>' +
					' <div id="post-status-select">' +
					' <input type="hidden" name="hidden_post_status" id="hidden_post_status" value="in-progress" />' +
					" <select name='post_status' id='post_status' tabindex='4'>" +
					' </select>' +
					'  <a href="#post_status" class="save-post-status button">' +
					i18n.ok +
					'</a>' +
					'  <a href="#post_status" class="cancel-post-status">' +
					i18n.cancel +
					'</a>' +
					' </div>'
			).insertAfter( '#post-status-display' );

			if ( ! status_dropdown_visible ) {
				jQuery( '#post-status-select' ).hide();
				jQuery( '.edit-post-status' ).show();
			}

			jQuery( '.edit-post-status' ).on( 'click', function () {
				jQuery( '#post-status-select' ).slideDown();
				jQuery( '.edit-post-status' ).hide();
				return false;
			} );
			jQuery( '.cancel-post-status, .save-post-status' ).on( 'click', function () {
				jQuery( '#post-status-select' ).slideUp();
				jQuery( '.edit-post-status' ).show();
				return false;
			} );
			jQuery( '.save-post-status' ).on( 'click', function () {
				jQuery( '#post-status-display' ).text(
					jQuery( 'select[name="post_status"] :selected' ).text()
				);
				return false;
			} );
		}
	}

	// 1. Add custom statuses to post.php Status dropdown
	// Or 2. Add custom statuses to quick-edit status dropdowns on edit.php
	// Or 3. Hide two inputs with the default workflow status to override 'Draft' as the default contributor status
	if ( jQuery( 'select[name="post_status"]' ).length > 0 ) {
		// Set the Save button to generic text by default
		ef_update_save_button( i18n.save );

		// Bind event when OK button is clicked
		jQuery( '.save-post-status' ).on( 'click', function () {
			ef_update_save_button();
		} );

		// Add custom statuses to Status dropdown
		ef_append_to_dropdown( 'select[name="post_status"]' );

		// Make status dropdown visible on load if enabled
		if ( status_dropdown_visible ) {
			jQuery( '#post-status-select' ).show();
			jQuery( '.edit-post-status' ).hide();
		}

		// Hide status dropdown if not allowed to edit
		if ( ! ef_can_change_status( current_status ) ) {
			jQuery( '#post-status-select' ).hide();
			jQuery( '.edit-post-status' ).hide();

			// set the current status as the selected one
			const $option = jQuery( '<option></option>' )
				.text( current_status_name )
				.attr( 'value', current_status )
				.attr( 'selected', 'selected' );

			$option.appendTo( 'select[name="post_status"]' );
		}

		// If custom status set for post, then set is as #post-status-display
		jQuery( '#post-status-display' ).text( ef_get_status_name( current_status ) );
	} else if ( jQuery( 'select[name="_status"]' ).length > 0 ) {
		ef_append_to_dropdown( 'select[name="_status"]' );
		// Clean up the bulk edit selector because it's non-standard
		jQuery( '#bulk-edit' )
			.find( 'select[name="_status"]' )
			.prepend( '<option value="">' + i18n.no_change + '</option>' );
		jQuery( '#bulk-edit' ).find( 'select[name="_status"] option' ).prop( 'selected', false );
		jQuery( '#bulk-edit' ).find( 'select[name="_status"] option[value="future"]' ).remove();
	} else {
		// Set the Save button to generic text by default
		ef_update_save_button( i18n.save );

		// If custom status set for post, then set is as #post-status-display
		jQuery( '#post-status-display' ).text( ef_get_status_name( current_status ) );
	}

	if ( jQuery( 'ul.subsubsub' ) ) {
		ef_add_tooltips_to_filter_links( 'ul.subsubsub li a' );
	}

	// Add custom statuses to Status dropdown
	function ef_append_to_dropdown( id ) {
		// Empty dropdown except for 'future' because we need to persist that
		jQuery( id + ' option' )
			.not( '[value="future"]' )
			.remove();

		// Add "Published" status to quick-edit for users that can publish
		if ( id == 'select[name="_status"]' && current_user_can_publish_posts ) {
			jQuery( id ).append(
				jQuery( '<option></option' ).attr( 'value', 'publish' ).text( i18n.published )
			);
		}

		// Add "Private" status to bulk/quick-edit for users that can publish
		// On the post editor, private is handled by the Visibility checkbox, but bulk edit has no such control
		if ( id == 'select[name="_status"]' && current_user_can_publish_posts ) {
			jQuery( id ).append(
				jQuery( '<option></option' ).attr( 'value', 'private' ).text( i18n.private )
			);
		}

		// Add remaining statuses to dropdown. 'future' already exists if we need it.
		// 'private' is handled above for bulk/quick edit, or via Visibility checkbox on post editor.
		jQuery.each( custom_statuses, function () {
			if ( this.slug == 'private' || this.slug == 'future' ) {
				return;
			}

			if ( current_status != 'publish' && this.slug == 'publish' ) {
				return;
			}

			const $option = jQuery( '<option></option>' )
				.text( this.name )
				.attr( 'value', this.slug )
				.attr( 'title', this.description ? this.description : '' );
			if ( current_status == this.slug ) {
				$option.attr( 'selected', 'selected' );
			}

			$option.appendTo( jQuery( id ) );
		} );
	}

	function ef_can_change_status( slug ) {
		let change = false;

		jQuery.each( custom_statuses, function () {
			if ( this.slug == slug ) {
				change = true;
			}
		} );
		if ( slug == 'publish' && ! current_user_can_publish_posts ) {
			change = false;
		}
		return change;
	}

	function ef_add_tooltips_to_filter_links( selector ) {
		jQuery.each( custom_statuses, function () {
			jQuery( selector + ':contains("' + this.name + '")' ).attr( 'title', this.description );
		} );
	}

	// Update "Save" button text
	function ef_update_save_button( text ) {
		if ( ! text ) {
			text = i18n.save_as + ' ' + jQuery( 'select[name="post_status"] :selected' ).text();
		}
		jQuery( ':input#save-post' ).attr( 'value', text );
	}

	// Returns the name of the status given a slug
	function ef_get_status_name( slug ) {
		let name = '';
		jQuery.each( custom_statuses, function () {
			if ( this.slug == slug ) {
				name = this.name;
			}
		} );

		if ( ! name ) {
			name = current_status_name;
		}

		return name;
	}

	// If we're on the Manage Posts screen, remove the trailing dashes left behind once we hide the post-state span (the status).
	// We do this since we already add a custom column for post status on the screen since custom statuses are a core part of EF.
	if ( jQuery( '.post-state' ).length > 0 ) {
		ef_remove_post_title_trailing_dashes();
	}

	// Remove the " - " in between a post title and the post-state span (separately hidden via CSS).
	// This will not affect the dash before post-state-format spans.
	function ef_remove_post_title_trailing_dashes() {
		jQuery( '.post-title.column-title strong' ).each( function () {
			jQuery( this ).html(
				jQuery( this )
					.html()
					.replace( /(.*) - (<span class="post-state".*<\/span>)$/g, '$1$2' )
			);
		} );
	}
} );
