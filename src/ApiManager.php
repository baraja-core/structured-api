<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\RuntimeInvokeException;
use Baraja\ServiceMethodInvoker;
use Baraja\ServiceMethodInvoker\ProjectEntityRepository;
use Baraja\StructuredApi\Entity\Convention;
use Baraja\StructuredApi\Middleware\MatchExtension;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Http\Response as HttpResponse;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\ILogger;

final class ApiManager
{

	/** @var array<string, string> (endpointPath => endpointType) */
	private array $endpoints;

	/** @var MatchExtension[] */
	private array $matchExtensions = [];


	/**
	 * @param array<string, string> $endpoints
	 */
	public function __construct(
		array $endpoints,
		private Container $container,
		private Request $request,
		private HttpResponse $response,
		private Convention $convention,
		private ?ProjectEntityRepository $projectEntityRepository = null
	) {
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
	public function run(string $path, ?array $params = [], ?string $method = null, bool $throw = false): void
	{
		$this->checkFirewall();
		$params = array_merge($this->safeGetParams($path), $this->getBodyParams($method = $method ?: Helpers::httpMethod()), $params ?? []);

		if (preg_match('/^api\/v(?<v>\d{1,3}(?:\.\d{1,3})?)\/(?<path>.*?)$/', $path, $pathParser)) {
			try {
				$route = $this->route((string) preg_replace('/^(.*?)(\?.*|)$/', '$1', $pathParser['path']), $pathParser['v'], $params);
				$response = null;
				try {
					$endpoint = $this->getEndpointService($route['class'], $params);
					$response = $this->process($endpoint, $params, $route['action'], $method);
				} catch (StructuredApiException $e) {
					throw $e;
				} catch (\Throwable $e) {
					$isDebugger = class_exists(Debugger::class);
					if ($isDebugger === true) {
						Debugger::log($e, ILogger::EXCEPTION);
					}

					$response = new JsonResponse($this->convention, [
						'state' => 'error',
						'message' => $isDebugger && Debugger::isEnabled() === true ? $e->getMessage() : null,
						'code' => $code = (($code = (int) $e->getCode()) === 0 ? 500 : $code),
					], $code);
				}
				if ($response === null) {
					throw new BadRequestException('Api endpoint "' . $path . '" must return some output. None returned.');
				}
			} catch (BadRequestException $e) {
				$response = new JsonResponse($this->convention, [
					'state' => 'error',
					'message' => $e->getMessage(),
					'code' => $e->getHttpCode(),
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
	 * @return array<string, string>
	 */
	public function getEndpoints(): array
	{
		return $this->endpoints;
	}


	/**
	 * Create new API endpoint instance with all injected dependencies.
	 *
	 * @param array<string|int, mixed> $params
	 * @internal
	 */
	public function getEndpointService(string $className, array $params): Endpoint
	{
		/** @var Endpoint $endpoint */
		$endpoint = $this->container->getByType($className);
		$endpoint->setConvention($this->convention);
		$endpoint->setData($params);

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
				$httpCode = 100;
			} elseif ($httpCode > 599) {
				if (class_exists('\Tracy\Debugger') === true) {
					Debugger::log(new \LogicException('Bad HTTP response "' . $httpCode . '".'), ILogger::CRITICAL);
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
	private function process(Endpoint $endpoint, array $params, string $action, string $method): ?Response
	{
		$methodName = Helpers::resolveMethodName($endpoint, $method, $action);
		if ($methodName === null) {
			return new JsonResponse($this->convention, [
				'state' => 'error',
				'message' => 'Method for action "' . $action . '" and HTTP method "' . $method . '" is not implemented.',
			], 404);
		}
		foreach ($this->matchExtensions as $extension) {
			$extensionResponse = $extension->beforeProcess($endpoint, $params, $action, $method);
			if ($extensionResponse !== null) {
				return $extensionResponse;
			}
		}
		$response = $this->invokeActionMethod($endpoint, $methodName, $method, $params);
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
		$return = (array) ($_GET ?? []);
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
	 * @return array<string, string>
	 */
	private function route(string $route, string $version, array $params): array
	{
		$class = null;
		$action = null;
		$route = trim($route, '/');
		if (!str_contains($route, '/')) { // 1. Simple match
			$class = $this->endpoints[$route] ?? null;
			$action = 'default';
		} elseif (preg_match('/^([^\/]+)\/([^\/]+)$/', $route, $routeParser)) { // 2. <endpoint>/<action>
			$class = $this->endpoints[$routeParser[1]] ?? null;
			$action = Helpers::formatApiName($routeParser[2]);
		}
		if ($action === null) {
			throw new BadRequestException('Action can not be empty.');
		}
		if ($class === null) {
			throw new BadRequestException(
				'Can not route "' . $route . '", because endpoint does not exist.'
				. ($params !== [] ? "\n" . 'Given params:' . "\n" . json_encode($params) : ''),
			);
		}
		if (\class_exists($class) === false) {
			throw new BadRequestException('Route class "' . $class . '" does not exist.');
		}

		return [
			'class' => $class,
			'action' => $action,
		];
	}


	/**
	 * Call all endpoint methods in regular order and return response state.
	 *
	 * @param array<string|int, mixed> $params
	 * @throws StructuredApiException
	 */
	private function invokeActionMethod(
		Endpoint $endpoint,
		string $methodName,
		string $method,
		array $params
	): ?Response {
		$endpoint->startup();
		$endpoint->startupCheck();
		$response = null;

		try {
			$response = (new ServiceMethodInvoker($this->projectEntityRepository))
				->invoke($endpoint, $methodName, $params, true);
		} catch (\InvalidArgumentException $e) {
			return $this->rewriteInvalidArgumentException($e) ?? throw $e;
		} catch (ThrowResponse $e) {
			$response = $e->getResponse();
		} catch (RuntimeInvokeException $e) {
			throw new StructuredApiException($e->getMessage(), (int) $e->getCode(), $e);
		}
		if ($method !== 'GET' && $response === null) {
			$response = new JsonResponse($this->convention, ['state' => 'ok']);
		}

		$endpoint->saveState();

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
			if ($post !== '' && preg_match('/^{.*}$/', $post)) { // support for legacy clients
				$json = json_decode($post, true);
				if (is_array($json) === false) {
					throw new \LogicException('Json is not valid array.');
				}
				unset($_POST[$post]);
			}
		} catch (\Throwable) {
			try {
				$input = (string) file_get_contents('php://input');
				$json = $input !== '' ? (array) json_decode($input, true) : [];
			} catch (\Throwable) {
				// Silence is golden.
			}
		}

		foreach ($json ?? [] as $key => $value) {
			$return[$key] = $value;
		}

		return $return;
	}


	private function checkFirewall(): void
	{
		if (str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'CloudFlare-AlwaysOnline') === true) {
			header('HTTP/1.0 403 Forbidden');
			echo '<title>Access denied | API endpoint</title>';
			echo '<h1>Access denied</h1>';
			echo '<p>API endpoint crawling is disabled for robots.</p>';
			echo '<p><b>Information for developers:</b> Endpoint API indexing is disabled for privacy reasons. At the same time, robots can crawl a disproportionate amount of data, copying your valuable data.';
			die;
		}
	}


	private function rewriteInvalidArgumentException(\InvalidArgumentException $e): ?Response
	{
		$message = null;
		if (preg_match('/^UserException:\s+(.+)$/', $e->getMessage(), $eMessageParser)) {
			$message = $eMessageParser[1] ?? $e->getMessage();
		} elseif (isset($e->getTrace()[0]['class']) && str_ends_with((string) $e->getTrace()[0]['class'], '\Assert')) {
			for ($i = 0; $i <= 3; $i++) {
				if (
					isset($e->getTrace()[$i])
					&& preg_match('/^set([A-Za-z0-9]+)$/', $e->getTrace()[$i]['function'] ?? '', $functionParser)
				) {
					$message = Strings::firstUpper($functionParser[1]) . ': ' . $e->getMessage();
					break;
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
}
