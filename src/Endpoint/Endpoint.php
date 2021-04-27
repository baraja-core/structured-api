<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Service;
use Baraja\StructuredApi\Entity\Convention;

interface Endpoint extends Service
{
	/**
	 * @param array<mixed, mixed> $data
	 */
	public function setData(array $data): void;

	public function setConvention(Convention $convention): void;

	public function startup(): void;

	public function startupCheck(): void;

	public function saveState(): void;
}
