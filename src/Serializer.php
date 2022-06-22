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
		private Convention $convention,
	) {
	}


	public function serialize(object $haystack): Response
	{
		return new JsonResponse(
			$this->convention,
			$this->processObject($haystack, 0),
		);
	}


	/**
	 * @return float|int|bool|array<int|string, mixed>|string|null
	 */
	private function process(mixed $haystack, int $level): float|null|int|bool|array|string
	{
		if ($level >= 32) {
			throw new \LogicException('Structure is too deep.');
		}
		if (is_scalar($haystack) || $haystack === null) {
			return $haystack;
		}
		if (is_array($haystack)) {
			return $this->processArray($haystack, $level);
		}
		if (is_object($haystack)) {
			return $this->processObject($haystack, $level);
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
	private function processObject(object $haystack, int $level): array
	{
		$ref = new \ReflectionClass($haystack);
		$return = [];
		foreach ($ref->getProperties() as $property) {
			$property->setAccessible(true);
			$return[$property->getName()] = $this->process($property->getValue($haystack), $level);
		}

		return $return;
	}


	/**
	 * @param mixed[] $haystack
	 * @return mixed[]
	 */
	private function processArray(array $haystack, int $level): array
	{
		$return = [];
		foreach ($haystack as $key => $value) {
			$return[$key] = $this->process($value, $level);
		}

		return $return;
	}
}
