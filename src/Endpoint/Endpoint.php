<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Service;

interface Endpoint extends Service
{
	/**
	 * @param mixed[] $data
	 */
	public function setData(array $data): void;

	public function startup(): void;

	public function startupCheck(): void;

	public function saveState(): void;
}