/* global tribe */
/**
 * Makes sure we have all the required levels on the Tribe Object
 *
 * @since TBD
 *
 * @type {PlainObject}
 */
tribe.tickets = tribe.tickets || {};
tribe.tickets.admin = tribe.tickets.admin || {};

/**
 * Configures admin manager Object in the Global Tribe variable
 *
 * @since TBD
 *
 * @type {PlainObject}
 */
tribe.tickets.admin.manager = {};

/**
 * Initializes in a Strict env the code that manages the Event Views
 *
 * @since TBD
 *
 * @param  {PlainObject} $   jQuery
 * @param  {PlainObject} _   Underscore.js
 * @param  {PlainObject} obj tribe.tickets.admin.manager
 *
 * @return {void}
 */
( function( $, _, obj ) {
	'use strict';
	const $document = $( document );

	/**
	 * Selectors used for configuration and setup
	 *
	 * @since TBD
	 *
	 * @type {PlainObject}
	 */
	obj.selectors = {
		container: '.tribe-tickets__admin-container',
		loader: '.tribe-common-c-loader',
		hiddenElement: '.tribe-common-a11y-hidden',
		messageError: '.tribe-tickets__admin-container-message--error',
	};

	/**
	 * Stores the current ajax request been handled by the manager.
	 *
	 * @since TBD
	 *
	 * @type {jqXHR|null}
	 */
	obj.currentAjaxRequest = null;

	/**
	 * Containers on the current page that were initialized.
	 *
	 * @since TBD
	 *
	 * @type {jQuery}
	 */
	obj.$containers = $();

	/**
	 * Saves all the containers in the page into the object.
	 *
	 * @since TBD
	 *
	 * @return {void}
	 */
	obj.selectContainers = function() {
		obj.$containers = $( obj.selectors.container );
	};

	/**
	 * Clean up the container and event listeners
	 *
	 * @since TBD
	 *
	 * @param  {jQuery} container Which element we are going to clean up
	 *
	 * @return {void}
	 */
	obj.cleanup = function( container ) {
		const $container = $( container );

		$container.trigger( 'beforeCleanup.tribeTicketsAdmin', [ $container ] );

		$container.trigger( 'afterCleanup.tribeTicketsAdmin', [ $container ] );
	};

	/**
	 * Setup the container for admin management
	 *
	 * @since TBD
	 *
	 * @todo  Requirement to setup other JS modules after hijacking Click and Submit
	 *
	 * @param  {integer}        index     jQuery.each index param
	 * @param  {Element|jQuery} container Which element we are going to setup
	 *
	 * @return {void}
	 */
	obj.setup = function( index, container ) {
		const $container = $( container );

		$container.trigger( 'beforeSetup.tribeTicketsAdmin', [ $container, index ] );

		$container.trigger( 'afterSetup.tribeTicketsAdmin', [ $container, index ] );
	};

	/**
	 * Performs an AJAX request.
	 *
	 * @since TBD
	 *
	 * @param  {object}         data       DOM Event related to the Click action
	 * @param  {Element|jQuery} $container Which container we are dealing with
	 *
	 * @return {void}
	 */
	obj.request = function( data, $container ) {
		const settings = obj.getAjaxSettings( $container );

		// Pass the data received to the $.ajax settings
		settings.data = data;

		obj.currentAjaxRequest = $.ajax( settings );
	};

	/**
	 * Gets the jQuery.ajax() settings provided a views container
	 *
	 * @since TBD
	 *
	 * @param  {Element|jQuery} $container Which container we are dealing with.
	 *
	 * @return {Object} ajaxSettings
	 */
	obj.getAjaxSettings = function( $container ) {
		const ajaxSettings = {
			url: TribeTickets.ajaxurl,
			method: 'POST',
			beforeSend: obj.ajaxBeforeSend,
			complete: obj.ajaxComplete,
			success: obj.ajaxSuccess,
			error: obj.ajaxError,
			context: $container,
		};

		return ajaxSettings;
	};

	/**
	 * Triggered on jQuery.ajax() beforeSend action, which we hook into to
	 * setup a Loading Lock, as well as trigger a before and after hook, so
	 * third-party developers can always extend all requests
	 *
	 * Context with the RSVP container used to fire this AJAX call
	 *
	 * @since TBD
	 *
	 * @param  {jqXHR}       jqXHR    Request object
	 * @param  {PlainObject} settings Settings that this request will be made with
	 *
	 * @return {void}
	 */
	obj.ajaxBeforeSend = function( jqXHR, settings ) {
		const $container = this;

		$container.trigger( 'beforeAjaxBeforeSend.tribeTicketsAdmin', [ jqXHR, settings ] );

		// @todo: check that we are loading the loader.
		tribe.tickets.loader.show( $container );

		$container.trigger( 'afterAjaxBeforeSend.tribeTicketsAdmin', [ jqXHR, settings ] );
	};

	/**
	 * Triggered on jQuery.ajax() complete action, which we hook into to
	 * removal of Loading Lock, as well as trigger a before and after hook,
	 * so third-party developers can always extend all requests
	 *
	 * Context with the RSVP container used to fire this AJAX call
	 *
	 * @since TBD
	 *
	 * @param  {jqXHR}  jqXHR      Request object
	 * @param  {String} textStatus Status for the request
	 *
	 * @return {void}
	 */
	obj.ajaxComplete = function( jqXHR, textStatus ) {
		const $container = this;

		$container.trigger( 'beforeAjaxComplete.tribeTicketsAdmin', [ jqXHR, textStatus ] );

		tribe.tickets.loader.hide( $container );

		$container.trigger( 'afterAjaxComplete.tribeTicketsAdmin', [ jqXHR, textStatus ] );

		// Reset the current AJAX request on the manager object.
		obj.currentAjaxRequest = null;
	};

	/**
	 * Triggered on jQuery.ajax() success action, which we hook into to
	 * replace the contents of the container which is the base behavior
	 * for the RSVP manager, as well as trigger a before and after hook,
	 * so third-party developers can always extend all requests
	 *
	 * Context with the RSVP container used to fire this AJAX call
	 *
	 * @since TBD
	 *
	 * @param  {Object} response   Response sent from the AJAX response.
	 * @param  {String} textStatus Status for the request
	 * @param  {jqXHR}  jqXHR      Request object
	 *
	 * @return {void}
	 */
	obj.ajaxSuccess = function( response, textStatus, jqXHR ) {
		const $container = this;
		const $html = response.data.html;

		// If the request is not successful, prepend the error.
		if ( ! response.success ) {
			// Prepend the error only once.
			if ( ! $container.find( obj.selectors.messageError ).length ) {
				$container.prepend( $html );
			}

			return;
		}

		$container.trigger( 'beforeAjaxSuccess.tribeTicketsAdmin', [ response, textStatus, jqXHR ] );

		// Clean up the container and event listeners.
		obj.cleanup( $container );

		// Replace the current container with the new Data.
		$container.html( $html );

		// Setup the container with the data received.
		obj.setup( $container, 0 );

		// Update the global set of containers with all of the manager object.
		obj.selectContainers();

		$container.trigger( 'afterAjaxSuccess.tribeTicketsAdmin', [ response, textStatus, jqXHR ] );
	};

	/**
	 * Triggered on jQuery.ajax() error action, which we hook into to
	 * display error and keep the user on the same "page", as well as
	 * trigger a before and after hook, so third-party developers can
	 * always extend all requests
	 *
	 * Context with the RSVP container used to fire this AJAX call
	 *
	 * @since TBD
	 *
	 * @param  {jqXHR}       jqXHR    Request object
	 * @param  {PlainObject} settings Settings that this request was made with
	 *
	 * @return {void}
	 */
	obj.ajaxError = function( jqXHR, settings ) {
		const $container = this;

		$container.trigger( 'beforeAjaxError.tribeTicketsAdmin', [ jqXHR, settings ] );

		$container.trigger( 'afterAjaxError.tribeTicketsAdmin', [ jqXHR, settings ] );
	};

	/**
	 * Handles the initialization of the manager when Document is ready.
	 *
	 * @since TBD
	 *
	 * @return {void}
	 */
	obj.ready = function() {
		obj.selectContainers();
		obj.$containers.each( obj.setup );
	};

	// Configure on document ready.
	$document.ready( obj.ready );
} )( jQuery, window.underscore || window._, tribe.tickets.admin.manager );
