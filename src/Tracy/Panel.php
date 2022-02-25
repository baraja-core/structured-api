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
		// header
		$time = $this->getResponseTime();
		$buffer = '<h1>Structured API';
		if ($time > 0) {
			$buffer .= sprintf(' [%s ms]', number_format($time, 1, '.', ' '));
		}
		$buffer .= sprintf(' [%s]', htmlspecialchars($this->httpMethod));
		$buffer .= '</h1>';

		// container begin
		$buffer .= '<div class="tracy-inner baraja-cms"><div class="tracy-inner-container">';

		// request
		$buffer .= '<div style="font-size:13pt;color:#555">Request ';
		if ($this->httpMethod === 'GET') {
			$buffer .= sprintf('<a href="%s" target="_blank">Open in new tab</a>', sprintf('%s/%s', Url::get()->getBaseUrl(), $this->path));
		}
		$buffer .= '</div>';

		// called endpoint
		$buffer .= '<table>'
			. '<tr><th>URL</th><td><code>' . htmlspecialchars($this->path) . '</code></td></tr>';
		if ($this->endpoint !== null) {
			$buffer .= sprintf('<tr><th>Endpoint</th><td><code>%s</code></td></tr>', htmlspecialchars($this->endpoint::class));
		}
		$buffer .= '</table>';
		$buffer .= '<table>'
			. '<tr><th>Raw HTTP input</th><th>Real endpoint args</th></tr>'
			. '<tr>'
			. '<td class="structured-api__dump">' . Dumper::toHtml($this->params) . '</td>'
			. '<td class="structured-api__dump">' . ($this->args !== null ? Dumper::toHtml($this->args) : 'No data.') . '</td>'
			. '</tr>'
			. '</table>';

		// response
		if ($this->response !== null) {
			$buffer .= '<div style="font-size:13pt;color:#555;margin-top:18px;padding-top:16px;border-top:1px solid #ddd">Response</div>'
				. '<table>'
				. '<tr><th>Type</th><td><code>' . htmlspecialchars($this->response::class) . '</code></td></tr>'
				. '<tr><th>HTTP code</th><td><code>' . htmlspecialchars((string) $this->response->getHttpCode()) . '</code></td></tr>'
				. '<tr><th>Content type</th><td><code>' . htmlspecialchars($this->response->getContentType()) . '</code></td></tr>'
				. '</table>'
				. '<p>Real output:</p>'
				. Dumper::toHtml($this->response->toArray());
		} else {
			$buffer .= '<p style="text-align:center"><i style="color:red">Empty response or error.</i></p>';
		}

		// container end
		$buffer .= '</div></div>';
		$buffer .= '<style>.structured-api__dump{padding:0 !important}.structured-api__dump .tracy-dump{margin:0 !important}</style>';

		return $buffer;
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
P
