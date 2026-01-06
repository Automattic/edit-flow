jQuery( document ).ready( function ( $ ) {
	$( '#ef-post_following_users_box ul' ).listFilterizer();

	const params = {
		action: 'save_notifications',
		post_id: $( '#post_ID' ).val(),
	};

	const localization =
		typeof ef_notifications_localization !== 'undefined' ? ef_notifications_localization : {};

	/**
	 * Disable the post author checkbox if auto-subscribe is enabled.
	 * The checkbox is disabled because unchecking it would have no effect -
	 * the post author will be re-subscribed automatically on save.
	 */
	const maybe_disable_post_author_checkbox = function () {
		if ( ! localization.post_author_id || ! localization.post_author_auto_subscribe ) {
			return;
		}

		const $checkbox = $( '#ef-selected-users-' + localization.post_author_id );
		if ( $checkbox.length ) {
			$checkbox.prop( 'disabled', true );
			$checkbox.attr( 'title', localization.auto_subscribed );
		}
	};

	// Initialize: disable post author checkbox if needed.
	maybe_disable_post_author_checkbox();

	const toggle_warning_badges = function ( container, response ) {
		const userId = parseInt( $( container ).val(), 10 );
		const $actionsDiv = $( container ).parent();

		// Remove existing warning badges (but keep Post Author and Auto-subscribed badges).
		$actionsDiv.find( '.post_following_list-no_access, .post_following_list-no_email' ).remove();

		// "No Access" If this user was flagged as not having access.
		const user_has_no_access = response.data.subscribers_with_no_access.includes( userId );
		if ( user_has_no_access ) {
			const span = $( '<span />' ).addClass( 'post_following_list-no_access' );
			span.text( localization.no_access );
			$actionsDiv.prepend( span );
			warning_background = true;
		}

		// "No Email" If this user was flagged as not having an email.
		const user_has_no_email = response.data.subscribers_with_no_email.includes( userId );
		if ( user_has_no_email ) {
			const span = $( '<span />' ).addClass( 'post_following_list-no_email' );
			span.text( localization.no_email );
			$actionsDiv.prepend( span );
			warning_background = true;
		}
	};

	$( document ).on(
		'click',
		'.ef-post_following_list li input:checkbox, .ef-following_usergroups li input:checkbox',
		function () {
			const user_group_ids = [];
			const parent_this = $( this );
			params.ef_notifications_name = $( this ).attr( 'name' );
			params._nonce = $( '#ef_notifications_nonce' ).val();

			$( this )
				.parents( '.ef-post_following_list' )
				.find( 'input:checked' )
				.map( function () {
					user_group_ids.push( $( this ).val() );
				} );

			params.user_group_ids = user_group_ids;

			$.ajax( {
				type: 'POST',
				url: ajaxurl ? ajaxurl : wpListL10n.url,
				data: params,

				success( response ) {
					// Reset background color (set during toggle_warning_badges if there's a warning)
					warning_background = false;

					// Toggle the warning badges ("No Access" and "No Email") to signal the user won't receive notifications
					if ( undefined !== response.data ) {
						toggle_warning_badges( $( parent_this ), response );
					}
					// Green 40% by default
					var backgroundHighlightColor = '#90d296';
					if ( warning_background ) {
						// Red 40% if there's a warning
						var backgroundHighlightColor = '#ea8484';
					}
					const backgroundColor = parent_this.css( 'background-color' );
					$( parent_this.parents( 'li' ) )
						.animate( { backgroundColor: backgroundHighlightColor }, 200 )
						.animate( { backgroundColor }, 200 );

					// This event is used to show an updated list of who will be notified of editorial comments and status updates.
					$( '#ef-post_following_box' ).trigger( 'following_list_updated' );
				},
				error( r ) {
					$( '#ef-post_following_users_box' )
						.prev()
						.append( ' <p class="error">There was an error. Please reload the page.</p>' );
				},
			} );
		}
	);

	// TODO: Should change this to _not_ use JQuery
	const webhookUrl = $( 'input#webhook_url' ).closest( 'tr' );
	const sendToWebhook = $( 'select#send_to_webhook' );
	if ( sendToWebhook.val() === 'off' ) {
		webhookUrl.hide();
	}
	sendToWebhook.on( 'change', function () {
		if ( $( this ).val() === 'off' ) {
			webhookUrl.hide();
		} else {
			webhookUrl.show();
		}
	} );
} );
