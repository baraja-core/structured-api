<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Schema\Elements\Structure;
use Nette\Schema\Elements\Type;
use Nette\Schema\Schema;

class TestSchema
{
	public function actionDefault(): Schema
	{
		return new Structure([
			'name' => (new Type('string'))->nullable()->pattern('\w{3,}'),
			'email' => (new Type('string'))->pattern('^.+@.+$'),
		]);
	}
}
