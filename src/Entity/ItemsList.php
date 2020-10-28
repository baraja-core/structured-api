<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


final class ItemsList
{

	/** @var ItemsListItem[] */
	private array $items;


	/**
	 * @param ItemsListItem[] $items
	 */
	public function __construct(array $items = [])
	{
		$this->items = $items;
	}


	/**
	 * @param ItemsListItem[] $items
	 * @return self
	 */
	public static function from(array $items): self
	{
		return new self($items);
	}


	/**
	 * @return mixed[][]
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
	 * @return ItemsListItem[]
	 */
	public function getItems(): array
	{
		return $this->items;
	}
}
