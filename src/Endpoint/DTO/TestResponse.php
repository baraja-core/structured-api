<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Endpoint\DTO;


final class TestResponse
{
	public function __construct(
		public string $name,
		public string $hello,
		public string $endpoint,
	) {
	}
}
