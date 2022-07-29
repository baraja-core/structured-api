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


	/**
	 * @phpstan-return never-return
	 * @throws self
	 */
	public static function invoke(Response $response): void
	{
		throw new self($response);
	}


	public function getResponse(): Response
	{
		return $this->response;
	}
}
