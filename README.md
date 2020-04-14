Structured REST API in PHP
==========================

![Integrity check](https://github.com/baraja-core/structured-api/workflows/Integrity%20check/badge.svg)

Full compatible smart structured API defined by schema.

- Define full type-hint input parameters,
- Validate returned data by schema,
- Full compatible with Nette framework,
- Inject dependencies by `@inject` annotation in public property.

How to install
--------------

This package can be installed using [PackageRegistrator](https://github.com/baraja-core/package-manager) which is also part of the Baraja [Sandbox](https://github.com/baraja-core/sandbox). If you are not using it, you have to install the package manually following this guide.

A model configuration can be found in the `common.neon` file inside the root of the package.

To manually install the package call Composer and execute the following command:

```shell
composer require baraja-core/structured-api
```

API endpoint
------------

API endpoint is simple class with action methods and dependencies. For best comfort please use your custom BaseEndpoint with declaring all required dependencies.

Simple example:

```php
<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


class TestEndpoint extends BaseEndpoint
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
