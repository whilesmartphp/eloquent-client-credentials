<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization\AccessTokenController;
use Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization\AuthorizationRequestController;
use Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization\AuthorizeController;
use Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization\ConnectionController;
use Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization\MetadataController;
use Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization\RegistrationController;

$config = config('client-credentials.authorization_code');

Route::get('.well-known/oauth-authorization-server', [MetadataController::class, 'authorizationServer'])
    ->name('client-credentials.authorization.metadata');
Route::get('.well-known/oauth-authorization-server/{path}', [MetadataController::class, 'authorizationServer'])
    ->where('path', '.*');
Route::get('.well-known/jwks.json', [MetadataController::class, 'jwks'])
    ->name('client-credentials.authorization.jwks');

if ($config['protected_resource_metadata'] ?? false) {
    Route::get('.well-known/oauth-protected-resource', [MetadataController::class, 'protectedResource'])
        ->name('client-credentials.authorization.protected-resource');
    // laravel/mcp adds the WWW-Authenticate discovery link to a 401 when a route has this name.
    Route::get('.well-known/oauth-protected-resource/{path}', [MetadataController::class, 'protectedResource'])
        ->where('path', '.*')
        ->name('mcp.oauth.protected-resource.nested');
}

Route::prefix($config['route_prefix'] ?? 'oauth')->group(function () use ($config) {
    Route::get('authorize', AuthorizeController::class)
        ->name('client-credentials.authorization.authorize');
    Route::post('token', AccessTokenController::class)
        ->middleware('throttle:'.($config['token_throttle'] ?? '60,1'))
        ->name('client-credentials.authorization.token');

    if ($config['registration']['enabled'] ?? false) {
        Route::post('register', RegistrationController::class)
            ->middleware('throttle:'.($config['registration']['throttle'] ?? '10,1'))
            ->name('client-credentials.authorization.register');
    }
});

Route::prefix($config['consent_prefix'] ?? 'api/oauth')
    ->middleware($config['consent_middleware'] ?? ['api', 'auth:sanctum'])
    ->group(function () {
        Route::get('authorization-requests/{id}', [AuthorizationRequestController::class, 'show'])
            ->name('client-credentials.authorization.requests.show');
        Route::post('authorization-requests/{id}/approve', [AuthorizationRequestController::class, 'approve'])
            ->name('client-credentials.authorization.requests.approve');
        Route::post('authorization-requests/{id}/deny', [AuthorizationRequestController::class, 'deny'])
            ->name('client-credentials.authorization.requests.deny');
        Route::get('connections', [ConnectionController::class, 'index'])
            ->name('client-credentials.authorization.connections.index');
        Route::delete('connections/{client}', [ConnectionController::class, 'destroy'])
            ->name('client-credentials.authorization.connections.destroy');
    });
