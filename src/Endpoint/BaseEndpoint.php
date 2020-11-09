<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Entity\Convention;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\DI\Container;
use Nette\Localization\ITranslator;
use Nette\Security\IAuthorizator;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Nette\SmartObject;
use Nette\Utils\Paginator;

abstract class BaseEndpoint implements Endpoint
{
	use SmartObject;

	/** @var callable[] */
	public array $onSaveState = [];

	protected Container $container;

	protected Convention $convention;

	/** @var mixed[] */
	protected array $data = [];

	/** @var string[][] */
	private array $messages = [];

	private bool $startupCheck = false;


	public function __toString(): string
	{
		return get_class($this);
	}


	public function startup(): void
	{
		if (PHP_SAPI !== 'cli' && class_exists('\Baraja\Localization\Localization') === true) {
			/** @var \Nette\Http\Request $httpRequest */
			$httpRequest = $this->container->getByType(\Nette\Http\Request::class);
			/** @var \Baraja\Localization\Localization $localization */
			$localization = $this->container->getByType(\Baraja\Localization\Localization::class);
			$localization->processHttpRequest($httpRequest);
		}

		$this->startupCheck = true;
	}


	/**
	 * Get current endpoint name.
	 */
	final public function getName(): string
	{
		return preg_replace('/^(?:.*\\\\)?([A-Z0-9][a-z0-9]+)Endpoint$/', '$1', get_class($this));
	}


	/**
	 * Get raw data.
	 * This method obtains an array of all user-passed values.
	 * Individual values may not correspond to the input validation.
	 * This method is suitable for processing large amounts of data that
	 * do not have a predetermined structure that we are able to describe as an object.
	 *
	 * @return mixed[]
	 */
	public function getData(): array
	{
		return $this->data;
	}


	/**
	 * @param mixed[] $data
	 * @internal only for ApiManager.
	 */
	final public function setData(array $data): void
	{
		$this->data = $data;
	}


	final public function setConvention(Convention $convention): void
	{
		$this->convention = $convention;
	}


	/**
	 * Send raw data to output.
	 *
	 * @param mixed[] $haystack
	 */
	final public function sendJson(array $haystack, int $httpCode = 200): void
	{
		if ($this->messages !== []) {
			if (isset($haystack['flashMessages']) === true) {
				throw new \RuntimeException('Flash message was already defined in your data. Did you want to use the flashMessage() function?');
			}
			$haystack = array_merge($haystack, ['flashMessages' => $this->messages]);
			$this->messages = []; // Reset for next response
		}

		throw new ThrowResponse(new JsonResponse($this->convention, $haystack, $httpCode));
	}


	final public function sendError(string $message, ?int $code = null): void
	{
		$this->sendJson([
			'state' => 'error',
			'message' => $message,
			'code' => $code = $code ?? $this->convention->getDefaultErrorCode(),
		], $code);
	}


	/**
	 * @param mixed[] $data
	 */
	final public function sendOk(array $data = [], ?string $message = null, ?int $code = null): void
	{
		$this->sendJson([
			'state' => 'ok',
			'message' => $message,
			'code' => $code = $code ?? $this->convention->getDefaultOkCode(),
			'data' => $data,
		], $code);
	}


	/**
	 * @param mixed[] $items
	 * @param mixed[] $data
	 */
	final public function sendItems(array $items, ?Paginator $paginator = null, array $data = []): void
	{
		$return = ['items' => $items];

		if ($paginator !== null) {
			$return['paginator'] = $paginator;
		}

		$this->sendJson(array_merge($return, $data));
	}


	/**
	 * Add new flash message to internal storage.
	 * All Flash messages will be returned in `flashMessages` key in all responses.
	 * Warning: FlashMessage can change the structure of your response data.
	 *
	 * @param string $type of array [success, info, warning, error]
	 */
	final public function flashMessage(string $message, string $type = 'info'): void
	{
		if (\in_array($type = strtolower($type), $types = ['success', 'info', 'warning', 'error'], true) === false) {
			throw new \RuntimeException('Flash message type "' . $type . '" must be one of "' . implode('", "', $types) . '".');
		}
		$this->messages[] = [
			'message' => $message,
			'type' => $type,
		];
	}


	final public function validateDataKey(string $key, ?string $message = null, ?int $code = null): void
	{
		if (array_key_exists($key, $this->data) === false) {
			$this->sendError($message ?? 'Key "' . $key . '" is required.', $code);
		}
	}


	/**
	 * @param mixed[] $keys
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
	 * @param mixed[] $haystack (key => scalar)
	 * @return mixed[][]
	 */
	final public function formatKeyValueArray(array $haystack, string $key = 'key', string $value = 'value'): array
	{
		$return = [];
		foreach ($haystack as $_key => $_value) {
			if (\is_scalar($_value) === false && $_value !== null) {
				throw new \InvalidArgumentException('Format key value must be scalar, but "' . gettype($_value) . '" given.');
			}

			$return[] = [
				$key => $_key,
				$value => $_value,
			];
		}

		return $return;
	}


	/**
	 * @param mixed[] $haystack (key => scalar)
	 * @return mixed[][]
	 */
	final public function formatBootstrapSelectArray(array $haystack): array
	{
		return $this->formatKeyValueArray($haystack, 'value', 'text');
	}


	final public function startupCheck(): void
	{
		if ($this->startupCheck === false) {
			throw new \LogicException('Method ' . \get_class($this) . '::startup() or its descendant doesn\'t call parent::startup()."');
		}
	}


	final public function saveState(): void
	{
		$this->onSaveState($this);
	}


	final public function getUser(): User
	{
		static $user;

		if ($user === null) {
			$user = $this->container->getByType(User::class);
		}

		return $user;
	}


	final public function isUserLoggedIn(): bool
	{
		try {
			return $this->getUser()->isLoggedIn();
		} catch (\Throwable $e) {
			return false;
		}
	}


	final public function getUserEntity(): ?IIdentity
	{
		return $this->getUser()->getIdentity();
	}


	final public function getAuthorizator(): IAuthorizator
	{
		return $this->getUser()->getAuthorizator();
	}


	/**
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
	 * @return mixed[]
	 */
	final public function getParameters(): array
	{
		return $this->container->getParameters();
	}


	/**
	 * @param mixed|null $defaultValue
	 * @return mixed|null
	 */
	final public function getParameter(string $key, $defaultValue = null)
	{
		return $this->container->getParameters()[$key] ?? $defaultValue;
	}


	final public function injectContainer(Container $container): void
	{
		$this->container = $container;
	}
}
