<?php

declare(strict_types=1);

namespace Baraja\StructuredApi\Tracy;


use Baraja\StructuredApi\Endpoint;
use Baraja\StructuredApi\Response;
use Baraja\Url\Url;
use Tracy\Dumper;
use Tracy\IBarPanel;

final class Panel implements IBarPanel
{
	private ?Endpoint $endpoint = null;

	/** @var array<string, mixed>|null */
	private ?array $args = null;

	private ?Response $response = null;

	private float $inputTime;

	private ?float $responseTime = null;


	/**
	 * @param mixed[] $params
	 */
	public function __construct(
		private string $path,
		private array $params,
		private string $httpMethod,
	) {
		$this->inputTime = microtime(true);
	}


	public function getTab(): string
	{
		$time = $this->getResponseTime();

		return sprintf(
			'API %s%s',
			htmlspecialchars($this->httpMethod),
			$time > 0 ? sprintf(' %s ms', number_format($time, 1, '.', ' ')) : '',
		);
	}


	public function getPanel(): string
	{
		$time = $this->getResponseTime();

		return '<h1>Structured API'
			. ($time > 0 ? sprintf(' [%s ms]', number_format($time, 1, '.', ' ')) : '')
			. sprintf(' [%s]', htmlspecialchars($this->httpMethod))
			. '</h1>'
			. '<div class="tracy-inner baraja-cms">'
			. '<div class="tracy-inner-container">'
			. '<div style="font-size:13pt;color:#555">'
			. 'Request '
			. ($this->httpMethod === 'GET'
				? '<a href="' . sprintf('%s/%s', Url::get()->getBaseUrl(), $this->path) . '" target="_blank">Open in new tab</a>'
				: '')
			. '</div>'
			. '<table>'
			. '<tr><th>URL</th><td><code>' . htmlspecialchars($this->path) . '</code></td></tr>'
			. ($this->endpoint !== null
				? '<tr><th>Endpoint</th><td><code>' . htmlspecialchars($this->endpoint::class) . '</code></td></tr>'
				: '')
			. '</table>'
			. '<table>'
			. '<tr><th>Raw input</th><th>Real args</th></tr>'
			. '<tr>'
			. '<td>' . Dumper::toHtml($this->params) . '</td>'
			. '<td>' . ($this->args !== null ? Dumper::toHtml($this->args) : 'No data.') . '</td>'
			. '</tr>'
			. '</table>'
			. ($this->response !== null
				? '<div style="font-size:13pt;color:#555;margin-top:12px">Response</div>'
				. '<table>'
				. '<tr><th>Type</th><td><code>' . htmlspecialchars($this->response::class) . '</code></td></tr>'
				. '<tr><th>HTTP code</th><td><code>' . htmlspecialchars((string) $this->response->getHttpCode()) . '</code></td></tr>'
				. '<tr><th>Content type</th><td><code>' . htmlspecialchars($this->response->getContentType()) . '</code></td></tr>'
				. '</table>'
				. '<p>Real output:</p>'
				. Dumper::toHtml($this->response->toArray())
				: '<p style="text-align:center"><i style="color:red">Empty response or error.</i></p>'
			) . '</div></div>';
	}


	public function setEndpoint(Endpoint $endpoint): void
	{
		$this->endpoint = $endpoint;
	}


	/**
	 * @param array<string, mixed> $args
	 */
	public function setArgs(array $args): void
	{
		$this->args = $args;
	}


	public function setResponse(?Response $response): void
	{
		$this->response = $response;
		$this->responseTime = microtime(true);
	}


	private function getResponseTime(): float
	{
		return $this->responseTime !== null
			? ($this->responseTime - $this->inputTime) * 1000
			: 0;
	}
}
