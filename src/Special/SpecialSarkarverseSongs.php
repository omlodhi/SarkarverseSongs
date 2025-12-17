<?php

namespace MediaWiki\Extension\SarkarverseSong\Special;

use MediaWiki\Extension\SarkarverseSong\SongStore;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialSarkarverseSongs extends SpecialPage {

	private SongStore $songStore;

	public function __construct( SongStore $songStore ) {
		parent::__construct( 'SarkarverseSongs' );
		$this->songStore = $songStore;
	}

	/**
	 * @param string|null $subPage
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->addModules( [ 'ext.sarkarversesong.dashboard' ] );
		$out->addModuleStyles( [ 'ext.sarkarversesong.styles' ] );

		// Get filter parameters
		$theme = $request->getText( 'theme', '' );
		$language = $request->getText( 'language', '' );
		$category = $request->getText( 'category', '' );
		$year = $request->getText( 'year', '' );
		$limit = $request->getInt( 'limit', 50 );
		$offset = $request->getInt( 'offset', 0 );

		// Get available options for dropdowns
		$themes = $this->songStore->getThemes();
		$languages = $this->songStore->getLanguages();
		$categories = $this->songStore->getCategories();
		$years = $this->songStore->getYears();

		// Build filter form
		$out->addHTML( $this->buildFilterForm( $themes, $languages, $categories, $years, $theme, $language, $category, $year ) );

		// Get songs with filters
		$songs = $this->songStore->getSongs(
			$theme ?: null,
			$language ?: null,
			$category ?: null,
			$limit,
			$offset,
			$year ?: null
		);
		$totalCount = $this->songStore->getSongsCount(
			$theme ?: null,
			$language ?: null,
			$category ?: null,
			$year ?: null
		);

		// Display results count
		$out->addHTML( Html::element(
			'p',
			[ 'class' => 'sarkarverse-songs-count' ],
			$this->msg( 'sarkarversesong-showing', count( $songs ), $totalCount )->text()
		) );

		// Build songs table
		$out->addHTML( $this->buildSongsTable( $songs ) );

		// Add pagination
		if ( $totalCount > $limit ) {
			$out->addHTML( $this->buildPagination( $theme, $language, $category, $year, $limit, $offset, $totalCount ) );
		}
	}

	/**
	 * Build the filter form HTML
	 */
	private function buildFilterForm(
		array $themes,
		array $languages,
		array $categories,
		array $years,
		string $selectedTheme,
		string $selectedLanguage,
		string $selectedCategory,
		string $selectedYear
	): string {
		$html = Html::openElement( 'form', [
			'method' => 'get',
			'action' => $this->getPageTitle()->getLocalURL(),
			'class' => 'sarkarverse-filter-form',
		] );

		// Year dropdown
		$html .= Html::openElement( 'div', [ 'class' => 'sarkarverse-filter-group' ] );
		$html .= Html::element( 'label', [ 'for' => 'year-filter' ], $this->msg( 'sarkarversesong-filter-year' )->text() );
		$html .= Html::openElement( 'select', [ 'name' => 'year', 'id' => 'year-filter' ] );
		$html .= Html::element( 'option', [ 'value' => '' ], $this->msg( 'sarkarversesong-all-years' )->text() );
		foreach ( $years as $y ) {
			$attrs = [ 'value' => $y ];
			if ( $y === $selectedYear ) {
				$attrs['selected'] = 'selected';
			}
			$html .= Html::element( 'option', $attrs, $y );
		}
		$html .= Html::closeElement( 'select' );
		$html .= Html::closeElement( 'div' );

		// Category dropdown (from song pages)
		$html .= Html::openElement( 'div', [ 'class' => 'sarkarverse-filter-group' ] );
		$html .= Html::element( 'label', [ 'for' => 'category-filter' ], $this->msg( 'sarkarversesong-filter-category' )->text() );
		$html .= Html::openElement( 'select', [ 'name' => 'category', 'id' => 'category-filter' ] );
		$html .= Html::element( 'option', [ 'value' => '' ], $this->msg( 'sarkarversesong-all-categories' )->text() );
		foreach ( $categories as $cat ) {
			$attrs = [ 'value' => $cat ];
			if ( $cat === $selectedCategory ) {
				$attrs['selected'] = 'selected';
			}
			$html .= Html::element( 'option', $attrs, $cat );
		}
		$html .= Html::closeElement( 'select' );
		$html .= Html::closeElement( 'div' );

		// Theme dropdown
		$html .= Html::openElement( 'div', [ 'class' => 'sarkarverse-filter-group' ] );
		$html .= Html::element( 'label', [ 'for' => 'theme-filter' ], $this->msg( 'sarkarversesong-filter-theme' )->text() );
		$html .= Html::openElement( 'select', [ 'name' => 'theme', 'id' => 'theme-filter' ] );
		$html .= Html::element( 'option', [ 'value' => '' ], $this->msg( 'sarkarversesong-all-themes' )->text() );
		foreach ( $themes as $t ) {
			$attrs = [ 'value' => $t ];
			if ( $t === $selectedTheme ) {
				$attrs['selected'] = 'selected';
			}
			$html .= Html::element( 'option', $attrs, $t );
		}
		$html .= Html::closeElement( 'select' );
		$html .= Html::closeElement( 'div' );

		// Language dropdown
		$html .= Html::openElement( 'div', [ 'class' => 'sarkarverse-filter-group' ] );
		$html .= Html::element( 'label', [ 'for' => 'language-filter' ], $this->msg( 'sarkarversesong-filter-language' )->text() );
		$html .= Html::openElement( 'select', [ 'name' => 'language', 'id' => 'language-filter' ] );
		$html .= Html::element( 'option', [ 'value' => '' ], $this->msg( 'sarkarversesong-all-languages' )->text() );
		foreach ( $languages as $lang ) {
			$attrs = [ 'value' => $lang ];
			if ( $lang === $selectedLanguage ) {
				$attrs['selected'] = 'selected';
			}
			$html .= Html::element( 'option', $attrs, $lang );
		}
		$html .= Html::closeElement( 'select' );
		$html .= Html::closeElement( 'div' );

		// Submit button
		$html .= Html::submitButton(
			$this->msg( 'sarkarversesong-filter-submit' )->text(),
			[ 'class' => 'sarkarverse-filter-submit' ]
		);

		$html .= Html::closeElement( 'form' );

		return $html;
	}

	/**
	 * Build the songs table HTML
	 */
	private function buildSongsTable( array $songs ): string {
		if ( empty( $songs ) ) {
			return Html::element( 'p', [ 'class' => 'sarkarverse-no-songs' ], $this->msg( 'sarkarversesong-no-songs' )->text() );
		}

		$html = Html::openElement( 'table', [ 'class' => 'wikitable sortable sarkarverse-songs-table' ] );

		// Header row
		$html .= Html::openElement( 'thead' );
		$html .= Html::openElement( 'tr' );
		$html .= Html::element( 'th', [], $this->msg( 'sarkarversesong-col-number' )->text() );
		$html .= Html::element( 'th', [], $this->msg( 'sarkarversesong-col-date' )->text() );
		$html .= Html::element( 'th', [], $this->msg( 'sarkarversesong-col-title' )->text() );
		$html .= Html::element( 'th', [], $this->msg( 'sarkarversesong-col-theme' )->text() );
		$html .= Html::element( 'th', [], $this->msg( 'sarkarversesong-col-language' )->text() );
		$html .= Html::element( 'th', [], $this->msg( 'sarkarversesong-col-music' )->text() );
		$html .= Html::closeElement( 'tr' );
		$html .= Html::closeElement( 'thead' );

		// Data rows
		$html .= Html::openElement( 'tbody' );
		foreach ( $songs as $song ) {
			$html .= Html::openElement( 'tr' );
			$html .= Html::element( 'td', [], $song['number'] );
			$html .= Html::element( 'td', [], $song['date'] );

			// Title with link
			$titleCell = '';
			if ( $song['title'] !== '' ) {
				$title = Title::newFromText( $song['title'] );
				if ( $title ) {
					$titleCell = Html::element( 'a', [ 'href' => $title->getLocalURL() ], $song['title'] );
				} else {
					$titleCell = htmlspecialchars( $song['title'] );
				}
			}
			$html .= Html::rawElement( 'td', [], $titleCell );

			$html .= Html::element( 'td', [], $song['theme'] );
			$html .= Html::element( 'td', [], $song['language'] );
			$html .= Html::element( 'td', [], $song['music'] );
			$html .= Html::closeElement( 'tr' );
		}
		$html .= Html::closeElement( 'tbody' );

		$html .= Html::closeElement( 'table' );

		return $html;
	}

	/**
	 * Build pagination HTML
	 */
	private function buildPagination(
		string $theme,
		string $language,
		string $category,
		string $year,
		int $limit,
		int $offset,
		int $total
	): string {
		$html = Html::openElement( 'div', [ 'class' => 'sarkarverse-pagination' ] );

		$currentPage = floor( $offset / $limit ) + 1;
		$totalPages = ceil( $total / $limit );

		// Previous link
		if ( $offset > 0 ) {
			$prevOffset = max( 0, $offset - $limit );
			$html .= Html::element( 'a', [
				'href' => $this->getPageTitle()->getLocalURL( [
					'theme' => $theme,
					'language' => $language,
					'category' => $category,
					'year' => $year,
					'limit' => $limit,
					'offset' => $prevOffset,
				] ),
				'class' => 'sarkarverse-pagination-prev',
			], $this->msg( 'sarkarversesong-prev' )->text() );
		}

		// Page info
		$html .= Html::element( 'span', [ 'class' => 'sarkarverse-pagination-info' ],
			$this->msg( 'sarkarversesong-page-info', $currentPage, $totalPages )->text()
		);

		// Next link
		if ( $offset + $limit < $total ) {
			$nextOffset = $offset + $limit;
			$html .= Html::element( 'a', [
				'href' => $this->getPageTitle()->getLocalURL( [
					'theme' => $theme,
					'language' => $language,
					'category' => $category,
					'year' => $year,
					'limit' => $limit,
					'offset' => $nextOffset,
				] ),
				'class' => 'sarkarverse-pagination-next',
			], $this->msg( 'sarkarversesong-next' )->text() );
		}

		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * @return string
	 */
	protected function getGroupName(): string {
		return 'pages';
	}
}
