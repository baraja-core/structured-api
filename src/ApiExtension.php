<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Middleware\PermissionExtension;
use Baraja\Url\Url;
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

		$builder->addDefinition($this->prefix('convention'))
			->setFactory(Convention::class);

		$builder->addDefinition($this->prefix('permissionExtension'))
			->setFactory(PermissionExtension::class)
			->setAutowired(PermissionExtension::class);

		$builder->addDefinition($this->prefix('apiManager'))
			->setFactory(ApiManager::class)
			->setArgument('endpoints', $endpoints)
			->addSetup('?->addMatchExtension(?)', ['@self', '@' . PermissionExtension::class]);
	}


	public function afterCompile(ClassType $class): void
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		/** @var ServiceDefinition $application */
		$application = $this->getContainerBuilder()->getDefinitionByType(Application::class);

		/** @var ServiceDefinition $apiManager */
		$apiManager = $this->getContainerBuilder()->getDefinitionByType(ApiManager::class);

		/** @var array<string, mixed> $config */
		$config = $this->getConfig();

		$skipError = (bool) ($config['skipError'] ?? false);
		$body = '$this->getService(\'' . $apiManager->getName() . '\')->run();';

		$class->getMethod('initialize')->addBody(
			'// Structured API.' . "\n"
			. '(function (): void {' . "\n"
			. "\t" . 'if (str_starts_with(' . Url::class . '::get()->getRelativeUrl(), \'api/\')) {' . "\n"
			. "\t\t" . '$this->getService(?)->onStartup[] = function(' . Application::class . ' $a): void {' . "\n"
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
			[$application->getName()],
		);
	}


	/**
	 * @return array<string, class-string> (name => type)
	 */
	private function createEndpointServices(ContainerBuilder $builder): array
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
					. 'More information: https://php.baraja.cz/autoloading-trid',
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
}
