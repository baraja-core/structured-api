<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Middleware\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\Loaders\RobotLoader;

final class MetaDataManager
{
	/**
	 * @return array<string, class-string> (route-path => type)
	 */
	public static function createEndpointServices(): array
	{
		$rootDir = dirname(__DIR__, 4);
		$robot = new RobotLoader;
		$robot->addDirectory($rootDir);
		$robot->setTempDirectory($rootDir . '/temp/cache/baraja.structuredApi');
		$robot->acceptFiles = ['*Endpoint.php'];
		$robot->reportParseErrors(false);
		$robot->refresh();

		$return = [];
		foreach (array_unique(array_keys($robot->getIndexedClasses())) as $class) {
			if ($class === BaseEndpoint::class) {
				continue;
			}
			if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
				throw new \RuntimeException(
					sprintf('Class "%s" was found, but it cannot be loaded by autoloading.', $class) . "\n"
					. 'More information: https://en.php.brj.cz/autoloading-classes-in-php',
				);
			}
			$rc = new \ReflectionClass($class);
			try {
				if ($rc->isInstantiable() === false) {
					if ($rc->hasMethod('__construct') && !$rc->getMethod('__construct')->isPublic()) {
						throw new \RuntimeException(sprintf('Constructor of endpoint "%s" is not callable.', $class));
					}
					continue;
				}
			} catch (\ReflectionException) {
				continue;
			}
			if ($rc->implementsInterface(Endpoint::class)) {
				$name = Helpers::formatToApiName((string) preg_replace('/^.*?([^\\\\]+)Endpoint$/', '$1', $class));
				if (isset($return[$name]) === true) {
					throw new \RuntimeException(
						sprintf(
							'Api Manager: Endpoint "%s" already exist, because this endpoint implements service "%s" and "%s".',
							$name,
							$class,
							$return[$name],
						),
					);
				}
				$return[$name] = $class;
			}
		}

		return $return;
	}


	public static function endpointInjectDependencies(Endpoint $endpoint, Container $container): void
	{
		if ($endpoint instanceof BaseEndpoint) {
			$endpoint->injectContainer($container);
		}
		$injectProperties = InjectExtension::getInjectProperties($endpoint::class);
		if ($injectProperties === []) {
			return;
		}

		$ref = new \ReflectionClass($endpoint);
		foreach ($injectProperties as $property => $service) {
			if ($service === 'Nette\DI\Container') {
				throw new \LogicException(
					sprintf(
						'%s [property "%s"]: Injecting the entire Container is not an allowed operation. Please use DIC.',
						$endpoint::class,
						$property,
					),
				);
			}
			trigger_error(
				sprintf(
					'%s: Property "%s" with @inject annotation or #[Inject] attribute is deprecated design pattern. '
					. 'Please inject all dependencies to constructor.',
					$endpoint::class,
					$property,
				),
				E_USER_DEPRECATED,
			);
			$p = $ref->getProperty($property);
			$p->setAccessible(true);
			/** @phpstan-ignore-next-line */
			$p->setValue($endpoint, $container->getByType($service));
		}
	}
}
