<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whilesmart\EloquentClientCredentials\Authorization\Connections;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\RefreshToken;

class ConnectionController
{
    public function index(Request $request): JsonResponse
    {
        $connections = $this->tokens($request)
            ->where(function ($query) {
                $query->valid()->orWhereHas('refreshTokens', fn ($refresh) => $refresh
                    ->where('revoked', false)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())));
            })
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->map(function ($tokens) {
                $client = $tokens->first()->client;

                return [
                    'client' => [
                        'id' => (string) $client->getKey(),
                        'name' => $client->name,
                    ],
                    'scopes' => $tokens->pluck('scopes')->flatten()->unique()->values(),
                    'connected_at' => $tokens->min('created_at')?->toAtomString(),
                    'last_used_at' => $client->last_used_at?->toAtomString(),
                ];
            })
            ->values();

        return response()->json(['data' => $connections]);
    }

    public function destroy(Request $request, string $client): JsonResponse
    {
        $model = config('client-credentials.default_model');

        if (! $model::find($client)) {
            return response()->json(['message' => __('client-credentials::messages.connection_not_found')], 404);
        }

        $user = $request->user();

        $revoked = Connections::exclusively($client, function () use ($request, $user, $client) {
            Connections::advance($client, $user->getMorphClass(), (string) $user->getAuthIdentifier());

            $tokens = $this->tokens($request)->where('client_id', $client);
            RefreshToken::whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);

            return $tokens->update(['revoked' => true]);
        });

        if ($revoked === 0) {
            return response()->json(['message' => __('client-credentials::messages.connection_not_found')], 404);
        }

        return response()->json(['message' => __('client-credentials::messages.connection_revoked')]);
    }

    protected function tokens(Request $request)
    {
        $user = $request->user();

        return AccessToken::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', (string) $user->getAuthIdentifier());
    }
}
