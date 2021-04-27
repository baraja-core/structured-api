<?php

declare(strict_types=1);

namespace Baraja\StructuredApi;


class RedirectResponse extends BaseResponse
{
	public function getUrl(): string
	{
		return $this->haystack['url'] ?? throw new \LogicException('URL does not exist. Did you call redirect() method first?');
	}
}
