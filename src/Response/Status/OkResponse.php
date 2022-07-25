<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Response\Status;


use Baraja\StructuredApi\Response;

final class OkResponse extends StatusResponse
{
	/**
	 * @param array<string, mixed>|Response|StatusResponse $data
	 */
	public function __construct(
		public string $state = 'ok',
		public ?string $message = null,
		public ?int $code = null,
		public array|Response|StatusResponse $data = [],
	) {
	}
}
