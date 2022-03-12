<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Attributes;

use Attribute;


#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Role
{
	/** @var array<int, string> */
	private array $roles;


	/**
	 * @param array<int, string|int>|string $roles
	 */
	public function __construct(array|string $roles)
	{
		$return = [];
		foreach (is_string($roles) ? [$roles] : $roles as $role) {
			$role = strtolower(trim((string) $role, '-'));
			if (preg_match('/^[a-z0-9-]+/', $role) !== 1) {
				throw new \InvalidArgumentException(sprintf('Role "%s" is not valid.', $role));
			}
			$return[] = $role;
		}
		$this->roles = $return;
	}


	/**
	 * @return array<int, string>
	 */
	public function getRoles(): array
	{
		return $this->roles;
	}
}
