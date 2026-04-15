<?php

declare(strict_types=1);

namespace Europolis\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * JsonResponseMiddleware — adaugă automat Content-Type: application/json
 * pe toate răspunsurile API-ului.
 *
 * (Cap. 6 — Specificația API, principii generale, Etapa 1)
 */
class JsonResponseMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
