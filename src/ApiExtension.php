<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\Container;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Extensions\InjectExtension;
use Nette\Loaders\RobotLoader;
use Nette\PhpGenerator\ClassType;
use Tracy\Debugger;

final class ApiExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$endpoints = $this->createEndpointServices($builder);

		$builder->addDefinition($this->prefix('apiManager'))
			->setFactory(ApiManager::class)
			->setArgument('endpoints', $endpoints);
	}


	public function afterCompile(ClassType $class): void
	{
		/** @var ServiceDefinition $application */
		$application = $this->getContainerBuilder()->getDefinitionByType(Application::class);

		/** @var ServiceDefinition $apiManager */
		$apiManager = $this->getContainerBuilder()->getDefinitionByType(ApiManager::class);

		$skipError = (bool) ($this->config['skipError'] ?? false);
		$body = '$this->getService(\'' . $apiManager->getName() . '\')->run($basePath);';

		$class->getMethod('initialize')->addBody(
			'// Structured API.' . "\n"
			. '(function () {' . "\n"
			. "\t" . 'if (strncmp($basePath = ' . Helpers::class . '::processPath($this->getService(\'http.request\')), \'api/\', 4) === 0) {' . "\n"
			. "\t\t" . '$this->getService(?)->onStartup[] = function(' . Application::class . ' $a) use ($basePath): void {' . "\n"
			. "\t\t\t" . ($skipError === true
				? "\t" . 'try {' . "\n"
				. "\t\t\t" . $body . "\n"
				. "\t\t\t" . '} catch (' . StructuredApiException::class . ' $e) {' . "\n"
				. "\t\t\t\t" . (class_exists(Debugger::class) ? Debugger::class . '::log($e);' : '/* log error */') . "\n"
				. "\t\t\t" . '}' . "\n"
				: $body . "\n"
			)
			. "\t\t" . '};' . "\n"
			. "\t" . '}' . "\n"
			. '})();' . "\n",
			[$application->getName()]
		);
	}


	/**
	 * @return string[] (name => type)
	 */
	private function createEndpointServices(ContainerBuilder $builder): array
	{
		$robot = new RobotLoader;
		$robot->addDirectory($rootDir = dirname(__DIR__, 4));
		$robot->setTempDirectory($rootDir . '/temp/cache/baraja.structuredApi');
		$robot->acceptFiles = ['*Endpoint.php'];
		$robot->reportParseErrors(false);
		$robot->refresh();

		$return = [];
		foreach (array_unique(array_keys($robot->getIndexedClasses())) as $class) {
			if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
				throw new \RuntimeException('Class "' . $class . '" was found, but it cannot be loaded by autoloading.' . "\n" . 'More information: https://php.baraja.cz/autoloading-trid');
			}
			try {
				$rc = new \ReflectionClass($class);
			} catch (\ReflectionException $e) {
				throw new \RuntimeException('Service "' . $class . '" is broken: ' . $e->getMessage(), $e->getCode(), $e);
			}
			if ($rc->isInstantiable() && $rc->implementsInterface(Endpoint::class)) {
				$endpoint = $builder->addDefinition($this->prefix('endpoint') . '.' . str_replace('\\', '.', $class))
					->setFactory($class)
					->addTag('structured-api-endpoint')
					->addSetup('?->injectContainer($this)', ['@self']);

				foreach (InjectExtension::getInjectProperties($class) as $property => $service) {
					if ($service === Container::class) {
						$endpoint->addSetup('?->? = $this', ['@self', $property]);
					} else {
						$endpoint->addSetup('?->? = $this->getByType(?)', ['@self', $property, $service]);
					}
				}
				if (isset($return[$name = Helpers::formatToApiName((string) preg_replace('/^.*?([^\\\\]+)Endpoint$/', '$1', $class))]) === true) {
					throw new \RuntimeException(
						'Api Manager: Endpoint "' . $name . '" already exist, '
						. 'because this endpoint implements service "' . $class . '" and "' . $return[$name] . '".'
					);
				}
				$return[$name] = $class;
			}
		}

		return $return;
	}
}
