<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Response\Status;


final class ErrorResponse extends StatusResponse
{
	public function __construct(
		public string $message,
		public string $state = 'error',
		public ?int $code = null,
		public ?string $hint = null,
	) {
	}
}
