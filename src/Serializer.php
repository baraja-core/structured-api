<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Localization\Translation;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Entity\ItemsList;
use Baraja\StructuredApi\Entity\StatusCount;
use Nette\Utils\Paginator;

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
			if ($haystack instanceof Translation) {
				return (string) $haystack;
			}
			if ($haystack instanceof \DateTimeInterface) {
				return $haystack->format($this->convention->getDateTimeFormat());
			}
			if ($haystack instanceof Paginator) {
				return $this->processPaginator($haystack);
			}
			if ($haystack instanceof StatusCount) {
				return $this->processStatusCount($haystack);
			}
			if ($haystack instanceof ItemsList) {
				return $this->process($haystack->getData(), $level);
			}
			if ($haystack instanceof \UnitEnum) {
				return $this->processEnum($haystack);
			}
			if ($this->convention->isRewriteTooStringMethod() && \method_exists($haystack, '__toString') === true) {
				return (string) $haystack;
			}
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


	/**
	 * @return array{page: int, pageCount: int, itemCount: int, itemsPerPage: int, firstPage: int, lastPage: int, isFirstPage: bool, isLastPage: bool}
	 */
	private function processPaginator(Paginator $haystack): array
	{
		return [
			'page' => $haystack->getPage(),
			'pageCount' => (int) $haystack->getPageCount(),
			'itemCount' => (int) $haystack->getItemCount(),
			'itemsPerPage' => $haystack->getItemsPerPage(),
			'firstPage' => $haystack->getFirstPage(),
			'lastPage' => (int) $haystack->getLastPage(),
			'isFirstPage' => $haystack->isFirst(),
			'isLastPage' => $haystack->isLast(),
		];
	}


	/**
	 * @return array{key: string, label: string, count: int}
	 */
	private function processStatusCount(StatusCount $haystack): array
	{
		return [
			'key' => $haystack->getKey(),
			'label' => $haystack->getLabel(),
			'count' => $haystack->getCount(),
		];
	}


	private function processEnum(\UnitEnum $enum): string|int
	{
		return $enum->value ?? $enum->name;
	}
}
