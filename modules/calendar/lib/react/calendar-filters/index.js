/* global EF_CALENDAR */

/**
 * External Dependencies
 */
import { SelectControl, Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import PropTypes from 'prop-types';
import React from 'react';

/**
 * Internal Dependencies
 */
import { ComboBox } from '../combobox';
import './style.react.scss';

function init( { filters } ) {
	return {
		...filters.reduce( ( acc, next ) => {
			const filter = {
				[ next.name ]: next.initialValue || '',
			};

			if ( next.filterType === 'combobox' ) {
				filter[ `${ next.name }InputValue` ] = next.initialValue ? next.initialValue.name : '';
			}

			return {
				...acc,
				...filter,
			};
		}, [] ),
	};
}

class CalendarFilters extends React.Component {
	constructor( props ) {
		super( props );

		this.state = init( props );
		this.formRef = React.createRef();
	}

	updateFilter( { name, value } ) {
		this.setState( {
			...this.state,
			[ name ]: value,
		} );
	}

	render() {
		const { filters, pageUrl, isLoading } = this.props;
		const state = this.state;

		return (
			<div className="ef-calendar-navigation">
				<div className="ef-calendar-filters">
					<form ref={ this.formRef } action="" method="GET" className="ef-calendar-filters-form">
						<input type="hidden" name="page" value="calendar" />
						{ filters.map( filter => {
							switch ( filter.filterType ) {
								case 'select':
									return (
										<div
											className={ `ef-calendar-filter ef-calendar-filter-${ filter.name }` }
											key={ `ef-calendar-filter-${ filter.name }` }
										>
											<SelectControl
												key={ filter.name }
												name={ filter.name }
												label={ filter.label }
												value={ state[ filter.name ] }
												options={ filter.options }
												onChange={ newValue =>
													this.updateFilter( {
														name: filter.name,
														value: newValue,
													} )
												}
												__next40pxDefaultSize={ true }
												__nextHasNoMarginBottom={ true }
											/>
										</div>
									);
								case 'combobox':
									return (
										<div
											className={ `ef-calendar-filter ef-calendar-filter-${ filter.name }` }
											key={ `ef-calendar-filter-${ filter.name }` }
										>
											<ComboBox
												key={ filter.name }
												className="ef-calendar-filter-combobox"
												inputId={ `ef-calendar-filter-${ filter.name }` }
												inputLabel={ filter.inputLabel }
												buttonOpenLabel={ filter.buttonOpenLabel }
												buttonCloseLabel={ filter.buttonCloseLabel }
												buttonClearLabel={ filter.buttonClearLabel }
												placeholder={ filter.placeholder }
												items={ filter.options }
												selectedItem={ state[ filter.name ] }
												inputValue={ state[ `${ filter.name }InputValue` ] }
												itemToString={ item => ( item ? item.name : '' ) }
												onInputBlur={ ( items, inputValue ) => {
													/**
													 * If this is set, if a user has typed out a name
													 * and it matches an item in the list, select it for them
													 */
													if (
														! filter.selectFirstItemOnBlur ||
														items.length < 1 ||
														! inputValue ||
														inputValue.toLowerCase() !== items[ 0 ].name.toLowerCase()
													) {
														return;
													}

													this.updateFilter( {
														name: filter.name,
														value: items[ 0 ],
													} );
												} }
												onStateChange={ changes => {
													if ( changes.hasOwnProperty( 'selectedItem' ) ) {
														this.updateFilter( {
															name: filter.name,
															value: changes.selectedItem,
														} );
													} else if ( changes.hasOwnProperty( 'inputValue' ) ) {
														this.updateFilter( {
															name: `${ filter.name }InputValue`,
															value: changes.inputValue,
														} );
													}
												} }
											/>
											<input
												key={ `${ filter.name }-input` }
												type="hidden"
												name={ filter.name }
												value={ state[ filter.name ] ? state[ filter.name ].value : '' }
											/>
										</div>
									);
							}
						} ) }
						<div className="ef-calendar-filters-buttons">
							<Button type="submit" isPrimary={ true } __next40pxDefaultSize={ true }>
								{ __( 'Apply', 'edit-flow' ) }
							</Button>
							<Button
								type="button"
								href={ addQueryArgs(
									pageUrl,
									filters.reduce( ( acc, filter ) => {
										return {
											...acc,
											[ filter.name ]: '',
										};
									}, {} )
								) }
								name="ef-calendar-reset-filters"
								isSecondary={ true }
								__next40pxDefaultSize={ true }
							>
								{ __( 'Reset', 'edit-flow' ) }
							</Button>
							{ isLoading ? <Spinner /> : null }
						</div>
					</form>
				</div>
			</div>
		);
	}
}

CalendarFilters.propTypes = {
	filters: PropTypes.arrayOf(
		PropTypes.shape( {
			name: PropTypes.string,
			filterType: PropTypes.string,
			label: PropTypes.string,
			options: PropTypes.arrayOf(
				PropTypes.shape( {
					name: PropTypes.string,
					value: PropTypes.any,
				} )
			),
			initialValue: PropTypes.any,
		} )
	),
	pageUrl: PropTypes.string,
	isLoading: PropTypes.bool,
};

export { CalendarFilters };
