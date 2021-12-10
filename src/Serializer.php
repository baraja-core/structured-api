<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Entity\Convention;

/**
 * Serialize typed object (DTO) to array response.
 */
final class Serializer
{
	public function __construct(
		private Convention $convention
	) {
	}


	public function serialize(object $haystack): Response
	{
		$ref = new \ReflectionClass($haystack);
		$return = [];
		foreach ($ref->getProperties() as $property) {
			$property->setAccessible(true);
			$return[$property->getName()] = $property->getValue($haystack);
		}

		return new JsonResponse($this->convention, $return);
	}


	/**
	 * @return float|int|bool|array<int|string, mixed>|string|null
	 */
	private function process(mixed $haystack): float|null|int|bool|array|string
	{
		if (is_scalar($haystack) || $haystack === null) {
			return $haystack;
		}
		if (is_array($haystack)) {
			return $this->processArray($haystack);
		}
		if (is_object($haystack)) {
			return $this->processObject($haystack);
		}

		throw new \InvalidArgumentException(
			sprintf(
				'Value type "%s" can not be serialized.',
				get_debug_type($haystack),
			),
		);
	}


	/**
	 * @return array<string, mixed>
	 */
	private function processObject(object $haystack): array
	{
		$ref = new \ReflectionClass($haystack);
		$return = [];
		foreach ($ref->getProperties() as $property) {
			$property->setAccessible(true);
			$return[$property->getName()] = $this->process($property->getValue($haystack));
		}

		return $return;
	}


	/**
	 * @param mixed[] $haystack
	 * @return mixed[]
	 */
	private function processArray(array $haystack): array
	{
		$return = [];
		foreach ($haystack as $key => $value) {
			$return[$key] = $this->process($value);
		}

		return $return;
	}
}
