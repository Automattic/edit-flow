jQuery( document ).ready( function ( $ ) {
	$( '#ef-post_following_users_box ul' ).listFilterizer();

	const params = {
		action: 'save_notifications',
		post_id: $( '#post_ID' ).val(),
	};

	const toggle_warning_badges = function ( container, response ) {
		// Remove any existing badges
		if ( $( container ).siblings( 'span' ).length ) {
			$( container ).siblings( 'span' ).remove();
		}

		// "No Access" If this user was flagged as not having access
		const user_has_no_access = response.data.subscribers_with_no_access.includes(
			parseInt( $( container ).val() )
		);
		if ( user_has_no_access ) {
			var span = $( '<span />' ).addClass( 'post_following_list-no_access' );
			span.text( ef_notifications_localization.no_access );
			$( container ).parent().prepend( span );
			warning_background = true;
		}
		// "No Email" If this user was flagged as not having an email
		const user_has_no_email = response.data.subscribers_with_no_email.includes(
			parseInt( $( container ).val() )
		);
		if ( user_has_no_email ) {
			var span = $( '<span />' ).addClass( 'post_following_list-no_email' );
			span.text( ef_notifications_localization.no_email );
			$( container ).parent().prepend( span );
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
