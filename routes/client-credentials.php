<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\EloquentClientCredentials\Http\Controllers\ClientController;
use Whilesmart\EloquentClientCredentials\Http\Controllers\TokenController;

Route::post('oauth/token', [TokenController::class, 'issue'])->name('client-credentials.token.issue');
Route::post('oauth/revoke', [TokenController::class, 'revoke'])->name('client-credentials.token.revoke');

if (config('client-credentials.routes.client_routes', false)) {
    Route::get('clients', [ClientController::class, 'index'])->name('client-credentials.clients.index');
    Route::post('clients', [ClientController::class, 'store'])->name('client-credentials.clients.store');
    Route::get('clients/{slug}', [ClientController::class, 'show'])->name('client-credentials.clients.show');
    Route::put('clients/{slug}', [ClientController::class, 'update'])->name('client-credentials.clients.update');
    Route::delete('clients/{slug}', [ClientController::class, 'destroy'])->name('client-credentials.clients.destroy');
    Route::post('clients/{slug}/regenerate-secret', [ClientController::class, 'regenerateSecret'])->name('client-credentials.clients.regenerate-secret');
}
