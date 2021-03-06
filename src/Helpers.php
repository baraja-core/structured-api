<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Url\Url;
use Nette\Http\Request;
use Nette\Utils\Strings;

final class Helpers
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . self::class . ' is static and cannot be instantiated.');
	}


	/**
	 * Return current API path by current HTTP URL.
	 * In case of CLI return empty string.
	 */
	public static function processPath(Request $httpRequest): string
	{
		return trim(str_replace(rtrim($httpRequest->getUrl()->withoutUserInfo()->getBaseUrl(), '/'), '', Url::get()->getCurrentUrl()), '/');
	}


	public static function formatApiName(string $name): string
	{
		return (string) preg_replace_callback('/-([a-z])/', static fn(array $match): string => strtoupper($match[1]), Strings::firstUpper($name));
	}


	public static function formatToApiName(string $type): string
	{
		return (string) preg_replace_callback('/([A-Z])/', static fn(array $match): string => '-' . strtolower($match[1]), Strings::firstLower($type));
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
			return array_map(static fn(string $role): string => strtolower(trim($role)), explode(',', trim($roleParser[1])));
		}

		return [];
	}
}
