<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


abstract class BaseResponse
{

	/**
	 * @var mixed[]
	 */
	protected $haystack;

	/**
	 * @param mixed[] $haystack
	 */
	public function __construct(array $haystack)
	{
		$this->haystack = $haystack;
	}

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return 'text/plain';
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return '';
	}

}