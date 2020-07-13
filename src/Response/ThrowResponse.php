<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class ThrowResponse extends \RuntimeException
{
	/** @var BaseResponse */
	private $response;


	public function __construct(BaseResponse $response)
	{
		parent::__construct('');
		$this->response = $response;
	}


	public function getResponse(): BaseResponse
	{
		return $this->response;
	}
}
