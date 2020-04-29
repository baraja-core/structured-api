<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\RuntimeInvokeException;
use Baraja\ServiceMethodInvoker;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Utils\Strings;
use Tracy\Debugger;

final class ApiManager
{

	/** @var Container */
	private $container;

	/** @var Request */
	private $request;

	/** @var Response */
	private $response;

	/** @var Cache */
	private $cache;

	/** @var string[] (endpointPath => endpointType) */
	private $endpoints = [];


	/**
	 * @param Container $container
	 * @param Request $request
	 * @param Response $response
	 * @param IStorage $storage
	 */
	public function __construct(Container $container, Request $request, Response $response, IStorage $storage)
	{
		$this->container = $container;
		$this->request = $request;
		$this->response = $response;
		$this->cache = new Cache($storage, 'structured-api');
	}


	/**
	 * By given inputs or current URL route specific API endpoint and send full HTTP response.
	 *
	 * @param string $path
	 * @param mixed[]|null $params
	 * @param string|null $method
	 * @param bool $throw
	 * @throws StructuredApiException
	 */
	public function run(string $path, ?array $params = [], ?string $method = null, bool $throw = false): void
	{
		$params = array_merge($this->safeGetParams($path), $this->getBodyParams($method = $method ?: $this->getMethod()), $params ?? []);

		if (preg_match('/^api\/v([^\/]+)\/?(.*?)$/', $path, $pathParser)) {
			if (($version = (int) $pathParser[1]) < 1 || $version > 999 || !preg_match('#^[+-]?\d+$#D', $pathParser[1])) {
				StructuredApiException::invalidVersion($pathParser[1]);
			}

			$route = $this->route((string) preg_replace('/^(.*?)(\?.*|)$/', '$1', $pathParser[2]), $params);

			try {
				$response = $this->invokeActionMethod(
					$this->createEndpointInstance($route['class'], $params),
					$route['action'],
					$method,
					$params
				);
			} catch (StructuredApiException $e) {
				throw $e;
			} catch (\Throwable $e) {
				if (($isDebugger = class_exists(Debugger::class)) === true) {
					Debugger::log($e);
				}

				$response = new JsonResponse([
					'state' => 'error',
					'message' => $isDebugger && Debugger::isEnabled() === true ? $e->getMessage() : null,
					'code' => ($code = $e->getCode()) === 0 ? 500 : $code,
				]);
			}

			if ($response !== null) {
				if ($throw === true) {
					throw new ThrowResponse($response);
				}
				if ($this->response->isSent() === false) {
					$this->response->setContentType($response->getContentType(), 'UTF-8');
					(new \Nette\Application\Responses\JsonResponse($response->toArray(), $response->getContentType()))
						->send($this->request, $this->response);
				} else {
					throw new \RuntimeException('API: Response already was sent.');
				}
				die;
			}

			StructuredApiException::apiEndpointMustReturnSomeData($path);
		}

		StructuredApiException::invalidApiPath($path);
	}


	/**
	 * @param string $path
	 * @param mixed[]|null $params
	 * @param string|null $method
	 * @return mixed[]
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


	/**
	 * @return string[]
	 */
	public function getEndpoints(): array
	{
		return $this->endpoints;
	}


	/**
	 * @internal for DIC
	 * @param string[] $endpointServices
	 */
	public function setEndpoints(array $endpointServices): void
	{
		$hash = implode('|', $endpointServices);
		if (($cache = $this->cache->load('endpoints')) === null || ($cache['hash'] ?? '') !== $hash) {
			$endpoints = [];
			foreach ($endpointServices as $endpointService) {
				$type = \get_class($this->container->getService($endpointService));
				$className = (string) preg_replace('/^.*?([^\\\\]+)Endpoint$/', '$1', $type);
				$endpointPath = Helpers::formatToApiName($className);
				if (isset($endpoints[$endpointPath]) === true) {
					throw new \RuntimeException(
						'Api Manager: Endpoint "' . $endpointPath . '" already exist, '
						. 'because this endpoint implements service "' . $type . '" and "' . $endpoints[$endpointPath] . '".'
					);
				}
				$endpoints[$endpointPath] = $type;
			}
			$this->cache->save('endpoints', [
				'hash' => $hash,
				'endpoints' => $endpoints,
			]);
		} else {
			$endpoints = $cache['endpoints'] ?? [];
		}
		$this->endpoints = $endpoints;
	}


	/**
	 * Create new API endpoint instance with all injected dependencies.
	 *
	 * @internal
	 * @param string $className
	 * @param mixed[] $params
	 * @return Endpoint
	 */
	public function createEndpointInstance(string $className, array $params): Endpoint
	{
		/** @var Endpoint $endpoint */
		$endpoint = $this->container->getByType($className);

		foreach (InjectExtension::getInjectProperties(\get_class($endpoint)) as $property => $service) {
			$endpoint->{$property} = $this->container->getByType($service);
		}

		$endpoint->setData($params);

		return $endpoint;
	}


