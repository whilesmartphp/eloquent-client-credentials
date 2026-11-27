<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use Psr\Http\Message\ResponseInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AccessTokenEntity;

class IssuerIdentification
{
    public static function apply(ResponseInterface $response): ResponseInterface
    {
        $location = $response->getHeaderLine('Location');

        if ($location === '') {
            return $response;
        }

        $separator = str_contains($location, '?') ? '&' : '?';

        return $response->withHeader('Location', $location.$separator.http_build_query(['iss' => AccessTokenEntity::issuer()]));
    }
}
