<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


interface HttpCodeResponse
{
	public function getHttpCode(): int;
}
