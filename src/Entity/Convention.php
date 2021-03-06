<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Entity;


/**
 * This entity is global configuration for formatting conventions and other future styling.
 */
final class Convention
{
	private string $dateTimeFormat = 'Y-m-d H:i:s';

	private int $defaultErrorCode = 500;

	private int $defaultOkCode = 200;

	private bool $rewriteTooStringMethod = true;

	/** @var string[] */
	private array $keysToHide = ['password', 'passwd', 'pass', 'pwd', 'creditcard', 'credit card', 'cc', 'pin'];

	private bool $ignoreDefaultPermission = false;


	public function getDateTimeFormat(): string
	{
		return $this->dateTimeFormat;
	}


	public function setDateTimeFormat(string $dateTimeFormat): void
	{
		if ($dateTimeFormat === '') {
			throw new \InvalidArgumentException('DateTime format can not be empty string.');
		}

		$this->dateTimeFormat = $dateTimeFormat;
	}


	public function getDefaultErrorCode(): int
	{
		return $this->defaultErrorCode;
	}


	public function setDefaultErrorCode(int $code): void
	{
		if ($code < 100 || $code > 999) {
			throw new \InvalidArgumentException('Code must be in interval (100; 999), but ' . $code . ' given.');
		}

		$this->defaultErrorCode = $code;
	}


	public function getDefaultOkCode(): int
	{
		return $this->defaultOkCode;
	}


	public function setDefaultOkCode(int $code): void
	{
		if ($code < 100 || $code > 999) {
			throw new \InvalidArgumentException('Code must be in interval (100; 999), but ' . $code . ' given.');
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
	 * @return string[]
	 */
	public function getKeysToHide(): array
	{
		return $this->keysToHide;
	}


	/**
	 * @param string[] $keysToHide
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
}
