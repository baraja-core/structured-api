<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


use Baraja\StructuredApi\Helpers;

final class StatusCount
{
	private string $label;


	public function __construct(
		private string $key,
		private int $count,
		?string $label = null,
	) {
		$this->label = $label ?? Helpers::firstUpper(str_replace('-', ' ', $key));
	}


	public function getKey(): string
	{
		return $this->key;
	}


	public function getLabel(): string
	{
		return $this->label;
	}


	public function getCount(): int
	{
		return $this->count;
	}
}
