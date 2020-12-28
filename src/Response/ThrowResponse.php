<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class ThrowResponse extends \RuntimeException
{
	private Response $response;


	public function __construct(Response $response)
	{
		parent::__construct('');
		$this->response = $response;
	}


	public function getResponse(): Response
	{
		return $this->response;
	}
}
