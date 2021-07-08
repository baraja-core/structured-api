<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Attributes;

use Attribute;


#[Attribute(Attribute::TARGET_CLASS)]
#[Attribute(Attribute::TARGET_METHOD)]
class Role
{
	/** @var string[] */
	private array $roles;


	/**
	 * @param array<int, string|int>|string $roles
	 */
	public function __construct(array|string $roles)
	{
		$return = [];
		foreach (is_string($roles) ? [$roles] : $roles as $role) {
			$role = trim(strtolower((string) $role), '-');
			if (!preg_match('/^[a-z0-9-]+/', $role)) {
				throw new \InvalidArgumentException('Role "' . $role . '" is not valid.');
			}
			$return[] = $role;
		}
		$this->roles = $return;
	}


	/**
	 * @return string[]
	 */
	public function getRoles(): array
	{
		return $this->roles;
	}
}
