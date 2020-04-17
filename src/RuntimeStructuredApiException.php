<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class RuntimeStructuredApiException extends StructuredApiException
{
	/**
	 * @param Endpoint $endpoint
	 * @param string $parameter
	 * @param int $position
	 * @param string $method
	 * @throws RuntimeStructuredApiException
	 */
	public static function parameterDoesNotSet(Endpoint $endpoint, string $parameter, int $position, string $method): void
	{
		throw new self(
			$endpoint . ': Parameter $' . $parameter . ' of method "' . $method . '" '
			. 'on position #' . $position . ' does not exist.'
		);
	}


	/**
	 * @param \Throwable $e
	 * @throws RuntimeStructuredApiException
	 */
	public static function reflectionException(\Throwable $e): void
	{
		throw new self($e->getMessage(), $e->getCode(), $e);
	}


	/**
	 * @param Endpoint $endpoint
	 * @throws RuntimeStructuredApiException
	 */
	public static function startupDoesntCallParent(Endpoint $endpoint): void
	{
		throw new self('Method ' . $endpoint . '::startup() or its descendant doesn\'t call parent::startup()."');
	}


	/**
	 * @param Endpoint $endpoint
	 * @param string $parameter
	 * @param string $class
	 * @throws RuntimeStructuredApiException
	 */
	public static function parameterMustBeObject(Endpoint $endpoint, string $parameter, string $class): void
	{
		throw new self(
			$endpoint . ': Parameter "' . $parameter . '" must be object '
			. 'of type "' . $class . '" but empty value given.'
		);
	}


	/**
	 * @param Endpoint $endpoint
	 * @param string $parameter
	 * @param string $typeName
	 * @throws RuntimeStructuredApiException
	 */
	public static function canNotCreateEmptyValueByType(Endpoint $endpoint, string $parameter, string $typeName): void
	{
		throw new self(
			$endpoint . ': Can not create default empty value for parameter "' . $parameter . '"'
			. ' type "' . $typeName . '" given.'
		);
	}


	/**
	 * @param Endpoint $endpoint
	 * @param string|null $type
	 * @throws RuntimeStructuredApiException
	 */
	public static function propertyDataMustBeArray(Endpoint $endpoint, ?string $type): void
	{
		throw new self(
			$endpoint . ': Api parameter "data" must be type of "array". '
			. ($type === null ? 'No type has been defined. Did you set PHP 7 strict data types?' : 'Type "' . $type . '" given.')
		);
	}
}
