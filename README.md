<div align='center'>
  <picture>
    <source media='(prefers-color-scheme: dark)' srcset='https://cdn.brj.app/images/brj-logo/logo-regular.png'>
    <img src='https://cdn.brj.app/images/brj-logo/logo-dark.png' alt='BRJ logo'>
  </picture>
  <br>
  <a href="https://brj.app">BRJ organisation</a>
</div>
<hr>

# Structured REST API in PHP

![Integrity check](https://github.com/baraja-core/structured-api/workflows/Integrity%20check/badge.svg)

A powerful, type-safe REST API framework for PHP 8.1+ with full Nette Framework integration. Define structured API endpoints as PHP classes with automatic parameter validation, response serialization, and built-in permission management.

## :dart: Key Features

- **Type-safe endpoints** - Define full type-hint input parameters with automatic validation
- **Schema-based responses** - Return typed DTOs that are automatically serialized to JSON
- **Automatic endpoint discovery** - Endpoints are auto-registered via Nette DI container
- **Built-in permission system** - Role-based access control with `#[PublicEndpoint]` and `#[Role]` attributes
- **HTTP method routing** - Method names determine HTTP verb (GET, POST, PUT, DELETE)
- **Tracy debugger integration** - Visual debug panel for API request/response inspection
- **CORS handling** - Automatic Cross-Origin Resource Sharing support
- **Dependency injection** - Full DI support via constructor or `#[Inject]` attribute
- **Flash messages** - Built-in flash message system for API responses

## :building_construction: Architecture Overview

The package follows a layered architecture with clear separation of concerns:

```
┌───────────────────────────────────────────────────────────────┐
│                        HTTP Request                           │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│                         ApiManager                            │
│  ┌───────────────┐  ┌────────────────┐  ┌─────────────────┐  │
│  │  URL Router   │  │  CORS Handler  │  │   Tracy Panel   │  │
│  │ /api/v1/{ep}  │  │  (Preflight)   │  │    (Debug)      │  │
│  └───────┬───────┘  └────────────────┘  └─────────────────┘  │
│          │                                                    │
│          ▼                                                    │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                  Middleware Pipeline                   │  │
│  │  ┌───────────────────┐  ┌──────────────────────────┐  │  │
│  │  │PermissionExtension│  │ Custom MatchExtensions   │  │  │
│  │  │  (Auth & Roles)   │  │ (before/after process)   │  │  │
│  │  └───────────────────┘  └──────────────────────────┘  │  │
│  └────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│                          Endpoint                             │
│  ┌───────────────┐  ┌────────────────┐  ┌─────────────────┐  │
│  │ BaseEndpoint  │  │ Method Invoker │  │   Convention    │  │
│  │ (Your Logic)  │  │ (Param Binding)│  │  (Formatting)   │  │
│  └───────┬───────┘  └────────────────┘  └─────────────────┘  │
│          │                                                    │
│          ▼                                                    │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                   Response System                      │  │
│  │  ┌────────────┐  ┌────────────┐  ┌──────────────────┐ │  │
│  │  │DTO Objects │  │ StatusResp │  │   JsonResponse   │ │  │
│  │  │(Your Types)│  │ (Ok/Error) │  │   (Serialized)   │ │  │
│  │  └────────────┘  └────────────┘  └──────────────────┘ │  │
│  └────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│                        HTTP Response                          │
│                  (JSON with proper HTTP code)                 │
└───────────────────────────────────────────────────────────────┘
```

### Core Components

| Component | Description |
|-----------|-------------|
| **ApiManager** | Central orchestrator that handles routing, CORS, middleware execution, and response processing |
| **ApiExtension** | Nette DI extension for automatic service registration and configuration |
| **BaseEndpoint** | Abstract base class providing helper methods for sending responses |
| **Endpoint** | Interface that marks a class as an API endpoint |
| **MetaDataManager** | Discovers and registers endpoint classes using RobotLoader |
| **Convention** | Configuration entity for response formatting, date formats, and security settings |
| **PermissionExtension** | Middleware for authentication and role-based access control |
| **MatchExtension** | Interface for creating custom middleware (before/after processing hooks) |
| **Tracy Panel** | Debug panel showing request details, parameters, and response data |

## :framed_picture: Tracy Debug Panel

The package includes a comprehensive Tracy debug panel for inspecting API requests during development:

![Baraja Structured API debug Tracy panel](doc/tracy-panel-design.png)

The panel displays:
- HTTP method and request URL
- Raw HTTP input parameters
- Resolved endpoint arguments after type casting
- Response type, HTTP code, and content type
- Complete serialized response data
- Request processing time in milliseconds

## :package: Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/structured-api) and
[GitHub](https://github.com/baraja-core/structured-api).

To install, simply use the command:

```shell
$ composer require baraja-core/structured-api
```

### Requirements

- PHP 8.1 or higher
- Extensions: `json`, `mbstring`, `iconv`
- Nette Framework 3.x

### Nette Framework Integration

The package integrates automatically with Nette Framework. A model configuration can be found in the `common.neon` file inside the root of the package.

If you use [PackageRegistrator](https://github.com/baraja-core/package-manager), the extension is registered automatically. Otherwise, register the extension manually in your `config.neon`:

```neon
extensions:
    structuredApi: Baraja\StructuredApi\ApiExtension
```

### Configuration Options

```neon
structuredApi:
    skipError: false  # If true, silently logs API exceptions instead of throwing
```

## :rocket: Basic Usage

### Creating Your First Endpoint

Create a class that extends `BaseEndpoint` with the suffix `Endpoint`:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Baraja\StructuredApi\BaseEndpoint;

final class ArticleEndpoint extends BaseEndpoint
{
    public function __construct(
        private ArticleRepository $articleRepository,
    ) {
    }

    public function actionDefault(): array
    {
        return [
            'articles' => $this->articleRepository->findAll(),
        ];
    }

    public function actionDetail(int $id): ArticleResponse
    {
        $article = $this->articleRepository->find($id);

        return new ArticleResponse(
            id: $article->getId(),
            title: $article->getTitle(),
            content: $article->getContent(),
        );
    }
}
```

This endpoint will be available at:
- `GET /api/v1/article` - calls `actionDefault()`
- `GET /api/v1/article/detail?id=123` - calls `actionDetail(123)`

### Response DTO Objects

For type-safe responses, create DTO classes:

```php
<?php

declare(strict_types=1);

namespace App\Api\DTO;

final class ArticleResponse
{
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public ?\DateTimeInterface $publishedAt = null,
    ) {
    }
}
```

The DTO is automatically serialized to JSON with proper type handling:
- `DateTimeInterface` objects are formatted according to `Convention::$dateTimeFormat`
- Nested objects and arrays are recursively serialized
- Objects with `__toString()` method can be converted to strings

## :globe_with_meridians: HTTP Methods

The library supports all HTTP methods for REST APIs. The HTTP method is determined by the method name prefix:

```php
final class UserEndpoint extends BaseEndpoint
{
    // GET /api/v1/user
    public function actionDefault(): array { }

    // GET /api/v1/user (alternative)
    public function getDefault(): array { }

    // GET /api/v1/user/detail?id=1
    public function actionDetail(int $id): UserResponse { }

    // POST /api/v1/user/create
    public function postCreate(string $name, string $email): UserResponse { }

    // POST /api/v1/user/create (alias)
    public function createCreate(string $name, string $email): UserResponse { }

    // PUT /api/v1/user/update
    public function putUpdate(int $id, string $name): UserResponse { }

    // PUT /api/v1/user/update (alias)
    public function updateUpdate(int $id, string $name): UserResponse { }

    // DELETE /api/v1/user/delete
    public function deleteDelete(int $id): void { }
}
```

### Method Prefix Aliases

| Prefix | HTTP Method |
|--------|-------------|
| `action` | GET |
| `get` | GET |
| `post` | POST |
| `create` | POST |
| `put` | PUT |
| `update` | PUT |
| `delete` | DELETE |

## :white_check_mark: Parameter Validation

Method parameters are automatically validated and type-cast:

```php
final class ArticleEndpoint extends BaseEndpoint
{
    /**
     * @param string|null $locale in format "cs" or "en"
     * @param int $page real page number, 1 = first page
     * @param int $limit in interval <1, 500>
     */
    public function actionDefault(
        ?string $locale = null,
        int $page = 1,
        int $limit = 32,
        ?string $status = null,
        ?string $query = null,
        ?\DateTimeInterface $filterFrom = null,
        ?\DateTimeInterface $filterTo = null,
        ?string $sort = null,
        ?string $orderBy = null,
    ): ArticleListResponse {
        // All parameters are validated and properly typed
    }
}
```

**Validation rules:**
- Parameters with default values are **optional**
- Parameters without defaults are **required** - request fails if missing
- Type mismatches trigger automatic conversion or error response
- The special parameter `array $data` receives all raw input data

### Accessing Raw Data

For complex data structures, use the reserved `$data` parameter:

```php
public function postProcessOrder(array $data): OrderResponse
{
    // $data contains all raw POST data from the request
}
```

## :left_speech_bubble: Response Methods

`BaseEndpoint` provides several helper methods for sending responses:

### sendJson()

Send raw array data as JSON:

```php
public function actionDetail(int $id): void
{
    $this->sendJson([
        'id' => $id,
        'title' => 'My Article',
        'content' => '...',
    ]);
}
```

### sendOk()

Send success response with optional data and message:

```php
public function postCreate(string $title): void
{
    $article = $this->repository->create($title);

    $this->sendOk(
        data: ['id' => $article->getId()],
        message: 'Article created successfully',
    );
}
```

Response format:
```json
{
    "state": "ok",
    "message": "Article created successfully",
    "code": 200,
    "data": {
        "id": 123
    }
}
```

### sendError()

Send error response:

```php
public function actionDetail(int $id): void
{
    $article = $this->repository->find($id);

    if ($article === null) {
        $this->sendError(
            message: 'Article not found',
            code: 404,
            hint: 'Check if the article ID is correct',
        );
    }

    // ...
}
```

Response format:
```json
{
    "state": "error",
    "message": "Article not found",
    "code": 404,
    "hint": "Check if the article ID is correct"
}
```

### sendItems()

Send paginated list of items:

```php
public function actionDefault(int $page = 1): void
{
    $items = $this->repository->findPage($page);
    $paginator = $this->repository->getPaginator();

    $this->sendItems($items, $paginator, [
        'totalCount' => $paginator->getTotalCount(),
    ]);
}
```

### Return Statement (Recommended)

The preferred approach is returning typed objects directly:

```php
public function actionDetail(int $id): ArticleResponse
{
    $article = $this->repository->find($id);

    return new ArticleResponse(
        id: $article->getId(),
        title: $article->getTitle(),
        content: $article->getContent(),
    );
}
```

This enables:
- Static analysis and IDE support
- Automatic documentation generation
- Type safety at compile time

## :bell: Flash Messages

Add flash messages to any response:

```php
public function postUpdate(int $id, string $title): ArticleResponse
{
    $article = $this->repository->update($id, $title);

    $this->flashMessage('Article updated successfully', self::FlashMessageSuccess);
    $this->flashMessage('Remember to publish your changes', self::FlashMessageInfo);

    return new ArticleResponse($article);
}
```

Available flash message types:
- `FlashMessageSuccess` - success
- `FlashMessageInfo` - info
- `FlashMessageWarning` - warning
- `FlashMessageError` - error

Flash messages are included in the response under `flashMessages` key:

```json
{
    "id": 123,
    "title": "Updated Article",
    "flashMessages": [
        {"message": "Article updated successfully", "type": "success"},
        {"message": "Remember to publish your changes", "type": "info"}
    ]
}
```

## :lock: Permissions & Security

### Default Security Behavior

**All endpoints are private by default.** Users must be logged in to access any endpoint unless explicitly marked as public.

### Public Endpoints

Mark an entire endpoint as publicly accessible:

```php
use Baraja\StructuredApi\Attributes\PublicEndpoint;

#[PublicEndpoint]
final class ProductEndpoint extends BaseEndpoint
{
    public function actionDefault(): array
    {
        // Accessible without authentication
    }
}
```

### Role-Based Access Control

Restrict access to specific user roles:

```php
use Baraja\StructuredApi\Attributes\Role;

#[Role(roles: ['admin', 'moderator'])]
final class ArticleEndpoint extends BaseEndpoint
{
    // Only admin or moderator can access any method
}
```

Restrict specific methods:

```php
#[PublicEndpoint]
final class ArticleEndpoint extends BaseEndpoint
{
    public function actionDefault(): array
    {
        // Public access
    }

    #[Role(roles: 'admin')]
    public function actionDelete(int $id): void
    {
        // Only admin can delete
    }

    #[Role(roles: ['admin', 'editor'])]
    public function postCreate(string $title): ArticleResponse
    {
        // Admin or editor can create
    }
}
```

### Permission Check Flow

1. Check if endpoint has `#[PublicEndpoint]` attribute
2. If not public, require user to be logged in (returns 401 if not)
3. Check `#[Role]` attributes on class and method
4. If roles defined, verify user has at least one matching role (returns 403 if not)
5. If user is logged in and no roles required, allow access

### Ignoring Default Permissions

For special cases, you can disable the default permission checking:

```php
$convention = $container->getByType(Convention::class);
$convention->setIgnoreDefaultPermission(true);
```

## :gear: Convention Configuration

The `Convention` entity controls response formatting and behavior:

```php
$convention = $container->getByType(Convention::class);

// Date/time format for serialization (default: 'Y-m-d H:i:s')
$convention->setDateTimeFormat('c'); // ISO 8601

// Default HTTP codes
$convention->setDefaultErrorCode(500);
$convention->setDefaultOkCode(200);

// Remove null values from response to reduce payload size
$convention->setRewriteNullToUndefined(true);

// Keys to hide from response (sensitive data)
$convention->setKeysToHide([
    'password', 'passwd', 'pass', 'pwd',
    'creditcard', 'credit card', 'cc', 'pin',
    'secret', 'token',
]);

// Use __toString() method when serializing objects
$convention->setRewriteTooStringMethod(true);
```

## :electric_plug: Middleware Extensions

Create custom middleware by implementing `MatchExtension`:

```php
use Baraja\StructuredApi\Middleware\MatchExtension;
use Baraja\StructuredApi\Endpoint;
use Baraja\StructuredApi\Response;

final class RateLimitExtension implements MatchExtension
{
    public function beforeProcess(
        Endpoint $endpoint,
        array $params,
        string $action,
        string $method,
    ): ?Response {
        if ($this->isRateLimited()) {
            return new JsonResponse($this->convention, [
                'state' => 'error',
                'message' => 'Rate limit exceeded',
            ], 429);
        }

        return null; // Continue processing
    }

    public function afterProcess(
        Endpoint $endpoint,
        array $params,
        ?Response $response,
    ): ?Response {
        // Modify or replace response after processing
        return null; // Use original response
    }
}
```

Register the extension:

```php
$apiManager = $container->getByType(ApiManager::class);
$apiManager->addMatchExtension(new RateLimitExtension());
```

## :telephone_receiver: Programmatic API Calls

Call endpoints programmatically from PHP code:

```php
$apiManager = $container->getByType(ApiManager::class);

// Get response as array
$result = $apiManager->get('article/detail', ['id' => 123]);

// With explicit HTTP method
$result = $apiManager->get('article/create', ['title' => 'New'], 'POST');

// The path is automatically prefixed with 'api/v1/' if not present
$result = $apiManager->get('api/v1/article/detail', ['id' => 123]);
```

## :link: URL Routing

API URLs follow this pattern:

```
/api/v{version}/{endpoint}/{action}?{parameters}
```

### Examples

| URL | Endpoint Class | Method Called |
|-----|----------------|---------------|
| `/api/v1/article` | `ArticleEndpoint` | `actionDefault()` |
| `/api/v1/article/detail?id=5` | `ArticleEndpoint` | `actionDetail(5)` |
| `/api/v1/user-profile` | `UserProfileEndpoint` | `actionDefault()` |
| `/api/v1/user-profile/settings` | `UserProfileEndpoint` | `actionSettings()` |

### Naming Convention

Endpoint class names are converted to URL paths using kebab-case:
- `ArticleEndpoint` → `/api/v1/article`
- `UserProfileEndpoint` → `/api/v1/user-profile`
- `MyAwesomeApiEndpoint` → `/api/v1/my-awesome-api`

Action names also use kebab-case in URLs:
- `actionGetUserProfile()` → `/api/v1/endpoint/get-user-profile`

## :busts_in_silhouette: User Management Integration

The package integrates with [baraja-core/cas](https://github.com/baraja-core/cas) for user management:

```php
final class ProfileEndpoint extends BaseEndpoint
{
    public function actionDefault(): UserResponse
    {
        // Check if user is logged in
        if (!$this->isUserLoggedIn()) {
            $this->sendError('Not authenticated', 401);
        }

        // Get current user
        $user = $this->getUser();

        // Get user identity entity
        $identity = $this->getUserEntity();

        return new UserResponse($identity);
    }
}
```

## :link: Link Generation

Generate application links from endpoints:

```php
final class ArticleEndpoint extends BaseEndpoint
{
    public function actionDetail(int $id): ArticleResponse
    {
        $article = $this->repository->find($id);

        return new ArticleResponse(
            id: $article->getId(),
            title: $article->getTitle(),
            editUrl: $this->link('Admin:Article:edit', ['id' => $id]),
            viewUrl: $this->linkSafe('Front:Article:detail', ['slug' => $article->getSlug()]),
        );
    }
}
```

- `link()` - Generates link, throws exception if route doesn't exist
- `linkSafe()` - Returns `null` if route doesn't exist

## :key: API Token Authentication

For secure communication with external services or partners, use the official token authorizer extension:

[structured-api-token-authorizer](https://github.com/baraja-core/structured-api-token-authorizator)

## :book: Automatic Documentation

Generate API documentation automatically from your endpoint code:

[Structured API Documentation](https://github.com/baraja-core/structured-api-doc)

This extension scans your endpoints and generates documentation based on:
- Method signatures and parameters
- PHPDoc annotations
- Return type DTOs
- Permission attributes

## :test_tube: Built-in Test Endpoints

The package includes two test endpoints for verification:

### Ping Endpoint

```
GET /api/v1/ping
```

Response:
```json
{
    "result": "PONG",
    "ip": "127.0.0.1",
    "datetime": "2024-01-15 10:30:00"
}
```

### Test Endpoint

```
GET /api/v1/test?hello=World
```

Response:
```json
{
    "name": "Test API endpoint",
    "hello": "World",
    "endpoint": "Test"
}
```

## :warning: Error Handling

The API automatically handles errors and returns appropriate HTTP codes:

| HTTP Code | Situation |
|-----------|-----------|
| 200 | Successful request |
| 400 | Bad request (validation error) |
| 401 | Unauthorized (not logged in) |
| 403 | Forbidden (missing role) |
| 404 | Endpoint or action not found |
| 500 | Internal server error |

Error response format:
```json
{
    "state": "error",
    "message": "Human readable error message",
    "code": 500
}
```

In debug mode (Tracy enabled), the `message` field contains the actual exception message. In production, a generic error message is shown for security.

## :arrows_counterclockwise: CORS Support

Cross-Origin Resource Sharing is automatically handled:

- `Access-Control-Allow-Origin` - Set to request origin
- `Access-Control-Allow-Credentials` - Enabled
- `Access-Control-Max-Age` - 86400 seconds (1 day)
- OPTIONS preflight requests are handled automatically

## :man_technologist: Author

**Jan Barasek** - [https://baraja.cz](https://baraja.cz)

## :page_facing_up: License

`baraja-core/structured-api` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/structured-api/blob/master/LICENSE) file for more details.
