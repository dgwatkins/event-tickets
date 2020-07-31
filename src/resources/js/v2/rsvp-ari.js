/**
 * Makes sure we have all the required levels on the Tribe Object
 *
 * @since TBD
 *
 * @type {PlainObject}
 */
tribe.tickets = tribe.tickets || {};
tribe.tickets.rsvp = tribe.tickets.rsvp || {};

/**
 * Configures RSVP ARI Object in the Global Tribe variable
 *
 * @since  TBD
 *
 * @type {PlainObject}
 */
tribe.tickets.rsvp.ari = {};

/**
 * Initializes in a Strict env the code that manages the RSVP block.
 *
 * @since TBD
 *
 * @param  {PlainObject} $   jQuery
 * @param  {PlainObject} obj tribe.tickets.rsvp.block
 *
 * @return {void}
 */
( function( $, obj ) {
	'use strict';
	const $document = $( document );
	const $window = $( window );

	/**
	 * Selectors used for configuration and setup
	 *
	 * @since TBD
	 *
	 * @type {PlainObject}
	 */
	obj.selectors = {
		container: '.tribe-tickets__rsvp-wrapper',
		rsvpForm: 'form[name~="tribe-tickets-rsvp-form-ari"]',
		hiddenElement: '.tribe-common-a11y-hidden',
		addGuestButton: '.tribe-tickets__rsvp-ar-quantity-input-number--plus',
		removeGuestButton: '.tribe-tickets__rsvp-ar-quantity-input-number--minus',
		guestList: '.tribe-tickets__rsvp-ar-guest-list',
		guestListItem: '.tribe-tickets__rsvp-ar-guest-list-item',
		guestListItemTemplate: '.tribe-tickets__rsvp-ar-guest-list-item-template',
		guestListItemButton: '.tribe-tickets__rsvp-ar-guest-list-item-button',
		guestListItemButtonInactive: '.tribe-tickets__rsvp-ar-guest-list-item-button--inactive',
		guestListItemButtonIcon: '.tribe-tickets__rsvp-ar-guest-icon',
		guestFormWrapper: '.tribe-tickets__rsvp-ar-form',
		guestFormFields: '.tribe-tickets__rsvp-ar-form-guest',
		guestFormFieldsError: '.tribe-tickets__form-message--error',
		guestFormFieldsTitle: '.tribe-tickets__rsvp-ar-form-title',
		guestFormFieldsTemplate: '.tribe-tickets__rsvp-ar-form-guest-template',
		nextGuestButton: '.tribe-tickets__rsvp-form-button--next',
		submitButton: '.tribe-tickets__rsvp-form-button--submit',
	};

	/**
	 * Go to guest.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 * @param {number} guestNumber The guest number we want to go to.
	 *
	 * @return {void}
	 */
	obj.goToGuest = function( $container, guestNumber ) {
		const $guestFormWrapper = $container.find( obj.selectors.guestFormWrapper );
		const $targetGuestForm = $guestFormWrapper.find( obj.selectors.guestFormFields + '[data-guest-number="' + guestNumber + '"]' );
		const $guestListButtons = $container.find( obj.selectors.guestListItemButton );
		const $guestListButtonsIcon = $container.find( obj.selectors.guestListItemButtonIcon );

		// Set all forms as hidden.
		$container.find( obj.selectors.guestFormFields ).addClass( obj.selectors.hiddenElement.className() );

		// Show the selected guest.
		obj.showElement( $targetGuestForm );

		// Set the classes for inactive.
		$guestListButtons.addClass( obj.selectors.guestListItemButtonInactive.className() );

		// Set the active class for the current.
		const $targetGuestButton = $container.find( obj.selectors.guestListItemButton + '[data-guest-number="' + guestNumber + '"]' );
		$targetGuestButton.removeClass( obj.selectors.guestListItemButtonInactive.className() );
	};

	/**
	 * Show element.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $element jQuery object of the element to show.
	 *
	 * @return {void}
	 */
	obj.showElement = function( $element ) {
		$element.removeClass( obj.selectors.hiddenElement.className() );
	};

	/**
	 * Hide element.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $element jQuery object of the element to hide.
	 *
	 * @return {void}
	 */
	obj.hideElement = function( $element ) {
		$element.addClass( obj.selectors.hiddenElement.className() );
	};

	/**
	 * Checks if the guest form is valid.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $guestForm jQuery object of the guest form container.
	 *
	 * @return {void}
	 */
	obj.isGuestValid = function( $guestForm ) {
		const $fields = $guestForm.find( tribe.tickets.meta.selectors.formFieldInput );
		let isValid = true;

		$fields.each(
			function() {
				const $field = $( this );
				const isValidfield = tribe.tickets.meta.validateField( $field[ 0 ] );

				if ( ! isValidfield ) {
					isValid = false;
				}
			}
		);

		const $guestFormError = $guestForm.find( obj.selectors.guestFormFieldsError );

		if ( isValid ) {
			obj.hideElement( $guestFormError );
		} else {
			obj.showElement( $guestFormError );
		}

		return isValid;
	};

	/**
	 * Checks if if can move to the guest coming in `guestNumber`.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 * @param {number} guestNumber The guest number we want to go to.
	 *
	 * @return {void}
	 */
	obj.canGoToGuest = function( $container, guestNumber ) {
		const currentGuest = obj.getCurrentGuest( $container );

		// If the guest number is lower than the current guest, return true.
		if ( guestNumber < currentGuest ) {
			return true;
		}

		// They can only proceed to the next guest.
		if ( 1 < ( guestNumber - currentGuest ) ) {
			return false;
		}

		// Get the gurrent guest form.
		const $currentGuestForm = $container.find( obj.selectors.guestFormFields + '[data-guest-number="' + currentGuest + '"]' );

		// Get if there are required fields in the current.
		const isCurrentGuestValid = obj.isGuestValid( $currentGuestForm );

		return isCurrentGuestValid;
	};

	/**
	 * Set the "Next" and "Submit" hidden classes.
	 * Bind the required actions to the "Next" button.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.setNextAndSubmit = function( $container ) {
		const $guestForm = $container.find( obj.selectors.guestFormFields );
		const totalGuests = $guestForm.length;

		obj.bindNextButton( $container );

		$guestForm.each( function( index, wrapper ) {
			const $nextGuestButton = $( wrapper ).find( obj.selectors.nextGuestButton );
			const $submitButton = $( wrapper ).find( obj.selectors.submitButton );
			const currentGuest = index + 1;

			// If it's the last guest.
			if ( currentGuest === totalGuests ) {
				obj.showElement( $submitButton );
				obj.hideElement( $nextGuestButton );
			} else {
				obj.showElement( $nextGuestButton );
				obj.hideElement( $submitButton );
			}
		} );
	};

	/**
	 * Bind go to guest.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 * @param {jQuery} $button jQuery object of the button.
	 * @param {number} guestNumber The guest number.
	 *
	 * @return {void}
	 */
	obj.bindGoToGuest = function( $container, $button, guestNumber = 1 ) {
		$button.on( 'click', function() {
			const guestNumberDataAttribute = $( this ).data( 'guest-number' );
			if ( undefined !== guestNumberDataAttribute ) {
				guestNumber = guestNumberDataAttribute;
			}

			if ( ! obj.canGoToGuest( $container, guestNumber ) ) {
				return;
			}

			obj.goToGuest( $container, guestNumber );
		} );
	};

	/**
	 * Add guest.
	 * Adds the form and the list item.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.addGuest = function( $container ) {
		const $guestList = $container.find( obj.selectors.guestList );
		const $guestFormWrapper = $container.find( obj.selectors.guestFormWrapper );
		const totalGuests = obj.getTotalGuests( $container );

		const rsvpId = $container.data( 'rsvp-id' );
		const rsvpFieldsTemplate = window.wp.template( obj.selectors.guestFormFieldsTemplate.className() + '-' + rsvpId );
		const guestListItemTemplate = window.wp.template( obj.selectors.guestListItemTemplate.className() + '-' + rsvpId );
		const data = { attendee_id: totalGuests };

		// Append the new guest list item and new guest form.
		$guestList.append( guestListItemTemplate( data ) );
		$guestFormWrapper.append( rsvpFieldsTemplate( data ) );

		const $guestListItems = $guestList.children( obj.selectors.guestListItem );
		const $newGuest = $guestListItems.last();
		const $newGuestButton = $newGuest.find( obj.selectors.guestListItemButton );

		const $newGuestForm = $guestFormWrapper.children( obj.selectors.guestFormFields ).last();

		// Globally set next guest / Submit.
		obj.setNextAndSubmit( $container );

		// bind actions on fields / buttons.
		obj.bindGoToGuest( $container, $newGuestButton );

		// Bind Cancel button in this new form.
		tribe.tickets.rsvp.block.bindCancel( $newGuestForm );
	};

	/**
	 * Handle the number input + and - actions
	 *
	 * @since TBD
	 *
	 * @param {event} e input event
	 */
	obj.handleQuantityChangeValue = function( e ) {
		e.preventDefault();
		const $this = $( e.target );
		const $container = e.data.container;

		const max = $this.attr( 'max' );
		const maxQty = 0;
		let newQuantity = parseInt( $this.val(), 10 );
		newQuantity = isNaN( newQuantity ) ? 0 : newQuantity;

		// Set it to the max if the new quantity is over the max.
		if ( max < newQuantity ) {
			newQuantity = max;
		}

		if ( 0 > maxQty ) {
			newQuantity += maxQty;
		}

		// If the quantity is 0, set it to the min.
		if ( 0 === newQuantity ) {
			newQuantity = $this.attr( 'min' );
		}

		// Set the input value.
		$this.val( newQuantity );

		// Define the difference and see if they're adding or removing.
		const difference = newQuantity - obj.getTotalGuests( $container );
		const isAdding = difference > 0;

		// Add or remove guest depending on the difference between the current value and
		// the new value from the input.
		for ( let i = 0; i < Math.abs( difference ); i++ ) {
			if ( isAdding ) {
				obj.addGuest( $container );
			} else {
				obj.removeGuest( $container );
			}
		}

		e.preventDefault();
	};

	/**
	 * Handle the RSVP form submission
	 *
	 * @since TBD
	 *
	 * @param {event} e submission event
	 */
	obj.handleSubmission = function( e ) {
		e.preventDefault();

		const $form = $( this );
		const $container = $form.closest( obj.selectors.container );
		const rsvpId = $form.data( 'rsvp-id' );
		const params = $form.serializeArray();

		let data = {
			action: 'tribe_tickets_rsvp_handle',
			ticket_id: rsvpId,
			step: 'success',
		};

		$( params ).each( function( index, object ) {
			data[ object.name ] = object.value;
		} );

		tribe.tickets.rsvp.manager.request( data, $container );
	};

	/**
	 * Binds events for the RSVP form.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.bindForm = function( $container ) {
		const $rsvpForm = $container.find( obj.selectors.rsvpForm );

		$rsvpForm.each( function( index, form ) {
			$( form ).on( 'submit', obj.handleSubmission );
		} );
	};

	/**
	 * Remove guest.
	 * Remove the form and the list item.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.removeGuest = function( $container ) {
		const totalGuests = obj.getTotalGuests( $container );
		const currentGuest = obj.getCurrentGuest( $container );

		// Bail if there's only one guest.
		if ( totalGuests === 1 ) {
			return;
		}

		// Go to the previous guest if we're on the last one.
		if ( totalGuests === currentGuest ) {
			obj.goToGuest( $container, currentGuest - 1 );
		}

		const $guestFormFields = $container.find( obj.selectors.guestFormFields );
		const $guestListItems = $container.find( obj.selectors.guestListItem );

		// Remove HTML and binded actions of the ones that were generated via JS.
		$guestListItems.last().remove();
		$guestFormFields.last().remove();

		// Update the Next Guest / Previous buttons for the new "last" guest.
		const $newLastGuest = $container.find( obj.selectors.guestFormFields ).last();
		const $nextGuestButton = $newLastGuest.find( obj.selectors.nextGuestButton );
		const $submitButton = $newLastGuest.find( obj.selectors.submitButton );

		obj.showElement( $submitButton );
		obj.hideElement( $nextGuestButton );
	};

	/**
	 * Get the total guests number for the container.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {number} Number representing the total guests.
	 */
	obj.getTotalGuests = function( $container ) {
		return $container.find( obj.selectors.guestFormFields ).length;
	};

	/**
	 * Get the current guest number for the container.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {number} Number representing the current guests.
	 */
	obj.getCurrentGuest = function( $container ) {
		const $currentFormFields = $container.find( obj.selectors.guestFormFields + ':not(' + obj.selectors.hiddenElement + ')' );

		return $currentFormFields.data( 'guest-number' );
	};

	obj.handleQuantityChange = function( e ) {
		e.preventDefault();
		const $input   = $( this ).parent().find( 'input[type="number"]' );
		const increase = $( this ).hasClass( obj.selectors.addGuestButton.className() );
		const step = $input[ 0 ].step ? Number( $input [ 0 ].step ) : 1;
		const originalValue = Number( $input[ 0 ].value );

		// stepUp or stepDown the input according to the button that was clicked
		// handle IE/Edge
		if ( increase ) {
			// we use 0 here as a shorthand for no maximum.
			const max = $input[ 0 ].max ? Number( $input[ 0 ].max ) : -1;

			if ( typeof $input[ 0 ].stepUp === 'function' ) {
				try {
					// Bail if we're already in the max, safari has issues with stepUp() here.
					if ( max < ( originalValue + step ) ) {
						return;
					}
					$input[ 0 ].stepUp();
				} catch ( ex ) {
					$input[ 0 ].value = ( -1 === max || max >= originalValue + step ) ? originalValue + step : max;
				}
			} else {
				$input[ 0 ].value = ( -1 === max || max >= originalValue + step ) ? originalValue + step : max;
			}
		} else {
			const min = $input[ 0 ].min ? Number( $input[ 0 ].min ) : 0;

			if ( typeof $input[ 0 ].stepDown === 'function' ) {
				try {
					$input[ 0 ].stepDown();
				} catch ( ex ) {
					$input[ 0 ].value = ( min <= originalValue - step ) ? originalValue - step : min;
				}
			} else {
				$input[ 0 ].value = ( min <= originalValue - step ) ? originalValue - step : min;
			}
		}

		// Trigger the on Change for the input (if it has changed) as it's not handled via stepUp() || stepDown()
		if ( originalValue !== $input[ 0 ].value ) {
			$input.trigger( 'change' );
		}
	};

	/**
	 * Binds events for guest addition/removal.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.bindGuestAddRemove = function( $container ) {
		const $addGuestButton = $container.find( obj.selectors.addGuestButton );
		const $removeGuestButton = $container.find( obj.selectors.removeGuestButton );
		const $guestListItemButton = $container.find( obj.selectors.guestListItemButton );
		const $qtyInput = $container.find( '.tribe-tickets__rsvp-ar-quantity-input input[type="number"]' );

		obj.bindGoToGuest( $container, $guestListItemButton );

		$addGuestButton.on( 'click', obj.handleQuantityChange );
		$removeGuestButton.on( 'click', obj.handleQuantityChange );

		$qtyInput.on(
			'change keyup',
			{ container: $container },
			obj.handleQuantityChangeValue
		);
	};

	/**
	 * Binds events for next guest button.
	 *
	 * @since TBD
	 *
	 * @param {jQuery} $container jQuery object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.bindNextButton = function( $container ) {
		const $guestForm = $container.find( obj.selectors.guestFormFields );
		const $lastForm = $guestForm.last();
		const $lastFormNextButton = $lastForm.find( obj.selectors.nextGuestButton );
		const lastFormGuestNumber = $lastForm.data( 'guest-number' );

		obj.bindGoToGuest( $container, $lastFormNextButton, lastFormGuestNumber + 1 );
	};

	/**
	 * Unbinds events.
	 *
	 * @since TBD
	 *
	 * @param  {Event}       event    event object for 'beforeAjaxSuccess.tribeTicketsRsvp' event
	 * @param  {jqXHR}       jqXHR    Request object
	 * @param  {PlainObject} settings Settings that this request was made with
	 *
	 * @return {void}
	 */
	obj.unbindEvents = function( event, jqXHR, settings ) {
		const $container = event.data.container;
		const $addGuestButton = $container.find( obj.selectors.addGuestButton );
		const $removeGuestButton = $container.find( obj.selectors.removeGuestButton );
		const $guestListItemButton = $container.find( obj.selectors.guestListItemButton );

		$addGuestButton.off();
		$removeGuestButton.off();
		$guestListItemButton.off();
	};

	/**
	 * Binds events for container.
	 *
	 * @since TBD
	 *
	 * @param {jQuery}  $container jQuery object of object of the RSVP container.
	 *
	 * @return {void}
	 */
	obj.bindEvents = function( $container ) {
		obj.bindGuestAddRemove( $container );
		obj.bindForm( $container );
		obj.bindNextButton( $container );

		$container.on(
			'beforeAjaxSuccess.tribeTicketsRsvp',
			{ container: $container },
			obj.unbindEvents
		);
	};

	/**
	 * Initialize RSVP events.
	 *
	 * @since TBD
	 *
	 * @param {Event}   event      event object for 'afterSetup.tribeTicketsRsvp' event
	 * @param {integer} index      jQuery.each index param from 'afterSetup.tribeTicketsRsvp' event.
	 * @param {jQuery}  $container jQuery object of view container.
	 *
	 * @return {void}
	 */
	obj.init = function( event, index, $container ) {
		obj.bindEvents( $container );
	};

	/**
	 * Handles the initialization of the RSVP block events when Document is ready.
	 *
	 * @since TBD
	 *
	 * @return {void}
	 */
	obj.ready = function() {
		$document.on(
			'afterSetup.tribeTicketsRsvp',
			tribe.tickets.rsvp.manager.selectors.container,
			obj.init
		);
	};

	// Configure on document ready.
	$document.ready( obj.ready );
} )( jQuery, tribe.tickets.rsvp.ari );
