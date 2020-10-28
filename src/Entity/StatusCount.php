<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


use Nette\Utils\Strings;

final class StatusCount
{
	private string $key;

	private string $label;

	private int $count;


	public function __construct(string $key, int $count, ?string $label = null)
	{
		$this->key = $key;
		$this->count = $count;
		$this->label = $label ?? Strings::firstUpper(str_replace('-', ' ', $key));
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
