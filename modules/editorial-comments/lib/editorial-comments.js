jQuery( document ).ready( function () {
	editorialCommentReply.init();

	// Check if certain hash flag set and take action
	if ( location.hash == '#editorialcomments/add' ) {
		editorialCommentReply.open();
	} else if ( location.hash.search( /#editorialcomments\/reply/ ) > -1 ) {
		const reply_id = location.hash.substring( location.hash.lastIndexOf( '/' ) + 1 );
		editorialCommentReply.open( reply_id );
	}
} );

/**
 * Blatantly stolen and modified from /wp-admin/js/edit-comment.dev.js -- yay!
 */
editorialCommentReply = {
	init() {
		const row = jQuery( '#ef-replyrow' );

		// Bind click events to cancel and submit buttons
		jQuery( 'a.ef-replycancel', row ).on( 'click', function () {
			return editorialCommentReply.revert();
		} );
		jQuery( 'a.ef-replysave', row ).on( 'click', function () {
			return editorialCommentReply.send();
		} );

		// Watch for changes to the subscribed users.
		jQuery( '#ef-post_following_box' ).on( 'following_list_updated', function () {
			editorialCommentReply.notifiedMessage();
		} );
	},

	revert() {
		// Fade out slowly, slowly, slowly...
		jQuery( '#ef-replyrow' ).fadeOut( 'fast', function () {
			editorialCommentReply.close();
		} );
		return false;
	},

	close() {
		jQuery( '#ef-comment_respond' ).show();

		// Move reply form back after the main "Respond" form
		jQuery( '#ef-post_comment' ).after( jQuery( '#ef-replyrow' ) );

		// Empty out all the form values
		jQuery( '#ef-replycontent' ).val( '' );
		jQuery( '#ef-comment_parent' ).val( '' );

		// Hide error and waiting
		jQuery( '#ef-replysubmit .error' ).html( '' ).hide();
		jQuery( '#ef-comment_loading' ).hide();
	},

	/**
	 * @id = comment id
	 */
	open( id ) {
		let parent;

		// Close any open reply boxes
		this.close();

		// Check if reply or new comment
		if ( id ) {
			jQuery( 'input#ef-comment_parent' ).val( id );
			parent = '#comment-' + id;
		} else {
			parent = '#ef-comments_wrapper';
		}

		jQuery( '#ef-comment_respond' ).hide();

		// Display who will be notified for this comment
		this.notifiedMessage();

		// Show reply textbox
		jQuery( '#ef-replyrow' ).show().appendTo( jQuery( parent ) );

		jQuery( '#ef-replycontent' ).focus();

		return false;
	},

	/**
	 * Sends the ajax response to save the commment
	 * @param bool reply - indicates whether the comment is a reply or not
	 */
	send( reply ) {
		const post = {};
		const containter_id = '#ef-replyrow';

		jQuery( '#ef-replysubmit .error' ).html( '' ).hide();

		// Validation: check to see if comment entered
		post.content = jQuery.trim( jQuery( '#ef-replycontent' ).val() );
		if ( ! post.content ) {
			jQuery( '#ef-replyrow .error' ).text( 'Please enter a comment' ).show();
			return;
		}

		jQuery( '#ef-comment_loading' ).show();

		// Prepare data
		post.action = 'editflow_ajax_insert_comment';
		post.parent =
			jQuery( '#ef-comment_parent' ).val() == '' ? 0 : jQuery( '#ef-comment_parent' ).val();
		post._nonce = jQuery( '#ef_comment_nonce' ).val();
		post.post_id = jQuery( '#ef-post_id' ).val();
		post.notification = jQuery( '#ef-reply-notifier' ).val();

		// Send the request
		jQuery.ajax( {
			type: 'POST',
			url: ajaxurl ? ajaxurl : wpListL10n.url,
			data: post,
			success( x ) {
				editorialCommentReply.show( x );
			},
			error( r ) {
				editorialCommentReply.error( r );
			},
		} );

		return false;
	},

	/**
	 * Display who will be notified of the new comment.
	 */
	notifiedMessage() {
		const message_wrapper = jQuery( '#ef-reply-notifier' );

		if ( ! message_wrapper[ 0 ] ) {
			return;
		}

		const subscribed_users = jQuery( '.ef-post_following_list li input:checkbox:checked' );

		// No users will be notified, so return early with a default message.
		if ( 0 === subscribed_users.length ) {
			message_wrapper.addClass( 'ef-none-selected' );
			message_wrapper.val( __ef_localize_post_comment.none_notified );
			return;
		}

		const usernames = [];
		subscribed_users.each( function () {
			// Add usernames of checked users to the list if they don't have a blocking class
			if (
				! jQuery( this )
					.siblings()
					.is( '.post_following_list-no_email,.post_following_list-no_access' ) &&
				! jQuery( this ).hasClass( 'post_following_list-current_user' )
			) {
				usernames.push(
					jQuery( this ).parent().siblings( '.ef-user_displayname, .ef-usergroup_name' ).text()
				);
			}
		} );

		// Convert array of usernames into a sentence.
		let message = usernames.pop();
		if ( usernames.length > 0 ) {
			message = usernames.join( ', ' ) + ' ' + __ef_localize_post_comment.and + ' ' + message + '.';
		}

		message_wrapper.removeClass( 'ef-none-selected' );
		message_wrapper.val( message );
	},

	show( xml ) {
		let response;
		let comment;
		let supplemental;
		let id;
		let bg;

		// Didn't pass validation, so let's throw an error
		if ( typeof xml === 'string' ) {
			this.error( { responseText: xml } );
			return false;
		}

		// Parse the response
		response = wpAjax.parseAjaxResponse( xml );
		if ( response.errors ) {
			// Uh oh, errors found
			this.error( { responseText: wpAjax.broken } );
			return false;
		}

		response = response.responses[ 0 ];
		comment = response.data;
		supplemental = response.supplemental;

		jQuery( comment ).hide();

		if ( response.action.indexOf( 'reply' ) == -1 || ! ef_thread_comments ) {
			// Not a reply, so add it to the bottom
			jQuery( '#ef-comments' ).append( comment );
		} else {
			// This is a reply, so add it after the comment replied to

			if ( jQuery( '#ef-replyrow' ).parent().next().is( 'ul' ) ) {
				// Already been replied to, so just add to the list
				jQuery( '#ef-replyrow' ).parent().next().append( comment );
			} else {
				// This is a first reply, so create an unordered list to house the comment
				const newUL = jQuery( '<ul></ul>' ).addClass( 'children' ).append( comment );
				jQuery( '#ef-replyrow' ).parent().after( newUL );
			}
		}

		// Get the comment contaner's id
		this.o = id = '#comment-' + response.id;
		// Close the reply box
		this.revert();

		// Show the new comment
		jQuery( id )
			.animate( { backgroundColor: '#CCEEBB' }, 600 )
			.animate( { backgroundColor: '#fff' }, 600 );
	},

	error( r ) {
		// Oh noes! We haz an error!
		jQuery( '#ef-comment_loading' ).hide();

		if ( r.responseText ) {
			er = r.responseText.replace( /<.[^<>]*?>/g, '' );
		}

		let message = '';
		try {
			er = JSON.parse( er );
			message = er.message ?? er;
		} catch ( parseErr ) {
			message = er;
		}

		if ( er ) {
			jQuery( '#ef-replysubmit .error' ).html( message ).show();
		}
	},
};
