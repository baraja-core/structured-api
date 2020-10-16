<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


interface BulkAction
{
	/**
	 * @return string[] (action => label)
	 */
	public function getBulkActionsList(): array;

	/**
	 * @param string[] $ids
	 */
	public function postProcessBulkAction(string $action, array $ids): void;
}
