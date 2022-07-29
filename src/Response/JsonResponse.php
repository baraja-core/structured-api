<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;
use Tracy\ILogger;

class JsonResponse extends BaseResponse
{
	public function getContentType(): string
	{
		return 'application/json';
	}


	public function __toString(): string
	{
		return $this->getJson();
	}


	public function getJson(): string
	{
		try {
			return Json::encode($this->toArray(), Json::PRETTY);
		} catch (JsonException $e) {
			trigger_error($e->getMessage());
			if (class_exists('\Tracy\Debugger') === true) {
				Debugger::log($e, ILogger::CRITICAL);
			}

			return '{}';
		}
	}
}
