<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Utils\Strings;
use Tracy\Debugger;

final class ApiManager
{
	/** @var mixed[] */
	private static $emptyTypeMapper = [
		'string' => '',
		'bool' => false,
		'int' => 0,
		'float' => 0.0,
		'array' => [],
		'null' => null,
	];

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
		$params = array_merge($_GET, $this->getBodyParams($method = $method ?: $this->getMethod()), $params ?? []);

		if (preg_match('/^api\/v([^\/]+)\/?(.*?)$/', $path, $pathParser)) {
			if (($version = (int) $pathParser[1]) < 1 || $version > 999 || !preg_match('#^[+-]?\d+$#D', $pathParser[1])) {
				StructuredApiException::invalidVersion($pathParser[1]);
			}

			$route = $this->route((string) preg_replace('/^(.*?)(\?.*|)$/', '$1', $pathParser[2]), $params);

			try {
				$response = $this->callActionMethods(
					$this->createInstance($route['class'], $params),
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
					'code' => $e->getCode(),
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
		$endpoint = new $class($this->container);
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
	private function callActionMethods(Endpoint $endpoint, string $action, string $method, array $params): ?BaseResponse
	{
		$endpoint->startup();
		$endpoint->startupCheck();
		$response = null;
		$ref = null;
		$args = [];

		if (($methodName = $this->getActionMethodName($endpoint, $method, $action)) !== null) {
			try {
				foreach (($ref = new \ReflectionMethod($endpoint, $methodName))->getParameters() as $parameter) {
					if (($pName = $parameter->getName()) === 'data') {
						if ((($type = $parameter->getType()) !== null && ($typeName = $type->getName()) !== 'array') || $type === null) {
							RuntimeStructuredApiException::propertyDataMustBeArray($endpoint, $type === null ? null : $typeName ?? '');
						}

						$args[$pName] = $params;
					} elseif (isset($params[$pName]) === true) {
						if ($params[$pName]) {
							$args[$pName] = $this->fixType($params[$pName], (($type = $parameter->getType()) !== null) ? $type : null);
						} elseif (($type = $parameter->getType()) !== null) {
							$args[$pName] = $this->returnEmptyValue($endpoint, $pName, $type);
						}
					} elseif ($parameter->isOptional() === true && $parameter->isDefaultValueAvailable() === true) {
						try {
							$args[$pName] = $parameter->getDefaultValue();
						} catch (\Throwable $e) {
						}
					} else {
						RuntimeStructuredApiException::parameterDoesNotSet(
							$endpoint,
							$parameter->getName(),
							$parameter->getPosition(),
							$methodName ?? ''
						);
					}
				}
			} catch (\ReflectionException $e) {
				RuntimeStructuredApiException::reflectionException($e);
			}
		}

		try {
			$response = $ref !== null ? $ref->invokeArgs($endpoint, $args) : null;
		} catch (ThrowResponse $e) {
			$response = $e->getResponse();
		}

		if ($method !== 'GET' && $response === null) {
			$response = new JsonResponse(['state' => 'ok']);
		}

		$endpoint->saveState();

		return $response;
	}


	/**
	 * @param Endpoint $endpoint
	 * @param string $parameter
	 * @param \ReflectionType $type
	 * @return mixed|null
	 * @throws RuntimeStructuredApiException
	 */
	private function returnEmptyValue(Endpoint $endpoint, string $parameter, \ReflectionType $type)
	{
		if ($type->allowsNull() === true) {
			return null;
		}

		if (strpos($name = $type->getName(), '/') !== false || class_exists($name) === true) {
			RuntimeStructuredApiException::parameterMustBeObject($endpoint, $parameter, $name);
		}

		if (isset(self::$emptyTypeMapper[$name]) === true) {
			return self::$emptyTypeMapper[$name];
		}

		RuntimeStructuredApiException::canNotCreateEmptyValueByType($endpoint, $parameter, $name);

		return null;
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
	 * Rewrite given type to preference type by annotation.
	 *
	 * 1. If type is nullable, keep original haystack
	 * 2. Empty value rewrite to null, if null is supported
	 * 3. Scalar types
	 * 4. Other -> keep original
	 *
	 * @param mixed $haystack
	 * @param \ReflectionType|null $type
	 * @return mixed
	 */
	private function fixType($haystack, ?\ReflectionType $type)
	{
		if ($type === null) {
			return $haystack;
		}

		if (!$haystack && $type->allowsNull()) {
			return null;
		}

		if ($type->getName() === 'bool') {
			return \in_array(strtolower((string) $haystack), ['1', 'true', 'yes'], true) === true;
		}

		if ($type->getName() === 'int') {
			return (int) $haystack;
		}

		if ($type->getName() === 'float') {
			return (float) $haystack;
		}

		return $haystack;
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
