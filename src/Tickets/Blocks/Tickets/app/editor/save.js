/**
 * External dependencies
 */
import { connect } from 'react-redux';
import { compose } from 'redux';

/**
 * Internal dependencies
 */
import { withStore } from '@moderntribe/common/hoc';
import { applyFilters } from '@wordpress/hooks';
import { selectors } from '@moderntribe/tickets/data/blocks/ticket';

const Save = (props) => {
	const { tickets, blockProps, currentPost, getBlock } = props;

	/*
	 * This block children are Ticket item blocks.
	 * Depending on the context of the post context of this operation, some Ticket blocks should not be saved
	 * in the `post_content`.
	 * Here we filter out the Ticket blocks that should not be saved.
	 */
	const ticketsToSave = Object.entries(tickets).reduce(function (
		toSave,
		[clientId, ticket]
	) {
		/**
		 * Filters whether to save the Ticket block markup from the post.
		 *
		 * @since TBD
		 *
		 * @param {boolean} saveTicketFromPost Whether to save the ticket from the post.
		 * @param {Object}  ticket             The ticket object, the format is the one returned by the Tickets REST API.
		 * @param {Object}  post               The post object, the format is the one returned by the WP REST API.
		 *
		 * @type {boolean} Whether to save the ticket from the post or not.
		 */
		const doSave = applyFilters(
			'tec.tickets.blocks.saveTicketFromPost',
			true,
			ticket,
			currentPost
		);

		if (!doSave) {
			return toSave;
		}

		const ticketBlock = getBlock(clientId);

		return [...toSave, ticketBlock];
	}, []);

	/*
	 * The `wp.blocks.serialize` function will "serialize" an array of blocks into a string of HTML and
	 * HTML comments delimiting blocks in the Block Editor; it's not a JSON serialization.
	 * E.g. running the function on a `wp/paragraph` block will yield the following HTML code:
	 * <!-- wp:paragraph --><div class="wp-block-core-paragraph"><p>Lorem dolor</p></div><!-- /wp:paragraph -->
	 */
	const serializedInnerBlocks = wp.blocks.serialize(ticketsToSave);

	/*
	 * Having possibly filtered the inner blocks, we cannot rely on `InnerBlocks.Content` to render the markup.
	 * We take charge of the serialization of the filtered inner blocks and render the markup ourselves.
	 * What is returned by this component is the verbatim HTML code that will be saved in the `post_content`.
	 */
	return (
		<div
			{...blockProps}
			dangerouslySetInnerHTML={{ __html: serializedInnerBlocks }}
		></div>
	);
};

const mapStateToProps = (state) => {
	return {
		currentPost: wp.data.select('core/editor').getCurrentPost(),
		tickets: selectors.getTicketsByClientId(state),
		getBlock(clientId) {
			return wp.data.select('core/block-editor').getBlock(clientId);
		},
	};
};

export default compose(withStore(), connect(mapStateToProps))(Save);