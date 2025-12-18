/* global EF_CALENDAR, document */

/**
 * External dependencies
 */
import { _n, __, sprintf } from '@wordpress/i18n';
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import { CalendarHeader } from './calendar-header';
import './style.react.scss';

// See: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/flat
function flatDeep( arr, d = 1 ) {
	return d > 0
		? arr.reduce(
				( acc, val ) => acc.concat( Array.isArray( val ) ? flatDeep( val, d - 1 ) : val ),
				[]
		  )
		: arr.slice();
}

/**
 * Recursively organizes the items into Parent -> Child -> Child nested array
 * @param {*} items A list of items represent
 * @param {*} parent This is used recursively, to identify the child items parent
 * @param {*} level This is to identify the current level of nesting
 * @returns {array} Array of organized items
 */
function organizeItems( items, parent = 0, level = 0 ) {
	return items
		.filter( item => item.parent === parent )
		.map( item => {
			return [ { ...item, level } ].concat( organizeItems( items, item.value, level + 1 ) );
		} );
}

const EF_USER_MAP = ( { id: value, display_name: name } ) => ( { value, name } );
const EF_CATEGORY_MAP = ( { term_id: value, name, parent } ) => ( { value, name, parent } );

/**
 * The number of weeks is a drop listing the maximum number of weeks a user can select (usually 12). This creates an array and fills
 * it with null so we can map over the length of the array and fill it with the labels we need
 */
const NUM_WEEKS_OPTIONS = new Array( EF_CALENDAR.NUM_WEEKS.MAX )
	.fill( null )
	.map( ( value, index ) => ( {
		value: index + 1,
		label: sprintf( _n( '%d week', '%d weeks', index + 1, 'text-domain' ), index + 1 ),
	} ) );

const INITIAL_CATEGORY = EF_CALENDAR.CATEGORIES.filter(
	category => category.term_id === EF_CALENDAR.FILTERS.cat
).map( EF_CATEGORY_MAP )[ 0 ];
const INITIAL_USER = EF_CALENDAR.USERS.filter( user => user.id === EF_CALENDAR.FILTERS.author ).map(
	EF_USER_MAP
)[ 0 ];

/**
 * Filters are hardcoded here for the moment, eventually should introduce some filtering to support custom filters
 * Maybe support applyFilters for folks who want to wrap an HOC around <CalendarFilters />
 */
const filters = [
	{
		name: 'post_status',
		filterType: 'select',
		label: __( 'Status', 'edit-flow' ),
		options: [ { value: '', label: __( 'All statuses', 'edit-flow' ) } ].concat(
			EF_CALENDAR.POST_STATI.map( ( { name: value, label } ) => ( { value, label } ) )
		),
		initialValue: EF_CALENDAR.FILTERS.post_status,
	},
	{
		name: 'author',
		filterType: 'combobox',
		inputLabel: __( 'Author', 'edit-flow' ),
		buttonOpenLabel: __( 'Open user menu', 'edit-flow' ),
		buttonCloseLabel: __( 'Close user menu', 'edit-flow' ),
		buttonClearLabel: __( 'Clear user selection', 'edit-flow' ),
		placeholder: __( 'Select a user', 'edit-flow' ),
		options: EF_CALENDAR.USERS.map( EF_USER_MAP ),
		initialValue: INITIAL_USER ? INITIAL_USER : null,
		selectFirstItemOnBlur: true,
	},
	{
		name: 'cat',
		filterType: 'combobox',
		inputLabel: __( 'Category', 'edit-flow' ),
		buttonOpenLabel: __( 'Open category menu', 'edit-flow' ),
		buttonCloseLabel: __( 'Close category menu', 'edit-flow' ),
		buttonClearLabel: __( 'Clear category selection', 'edit-flow' ),
		placeholder: __( 'Select a category', 'edit-flow' ),
		options: flatDeep(
			organizeItems( EF_CALENDAR.CATEGORIES.map( EF_CATEGORY_MAP ), 0 ),
			Infinity
		),
		initialValue: INITIAL_CATEGORY ? INITIAL_CATEGORY : null,
		selectFirstItemOnBlur: true,
	},
];

if ( EF_CALENDAR.POST_TYPES && EF_CALENDAR.POST_TYPES.length > 1 ) {
	filters.push( {
		name: 'cpt',
		filterType: 'select',
		label: __( 'Post Type', 'edit-flow' ),
		options: [ { value: '', label: __( 'All types', 'edit-flow' ) } ].concat(
			EF_CALENDAR.POST_TYPES.map( ( { name: value, label } ) => ( { value, label } ) )
		),
		initialValue: EF_CALENDAR.FILTERS.cpt,
	} );
}

filters.push( {
	name: 'num_weeks',
	filterType: 'select',
	label: __( 'Number of weeks', 'edit-flow' ),
	options: NUM_WEEKS_OPTIONS,
	initialValue: EF_CALENDAR.FILTERS.num_weeks,
} );

const root = createRoot( document.getElementById( 'ef-calendar-navigation-mount' ) );

root.render(
	<CalendarHeader
		numberOfWeeks={ EF_CALENDAR.FILTERS.num_weeks }
		beginningOfWeek={ EF_CALENDAR.BEGINNING_OF_WEEK }
		pageUrl={ EF_CALENDAR.PAGE_URL }
		filters={ filters }
		filterValues={ EF_CALENDAR.FILTERS }
	/>
);
