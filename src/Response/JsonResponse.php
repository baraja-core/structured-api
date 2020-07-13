<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;
use Tracy\ILogger;

final class JsonResponse extends BaseResponse
{
	public function getContentType(): string
	{
		return 'application/json';
	}


	public function __toString(): string
	{
		try {
			return $this->getJson();
		} catch (JsonException $e) {
			if (class_exists('\Tracy\Debugger') === true) {
				Debugger::log($e, ILogger::CRITICAL);
			}

			return '{}';
		}
	}


	/**
	 * @return string
	 * @throws JsonException
	 */
	public function getJson(): string
	{
		return Json::encode($this->toArray(), Json::PRETTY);
	}
}
