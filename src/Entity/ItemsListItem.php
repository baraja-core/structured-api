<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


final class ItemsListItem
{

	/** @var int|string */
	private $id;

	/** @var mixed[] */
	private array $data;


	/**
	 * @param int|string|mixed $id
	 * @param mixed[] $data
	 */
	public function __construct($id, array $data = [])
	{
		if (\is_int($id) === false && \is_string($id) === false) {
			throw new \InvalidArgumentException('Identifier must be integer or string, but "' . \gettype($id) . '" given.');
		}

		$this->id = $id;
		$this->data = $data;
	}


	/**
	 * @return int|string
	 */
	public function getId()
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
