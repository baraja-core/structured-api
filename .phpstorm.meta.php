<?php

declare(strict_types=1);

namespace PHPSTORM_META;

exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendJson());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendOk());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendSuccess());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendError());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendItems());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::redirect());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::redirectUrl());

expectedArguments(
	\Baraja\StructuredApi\BaseEndpoint::flashMessage(),
	1,
	\Baraja\StructuredApi\BaseEndpoint::FLASH_MESSAGE_SUCCESS,
	\Baraja\StructuredApi\BaseEndpoint::FLASH_MESSAGE_INFO,
	\Baraja\StructuredApi\BaseEndpoint::FLASH_MESSAGE_WARNING,
	\Baraja\StructuredApi\BaseEndpoint::FLASH_MESSAGE_ERROR,
);
