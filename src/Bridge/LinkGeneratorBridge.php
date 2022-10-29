<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Bridge;


use Nette\Application\LinkGenerator;

final class LinkGeneratorBridge
{
	public function __construct(
		private ?LinkGenerator $linkGenerator = null,
	) {
	}


	/**
	 * Generates URL to presenter.
	 *
	 * @param string $dest in format "[[[module:]presenter:]action] [#fragment]"
	 * @param array<string, mixed> $params
	 * @throws \InvalidArgumentException
	 */
	public function link(string $dest, array $params = []): string
	{
		if ($this->linkGenerator === null) {
			throw new \RuntimeException('Service LinkGenerator is not available. Did you install nette/application?');
		}

		try {
			return $this->linkGenerator->link(ltrim($dest, ':'), $params);
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException($e->getMessage(), $e->getCode());
		}
	}
}
