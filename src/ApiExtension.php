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
		$endpointServices = MetaDataManager::createEndpointServices();
		foreach ($endpointServices as $endpointService) {
			$builder->addDefinition($this->prefix('endpoint') . '.' . str_replace('\\', '.', $endpointService))
				->setFactory($endpointService)
				->addTag('structured-api-endpoint')
				->addSetup('?->injectContainer($this)', ['@self']);
		}

		$builder->addDefinition($this->prefix('convention'))
			->setFactory(Convention::class);

		$builder->addDefinition($this->prefix('permissionExtension'))
			->setFactory(PermissionExtension::class)
			->setAutowired(PermissionExtension::class);

		$builder->addDefinition($this->prefix('apiManager'))
			->setFactory(ApiManager::class)
			->setArgument('endpoints', $endpointServices)
			->addSetup('?->addMatchExtension(?)', ['@self', '@' . PermissionExtension::class]);
	}


	public function afterCompile(ClassType $class): void
	{
		if (PHP_SAPI === 'cli') {
			return;
		}
		$application = $this->getContainerBuilder()->getDefinitionByType(Application::class);
		assert($application instanceof ServiceDefinition);

		$apiManager = $this->getContainerBuilder()->getDefinitionByType(ApiManager::class);
		assert($apiManager instanceof ServiceDefinition);

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
}
