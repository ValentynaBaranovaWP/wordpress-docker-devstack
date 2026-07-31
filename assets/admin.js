( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.getElementById( 'cop-refresh-status' );
		var holder = document.getElementById( 'cop-order-status' );

		if ( ! button || ! holder || typeof window.copAdmin === 'undefined' ) {
			return;
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;

			var body = new URLSearchParams( {
				action: 'cop_refresh_order_status',
				nonce: window.copAdmin.nonce,
				order_id: holder.dataset.orderId
			} );

			fetch( window.copAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( payload ) {
					if ( payload && payload.success ) {
						var badge = holder.querySelector( '.cop-status' );
						if ( badge ) {
							badge.className = 'cop-status cop-status--' + payload.data.status;
							badge.textContent = payload.data.label;
						}
					}
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	} );
} )();
