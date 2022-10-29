<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\CAS\User;
use Baraja\CAS\UserIdentity;
use Baraja\Localization\Localization;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Middleware\Container;
use Baraja\StructuredApi\Response\Status\ErrorResponse;
use Baraja\StructuredApi\Response\Status\OkResponse;
use Baraja\StructuredApi\Response\Status\StatusResponse;
use Baraja\StructuredApi\Response\Status\SuccessResponse;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Http\Request;
use Nette\Localization\Translator;

abstract class BaseEndpoint implements Endpoint
{
	// TODO: @deprecated since 2022-04-18, use Perl case instead
	public const
		FLASH_MESSAGE_SUCCESS = 'success',
		FLASH_MESSAGE_INFO = 'info',
		FLASH_MESSAGE_WARNING = 'warning',
		FLASH_MESSAGE_ERROR = 'error';

	public const
		FlashMessageSuccess = 'success',
		FlashMessageInfo = 'info',
		FlashMessageWarning = 'warning',
		FlashMessageError = 'error';

	public const FlashMessageTypes = [
		self::FlashMessageSuccess,
		self::FlashMessageInfo,
		self::FlashMessageWarning,
		self::FlashMessageError,
	];

	/** @var callable[] */
	public array $onSaveState = [];

	protected Container $container;

	protected Convention $convention;

	/** @var mixed[] */
	protected array $data = [];

	/** @var array<int, array{message: string, type: string}> */
	private array $messages = [];

	private bool $startupCheck = false;


	public function __toString(): string
	{
		return static::class;
	}


	public function startup(): void
	{
		if (PHP_SAPI !== 'cli' && class_exists('\Baraja\Localization\Localization') === true) {
			$httpRequest = $this->container->getByType(Request::class);
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
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendResponse(Response $response): void
	{
		ThrowResponse::invoke($response);
	}


	/**
	 * This method returns an array of data exactly as you pass it and converts it to a valid json.
	 *
	 * Note: The formatting and type of data is purely managed by the user.
	 * If you want to send status data, it is recommended to use the sendOk() and sendError() methods.
	 * This method should be used for sending data in a user-defined structure only.
	 *
	 * @param array<string, mixed>|Response|StatusResponse $haystack
	 * @param positive-int $httpCode
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendJson(array|Response|StatusResponse $haystack, int $httpCode = 200): void
	{
		if (is_array($haystack) && $this->messages !== []) {
			if (isset($haystack['flashMessages']) === true) {
				throw new \RuntimeException('Flash message was already defined in your data. Did you want to use the flashMessage() function?');
			}
			$haystack += ['flashMessages' => $this->messages];
			$this->messages = []; // Reset for next response
		}

		$this->sendResponse(new JsonResponse($this->convention, $haystack, $httpCode));
	}


	/**
	 * @param positive-int|null $code
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendError(string $message, ?int $code = null, ?string $hint = null): void
	{
		$code ??= $this->convention->getDefaultErrorCode();
		$this->sendJson(new ErrorResponse(
			message: $message,
			code: $code,
			hint: $hint,
		), $code);
	}


	/**
	 * @param array<string, mixed>|Response|StatusResponse $data
	 * @param positive-int|null $code
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendOk(
		array|Response|StatusResponse $data = [],
		?string $message = null,
		?int $code = null,
	): void {
		$code ??= $this->convention->getDefaultOkCode();
		$this->sendJson(new OkResponse(
			message: $message,
			code: $code,
			data: $data,
		), $code);
	}


	/**
	 * @param array<string, mixed>|Response|StatusResponse $data
	 * @param positive-int|null $code
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendSuccess(
		array|Response|StatusResponse $data = [],
		?string $message = null,
		?int $code = null,
	): void {
		$code ??= $this->convention->getDefaultOkCode();
		$this->sendJson(new SuccessResponse(
			message: $message,
			code: $code,
			data: $data,
		), $code);
	}


	/**
	 * @param array<int, mixed> $items
	 * @param array<string, mixed> $data
	 * @phpstan-return never-return
	 * @throws ThrowResponse
	 */
	final public function sendItems(array $items, ?object $paginator = null, array $data = []): void
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
	final public function flashMessage(string $message, string $type = self::FlashMessageInfo): void
	{
		$type = strtolower($type);
		if (\in_array($type, self::FlashMessageTypes, true) === false) {
			throw new \LogicException(sprintf(
				'Flash message type "%s" must be one of "%s". Did you use FLASH_MESSAGE_* constant?',
				$type,
				implode('", "', self::FlashMessageTypes),
			));
		}
		$this->messages[] = [
			'message' => $message,
			'type' => $type,
		];
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
				throw new \InvalidArgumentException(sprintf('Format key value must be scalar, but "%s" given.', get_debug_type($dataValue)));
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
			throw new \LogicException(sprintf('Method %s::startup() or its descendant doesn\'t call parent::startup()."', static::class));
		}
	}


	final public function saveState(): void
	{
		foreach ($this->onSaveState as $saveState) {
			$saveState($this);
		}
	}


	final public function getUser(): User
	{
		static $user;
		if ($user === null) {
			if (class_exists('Baraja\CAS\User') === false) {
				throw new \RuntimeException('Service "Baraja\CAS\User" is not available. Did you install baraja-core/cas?');
			}
			$user = $this->container->getByType('Baraja\CAS\User');
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


	final public function getUserEntity(): ?UserIdentity
	{
		return $this->getUser()->getIdentity();
	}


	/** @deprecated since 2022-10-29, please use baraja-core/cas instead. */
	final public function getAuthorizator(): void
	{
		throw new \LogicException('Method "getAuthorizator" has been removed, please use baraja-core/cas instead.');
	}


	/**
	 * @param array<string, mixed> $params
	 */
	final public function link(string $dest, array $params = []): string
	{
		static $linkGenerator;
		if ($linkGenerator === null) {
			if (class_exists('Nette\Application\LinkGenerator') === false) {
				throw new \RuntimeException('Service "Nette\Application\LinkGenerator" is not available. Did you install nette/application?');
			}
			$linkGenerator = $this->container->getByType('Nette\Application\LinkGenerator');
		}

		try {
			return $linkGenerator->link(ltrim($dest, ':'), $params);
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException($e->getMessage(), $e->getCode());
		}
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
		} catch (\InvalidArgumentException) {
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
		if (Helpers::isUrl($url) === false) {
			throw new \InvalidArgumentException(sprintf('Haystack "%s" is not valid URL for redirect.', $url));
		}
		throw new ThrowResponse(new RedirectResponse($this->convention, ['url' => $url], $httpCode));
	}


	final public function getCache(?string $namespace = null): Cache
	{
		static $storage;
		static $cache = [];
		$name = sprintf('api---%s', strtolower($namespace ?? $this->getName()));

		if ($storage === null) {
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
