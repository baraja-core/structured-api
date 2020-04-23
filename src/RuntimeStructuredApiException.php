<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class RuntimeStructuredApiException extends StructuredApiException
{

	/**
	 * @param Endpoint $endpoint
	 * @throws RuntimeStructuredApiException
	 */
	public static function startupDoesntCallParent(Endpoint $endpoint): void
	{
		throw new self('Method ' . $endpoint . '::startup() or its descendant doesn\'t call parent::startup()."');
	}
}
