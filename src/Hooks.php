<?php

namespace MediaWiki\Extension\SarkarverseSong;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;

class Hooks implements ParserFirstCallInitHook {

	private SongStore $songStore;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct( SongStore $songStore, LinkBatchFactory $linkBatchFactory ) {
		$this->songStore = $songStore;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Register parser functions
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( 'song', [ $this, 'renderSong' ] );
		$parser->setFunctionHook( 'songlist', [ $this, 'renderSongList' ] );
	}

	/**
	 * Render the {{#song:}} parser function
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return array
	 */
	public function renderSong( Parser $parser, ...$args ): array {
		// Parse arguments: number|date|title|theme|language|music
		$number = trim( $args[0] ?? '' );
		$date = trim( $args[1] ?? '' );
		$title = trim( $args[2] ?? '' );
		$theme = trim( $args[3] ?? '' );
		$language = trim( $args[4] ?? '' );
		$music = trim( $args[5] ?? '' );

		$parser->getOutput()->addModuleStyles( [ 'ext.sarkarversesong.styles' ] );

		// Store song data in extension data for LinksUpdateComplete hook
		// The hook handler (LinksUpdateHooks) will process all songs in a single batch
		$songData = [
			'number' => $number,
			'date' => $date,
			'title' => $title,
			'theme' => $theme,
			'language' => $language,
			'music' => $music,
		];

		// Append to existing song data array (multiple songs on one page)
		$existingData = $parser->getOutput()->getExtensionData( 'sarkarversesong-songs' ) ?? [];
		$existingData[] = $songData;
		$parser->getOutput()->setExtensionData( 'sarkarversesong-songs', $existingData );

		// Create link to song page
		$titleLink = $title !== '' ? "''[[{$title}]]''" : '';

		// Output table row format (|- before row is standard wikitable syntax)
		$output = "|-\n| {$number} || {$date} || {$titleLink} || {$theme} || {$language} || {$music}";

		return [
			$output,
			'noparse' => false,
			'isHTML' => false,
		];
	}

	/**
	 * Render the {{#songlist:}} parser function
	 * Usage: {{#songlist:categories=Cat1,Cat2,Cat3}}
	 *
	 * @param Parser $parser
	 * @param string ...$args
	 * @return array
	 */
	public function renderSongList( Parser $parser, ...$args ): array {
		$parser->getOutput()->addModuleStyles( [ 'ext.sarkarversesong.styles' ] );
		$parser->getOutput()->addModules( [ 'ext.sarkarversesong.filter' ] );

		// Parse named parameters
		$params = [];
		foreach ( $args as $arg ) {
			if ( strpos( $arg, '=' ) !== false ) {
				[ $key, $value ] = explode( '=', $arg, 2 );
				$params[trim( $key )] = trim( $value );
			}
		}

		// Get categories from params (comma-separated list)
		$categories = [];
		if ( isset( $params['categories'] ) && $params['categories'] !== '' ) {
			$categories = array_map( 'trim', explode( ',', $params['categories'] ) );
			$categories = array_filter( $categories, static fn( $c ) => $c !== '' );
		}

		// Get all songs and years (years are auto-fetched from DB)
		$songs = $this->songStore->getSongs();
		$years = $this->songStore->getYears();

		// Batch fetch all categories in ONE query (fixes N+1 query problem)
		$allCategories = $this->songStore->getAllSongCategories();

		// Batch check page existence for all song titles (ONE query instead of 5000)
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$titleObjects = [];
		foreach ( $songs as $song ) {
			if ( $song['title'] !== '' ) {
				$titleObj = Title::newFromText( $song['title'] );
				if ( $titleObj ) {
					$linkBatch->addObj( $titleObj );
					$titleObjects[$song['title']] = $titleObj;
				}
			}
		}
		$linkBatch->execute();

		// Build the filter UI
		$output = '<div class="sarkarverse-songlist-container">';
		$output .= '<div class="sarkarverse-songlist-filters">';

		// Category filter (only show if categories are specified)
		if ( count( $categories ) > 0 ) {
			$output .= '<label for="sarkarverse-filter-category">Category: </label>';
			$output .= '<select id="sarkarverse-filter-category" class="sarkarverse-filter">';
			$output .= '<option value="">All Songs</option>';
			foreach ( $categories as $category ) {
				$output .= '<option value="' . htmlspecialchars( $category ) . '">' . htmlspecialchars( $category ) . '</option>';
			}
			$output .= '</select>';
		}

		// Year filter
		$output .= '<label for="sarkarverse-filter-year">Year: </label>';
		$output .= '<select id="sarkarverse-filter-year" class="sarkarverse-filter">';
		$output .= '<option value="">All Years</option>';
		foreach ( $years as $year ) {
			$output .= '<option value="' . htmlspecialchars( $year ) . '">' . htmlspecialchars( $year ) . '</option>';
		}
		$output .= '</select>';

		// Song count display
		$totalCount = count( $songs );
		$output .= ' <span class="sarkarverse-song-count">Showing <span id="sarkarverse-visible-count">' . $totalCount . '</span> of ' . $totalCount . ' songs</span>';

		$output .= '</div>';

		// Build the table
		$output .= '<table class="wikitable sarkarverse-songlist" style="width: 100%; text-align: center;">';
		$output .= '<tr><th>Number</th><th>Date</th><th>First line(s)</th><th>Theme</th><th>Language</th><th>Music</th></tr>';

		foreach ( $songs as $song ) {
			// Get categories from pre-fetched data (no additional DB query)
			$songCategories = $allCategories[$song['number']] ?? [];
			$categoriesJson = htmlspecialchars( json_encode( $songCategories ), ENT_QUOTES, 'UTF-8' );

			// Extract year from date
			$songYear = '';
			if ( preg_match( '/(\d{4})/', $song['date'], $matches ) ) {
				$songYear = $matches[1];
			}

			// Create proper HTML link for the song title with red/blue link styling
			$titleLink = '';
			if ( $song['title'] !== '' && isset( $titleObjects[$song['title']] ) ) {
				$songTitle = $titleObjects[$song['title']];
				// Add 'new' class for non-existent pages (red links) - uses cached existence check
				$linkClass = $songTitle->exists() ? '' : ' class="new"';
				$titleLink = '<i><a href="' . htmlspecialchars( $songTitle->getLocalURL() ) . '"' . $linkClass . '>' . htmlspecialchars( $song['title'] ) . '</a></i>';
			}

			$output .= '<tr class="sarkarverse-song-row" data-categories="' . $categoriesJson . '" data-year="' . htmlspecialchars( $songYear ) . '">';
			$output .= '<td>' . htmlspecialchars( $song['number'] ) . '</td>';
			$output .= '<td>' . htmlspecialchars( $song['date'] ) . '</td>';
			$output .= '<td>' . $titleLink . '</td>';
			$output .= '<td>' . htmlspecialchars( $song['theme'] ) . '</td>';
			$output .= '<td>' . htmlspecialchars( $song['language'] ) . '</td>';
			$output .= '<td>' . htmlspecialchars( $song['music'] ) . '</td>';
			$output .= '</tr>';
		}

		$output .= '</table>';
		$output .= '</div>';

		return [
			$output,
			'noparse' => false,
			'isHTML' => true,
		];
	}
}
