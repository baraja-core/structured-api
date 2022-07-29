<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Response\Status;


final class NotFoundResponse extends ErrorResponse
{
	public function getHttpCode(): int
	{
		return 404;
	}
}
