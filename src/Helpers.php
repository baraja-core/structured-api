<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Http\Request;

final class Helpers
{

	/**
	 * @throws \Error
	 */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * Return current API path by current HTTP URL.
	 * In case of CLI return empty string.
	 *
	 * @param Request $httpRequest
	 * @return string
	 */
	public static function processPath(Request $httpRequest): string
	{
		return trim(str_replace(rtrim($httpRequest->getUrl()->withoutUserInfo()->getBaseUrl(), '/'), '', (string) self::getCurrentUrl()), '/');
	}

	/**
	 * Return current absolute URL.
	 * Return null, if current URL does not exist (for example in CLI mode).
	 *
	 * @return string|null
	 */
	public static function getCurrentUrl(): ?string
	{
		if (!isset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST'])) {
			return null;
		}

		return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
			. '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	/**
	 * @return string|null
	 */
	public static function getBaseUrl(): ?string
	{
		static $return;

		if ($return !== null) {
			return $return;
		}

		if (($currentUrl = self::getCurrentUrl()) !== null) {
			if (preg_match('/^(https?:\/\/.+)\/www\//', $currentUrl, $localUrlParser)) {
				$return = $localUrlParser[0];
			} elseif (preg_match('/^(https?:\/\/[^\/]+)/', $currentUrl, $publicUrlParser)) {
				$return = $publicUrlParser[1];
			}
		}

		if ($return !== null) {
			$return = rtrim($return, '/');
		}

		return $return;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public static function formatApiName(string $name): string
	{
		return (string) preg_replace_callback('/-([a-z])/', function (array $match): string {
			return strtoupper($match[1]);
		}, self::firstUpper($name));
	}

	/**
	 * Converts first character to lower case.
	 *
	 * @param string $s
	 * @return string
	 */
	public static function firstUpper(string $s): string
	{
		return strtoupper($s[0] ?? '') . (function_exists('mb_substr')
				? mb_substr($s, 1, null, 'UTF-8')
				: iconv_substr($s, 1, self::length($s), 'UTF-8')
			);
	}

	/**
	 * Returns number of characters (not bytes) in UTF-8 string.
	 * That is the number of Unicode code points which may differ from the number of graphemes.
	 *
	 * @param string $s
	 * @return int
	 */
	public static function length(string $s): int
	{
		return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_decode($s));
	}

}