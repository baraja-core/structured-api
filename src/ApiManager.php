<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\Localization\Localization;
use Baraja\RuntimeInvokeException;
use Baraja\Serializer\Serializer;
use Baraja\ServiceMethodInvoker;
use Baraja\ServiceMethodInvoker\ProjectEntityRepository;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Middleware\Container;
use Baraja\StructuredApi\Middleware\MatchExtension;
use Baraja\StructuredApi\Tracy\Panel;
use Baraja\Url\Url;
use Nette\DI\Container as NetteContainer;
use Nette\Http\Request;
use Nette\Http\Response as HttpResponse;
use Tracy\Debugger;
use Tracy\ILogger;

final class ApiManager
{
	private Serializer $serializer;

	private Container $container;

	/** @var array<string, class-string> (endpointPath => endpointType) */
	private array $endpoints;

	/** @var MatchExtension[] */
	private array $matchExtensions = [];


	/**
	 * @param array<string, class-string> $endpoints
	 */
	public function __construct(
		array $endpoints,
		NetteContainer $netteContainer,
		private Request $request,
		private HttpResponse $response,
		private Convention $convention,
		private ?ProjectEntityRepository $projectEntityRepository = null,
	) {
		$this->serializer = new Serializer($convention);
		$this->container = new Container($netteContainer);
		$this->endpoints = $endpoints;
	}


	/**
	 * Based on the URL address or manual input, the corresponding endpoint is called,
	 * which returns the data as a complete HTTP response.
	 * The invalid path will be ignored because it may be handled by another application layer or other route.
	 *
	 * @param array<string|int, mixed>|null $params
	 * @throws StructuredApiException
	 */
	public function run(?string $path = null, ?array $params = [], ?string $method = null, bool $throw = false): void
	{
		$path ??= Url::get()->getRelativeUrl();
		$method = $method === null || $method === '' ? Helpers::httpMethod() : $method;
		$this->handleCorsRequest($method);
		$params = array_merge($this->safeGetParams($path), $this->getBodyParams($method), $params ?? []);
		$panel = new Panel($path, $params, $method);
		$isDebugger = class_exists(Debugger::class);
		if ($isDebugger) {
			Debugger::getBar()->addPanel($panel);
		}

		if (preg_match('/^api\/v(?<v>\d{1,3}(?:\.\d{1,3})?)\/(?<path>.*?)$/', $path, $pathParser) === 1) {
			try {
				$route = $this->route((string) preg_replace('/^(.*?)(\?.*|)$/', '$1', $pathParser['path']), $pathParser['v'], $params);
				try {
					$endpoint = $this->getEndpointService($route['class']);
					$panel->setEndpoint($endpoint);
					$response = $this->process($endpoint, $params, $route['action'], $method, $panel);
					$panel->setResponse($response);
				} catch (StructuredApiException $e) {
					throw $e;
				} catch (\Throwable $e) {
					if ($isDebugger) {
						Debugger::log($e, ILogger::EXCEPTION);
					}

					$code = $e->getCode();
					$code = is_int($code) && $code >= 100 && $code < 600 ? $code : 500;
					$response = new JsonResponse($this->convention, [
						'state' => 'error',
						'message' => $isDebugger && Debugger::isEnabled() === true ? $e->getMessage() : null,
						'code' => $code,
					], $code);
				}
				if ($response === null) {
					throw new BadRequestException(sprintf('Api endpoint "%s" must return some output. None returned.', $path));
				}
			} catch (BadRequestException $e) {
				$response = new JsonResponse($this->convention, [
					'state' => 'error',
					'message' => $e->getMessage(),
					'code' => $e->getHttpCode() >= 200 ? $e->getHttpCode() : 500,
				], $e->getHttpCode());
			}
			$this->processResponse($response, $throw);
		}
	}


	/**
	 * @param array<string|int, mixed>|null $params
	 * @return array<string|int, mixed>
	 * @throws StructuredApiException
	 */
	public function get(string $path, ?array $params = [], ?string $method = null): array
	{
		try {
			if (preg_match('/^api\/v([^\/]+)\/?(.*?)$/', $path) === 0) {
				$path = 'api/v1/' . $path;
			}

			$this->run($path, $params, $method, true);
		} catch (StructuredApiException $e) {
			throw $e;
		} catch (ThrowResponse $e) {
			return $e->getResponse()->toArray();
		}

		return [];
	}


	public function getConvention(): Convention
	{
		return $this->convention;
	}


	/**
	 * @return array<string, class-string>
	 */
	public function getEndpoints(): array
	{
		return $this->endpoints;
	}


	/**
	 * Create new API endpoint instance with all injected dependencies.
	 *
	 * @param class-string $className
	 * @internal
	 */
	public function getEndpointService(string $className): Endpoint
	{
		$endpoint = $this->container->getEndpoint($className);
		if ($endpoint instanceof BaseEndpoint) {
			$endpoint->convention = $this->convention;
		}

		return $endpoint;
	}


