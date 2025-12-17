<?php

namespace MediaWiki\Extension\SarkarverseSong;

use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;

class Hooks implements ParserFirstCallInitHook {

	private SongStore $songStore;

	public function __construct( SongStore $songStore ) {
		$this->songStore = $songStore;
	}

	/**
	 * Register parser function
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( 'song', [ $this, 'renderSong' ] );
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

		// Get current page info (list page where {{#song:}} is used)
		$listPageTitle = $parser->getTitle();
		$listPageId = $listPageTitle->getArticleID();
		$listPageTitleText = $listPageTitle->getPrefixedText();

		$parser->getOutput()->addModuleStyles( [ 'ext.sarkarversesong.styles' ] );

		// Store song data in extension data for LinksUpdate
		$songStore = $this->songStore;
		$songData = [
			'number' => $number,
			'date' => $date,
			'title' => $title,
			'theme' => $theme,
			'language' => $language,
			'music' => $music,
			'list_page_id' => $listPageId,
			'list_page_title' => $listPageTitleText,
		];

		// Append to existing song data array (multiple songs on one page)
		$existingData = $parser->getOutput()->getExtensionData( 'sarkarversesong-songs' ) ?? [];
		$existingData[] = $songData;
		$parser->getOutput()->setExtensionData( 'sarkarversesong-songs', $existingData );

		// Register LinksUpdate hook to store data and fetch categories from song page
		\MediaWiki\MediaWikiServices::getInstance()->getHookContainer()->register(
			'LinksUpdateComplete',
			static function ( LinksUpdate $linksUpdate ) use ( $songStore, $number, $date, $title, $theme, $language, $music, $listPageId, $listPageTitleText ) {
				if ( $number === '' ) {
					return;
				}

				// Store the song data
				$songStore->storeSong(
					$number,
					$date,
					$title,
					$theme,
					$language,
					$music,
					$listPageId,
					$listPageTitleText
				);

				// Now fetch categories from the individual song page (e.g., "Bandhu he niye calo")
				if ( $title !== '' ) {
					$songPageTitle = Title::newFromText( $title );
					if ( $songPageTitle && $songPageTitle->exists() ) {
						$songPageId = $songPageTitle->getArticleID();

						// Get categories from the categorylinks table for the song page
						$dbr = \MediaWiki\MediaWikiServices::getInstance()
							->getDBLoadBalancerFactory()
							->getReplicaDatabase();

						$result = $dbr->newSelectQueryBuilder()
							->select( 'cl_to' )
							->from( 'categorylinks' )
							->where( [ 'cl_from' => $songPageId ] )
							->caller( __METHOD__ )
							->fetchResultSet();

						$categories = [];
						foreach ( $result as $row ) {
							// cl_to stores category name without "Category:" prefix
							$categories[] = str_replace( '_', ' ', $row->cl_to );
						}

						// Store categories for this song
						if ( !empty( $categories ) ) {
							$songStore->storeSongCategories( $number, $categories );
						}
					}
				}
			}
		);

		// Create link to song page
		$titleLink = $title !== '' ? "''[[{$title}]]''" : '';

		// Output table row format
		$output = "| {$number} || {$date} || {$titleLink} || {$theme} || {$language} || {$music}\n|-";

		return [
			$output,
			'noparse' => false,
			'isHTML' => false,
		];
	}
}
