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
	}
}
