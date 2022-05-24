<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Entity\ItemsList;
use Baraja\StructuredApi\Entity\StatusCount;
use Nette\Utils\Paginator;
use Tracy\Debugger;
use Tracy\ILogger;

abstract class BaseResponse implements Response
{
	private const HIDDEN_KEY_LABEL = '*****';

	/** @var mixed[] */
	protected array $haystack;

	private int $httpCode;


	/**
	 * @param array<string, mixed> $haystack
	 */
	final public function __construct(
		private Convention $convention,
		array $haystack,
		int|string $httpCode = 200,
	) {
		if (is_string($httpCode) && preg_match('#^[+-]?\d*[.]?\d+$#D', $httpCode) !== 1) {
			$httpCode = 500;
		}
		$this->haystack = $haystack;
		$this->httpCode = (int) $httpCode;
	}


	public function getContentType(): string
	{
		return 'text/plain';
	}


	public function __toString(): string
	{
		return '';
	}


	/**
	 * @return mixed[]
	 */
	final public function toArray(): array
	{
		$return = $this->process($this->haystack);
		if (is_array($return)) {
			return $return;
		}

		throw new \LogicException(sprintf(
			'Response can not be casted to array, because type "%s" given.',
			get_debug_type($return),
		));
	}


	/**
	 * @return mixed[]
	 */
	final public function getArray(): array
	{
		return $this->haystack;
	}


	final public function getHttpCode(): int
	{
		return $this->httpCode;
	}


	final protected function hideKey(mixed $key, mixed $value): bool
	{
		static $hide;

		if ($hide === null) {
			$hide = [];
			foreach ($this->convention->getKeysToHide() as $hideKey) {
				$hide[$hideKey] = true;
			}
		}
		if (isset($hide[$key]) && (is_string($value) || $value instanceof \Stringable)) {
			if (preg_match('/^\$2[ayb]\$.{56}$/', (string) $value) === 1) { // Allow BCrypt hash only.
				return false;
			}
			if (\class_exists(Debugger::class) === true) {
				Debugger::log(
					new \RuntimeException(
						'Security warning: User password may have been compromised! Key "' . $key . '" given.'
						. "\n" . 'The Baraja API prevented passwords being passed through the API in a readable form.',
					),
					ILogger::CRITICAL,
				);
			}

			return true;
		}

		return false;
	}


	/**
	 * Convert common haystack to json compatible format.
	 *
	 * @param array<string, bool> $trackedInstanceHashes (key => true)
	 */
	private function process(mixed $haystack, array $trackedInstanceHashes = []): mixed
	{
		if (\is_array($haystack) === true) {
			return $this->processArray($haystack);
		}
		if (\is_object($haystack) === false) {
			return $haystack;
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
			return $this->process($haystack->getData(), $trackedInstanceHashes);
		}
		if ($haystack instanceof \UnitEnum) {
			return $this->processEnum($haystack);
		}
		if ($this->convention->isRewriteTooStringMethod() && \method_exists($haystack, '__toString') === true) {
			return (string) $haystack;
		}

		return $this->processReflection($haystack, $trackedInstanceHashes);
	}


	/**
	 * @param mixed[] $haystack
	 * @return mixed[]
	 */
	private function processArray(array $haystack): array
	{
		$return = [];
		foreach ($haystack as $key => $value) {
			if ($value instanceof ItemsList && $key !== 'items') {
				throw new \InvalidArgumentException(
					'Convention error: Item list must be in key "items", but "' . $key . '" given.',
				);
			}
			if ($value instanceof Paginator && $key !== 'paginator') {
				throw new \InvalidArgumentException(
					'Convention error: Paginator must be in key "paginator", but "' . $key . '" given.',
				);
			}

			$return[$key] = $this->hideKey($key, $value)
				? self::HIDDEN_KEY_LABEL
				: $this->process($value);
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


	/**
	 * @param array<string, bool> $trackedInstanceHashes (key => true)
	 * @return mixed[]
	 */
	private function processReflection(mixed $haystack, array $trackedInstanceHashes): array
	{
		$return = [];
		if (is_object($haystack)) {
			$ref = new \ReflectionClass($haystack);
			foreach ($ref->getProperties() as $property) {
				$property->setAccessible(true);
				$key = $property->getName();
				if (($key[0] ?? '') === '_') {
					continue;
				}
				$value = $property->getValue($haystack);
				if (\is_object($value) === true) {
					$objectHash = spl_object_hash($value);
					if (isset($trackedInstanceHashes[$objectHash]) === true) {
						throw new \InvalidArgumentException(
							'Attention: Recursion has been stopped! BaseResponse detected an infinite recursion that was automatically stopped.'
							. "\n\n" . 'To resolve this issue: Never pass entire recursive entities to the API. If you can, pass the processed field without recursion.',
						);
					}
					$trackedInstanceHashes[$objectHash] = true;
				}
				$return[$key] = $this->hideKey($key, $value)
					? self::HIDDEN_KEY_LABEL
					: $this->process($value, $trackedInstanceHashes);
			}
		} elseif (is_iterable($haystack)) {
			foreach ($haystack as $key => $value) {
				$return[$key] = $this->hideKey($key, $value)
					? self::HIDDEN_KEY_LABEL
					: $this->process($value);
			}
		} else {
			throw new \RuntimeException(
				sprintf(
					'Can not hydrate input to array, because type "%s" given.',
					get_debug_type($haystack),
				),
				500,
			);
		}

		return $return;
	}


	private function processEnum(\UnitEnum $enum): string|int
	{
		return $enum->value ?? $enum->name;
	}
}
