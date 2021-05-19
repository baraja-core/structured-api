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
