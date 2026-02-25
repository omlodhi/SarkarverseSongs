<?php

namespace MediaWiki\Extension\SarkarverseSong;

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;

class LinksUpdateHooks {

	private SongStore $songStore;
	private IConnectionProvider $dbProvider;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		SongStore $songStore,
		IConnectionProvider $dbProvider,
		LinkBatchFactory $linkBatchFactory
	) {
		$this->songStore = $songStore;
		$this->dbProvider = $dbProvider;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * Handle LinksUpdateComplete - process all songs from the page in a single batch
	 *
	 * @param LinksUpdate $linksUpdate
	 * @param mixed $ticket
	 */
	public function onLinksUpdateComplete( LinksUpdate $linksUpdate, $ticket ): void {
		$parserOutput = $linksUpdate->getParserOutput();
		$songs = $parserOutput->getExtensionData( 'sarkarversesong-songs' );

		if ( !$songs || !is_array( $songs ) || count( $songs ) === 0 ) {
			return;
		}

		$pageId = $linksUpdate->getPageId();
		$pageTitle = $linksUpdate->getTitle()->getPrefixedText();

		// Batch store all songs (ONE query)
		$this->batchStoreSongs( $songs, $pageId, $pageTitle );

		// Batch fetch and store categories for all songs
		$this->batchStoreSongCategories( $songs );
	}

	/**
	 * Store all songs in a single database operation using batch INSERT
	 *
	 * @param array $songs
	 * @param int $pageId
	 * @param string $pageTitle
	 */
	private function batchStoreSongs( array $songs, int $pageId, string $pageTitle ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();

		// Build all rows for batch insert
		$rows = [];
		foreach ( $songs as $song ) {
			$number = $song['number'] ?? '';
			if ( $number === '' ) {
				continue;
			}

			// Extract year from date for indexed filtering
			$year = '';
			if ( preg_match( '/(\d{4})/', $song['date'] ?? '', $matches ) ) {
				$year = $matches[1];
			}

			$rows[] = [
				'song_number' => $number,
				'song_date' => $song['date'] ?? '',
				'song_year' => $year,
				'song_title' => $song['title'] ?? '',
				'song_theme' => $song['theme'] ?? '',
				'song_language' => $song['language'] ?? '',
				'song_music' => $song['music'] ?? '',
				'song_page_id' => $pageId,
				'song_page_title' => $pageTitle,
			];
		}

		if ( empty( $rows ) ) {
			return;
		}

		// Single batch REPLACE - portable across DB backends
		$dbw->newReplaceQueryBuilder()
			->replaceInto( 'sarkarverse_songs' )
			->uniqueIndexFields( [ 'song_number' ] )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Batch fetch categories from song pages and store them
	 * Uses LinkBatch for efficient existence checking
	 *
	 * @param array $songs
	 */
	private function batchStoreSongCategories( array $songs ): void {
		// First, collect all titles and use LinkBatch for batch existence check
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		$titleMap = []; // title text => [ 'obj' => Title, 'number' => song_number ]

		foreach ( $songs as $song ) {
			$title = $song['title'] ?? '';
			$number = $song['number'] ?? '';
			if ( $title !== '' && $number !== '' ) {
				$titleObj = Title::newFromText( $title );
				if ( $titleObj ) {
					$linkBatch->addObj( $titleObj );
					$titleMap[$title] = [
						'obj' => $titleObj,
						'number' => $number,
					];
				}
			}
		}

		// Execute batch existence check (ONE query for all titles)
		$linkBatch->execute();

		// Now filter to only existing pages and build pageId => songNumber map
		$titleToNumber = [];
		foreach ( $titleMap as $data ) {
			if ( $data['obj']->exists() ) {
				$titleToNumber[$data['obj']->getArticleID()] = $data['number'];
			}
		}

		if ( empty( $titleToNumber ) ) {
			return;
		}

		$pageIds = array_keys( $titleToNumber );

		// Fetch all categories for all song pages in ONE query
		$dbr = $this->dbProvider->getReplicaDatabase();
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'cl_from', 'cl_to' ] )
			->from( 'categorylinks' )
			->where( [ 'cl_from' => $pageIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Group categories by song number
		$categoriesByNumber = [];
		foreach ( $result as $row ) {
			$songNumber = $titleToNumber[$row->cl_from];
			$categoryName = str_replace( '_', ' ', $row->cl_to );
			$categoriesByNumber[$songNumber][] = $categoryName;
		}

		if ( empty( $categoriesByNumber ) ) {
			return;
		}

		$dbw = $this->dbProvider->getPrimaryDatabase();
		$songNumbers = array_keys( $categoriesByNumber );

		// Batch DELETE all existing categories for these songs (ONE query)
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'sarkarverse_song_categories' )
			->where( [ 'ssc_song_number' => $songNumbers ] )
			->caller( __METHOD__ )
			->execute();

		// Build all category rows for batch INSERT
		$categoryRows = [];
		foreach ( $categoriesByNumber as $songNumber => $categories ) {
			foreach ( $categories as $category ) {
				$categoryRows[] = [
					'ssc_song_number' => $songNumber,
					'ssc_category' => $category,
				];
			}
		}

		// Batch INSERT all categories (ONE query)
		if ( !empty( $categoryRows ) ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'sarkarverse_song_categories' )
				->rows( $categoryRows )
				->caller( __METHOD__ )
				->execute();
		}
	}
}
