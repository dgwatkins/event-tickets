/**
 * External dependencies
 */
import { connect } from 'react-redux';
import { compose } from 'redux';

/**
 * WordPress dependencies
 */
import { applyFilters } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import Template from './template';
import { withStore } from '@moderntribe/common/hoc';
import withSaveData from '@moderntribe/tickets/blocks/hoc/with-save-data';
import { actions, selectors } from '@moderntribe/tickets/data/blocks/ticket';
import { getShowUneditableTickets } from './container/container';
import {
	hasRecurrenceRules,
	noTicketsOnRecurring,
} from '@moderntribe/common/utils/recurrence';

const mapStateToProps = (state, ownProps) => {
	const headerImageId = selectors.getTicketsHeaderImageId(state);
	let mappedProps = {
		header: headerImageId ? `${headerImageId}` : '',
		hasProviders: selectors.hasTicketProviders(),
		isSettingsOpen: selectors.getTicketsIsSettingsOpen(state),
		provider: selectors.getTicketsProvider(state),
		sharedCapacity: selectors.getTicketsSharedCapacity(state),
		canCreateTickets: selectors.canCreateTickets(),
		hasRecurrenceRules: hasRecurrenceRules(state),
		noTicketsOnRecurring: noTicketsOnRecurring(),
		showUneditableTickets: getShowUneditableTickets(state, ownProps),
	};

	/**
	 * Filters the properties mapped from the state for the Tickets component.
	 *
	 * @since TBD
	 *
	 * @param {Object} mappedProps      The mapped props.
	 * @param {Object} context.state    The state of the block.
	 * @param {Object} context.ownProps The props passed to the block.
	 */
	mappedProps = applyFilters(
		'tec.tickets.blocks.Tickets.mappedProps',
		mappedProps,
		{ state, ownProps }
	);

	return mappedProps;
};

const mapDispatchToProps = (dispatch) => ({
	setInitialState: (props) => {
		dispatch(actions.setTicketsInitialState(props));
	},
	onBlockUpdate: (isSelected) => {
		dispatch(actions.setTicketsIsSelected(isSelected));
	},
	onBlockRemoved: () => {
		dispatch(actions.resetTicketsBlock());
	},
});

export default compose(
	withStore(),
	connect(mapStateToProps, mapDispatchToProps),
	withSaveData()
)(Template);
