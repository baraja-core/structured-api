<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Response\Status\StatusResponse;

final class ThrowStatusResponse extends \RuntimeException
{
	public function __construct(
		private StatusResponse $response,
	) {
		parent::__construct('');
	}


	/**
	 * @phpstan-return never-return
	 * @throws self
	 */
	public static function invoke(StatusResponse $response): void
	{
		throw new self($response);
	}


	public function getResponse(): StatusResponse
	{
		return $this->response;
	}
}
