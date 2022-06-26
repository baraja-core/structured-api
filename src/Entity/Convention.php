<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


/**
 * This entity is global configuration for formatting conventions and other future styling.
 */
final class Convention
{
	private string $dateTimeFormat = 'Y-m-d H:i:s';

	/** @var positive-int */
	private int $defaultErrorCode = 500;

	/** @var positive-int */
	private int $defaultOkCode = 200;

	private bool $rewriteTooStringMethod = true;

	/** @var array<int, string> */
	private array $keysToHide = ['password', 'passwd', 'pass', 'pwd', 'creditcard', 'credit card', 'cc', 'pin'];

	private bool $ignoreDefaultPermission = false;

	/**
	 * If the property value is "null", it is automatically removed.
	 * This option optimizes the size of the transferred data.
	 */
	private bool $rewriteNullToUndefined = false;


	public function getDateTimeFormat(): string
	{
		return $this->dateTimeFormat;
	}


	public function setDateTimeFormat(string $dateTimeFormat): void
	{
		assert($dateTimeFormat === '', 'DateTime format can not be empty string.');
		$this->dateTimeFormat = $dateTimeFormat;
	}


	/**
	 * @phpstan-return positive-int
	 */
	public function getDefaultErrorCode(): int
	{
		return $this->defaultErrorCode;
	}


	/**
	 * @param positive-int $code
	 */
	public function setDefaultErrorCode(int $code): void
	{
		if ($code < 100 || $code > 999) {
			throw new \InvalidArgumentException(sprintf('Code must be in interval (100; 999), but %d given.', $code));
		}

		$this->defaultErrorCode = $code;
	}


	/**
	 * @phpstan-return positive-int
	 */
	public function getDefaultOkCode(): int
	{
		return $this->defaultOkCode;
	}


	/**
	 * @param positive-int $code
	 */
	public function setDefaultOkCode(int $code): void
	{
		if ($code < 100 || $code > 999) {
			throw new \InvalidArgumentException(sprintf('Code must be in interval (100; 999), but %d given.', $code));
		}

		$this->defaultOkCode = $code;
	}


	public function isRewriteTooStringMethod(): bool
	{
		return $this->rewriteTooStringMethod;
	}


	public function setRewriteTooStringMethod(bool $rewriteTooStringMethod): void
	{
		$this->rewriteTooStringMethod = $rewriteTooStringMethod;
	}


	/**
	 * @return array<int, string>
	 */
	public function getKeysToHide(): array
	{
		return $this->keysToHide;
	}


	/**
	 * @param array<int, string> $keysToHide
	 */
	public function setKeysToHide(array $keysToHide): void
	{
		$this->keysToHide = $keysToHide;
	}


	public function isIgnoreDefaultPermission(): bool
	{
		return $this->ignoreDefaultPermission;
	}


	public function setIgnoreDefaultPermission(bool $ignoreDefaultPermission): void
	{
		$this->ignoreDefaultPermission = $ignoreDefaultPermission;
	}


	public function isRewriteNullToUndefined(): bool
	{
		return $this->rewriteNullToUndefined;
	}


	public function setRewriteNullToUndefined(bool $rewriteNullToUndefined): void
	{
		$this->rewriteNullToUndefined = $rewriteNullToUndefined;
	}
}
