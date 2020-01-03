<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


class ThrowResponse extends \RuntimeException
{

	/**
	 * @var BaseResponse
	 */
	private $response;

	/**
	 * @param BaseResponse $response
	 */
	public function __construct(BaseResponse $response)
	{
		parent::__construct('');
		$this->response = $response;
	}

	/**
	 * @return BaseResponse
	 */
	public function getResponse(): BaseResponse
	{
		return $this->response;
	}

}