<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Utils\Json;
use Nette\Utils\JsonException;

class JsonResponse extends BaseResponse
{

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return 'application/json';
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		try {
			return $this->getJson();
		} catch (JsonException $e) {
			return '{}';
		}
	}

	/**
	 * @return mixed[]
	 */
	public function getHaystack(): array
	{
		return $this->haystack;
	}

	/**
	 * @return string
	 * @throws JsonException
	 */
	public function getJson(): string
	{
		return Json::encode($this->process($this->haystack), Json::PRETTY);
	}

	/**
	 * Convert common haystack to json compatible format.
	 *
	 * @param mixed $haystack
	 * @return array|string|mixed
	 */
	private function process($haystack)
	{
		if (\is_array($haystack) === true) {
			$return = [];

			foreach ($haystack as $key => $value) {
				$return[$key] = $this->hideKey($key, $value) ? self::$hiddenKeyLabel : $this->process($value);
			}

			return $return;
		}

		if (\is_object($haystack) === true) {
			if (\method_exists($haystack, '__toString') === true) {
				return (string) $haystack;
			}

			$return = [];

			try {
				foreach ((new \ReflectionClass($haystack))->getProperties() as $property) {
					$property->setAccessible(true);

					if (($key = $property->getName()) && ($key[0] ?? '') === '_') {
						continue;
					}

					$value = $property->getValue($haystack);
					$return[$key] = $this->hideKey($key, $value) ? self::$hiddenKeyLabel : $this->process($value);
				}
			} catch (\ReflectionException $e) {
				foreach ($haystack as $key => $value) {
					$return[$key] = $this->hideKey($key, $value) ? self::$hiddenKeyLabel : $this->process($value);
				}
			}

			return $return;
		}

		return $haystack;
	}

}