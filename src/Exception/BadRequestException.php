<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class BadRequestException extends \RuntimeException
{
	public function __construct(string $message = '', int $httpCode = 0, ?\Throwable $previous = null)
	{
		parent::__construct(
			$message,
			$httpCode === 0 ? (int) $this->code : $httpCode,
			$previous,
		);
	}


	public function getHttpCode(): int
	{
		return (int) $this->code;
	}
}
