<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Tracy\Debugger;

class ApiExtension extends CompilerExtension
{

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$skipError = (bool) ($this->config['skipError'] ?? false);
		$body = '$this->getByType(\'' . ApiManager::class . '\')->run($structuredApi__basePath);';

		$class->getMethod('initialize')->addBody(
			'if (strncmp($structuredApi__basePath = ' . Helpers::class . '::processPath($this->getService(\'http.request\')), \'api/\', 4) === 0) {'
			. "\n\t" . '$this->getByType(' . Application::class . '::class)->onStartup[] = function(' . Application::class . ' $a) use ($structuredApi__basePath) {'
			. "\n\t\t" . ($skipError === true
				? 'try {'
				. "\n\t\t\t" . $body
				. "\n\t\t" . '} catch (' . StructuredApiException::class . ' $e) {'
				. "\n\t\t\t" . (class_exists(Debugger::class) ? Debugger::class . '::log($e);' : '/* log error */')
				. "\n\t\t" . '}'
				: $body
			)
			. "\n\t" . '};'
			. "\n" . '}'
		);
	}

}
