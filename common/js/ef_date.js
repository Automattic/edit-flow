/* global document, jQuery, ef_week_first_day  */

jQuery( document ).ready( function() {
	const dateTimePicks = jQuery( '.date-time-pick' );

	dateTimePicks.each( function() {
		const $dTP = jQuery( this );

		$dTP.datetimepicker( {
			dateFormat: 'M dd yy',
			firstDay: ef_week_first_day,
			alwaysSetTime: false,
			controlType: 'select',
			altField: '#' + $dTP.prop( 'id' ) + '_hidden',
			altFieldTimeOnly: false,
			altFormat: 'yy-mm-dd',
			altTimeFormat: 'HH:mm',
		} );
	} );

	const datePicks = jQuery( '.date-pick' );
	datePicks.each( function() {
		const $datePicker = jQuery( this );

		$datePicker.datepicker( {
			firstDay: ef_week_first_day,
			altField: '#' + $datePicker.prop( 'id' ) + '_hidden',
			altFormat: 'yy-mm-dd',
			showButtonPanel: true,
		} );
	} );
} );
