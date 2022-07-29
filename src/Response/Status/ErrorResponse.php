<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Response\Status;


use Baraja\StructuredApi\ThrowStatusResponse;

class ErrorResponse extends StatusResponse
{
	public function __construct(
		public string $message,
		public string $state = 'error',
		public ?int $code = null,
		public ?string $hint = null,
	) {
		if ($code === null) {
			$this->code = $this->getHttpCode();
		}
	}


	/**
	 * @phpstan-return never-return
	 * @throws ThrowStatusResponse
	 */
	public static function invoke(
		string $message,
		string $state = 'error',
		?int $code = null,
		?string $hint = null,
	): void {
		ThrowStatusResponse::invoke(new static($message, $state, $code, $hint));
	}


	public function getHttpCode(): int
	{
		return 500;
	}
}
