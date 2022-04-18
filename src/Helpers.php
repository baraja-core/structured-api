<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Utils\Strings;

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
			Strings::firstUpper($name),
		);
	}


	public static function formatToApiName(string $type): string
	{
		return (string) preg_replace_callback(
			'/([A-Z])/',
			static fn(array $match): string => sprintf('-%s', strtolower($match[1])),
			Strings::firstLower($type),
		);
	}


	public static function resolveMethodName(Endpoint $endpoint, string $method, string $action): ?string
	{
		$tryMethods = [];
		$tryMethods[] = ($method === 'GET' ? 'action' : strtolower($method)) . Strings::firstUpper($action);
		if ($method === 'PUT') {
			$tryMethods[] = sprintf('update%s', Strings::firstUpper($action));
		} elseif ($method === 'POST') {
			$tryMethods[] = sprintf('create%s', Strings::firstUpper($action));
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
}
