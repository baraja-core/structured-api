<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Serializer\Serializer;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Response\Status\StatusResponse;

abstract class BaseResponse implements Response
{
	/** @var array<string, mixed>|Response|StatusResponse */
	protected array|Response|StatusResponse $haystack;

	private Serializer $serializer;

	private int $httpCode;


	/**
	 * @param array<string, mixed>|Response|StatusResponse $haystack
	 */
	final public function __construct(
		Convention $convention,
		array|Response|StatusResponse $haystack,
		int|string $httpCode = 200,
	) {
		if (is_string($httpCode) && preg_match('#^[+-]?\d*[.]?\d+$#D', $httpCode) !== 1) {
			$httpCode = 500;
		}
		$this->serializer = new Serializer($convention);
		$this->haystack = $haystack;
		$this->httpCode = (int) $httpCode;
	}


	public function getContentType(): string
	{
		return 'text/plain';
	}


	public function __toString(): string
	{
		return '';
	}


	/**
	 * @return mixed[]
	 */
	final public function toArray(): array
	{
		return $this->serializer->serialize($this->haystack);
	}


	final public function getHttpCode(): int
	{
		return $this->httpCode;
	}
}