	public function addMatchExtension(MatchExtension $extension): void
	{
		$this->matchExtensions[] = $extension;
	}


	private function processResponse(Response $response, bool $throw): void
	{
		if ($throw === true) {
			throw new ThrowResponse($response);
		}
		if ($this->response->isSent() === false) {
			$httpCode = $response->getHttpCode();
			if ($httpCode < 100) {
				$httpCode = 500;
			} elseif ($httpCode > 599) {
				if (class_exists('\Tracy\Debugger') === true) {
					Debugger::log(new \LogicException(sprintf('Bad HTTP response "%d".', $httpCode)), ILogger::CRITICAL);
				}
				$httpCode = 500;
			}
			$this->response->setCode($httpCode);
			if ($response instanceof RedirectResponse) {
				$this->response->setHeader('Location', $response->getUrl());
				(new \Nette\Application\Responses\JsonResponse(['location' => $response->getUrl()]))
					->send($this->request, $this->response);
			} else {
				$this->response->setContentType($response->getContentType(), 'UTF-8');
				(new \Nette\Application\Responses\JsonResponse($response->toArray(), $response->getContentType()))
					->send($this->request, $this->response);
			}
		} else {
			throw new \RuntimeException('API: Response already was sent.');
		}
		die;
	}


	/**
	 * @param array<string|int, mixed> $params
	 * @throws StructuredApiException
	 */
	private function process(
		Endpoint $endpoint,
		array $params,
		string $action,
		string $method,
		Panel $panel,
	): ?Response {
		$params = $this->formatParams($params);
		$methodName = Helpers::resolveMethodName($endpoint, $method, $action);
		if ($methodName === null) {
			return new JsonResponse($this->convention, [
				'state' => 'error',
				'message' => sprintf('Method for action "%s" and HTTP method "%s" is not implemented.', $action, $method),
			], 404);
		}
		foreach ($this->matchExtensions as $extension) {
			$extensionResponse = $extension->beforeProcess($endpoint, $params, $action, $method);
			if ($extensionResponse !== null) {
				return $extensionResponse;
			}
		}
		$response = $this->invokeActionMethod($endpoint, $methodName, $method, $params, $panel);
		foreach ($this->matchExtensions as $extension) {
			$extensionResponse = $extension->afterProcess($endpoint, $params, $response);
			if ($extensionResponse !== null) {
				return $extensionResponse;
			}
		}

		return $response;
	}


	/**
	 * Safe method for get parameters from query. This helper is for CLI mode and broken Ngnix mod rewriting.
	 *
	 * @return array<string|int, mixed>
	 */
	private function safeGetParams(string $path): array
	{
		$return = $_GET;
		if ($return === []) {
			$query = trim(explode('?', $path, 2)[1] ?? '');
			if ($query !== '') {
				parse_str($query, $queryParams);
				foreach ($queryParams as $key => $value) {
					$return[$key] = $value;
				}
			}
		}

		return $return;
	}


	/**
	 * Route user query to class and action.
	 *
	 * @param array<string|int, mixed> $params
	 * @param string $version in format /\d{1,3}(?:\.\d{1,3})?/
	 * @return array{class: class-string, action: string}
	 */
	private function route(string $route, string $version, array $params): array
	{
		$class = null;
		$action = null;
		$route = trim($route, '/');
		if (!str_contains($route, '/')) { // 1. Simple match
			$class = $this->endpoints[$route] ?? null;
			$action = 'default';
		} elseif (preg_match('/^([^\/]+)\/([^\/]+)$/', $route, $routeParser) === 1) { // 2. <endpoint>/<action>
			$class = $this->endpoints[$routeParser[1]] ?? null;
			$action = Helpers::formatApiName($routeParser[2]);
		}
		if ($action === null) {
			throw new BadRequestException('Action can not be empty.');
		}
		if ($class === null) {
			throw new BadRequestException(
				sprintf('Can not route "%s", because endpoint does not exist.', $route)
				. ($params !== [] ? "\n" . 'Given params:' . "\n" . json_encode($params, JSON_THROW_ON_ERROR) : ''),
			);
		}
		if (\class_exists($class) === false) {
			throw new BadRequestException(sprintf('Route class "%s" does not exist.', $class));
		}

		return [
			'class' => $class,
			'action' => $action,
		];
	}


