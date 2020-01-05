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
			'endpoint' => $this->getName(),
		]);
	}

	/**
	 * @param mixed[] $data
	 */
	public function postCreateUser(array $data): void
	{
		$this->sendOk($data);
	}

}