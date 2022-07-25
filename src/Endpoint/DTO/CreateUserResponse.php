<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Endpoint\DTO;


final class CreateUserResponse
{
	public function __construct(
		public string $username,
	) {
	}
}
