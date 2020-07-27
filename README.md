Structured REST API in PHP
==========================

![Integrity check](https://github.com/baraja-core/structured-api/workflows/Integrity%20check/badge.svg)

Full compatible smart structured API defined by schema.

- Define full type-hint input parameters,
- Validate returned data by schema,
- Full compatible with Nette framework,
- Inject dependencies by `@inject` annotation in public property.

📦 Installation & Basic Usage
-----------------------------

This package can be installed using [PackageRegistrator](https://github.com/baraja-core/package-manager) which is also part of the Baraja [Sandbox](https://github.com/baraja-core/sandbox). If you are not using it, you have to install the package manually following this guide.

A model configuration can be found in the `common.neon` file inside the root of the package.

To manually install the package call Composer and execute the following command:

```shell
$ composer require baraja-core/structured-api
```

🛠️ API endpoint
---------------

API endpoint is simple class with action methods and dependencies. For best comfort please use your custom BaseEndpoint with declaring all required dependencies.

Simple example:

```php
<?php

declare(strict_types=1);

namespace App\Model;


final class MyAwesomeEndpoint extends BaseEndpoint
{

	/**
	 * This is test API endpoint as demonstration of inner logic.
	 *
	 * @param string $hello some user-defined parameter.
	 */
	public function actionDefault(string $hello = 'world'): void
	{
		$this->sendJson([
			'name' => 'Test API endpoint',
			'hello' => $hello,
		]);
	}

	/**
	 * @param mixed[] $data
	 */
	public function postCreateUser(array $data): void
	{
		$this->sendJson([
			'state' => 'ok',
			'data' => $data,
		]);
	}
}
```

Method `actionDefault` is used for request in format `/api/v1/test` with query parameter `?hello=...`.

Method `postCreateUser` will be called by POST request with all data.

🔒 Permissions
--------------

> 🚩**Warning:** If you do not set the rights at all, by default all endpoints are private and you must log in to call them! 👮

All API requests are validated at runtime. If you want to allow all users access to your endpoints, please add the `@public` annotation to main class doc comment.

For example (this endpoint will be public):

```php
/**
 * @public
 */
final class ProductEndpoint extends BaseEndpoint
{
}
```

To restrict rights, define an `@role` annotation over a class or method.

For example (only administrators and moderators can call this endpoint):

```php
/**
 * @role admin, moderator
 */
final class ArticleEndpoint extends BaseEndpoint
{
}
```

Rights settings can also be combined. For example, in a public endpoint, restrict rights to a specific method only:

```php
/**
 * @public
 */
final class SitemapEndpoint extends BaseEndpoint
{

   /**
    * @role admin
    */
   public function actionClearCache(): void
   {
      // your secured implementation
   }
}
```

🗺️ Project endpoint documentation
---------------------------------

When developing a real application, you will often need to pass work between the backend and the frontend.

To describe all endpoints, the package offers an optional extension that generates documentation automatically based on real code scanning.

Try the [Structured API Documentation](https://github.com/baraja-core/structured-api-doc).

📄 License
-----------

`baraja-core/structured-api` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/structured-api/blob/master/LICENSE) file for more details.
