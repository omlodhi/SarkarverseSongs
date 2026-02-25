<?php

namespace MediaWiki\Extension\SarkarverseSong;

use Wikimedia\Rdbms\IConnectionProvider;

class SongStore {

	private IConnectionProvider $dbProvider;

	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/**
	 * Get categories for multiple songs in a single query
	 * Returns array keyed by song number: [ 'song_number' => [ 'cat1', 'cat2' ], ... ]
	 *
	 * @param array $songNumbers Array of song numbers
	 * @return array
	 */
	public function getAllSongCategories( array $songNumbers = [] ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'ssc_song_number', 'ssc_category' ] )
			->from( 'sarkarverse_song_categories' )
			->orderBy( [ 'ssc_song_number', 'ssc_category' ], 'ASC' )
			->caller( __METHOD__ );

		// If specific song numbers provided, filter by them
		if ( !empty( $songNumbers ) ) {
			$queryBuilder->where( [ 'ssc_song_number' => $songNumbers ] );
		}

		$result = $queryBuilder->fetchResultSet();

		$categoriesByNumber = [];
		foreach ( $result as $row ) {
			$categoriesByNumber[$row->ssc_song_number][] = $row->ssc_category;
		}

		return $categoriesByNumber;
	}

	/**
	 * Delete songs by page ID (when page is deleted or updated)
	 */
	public function deleteSongsByPageId( int $pageId ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'sarkarverse_songs' )
			->where( [ 'song_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Delete song categories by song number
	 */
	public function deleteSongCategories( string $songNumber ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'sarkarverse_song_categories' )
			->where( [ 'ssc_song_number' => $songNumber ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Get all songs with optional filters
	 */
	public function getSongs(
		?string $theme = null,
		?string $language = null,
		?string $category = null,
		?int $limit = null,
		?int $offset = null,
		?string $year = null
	): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'song_id',
				'song_number',
				'song_date',
				'song_title',
				'song_theme',
				'song_language',
				'song_music',
				'song_page_id',
				'song_page_title',
			] )
			->from( 'sarkarverse_songs' )
			->orderBy( 'song_number', 'ASC' )
			->caller( __METHOD__ );

		if ( $theme !== null && $theme !== '' ) {
			$queryBuilder->where( [ 'song_theme' => $theme ] );
		}

		if ( $language !== null && $language !== '' ) {
			$queryBuilder->where( [ 'song_language' => $language ] );
		}

		// Filter by category (from song page)
		if ( $category !== null && $category !== '' ) {
			$queryBuilder->join(
				'sarkarverse_song_categories',
				null,
				'song_number = ssc_song_number'
			);
			$queryBuilder->where( [ 'ssc_category' => $category ] );
		}

		// Filter by year (uses indexed song_year column)
		if ( $year !== null && $year !== '' ) {
			$queryBuilder->where( [ 'song_year' => $year ] );
		}

		if ( $limit !== null ) {
			$queryBuilder->limit( $limit );
		}

		if ( $offset !== null ) {
			$queryBuilder->offset( $offset );
		}

		$result = $queryBuilder->fetchResultSet();

		$songs = [];
		foreach ( $result as $row ) {
			$songs[] = [
				'id' => (int)$row->song_id,
				'number' => $row->song_number,
				'date' => $row->song_date,
				'title' => $row->song_title,
				'theme' => $row->song_theme,
				'language' => $row->song_language,
				'music' => $row->song_music,
				'page_id' => (int)$row->song_page_id,
				'page_title' => $row->song_page_title,
			];
		}

		return $songs;
	}

	/**
	 * Get count of songs with optional filters
	 */
	public function getSongsCount(
		?string $theme = null,
		?string $language = null,
		?string $category = null,
		?string $year = null
	): int {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( 'COUNT(*) as cnt' )
			->from( 'sarkarverse_songs' )
			->caller( __METHOD__ );

		if ( $theme !== null && $theme !== '' ) {
			$queryBuilder->where( [ 'song_theme' => $theme ] );
		}

		if ( $language !== null && $language !== '' ) {
			$queryBuilder->where( [ 'song_language' => $language ] );
		}

		if ( $category !== null && $category !== '' ) {
			$queryBuilder->join(
				'sarkarverse_song_categories',
				null,
				'song_number = ssc_song_number'
			);
			$queryBuilder->where( [ 'ssc_category' => $category ] );
		}

		// Filter by year (uses indexed song_year column)
		if ( $year !== null && $year !== '' ) {
			$queryBuilder->where( [ 'song_year' => $year ] );
		}

		$row = $queryBuilder->fetchRow();

		return (int)$row->cnt;
	}

	/**
	 * Get all unique themes
	 */
	public function getThemes(): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'song_theme' )
			->distinct()
			->from( 'sarkarverse_songs' )
			->where( $dbr->expr( 'song_theme', '!=', '' ) )
			->orderBy( 'song_theme', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$themes = [];
		foreach ( $result as $row ) {
			$themes[] = $row->song_theme;
		}

		return $themes;
	}

	/**
	 * Get all unique languages
	 */
	public function getLanguages(): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'song_language' )
			->distinct()
			->from( 'sarkarverse_songs' )
			->where( $dbr->expr( 'song_language', '!=', '' ) )
			->orderBy( 'song_language', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$languages = [];
		foreach ( $result as $row ) {
			$languages[] = $row->song_language;
		}

		return $languages;
	}

	/**
	 * Get all unique categories (from song pages)
	 */
	public function getCategories(): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'ssc_category' )
			->distinct()
			->from( 'sarkarverse_song_categories' )
			->orderBy( 'ssc_category', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$categories = [];
		foreach ( $result as $row ) {
			$categories[] = $row->ssc_category;
		}

		return $categories;
	}

	/**
	 * Get all unique years from indexed song_year column
	 */
	public function getYears(): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		// Uses indexed song_year column - returns only unique years, not 5000 rows
		$result = $dbr->newSelectQueryBuilder()
			->select( 'song_year' )
			->distinct()
			->from( 'sarkarverse_songs' )
			->where( $dbr->expr( 'song_year', '!=', '' ) )
			->orderBy( 'song_year', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$years = [];
		foreach ( $result as $row ) {
			$years[] = $row->song_year;
		}

		return $years;
	}

	/**
	 * Get a song by its number
	 */
	public function getSongByNumber( string $number ): ?array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$row = $dbr->newSelectQueryBuilder()
			->select( [
				'song_id',
				'song_number',
				'song_date',
				'song_title',
				'song_theme',
				'song_language',
				'song_music',
				'song_page_id',
				'song_page_title',
			] )
			->from( 'sarkarverse_songs' )
			->where( [ 'song_number' => $number ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			return null;
		}

		return [
			'id' => (int)$row->song_id,
			'number' => $row->song_number,
			'date' => $row->song_date,
			'title' => $row->song_title,
			'theme' => $row->song_theme,
			'language' => $row->song_language,
			'music' => $row->song_music,
			'page_id' => (int)$row->song_page_id,
			'page_title' => $row->song_page_title,
		];
	}
}
