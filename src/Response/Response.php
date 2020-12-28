<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


interface Response
{
	public function getContentType(): string;

	public function __toString(): string;

	/**
	 * @return mixed[]
	 */
	public function toArray(): array;

	/**
	 * @return mixed[]
	 */
	public function getArray(): array;

	public function getHttpCode(): int;
}
