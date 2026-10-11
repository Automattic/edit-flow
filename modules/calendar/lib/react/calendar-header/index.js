/* global EF_CALENDAR */

/**
 * External dependencies
 */
import { Snackbar, Animate } from '@wordpress/components';
import { dispatch, withSelect, registerStore } from '@wordpress/data';
import classnames from 'classnames';
import PropTypes from 'prop-types';
import React from 'react';

/**
 * Internal dependencies
 */
import { CalendarDateChangeButtons } from '../calendar-date-change-buttons';
import { CalendarFilters } from '../calendar-filters';
import './style.react.scss';

const DEFAULT_STORE_STATE = {
	calendarSnackbarMessage: null,
	calendarIsLoading: false,
	quickEditPost: null,
};

registerStore( 'edit-flow/calendar', {
	reducer( state = DEFAULT_STORE_STATE, action ) {
		switch ( action.type ) {
			case 'SET_POST_SAVED':
				return {
					...state,
					calendarSnackbarMessage: action.message,
					calendarIsLoading: false,
				};
			case 'CLEAR_CALENDAR_SNACKBAR_MESSAGE':
				return {
					...state,
					calendarSnackbarMessage: null,
				};
			case 'SET_CALENDAR_IS_LOADING':
				return {
					...state,
					calendarIsLoading: action.isLoading,
				};
			case 'OPEN_QUICK_EDIT':
				return {
					...state,
					quickEditPost: action.post,
				};
			case 'CLOSE_QUICK_EDIT':
				return {
					...state,
					quickEditPost: null,
				};
		}

		return state;
	},
	actions: {
		setPostSaved( message ) {
			return {
				type: 'SET_POST_SAVED',
				message,
			};
		},

		clearCalendarSnackbarMessage() {
			return {
				type: 'CLEAR_CALENDAR_SNACKBAR_MESSAGE',
			};
		},

		setCalendarIsLoading( isLoading ) {
			return {
				type: 'SET_CALENDAR_IS_LOADING',
				isLoading,
			};
		},

		/**
		 * @param {Object} post Post to open, as { id, title }.
		 */
		openQuickEdit( post ) {
			return {
				type: 'OPEN_QUICK_EDIT',
				post,
			};
		},

		closeQuickEdit() {
			return {
				type: 'CLOSE_QUICK_EDIT',
			};
		},
	},
	selectors: {
		getCalendarSnackbarMessage( state ) {
			return state.calendarSnackbarMessage;
		},

		getCalendarIsLoading( state ) {
			return state.calendarIsLoading;
		},

		getQuickEditPost( state ) {
			return state.quickEditPost;
		},
	},
} );

// This bundle has its own copy of @wordpress/data, so the store is not in the global
// wp.data registry. Hand its actions to calendar.js, which runs outside the bundle.
window.EF_CALENDAR_STORE = dispatch( 'edit-flow/calendar' );

const CalendarHeader = ( {
	snackbarMessage,
	isLoading,
	filters,
	filterValues,
	numberOfWeeks,
	beginningOfWeek,
	pageUrl,
} ) => {
	return (
		<div className="ef-calendar-header">
			<CalendarFilters isLoading={ isLoading } pageUrl={ pageUrl } filters={ filters } />
			<CalendarDateChangeButtons
				beginningOfWeek={ beginningOfWeek }
				pageUrl={ pageUrl }
				numberOfWeeks={ numberOfWeeks }
				filterValues={ filterValues }
			/>
			{ snackbarMessage ? (
				<Animate options={ { origin: 'bottom left' } } type="appear">
					{ ( { className } ) => (
						<Snackbar className={ classnames( className, 'ef-calendar-snackbar' ) }>
							<div>{ snackbarMessage }</div>
						</Snackbar>
					) }
				</Animate>
			) : null }
		</div>
	);
};

CalendarHeader.propTypes = {
	filters: PropTypes.arrayOf(
		PropTypes.shape( {
			name: PropTypes.string,
			filterType: PropTypes.string,
			label: PropTypes.string,
			options: PropTypes.arrayOf(
				PropTypes.shape( {
					label: PropTypes.string,
					value: PropTypes.any,
				} )
			),
			initialValue: PropTypes.any,
		} )
	),
	filterValues: PropTypes.object, // FilterValues is an object of key value pairs
	numberOfWeeks: PropTypes.number,
	beginningOfWeek: PropTypes.string, // Formatted 'YYYY-MM-DD'
	pageUrl: PropTypes.string,
	snackbarMessage: PropTypes.string,
	isLoading: PropTypes.bool,
};

const CalendarHeaderWithData = withSelect( select => {
	const { getCalendarSnackbarMessage, getCalendarIsLoading } = select( 'edit-flow/calendar' );

	return {
		snackbarMessage: getCalendarSnackbarMessage(),
		isLoading: getCalendarIsLoading(),
	};
} )( CalendarHeader );

export { CalendarHeaderWithData as CalendarHeader };
