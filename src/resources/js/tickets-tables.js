(function( window, $ ) {
	var $table = $( '.eventtable.ticket_list.eventForm tbody' ),
		enable_width = '400px';

	// For drag-n-drop
	function make_sortable( $element ) {
		$element.sortable({
			cursor: 'move',
			items: 'tr:not(.Tribe__Tickets__RSVP)',
			forcePlaceholderSize: true,
			update: function() {
				data = $(this).sortable( 'toArray', { key: 'order[]', attribute: 'data-ticket-order-id' } );
				console.log( 'data: ' + data);
				document.getElementById( 'tribe_tickets_order' ).value = data;
			}
		});
		$element.disableSelection();
		$element.find( '.table-header' ).disableSelection();
		$element.sortable( 'option', 'disabled', false );
	}

	$( '.ticket_edit_button' ).on(
		'click',
		function( e ) {
			var ticket = $( e.target ).data( 'ticket-id' );
		}
	);

	$(document).ready(function () {
		// init if we're not on small screens
		if ( window.matchMedia( '( min-width: 400px )' ).matches ) {
			 make_sortable( $table );
		}

		// disable/init depending on screen size
		$(window).on( 'resize', function() {
			if ( window.matchMedia( '( min-width: 400px )' ).matches ) {
				if ( ! $( $table ).hasClass( 'ui-sortable' ) ) {
					make_sortable( $table );
				}
			} else {
				if ( $( $table ).hasClass( 'ui-sortable' ) ) {
					$( $table ).sortable( 'option', 'disabled', true );
				}
			}
		});
	});



})( window, jQuery );
