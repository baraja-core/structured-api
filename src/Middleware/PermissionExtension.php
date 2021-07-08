<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Middleware;


use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\Endpoint;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Helpers;
use Baraja\StructuredApi\JsonResponse;
use Baraja\StructuredApi\Response;
use Nette\Security\User;

final class PermissionExtension implements MatchExtension
{
	public function __construct(
		private User $user,
		private Convention $convention
	) {
	}


	/**
	 * @param mixed[] $params
	 */
	public function beforeProcess(Endpoint $endpoint, array $params, string $action, string $method): ?Response
	{
		if ($this->convention->isIgnoreDefaultPermission() === true) {
			return null;
		}
		try {
			if ($this->checkPermission($endpoint, $method, $action) === false) { // Forbidden or permission denied
				return new JsonResponse($this->convention, [
					'state' => 'error',
					'message' => 'You do not have permission to perform this action.',
				], 403);
			}
		} catch (\InvalidArgumentException $e) { // Unauthorized or internal error
			return new JsonResponse($this->convention, [
				'state' => 'error',
				'message' => $e->getMessage(),
			], 401);
		}

		return null;
	}


	/**
	 * @param mixed[] $params
	 */
	public function afterProcess(Endpoint $endpoint, array $params, ?Response $response): ?Response
	{
		return null;
	}


	private function checkPermission(Endpoint $endpoint, string $method, string $action): bool
	{
		try {
			$ref = new \ReflectionClass($endpoint);
			$docComment = trim((string) $ref->getDocComment());
			$public = (PHP_VERSION_ID >= 80000 && $ref->getAttributes(PublicEndpoint::class)) || str_contains($docComment, '@public');
			if ($public === false && $this->user->isLoggedIn() === false) {
				throw new \InvalidArgumentException('This API endpoint is private. You must be logged in to use.');
			}
			foreach (Helpers::parseRolesFromComment($docComment) as $role) {
				if ($this->user->isInRole($role) === true) {
					return true;
				}
			}
		} catch (\ReflectionException $e) {
			throw new \InvalidArgumentException('Endpoint "' . $endpoint::class . '" can not be reflected: ' . $e->getMessage(), $e->getCode(), $e);
		}
		try {
			$methodName = Helpers::resolveMethodName($endpoint, $method, $action);
			if ($methodName === null) {
				throw new \InvalidArgumentException('Method for action "' . $action . '" and HTTP method "' . $method . '" is not implemented.');
			}
			$ref = new \ReflectionMethod($endpoint, $methodName);
		} catch (\ReflectionException $e) {
			throw new \InvalidArgumentException('Method "' . $action . '" can not be reflected: ' . $e->getMessage(), $e->getCode(), $e);
		}
		$roles = Helpers::parseRolesFromComment((string) $ref->getDocComment());
		if ($roles !== []) { // roles as required, user must be logged in
			foreach ($roles as $role) {
				if ($this->user->isInRole($role) === true) {
					return true;
				}
			}

			return false;
		}
		if (
			($public ?? false) === false
			&& $this->user->isLoggedIn() === true
		) { // private endpoint, but user is logged in
			return true;
		}

		return $public ?? false;
	}
}
