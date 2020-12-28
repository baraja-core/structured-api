<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Middleware;


use Baraja\StructuredApi\Endpoint;
use Baraja\StructuredApi\Response;

interface MatchExtension
{
	/**
	 * @param mixed[] $params
	 */
	public function beforeProcess(Endpoint $endpoint, array $params, string $action, string $method): ?Response;

	/**
	 * @param mixed[] $params
	 */
	public function afterProcess(Endpoint $endpoint, array $params, ?Response $response): ?Response;
}
