<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


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
			return json_encode(
				$this->toArray(),
				JSON_THROW_ON_ERROR
				| JSON_UNESCAPED_SLASHES
				| JSON_UNESCAPED_UNICODE
				| JSON_PRETTY_PRINT
				| (defined('JSON_PRESERVE_ZERO_FRACTION') ? JSON_PRESERVE_ZERO_FRACTION : 0),
			);
		} catch (\JsonException $e) {
			trigger_error($e->getMessage());
			if (class_exists('\Tracy\Debugger') === true) {
				Debugger::log($e, ILogger::CRITICAL);
			}
		}

		return '{}';
	}
}
