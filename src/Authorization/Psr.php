<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use Illuminate\Http\Request;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class Psr
{
    public static function request(Request $request): ServerRequestInterface
    {
        $factory = new Psr17Factory;

        return (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request);
    }

    public static function newResponse(): ResponseInterface
    {
        return new PsrResponse;
    }

    public static function response(ResponseInterface $response): Response
    {
        return (new HttpFoundationFactory)->createResponse($response);
    }
}
