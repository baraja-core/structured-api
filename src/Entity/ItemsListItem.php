<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


class ItemsListItem
{

	/** @var string */
	private $id;

	/** @var mixed[] */
	private $data;


	/**
	 * @param string $id
	 * @param mixed[] $data
	 */
	public function __construct(string $id, array $data = [])
	{
		$this->id = $id;
		$this->data = $data;
	}


	/**
	 * @return string
	 */
	public function getId(): string
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
