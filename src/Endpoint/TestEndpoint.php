<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\Endpoint\DTO\CreateUserResponse;
use Baraja\StructuredApi\Endpoint\DTO\TestResponse;

#[PublicEndpoint]
final class TestEndpoint extends BaseEndpoint
{
	/**
	 * This is test API endpoint as demonstration of inner logic.
	 *
	 * @param string $hello some user-defined parameter.
	 */
	public function actionDefault(string $hello = 'world'): TestResponse
	{
		return new TestResponse(
			name: 'Test API endpoint',
			hello: $hello,
			endpoint: 'Test',
		);
	}


	public function postCreateUser(string $username, string $password): CreateUserResponse
	{
		if (mb_strlen($password) < 8) {
			$this->sendError('Password must be at least 8 characters long.');
		}

		return new CreateUserResponse(
			username: $username,
		);
	}
}
