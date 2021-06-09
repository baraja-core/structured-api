<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Attributes;

use Attribute;


#[Attribute(Attribute::TARGET_PROPERTY)]
class Inject extends \Nette\DI\Attributes\Inject
{
}
