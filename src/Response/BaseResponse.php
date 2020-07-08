<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Entity\ItemsList;
use Baraja\StructuredApi\Entity\StatusCount;
use Nette\Utils\Paginator;
use Tracy\Debugger;
use Tracy\ILogger;

abstract class BaseResponse
{
	/** @var array|string[] */
	public static $keysToHide = ['password', 'passwd', 'pass', 'pwd', 'creditcard', 'credit card', 'cc', 'pin'];

	/** @var string */
	public static $hiddenKeyLabel = '*****';

	/** @var mixed[] */
	protected $haystack;

	/** @var int */
	private $httpCode;


	/**
	 * @param mixed[] $haystack
	 * @param int $httpCode
	 */
	final public function __construct(array $haystack, int $httpCode = 200)
	{
		$this->haystack = $haystack;
		$this->httpCode = $httpCode;
	}


	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return 'text/plain';
	}


	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return '';
	}


	/**
	 * @return mixed[]
	 */
	final public function toArray(): array
	{
		return $this->process($this->haystack);
	}


	/**
	 * @return mixed[]
	 */
	final public function getArray(): array
	{
		return $this->haystack;
	}


	/**
	 * @return int
	 */
	final public function getHttpCode(): int
	{
		return $this->httpCode;
	}


	/**
	 * @param mixed $key
	 * @param mixed $value
	 * @return bool
	 */
	final protected function hideKey($key, $value): bool
	{
		static $hide;

		if ($hide === null) {
			$hide = [];
			foreach (self::$keysToHide as $hideKey) {
				$hide[$hideKey] = true;
			}
		}

		if ($value !== null && isset($hide[$key]) === true) {
			if (preg_match('/^\$2[ayb]\$.{56}$/', (string) $value)) { // Allow BCrypt hash only.
				return false;
			}

			if (\class_exists(Debugger::class) === true) {
				Debugger::log(
					new RuntimeStructuredApiException(
						'Security warning: User password may have been compromised! Key "' . $key . '" given.'
						. "\n" . 'The Baraja API prevented passwords being passed through the API in a readable form.'
					), ILogger::CRITICAL
				);
			}

			return true;
		}

		return false;
	}


	/**
	 * Convert common haystack to json compatible format.
	 *
	 * @param mixed $haystack
	 * @param string[] $trackedInstanceHashes
	 * @return array|string|mixed
	 */
	private function process($haystack, array $trackedInstanceHashes = [])
	{
		if (\is_array($haystack) === true) {
			$return = [];
			foreach ($haystack as $key => $value) {
				if ($value instanceof ItemsList && $key !== 'items') {
					throw new \InvalidArgumentException('Convention error: Item list must be in key "items", but "' . $key . '" given.');
				}
				if ($value instanceof Paginator && $key !== 'paginator') {
					throw new \InvalidArgumentException('Convention error: Paginator must be in key "paginator", but "' . $key . '" given.');
				}

				$return[$key] = $this->hideKey($key, $value) ? self::$hiddenKeyLabel : $this->process($value);
			}

			return $return;
		}

		if (\is_object($haystack) === true) {
			if ($haystack instanceof \DateTimeInterface) {
				return $haystack->format('Y-m-d H:i:s');
			}
			if ($haystack instanceof Paginator) {
				return [
					'page' => $haystack->getPage(),
					'pageCount' => $haystack->getPageCount(),
					'itemCount' => $haystack->getItemCount(),
					'itemsPerPage' => $haystack->getItemsPerPage(),
					'firstPage' => $haystack->getFirstPage(),
					'lastPage' => $haystack->getLastPage(),
					'isFirstPage' => $haystack->isFirst(),
					'isLastPage' => $haystack->isLast(),
				];
			}
			if ($haystack instanceof StatusCount) {
				return [
					'key' => $haystack->getKey(),
					'label' => $haystack->getLabel(),
					'count' => $haystack->getCount(),
				];
			}
			if ($haystack instanceof ItemsList) {
				return $haystack->getData();
			}
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

					if (\is_object($value = $property->getValue($haystack)) === true) {
						if (isset($trackedInstanceHashes[$objectHash = spl_object_hash($value)]) === true) {
							throw new \InvalidArgumentException(
								'Attention: Recursion has been stopped! BaseResponse detected an infinite recursion that was automatically stopped.'
								. "\n\n" . 'To resolve this issue: Never pass entire recursive entities to the API. If you can, pass the processed field without recursion.'
							);
						}
						$trackedInstanceHashes[$objectHash] = true;
					}
					$return[$key] = $this->hideKey($key, $value) ? self::$hiddenKeyLabel : $this->process($value, $trackedInstanceHashes);
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
