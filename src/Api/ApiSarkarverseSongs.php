<?php

namespace MediaWiki\Extension\SarkarverseSong\Api;

use ApiBase;
use MediaWiki\Extension\SarkarverseSong\SongStore;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class ApiSarkarverseSongs extends ApiBase {

	private SongStore $songStore;

	public function __construct( $main, $action, SongStore $songStore ) {
		parent::__construct( $main, $action );
		$this->songStore = $songStore;
	}

	public function execute(): void {
		$params = $this->extractRequestParams();

		$theme = $params['theme'] ?? null;
		$language = $params['language'] ?? null;
		$category = $params['category'] ?? null;
		$year = $params['year'] ?? null;
		$limit = $params['limit'];
		$offset = $params['offset'];

		$result = $this->getResult();

		// Get songs
		$songs = $this->songStore->getSongs( $theme, $language, $category, $limit, $offset, $year );
		$total = $this->songStore->getSongsCount( $theme, $language, $category, $year );

		$result->addValue( null, 'total', $total );
		$result->addValue( null, 'songs', $songs );

		// Add available filters
		$result->addValue( null, 'themes', $this->songStore->getThemes() );
		$result->addValue( null, 'languages', $this->songStore->getLanguages() );
		$result->addValue( null, 'categories', $this->songStore->getCategories() );
		$result->addValue( null, 'years', $this->songStore->getYears() );
	}

	/**
	 * @return array
	 */
	protected function getAllowedParams(): array {
		return [
			'theme' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'language' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'category' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'year' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'limit' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 50,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 500,
			],
			'offset' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 0,
				IntegerDef::PARAM_MIN => 0,
			],
		];
	}

	/**
	 * @return array
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=sarkarversesongs' => 'apihelp-sarkarversesongs-example-1',
			'action=sarkarversesongs&theme=Enlightenment' => 'apihelp-sarkarversesongs-example-2',
			'action=sarkarversesongs&language=Bengali&limit=10' => 'apihelp-sarkarversesongs-example-3',
			'action=sarkarversesongs&category=Prabhat%20Samgiita' => 'apihelp-sarkarversesongs-example-4',
			'action=sarkarversesongs&year=1982' => 'apihelp-sarkarversesongs-example-5',
		];
	}
}
