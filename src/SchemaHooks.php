<?php

namespace MediaWiki\Extension\SarkarverseSong;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Installer\DatabaseUpdater;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {

	/**
	 * Load database schema updates
	 *
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$dir = dirname( __DIR__ ) . '/sql';

		$updater->addExtensionTable(
			'sarkarverse_songs',
			"$dir/tables-generated.sql"
		);

		// Add song_year column for efficient year filtering (avoids LIKE queries)
		$updater->addExtensionField(
			'sarkarverse_songs',
			'song_year',
			"$dir/patch-song_year.sql"
		);

		// Add index for song_year column
		$updater->addExtensionIndex(
			'sarkarverse_songs',
			'idx_song_year',
			"$dir/patch-song_year-index.sql"
		);
	}
}
