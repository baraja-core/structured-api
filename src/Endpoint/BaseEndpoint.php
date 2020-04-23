<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\Localization\ITranslator;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Nette\SmartObject;

abstract class BaseEndpoint implements Endpoint
{
	use SmartObject;

	/**  @var callable[] */
	public $onSaveState = [];

	/** @var Container */
	protected $container;

	/**  @var mixed[] */
	protected $data = [];

	/** @var bool */
	private $startupCheck = false;


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
	 * @return mixed[]
	 */
	public function getData(): array
	{
		return $this->data;
	}


	/**
	 * @internal only for ApiManager.
	 * @param mixed[] $data
	 */
	final public function setData(array $data): void
	{
		$this->data = $data;
	}


	/**
	 * This method inject container for common use
	 * and automatically find all comment dependencies in public properties (witch @inject annotation)
	 * like Nette framework in the Presenter class.
	 *
	 * All dependencies will be injected automatically.
	 *
	 * @internal only for ApiManager.
	 * @param Container $container
	 */
	final public function injectContainer(Container $container): void
	{
		$this->container = $container;

		foreach (InjectExtension::getInjectProperties(\get_class($this)) as $property => $service) {
			$this->{$property} = $container->getByType($service);
		}
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
	 * @param int|null $code
	 */
	final public function sendError(string $message, ?int $code = null): void
	{
		$this->sendJson([
			'state' => 'error',
			'message' => $message,
			'code' => $code ?? 500,
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
	 * @param string $key
	 * @param string|null $message
	 * @param int|null $code
	 */
	final public function validateDataKey(string $key, ?string $message = null, ?int $code = null): void
	{
		if (array_key_exists($key, $this->data) === false) {
			$this->sendError($message ?? 'Key "' . $key . '" is required.', $code);
		}
	}


	/**
	 * @param mixed[] $keys
	 * @param string|null $message
	 * @param int|null $code
	 */
	final public function validateDataKeys(array $keys, ?string $message = null, ?int $code = null): void
	{
		$invalidKeys = [];

		foreach ($keys as $key) {
			if (array_key_exists($key, $this->data) === false) {
				$invalidKeys[] = $key;
			}
		}

		if ($invalidKeys !== []) {
			$this->sendError(
				$message ?? (\count($invalidKeys) === 1
					? 'Key "' . $invalidKeys[0] . '" is required.'
					: 'Keys "' . implode('", "', $invalidKeys) . '" are required.'),
				$code
			);
		}
	}


	/**
	 * @param mixed[] $haystack
	 * @param string $key
	 * @param string $value
	 * @return mixed[][]
	 */
	final public function formatKeyValueArray(array $haystack, string $key = 'key', $value = 'value'): array
	{
		$return = [];

		foreach ($haystack as $_key => $_value) {
			$return[] = [
				$key => $_key,
				$value => $_value,
			];
		}

		return $return;
	}


	/**
	 * @param mixed[] $haystack
	 * @return mixed[][]
	 */
	final public function formatBootstrapSelectArray(array $haystack): array
	{
		return $this->formatKeyValueArray($haystack, 'value', 'text');
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
		$this->onSaveState($this);
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
			/** @var LinkGenerator $linkGenerator */
			$linkGenerator = $this->container->getByType(LinkGenerator::class);
		}

		return $linkGenerator->link(trim($dest, ':'), $params);
	}


	/**
	 * Generate link. If link does not exist return null.
	 *
	 * @param string $dest
	 * @param mixed[] $params
	 * @return string|null
	 */
	final public function linkSafe(string $dest, array $params = []): ?string
	{
		try {
			return $this->link($dest, $params);
		} catch (InvalidLinkException $e) {
			return null;
		}
	}


	/**
	 * @param string|null $namespace
	 * @return Cache
	 */
	final public function getCache(?string $namespace = null): Cache
	{
		static $storage;
		static $cache = [];
		$name = 'api---' . strtolower($namespace ?? $this->getName());

		if ($storage === null) {
			/** @var IStorage $storage */
			$storage = $this->container->getByType(IStorage::class);
		}

		if (isset($cache[$name]) === false) {
			$cache[$name] = new Cache($storage, $name);
		}

		return $cache[$name];
	}


	/**
	 * @return ITranslator
	 */
	final public function getTranslator(): ITranslator
	{
		static $translator;

		if ($translator === null) {
			/** @var ITranslator $translator */
			$translator = $this->container->getByType(ITranslator::class);
		}

		return $translator;
	}


	/**
	 * @param string|mixed $message
	 * @param mixed[]|mixed ...$parameters
	 * @return string
	 */
	final public function translate($message, ...$parameters): string
	{
		return $this->getTranslator()->translate($message, $parameters);
	}


	/**
	 * @return array<mixed>
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
