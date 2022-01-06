<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Localization\Localization;
use Baraja\StructuredApi\Entity\Convention;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Localization\Translator;
use Nette\Security\Authorizator;
use Nette\Security\IIdentity;
use Nette\Security\User;
use Nette\SmartObject;
use Nette\Utils\Paginator;
use Nette\Utils\Validators;

abstract class BaseEndpoint implements Endpoint
{
	use SmartObject;

	public const
		FLASH_MESSAGE_SUCCESS = 'success',
		FLASH_MESSAGE_INFO = 'info',
		FLASH_MESSAGE_WARNING = 'warning',
		FLASH_MESSAGE_ERROR = 'error';

	public const FLASH_MESSAGE_TYPES = [
		self::FLASH_MESSAGE_SUCCESS,
		self::FLASH_MESSAGE_INFO,
		self::FLASH_MESSAGE_WARNING,
		self::FLASH_MESSAGE_ERROR,
	];

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
		return static::class;
	}


	public function startup(): void
	{
		if (PHP_SAPI !== 'cli' && class_exists('\Baraja\Localization\Localization') === true) {
			/** @var \Nette\Http\Request $httpRequest */
			$httpRequest = $this->container->getByType(Request::class);
			/** @var \Baraja\Localization\Localization $localization */
			$localization = $this->container->getByType(Localization::class);
			$localization->processHttpRequest($httpRequest);
		}

		$this->startupCheck = true;
	}


	/**
	 * Get current endpoint name.
	 */
	final public function getName(): string
	{
		return (string) preg_replace('/^(?:.*\\\\)?([A-Z0-9][a-z0-9]+)Endpoint$/', '$1', static::class);
	}


	/**
	 * Get raw data.
	 * This method obtains an array of all user-passed values.
	 * Individual values may not correspond to the input validation.
	 * This method is suitable for processing large amounts of data that
	 * do not have a predetermined structure that we are able to describe as an object.
	 *
	 * @return array<int|string, mixed>
	 */
	public function getData(): array
	{
		return $this->data;
	}


	final public function setConvention(Convention $convention): void
	{
		$this->convention = $convention;
	}


	/**
	 * This method returns an array of data exactly as you pass it and converts it to a valid json.
	 *
	 * Note: The formatting and type of data is purely managed by the user.
	 * If you want to send status data, it is recommended to use the sendOk() and sendError() methods.
	 * This method should be used for sending data in a user-defined structure only.
	 *
	 * @param array<string, mixed> $haystack
	 * @param positive-int $httpCode
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendJson(array $haystack, int $httpCode = 200): void
	{
		if ($this->messages !== []) {
			if (isset($haystack['flashMessages']) === true) {
				throw new \RuntimeException('Flash message was already defined in your data. Did you want to use the flashMessage() function?');
			}
			$haystack += ['flashMessages' => $this->messages];
			$this->messages = []; // Reset for next response
		}

		throw new ThrowResponse(new JsonResponse($this->convention, $haystack, $httpCode));
	}


	/**
	 * @param positive-int|null $code
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendError(string $message, ?int $code = null, ?string $hint = null): void
	{
		$code ??= $this->convention->getDefaultOkCode();
		$this->sendJson([
			'state' => 'error',
			'message' => $message,
			'code' => $code,
			'hint' => $hint,
		], $code);
	}


	/**
	 * @param array<string, mixed> $data
	 * @param positive-int|null $code
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendOk(array $data = [], ?string $message = null, ?int $code = null): void
	{
		$code ??= $this->convention->getDefaultOkCode();
		$this->sendJson([
			'state' => 'ok',
			'message' => $message,
			'code' => $code,
			'data' => $data,
		], $code);
	}


	/**
	 * @param array<string, mixed> $data
	 * @param positive-int|null $code
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendSuccess(array $data = [], ?string $message = null, ?int $code = null): void
	{
		$code ??= $this->convention->getDefaultOkCode();
		$this->sendJson([
			'state' => 'success',
			'message' => $message,
			'code' => $code,
			'data' => $data,
		], $code);
	}


	/**
	 * @param array<int, mixed> $items
	 * @param array<string, mixed> $data
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendItems(array $items, ?Paginator $paginator = null, array $data = []): void
	{
		$return = ['items' => $items];
		if ($paginator !== null) {
			$return += ['paginator' => $paginator];
		}

		$this->sendJson(array_merge($return, $data));
	}


	/**
	 * Add new flash message to internal storage.
	 * All Flash messages will be returned in `flashMessages` key in all responses.
	 * Warning: FlashMessage can change the structure of your response data.
	 *
	 * @param string $type one of FLASH_MESSAGE_* constant
	 */
	final public function flashMessage(string $message, string $type = self::FLASH_MESSAGE_INFO): void
	{
		$type = strtolower($type);
		if (\in_array($type, self::FLASH_MESSAGE_TYPES, true) === false) {
			throw new \LogicException(
				'Flash message type "' . $type . '" '
				. 'must be one of "' . implode('", "', self::FLASH_MESSAGE_TYPES) . '". '
				. 'Did you use FLASH_MESSAGE_* constant?',
			);
		}
		$this->messages[] = [
			'message' => $message,
			'type' => $type,
		];
	}


	/**
	 * @deprecated since 2021-05-19 use method arguments instead.
	 * @param positive-int|null $code
	 */
	final public function validateDataKey(string $key, ?string $message = null, ?int $code = null): void
	{
		trigger_error(
			__METHOD__ . ': This method is deprecated, use method arguments instead.',
			E_USER_DEPRECATED,
		);
		if (array_key_exists($key, $this->data) === false) {
			$this->sendError($message ?? 'Key "' . $key . '" is required.', $code);
		}
	}


	/**
	 * @deprecated since 2021-05-19 use method arguments instead.
	 * @param positive-int|null $code
	 * @param array<int|string, string> $keys
	 */
	final public function validateDataKeys(array $keys, ?string $message = null, ?int $code = null): void
	{
		trigger_error(
			__METHOD__ . ': This method is deprecated, use method arguments instead.',
			E_USER_DEPRECATED,
		);
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
				$code,
			);
		}
	}


	/**
	 * @param array<int|string, int|string|null> $haystack (key => scalar)
	 * @return array<int, array<string, string|int|null>>
	 */
	final public function formatKeyValueArray(array $haystack, string $key = 'key', string $value = 'value'): array
	{
		$return = [];
		foreach ($haystack as $dataKey => $dataValue) {
			/** @phpstan-ignore-next-line */
			if (\is_scalar($dataValue) === false && $dataValue !== null) {
				throw new \InvalidArgumentException(
					'Format key value must be scalar, but "' . get_debug_type($dataValue) . '" given.',
				);
			}

			$return[] = [
				$key => $dataKey === '' ? null : $dataKey,
				$value => $dataValue,
			];
		}

		return $return;
	}


	/**
	 * @param array<int|string, int|string|null> $haystack (key => scalar)
	 * @return array<int, array{value: int|string, text: string}>
	 */
	final public function formatBootstrapSelectArray(array $haystack): array
	{
		/** @phpstan-ignore-next-line */
		return $this->formatKeyValueArray($haystack, 'value', 'text');
	}


	/**
	 * @param array<int|string, int|string|null> $haystack (key => scalar)
	 * @return array<int, array{value: int, text: string}>
	 */
	final public function formatBootstrapSelectList(array $haystack): array
	{
		/** @phpstan-ignore-next-line */
		return $this->formatKeyValueArray($haystack, 'value', 'text');
	}


	final public function startupCheck(): void
	{
		if ($this->startupCheck === false) {
			throw new \LogicException('Method ' . static::class . '::startup() or its descendant doesn\'t call parent::startup()."');
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
		} catch (\Throwable) {
			return false;
		}
	}


	final public function getUserEntity(): ?IIdentity
	{
		return $this->getUser()->getIdentity();
	}


	final public function getAuthorizator(): Authorizator
	{
		$authorizator = $this->getUser()->getAuthorizatorIfExists();
		if ($authorizator === null) {
			throw new \RuntimeException('Authorizator has not been set.');
		}

		return $authorizator;
	}


	/**
	 * @param array<string, mixed> $params
	 * @throws InvalidLinkException
	 */
	final public function link(string $dest, array $params = []): string
	{
		static $linkGenerator;

		if ($linkGenerator === null) {
			/** @var LinkGenerator $linkGenerator */
			$linkGenerator = $this->container->getByType(LinkGenerator::class);
		}

		return $linkGenerator->link(ltrim($dest, ':'), $params);
	}


	/**
	 * Generate link. If link does not exist return null.
	 *
	 * @param array<string, mixed> $params
	 */
	final public function linkSafe(string $dest, array $params = []): ?string
	{
		try {
			return $this->link($dest, $params);
		} catch (InvalidLinkException) {
			return null;
		}
	}


	/**
	 * @param array<string, mixed> $params
	 * @param positive-int $httpCode
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function redirect(string $dest, array $params = [], int $httpCode = 301): void
	{
		$this->redirectUrl((string) $this->linkSafe($dest, $params), $httpCode);
	}


	/**
	 * @param positive-int $httpCode
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function redirectUrl(string $url, int $httpCode = 301): void
	{
		if (Validators::isUrl($url) === false) {
			throw new \InvalidArgumentException('Haystack "' . $url . '" is not valid URL for redirect.');
		}
		throw new ThrowResponse(new RedirectResponse($this->convention, ['url' => $url], $httpCode));
	}


	final public function getCache(?string $namespace = null): Cache
	{
		static $storage;
		static $cache = [];
		$name = 'api---' . strtolower($namespace ?? $this->getName());

		if ($storage === null) {
			/** @var Storage $storage */
			$storage = $this->container->getByType(Storage::class);
		}
		if (isset($cache[$name]) === false) {
			$cache[$name] = new Cache($storage, $name);
		}

		return $cache[$name];
	}


	final public function getTranslator(): Translator
	{
		static $translator;
		if ($translator === null) {
			/** @var Translator $translator */
			$translator = $this->container->getByType(Translator::class);
		}

		return $translator;
	}


	/**
	 * @param array<string, mixed>|mixed ...$parameters
	 */
	final public function translate(mixed $message, ...$parameters): string
	{
		return $this->getTranslator()->translate($message, $parameters);
	}


	/**
	 * @return array<string, mixed>
	 */
	final public function getParameters(): array
	{
		return $this->container->getParameters();
	}


	final public function getParameter(string $key, mixed $defaultValue = null): mixed
	{
		return $this->container->getParameters()[$key] ?? $defaultValue;
	}


	final public function injectContainer(Container $container): void
	{
		$this->container = $container;
	}


	/**
	 * Is it an AJAX request?
	 */
	final public function isAjax(): bool
	{
		return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
			|| isset($_SERVER['HTTP_X_TRACY_AJAX']);
	}
}
