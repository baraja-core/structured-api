<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Http\Request;
use Nette\Utils\Strings;

final class Helpers
{

	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * Return current API path by current HTTP URL.
	 * In case of CLI return empty string.
	 */
	public static function processPath(Request $httpRequest): string
	{
		return trim(str_replace(rtrim($httpRequest->getUrl()->withoutUserInfo()->getBaseUrl(), '/'), '', (string) self::getCurrentUrl()), '/');
	}


	/**
	 * Return current absolute URL.
	 * Return null, if current URL does not exist (for example in CLI mode).
	 */
	public static function getCurrentUrl(): ?string
	{
		if (!isset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST'])) {
			return null;
		}

		return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
			. '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}


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


	public static function formatApiName(string $name): string
	{
		return (string) preg_replace_callback('/-([a-z])/', function (array $match): string {
			return strtoupper($match[1]);
		}, Strings::firstUpper($name));
	}


	public static function formatToApiName(string $type): string
	{
		return (string) preg_replace_callback('/([A-Z])/', function (array $match): string {
			return '-' . strtolower($match[1]);
		}, Strings::firstLower($type));
	}


	public static function resolveMethodName(Endpoint $endpoint, string $method, string $action): ?string
	{
		$tryMethods = [];
		$tryMethods[] = ($method === 'GET' ? 'action' : strtolower($method)) . Strings::firstUpper($action);
		if ($method === 'PUT') {
			$tryMethods[] = 'update' . Strings::firstUpper($action);
		} elseif ($method === 'POST') {
			$tryMethods[] = 'create' . Strings::firstUpper($action);
		}

		$methodName = null;
		foreach ($tryMethods as $tryMethod) {
			if (\method_exists($endpoint, $tryMethod) === true) {
				return $tryMethod;
			}
		}

		return null;
	}


	public static function httpMethod(): string
	{
		if (($method = $_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
			&& preg_match('#^[A-Z]+$#D', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '')
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		return $method ?: 'GET';
	}


	/**
	 * @return string[]
	 */
	public static function parseRolesFromComment(string $comment): array
	{
		if (preg_match('/@role\s+([^\n]+)/', $comment, $roleParser)) {
			return array_map(static function (string $role): string {
				return strtolower(trim($role));
			}, explode(',', trim($roleParser[1])));
		}

		return [];
	}
}
