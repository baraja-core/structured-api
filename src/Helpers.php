<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error(sprintf('Class "%s" is static and cannot be instantiated.', self::class));
	}


	public static function formatApiName(string $name): string
	{
		return (string) preg_replace_callback(
			'/-([a-z])/',
			static fn(array $match): string => strtoupper($match[1]),
			self::firstUpper($name),
		);
	}


	public static function formatToApiName(string $type): string
	{
		return (string) preg_replace_callback(
			'/([A-Z])/',
			static fn(array $match): string => sprintf('-%s', strtolower($match[1])),
			self::firstLower($type),
		);
	}


	public static function resolveMethodName(Endpoint $endpoint, string $method, string $action): ?string
	{
		$tryMethods = [];
		$tryMethods[] = ($method === 'GET' ? 'action' : strtolower($method)) . self::firstUpper($action);
		if ($method === 'PUT') {
			$tryMethods[] = sprintf('update%s', self::firstUpper($action));
		} elseif ($method === 'POST') {
			$tryMethods[] = sprintf('create%s', self::firstUpper($action));
		}

		foreach ($tryMethods as $tryMethod) {
			if (\method_exists($endpoint, $tryMethod) === true) {
				return $tryMethod;
			}
		}

		return null;
	}


	public static function httpMethod(): string
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$methodOverride = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
		if ($method === 'POST' && $methodOverride !== '' && preg_match('#^[A-Z]+$#D', $methodOverride) === 1) {
			$method = $methodOverride;
		}

		return $method;
	}


	/**
	 * Checks if the value is a valid URL address.
	 * Moved from nette/utils.
	 */
	public static function isUrl(string $value): bool
	{
		$alpha = "a-z\x80-\xFF";
		return (bool) preg_match(<<<XX
		(^
			https?://(
				(([-_0-9$alpha]+\\.)*                       # subdomain
					[0-9$alpha]([-0-9$alpha]{0,61}[0-9$alpha])?\\.)?  # domain
					[$alpha]([-0-9$alpha]{0,17}[$alpha])?   # top domain
				|\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}  # IPv4
				|\\[[0-9a-f:]{3,39}\\]                      # IPv6
			)(:\\d{1,5})?                                   # port
			(/\\S*)?                                        # path
			(\\?\\S*)?                                      # query
			(\\#\\S*)?                                      # fragment
		$)Dix
XX
			, $value);
	}


	public static function firstUpper(string $s): string
	{
		return mb_strtoupper(self::substring($s, 0, 1), 'UTF-8') . self::substring($s, 1);
	}


	public static function firstLower(string $s): string
	{
		return mb_strtolower(self::substring($s, 0, 1), 'UTF-8') . self::substring($s, 1);
	}


	/**
	 * Returns a part of UTF-8 string specified by starting position and length. If start is negative,
	 * the returned string will start at the start'th character from the end of string.
	 */
	public static function substring(string $s, int $start, ?int $length = null): string
	{
		if (function_exists('mb_substr') === false) {
			throw new \RuntimeException('PHP extension "mb_substr" is mandatory.');
		}

		return mb_substr($s, $start, $length, 'UTF-8');
	}


	/**
	 * @deprecated since 2021-07-08, use #[Role] attribute instead.
	 * @return string[]
	 */
	public static function parseRolesFromComment(string $comment): array
	{
		if (preg_match('/@role\s+([^\n]+)/', $comment, $roleParser) === 1) {
			trigger_error(
				'Doc annotation @role is deprecated since 2021-07-08, use PHP 8.0 #[Role] attribute instead.'
				. "\n" . sprintf('Comment: %s', $roleParser[0]),
				E_USER_DEPRECATED,
			);

			return array_map(static fn(string $role): string => strtolower(trim($role)), explode(',', trim($roleParser[1])));
		}

		return [];
	}


	/**
	 * Is it an AJAX request?
	 */
	public static function isAjax(): bool
	{
		return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
			|| isset($_SERVER['HTTP_X_TRACY_AJAX']);
	}
}
