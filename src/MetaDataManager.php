<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\DI\Container;
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
					throw new \RuntimeException(sprintf(
						'Api Manager: Endpoint "%s" already exist, because this endpoint implements service "%s" and "%s".',
						$name,
						$class,
						$return[$name],
					));
				}
				$return[$name] = $class;
			}
		}

		return $return;
	}


	public static function endpointInjectDependencies(Endpoint $endpoint, Container $container): void
	{
		foreach (InjectExtension::getInjectProperties($endpoint::class) as $property => $service) {
			$endpoint->{$property} = $service === Container::class ? $container : $container->getByType($service);
		}
	}
}
