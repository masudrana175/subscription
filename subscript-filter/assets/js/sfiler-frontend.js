( function ( $ ) {
	'use strict';

	function updatePrices( $wrapper, regularPrice ) {
		var discount = parseFloat( $wrapper.data( 'discount' ) ) || 0;
		var subscribePrice = regularPrice * ( 1 - discount / 100 );
		var savings = regularPrice - subscribePrice;

		$wrapper.find( '.sfiler-price-onetime' ).text( regularPrice.toFixed( 2 ) );
		$wrapper.find( '.sfiler-price-subscribe' ).text( subscribePrice.toFixed( 2 ) );
		$wrapper.find( '.sfiler-regular-price del' ).text( regularPrice.toFixed( 2 ) );
		$wrapper.find( '.sfiler-savings' ).text( savings.toFixed( 2 ) );
	}

	$( function () {
		var $wrapper = $( '.sfiler-purchase-options' );

		if ( ! $wrapper.length ) {
			return;
		}

		$( '.variations_form' ).on( 'found_variation', function ( event, variation ) {
			// Use the variation's regular (list) price, not its current
			// display_price — the discount is meant to be a stable percentage
			// off list price, matching how the cart prices it server-side,
			// not off whatever sale price happens to be active.
			var price = variation && ( variation.display_regular_price || variation.display_price );
			if ( price ) {
				$wrapper.data( 'regular-price', price );
				updatePrices( $wrapper, parseFloat( price ) );
			}
		} );

		$( '.variations_form' ).on( 'reset_data', function () {
			var regularPrice = parseFloat( $wrapper.data( 'regular-price' ) ) || 0;
			updatePrices( $wrapper, regularPrice );
		} );

		$wrapper.find( '.sfiler-frequency-select' ).toggle( $wrapper.find( 'input[name="sfiler_purchase_type"]:checked' ).val() === 'subscription' );

		$wrapper.on( 'change', 'input[name="sfiler_purchase_type"]', function () {
			$wrapper.find( '.sfiler-frequency-select' ).toggle( $( this ).val() === 'subscription' );
		} );
	} );
} )( jQuery );
