( function () {
	'use strict';

	function initFilters() {
		var categoryFilter = document.getElementById( 'sarkarverse-filter-category' );
		var yearFilter = document.getElementById( 'sarkarverse-filter-year' );
		var visibleCountEl = document.getElementById( 'sarkarverse-visible-count' );
		var rows = document.querySelectorAll( '.sarkarverse-song-row' );

		if ( !rows.length ) {
			return;
		}

		function applyFilters() {
			var selectedCategory = categoryFilter ? categoryFilter.value : '';
			var selectedYear = yearFilter ? yearFilter.value : '';
			var visibleCount = 0;

			rows.forEach( function ( row ) {
				var categories = [];
				try {
					categories = JSON.parse( row.getAttribute( 'data-categories' ) || '[]' );
				} catch ( e ) {
					categories = [];
				}
				var year = row.getAttribute( 'data-year' ) || '';

				var categoryMatch = !selectedCategory || categories.indexOf( selectedCategory ) !== -1;
				var yearMatch = !selectedYear || year === selectedYear;

				if ( categoryMatch && yearMatch ) {
					row.style.display = '';
					visibleCount++;
				} else {
					row.style.display = 'none';
				}
			} );

			if ( visibleCountEl ) {
				visibleCountEl.textContent = visibleCount;
			}
		}

		if ( categoryFilter ) {
			categoryFilter.addEventListener( 'change', applyFilters );
		}
		if ( yearFilter ) {
			yearFilter.addEventListener( 'change', applyFilters );
		}

		// Apply initial filters
		applyFilters();
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initFilters );
	} else {
		initFilters();
	}
}() );
