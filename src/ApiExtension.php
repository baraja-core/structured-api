<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Tracy\Debugger;

final class ApiExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		/** @var ServiceDefinition $apiManager */
		$apiManager = $this->getContainerBuilder()->getDefinitionByType(ApiManager::class);
		$apiManager->addSetup('?->setEndpoints(array_keys($this->findByTag(?)))', ['@self', 'structured-api-endpoint']);
	}


	/**
	 * @param ClassType $class
	 */
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
}
