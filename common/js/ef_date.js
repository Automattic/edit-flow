/* global document, jQuery, ef_week_first_day, wp */

jQuery( document ).ready( function( $ ) {
	/**
	 * Check if we're in the Gutenberg editor.
	 *
	 * @return {boolean} True if Gutenberg is available.
	 */
	function isGutenberg() {
		return typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select;
	}

	/**
	 * Check if Gutenberg post entity is ready for meta updates.
	 *
	 * @return {boolean} True if ready.
	 */
	function isGutenbergReady() {
		if ( ! isGutenberg() ) {
			return false;
		}
		try {
			const postType = wp.data.select( 'core/editor' ).getCurrentPostType();
			return !! postType;
		} catch ( e ) {
			return false;
		}
	}

	/**
	 * Update Gutenberg's data store with the meta value.
	 * This ensures that when the user saves, the REST API includes our updated meta.
	 *
	 * @param {string} metaKey   The post meta key.
	 * @param {*}      metaValue The value to save (will be converted to string).
	 */
	function updateGutenbergMeta( metaKey, metaValue ) {
		if ( ! isGutenbergReady() ) {
			return;
		}

		const meta = {};
		// Convert to string since REST API expects string type.
		meta[ metaKey ] = String( metaValue );

		wp.data.dispatch( 'core/editor' ).editPost( { meta: meta } );
	}

	/**
	 * Update the hidden field with combined date and time values.
	 * The hidden field stores the value in 'Y-m-d H:i' format for PHP processing.
	 * Also updates Gutenberg's data store with the Unix timestamp.
	 *
	 * @param {jQuery}  $dateInput      The date input element.
	 * @param {boolean} updateGutenberg Whether to update Gutenberg store (default true).
	 */
	function updateHiddenField( $dateInput, updateGutenberg ) {
		if ( typeof updateGutenberg === 'undefined' ) {
			updateGutenberg = true;
		}

		// Derive related element IDs from the date input ID.
		// Date input: {key}_date, Time input: {key}_time, Hidden: {key}_hidden
		const baseId = $dateInput.attr( 'id' ).replace( /_date$/, '' );
		const $timeInput = $( '#' + baseId + '_time' );
		const $hiddenInput = $( '#' + baseId + '_hidden' );

		if ( ! $hiddenInput.length ) {
			return;
		}

		// Get the date value from the datepicker's altField mechanism or parse it.
		let dateValue = $dateInput.datepicker( 'getDate' );
		if ( ! dateValue ) {
			$hiddenInput.val( '' );
			if ( updateGutenberg ) {
				updateGutenbergMeta( baseId, '' );
			}
			return;
		}

		// Format date as Y-m-d.
		const year = dateValue.getFullYear();
		const month = String( dateValue.getMonth() + 1 ).padStart( 2, '0' );
		const day = String( dateValue.getDate() ).padStart( 2, '0' );
		const formattedDate = year + '-' + month + '-' + day;

		// Get time value (HH:mm format from HTML5 time input).
		let timeValue = $timeInput.val() || '00:00';

		// Combine into 'Y-m-d H:i' format for the hidden field.
		$hiddenInput.val( formattedDate + ' ' + timeValue );

		if ( updateGutenberg ) {
			// Calculate Unix timestamp for Gutenberg.
			const timeParts = timeValue.split( ':' );
			const hours = parseInt( timeParts[ 0 ], 10 ) || 0;
			const minutes = parseInt( timeParts[ 1 ], 10 ) || 0;

			// Create a new Date object with the combined date and time.
			const combinedDate = new Date( year, dateValue.getMonth(), dateValue.getDate(), hours, minutes, 0 );
			const timestamp = Math.floor( combinedDate.getTime() / 1000 );

			// Update Gutenberg's data store with the Unix timestamp (as string).
			updateGutenbergMeta( baseId, timestamp );
		}
	}

	// Initialize jQuery UI datepicker on .date-pick elements.
	const $datePicks = $( '.date-pick' );

	$datePicks.each( function() {
		const $datePicker = $( this );

		$datePicker.datepicker( {
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day,
			showButtonPanel: true,
			onSelect: function() {
				updateHiddenField( $datePicker, true );
			},
		} );

		// Update hidden field when date input changes (e.g., manual input).
		$datePicker.on( 'change', function() {
			updateHiddenField( $datePicker, true );
		} );
	} );

	// Update hidden field when time input changes.
	$( '.time-pick' ).on( 'change', function() {
		const $timeInput = $( this );
		// Derive the date input from the time input ID.
		const baseId = $timeInput.attr( 'id' ).replace( /_time$/, '' );
		const $dateInput = $( '#' + baseId + '_date' );

		if ( $dateInput.length ) {
			updateHiddenField( $dateInput, true );
		}
	} );

	// Initialize hidden fields with current values on page load.
	// Do NOT update Gutenberg here - just set up the hidden field for form submission.
	$datePicks.each( function() {
		const $datePicker = $( this );
		if ( $datePicker.val() ) {
			updateHiddenField( $datePicker, false );
		}
	} );

	/**
	 * Sync all Editorial Metadata fields to Gutenberg.
	 * This handles text, paragraph, checkbox, user, number, and location fields.
	 */
	function setupMetaboxSync() {
		const $metaBox = $( '#ef_editorial_meta_meta_box' );

		if ( ! $metaBox.length || ! isGutenberg() ) {
			return;
		}

		// Text, paragraph, number, and location inputs.
		$metaBox.find( 'input[type="text"]:not(.date-pick), textarea, select' ).on( 'change input', function() {
			const $input = $( this );
			const name = $input.attr( 'name' );

			// Skip hidden fields and fields without names.
			if ( ! name || name.endsWith( '_hidden' ) ) {
				return;
			}

			updateGutenbergMeta( name, $input.val() );
		} );

		// Checkbox inputs.
		$metaBox.find( 'input[type="checkbox"]' ).on( 'change', function() {
			const $input = $( this );
			const name = $input.attr( 'name' );

			if ( ! name ) {
				return;
			}

			// Store as '1' or '' to match PHP behavior.
			const value = $input.is( ':checked' ) ? '1' : '';
			updateGutenbergMeta( name, value );
		} );
	}

	// Set up metabox sync for Gutenberg.
	setupMetaboxSync();
} );
