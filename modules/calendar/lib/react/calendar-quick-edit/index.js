/* global document */

/**
 * External dependencies
 */
import { Modal, Notice, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import React, { useEffect, useState } from 'react';

/**
 * Internal dependencies
 */
import './style.react.scss';

/**
 * Shows the details of a calendar post in a modal.
 *
 * The details are server-rendered HTML, so the existing PHP filters and actions
 * keep working, and calendar.js handles the inline editing inside it.
 */
const CalendarQuickEdit = () => {
	const post = useSelect( select => select( 'edit-flow/calendar' ).getQuickEditPost(), [] );
	const { closeQuickEdit } = useDispatch( 'edit-flow/calendar' );
	const [ html, setHtml ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! post ) {
			return;
		}

		const loadError = __( 'The post details could not be loaded.', 'edit-flow' );
		const nonceField = document.getElementById( 'ef-calendar-modify' );
		const body = new window.FormData();
		body.append( 'action', 'ef_calendar_get_quick_edit' );
		body.append( 'nonce', nonceField ? nonceField.value : '' );
		body.append( 'post_id', post.id );

		// Ignore a late response if another post was opened in the meantime.
		let ignore = false;
		setHtml( null );
		setError( null );

		window
			.fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body } )
			.then( response => response.json() )
			.then( response => {
				if ( ignore ) {
					return;
				}
				if ( response && 'success' === response.status ) {
					setHtml( response.message );
				} else {
					setError( ( response && response.message ) || loadError );
				}
			} )
			.catch( () => {
				if ( ! ignore ) {
					setError( loadError );
				}
			} );

		return () => {
			ignore = true;
		};
	}, [ post ] );

	if ( ! post ) {
		return null;
	}

	return (
		<Modal
			title={ post.title }
			onRequestClose={ closeQuickEdit }
			className="ef-calendar-quick-edit-modal"
			size="medium"
		>
			{ error ? (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) : null }
			{ ! error && null === html ? <Spinner /> : null }
			{ ! error && null !== html ? (
				// The markup comes from our own endpoint, where get_inner_information() escapes it.
				<div className="ef-calendar-quick-edit" dangerouslySetInnerHTML={ { __html: html } } />
			) : null }
		</Modal>
	);
};

export { CalendarQuickEdit };
