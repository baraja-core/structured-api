<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Nette\Utils\DateTime;

final class PingEndpoint extends BaseEndpoint
{
	public function actionDefault(): void
	{
		$this->sendJson([
			'result' => 'PONG',
			'ip' => $this->getIp(),
			'datetime' => DateTime::from('now'),
		]);
	}


	private function getIp(): string
	{
		$ip = null;
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
		}

		return is_string($ip) ? $ip : '127.0.0.1';
	}
}