	/**
	 * Call all endpoint methods in regular order and return response state.
	 *
	 * @param array<string, mixed> $params
	 * @throws StructuredApiException
	 */
	private function invokeActionMethod(
		Endpoint $endpoint,
		string $methodName,
		string $method,
		array $params,
		Panel $panel,
	): ?Response {
		if (PHP_SAPI !== 'cli') {
			$httpRequest = class_exists(Request::class)
				? $this->container->getByType(Request::class)
				: null;
			$localization = class_exists(Localization::class)
				? $this->container->getByType(Localization::class)
				: null;
			if ($httpRequest !== null && $localization !== null) {
				$localization->processHttpRequest($httpRequest);
			}
		}

		try {
			$invoker = new ServiceMethodInvoker($this->projectEntityRepository);
			$args = $invoker->getInvokeArgs($endpoint, $methodName, $params, true);
			$panel->setArgs($args);

			try {
				$httpCode = 200;
				try {
					$methodResponse = (new \ReflectionMethod($endpoint, $methodName))->invokeArgs($endpoint, $args);
					/** @phpstan-ignore-next-line */
				} catch (ThrowStatusResponse $statusResponse) {
					$methodResponse = $statusResponse->getResponse();
					$httpCode = $methodResponse->getHttpCode();
				}
				if ($methodResponse === null || $methodResponse instanceof Response) {
					$response = $methodResponse;
				} elseif (is_object($methodResponse) || is_array($methodResponse)) {
					$response = new JsonResponse(
						convention: $this->convention,
						haystack: $this->serializer->serialize($methodResponse),
						httpCode: $httpCode,
					);
				} else {
					throw new \LogicException(sprintf(
						'Response "%s" is not valid, because it must be instance of "%s" or serializable object (DTO).',
						get_debug_type($methodResponse),
						Response::class,
					));
				}
			} catch (\ReflectionException $e) {
				throw new \RuntimeException($e->getMessage(), 500, $e);
			}
		} catch (\InvalidArgumentException $e) {
			return $this->rewriteInvalidArgumentException($e) ?? throw $e;
		} catch (ThrowResponse $e) {
			$response = $e->getResponse();
		} catch (RuntimeInvokeException $e) {
			throw new StructuredApiException($e->getMessage(), 500, $e);
		}
		if ($method !== 'GET' && $response === null) {
			$response = new JsonResponse($this->convention, ['state' => 'ok']);
		}
		if ($endpoint instanceof BaseEndpoint) {
			$endpoint->saveState();
		}

		return $response;
	}


	/**
	 * Gets POST data directly from the HTTP header, or tries to parse the data from the string.
	 * Some legacy clients send data as json, which is in base string format, so field casting to array is mandatory.
	 *
	 * @return array<string|int, mixed>
	 */
	private function getBodyParams(string $method): array
	{
		if ($method === 'GET' || $method === 'DELETE') {
			return [];
		}

		$return = array_merge((array) $this->request->getPost(), $this->request->getFiles());
		try {
			$post = array_keys($_POST)[0] ?? '';
			if (str_starts_with($post, '{') && str_ends_with($post, '}')) { // support for legacy clients
				$json = json_decode($post, true, 512, JSON_THROW_ON_ERROR);
				if (is_array($json) === false) {
					throw new \LogicException('Json is not valid array.');
				}
				unset($_POST[$post]);
				foreach ($json as $key => $value) {
					$return[$key] = $value;
				}
			}
		} catch (\Throwable) {
			// Silence is golden.
		}
		try {
			$input = (string) file_get_contents('php://input');
			if ($input !== '') {
				$phpInputArgs = (array) json_decode($input, true, 512, JSON_THROW_ON_ERROR);
				foreach ($phpInputArgs as $key => $value) {
					$return[$key] = $value;
				}
			}
		} catch (\Throwable) {
			// Silence is golden.
		}

		return $return;
	}


	private function rewriteInvalidArgumentException(\InvalidArgumentException $e): ?Response
	{
		$message = null;
		if (preg_match('/^UserException:\s+(.+)$/', $e->getMessage(), $eMessageParser) === 1) {
			$message = $eMessageParser[1] ?? $e->getMessage();
		} else {
			$traceClass = $e->getTrace()[0]['class'] ?? null;
			if (is_string($traceClass) && str_ends_with($traceClass, '\Assert')) {
				for ($i = 0; $i <= 3; $i++) {
					$traceFunction = $e->getTrace()[$i]['function'] ?? null;
					if (
						is_string($traceFunction)
						&& preg_match('/^set([A-Za-z0-9]+)$/', $traceFunction, $functionParser) === 1
					) {
						$message = sprintf('%s: %s', Helpers::firstUpper($functionParser[1]), $e->getMessage());
						break;
					}
				}
			}
		}
		if ($message !== null) {
			return new JsonResponse($this->convention, [
				'state' => 'error',
				'message' => $message,
			], $this->convention->getDefaultErrorCode());
		}

		return null;
	}


	/**
	 * @param array<string|int, mixed> $params
	 * @return array<string, mixed>
	 */
	private function formatParams(array $params): array
	{
		$return = [];
		foreach ($params as $key => $value) {
			if (is_string($key) === false) {
				$key = (string) $key;
				trigger_error(sprintf('Argument %s: Only string keys are supported.', $key));
			}
			if ($key === '') {
				throw new \InvalidArgumentException('Only non-empty string keys are supported.');
			}
			$return[$key] = $value;
		}

		return $return;
	}


	private function handleCorsRequest(string $httpMethod): void
	{
		if (isset($_SERVER['HTTP_ORIGIN'])) {
			header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400'); // cache for 1 day
		}
		if ($httpMethod === 'OPTIONS') {
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
				header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
			}
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
				header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
			}
			die;
		}
	}
}