	/**
	 * Safe method for get parameters from query. This helper is for CLI mode and broken Ngnix mod rewriting.
	 *
	 * @param string $path
	 * @return mixed[]
	 */
	private function safeGetParams(string $path): array
	{
		$return = (array) ($_GET ?? []);

		if ($return === [] && ($query = trim(explode('?', $path, 2)[1] ?? '')) !== '') {
			parse_str($query, $queryParams);
			foreach ($queryParams as $key => $value) {
				$return[$key] = $value;
			}
		}

		return $return;
	}


	/**
	 * Route user query to class and action.
	 *
	 * @param string $route
	 * @param mixed[] $params
	 * @return string[]
	 * @throws StructuredApiException
	 */
	private function route(string $route, array $params): array
	{
		$class = null;
		$action = null;

		if (strpos($route = trim($route, '/'), '/') === false) { // 1. Simple match
			$class = $this->endpoints[$route] ?? null;
			$action = 'default';
		} elseif (preg_match('/^([^\/]+)\/([^\/]+)$/', $route, $routeParser)) { // 2. <endpoint>/<action>
			$class = $this->endpoints[$routeParser[1]] ?? null;
			$action = Helpers::formatApiName($routeParser[2]);
		}

		if ($class === null) {
			StructuredApiException::canNotRouteException($route, $params);
		}

		if (\class_exists($class) === false) {
			StructuredApiException::routeClassDoesNotExist($class);
		}

		return [
			'class' => $class,
			'action' => $action,
		];
	}


	/**
	 * Create new API endpoint instance with all injected dependencies.
	 *
	 * @param string $class
	 * @param mixed[] $params
	 * @return Endpoint
	 */
	private function createInstance(string $class, array $params): Endpoint
	{
		/** @var Endpoint $endpoint */
		$endpoint = $this->container->getByType($class);

		foreach (InjectExtension::getInjectProperties(\get_class($endpoint)) as $property => $service) {
			$endpoint->{$property} = $this->container->getByType($service);
		}

		$endpoint->setData($params);

		return $endpoint;
	}


	/**
	 * Call all endpoint methods in regular order and return response state.
	 *
	 * @param Endpoint $endpoint
	 * @param string $action
	 * @param string $method
	 * @param mixed[] $params
	 * @return BaseResponse|null
	 * @throws RuntimeStructuredApiException
	 */
	private function invokeActionMethod(Endpoint $endpoint, string $action, string $method, array $params): ?BaseResponse
	{
		$endpoint->startup();
		$endpoint->startupCheck();
		$response = null;
		$ref = null;

		if (($methodName = $this->getActionMethodName($endpoint, $method, $action)) !== null) {
			try {
				$response = (new ServiceMethodInvoker)->invoke($endpoint, $methodName, $params, true);
			} catch (ThrowResponse $e) {
				$response = $e->getResponse();
			} catch (RuntimeInvokeException $e) {
				throw new RuntimeStructuredApiException($e->getMessage(), $e->getCode(), $e);
			}
		}

		if ($method !== 'GET' && $response === null) {
			$response = new JsonResponse(['state' => 'ok']);
		}

		$endpoint->saveState();

		return $response;
	}


	/**
	 * Return current HTTP method.
	 *
	 * @return string
	 */
	private function getMethod(): string
	{
		if (($method = $_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
			&& preg_match('#^[A-Z]+$#D', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '')
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		return $method ?: 'GET';
	}


	/**
	 * @param string $method
	 * @return mixed[]
	 */
	private function getBodyParams(string $method): array
	{
		if ($method !== 'GET' && $method !== 'DELETE') {
			$return = [];
			if (\count($_POST) === 1 && preg_match('/^\{.*\}$/', $post = array_keys($_POST)[0]) && is_array($json = json_decode($post, true)) === true) {
				foreach ($json as $key => $value) {
					$return[$key] = $value;
				}
				unset($_POST[$post]);
			} elseif (($input = (string) file_get_contents('php://input')) !== '' && $json = json_decode($input, true)) {
				foreach ($json as $key => $value) {
					$return[$key] = $value;
				}
			}

			return $return;
		}

		return [];
	}


	/**
	 * @param Endpoint $endpoint
	 * @param string $method
	 * @param string $action
	 * @return string|null
	 */
	private function getActionMethodName(Endpoint $endpoint, string $method, string $action): ?string
	{
		$tryMethods = [];
		$tryMethods[] = ($method === 'GET' ? 'action' : strtolower($method)) . Strings::firstUpper($action);
		if ($method === 'PUT') {
			$tryMethods[] = 'update' . Strings::firstUpper($action);
		} elseif ($method === 'POST') {
			$tryMethods[] = 'create' . Strings::firstUpper($action);
		}

		$methodName = null;
		foreach ($tryMethods as $tryMethod) {
			if (\method_exists($endpoint, $tryMethod) === true) {
				return $tryMethod;
			}
		}

		return null;
	}
}
