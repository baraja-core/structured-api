<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


use Nette\Utils\Strings;

final class StatusCount
{

	/** @var string */
	private $key;

	/** @var string */
	private $label;

	/** @var int */
	private $count;


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
