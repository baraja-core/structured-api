<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Response\Status;


use Baraja\StructuredApi\Response;
use Baraja\StructuredApi\ThrowStatusResponse;

final class SuccessResponse extends StatusResponse
{
	/**
	 * @param array<string, mixed>|Response|StatusResponse $data
	 */
	final public function __construct(
		public string $state = 'success',
		public ?string $message = null,
		public ?int $code = null,
		public array|Response|StatusResponse $data = [],
	) {
		if ($code === null) {
			$this->code = $this->getHttpCode();
		}
	}


	/**
	 * @param array<string, mixed>|Response|StatusResponse $data
	 * @phpstan-return never-return
	 * @throws ThrowStatusResponse
	 */
	public static function invoke(
		string $state = 'success',
		?string $message = null,
		?int $code = null,
		array|Response|StatusResponse $data = [],
	): void {
		ThrowStatusResponse::invoke(new self($state, $message, $code, $data));
	}


	public function getHttpCode(): int
	{
		return 200;
	}
}
