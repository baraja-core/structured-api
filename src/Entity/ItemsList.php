<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


final class ItemsList
{
	/** @var array<int, ItemsListItem> */
	private array $items;


	/**
	 * @param array<int, ItemsListItem> $items
	 */
	public function __construct(array $items = [])
	{
		$this->items = $items;
	}


	/**
	 * @param array<int, ItemsListItem> $items
	 */
	public static function from(array $items): self
	{
		return new self($items);
	}


	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getData(): array
	{
		$return = [];
		foreach ($this->items as $item) {
			$return[] = array_merge($item->getData(), [
				'id' => $item->getId(),
			]);
		}

		return $return;
	}


	public function addItem(ItemsListItem $item): self
	{
		$this->items[] = $item;

		return $this;
	}


	/**
	 * @return array<int, ItemsListItem>
	 */
	public function getItems(): array
	{
		return $this->items;
	}
}
