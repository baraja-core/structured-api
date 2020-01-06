<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Tracy\Debugger;
use Tracy\ILogger;

abstract class BaseResponse
{

	public static $keysToHide = ['password', 'passwd', 'pass', 'pwd', 'creditcard', 'credit card', 'cc', 'pin'];

	public static $hiddenKeyLabel = '*****';

	/**
	 * @var mixed[]
	 */
	protected $haystack;

	/**
	 * @param mixed[] $haystack
	 */
	public function __construct(array $haystack)
	{
		$this->haystack = $haystack;
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
	 * @param mixed $key
	 * @param mixed $value
	 * @return bool
	 */
	protected function hideKey($key, $value): bool
	{
		static $hide;

		if ($hide === null) {
			$hide = [];
			foreach (self::$keysToHide as $hideKey) {
				$hide[$hideKey] = true;
			}
		}

		if (isset($hide[$key]) === true) {
			if (preg_match('/^\$2[ayb]\$.{56}$/', $value)) { // Allow BCrypt hash only.
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

}