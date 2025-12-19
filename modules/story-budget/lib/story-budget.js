// Story Budget specific JS, assumes that ef_date.js has already been included

jQuery( document ).ready( function ( $ ) {
	// Hide/show a single category section when clicking the toggle button
	$( 'button.handlediv' ).on( 'click', function () {
		const $postbox = $( this ).closest( '.postbox' );
		const $inside = $postbox.children( 'div.inside' );
		const isExpanded = $( this ).attr( 'aria-expanded' ) === 'true';

		$inside.toggle();
		$postbox.toggleClass( 'closed', isExpanded );
		$( this ).attr( 'aria-expanded', ! isExpanded );
	} );

	// Change number of columns when choosing a new number from Screen Options
	const columnsSwitch = $( 'input[name=ef_story_budget_screen_columns]' );
	columnsSwitch.on( 'click', function () {
		const numColumns = parseInt( $( this ).val() );
		const classPrefix = 'columns-number-';
		$( '.postbox-container' )
			.removeClass( function () {
				for ( var index = 1, c = []; index <= columnsSwitch.length; index++ ) {
					c.push( classPrefix + index );
				}
				return c.join( ' ' );
			} )
			.addClass( classPrefix + numColumns );
	} );

	// Toggle excerpts visibility instantly from Screen Options
	$( 'input[name=ef_story_budget_show_excerpts]' ).on( 'change', function () {
		if ( $( this ).is( ':checked' ) ) {
			$( '#ef-story-budget-wrap' ).addClass( 'show-excerpts' );
		} else {
			$( '#ef-story-budget-wrap' ).removeClass( 'show-excerpts' );
		}
	} );

	// Toggle empty categories visibility instantly from Screen Options
	$( 'input[name=ef_story_budget_hide_empty_terms]' ).on( 'change', function () {
		if ( $( this ).is( ':checked' ) ) {
			$( '#ef-story-budget-wrap' ).addClass( 'hide-empty-terms' );
		} else {
			$( '#ef-story-budget-wrap' ).removeClass( 'hide-empty-terms' );
		}
	} );
} );
