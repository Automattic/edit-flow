import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import React from 'react'
import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

// Registers the edit-flow/calendar store.
import '../../../modules/calendar/lib/react/calendar-header';
import { CalendarQuickEdit } from '../../../modules/calendar/lib/react/calendar-quick-edit';

function mockFetchResponse( body ) {
    window.fetch = vi.fn( () => Promise.resolve( { json: () => Promise.resolve( body ) } ) );
}

function openPost() {
    act( () => {
        // The same bridge calendar.js uses to open a post.
        window.EF_CALENDAR_STORE.openQuickEdit( { id: 42, title: 'My draft' } );
    } );
}

describe( 'CalendarQuickEdit', () => {
    beforeEach( () => {
        window.ajaxurl = '/wp-admin/admin-ajax.php';
        document.body.insertAdjacentHTML( 'beforeend', '<input type="hidden" id="ef-calendar-modify" value="test-nonce" />' );
    } );

    afterEach( () => {
        act( () => {
            window.EF_CALENDAR_STORE.closeQuickEdit();
        } );
        document.getElementById( 'ef-calendar-modify' ).remove();
    } );

    test( 'renders nothing until a post is opened', () => {
        mockFetchResponse( { status: 'success', message: '' } );
        render( <CalendarQuickEdit /> );

        expect( screen.queryByRole( 'dialog' ) ).toBeNull();
        expect( window.fetch ).not.toHaveBeenCalled();
    } );

    test( 'loads and shows the post details in a modal', async () => {
        mockFetchResponse( { status: 'success', message: '<table class="item-information"><tr><td>Quick edit details</td></tr></table>' } );
        render( <CalendarQuickEdit /> );

        openPost();

        expect( screen.getByRole( 'dialog', { name: 'My draft' } ) ).toBeInTheDocument();
        expect( await screen.findByText( 'Quick edit details' ) ).toBeInTheDocument();

        const [ url, options ] = window.fetch.mock.calls[ 0 ];
        expect( url ).toBe( '/wp-admin/admin-ajax.php' );
        expect( options.body.get( 'action' ) ).toBe( 'ef_calendar_get_quick_edit' );
        expect( options.body.get( 'post_id' ) ).toBe( '42' );
        expect( options.body.get( 'nonce' ) ).toBe( 'test-nonce' );
    } );

    test( 'shows the error message when the details cannot be loaded', async () => {
        mockFetchResponse( { status: 'error', message: 'You do not have permission.' } );
        render( <CalendarQuickEdit /> );

        openPost();

        expect( await screen.findByText( 'You do not have permission.', { selector: '.components-notice__content' } ) ).toBeInTheDocument();
    } );

    test( 'shows a fallback error when the request fails', async () => {
        window.fetch = vi.fn( () => Promise.reject( new Error( 'Network error' ) ) );
        render( <CalendarQuickEdit /> );

        openPost();

        expect( await screen.findByText( 'The post details could not be loaded.', { selector: '.components-notice__content' } ) ).toBeInTheDocument();
    } );

    test( 'closes the modal', async () => {
        mockFetchResponse( { status: 'success', message: '<p>Details</p>' } );
        render( <CalendarQuickEdit /> );

        openPost();
        await screen.findByText( 'Details' );

        await userEvent.click( screen.getByRole( 'button', { name: 'Close' } ) );

        await waitFor( () => expect( screen.queryByRole( 'dialog' ) ).toBeNull() );
    } );
} );
