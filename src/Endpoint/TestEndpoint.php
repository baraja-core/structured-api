<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


/**
 * @public
 */
final class TestEndpoint extends BaseEndpoint
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
			'endpoint' => $this->getName(),
		]);
	}


	public function postCreateUser(string $username, string $password): void
	{
		if (strlen($password) < 8) {
			$this->sendError('Password must be at least 8 characters long.');
		}

		$this->sendOk([
			'username' => $username,
		]);
	}
}
