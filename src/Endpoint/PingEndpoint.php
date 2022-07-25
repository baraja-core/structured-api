<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\Endpoint\DTO\PingResponse;

#[PublicEndpoint]
final class PingEndpoint extends BaseEndpoint
{
	public function actionDefault(): PingResponse
	{
		return new PingResponse(
			result: 'PONG',
			ip: $this->getIp(),
			datetime: new \DateTimeImmutable('now'),
		);
	}


	private function getIp(): string
	{
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) { // Cloudflare support
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} elseif (isset($_SERVER['REMOTE_ADDR']) === true) {
			$ip = $_SERVER['REMOTE_ADDR'];
			if ($ip === '127.0.0.1') {
				if (isset($_SERVER['HTTP_X_REAL_IP'])) {
					$ip = $_SERVER['HTTP_X_REAL_IP'];
				} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
				}
			}
		} else {
			$ip = '127.0.0.1';
		}
		if (in_array($ip, ['::1', '0.0.0.0', 'localhost'], true)) {
			$ip = '127.0.0.1';
		}
		$filter = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
		if ($filter === false) {
			$ip = '127.0.0.1';
		}

		return $ip;
	}
}
