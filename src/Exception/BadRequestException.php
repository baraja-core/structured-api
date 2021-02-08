<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class BadRequestException extends \RuntimeException
{
	/** @var int */
	protected $code = 404;


	public function __construct(string $message = '', int $httpCode = 0, ?\Throwable $previous = null)
	{
		parent::__construct($message, $httpCode ?: $this->code, $previous);
	}


	public function getHttpCode(): int
	{
		return $this->code;
	}
}
