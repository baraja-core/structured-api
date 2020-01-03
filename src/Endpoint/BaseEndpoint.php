<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\SmartObject;

abstract class BaseEndpoint
{

	use SmartObject;

	/**
	 * @var callable[]
	 */
	protected $onSaveState = [];

	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * @var bool
	 */
	private $startupCheck = false;

	/**
	 * @param Container $container
	 */
	final public function __construct(Container $container)
	{
		$this->container = $container;

		foreach (InjectExtension::getInjectProperties(\get_class($this)) as $property => $service) {
			$this->{$property} = $container->getByType($service);
		}
	}

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return get_class($this);
	}

	public function startup(): void
	{
		$this->startupCheck = true;
	}

	/**
	 * @param mixed[] $haystack
	 */
	final public function sendJson(array $haystack): void
	{
		throw new ThrowResponse(new JsonResponse($haystack));
	}

	/**
	 * @param string $message
	 * @param int $code
	 */
	final public function sendError(string $message, int $code = 500): void
	{
		$this->sendJson([
			'state' => 'error',
			'message' => $message,
			'code' => $code,
		]);
	}

	/**
	 * @throws RuntimeStructuredApiException
	 */
	final public function startupCheck(): void
	{
		if ($this->startupCheck === false) {
			RuntimeStructuredApiException::startupDoesntCallParent($this);
		}
	}

	final public function saveState(): void
	{
		// TODO: Implement me!
	}

}