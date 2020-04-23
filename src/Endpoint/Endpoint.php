<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


interface Endpoint
{
	/**
	 * @param mixed[] $data
	 */
	public function setData(array $data): void;

	public function startup(): void;

	public function startupCheck(): void;

	public function saveState(): void;

	public function __toString(): string;
}