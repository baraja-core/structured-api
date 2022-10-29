<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


interface Response extends HttpCodeResponse
{
	public function getContentType(): string;

	public function __toString(): string;

	/**
	 * @return mixed[]
	 */
	public function toArray(): array;
}
