<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Application\Application;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;

class ApiExtension extends CompilerExtension
{

	/**
	 * @param ClassType $class
	 */
	public function afterCompile(ClassType $class): void
	{
		$class->getMethod('initialize')->addBody(
			'if (strncmp(' . Helpers::class . '::processPath(), \'api/\', 4) === 0) {'
			. "\n\t" . '$this->getByType(' . Application::class . '::class)->onStartup[] = function(' . Application::class . ' $a) {'
			. "\n\t\t" . '$this->getByType(\'' . ApiManager::class . '\')->run();'
			. "\n\t" . '};'
			. "\n" . '}'
		);
	}

}