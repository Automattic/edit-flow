/* global EF_CALENDAR */

/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import moment from 'moment';
import PropTypes from 'prop-types';
import React from 'react';

// Get rid of this eventually
const BUTTON_TYPE_PROPS =
	parseFloat( EF_CALENDAR.WP_VERSION ) >= 5.4 ? { isSecondary: true } : { isDefault: true };

/**
 * Internal dependencies
 */
import './style.react.scss';

/**
 * Used to shift the calendar forwards or backwards by some number of weeks
 * @param {string} addOrSubtract The valid values for this are 'add'|'subtract'
 * @param {string} beginningOfWeek A date string formatted like 'YYYY-MM-DD'
 * @param {string} pageUrl The url of the page the query parameters are going to be appended to
 * @param {object} filterValues An object of string key and string value pairs representing filter names and values
 * @param {number} weeksNumber The number of weeks to shift by
 * @returns {string} the url with query params
 */
const moveByWeeks = ( addOrSubtract, beginningOfWeek, pageUrl, filterValues, weeksNumber ) => {
	const queryArgFilters = { ...filterValues };

	if ( weeksNumber === 0 ) {
		queryArgFilters.start_date = beginningOfWeek;
	}

	queryArgFilters.start_date = moment( queryArgFilters.start_date, 'YYYY-MM-DD' )
		[ addOrSubtract ]( weeksNumber, 'weeks' )
		.format( 'YYYY-MM-DD' );

	return addQueryArgs( pageUrl, queryArgFilters );
};

/**
 * A curried function leveraging `moveByWeek` that returns a URL with query parameters applied
 * that will shift the calendar forward by `weeksNumber`
 * @param {number} weeksNumber A number representing the weeks to move forward
 * @param {string} beginningOfWeek A date string formatted like 'YYYY-MM-DD'
 * @param {string} pageUrl The url of the page the query parameters are going to be appended to
 * @param {object} filterValues An object of string key and string value pairs representing filter names and values
 * @returns {string} the url with query params
 */
const moveFowardByWeeks = ( weeksNumber, beginningOfWeek, pageUrl, filterValues ) => {
	return moveByWeeks( 'add', beginningOfWeek, pageUrl, filterValues, weeksNumber );
};

/**
 * A curried function leveraging `moveByWeek` that returns a URL with query parameters applied
 * that will shift the calendar backwards by `weeksNumber`
 * @param {number} weeksNumber A number representing the weeks to move forward
 * @param {string} beginningOfWeek A date string formatted like 'YYYY-MM-DD'
 * @param {string} pageUrl The url of the page the query parameters are going to be appended to
 * @param {object} filterValues An object of string key and string value pairs representing filter names and values
 * @returns {string} the url with query params
 */
const moveBackByWeeks = ( weeksNumber, beginningOfWeek, pageUrl, filterValues ) => {
	return moveByWeeks( 'subtract', beginningOfWeek, pageUrl, filterValues, weeksNumber );
};

const CalendarDateChangeButtons = ( { numberOfWeeks, beginningOfWeek, pageUrl, filterValues } ) => {
	return (
		<div className="ef-calendar-date-change-buttons">
			{ numberOfWeeks > 1 ? (
				<Button
					{ ...BUTTON_TYPE_PROPS }
					className="ef-calendar-date-change-button"
					title={ sprintf( __( 'Backwards %d weeks', 'edit-flow' ), numberOfWeeks ) }
					href={ moveBackByWeeks( numberOfWeeks, beginningOfWeek, pageUrl, filterValues ) }
				>
					{ __( '«', 'edit-flow' ) }
				</Button>
			) : null }

			<Button
				{ ...BUTTON_TYPE_PROPS }
				className="ef-calendar-date-change-button"
				title={ __( 'Backwards 1 week', 'edit-flow' ) }
				href={ moveBackByWeeks( 1, beginningOfWeek, pageUrl, filterValues ) }
			>
				{ __( '‹', 'edit-flow' ) }
			</Button>
			<Button
				{ ...BUTTON_TYPE_PROPS }
				className="ef-calendar-date-change-button"
				title={ __( 'Today', 'edit-flow' ) }
				href={ moveFowardByWeeks( 0, beginningOfWeek, pageUrl, filterValues ) }
			>
				{ __( 'Today', 'edit-flow' ) }
			</Button>
			<Button
				{ ...BUTTON_TYPE_PROPS }
				className="ef-calendar-date-change-button"
				title={ __( 'Forward 1 week', 'edit-flow' ) }
				href={ moveFowardByWeeks( 1, beginningOfWeek, pageUrl, filterValues ) }
			>
				{ __( '›', 'edit-flow' ) }
			</Button>

			{ numberOfWeeks > 1 ? (
				<Button
					{ ...BUTTON_TYPE_PROPS }
					className="ef-calendar-date-change-button"
					title={ sprintf( __( 'Forward %d weeks', 'edit-flow' ), numberOfWeeks ) }
					href={ moveFowardByWeeks( numberOfWeeks, beginningOfWeek, pageUrl, filterValues ) }
				>
					{ __( '»', 'edit-flow' ) }
				</Button>
			) : null }
		</div>
	);
};

CalendarDateChangeButtons.propTypes = {
	numberOfWeeks: PropTypes.number,
	beginningOfWeek: PropTypes.string, // Formatted like 'YYYY-MM-DD'
	pageUrl: PropTypes.string,
	filterValues: PropTypes.object, // Object should just be k:v pairs
};

export { CalendarDateChangeButtons };
