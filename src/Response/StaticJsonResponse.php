<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


interface StaticJsonResponse
{
	/**
	 * @return mixed[]
	 */
	public static function toArray(): array;

	public static function getHttpCode(): int;
}
