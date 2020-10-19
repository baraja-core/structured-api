<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\RuntimeInvokeException;
use Baraja\ServiceMethodInvoker;
use Baraja\StructuredApi\Entity\Convention;
use Nette\DI\Container;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Security\User;
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

	/** @var User */
	private $user;

	/** @var Convention */
	private $convention;

	/** @var string[] (endpointPath => endpointType) */
	private $endpoints = [];


	/**
	 * @param string[] $endpoints
	 */
	public function __construct(array $endpoints, Container $container, Request $request, Response $response, User $user)
	{
		$this->endpoints = $endpoints;
		$this->container = $container;
		$this->request = $request;
		$this->response = $response;
		$this->user = $user;
		$this->convention = new Convention;
	}


	/**
	 * By given inputs or current URL route specific API endpoint and send full HTTP response.
	 *
	 * @param mixed[]|null $params
	 * @throws StructuredApiException
	 */
	public function run(string $path, ?array $params = [], ?string $method = null, bool $throw = false): void
	{
		$this->checkFirewall();
		$params = array_merge($this->safeGetParams($path), $this->getBodyParams($method = $method ?: $this->getHttpMethod()), $params ?? []);

		if (preg_match('/^api\/v(?<v>\d{1,3}(?:\.\d{1,3})?)\/(?<path>.*?)$/', $path, $pathParser)) {
			$route = $this->route((string) preg_replace('/^(.*?)(\?.*|)$/', '$1', $pathParser['path']), $pathParser['v'], $params);

			try {
				$response = $this->invokeActionMethod(
					$this->getEndpointService($route['class'], $params),
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

				$response = new JsonResponse($this->convention, [
					'state' => 'error',
					'message' => $isDebugger && Debugger::isEnabled() === true ? $e->getMessage() : null,
					'code' => $code = (($code = $e->getCode()) === 0 ? 500 : $code),
				], $code);
			}

			if ($response !== null) {
				if ($throw === true) {
					throw new ThrowResponse($response);
				}
				if ($this->response->isSent() === false) {
					$this->response->setCode($response->getHttpCode());
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
	}


	/**
	 * @param mixed[]|null $params
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


	public function getConvention(): Convention
	{
		return $this->convention;
	}


	/**
	 * @return string[]
	 */
	public function getEndpoints(): array
	{
		return $this->endpoints;
	}


	/**
	 * Create new API endpoint instance with all injected dependencies.
	 *
	 * @internal
	 * @param mixed[] $params
	 * @return Endpoint
	 */
	public function getEndpointService(string $className, array $params): Endpoint
	{
		/** @var Endpoint $endpoint */
		$endpoint = $this->container->getByType($className);
		$endpoint->setConvention($this->convention);
		$endpoint->setData($params);

		return $endpoint;
	}


	/**
	 * Safe method for get parameters from query. This helper is for CLI mode and broken Ngnix mod rewriting.
	 *
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
	 * @param mixed[] $params
	 * @param string $version in format /\d{1,3}(?:\.\d{1,3})?/
	 * @return string[]
	 * @throws StructuredApiException
	 */
	private function route(string $route, string $version, array $params): array
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
	 * Call all endpoint methods in regular order and return response state.
	 *
	 * @param mixed[] $params
	 * @return BaseResponse|null
	 * @throws RuntimeStructuredApiException
	 */
	private function invokeActionMethod(Endpoint $endpoint, string $action, string $method, array $params): ?BaseResponse
	{
		if (($methodName = $this->getActionMethodName($endpoint, $method, $action)) === null) {
			return new JsonResponse($this->convention, [
				'state' => 'error',
				'message' => 'Method for action "' . $action . '" and HTTP method "' . $method . '" is not implemented.',
			], 404);
		}
		try {
			if ($this->checkPermission($endpoint, $methodName) === false) { // Forbidden or permission denied
				return new JsonResponse($this->convention, [
					'state' => 'error',
					'message' => 'You do not have permission to perform this action.',
				], 403);
			}
		} catch (\InvalidArgumentException $e) { // Unauthorized or internal error
			return new JsonResponse($this->convention, [
				'state' => 'error',
				'message' => $e->getMessage(),
			], 401);
		}

		$endpoint->startup();
		$endpoint->startupCheck();
		$ref = null;
		$response = null;

		try {
			$response = (new ServiceMethodInvoker)->invoke($endpoint, $methodName, $params, true);
		} catch (ThrowResponse $e) {
			$response = $e->getResponse();
		} catch (RuntimeInvokeException $e) {
			throw new RuntimeStructuredApiException($e->getMessage(), $e->getCode(), $e);
		}
		if ($method !== 'GET' && $response === null) {
			$response = new JsonResponse($this->convention, ['state' => 'ok']);
		}

		$endpoint->saveState();

		return $response;
	}


	private function getHttpMethod(): string
	{
		if (($method = $_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
			&& preg_match('#^[A-Z]+$#D', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '')
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		return $method ?: 'GET';
	}


	/**
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


	private function checkPermission(Endpoint $endpoint, string $methodName): bool
	{
		try {
			$docComment = trim((string) (new \ReflectionClass($endpoint))->getDocComment());
			$public = (bool) preg_match('/@public(?:$|\s|\n)/', $docComment);
			if (($docComment === '' || $public === false) && $this->user->isLoggedIn() === false) {
				throw new \InvalidArgumentException('This API endpoint is private. You must log in to use.');
			}
			foreach (Helpers::parseRolesFromComment($docComment) as $role) {
				if ($this->user->isInRole($role) === true) {
					return true;
				}
			}
		} catch (\ReflectionException $e) {
			throw new \InvalidArgumentException('Endpoint "' . \get_class($endpoint) . '" can not be reflected: ' . $e->getMessage(), $e->getCode(), $e);
		}
		try {
			$ref = new \ReflectionMethod($endpoint, $methodName);
		} catch (\ReflectionException $e) {
			throw new \InvalidArgumentException('Method "' . $methodName . '" can not be reflected: ' . $e->getMessage(), $e->getCode(), $e);
		}
		if (($roles = Helpers::parseRolesFromComment((string) $ref->getDocComment())) !== []) { // roles as required, user must be logged in
			foreach ($roles as $role) {
				if ($this->user->isInRole($role) === true) {
					return true;
				}
			}

			return false;
		}
		if (($public ?? false) === false && $this->user->isLoggedIn() === true) { // private endpoint, but user is logged in
			return true;
		}

		return $public ?? false;
	}


	private function checkFirewall(): void
	{
		$match = strpos($userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '', 'CloudFlare-AlwaysOnline') !== false;

		if ($match === true) {
			header('HTTP/1.0 403 Forbidden');
			echo '<title>Access denied | API endpoint</title>';
			echo '<h1>Access denied</h1>';
			echo '<p>API endpoint crawling is disabled for robots.</p>';
			echo '<p><b>Information for developers:</b> Endpoint API indexing is disabled for privacy reasons. At the same time, robots can crawl a disproportionate amount of data, copying your valuable data.';
			die;
		}
	}
}
