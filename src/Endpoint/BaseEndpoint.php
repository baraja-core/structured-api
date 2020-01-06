<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Doctrine\EntityManager;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\Security\IIdentity;
use Nette\Security\User;
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
	 * Get current endpoint name.
	 *
	 * @return string
	 */
	final public function getName(): string
	{
		return preg_replace('/^(?:.*\\\\)?([A-Z0-9][a-z0-9]+)Endpoint$/', '$1', get_class($this));
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
	 * @param mixed[] $data
	 * @param string|null $message
	 * @param int $code
	 */
	final public function sendOk(array $data = [], ?string $message = null, int $code = 200): void
	{
		$this->sendJson([
			'state' => 'ok',
			'message' => $message,
			'code' => $code,
			'data' => $data,
		]);
	}

	/**
	 * @param mixed[] $haystack
	 * @return mixed[][]
	 */
	final public function formatKeyValueArray(array $haystack): array
	{
		$return = [];

		foreach ($haystack as $key => $value) {
			$return[] = [
				'key' => $key,
				'value' => $value,
			];
		}

		return $return;
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

	/**
	 * @return EntityManager
	 */
	final public function em(): EntityManager
	{
		static $em;

		if ($em === null) {
			$em = $this->container->getByType(EntityManager::class);
		}

		return $em;
	}

	/**
	 * @return User
	 */
	final public function getUser(): User
	{
		static $user;

		if ($user === null) {
			$user = $this->container->getByType(User::class);
		}

		return $user;
	}

	/**
	 * @return bool
	 */
	final public function isUserLoggedIn(): bool
	{
		try {
			return $this->getUser()->isLoggedIn();
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * @return IIdentity|null
	 */
	final public function getUserEntity(): ?IIdentity
	{
		return $this->getUser()->getIdentity();
	}

	/**
	 * @param string $dest
	 * @param mixed[] $params
	 * @return string
	 * @throws InvalidLinkException
	 */
	final public function link(string $dest, array $params = []): string
	{
		static $linkGenerator;

		if ($linkGenerator === null) {
			$linkGenerator = $this->container->getByType(LinkGenerator::class);
		}

		return $linkGenerator->link(trim($dest, ':'), $params);
	}

	/**
	 * @return Cache
	 */
	final public function getCache(?string $namespace = null): Cache
	{
		static $storage;
		static $cache = [];
		$name = 'api---' . strtolower($namespace ?? $this->getName());

		if ($storage === null) {
			$storage = $this->container->getByType(IStorage::class);
		}

		if (isset($cache[$name]) === false) {
			$cache[$name] = new Cache($storage, $name);
		}

		return $cache[$name];
	}

	/**
	 * @return mixed
	 */
	final public function getParameters(): array
	{
		return $this->container->getParameters();
	}

	/**
	 * @param string $key
	 * @param mixed|null $defaultValue
	 * @return mixed|null
	 */
	final public function getParameter(string $key, $defaultValue = null)
	{
		return $this->container->getParameters()[$key] ?? $defaultValue;
	}

}