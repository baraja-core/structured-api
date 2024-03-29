<?php

declare(strict_types=1);

namespace PHPSTORM_META;

exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendJson());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendOk());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendSuccess());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendError());
exitPoint(\Baraja\StructuredApi\BaseEndpoint::sendItems());
exitPoint(\Baraja\StructuredApi\ThrowStatusResponse::invoke());
exitPoint(\Baraja\StructuredApi\Response\Status\StatusResponse::invoke());
exitPoint(\Baraja\StructuredApi\Response\Status\ErrorResponse::invoke());
exitPoint(\Baraja\StructuredApi\Response\Status\OkResponse::invoke());
exitPoint(\Baraja\StructuredApi\Response\Status\SuccessResponse::invoke());

expectedArguments(
	\Baraja\StructuredApi\BaseEndpoint::flashMessage(),
	1,
	\Baraja\StructuredApi\BaseEndpoint::FlashMessageSuccess,
	\Baraja\StructuredApi\BaseEndpoint::FlashMessageInfo,
	\Baraja\StructuredApi\BaseEndpoint::FlashMessageWarning,
	\Baraja\StructuredApi\BaseEndpoint::FlashMessageError,
);
