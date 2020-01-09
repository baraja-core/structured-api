<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\DI\Container;
use Tracy\Debugger;

class ApiManager
{

	/**
	 * @var mixed[]
	 */
	private static $emptyTypeMapper = [
		'string' => '',
		'bool' => false,
		'int' => 0,
		'float' => 0.0,
		'array' => [],
		'null' => null,
	];

	/**
	 * @var string[]
	 */
	private $namespaceConventions;

	/**
	 * @var Container
	 */
	private $container;

	/**
	 * @param string[] $namespaceConventions
	 * @param Container $container
	 */
	public function __construct(array $namespaceConventions, Container $container)
	{
		$this->namespaceConventions = $namespaceConventions;
		$this->container = $container;
	}

	/**
	 * By given inputs or current URL route specific API endpoint and send full HTTP response.
	 *
	 * @param string $path
	 * @param mixed[]|null $params
	 * @param string|null $method
	 * @throws StructuredApiException
	 */
	public function run(string $path, ?array $params = [], ?string $method = null): void
	{
		$params = array_merge($_GET, $params ?? []);

		if (preg_match('/^api\/v([^\/]+)\/?(.*?)$/', $path, $pathParser)) {
			if (($version = (int) $pathParser[1]) < 1 || $version > 999 || !preg_match('#^[+-]?\d+$#D', $pathParser[1])) {
				StructuredApiException::invalidVersion($pathParser[1]);
			}

			$route = $this->route((string) preg_replace('/^(.*?)(\?.*|)$/', '$1', $pathParser[2]), $params);

			try {
				$response = $this->callActionMethods(
					$this->createInstance($route['class'], $params, $method = $method ? : $this->getMethod()),
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
				header('Content-Type: ' . $response->getContentType());
				echo (string) $response;
				die;
			}

			StructuredApiException::apiEndpointMustReturnSomeData($path);
		}

		StructuredApiException::invalidApiPath($path);
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

		if (strpos($route, '/') === false) { // 1. Simple match
			foreach ($this->namespaceConventions as $classFormat) {
				if (\class_exists($class = str_replace('*', Helpers::formatApiName($route), $classFormat)) === true) {
					break;
				}
			}
			$action = 'default';
		} elseif (preg_match('/^([^\/]+)\/([^\/]+)$/', $route, $routeParser)) { // 2. <endpoint>/<action>
			foreach ($this->namespaceConventions as $classFormat) {
				if (\class_exists($class = str_replace('*', Helpers::formatApiName($routeParser[1]), $classFormat)) === true) {
					break;
				}
			}
			$action = Helpers::formatApiName($routeParser[2]);
		}

		if ($class === null) {
			StructuredApiException::canNotRouteException($route, $params);
		}

		if (\class_exists($class) === false) {
			StructuredApiException::routeClassDoesNotExist((string) $class);
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
	 * @param string $method
	 * @return BaseEndpoint
	 */
	private function createInstance(string $class, array $params, string $method): BaseEndpoint
	{
		if ($method === 'POST') {
			if (\count($_POST) === 1 && preg_match('/^\{.*\}$/', $post = array_keys($_POST)[0]) && ($json = json_decode($post)) instanceof \stdClass) {
				foreach ($json as $key => $value) {
					$_POST[$key] = $value;
				}
				unset($_POST[$post]);
			} elseif (($input = (string) file_get_contents('php://input')) !== '' && $json = json_decode($input, true)) {
				foreach ($json as $key => $value) {
					$_POST[$key] = $value;
				}
			}

			$params = array_merge($_POST, $params);
		}

		return new $class($this->container, $params);
	}

	/**
	 * Call all endpoint methods in regular order and return response state.
	 *
	 * @param BaseEndpoint $endpoint
	 * @param string $action
	 * @param string $method
	 * @param mixed[] $params
	 * @return BaseResponse|null
	 * @throws RuntimeStructuredApiException
	 */
	private function callActionMethods(BaseEndpoint $endpoint, string $action, string $method, array $params): ?BaseResponse
	{
		$endpoint->startup();
		$endpoint->startupCheck();
		$response = null;

		if ($method === 'POST' && method_exists($endpoint, $postMethod = 'post' . $action)) {
			try {
				$response = $endpoint->$postMethod(array_merge($endpoint->getData(), $params));
			} catch (ThrowResponse $e) {
				$response = $e->getResponse();
			}

			if ($response === null) {
				$response = new JsonResponse(['state' => 'ok']);
			}
		}

		if ($method === 'GET' && method_exists($endpoint, $actionMethod = 'action' . $action)) {
			$args = [];
			try {
				foreach (($ref = new \ReflectionMethod($endpoint, $actionMethod))->getParameters() as $parameter) {
					if (isset($params[$pName = $parameter->getName()]) === true) {
						if ($params[$pName]) {
							$args[$pName] = $params[$pName];
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
							$actionMethod
						);
					}
				}

				try {
					$response = $ref->invokeArgs($endpoint, $args);
				} catch (ThrowResponse $e) {
					$response = $e->getResponse();
				}
			} catch (\ReflectionException $e) {
				RuntimeStructuredApiException::reflectionException($e);
			}
		}

		$endpoint->saveState();

		return $response;
	}

	/**
	 * @param BaseEndpoint $endpoint
	 * @param string $parameter
	 * @param \ReflectionType $type
	 * @return mixed|null
	 * @throws RuntimeStructuredApiException
	 */
	private function returnEmptyValue(BaseEndpoint $endpoint, string $parameter, \ReflectionType $type)
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

		return $method ? : 'GET';
	}

}
