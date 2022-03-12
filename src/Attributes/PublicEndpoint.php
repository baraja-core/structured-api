<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Attributes;

use Attribute;


#[Attribute(Attribute::TARGET_CLASS)]
class PublicEndpoint
{
	public function __construct(
		private bool $requireToken = false,
	) {
	}


	public function isRequireToken(): bool
	{
		return $this->requireToken;
	}
}
