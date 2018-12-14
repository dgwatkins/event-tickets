/**
 * External Dependencies
 */
import React from 'react';
import PropTypes from 'prop-types';
import { __ } from '@wordpress/i18n';
import { Button } from '@moderntribe/common/elements';
import './style.pcss';

const MoveDelete = ( {
	moveTicket,
	removeTicket,
	isDisabled,
} ) => {
	return (
		<div className="tribe-editor__ticket__content-row--move-delete">
			<Button type="button" onClick={ moveTicket } disabled={ isDisabled }>
				{ __( 'Move Ticket', 'events-tickets' ) }
			</Button>
			<Button type="button" onClick={ removeTicket } disabled={ isDisabled }>
				{ __( 'Remove Ticket', 'events-tickets' ) }
			</Button>
		</div>
	);
};

MoveDelete.propTypes = {
	moveTicket: PropTypes.func.isRequired,
	removeTicket: PropTypes.func.isRequired,
	isDisabled: PropTypes.func.isRequired,
};

export default MoveDelete;
