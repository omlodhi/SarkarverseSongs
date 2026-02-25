<?php

use MediaWiki\Extension\SarkarverseSong\SongStore;
use MediaWiki\MediaWikiServices;

return [
	'SarkarverseSong.SongStore' => static function ( MediaWikiServices $services ): SongStore {
		return new SongStore(
			$services->getConnectionProvider()
		);
	},
];
