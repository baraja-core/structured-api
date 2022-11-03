<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\CAS\User;
use Baraja\CAS\UserIdentity;
use Baraja\StructuredApi\Bridge\LinkGeneratorBridge;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Response\Status\ErrorResponse;
use Baraja\StructuredApi\Response\Status\OkResponse;
use Baraja\StructuredApi\Response\Status\StatusResponse;
use Baraja\StructuredApi\Response\Status\SuccessResponse;

abstract class BaseEndpoint implements Endpoint
{
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

	public ?User $user = null;

	public LinkGeneratorBridge $linkGenerator;

	public Convention $convention;

	/** @var array<int, array{message: string, type: string}> */
	private array $messages = [];


	public function __toString(): string
	{
		return static::class;
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


	final public function saveState(): void
	{
		foreach ($this->onSaveState as $saveState) {
			$saveState($this);
		}
	}


	final public function getUser(): User
	{
		if ($this->user === null) {
			throw new \RuntimeException('Service "Baraja\CAS\User" is not available. Did you install baraja-core/cas?');
		}

		return $this->user;
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


	/**
	 * @param array<string, mixed> $params
	 */
	final public function link(string $dest, array $params = []): string
	{
		return $this->linkGenerator->link(ltrim($dest, ':'), $params);
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
}
