global.TribeCartEndpoint = { url : 'test' };

// Follows the formatting found here: `Tribe__Tickets__Tickets::get_asset_localize_data_for_currencies()`
const formats = {
	'default' : {
		"symbol":"$",
		"placement":"prefix",
		"decimal_point":".",
		"thousands_sep":",",
		"number_of_decimals":2
	},
	'german' : {
		"symbol":"$",
		"placement":"prefix",
		"decimal_point":",",
		"thousands_sep":".",
		"number_of_decimals":2
	},

};
global.TribeCurrency = { formatting: JSON.stringify(formats) };

global.tribe = {
	tickets : {}
};

require( '../tickets-utils' );

const utils = tribe.tickets.utils;

test( 'Testing demo rest endpoint', () => {
	expect(utils.getRestEndpoint()).toBe("test");
} );

test( 'Testing formats are not empty', () => {
	expect( utils.getCurrencyFormatting('default') ).not.toBe( {} );
} );

describe( 'Format testing', () => {

	const dataset = [
		{ format: 'default', number: '12.22', output: '12.22' },
		{ format: 'default', number: '1112.22', output: '1,112.22' },
		{ format: 'default', number: '1111111.00', output: '1,111,111.00' },
		{ format: 'german', number: '12.22', output: '12,22' },
		{ format: 'german', number: '1212.22', output: '1.212,22' },
		{ format: 'german', number: '393939.00', output: '393.939,00' },
		{ format: 'german', number: '2011.00', output: '2.011,00' },
	];

	describe( 'Testing Number format', () => {
		it.each(dataset)('values :( $format, $number, $output )', ({ format, number, output }) => {
			expect(utils.numberFormat( number, format )).toBe(output);
		});
	} );

	describe( 'Testing Clean Number', () => {
		it.each(dataset)('values : ( $format, $number, $output )', ({ format, number, output }) => {
			expect(utils.cleanNumber( output, format )).toBe(number);
		});
	} );
} );