<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class ThrowResponse extends \RuntimeException
{
	public function __construct(
		private Response $response,
	) {
		parent::__construct('');
	}


	public function getResponse(): Response
	{
		return $this->response;
	}
}
