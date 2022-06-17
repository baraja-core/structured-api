<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


interface StaticJsonResponse
{
	public static function __toString(): string;

	/**
	 * @return mixed[]
	 */
	public static function toArray(): array;

	public static function getHttpCode(): int;
}
