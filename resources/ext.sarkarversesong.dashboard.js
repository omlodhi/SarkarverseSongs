/**
 * SarkarverseSong dashboard JavaScript
 */
( function () {
	'use strict';

	// Auto-submit on filter change
	$( function () {
		$( '#year-filter, #category-filter, #theme-filter, #language-filter' ).on( 'change', function () {
			$( this ).closest( 'form' ).trigger( 'submit' );
		} );
	} );

}() );
