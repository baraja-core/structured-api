<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


interface BulkAction
{
	/**
	 * @return array<string, string> (action => label)
	 */
	public function getBulkActionsList(): array;

	/**
	 * @param array<int, int|string> $ids
	 */
	public function postProcessBulkAction(string $action, array $ids): void;
}
