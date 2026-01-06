/* global EF_CALENDAR */

/**
 * External dependencies
 */
import { Button, IconButton } from '@wordpress/components';
import classnames from 'classnames';
import Downshift from 'downshift';
import { matchSorter } from 'match-sorter';
import PropTypes from 'prop-types';
import React from 'react';

// Get rid of this eventually
const ACTIVE_ICON_BUTTON = parseFloat( EF_CALENDAR.WP_VERSION ) >= 5.3 ? Button : IconButton;

/**
 * Internal dependencies
 */
import './style.react.scss';

/**
 * Filters items based on simple name text match
 *
 * @param {string} filter a string to filter items by
 * @param {Item[]} items a list of items to be filtered
 * @return {string[]} array of strings that match
 */
function getItems( filter, items ) {
	return filter
		? matchSorter( items, filter, {
				keys: [ 'name' ],
		  } )
		: items;
}

/**
 * Find an item by Id
 * @param {Item[]} items a list of items
 * @param {*} id an id to find
 * @return {Item} an item with the id
 */
function getItem( items, id ) {
	return items.find( item => item.value === id );
}

/**
 * An item that can be supplied to <Combobox>
 * @typedef {Object} Item
 * @property {string} name - The name of the item, used for filtering results.
 * @property {string|number} id - The unique identifier for the item
 * @property {string|number} [parent] - An optional identifier for a parent
 * @property {number} [level] - An optional identifier designating nesting level
 */

// A combobox supporting <select> with search
const ComboBox = ( {
	className,
	placeholder,
	inputLabel,
	inputId,
	buttonOpenLabel,
	buttonCloseLabel,
	buttonClearLabel,
	items,
	noMatchText = 'No items match',
	onInputBlur,
	...comboboxPropsRest
} ) => {
	return (
		<div className={ classnames( 'ef-combobox', className ) }>
			<Downshift { ...comboboxPropsRest }>
				{ ( {
					getInputProps,
					getToggleButtonProps,
					getMenuProps,
					getItemProps,
					isOpen,
					openMenu,
					clearSelection,
					selectedItem,
					inputValue,
					highlightedIndex,
				} ) => {
					let foundItems = [];
					let filteredItems = [];

					if ( isOpen ) {
						filteredItems = getItems( inputValue, items );
						foundItems = filteredItems.map( ( item, index ) => {
							return (
								<li
									aria-label={ item.name }
									className={ classnames( {
										'is-active': highlightedIndex === index,
									} ) }
									key={ item.value }
									{ ...getItemProps( {
										item,
										index,
									} ) }
								>
									{ item.level && ! inputValue
										? new Array( item.level ).fill( '\xa0' ).join( '' )
										: null }
									{ item.parent && inputValue ? (
										<span className="ef-combobox-item-parent">
											{ getItem( items, item.parent ).name }
										</span>
									) : null }
									{ item.parent && inputValue ? '\xa0' : null }
									{ item.name }
								</li>
							);
						} );
					}

					if ( isOpen && foundItems.length < 1 ) {
						foundItems = [
							<li
								aria-label={ noMatchText }
								className="disabled"
								key="no-items-match"
								{ ...getItemProps( {
									item: noMatchText,
									disabled: true,
								} ) }
							>
								{ noMatchText }
							</li>,
						];
					}

					return (
						<div>
							{ inputLabel && (
								<label
									className="components-base-control__label ef-combobox-label"
									htmlFor={ inputId }
								>
									{ inputLabel }
								</label>
							) }
							<div className="ef-combobox-input-wrapper">
								<input
									className={ classnames(
										{ 'is-open': isOpen },
										'ef-combobox-input components-text-control__input'
									) }
									{ ...getInputProps( {
										id: inputId,
										onBlur: () => {
											onInputBlur && onInputBlur( filteredItems, inputValue );
										},
										onFocus: openMenu,
										type: 'text',
										placeholder,
									} ) }
								/>
								{ selectedItem ? (
									<ACTIVE_ICON_BUTTON
										{ ...getToggleButtonProps( {
											'aria-label': buttonClearLabel,
										} ) }
										onClick={ clearSelection }
										key="no-alt"
										className="ef-combobox-input-button"
										icon="no-alt"
									/>
								) : (
									<ACTIVE_ICON_BUTTON
										{ ...getToggleButtonProps( {
											'aria-label': isOpen ? buttonCloseLabel : buttonOpenLabel,
										} ) }
										className="ef-combobox-input-button"
										icon={ isOpen ? 'arrow-up-alt2' : 'arrow-down-alt2' }
									/>
								) }
							</div>
							<ul
								className={ classnames( 'ef-combobox-menu-wrapper', {
									'ef-combobox-menu-wrapper-hidden': ! isOpen,
								} ) }
								{ ...getMenuProps() }
							>
								{ isOpen ? foundItems : null }
							</ul>
						</div>
					);
				} }
			</Downshift>
		</div>
	);
};

ComboBox.propTypes = {
	className: PropTypes.string,
	placeholder: PropTypes.string,
	inputLabel: PropTypes.string,
	inputId: PropTypes.string,
	buttonOpenLabel: PropTypes.string,
	buttonCloseLabel: PropTypes.string,
	buttonClearLabel: PropTypes.string,
	label: PropTypes.string,
	items: PropTypes.arrayOf(
		PropTypes.shape( {
			name: PropTypes.string.isRequired,
			id: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
			parent: PropTypes.oneOfType( [ PropTypes.string, PropTypes.number ] ),
			level: PropTypes.number,
		} )
	),
	noMatchText: PropTypes.string, // What to display in the menu dropdown if no items match
	onInputBlur: PropTypes.func, // Arguments to function: (filtered items at the time of blur, the value in the input)
};

export { ComboBox };
