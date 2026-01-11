import './editor.scss';
import './style.scss';

import { SelectControl } from '@wordpress/components';
import { compose } from '@wordpress/compose';
import { subscribe, dispatch, select } from '@wordpress/data';
import { withSelect, withDispatch } from '@wordpress/data';
import { PluginPostStatusInfo } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Map Custom Statuses as options for SelectControl
 */
const statuses = window.EditFlowCustomStatuses.map( s => ( { label: s.name, value: s.slug } ) );

/**
 * Subscribe to changes so we can set a default status and update a button's text.
 */
let buttonTextObserver = null;
subscribe( function () {
	const postId = select( 'core/editor' ).getCurrentPostId();
	if ( ! postId ) {
		// Post isn't ready yet so don't do anything.
		return;
	}

	// For new posts, we need to force the default custom status.
	// Only update if the current status differs to avoid marking the post as dirty.
	const isCleanNewPost = select( 'core/editor' ).isCleanNewPost();
	if ( isCleanNewPost ) {
		const currentStatus = select( 'core/editor' ).getEditedPostAttribute( 'status' );
		if ( currentStatus !== ef_default_custom_status ) {
			dispatch( 'core/editor' ).editPost( {
				status: ef_default_custom_status,
			} );
		}
	}

	// If the save button exists, let's update the text if needed.
	maybeUpdateButtonText( document.querySelector( '.editor-post-save-draft' ) );

	// The post is being saved, so we need to set up an observer to update the button text when it's back.
	if (
		buttonTextObserver === null &&
		window.MutationObserver &&
		select( 'core/editor' ).isSavingPost()
	) {
		buttonTextObserver = createButtonObserver(
			document.querySelector( '.edit-post-header__settings' )
		);
	}
} );

/**
 * Create a mutation observer that will update the
 * save button text right away when it's changed/re-added.
 *
 * Ideally there will be better ways to go about this in the future.
 * @see https://github.com/Automattic/Edit-Flow/issues/583
 */
function createButtonObserver( parentNode ) {
	if ( ! parentNode ) {
		return null;
	}

	const observer = new MutationObserver( mutationsList => {
		for ( const mutation of mutationsList ) {
			for ( const node of mutation.addedNodes ) {
				maybeUpdateButtonText( node );
			}
		}
	} );

	observer.observe( parentNode, { childList: true } );
	return observer;
}

function maybeUpdateButtonText( saveButton ) {
	/*
	 * saveButton.children < 1 accounts for when a user hovers over the save button
	 * and a tooltip is rendered
	 */
	if (
		saveButton &&
		saveButton.children < 1 &&
		( saveButton.innerText === __( 'Save Draft' ) ||
			saveButton.innerText === __( 'Save as Pending' ) )
	) {
		saveButton.innerText = __( 'Save' );
	}
}

/**
 * Custom status component
 * @param object props
 */
const EditFlowCustomPostStati = ( { onUpdate, status } ) => (
	<PluginPostStatusInfo
		className={ `edit-flow-extended-post-status edit-flow-extended-post-status-${ status }` }
	>
		<h4>
			{ status !== 'publish'
				? __( 'Extended Post Status', 'edit-flow' )
				: __( 'Extended Post Status Disabled.', 'edit-flow' ) }
		</h4>

		{ status !== 'publish' ? (
			<SelectControl
				label=""
				value={ status }
				options={ statuses }
				onChange={ onUpdate }
				__next40pxDefaultSize={ true }
				__nextHasNoMarginBottom={ true }
			/>
		) : null }

		<small className="edit-flow-extended-post-status-note">
			{ status !== 'publish'
				? __( 'Note: this will override all status settings above.', 'edit-flow' )
				: __( 'To select a custom status, please unpublish the content first.', 'edit-flow' ) }
		</small>
	</PluginPostStatusInfo>
);

const mapSelectToProps = select => {
	return {
		status: select( 'core/editor' ).getEditedPostAttribute( 'status' ),
	};
};

const mapDispatchToProps = dispatch => {
	return {
		onUpdate( status ) {
			dispatch( 'core/editor' ).editPost( { status } );
		},
	};
};

const plugin = compose(
	withSelect( mapSelectToProps ),
	withDispatch( mapDispatchToProps )
)( EditFlowCustomPostStati );

/**
 * Kick it off
 */
registerPlugin( 'edit-flow-custom-status', {
	icon: 'edit-flow',
	render: plugin,
} );
