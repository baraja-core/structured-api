<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


final class ItemsListItem
{
	/** @var mixed[] */
	private array $data;


	/**
	 * @param mixed[] $data
	 */
	public function __construct(
		private int|string $id,
		array $data = []
	) {
		$this->data = $data;
	}


	public function getId(): int|string
	{
		return $this->id;
	}


	/**
	 * @return mixed[]
	 */
	public function getData(): array
	{
		return $this->data;
	}
}
