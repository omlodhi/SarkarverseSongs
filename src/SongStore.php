<?php

namespace MediaWiki\Extension\SarkarverseSong;

use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

class SongStore {

	private IConnectionProvider $dbProvider;

	public function __construct( IConnectionProvider $dbProvider ) {
		$this->dbProvider = $dbProvider;
	}

	/**
	 * Store a song in the database
	 */
	public function storeSong(
		string $number,
		string $date,
		string $title,
		string $theme,
		string $language,
		string $music,
		int $pageId,
		string $pageTitle
	): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();

		$dbw->newReplaceQueryBuilder()
			->replaceInto( 'sarkarverse_songs' )
			->uniqueIndexFields( [ 'song_number' ] )
			->row( [
				'song_number' => $number,
				'song_date' => $date,
				'song_title' => $title,
				'song_theme' => $theme,
				'song_language' => $language,
				'song_music' => $music,
				'song_page_id' => $pageId,
				'song_page_title' => $pageTitle,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Store categories for a song (from the individual song page)
	 */
	public function storeSongCategories( string $songNumber, array $categories ): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();

		// Delete existing categories for this song
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'sarkarverse_song_categories' )
			->where( [ 'ssc_song_number' => $songNumber ] )
			->caller( __METHOD__ )
			->execute();

		// Insert new categories
		foreach ( $categories as $category ) {
			$category = trim( $category );
			if ( $category === '' ) {
				continue;
			}
			$dbw->newInsertQueryBuilder()
				->insertInto( 'sarkarverse_song_categories' )
				->row( [
					'ssc_song_number' => $songNumber,
					'ssc_category' => $category,
				] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Get categories for a song
	 */
	public function getSongCategories( string $songNumber ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'ssc_category' )
			->from( 'sarkarverse_song_categories' )
			->where( [ 'ssc_song_number' => $songNumber ] )
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

		// Filter by year (extracted from song_date)
		if ( $year !== null && $year !== '' ) {
			$queryBuilder->where( $dbr->expr( 'song_date', IExpression::LIKE, new LikeValue( $dbr->anyString(), $year, $dbr->anyString() ) ) );
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

		// Filter by year (extracted from song_date)
		if ( $year !== null && $year !== '' ) {
			$queryBuilder->where( $dbr->expr( 'song_date', IExpression::LIKE, new LikeValue( $dbr->anyString(), $year, $dbr->anyString() ) ) );
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
	 * Get all unique years from song dates
	 */
	public function getYears(): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$result = $dbr->newSelectQueryBuilder()
			->select( 'song_date' )
			->distinct()
			->from( 'sarkarverse_songs' )
			->where( $dbr->expr( 'song_date', '!=', '' ) )
			->caller( __METHOD__ )
			->fetchResultSet();

		$years = [];
		foreach ( $result as $row ) {
			// Extract year from date string (assuming format like "1982-09-14" or "14 September 1982")
			if ( preg_match( '/(\d{4})/', $row->song_date, $matches ) ) {
				$years[$matches[1]] = true;
			}
		}

		$years = array_keys( $years );
		sort( $years );

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
