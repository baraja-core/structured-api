<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Endpoint\DTO;


final class PingResponse
{
	public function __construct(
		public string $result,
		public string $ip,
		public \DateTimeInterface $datetime,
	) {
	}
}
