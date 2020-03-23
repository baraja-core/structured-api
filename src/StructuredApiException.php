<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


class StructuredApiException extends \Exception
{
	/**
	 * @param string $version
	 * @throws StructuredApiException
	 */
	public static function invalidVersion(string $version): void
	{
		throw new self(
			'Invalid API version, because version "' . $version . '" given.'
			. "\n" . 'Version must be integer between 1 and 999.'
		);
	}


	/**
	 * @param string $route
	 * @param mixed[] $params
	 * @throws StructuredApiException
	 */
	public static function canNotRouteException(string $route, array $params): void
	{
		throw new self(
			'Can not route "' . $route . '"'
			. ($params !== [] ? ' with parameters: ' . "\n" . json_encode($params) : '.')
		);
	}


	/**
	 * @param string $class
	 * @throws StructuredApiException
	 */
	public static function routeClassDoesNotExist(string $class): void
	{
		throw new self('Route class "' . $class . '" does not exist.');
	}


	/**
	 * @param string $path
	 * @throws StructuredApiException
	 */
	public static function invalidApiPath(string $path): void
	{
		throw new self(
			'Invalid API URL path "' . $path . '".'
			. "\n" . 'Did you mean format "api/v1/<endpoint>/<action>"?'
		);
	}


	/**
	 * @param string $path
	 * @throws StructuredApiException
	 */
	public static function apiEndpointMustReturnSomeData(string $path): void
	{
		throw new self(
			'Api endpoint "' . $path . '" must return some output. None returned.'
		);
	}
}
