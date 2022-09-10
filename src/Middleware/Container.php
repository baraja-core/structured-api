<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Middleware;


use Baraja\StructuredApi\Endpoint;
use Baraja\StructuredApi\MetaDataManager;
use Nette\DI\Container as NetteContainer;

final class Container
{
	public function __construct(
		private NetteContainer $container,
	) {
	}


	public function setContainer(NetteContainer $container): void
	{
		$this->container = $container;
	}


	/**
	 * @template T
	 * @param class-string<T> $type
	 * @return ?T
	 */
	public function getByType(string $type): object
	{
		return $this->container->getByType($type);
	}


	/**
	 * @param class-string $className
	 */
	public function getEndpoint(string $className): Endpoint
	{
		$endpoint = $this->getByType($className);
		if (!$endpoint instanceof Endpoint) {
			throw new \LogicException(
				sprintf(
					'Service "%s" must be instance of "%s", but type "%s" given.',
					$className,
					Endpoint::class,
					$endpoint::class,
				),
			);
		}
		MetaDataManager::endpointInjectDependencies($endpoint, $this);

		return $endpoint;
	}


	/**
	 * @return array<string, mixed>
	 */
	public function getParameters(): array
	{
		return $this->container->getParameters();
	}
}
